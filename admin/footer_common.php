<?php
declare(strict_types=1);

function ensure_footer_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS footer_sections (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(120) NOT NULL,
            slug VARCHAR(120) NOT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_footer_sections_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS footer_links (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            section_id INT UNSIGNED NOT NULL,
            label VARCHAR(120) NOT NULL,
            url VARCHAR(255) NOT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_footer_links_section (section_id),
            CONSTRAINT fk_footer_links_section FOREIGN KEY (section_id)
                REFERENCES footer_sections (id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    foreach ([
        'footer_sections' => 'title_en',
        'footer_links' => 'label_en',
    ] as $table => $column) {
        $columnExists = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name'
        );
        $columnExists->execute(['table_name' => $table, 'column_name' => $column]);
        if ((int) $columnExists->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` VARCHAR(120) NOT NULL DEFAULT ''");
        }
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS footer_settings (
            id TINYINT UNSIGNED NOT NULL,
            logo_path VARCHAR(255) NOT NULL DEFAULT "img/images.png",
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $pdo->exec(
        'INSERT IGNORE INTO footer_settings (id, logo_path) VALUES (1, "img/images.png")'
    );
}

function footer_public_data(PDO $pdo): array
{
    $sections = $pdo->query(
        'SELECT id, title, title_en FROM footer_sections
         WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
    )->fetchAll();
    $linksBySection = [];
    $links = $pdo->query(
        'SELECT section_id, label, label_en, url FROM footer_links
         WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
    )->fetchAll();

    foreach ($links as $link) {
        $linksBySection[(int) $link['section_id']][] = [
            'label_th' => (string) $link['label'],
            'label_en' => (string) $link['label_en'],
            'url' => (string) $link['url'],
        ];
    }

    $result = [];
    foreach ($sections as $section) {
        $result[] = [
            'title_th' => (string) $section['title'],
            'title_en' => (string) $section['title_en'],
            'links' => $linksBySection[(int) $section['id']] ?? [],
        ];
    }

    $logoPath = (string) $pdo->query(
        'SELECT logo_path FROM footer_settings WHERE id = 1'
    )->fetchColumn();

    return [
        'sections' => $result,
        'logo_path' => $logoPath !== '' ? $logoPath : 'img/images.png',
    ];
}

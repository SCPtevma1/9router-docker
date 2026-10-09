<?php
declare(strict_types=1);

function site_settings_defaults(): array
{
    return [
        'site_name' => 'มหาวิทยาลัยตัวอย่าง',
        'contact_email' => 'info@example-university.ac.th',
        'contact_phone' => '0-4422-0000',
        'registration_student' => '1',
        'registration_personnel' => '1',
        'registration_alumni' => '1',
    ];
}

function site_settings(): array 
{
    static $settings = null;
    if (is_array($settings)) {
        return $settings;
    }

    $defaults = site_settings_defaults();
    $pdo = db();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
            setting_value VARCHAR(255) NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMPgo
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $insertDefault = $pdo->prepare(
        'INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES (:setting_key, :setting_value)'
    );
    foreach ($defaults as $key => $value) {
        $insertDefault->execute(['setting_key' => $key, 'setting_value' => $value]);
    }

    $settings = $defaults;
    foreach ($pdo->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll() as $row) {
        if (array_key_exists($row['setting_key'], $defaults)) {
            $settings[$row['setting_key']] = (string) $row['setting_value'];
        }
    }

    return $settings;
}

function save_site_settings(array $values): void
{
    $allowedKeys = array_keys(site_settings_defaults());
    $pdo = db();
    $statement = $pdo->prepare(
        'INSERT INTO site_settings (setting_key, setting_value)
        VALUES (:setting_key, :setting_value)
        ON DUPLICATE KEY UPDATE setting_value = :updated_value'
    );

    $pdo->beginTransaction();
    try {
        foreach ($allowedKeys as $key) {
            $value = (string) ($values[$key] ?? site_settings_defaults()[$key]);
            $statement->execute([
                'setting_key' => $key,
                'setting_value' => $value,
                'updated_value' => $value,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
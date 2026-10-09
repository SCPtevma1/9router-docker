CREATE TABLE IF NOT EXISTS personnel_profile_images (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    personnel_profile_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_personnel_profile_images_owner (personnel_profile_id, display_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_images (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    content_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_content_images_owner (content_id, display_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

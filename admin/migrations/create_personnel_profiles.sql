CREATE TABLE IF NOT EXISTS personnel_profiles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    prefix VARCHAR(50) NOT NULL DEFAULT '',
    fullname VARCHAR(255) NOT NULL,
    position VARCHAR(150) NOT NULL,
    department VARCHAR(150) NOT NULL,
    email VARCHAR(190) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    image VARCHAR(255) NOT NULL DEFAULT '',
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_personnel_profiles_public (status, display_order, fullname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

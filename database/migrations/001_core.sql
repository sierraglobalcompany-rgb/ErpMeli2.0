CREATE TABLE IF NOT EXISTS companies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uq_companies_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(191) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_users (
    company_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role VARCHAR(32) NOT NULL DEFAULT 'member',
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (company_id, user_id),
    KEY idx_company_users_user (user_id, company_id),
    CONSTRAINT fk_company_users_company FOREIGN KEY (company_id) REFERENCES companies(id),
    CONSTRAINT fk_company_users_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_settings (
    id TINYINT UNSIGNED NOT NULL,
    automation_enabled TINYINT NOT NULL DEFAULT 0,
    meli_writes_enabled TINYINT NOT NULL DEFAULT 0,
    debug_enabled TINYINT NOT NULL DEFAULT 0,
    debug_retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 7,
    debug_max_mb INT UNSIGNED NOT NULL DEFAULT 100,
    updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    updated_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_system_settings_user FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings
    (id, automation_enabled, meli_writes_enabled, debug_enabled, debug_retention_days, debug_max_mb)
VALUES (1, 0, 0, 0, 7, 100)
ON DUPLICATE KEY UPDATE id = VALUES(id);

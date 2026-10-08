CREATE TABLE IF NOT EXISTS work_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    company_id BIGINT UNSIGNED NULL,
    account_id BIGINT UNSIGNED NULL,
    scope_key VARCHAR(191) NOT NULL,

    type VARCHAR(80) NOT NULL,
    resource_key VARCHAR(191) NULL,
    dedupe_key VARCHAR(64) NOT NULL,

    payload_json JSON NULL,

    status ENUM('pending', 'running', 'done', 'failed') NOT NULL DEFAULT 'pending',
    active_dedupe_key VARCHAR(64)
        AS (CASE WHEN status IN ('pending', 'running') THEN dedupe_key ELSE NULL END) PERSISTENT,

    available_at DATETIME(6) NOT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    claim_token CHAR(32) NULL,
    claimed_at DATETIME(6) NULL,

    last_error_code VARCHAR(80) NULL,
    last_error_safe VARCHAR(500) NULL,

    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    finished_at DATETIME(6) NULL,

    PRIMARY KEY (id),
    KEY idx_work_items_eligible (status, available_at, id),
    UNIQUE KEY uq_work_items_active (scope_key, type, active_dedupe_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

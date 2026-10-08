CREATE TABLE meli_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    external_user_id VARCHAR(32) NOT NULL,
    site_id VARCHAR(8) NOT NULL,
    nickname VARCHAR(120) NULL,
    status ENUM('connected', 'attention', 'reauth_required', 'disabled') NOT NULL DEFAULT 'connected',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_meli_accounts_company_user (company_id, external_user_id),
    KEY idx_meli_accounts_company_status (company_id, status, id),
    CONSTRAINT fk_meli_accounts_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE meli_tokens (
    account_id BIGINT UNSIGNED NOT NULL,
    access_token_cipher BLOB NOT NULL,
    refresh_token_cipher BLOB NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    refresh_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (account_id),
    CONSTRAINT fk_meli_tokens_account
        FOREIGN KEY (account_id) REFERENCES meli_accounts(id)
        ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE meli_cooldowns (
    cooldown_key VARCHAR(191) NOT NULL,
    blocked_until DATETIME(6) NOT NULL,
    consecutive_429 SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (cooldown_key),
    KEY idx_meli_cooldowns_blocked_until (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_usage_daily (
    usage_date DATE NOT NULL,
    scope_key VARCHAR(191) NOT NULL,
    operation_key VARCHAR(80) NOT NULL,
    requests BIGINT UNSIGNED NOT NULL DEFAULT 0,
    resources BIGINT UNSIGNED NOT NULL DEFAULT 0,
    successes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    client_errors BIGINT UNSIGNED NOT NULL DEFAULT 0,
    server_errors BIGINT UNSIGNED NOT NULL DEFAULT 0,
    rate_limited BIGINT UNSIGNED NOT NULL DEFAULT 0,
    duration_ms_sum BIGINT UNSIGNED NOT NULL DEFAULT 0,
    duration_ms_max INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (usage_date, scope_key, operation_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

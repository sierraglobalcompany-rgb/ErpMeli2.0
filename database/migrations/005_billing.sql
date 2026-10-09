CREATE TABLE billing_periods (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    period_key DATE NOT NULL,
    document_type VARCHAR(16) NOT NULL,
    cursor_last_id VARCHAR(40) NOT NULL DEFAULT '0',
    sync_state VARCHAR(16) NOT NULL DEFAULT 'pending',
    partial_flag TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    last_synced_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_billing_periods_scope (company_id, account_id, period_key, document_type),
    KEY idx_billing_periods_sync (sync_state, partial_flag, updated_at, id),
    CONSTRAINT fk_billing_periods_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_billing_periods_company_account
        FOREIGN KEY (company_id, account_id) REFERENCES meli_accounts(company_id, id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE billing_details (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    billing_period_id BIGINT UNSIGNED NOT NULL,
    external_detail_id VARCHAR(40) NOT NULL,
    associated_detail_id VARCHAR(40) NULL,
    detail_type VARCHAR(16) NOT NULL,
    detail_sub_type VARCHAR(40) NULL,
    detail_amount DECIMAL(18,4) NOT NULL,
    currency_id VARCHAR(8) NULL,
    document_id VARCHAR(40) NULL,
    marketplace VARCHAR(40) NULL,
    remote_created_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_billing_details_period_detail (billing_period_id, external_detail_id),
    KEY idx_billing_details_type (billing_period_id, detail_type, detail_sub_type, id),
    CONSTRAINT fk_billing_details_period
        FOREIGN KEY (billing_period_id) REFERENCES billing_periods(id)
        ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

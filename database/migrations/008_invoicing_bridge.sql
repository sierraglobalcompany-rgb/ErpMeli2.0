CREATE TABLE invoicing_service_keys (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    key_hash CHAR(64) NOT NULL,
    scopes VARCHAR(64) NOT NULL,
    status ENUM('active', 'revoked') NOT NULL DEFAULT 'active',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    revoked_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_invoicing_service_keys_hash (key_hash),
    KEY idx_invoicing_service_keys_company_status (company_id, status, id),
    CONSTRAINT fk_invoicing_service_keys_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoicing_artifacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    source_order_id BIGINT UNSIGNED NOT NULL,
    invoice_number VARCHAR(80) NOT NULL,
    issued_at DATETIME(6) NOT NULL,
    currency VARCHAR(8) NOT NULL,
    exact_total DECIMAL(18,4) NOT NULL,
    channel VARCHAR(32) NOT NULL DEFAULT 'WINDOWS_BRIDGE',
    pdf_storage_ref VARCHAR(255) NOT NULL,
    pdf_sha256 CHAR(64) NOT NULL,
    xml_storage_ref VARCHAR(255) NULL,
    xml_sha256 CHAR(64) NULL,
    cufe VARCHAR(120) NULL,
    provider_document_id VARCHAR(120) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_invoicing_artifacts_source (company_id, account_id, source_order_id),
    CONSTRAINT fk_invoicing_artifacts_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_invoicing_artifacts_company_account
        FOREIGN KEY (company_id, account_id) REFERENCES meli_accounts(company_id, id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_invoicing_artifacts_order
        FOREIGN KEY (source_order_id) REFERENCES orders(id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

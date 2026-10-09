CREATE TABLE webhook_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id VARCHAR(80) NULL,
    topic VARCHAR(64) NOT NULL,
    resource VARCHAR(191) NOT NULL,
    external_user_id VARCHAR(32) NOT NULL,
    application_id VARCHAR(32) NULL,
    attempts SMALLINT UNSIGNED NULL,
    sent_at DATETIME(6) NULL,
    received_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_webhook_events_event_id (event_id),
    KEY idx_webhook_events_created_at (created_at),
    KEY idx_webhook_events_user_topic (external_user_id, topic, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE meli_accounts
    ADD UNIQUE KEY uq_meli_accounts_company_id (company_id, id);

CREATE TABLE orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    external_order_id VARCHAR(32) NOT NULL,
    status VARCHAR(40) NOT NULL,
    status_detail VARCHAR(80) NULL,
    date_created DATETIME(6) NULL,
    date_closed DATETIME(6) NULL,
    last_updated DATETIME(6) NULL,
    total_amount DECIMAL(18,4) NOT NULL DEFAULT 0,
    currency_id VARCHAR(8) NOT NULL,
    buyer_id VARCHAR(32) NULL,
    pack_id VARCHAR(32) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_orders_company_account_external (company_id, account_id, external_order_id),
    KEY idx_orders_company_account_date (company_id, account_id, date_created, id),
    KEY idx_orders_company_account_status_date (company_id, account_id, status, date_created, id),
    CONSTRAINT fk_orders_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_orders_company_account
        FOREIGN KEY (company_id, account_id) REFERENCES meli_accounts(company_id, id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    external_item_id VARCHAR(32) NOT NULL,
    variation_id VARCHAR(32) NULL,
    title VARCHAR(255) NOT NULL,
    quantity DECIMAL(14,4) NOT NULL,
    unit_price DECIMAL(18,4) NOT NULL,
    currency_id VARCHAR(8) NOT NULL,
    seller_sku VARCHAR(120) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_order_items_order (order_id, id),
    KEY idx_order_items_external (external_item_id, variation_id),
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

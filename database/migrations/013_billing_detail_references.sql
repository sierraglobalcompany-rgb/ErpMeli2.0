CREATE TABLE billing_detail_references (
    billing_detail_id BIGINT UNSIGNED NOT NULL,
    reference_kind VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    reference_value VARBINARY(255) NOT NULL,
    PRIMARY KEY (billing_detail_id, reference_kind, reference_value),
    KEY idx_billing_detail_references_lookup (reference_kind, reference_value, billing_detail_id),
    CONSTRAINT fk_billing_detail_references_detail
        FOREIGN KEY (billing_detail_id) REFERENCES billing_details(id)
        ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations(version, checksum)
SELECT '013_billing_detail_references_backfill',
       SHA2('billing-detail-references-backfill-v1', 256)
WHERE NOT EXISTS (SELECT 1 FROM billing_details);

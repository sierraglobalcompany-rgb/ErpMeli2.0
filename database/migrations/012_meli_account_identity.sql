ALTER TABLE meli_accounts
    DROP INDEX uq_meli_accounts_company_user,
    ADD UNIQUE KEY uq_meli_accounts_external_user (external_user_id);

ALTER TABLE users ADD is_system_admin TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE companies ADD archived_at DATETIME(3) NULL;

UPDATE users SET is_system_admin = 1
WHERE (SELECT COUNT(*) FROM companies) = 1
  AND id = (SELECT MIN(user_id) FROM company_users WHERE role = 'admin' HAVING COUNT(*) = 1);

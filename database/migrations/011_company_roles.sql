UPDATE company_users SET role = 'operator' WHERE role = 'member';
ALTER TABLE company_users MODIFY role VARCHAR(32) NOT NULL DEFAULT 'operator';

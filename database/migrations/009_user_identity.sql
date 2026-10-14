ALTER TABLE users ADD username VARCHAR(191) NULL, ADD display_name VARCHAR(191) NULL;
UPDATE users SET username = LOWER(TRIM(email)), display_name = email;
ALTER TABLE users MODIFY username VARCHAR(191) NOT NULL, MODIFY display_name VARCHAR(191) NOT NULL,
    ADD UNIQUE KEY uq_users_username (username);

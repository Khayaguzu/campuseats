-- Run against the existing CampusEats database before deploying the new PHP code.
-- Account IDs, passwords, Google links, orders and historic prices are preserved.
-- Expand first so strict SQL mode permits converting existing account roles.
ALTER TABLE users MODIFY role ENUM('student','lecturer','customer','admin') NOT NULL DEFAULT 'customer';
UPDATE users SET role='customer' WHERE role IN ('student','lecturer');
ALTER TABLE users MODIFY role ENUM('customer','admin') NOT NULL DEFAULT 'customer';
-- Legacy requested_role, student_number and verified columns may remain for
-- data retention. The updated application never reads or writes these columns.
-- Rerunning this migration is safe; pending role requests never grant admin access.

-- Safe to run on an existing CampusEats database without removing any orders.
-- Back up the database before changes; CREATE TABLE IF NOT EXISTS preserves prior data.
USE campuseats;
CREATE TABLE IF NOT EXISTS order_payments (
    order_id INT UNSIGNED NOT NULL PRIMARY KEY,
    method ENUM('eft','payfast','demo') NOT NULL,
    state ENUM('pending','paid') NOT NULL DEFAULT 'pending',
    bank_reference VARCHAR(40) NULL,
    gateway_payment_id VARCHAR(100) NULL UNIQUE,
    confirmed_at DATETIME NULL,
    payment_token_hash CHAR(64) NULL,
    payment_token_expires_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB;

-- Re-running migration also upgrades the first payment schema while retaining rows.
ALTER TABLE order_payments MODIFY COLUMN method ENUM('eft','payfast','demo') NOT NULL;

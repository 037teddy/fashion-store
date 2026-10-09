-- Fashion Store: M-Pesa payments migration
-- Run against the existing fashion_store database in phpMyAdmin or:
--   mysql -u root fashion_store < database/payments_migration.sql

USE fashion_store;

-- Allow 'mpesa' as a payment method on orders
ALTER TABLE orders
  MODIFY payment_method ENUM('cash_on_delivery','mpesa') NOT NULL DEFAULT 'mpesa';

-- Track every M-Pesa payment attempt tied to an order
CREATE TABLE IF NOT EXISTS payments (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id              INT UNSIGNED NOT NULL,
  amount                DECIMAL(10,2) NOT NULL,
  method                ENUM('mpesa') NOT NULL DEFAULT 'mpesa',
  phone_number          VARCHAR(15) NULL,
  external_reference    VARCHAR(80) NULL,
  checkout_request_id   VARCHAR(120) NULL,
  transaction_reference VARCHAR(120) NULL,
  status                ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
  failure_reason        VARCHAR(255) NULL,
  paid_at               DATETIME NULL,
  created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_payments_order    (order_id),
  KEY idx_payments_checkout (checkout_request_id),
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

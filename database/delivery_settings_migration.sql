-- Add the editable store-wide delivery fee without changing existing orders.
USE fashion_store;

CREATE TABLE IF NOT EXISTS store_settings (
  id           TINYINT UNSIGNED PRIMARY KEY,
  delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 250.00,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO store_settings (id, delivery_fee) VALUES (1, 250.00);
-- Make M-Pesa the default for new orders and move unpaid legacy COD orders to M-Pesa.
-- Paid historical COD orders retain their recorded payment method.
USE fashion_store;

ALTER TABLE orders
  MODIFY payment_method ENUM('cash_on_delivery','mpesa') NOT NULL DEFAULT 'mpesa';

UPDATE orders
SET payment_method = 'mpesa'
WHERE payment_method = 'cash_on_delivery' AND payment_status = 'unpaid';
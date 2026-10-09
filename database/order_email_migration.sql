-- Track attempts and successful sending of paid-order confirmation emails.
USE fashion_store;

ALTER TABLE orders
  ADD COLUMN confirmation_email_attempted_at DATETIME NULL,
  ADD COLUMN confirmation_email_sent_at DATETIME NULL;
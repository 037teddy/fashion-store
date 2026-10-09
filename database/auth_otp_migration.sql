-- Store only a hash of the short-lived customer login verification code.
USE fashion_store;

ALTER TABLE users
  ADD COLUMN login_otp_hash VARCHAR(255) NULL,
  ADD COLUMN login_otp_expires DATETIME NULL,
  ADD COLUMN login_otp_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN login_otp_sent_at DATETIME NULL;
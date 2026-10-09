-- Fashion Store: schema for accounts, catalogue and product media.
-- Import with phpMyAdmin (Import tab) or: mysql -u root < database/schema.sql
CREATE DATABASE IF NOT EXISTS fashion_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fashion_store;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  phone         VARCHAR(15)  NOT NULL COMMENT 'normalized 2547XXXXXXXX / 2541XXXXXXXX',
  password_hash VARCHAR(255) NOT NULL,
  login_otp_hash VARCHAR(255) NULL,
  login_otp_expires DATETIME NULL,
  login_otp_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  login_otp_sent_at DATETIME NULL,
  role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_phone (phone)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
  fingerprint       CHAR(64) PRIMARY KEY,
  attempts          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  window_started_at DATETIME NOT NULL,
  locked_until      DATETIME NULL,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED NULL,
  name      VARCHAR(100) NOT NULL,
  slug      VARCHAR(120) NOT NULL,
  UNIQUE KEY uq_categories_slug (slug),
  CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NULL,
  name        VARCHAR(190) NOT NULL,
  description TEXT NULL,
  price       DECIMAL(10,2) NOT NULL,
  promo_price DECIMAL(10,2) NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_products_category (category_id),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_ratings (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  rating     TINYINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_product_rating_user (product_id, user_id),
  KEY idx_product_ratings_product (product_id),
  CONSTRAINT fk_product_ratings_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_product_ratings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_variants (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  sku        VARCHAR(100) NOT NULL,
  size       VARCHAR(64) NULL,
  color      VARCHAR(64) NULL,
  price      DECIMAL(10,2) NULL,
  stock      INT UNSIGNED NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_variants_sku (sku),
  KEY idx_variants_product (product_id, is_active),
  CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED NOT NULL,
  order_number     VARCHAR(32) NOT NULL,
  status           ENUM('placed','confirmed','shipped','delivered','cancelled') NOT NULL DEFAULT 'placed',
  payment_method   ENUM('cash_on_delivery','mpesa') NOT NULL DEFAULT 'mpesa',
  payment_status   ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
  subtotal         DECIMAL(10,2) NOT NULL,
  delivery_fee     DECIMAL(10,2) NOT NULL DEFAULT 0,
  total            DECIMAL(10,2) NOT NULL,
  shipping_name    VARCHAR(120) NOT NULL,
  shipping_phone   VARCHAR(15) NOT NULL,
  shipping_address VARCHAR(255) NOT NULL,
  shipping_city    VARCHAR(100) NOT NULL,
  customer_note    VARCHAR(500) NULL,
  confirmation_email_attempted_at DATETIME NULL,
  confirmation_email_sent_at DATETIME NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_orders_number (order_number),
  KEY idx_orders_user (user_id, created_at),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id      INT UNSIGNED NOT NULL,
  product_id    INT UNSIGNED NULL,
  variant_id    INT UNSIGNED NULL,
  product_name  VARCHAR(190) NOT NULL,
  sku           VARCHAR(100) NOT NULL,
  variant_label VARCHAR(140) NOT NULL,
  quantity      INT UNSIGNED NOT NULL,
  unit_price    DECIMAL(10,2) NOT NULL,
  line_total    DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT fk_order_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Photos and videos shown in the product gallery.
-- file_path / poster_path are relative to the /uploads folder.
CREATE TABLE IF NOT EXISTS product_media (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id  INT UNSIGNED NOT NULL,
  type        ENUM('image','video') NOT NULL,
  source      ENUM('uploaded','generated') NOT NULL DEFAULT 'uploaded',
  file_path   VARCHAR(255) NOT NULL,
  poster_path VARCHAR(255) NULL,
  duration    DECIMAL(5,2) NULL COMMENT 'seconds, videos only',
  source_hash CHAR(64) NULL COMMENT 'hash of the photo list a generated video was built from',
  sort_order  INT NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_media_product (product_id, type, sort_order),
  CONSTRAINT fk_media_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- M-Pesa payment attempts, one or more per order.
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

CREATE TABLE IF NOT EXISTS store_settings (
  id           TINYINT UNSIGNED PRIMARY KEY,
  delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 250.00,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO store_settings (id, delivery_fee) VALUES (1, 250.00);

-- Add one editable 1-5 star rating per customer and product.
USE fashion_store;

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
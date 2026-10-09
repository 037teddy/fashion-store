USE fashion_store;
INSERT INTO categories (name, slug) VALUES ('Shoes', 'shoes'), ('Women''s Clothing', 'womens-clothing')
  ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO products (category_id, name, description, price, promo_price)
  SELECT id, 'Canvas Sneakers', 'Lightweight everyday sneakers with a cushioned sole.', 2499.00, 1899.00
  FROM categories WHERE slug = 'shoes';

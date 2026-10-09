<?php
// Setup categories and products from the images folder
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit('CLI only'); }

require __DIR__ . '/../includes/bootstrap.php';

echo "Setting up categories and products...\n\n";

// Define categories and products based on the images folder structure
$setup = [
    'Kids' => [
        'slug' => 'kids',
        'products' => [
            ['name' => 'Gray Outfit Suit', 'description' => 'Stylish gray outfit suit for kids', 'price' => 3499.00],
            ['name' => 'Pink Blouse', 'description' => 'Cute pink blouse for kids', 'price' => 1299.00],
            ['name' => 'Pink Traditional Clothing', 'description' => 'Traditional pink clothing for kids', 'price' => 2499.00],
            ['name' => 'T-Shirt and Green Shorts', 'description' => 'Comfortable t-shirt with green shorts', 'price' => 1499.00],
            ['name' => 'Yellow Dress', 'description' => 'Bright yellow dress for kids', 'price' => 1999.00],
        ]
    ],
    'Shirts' => [
        'slug' => 'shirts',
        'products' => [
            ['name' => 'Black T-Shirt', 'description' => 'Classic black t-shirt', 'price' => 999.00],
            ['name' => 'Classic Shirt', 'description' => 'Classic styled shirt', 'price' => 1499.00],
            ['name' => 'Casual T-Shirt', 'description' => 'Comfortable casual t-shirt', 'price' => 899.00],
            ['name' => 'T-Shirts Pack', 'description' => 'Pack of comfortable t-shirts', 'price' => 2499.00],
            ['name' => 'Graphic T-Shirt', 'description' => 'T-shirt with graphic design', 'price' => 1199.00],
            ['name' => 'Premium T-Shirts', 'description' => 'Premium quality t-shirts', 'price' => 1799.00],
        ]
    ],
    'Trousers' => [
        'slug' => 'trousers',
        'products' => [
            ['name' => 'Stylish Blue Denim Short', 'description' => 'Stylish blue denim shorts', 'price' => 1899.00],
            ['name' => 'Blue Jeans', 'description' => 'Classic blue jeans', 'price' => 2499.00],
            ['name' => 'Green Corduroy Pants', 'description' => 'Comfortable green corduroy pants', 'price' => 2299.00],
            ['name' => 'Tailored Trouser', 'description' => 'Well-tailored trousers', 'price' => 2999.00],
        ]
    ],
    'Shoes' => [
        'slug' => 'shoes',
        'products' => [
            ['name' => 'Stylish Black Crocs', 'description' => 'Comfortable black crocs', 'price' => 2999.00],
            ['name' => 'Vibrant Red Sneakers', 'description' => 'Eye-catching red sneakers', 'price' => 3499.00],
            ['name' => 'Brown Leather Boat Shoes', 'description' => 'Classic brown leather boat shoes', 'price' => 4499.00],
            ['name' => 'Leather Shoe', 'description' => 'Classic leather shoes', 'price' => 3999.00],
            ['name' => 'Sports Shoes', 'description' => 'Comfortable sports shoes with SUPER design', 'price' => 3299.00],
        ]
    ],
    'Women' => [
        'slug' => 'womens-clothing',
        'products' => [
            ['name' => 'Colorful Tunic Dress', 'description' => 'Colorful tunic dress with swirls and floral abstract patterns', 'price' => 4499.00],
            ['name' => 'Stylish Black Dress', 'description' => 'Elegant black dress', 'price' => 3999.00],
            ['name' => 'Stylish Dress', 'description' => 'Fashionable stylish dress', 'price' => 3499.00],
            ['name' => 'Colorful Dresses', 'description' => 'Vibrant colorful dresses', 'price' => 3299.00],
            ['name' => 'Elegant Dress', 'description' => 'Beautiful elegant dress', 'price' => 4999.00],
            ['name' => 'Pink Dress', 'description' => 'Lovely pink dress', 'price' => 2799.00],
        ]
    ],
];

$categoryIdMap = [];

foreach ($setup as $categoryName => $data) {
    // Insert or update category
    $stmt = db()->prepare(
        "INSERT INTO categories (name, slug) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name)"
    );
    $stmt->execute([$categoryName, $data['slug']]);
    
    // Get category ID
    $stmt = db()->prepare('SELECT id FROM categories WHERE slug = ?');
    $stmt->execute([$data['slug']]);
    $categoryId = $stmt->fetchColumn();
    $categoryIdMap[$categoryName] = $categoryId;
    
    echo "Category: {$categoryName} (ID: {$categoryId})\n";
    
    // Insert products
    foreach ($data['products'] as $product) {
        $stmt = db()->prepare(
            "INSERT INTO products (category_id, name, description, price, is_active)
             VALUES (?, ?, ?, ?, 1)"
        );
        $stmt->execute([$categoryId, $product['name'], $product['description'], $product['price']]);
        $productId = db()->lastInsertId();
        echo "  - Product: {$product['name']} (ID: {$productId}) - KSh " . number_format($product['price'], 2) . "\n";
    }
    echo "\n";
}

echo "Setup complete!\n";
echo "Categories created: " . count($categoryIdMap) . "\n";
echo "You can now upload images to these products via the admin panel.\n";

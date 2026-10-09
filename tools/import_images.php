<?php
// Import images from the images folder to products
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit('CLI only'); }

require __DIR__ . '/../includes/bootstrap.php';

echo "Importing images to products...\n\n";

// Map folder names to product IDs (based on the setup we just done)
$imageMap = [
    'Kids' => [
        'gray outfit suit.jpg' => 2,
        'pink blouse.jpg' => 3,
        'pink traditional clothing.jpg' => 4,
        't-shirt and green shorts.jpg' => 5,
        'yellow dress..jpg' => 6,
    ],
    'Shirts' => [
        'Black T-shirt.jpg' => 7,
        'Shirt.jpg' => 8,
        'T sh.jpg' => 9,
        'T-shirts.jpg' => 10,
        'shirtts.png' => 11,
        'tshirts.jpg' => 12,
    ],
    'Troursers' => [
        'Stylish blue denim short.jpg' => 13,
        'blue jeans indoors.jpg' => 14,
        'green corduroy pants.jpg' => 15,
        'tailored trouser.jpg' => 16,
    ],
    'shoes' => [
        'Stylish black Crocs.jpg' => 17,
        'Vibrant red sneakers.jpg' => 18,
        'brown leather boat shoes with white soles.jpg' => 19,
        'leather shoe.jpg' => 20,
        'sports shoes with \'SUPER\' design.jpg' => 21,
    ],
    'women' => [
        'Colorful tunic dress with swirls and floral abstract patterns.jpg' => 22,
        'Stylish black dress.jpg' => 23,
        'Stylish dress.jpg' => 24,
        'colorful dresses.jpg' => 25,
        'elegant dress.jpg' => 26,
        'pink dress.jpg' => 27,
    ],
];

$imported = 0;
$errors = [];

foreach ($imageMap as $folder => $files) {
    $folderPath = BASE_PATH . '/images/' . $folder;
    if (!is_dir($folderPath)) {
        echo "Skipping folder: {$folder} (not found)\n";
        continue;
    }
    
    echo "Processing folder: {$folder}\n";
    
    foreach ($files as $filename => $productId) {
        $sourcePath = $folderPath . '/' . $filename;
        
        if (!is_file($sourcePath)) {
            $errors[] = "File not found: {$filename}";
            echo "  ✗ File not found: {$filename}\n";
            continue;
        }
        
        try {
            // Validate file size
            if (filesize($sourcePath) > MAX_IMAGE_BYTES) {
                throw new RuntimeException('File is too large');
            }
            
            // Get image info
            $info = @getimagesize($sourcePath);
            if ($info === false || $info[0] * $info[1] > 40_000_000) {
                throw new RuntimeException('Image is unreadable or too large');
            }
            
            // Process image
            $src = @imagecreatefromstring((string) file_get_contents($sourcePath));
            if ($src === false) {
                throw new RuntimeException('Image could not be processed');
            }
            
            // Resize if needed
            $w = imagesx($src);
            $h = imagesy($src);
            $scale = min(1.0, 1600 / max($w, $h));
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            
            $dst = imagecreatetruecolor($nw, $nh);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            
            // Create upload directory
            $relDir = "products/{$productId}";
            $absDir = UPLOAD_DIR . '/' . $relDir;
            if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
                throw new RuntimeException('Could not create upload folder');
            }
            
            // Save as JPEG
            $name = bin2hex(random_bytes(8)) . '.jpg';
            if (!imagejpeg($dst, "{$absDir}/{$name}", 85)) {
                throw new RuntimeException('Could not save image');
            }
            
            $relPath = "{$relDir}/{$name}";
            
            // Get current max sort_order for this product
            $stmt = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM product_media WHERE product_id = ?');
            $stmt->execute([$productId]);
            $nextSort = (int) $stmt->fetchColumn() + 1;
            
            // Insert into database
            $stmt = db()->prepare(
                "INSERT INTO product_media (product_id, type, source, file_path, sort_order)
                 VALUES (?, 'image', 'uploaded', ?, ?)"
            );
            $stmt->execute([$productId, $relPath, $nextSort]);
            
            echo "  ✓ Imported: {$filename} → Product ID {$productId}\n";
            $imported++;
            
        } catch (RuntimeException $ex) {
            $errors[] = "{$filename}: " . $ex->getMessage();
            echo "  ✗ Error: {$filename} - " . $ex->getMessage() . "\n";
        }
    }
    echo "\n";
}

echo "Import complete!\n";
echo "Successfully imported: {$imported} images\n";
if ($errors) {
    echo "Errors: " . count($errors) . "\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
}

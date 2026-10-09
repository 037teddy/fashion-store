<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_layout.php';
require_admin();

$productId = (int) ($_GET['id'] ?? $_POST['product_id'] ?? 0);
$product = [
    'category_id' => '',
    'name' => '',
    'description' => '',
    'price' => '',
    'promo_price' => '',
    'is_active' => 1,
];

if ($productId > 0) {
    $stmt = db()->prepare('SELECT id, category_id, name, description, price, promo_price, is_active FROM products WHERE id = ?');
    $stmt->execute([$productId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        http_response_code(404);
        exit('Product not found.');
    }
    $product = $existing;
}

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $product['name'] = trim((string) ($_POST['name'] ?? ''));
    $product['description'] = trim((string) ($_POST['description'] ?? ''));
    $product['category_id'] = trim((string) ($_POST['category_id'] ?? ''));
    $product['price'] = trim((string) ($_POST['price'] ?? ''));
    $product['promo_price'] = trim((string) ($_POST['promo_price'] ?? ''));
    $product['is_active'] = isset($_POST['is_active']) ? 1 : 0;

    $validPrice = static fn(string $value): bool => preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $value) === 1;
    $categoryId = $product['category_id'] === '' ? null : filter_var($product['category_id'], FILTER_VALIDATE_INT);
    $validCategoryIds = array_map(static fn(array $category): int => (int) $category['id'], $categories);

    if ($product['name'] === '' || strlen($product['name']) > 190) {
        $error = 'Enter a product name of 190 characters or fewer.';
    } elseif (!$validPrice($product['price'])) {
        $error = 'Enter a valid price with up to two decimal places.';
    } elseif ($product['promo_price'] !== '' && (!$validPrice($product['promo_price']) || (float) $product['promo_price'] >= (float) $product['price'])) {
        $error = 'The sale price must be valid and lower than the regular price.';
    } elseif ($product['category_id'] !== '' && ($categoryId === false || !in_array((int) $categoryId, $validCategoryIds, true))) {
        $error = 'Choose a valid category.';
    } else {
        $values = [
            $categoryId === null ? null : (int) $categoryId,
            $product['name'],
            $product['description'] !== '' ? $product['description'] : null,
            number_format((float) $product['price'], 2, '.', ''),
            $product['promo_price'] === '' ? null : number_format((float) $product['promo_price'], 2, '.', ''),
            $product['is_active'],
        ];

        if ($productId > 0) {
            $values[] = $productId;
            $save = db()->prepare('UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, promo_price = ?, is_active = ? WHERE id = ?');
            $save->execute($values);
            flash('ok', 'Product updated.');
        } else {
            $save = db()->prepare('INSERT INTO products (category_id, name, description, price, promo_price, is_active) VALUES (?, ?, ?, ?, ?, ?)');
            $save->execute($values);
            flash('ok', 'Product added.');
        }

        header('Location: ' . url('admin/index.php'));
        exit;
    }
}

admin_header($productId > 0 ? 'Edit product' : 'Add product');
?>
<p><a href="<?= e(url('admin/index.php')) ?>">&larr; All products</a></p>
<h1><?= $productId > 0 ? 'Edit product' : 'Add product' ?></h1>
<?php if ($error): ?><p class="notice notice-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<section class="card narrow">
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <?php if ($productId > 0): ?><input type="hidden" name="product_id" value="<?= $productId ?>"><?php endif; ?>
    <label>Product name<input name="name" maxlength="190" value="<?= e($product['name']) ?>" required></label>
    <label>Category
      <select name="category_id">
        <option value="">No category</option>
        <?php foreach ($categories as $category): ?>
          <option value="<?= (int) $category['id'] ?>" <?= (string) $product['category_id'] === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Description<textarea name="description" rows="5"><?= e((string) $product['description']) ?></textarea></label>
    <label>Price (KES)<input type="number" name="price" min="0" max="99999999.99" step="0.01" value="<?= e((string) $product['price']) ?>" required></label>
    <label>Sale price (KES)<input type="number" name="promo_price" min="0" max="99999999.98" step="0.01" value="<?= e((string) $product['promo_price']) ?>" placeholder="Optional"></label>
    <label class="check-label"><input type="checkbox" name="is_active" value="1" <?= $product['is_active'] ? 'checked' : '' ?>> Available in store</label>
        <div class="admin-form-actions">
            <button class="btn" type="submit"><?= $productId > 0 ? 'Save changes' : 'Add product' ?></button>
        </div>
  </form>
</section>
<?php admin_footer();
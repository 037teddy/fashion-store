<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_layout.php';
require_admin();

$productId = (int) ($_GET['id'] ?? $_POST['product_id'] ?? 0);
$stmt = db()->prepare('SELECT id, name, price FROM products WHERE id = ?');
$stmt->execute([$productId]);
$product = $stmt->fetch();
if (!$product) {
    http_response_code(404);
    exit('Product not found.');
}
$self = url('admin/variants.php?id=' . $productId);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    $sku = strtoupper(trim((string) ($_POST['sku'] ?? '')));
    $size = trim((string) ($_POST['size'] ?? ''));
    $color = trim((string) ($_POST['color'] ?? ''));
    $priceText = trim((string) ($_POST['price'] ?? ''));
    $price = $priceText === '' ? null : filter_var($priceText, FILTER_VALIDATE_FLOAT);
    $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);
    $active = isset($_POST['is_active']) ? 1 : 0;

    if ($action === 'save') {
        if ($sku === '' || strlen($sku) > 100 || strlen($size) > 64 || strlen($color) > 64) {
            $error = 'Enter a SKU and keep the size and color within 64 characters.';
        } elseif ($priceText !== '' && ($price === false || $price < 0)) {
            $error = 'Enter a valid non-negative price, or leave it blank to use the product price.';
        } elseif ($stock === false || $stock < 0) {
            $error = 'Stock must be a whole number of zero or more.';
        } else {
            try {
                $variantId = (int) ($_POST['variant_id'] ?? 0);
                if ($variantId > 0) {
                    $save = db()->prepare(
                        'UPDATE product_variants SET sku = ?, size = ?, color = ?, price = ?, stock = ?, is_active = ? WHERE id = ? AND product_id = ?'
                    );
                    $save->execute([$sku, $size ?: null, $color ?: null, $price, $stock, $active, $variantId, $productId]);
                } else {
                    $save = db()->prepare(
                        'INSERT INTO product_variants (product_id, sku, size, color, price, stock, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)'
                    );
                    $save->execute([$productId, $sku, $size ?: null, $color ?: null, $price, $stock]);
                }
                header('Location: ' . $self);
                exit;
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $error = 'That SKU is already in use.';
                } else {
                    throw $exception;
                }
            }
        }
    }
}

$variantsStmt = db()->prepare('SELECT * FROM product_variants WHERE product_id = ? ORDER BY size, color, sku');
$variantsStmt->execute([$productId]);
$variants = $variantsStmt->fetchAll();
admin_header('Variants & stock');
?>
<p><a href="<?= e(url('admin/index.php')) ?>">&larr; All products</a></p>
<h1><?= e($product['name']) ?></h1>
<p class="muted">Base price <?= e(money_kes((float) $product['price'])) ?>. Leave a variant price blank to use the current product sale price.</p>
<?php if ($error): ?><p class="notice notice-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<section class="card">
  <h2>Inventory options</h2>
  <?php if ($variants): ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>SKU</th><th>Size</th><th>Color</th><th>Price</th><th>Stock</th><th>Available</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($variants as $variant): ?>
        <tr>
          <td><?= e($variant['sku']) ?></td><td><?= e((string) $variant['size']) ?></td><td><?= e((string) $variant['color']) ?></td>
          <td><?= $variant['price'] === null ? 'Product price' : e(money_kes((float) $variant['price'])) ?></td>
          <td><?= (int) $variant['stock'] ?></td><td><?= $variant['is_active'] ? 'Active' : 'Hidden' ?></td>
          <td>
            <details><summary>Edit</summary>
              <form method="post" class="variant-form stack">
                <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= $productId ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="variant_id" value="<?= (int) $variant['id'] ?>">
                <label>SKU<input name="sku" maxlength="100" value="<?= e($variant['sku']) ?>" required></label>
                <label>Size<input name="size" maxlength="64" value="<?= e((string) $variant['size']) ?>"></label>
                <label>Color<input name="color" maxlength="64" value="<?= e((string) $variant['color']) ?>"></label>
                <label>Price override<input type="number" name="price" min="0" step="0.01" value="<?= e((string) $variant['price']) ?>" placeholder="Use product price"></label>
                <label>Stock<input type="number" name="stock" min="0" step="1" value="<?= (int) $variant['stock'] ?>" required></label>
                <label class="check-label"><input type="checkbox" name="is_active" value="1" <?= $variant['is_active'] ? 'checked' : '' ?>> Active in store</label>
                <button class="btn btn-secondary" type="submit">Save option</button>
              </form>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?><p class="muted">No options yet. Add a size, color, and SKU to make this item purchasable.</p><?php endif; ?>
</section>
<section class="card">
  <h2>Add an option</h2>
  <form method="post" class="variant-form stack">
    <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= $productId ?>"><input type="hidden" name="action" value="save">
    <label>SKU<input name="sku" maxlength="100" required></label>
    <label>Size<input name="size" maxlength="64" placeholder="For example, 38 or M"></label>
    <label>Color<input name="color" maxlength="64" placeholder="For example, Black"></label>
    <label>Price override<input type="number" name="price" min="0" step="0.01" placeholder="Use product price"></label>
    <label>Opening stock<input type="number" name="stock" min="0" step="1" value="0" required></label>
    <button class="btn" type="submit">Add option</button>
  </form>
</section>
<?php admin_footer();

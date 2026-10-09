<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_layout.php';
require_admin();

$products = db()->query(
  "SELECT p.id, p.name, p.price, p.is_active,
      (SELECT COUNT(*) FROM product_media m WHERE m.product_id = p.id AND m.type = 'image') AS photos,
      (SELECT COUNT(*) FROM product_media m WHERE m.product_id = p.id AND m.type = 'video') AS videos,
      (SELECT COALESCE(SUM(v.stock), 0) FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1) AS stock
   FROM products p ORDER BY p.id DESC"
)->fetchAll();

admin_header('Products');
?>
<div class="page-heading">
  <h1>Products</h1>
  <a class="btn" href="<?= e(url('admin/product.php')) ?>">Add product</a>
</div>
<?php if (!$products): ?>
  <p class="muted">No products yet. Import <code>database/seed.sql</code> for a sample product.</p>
<?php else: ?>
<div class="table-wrap">
<table>
  <thead><tr><th>Product</th><th>Price</th><th>Stock</th><th>Photos</th><th>Video</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($products as $p): ?>
    <tr>
      <td><?= e($p['name']) ?><?= $p['is_active'] ? '' : ' <span class="muted">(inactive)</span>' ?></td>
      <td><?= e(money_kes((float) $p['price'])) ?></td>
      <td><?= (int) $p['stock'] ?></td>
      <td><?= (int) $p['photos'] ?></td>
      <td><?= (int) $p['videos'] ? 'Yes' : 'No' ?></td>
      <td>
        <a href="<?= e(url('admin/product.php?id=' . (int) $p['id'])) ?>">Edit</a> ·
        <a href="<?= e(url('admin/variants.php?id=' . (int) $p['id'])) ?>">Variants &amp; stock</a> ·
        <a href="<?= e(url('admin/product_media.php?id=' . (int) $p['id'])) ?>">Photos &amp; video</a> ·
        <a href="<?= e(url('product.php?id=' . (int) $p['id'])) ?>">View page</a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif;
admin_footer();

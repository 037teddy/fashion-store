<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/storefront.php';
require __DIR__ . '/includes/storefront_layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $cart = cart_quantities();
    $removeId = (int) ($_POST['remove_id'] ?? 0);
    if ($removeId > 0) {
        unset($cart[$removeId]);
        flash('ok', 'Item removed from your cart.');
    } else {
        $stockStmt = db()->prepare(
            'SELECT v.stock FROM product_variants v JOIN products p ON p.id = v.product_id
             WHERE v.id = ? AND v.is_active = 1 AND p.is_active = 1'
        );
        foreach ((array) ($_POST['quantity'] ?? []) as $id => $quantityValue) {
            $variantId = (int) $id;
            $quantity = filter_var($quantityValue, FILTER_VALIDATE_INT);
            if ($variantId < 1 || $quantity === false) { continue; }
            if ($quantity < 1) {
                unset($cart[$variantId]);
                continue;
            }
            $stockStmt->execute([$variantId]);
            $stock = $stockStmt->fetchColumn();
            if ($stock === false || (int) $stock < 1) {
                flash('error', 'An unavailable item could not be updated. Remove it to continue.');
                continue;
            }
            $cart[$variantId] = min($quantity, (int) $stock);
            if ($quantity > (int) $stock) {
                flash('warn', 'A quantity was reduced to the available stock.');
            }
        }
        flash('ok', 'Cart updated.');
    }
    $_SESSION['cart'] = $cart;
    redirect('cart.php');
}

$items = storefront_cart();
$allAvailable = count($items) > 0;
foreach ($items as $item) { $allAvailable = $allAvailable && $item['available']; }
storefront_header('Your cart');
?>
<section class="page-heading"><p class="eyebrow">YOUR SELECTION</p><h1>Your cart <span><?= cart_item_count() ?></span></h1></section>
<?php if (!$items): ?>
  <section class="empty-state"><div class="empty-mark">FS</div><h2>Your cart is waiting for a good find.</h2><p>Browse new-season shoes, clothing, and accessories.</p><a class="btn" href="<?= e(url()) ?>">Explore the store</a></section>
<?php else: ?>
<div class="cart-layout">
  <form method="post" class="cart-lines">
    <?= csrf_field() ?>
    <?php foreach ($items as $item): ?>
      <article class="cart-line<?= $item['available'] ? '' : ' is-unavailable' ?>">
        <a class="cart-image" href="<?= e(url('product.php?id=' . (int) $item['product_id'])) ?>">
          <?php if ($item['image_path']): ?><img src="<?= e(UPLOAD_URL . '/' . $item['image_path']) ?>" alt="<?= e($item['name']) ?>">
          <?php else: ?><span class="product-art" aria-hidden="true">FS</span><?php endif; ?>
        </a>
        <div class="cart-copy"><a href="<?= e(url('product.php?id=' . (int) $item['product_id'])) ?>"><h2><?= e($item['name']) ?></h2></a><p><?= e($item['variant_label']) ?></p><p class="muted">SKU <?= e($item['sku']) ?> · <?= (int) $item['stock'] ?> available</p>
          <?php if (!$item['available']): ?><p class="stock-error">This option is unavailable or your quantity exceeds stock.</p><?php endif; ?>
        </div>
        <label class="cart-quantity">Qty<input type="number" name="quantity[<?= (int) $item['variant_id'] ?>]" min="0" max="<?= max(1, (int) $item['stock']) ?>" value="<?= (int) $item['quantity'] ?>"></label>
        <strong class="cart-line-total"><?= e(money_kes((float) $item['line_total'])) ?></strong>
        <button class="link-btn danger cart-remove" name="remove_id" value="<?= (int) $item['variant_id'] ?>" type="submit">Remove</button>
      </article>
    <?php endforeach; ?>
    <button class="btn btn-secondary" type="submit">Update cart</button>
  </form>
  <aside class="order-summary">
    <h2>Order summary</h2><div><span>Subtotal</span><strong><?= e(money_kes(cart_subtotal($items))) ?></strong></div>
    <div><span>Delivery</span><span>Calculated at checkout</span></div>
    <?php if ($allAvailable): ?><a class="btn checkout-button" href="<?= e(url('checkout.php')) ?>">Continue to checkout</a>
    <?php else: ?><p class="stock-error">Update or remove unavailable items before checkout.</p><?php endif; ?>
    <a class="continue-link" href="<?= e(url()) ?>">Continue shopping</a>
  </aside>
</div>
<?php endif; ?>
<?php storefront_footer();

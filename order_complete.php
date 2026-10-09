<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/storefront_layout.php';
$user   = require_customer();
$number = trim((string) ($_GET['number'] ?? ''));
$stmt   = db()->prepare('SELECT * FROM orders WHERE order_number = ? AND user_id = ?');
$stmt->execute([$number, (int) $user['id']]);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}
$itemStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
$itemStmt->execute([(int) $order['id']]);
$items = $itemStmt->fetchAll();

// Fetch M-Pesa receipt if paid online
$receipt = null;
if ($order['payment_status'] === 'paid' && $order['payment_method'] === 'mpesa') {
    $rStmt = db()->prepare("SELECT transaction_reference FROM payments WHERE order_id = ? AND status = 'paid' LIMIT 1");
    $rStmt->execute([(int) $order['id']]);
    $receipt = $rStmt->fetchColumn() ?: null;
}

$isPaid  = $order['payment_status'] === 'paid';
$canPay  = !$isPaid && in_array($order['status'], ['placed', 'confirmed'], true);

storefront_header('Order ' . $order['order_number']);
?>
<section class="confirmation">
  <div class="confirmation-mark" aria-hidden="true">&#10003;</div>
  <p class="eyebrow">ORDER RECEIVED</p>
  <h1>Thank you, <?= e($order['shipping_name']) ?>.</h1>
  <?php if ($isPaid && $order['payment_method'] === 'mpesa'): ?>
    <p>Your order <strong><?= e($order['order_number']) ?></strong> is confirmed and paid via M-Pesa.<?= $receipt ? ' Receipt: <strong>' . e($receipt) . '</strong>.' : '' ?></p>
  <?php elseif ($isPaid): ?>
    <p>Your order <strong><?= e($order['order_number']) ?></strong> is confirmed. Payment received.</p>
  <?php elseif ($canPay): ?>
    <p>Your order <strong><?= e($order['order_number']) ?></strong> is placed. Pay now with M-Pesa or pay the courier on delivery.</p>
  <?php else: ?>
    <p>Your order <strong><?= e($order['order_number']) ?></strong> is placed. Payment is due on delivery.</p>
  <?php endif; ?>

  <div class="confirmation-details">
    <h2>Delivery to</h2>
    <p><?= e($order['shipping_address']) ?><br><?= e($order['shipping_city']) ?><br><?= e($order['shipping_phone']) ?></p>
    <?php foreach ($items as $item): ?>
      <div><span><?= e($item['product_name']) ?> · <?= e($item['variant_label']) ?> × <?= (int) $item['quantity'] ?></span><strong><?= e(money_kes((float) $item['line_total'])) ?></strong></div>
    <?php endforeach; ?>
    <div><span>Delivery</span><strong><?= e(money_kes((float) $order['delivery_fee'])) ?></strong></div>
    <div class="summary-total"><span><?= $isPaid ? 'Total paid' : 'Total due' ?></span><strong><?= e(money_kes((float) $order['total'])) ?></strong></div>
  </div>

  <?php if ($canPay): ?>
    <a class="btn" href="<?= e(url('pay.php?number=' . rawurlencode($order['order_number']))) ?>" style="margin-bottom:.8rem">Pay <?= e(money_kes((float) $order['total'])) ?> with M-Pesa</a><br>
  <?php endif; ?>
  <a class="btn<?= $canPay ? ' btn-secondary' : '' ?>" href="<?= e(url('orders.php')) ?>">View your orders</a>
</section>
<?php storefront_footer();

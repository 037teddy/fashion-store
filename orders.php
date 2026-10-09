<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/storefront_layout.php';
$user = require_customer();
$stmt = db()->prepare(
    'SELECT o.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
     FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC'
);
$stmt->execute([(int) $user['id']]);
$orders = $stmt->fetchAll();
storefront_header('Your orders');
?>
<style>
.order-row { grid-template-columns: 1.3fr 1fr 1fr 1fr auto; }
.pay-now-btn { flex-shrink: 0; padding: .35rem .75rem; font-size: .75rem; min-height: auto; }
@media (max-width: 560px) { .order-row { grid-template-columns: 1fr auto; } .order-row > span:nth-child(2), .order-row > span:nth-child(3) { display: none; } }
</style>
<section class="page-heading"><p class="eyebrow">ORDER HISTORY</p><h1>Your orders</h1></section>
<?php if (!$orders): ?>
  <section class="empty-state"><div class="empty-mark">FS</div><h2>Your first order is one good find away.</h2><a class="btn" href="<?= e(url()) ?>">Shop the store</a></section>
<?php else: ?>
<div class="order-list">
  <?php foreach ($orders as $order): ?>
    <?php $canPay = $order['payment_status'] === 'unpaid' && in_array($order['status'], ['placed', 'confirmed'], true); ?>
    <a class="order-row" href="<?= e(url('order_complete.php?number=' . rawurlencode($order['order_number']))) ?>">
      <span><small><?= e(date('j M Y', strtotime($order['created_at']))) ?></small><strong><?= e($order['order_number']) ?></strong></span>
      <span><?= (int) $order['item_count'] ?> item<?= (int) $order['item_count'] === 1 ? '' : 's' ?></span>
      <span class="order-status status-<?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span>
      <strong><?= e(money_kes((float) $order['total'])) ?></strong>
      <?php if ($canPay): ?>
        <a class="btn pay-now-btn" href="<?= e(url('pay.php?number=' . rawurlencode($order['order_number']))) ?>"
           onclick="event.stopPropagation()">Pay with M-Pesa</a>
      <?php elseif ($order['payment_status'] === 'paid'): ?>
        <span class="order-status" style="background:#d6e75d">Paid</span>
      <?php else: ?>
        <span></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php storefront_footer();

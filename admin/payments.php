<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_layout.php';
require_admin();

$payments = db()->query(
    "SELECT o.id, o.order_number, o.created_at, o.status AS order_status,
            o.payment_method, o.total, o.shipping_phone,
            u.full_name, u.email, u.phone AS account_phone,
            p.method AS recorded_method, p.amount AS recorded_amount,
            p.phone_number AS payment_phone, p.transaction_reference,
            p.external_reference, p.paid_at
     FROM orders o
     JOIN users u ON u.id = o.user_id
     LEFT JOIN payments p ON p.id = (
         SELECT paid.id FROM payments paid
         WHERE paid.order_id = o.id AND paid.status = 'paid'
         ORDER BY paid.paid_at DESC, paid.id DESC LIMIT 1
     )
     WHERE o.payment_status = 'paid'
     ORDER BY COALESCE(p.paid_at, o.created_at) DESC, o.id DESC"
)->fetchAll();

admin_header('Payments');
?>
<h1>Payments received</h1>
<?php if (!$payments): ?>
  <p class="muted">No paid orders yet.</p>
<?php else: ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Paid</th><th>Order</th><th>Customer</th><th>Contact</th><th>Method</th><th>Amount</th><th>Reference</th><th>Order status</th></tr></thead>
    <tbody>
    <?php foreach ($payments as $payment): ?>
      <tr>
        <td><?= e(date('j M Y H:i', strtotime($payment['paid_at'] ?: $payment['created_at']))) ?></td>
        <td><?= e($payment['order_number']) ?></td>
        <td><?= e($payment['full_name']) ?><br><span class="muted"><?= e($payment['email']) ?></span></td>
        <td><?= e((string) ($payment['payment_phone'] ?: $payment['shipping_phone'] ?: $payment['account_phone'])) ?></td>
        <td><?= e(strtoupper(str_replace('_', ' ', $payment['recorded_method'] ?: $payment['payment_method']))) ?></td>
        <td><?= e(money_kes((float) ($payment['recorded_amount'] ?? $payment['total']))) ?></td>
        <td><?= e((string) ($payment['transaction_reference'] ?: $payment['external_reference'] ?: '—')) ?></td>
        <td><?= e(ucfirst($payment['order_status'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php admin_footer();
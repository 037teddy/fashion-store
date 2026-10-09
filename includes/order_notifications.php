<?php
declare(strict_types=1);
require_once __DIR__ . '/mailer.php';

function send_order_confirmation_email(int $orderId): bool
{
    $claim = db()->prepare(
        "UPDATE orders
         SET confirmation_email_attempted_at = NOW()
         WHERE id = ? AND payment_status = 'paid' AND confirmation_email_sent_at IS NULL
           AND (confirmation_email_attempted_at IS NULL OR confirmation_email_attempted_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))"
    );
    $claim->execute([$orderId]);
    if ($claim->rowCount() !== 1) {
        return false;
    }

    try {
        $orderStmt = db()->prepare(
            'SELECT o.order_number, o.total, o.shipping_name, o.shipping_address, o.shipping_city, u.email
             FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ? AND o.payment_status = \'paid\''
        );
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch();
        if (!$order) {
            return false;
        }

        $itemsStmt = db()->prepare(
            'SELECT product_name, variant_label, quantity, line_total FROM order_items WHERE order_id = ? ORDER BY id'
        );
        $itemsStmt->execute([$orderId]);
        $rows = '';
        foreach ($itemsStmt->fetchAll() as $item) {
            $rows .= '<tr><td>' . e($item['product_name']) . ' · ' . e($item['variant_label'])
                . '</td><td>' . (int) $item['quantity'] . '</td><td>'
                . e(money_kes((float) $item['line_total'])) . '</td></tr>';
        }

        $orderUrl = url('order_complete.php?number=' . rawurlencode($order['order_number']));
        $html = '<h1>Order confirmed</h1>'
            . '<p>Hello ' . e($order['shipping_name']) . ',</p>'
            . '<p>We received your M-Pesa payment for order <strong>' . e($order['order_number']) . '</strong>. Your order includes:</p>'
            . '<table border="1" cellpadding="8" cellspacing="0"><thead><tr><th>Product</th><th>Qty</th><th>Amount</th></tr></thead><tbody>'
            . $rows . '</tbody></table>'
            . '<p><strong>Total paid:</strong> ' . e(money_kes((float) $order['total'])) . '</p>'
            . '<p><strong>Delivering to:</strong><br>' . e($order['shipping_address']) . '<br>' . e($order['shipping_city']) . '</p>'
            . '<p>Delivery is expected within 7 days after payment confirmation. If the order is not delivered within 7 days, you are guaranteed a full refund.</p>'
            . '<p><a href="' . e($orderUrl) . '">View your order</a></p>';

        if (!send_app_email((string) $order['email'], 'Order confirmed · ' . $order['order_number'], $html)) {
            return false;
        }

        db()->prepare('UPDATE orders SET confirmation_email_sent_at = NOW() WHERE id = ? AND confirmation_email_sent_at IS NULL')->execute([$orderId]);
        return true;
    } catch (Throwable $exception) {
        error_log('[FS-OrderEmail] Failed for order ' . $orderId . ': ' . $exception->getMessage());
        return false;
    }
}
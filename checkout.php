<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/storefront.php';
require __DIR__ . '/includes/storefront_layout.php';
$user = require_customer();
$items = storefront_cart();
if (!$items) {
    flash('warn', 'Add an item to your cart before checkout.');
    redirect('cart.php');
}
$deliveryFee = (float) db()->query('SELECT delivery_fee FROM store_settings WHERE id = 1')->fetchColumn();
$error = '';
$values = [
    'shipping_name' => $user['full_name'],
    'shipping_phone' => '',
    'shipping_address' => '',
    'shipping_city' => '',
    'customer_note' => '',
];
$phoneStmt = db()->prepare('SELECT phone FROM users WHERE id = ?');
$phoneStmt->execute([(int) $user['id']]);
$storedPhone = (string) $phoneStmt->fetchColumn();
$values['shipping_phone'] = $storedPhone;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach ($values as $key => $_) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $phone = normalize_ke_phone($values['shipping_phone']);
    if ($values['shipping_name'] === '' || strlen($values['shipping_name']) > 120) {
        $error = 'Enter the recipient name.';
    } elseif ($phone === null) {
        $error = 'Enter a valid Kenyan mobile number.';
    } elseif ($values['shipping_address'] === '' || strlen($values['shipping_address']) > 255) {
        $error = 'Enter a delivery address, up to 255 characters.';
    } elseif ($values['shipping_city'] === '' || strlen($values['shipping_city']) > 100) {
        $error = 'Enter your town or city.';
    } elseif (strlen($values['customer_note']) > 500) {
        $error = 'Keep delivery notes under 500 characters.';
    } else {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $lockedItems = [];
            $subtotal = 0.0;
            $lineStmt = $pdo->prepare(
                'SELECT v.id, v.product_id, v.sku, v.size, v.color, v.stock,
                        COALESCE(v.price, CASE WHEN p.promo_price IS NOT NULL AND p.promo_price < p.price THEN p.promo_price ELSE p.price END) AS unit_price,
                        p.name
                 FROM product_variants v JOIN products p ON p.id = v.product_id
                 WHERE v.id = ? AND v.is_active = 1 AND p.is_active = 1 FOR UPDATE'
            );
            foreach ($items as $item) {
                $lineStmt->execute([(int) $item['variant_id']]);
                $line = $lineStmt->fetch();
                if (!$line || (int) $line['stock'] < (int) $item['quantity']) {
                    throw new RuntimeException('Stock changed. Review your cart and try again.');
                }
                $line['quantity'] = (int) $item['quantity'];
                $line['unit_price'] = (float) $line['unit_price'];
                $line['line_total'] = round($line['unit_price'] * $line['quantity'], 2);
                $line['variant_label'] = implode(' · ', array_filter([
                    $line['size'] ? 'Size ' . $line['size'] : '',
                    $line['color'] ? 'Color ' . $line['color'] : '',
                ])) ?: 'SKU ' . $line['sku'];
                $subtotal += $line['line_total'];
                $lockedItems[] = $line;
            }

            $total = round($subtotal + $deliveryFee, 2);
            $orderNumber = 'FS-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $orderStmt = $pdo->prepare(
                "INSERT INTO orders (user_id, order_number, payment_method, subtotal, delivery_fee, total, shipping_name, shipping_phone, shipping_address, shipping_city, customer_note)
                 VALUES (?, ?, 'mpesa', ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $orderStmt->execute([
                (int) $user['id'], $orderNumber, $subtotal, $deliveryFee, $total,
                $values['shipping_name'], $phone, $values['shipping_address'], $values['shipping_city'],
                $values['customer_note'] ?: null,
            ]);
            $orderId = (int) $pdo->lastInsertId();
            $insertItem = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, variant_id, product_name, sku, variant_label, quantity, unit_price, line_total)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $decrement = $pdo->prepare('UPDATE product_variants SET stock = stock - ? WHERE id = ? AND stock >= ?');
            foreach ($lockedItems as $line) {
                $decrement->execute([$line['quantity'], $line['id'], $line['quantity']]);
                if ($decrement->rowCount() !== 1) {
                    throw new RuntimeException('Stock changed. Review your cart and try again.');
                }
                $insertItem->execute([
                    $orderId, $line['product_id'], $line['id'], $line['name'], $line['sku'],
                    $line['variant_label'], $line['quantity'], $line['unit_price'], $line['line_total'],
                ]);
            }
            $pdo->commit();
            $_SESSION['cart'] = [];
            redirect('pay.php?number=' . rawurlencode($orderNumber));
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
            $error = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'We could not place the order. Please try again.';
        }
    }
}

$allAvailable = true;
foreach ($items as $item) { $allAvailable = $allAvailable && $item['available']; }
storefront_header('Checkout');
?>
<section class="page-heading"><p class="eyebrow">SECURE CHECKOUT</p><h1>Delivery details</h1></section>
<?php if ($error): ?><p class="notice notice-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<div class="checkout-layout">
  <form method="post" class="checkout-form stack">
    <?= csrf_field() ?>
    <label>Recipient name<input name="shipping_name" maxlength="120" value="<?= e($values['shipping_name']) ?>" autocomplete="name" required></label>
    <label>Mobile number<input name="shipping_phone" type="tel" value="<?= e($values['shipping_phone']) ?>" autocomplete="tel" required></label>
    <label>Street, building, estate or landmark<input name="shipping_address" maxlength="255" value="<?= e($values['shipping_address']) ?>" autocomplete="street-address" required></label>
    <label>Town or city<input name="shipping_city" maxlength="100" value="<?= e($values['shipping_city']) ?>" autocomplete="address-level2" required></label>
    <label>Delivery note <span class="muted">Optional</span><textarea name="customer_note" maxlength="500" rows="3"><?= e($values['customer_note']) ?></textarea></label>
    <div class="delivery-promise"><strong>Delivery within 7 days</strong><span>After payment is confirmed. If your order is not delivered within 7 days, you are guaranteed a full refund.</span></div>
    <fieldset><legend>Payment</legend><span class="mpesa-badge">M-PESA</span><p class="muted">After placing your order, you will be prompted to pay securely with M-Pesa before it is confirmed.</p></fieldset>
    <?php if (!$allAvailable): ?><p class="stock-error">Some cart items are no longer available. Return to your cart to update it.</p><?php endif; ?>
    <button class="btn" type="submit" <?= $allAvailable ? '' : 'disabled' ?>>Place order and pay with M-Pesa</button>
  </form>
  <aside class="order-summary"><h2>Your order</h2>
    <?php foreach ($items as $item): ?><div class="summary-item"><span><?= e($item['name']) ?> × <?= (int) $item['quantity'] ?><small><?= e($item['variant_label']) ?></small></span><strong><?= e(money_kes((float) $item['line_total'])) ?></strong></div><?php endforeach; ?>
    <?php $subtotal = cart_subtotal($items); ?><div><span>Subtotal</span><strong><?= e(money_kes($subtotal)) ?></strong></div>
    <div><span>Delivery</span><strong><?= e(money_kes($deliveryFee)) ?></strong></div>
    <div class="summary-total"><span>Total</span><strong><?= e(money_kes($subtotal + $deliveryFee)) ?></strong></div>
  </aside>
</div>
<?php storefront_footer();

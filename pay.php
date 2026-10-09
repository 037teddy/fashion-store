<?php
declare(strict_types=1);
/**
 * pay.php — M-Pesa payment page for a placed order.
 * Linked from orders.php for any order with payment_status = 'unpaid'.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/storefront_layout.php';
require __DIR__ . '/config/swift_wallet.php';

$user   = require_customer();
$number = trim((string) ($_GET['number'] ?? ''));

// Load the order
$stmt = db()->prepare('SELECT * FROM orders WHERE order_number = ? AND user_id = ?');
$stmt->execute([$number, (int) $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

// Already paid → straight to confirmation
if ($order['payment_status'] === 'paid') {
    redirect('order_complete.php?number=' . rawurlencode($number));
}

if (!in_array($order['status'], ['placed', 'confirmed'], true)) {
    flash('warn', 'This order cannot be paid at this stage.');
    redirect('orders.php');
}

// Load order items for the summary panel
$itemsStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
$itemsStmt->execute([(int) $order['id']]);
$items = $itemsStmt->fetchAll();

// Pre-fill phone from user profile
$phoneStmt = db()->prepare('SELECT phone FROM users WHERE id = ?');
$phoneStmt->execute([(int) $user['id']]);
$stored_phone = (string) $phoneStmt->fetchColumn();

storefront_header('Pay · ' . $order['order_number']);
?>
<style>
/* ── Pay page extras ────────────────────────────────────── */
.pay-layout { display: grid; grid-template-columns: minmax(0, 1fr) minmax(260px, .8fr); gap: 2rem; align-items: start; margin-bottom: 4rem; }
.pay-form-wrap { padding: 1.3rem; border: 1px solid var(--store-line); background: #fff; }
.pay-form-wrap h2 { margin: 0 0 .4rem; font-size: 1.4rem; }
.pay-form-wrap > p { margin: 0 0 1.3rem; color: var(--store-muted); font-size: .82rem; }
.mpesa-logo { display: flex; align-items: center; gap: .55rem; margin-bottom: 1.25rem; }
.mpesa-badge { display: inline-flex; align-items: center; justify-content: center; padding: .3rem .7rem; background: #00a651; color: #fff; font-weight: 800; font-size: .78rem; letter-spacing: .04em; border-radius: 3px; }
.mpesa-tagline { font-size: .75rem; color: var(--store-muted); }
#pay-status { margin: .75rem 0 0; }
#pay-status .notice { margin: 0; }
.pay-steps { display: flex; gap: .5rem; align-items: center; font-size: .73rem; color: var(--store-muted); margin-top: 1rem; }
.pay-steps span { display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 50%; background: var(--store-lime); font-weight: 800; font-size: .68rem; color: var(--store-ink); flex-shrink: 0; }
.pay-steps b { color: var(--store-ink); font-weight: 600; }
.spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid currentColor; border-top-color: transparent; border-radius: 50%; animation: spin .7s linear infinite; vertical-align: middle; margin-right: .4rem; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 900px) { .pay-layout { grid-template-columns: 1fr; } .order-summary { grid-row: 1; } }
</style>

<section class="page-heading">
  <p class="eyebrow">SECURE PAYMENT</p>
  <h1>Pay with M-Pesa</h1>
</section>

<div class="pay-layout">
  <!-- ── Payment form ── -->
  <div class="pay-form-wrap">
    <div class="mpesa-logo">
      <span class="mpesa-badge">M-PESA</span>
      <span class="mpesa-tagline">Powered by Swift Wallet · Safe &amp; instant</span>
    </div>
    <h2>Order <?= e($order['order_number']) ?></h2>
    <p>Enter your Safaricom number to receive an M-Pesa prompt. Enter your PIN to confirm.</p>
    <div class="delivery-promise"><strong>Delivery within 7 days</strong><span>After payment is confirmed. If your order is not delivered within 7 days, you are guaranteed a full refund.</span></div>

    <div id="pay-status"></div>

    <form id="pay-form" class="stack" autocomplete="off">
      <label>M-Pesa phone number
        <input id="phone-input" type="tel" name="phone_number"
               value="<?= e($stored_phone) ?>"
               placeholder="e.g. 0712 345 678 or +254712345678"
               required autocomplete="tel">
      </label>
      <button class="btn" id="pay-btn" type="submit">
        Pay <?= e(money_kes((float) $order['total'])) ?> with M-Pesa
      </button>
    </form>

    <div class="pay-steps">
      <span>1</span> Enter number &nbsp;·&nbsp;
      <span>2</span> Check phone for prompt &nbsp;·&nbsp;
      <span>3</span> <b>Enter PIN</b>
    </div>
  </div>

  <!-- ── Order summary sidebar ── -->
  <aside class="order-summary">
    <h2>Your order</h2>
    <?php foreach ($items as $item): ?>
      <div class="summary-item">
        <span><?= e($item['product_name']) ?> × <?= (int) $item['quantity'] ?>
          <small><?= e($item['variant_label']) ?></small>
        </span>
        <strong><?= e(money_kes((float) $item['line_total'])) ?></strong>
      </div>
    <?php endforeach; ?>
    <div>
      <span>Subtotal</span>
      <strong><?= e(money_kes((float) $order['subtotal'])) ?></strong>
    </div>
    <div>
      <span>Delivery</span>
      <strong><?= e(money_kes((float) $order['delivery_fee'])) ?></strong>
    </div>
    <div class="summary-total">
      <span>Total to pay</span>
      <strong><?= e(money_kes((float) $order['total'])) ?></strong>
    </div>
  </aside>
</div>

<script>
(function () {
  const orderNumber = <?= json_encode($order['order_number']) ?>;
  const form        = document.getElementById('pay-form');
  const statusBox   = document.getElementById('pay-status');
  const payBtn      = document.getElementById('pay-btn');
  let   pollTimer   = null;

  function showStatus(html, type) {
    statusBox.innerHTML = '<p class="notice notice-' + type + '">' + html + '</p>';
  }

  function stopPolling() {
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  }

  function pollStatus(checkoutId) {
    let attempts = 0;
    const maxAttempts = 40; // ~2 minutes at 3-second intervals

    pollTimer = setInterval(async function () {
      attempts++;
      try {
        const params = new URLSearchParams({ order_number: orderNumber });
        if (checkoutId) params.set('checkout_request_id', checkoutId);

        const res  = await fetch(<?= json_encode(url('payments/check_status.php')) ?> + '?' + params);
        const data = await res.json();

        if (data.status === 'completed') {
          stopPolling();
          showStatus('✓ Payment confirmed! Redirecting&hellip;', 'ok');
          setTimeout(function () {
            window.location.href = <?= json_encode(url('order_complete.php?number=' . rawurlencode($order['order_number']))) ?>;
          }, 1500);
        } else if (data.status === 'failed') {
          stopPolling();
          showStatus('Payment failed: ' + (data.error || 'please try again.'), 'error');
          payBtn.disabled = false;
        } else if (attempts >= maxAttempts) {
          stopPolling();
          showStatus(
            'Still waiting for confirmation. If you completed the M-Pesa prompt, ' +
            'refresh this page in a moment or check your <a href="' +
            <?= json_encode(url('orders.php')) ?> + '">orders</a>.',
            'warn'
          );
          payBtn.disabled = false;
        }
      } catch (_) {
        // Network hiccup — keep polling silently
      }
    }, 3000);
  }

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    stopPolling();
    payBtn.disabled = true;
    showStatus('<span class="spinner"></span>Sending M-Pesa prompt to your phone&hellip;', 'warn');

    try {
      const res = await fetch(<?= json_encode(url('payments/initiate.php')) ?>, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
          order_number: orderNumber,
          phone_number: document.getElementById('phone-input').value.trim(),
        }),
      });
      let data;
      try {
        data = await res.json();
      } catch (jsonErr) {
        showStatus('Server error (' + res.status + '). Please try again.', 'error');
        payBtn.disabled = false;
        return;
      }

      if (!data.success) {
        showStatus(data.error || 'Could not start payment. Please try again.', 'error');
        payBtn.disabled = false;
        return;
      }

      showStatus(
        '<span class="spinner"></span>Prompt sent! Enter your M-Pesa PIN on your phone to complete payment.',
        'warn'
      );
      pollStatus(data.checkout_request_id);
    } catch (err) {
      showStatus('Could not contact the payment service. Check your connection and try again.', 'error');
      payBtn.disabled = false;
    }
  });
}());
</script>
<?php storefront_footer();

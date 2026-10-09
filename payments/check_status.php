<?php
declare(strict_types=1);
/**
 * payments/check_status.php
 * Polls the local DB (and Swift Wallet if still pending) for M-Pesa payment status.
 *
 * GET  ?order_number=FS-XXXXXX
 * Returns JSON: { status: 'pending'|'completed'|'failed', receipt?, error? }
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../config/swift_wallet.php';
require_once __DIR__ . '/../includes/order_notifications.php';

$user = require_customer();
header('Content-Type: application/json');

$order_number = trim((string) ($_GET['order_number'] ?? ''));
if ($order_number === '') {
    echo json_encode(['status' => 'unknown', 'error' => 'Order number required.']);
    exit;
}

// Fetch the latest payment row for this order, verifying ownership via the orders join
$pdo  = db();
$stmt = $pdo->prepare('
    SELECT p.*
    FROM payments p
    JOIN orders o ON o.id = p.order_id
    WHERE o.order_number = ? AND o.user_id = ?
    ORDER BY p.id DESC LIMIT 1
');
$stmt->execute([$order_number, (int) $user['id']]);
$payment = $stmt->fetch();

if (!$payment) {
    echo json_encode(['status' => 'unknown', 'error' => 'Payment not found.']);
    exit;
}

// ── Already resolved in DB ────────────────────────────────────────────────────
if ($payment['status'] === 'paid') {
    send_order_confirmation_email((int) $payment['order_id']);
    echo json_encode(['status' => 'completed', 'receipt' => $payment['transaction_reference']]);
    exit;
}
if ($payment['status'] === 'failed') {
    echo json_encode(['status' => 'failed', 'error' => $payment['failure_reason'] ?: 'Payment failed.']);
    exit;
}

// ── Still pending locally → ask Swift Wallet ─────────────────────────────────
if (!$swift_api_key || !$payment['checkout_request_id']) {
    // No API key configured or STK push not yet started — stay pending
    echo json_encode(['status' => 'pending']);
    exit;
}

$url = $swift_transactions_url . '?checkout_request_id=' . urlencode((string) $payment['checkout_request_id']);
$ch  = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $swift_api_key,
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data           = json_decode($response ?: '', true);
$txn            = $data['data']['transaction'] ?? ($data['data'][0] ?? null);
$gateway_status = strtolower((string) ($txn['status'] ?? ''));
$receipt        = $txn['result']['MpesaReceiptNumber']
    ?? $txn['mpesa_receipt']
    ?? $txn['transaction_id']
    ?? null;

if ($http_code === 200 && $gateway_status === 'completed') {
    $ref = (string) ($receipt ?: $payment['checkout_request_id']);
    try {
        $pdo->beginTransaction();
        $pdo->prepare(
            "UPDATE payments SET status = 'paid', paid_at = NOW(), transaction_reference = ? WHERE id = ? AND status = 'pending'"
        )->execute([$ref, (int) $payment['id']]);
        $pdo->prepare(
            "UPDATE orders SET payment_status = 'paid', payment_method = 'mpesa', status = 'confirmed' WHERE id = ?"
        )->execute([(int) $payment['order_id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[FS-Pay] check_status commit error: ' . $e->getMessage());
    }
    send_order_confirmation_email((int) $payment['order_id']);
    echo json_encode(['status' => 'completed', 'receipt' => $receipt]);
    exit;
}

if (in_array($gateway_status, ['failed', 'cancelled'], true)) {
    $pdo->prepare(
        "UPDATE payments SET status = 'failed', failure_reason = ? WHERE id = ? AND status = 'pending'"
    )->execute([$gateway_status, (int) $payment['id']]);
    echo json_encode(['status' => 'failed', 'error' => 'M-Pesa payment was ' . $gateway_status . '.']);
    exit;
}

echo json_encode(['status' => 'pending']);

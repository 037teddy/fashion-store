<?php
declare(strict_types=1);
/**
 * payments/initiate.php
 * Initiates an M-Pesa STK push for an existing, unpaid order.
 *
 * POST  application/json  { "order_number": "FS-XXXXXX", "phone_number": "07XXXXXXXX" }
 * Returns JSON.
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../config/swift_wallet.php';

$user = require_customer();
header('Content-Type: application/json');

$data         = json_decode(file_get_contents('php://input'), true) ?: [];
$order_number = trim((string) ($data['order_number'] ?? ''));
$phone_input  = trim((string) ($data['phone_number'] ?? ''));

if ($order_number === '' || $phone_input === '') {
    echo json_encode(['success' => false, 'error' => 'Order number and phone number are required.']);
    exit;
}

// Reuse the existing helper from helpers.php (normalises to 2547XXXXXXXX / 2541XXXXXXXX)
$phone = normalize_ke_phone($phone_input);
if ($phone === null) {
    echo json_encode(['success' => false, 'error' => 'Enter a valid Kenyan mobile number such as 0712345678 or +254712345678.']);
    exit;
}

// Verify the order belongs to the authenticated customer and is payable
$stmt = db()->prepare('SELECT id, total, status, payment_status FROM orders WHERE order_number = ? AND user_id = ?');
$stmt->execute([$order_number, (int) $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    echo json_encode(['success' => false, 'error' => 'Order not found.']);
    exit;
}
if ($order['payment_status'] === 'paid') {
    echo json_encode(['success' => false, 'error' => 'This order has already been paid.']);
    exit;
}
if (!in_array($order['status'], ['placed', 'confirmed'], true)) {
    echo json_encode(['success' => false, 'error' => 'This order cannot be paid at this stage.']);
    exit;
}

$pdo              = db();
$order_id         = (int) $order['id'];
$amount           = (int) ceil((float) $order['total']);
$external_ref     = 'FS-' . str_pad((string) $order_id, 8, '0', STR_PAD_LEFT) . '-' . time();

// Upsert a pending payments row (reuse any existing pending attempt for this order)
$existStmt = $pdo->prepare("SELECT id FROM payments WHERE order_id = ? AND status = 'pending' LIMIT 1");
$existStmt->execute([$order_id]);
$existing_id = $existStmt->fetchColumn();

if ($existing_id) {
    $pdo->prepare(
        "UPDATE payments SET amount = ?, phone_number = ?, external_reference = ?, failure_reason = NULL WHERE id = ?"
    )->execute([$amount, $phone, $external_ref, (int) $existing_id]);
} else {
    $pdo->prepare(
        "INSERT INTO payments (order_id, amount, method, phone_number, external_reference, status)
         VALUES (?, ?, 'mpesa', ?, ?, 'pending')"
    )->execute([$order_id, $amount, $phone, $external_ref]);
}

// Mark payment_method on the order now so the UI reflects "M-Pesa pending"
$pdo->prepare("UPDATE orders SET payment_method = 'mpesa' WHERE id = ?")->execute([$order_id]);

// ── Call Swift Wallet STK-push API ────────────────────────────────────────────
$payload = [
    'amount'             => $amount,
    'phone_number'       => $phone,
    'external_reference' => $external_ref,
    'customer_name'      => $user['full_name'],
    'callback_url'       => $swift_callback_url,
];

$ch = curl_init($swift_stk_initiate_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $swift_api_key,
    ],
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);

$response   = curl_exec($ch);
$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

error_log('[FS-Pay] initiate: order=' . $order_number . ' HTTP=' . $http_code . ' body=' . $response);

if ($curl_error !== '') {
    echo json_encode(['success' => false, 'error' => 'Could not reach the payment service: ' . $curl_error]);
    exit;
}

$api = json_decode($response ?: '', true);
if (!is_array($api)) {
    echo json_encode(['success' => false, 'error' => 'Invalid response from payment service.']);
    exit;
}

if (($http_code !== 200 && $http_code !== 201) || ($api['success'] ?? false) !== true) {
    $err = substr((string) ($api['message'] ?? $api['error'] ?? 'Payment initiation failed.'), 0, 255);
    $pdo->prepare("UPDATE payments SET status = 'failed', failure_reason = ? WHERE order_id = ? AND status = 'pending'")
        ->execute([$err, $order_id]);
    echo json_encode(['success' => false, 'error' => $err]);
    exit;
}

$checkout_id = $api['checkout_request_id'] ?? null;
$pdo->prepare('UPDATE payments SET checkout_request_id = ? WHERE order_id = ? AND external_reference = ?')
    ->execute([$checkout_id, $order_id, $external_ref]);

echo json_encode([
    'success'             => true,
    'message'             => 'M-Pesa prompt sent. Enter your PIN on your phone.',
    'checkout_request_id' => $checkout_id,
]);

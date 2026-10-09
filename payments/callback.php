<?php
declare(strict_types=1);
/**
 * payments/callback.php
 * Public endpoint called by Swift Wallet after an STK push completes or fails.
 * Always responds 200 + {"ResultCode":0} so Swift Wallet stops retrying.
 */
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/order_notifications.php';

header('Content-Type: application/json');

$payload        = json_decode(file_get_contents('php://input'), true) ?: [];
$result         = $payload['result'] ?? [];
$checkout_id    = $payload['checkout_request_id'] ?? null;
$external_ref   = $payload['external_reference'] ?? null;
$transaction_id = $payload['transaction_id'] ?? null;
$status         = strtolower((string) ($payload['status'] ?? ''));
$success        = ($payload['success'] ?? false) === true;
$result_code    = isset($result['ResultCode'])
    ? (int) $result['ResultCode']
    : (isset($payload['ResultCode']) ? (int) $payload['ResultCode'] : null);
$receipt        = $result['MpesaReceiptNumber'] ?? $payload['MpesaReceiptNumber'] ?? $transaction_id;
$failure_reason = $result['ResultDesc'] ?? $payload['ResultDesc'] ?? 'Payment failed or was cancelled.';

error_log('[FS-Pay] callback: checkout_id=' . $checkout_id . ' ext_ref=' . $external_ref . ' result_code=' . $result_code . ' status=' . $status);

// Look up the payment by checkout_request_id or external_reference
$pdo  = db();
$stmt = $pdo->prepare('
    SELECT id, order_id, status AS payment_status
    FROM payments
    WHERE (? IS NOT NULL AND checkout_request_id = ?)
       OR (? IS NOT NULL AND external_reference   = ?)
    ORDER BY id DESC LIMIT 1
');
$stmt->execute([$checkout_id, $checkout_id, $external_ref, $external_ref]);
$payment = $stmt->fetch();

if (!$payment) {
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Payment reference not found']);
    exit;
}
if ($payment['payment_status'] === 'paid') {
    send_order_confirmation_email((int) $payment['order_id']);
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Already processed']);
    exit;
}

$paid = $result_code === 0 || ($success && $status === 'completed' && $receipt);

$pdo->beginTransaction();
try {
    if ($paid) {
        $ref = (string) ($receipt ?: ($checkout_id ?: $transaction_id));
        $pdo->prepare(
            "UPDATE payments SET status = 'paid', paid_at = NOW(), transaction_reference = ?, failure_reason = NULL WHERE id = ?"
        )->execute([$ref, (int) $payment['id']]);

        $pdo->prepare(
            "UPDATE orders SET payment_status = 'paid', payment_method = 'mpesa', status = 'confirmed' WHERE id = ?"
        )->execute([(int) $payment['order_id']]);
    } else {
        $reason = substr($failure_reason, 0, 255);
        $pdo->prepare(
            "UPDATE payments SET status = 'failed', failure_reason = ? WHERE id = ? AND status = 'pending'"
        )->execute([$reason, (int) $payment['id']]);
    }
    $pdo->commit();
    if ($paid) {
        send_order_confirmation_email((int) $payment['order_id']);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[FS-Pay] callback DB error: ' . $e->getMessage());
}

echo json_encode(['ResultCode' => 0, 'ResultDesc' => $paid ? 'Payment processed' : 'Payment failed']);

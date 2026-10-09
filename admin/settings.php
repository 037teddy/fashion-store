<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_layout.php';
require_admin();

$feeQuery = db()->query('SELECT delivery_fee FROM store_settings WHERE id = 1');
$deliveryFee = (string) $feeQuery->fetchColumn();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $deliveryFee = trim((string) ($_POST['delivery_fee'] ?? ''));
    if (preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $deliveryFee) !== 1) {
        $error = 'Enter an amount with up to two decimal places.';
    } else {
        $save = db()->prepare('UPDATE store_settings SET delivery_fee = ? WHERE id = 1');
        $save->execute([number_format((float) $deliveryFee, 2, '.', '')]);
        flash('ok', 'Delivery fee updated.');
        header('Location: ' . url('admin/settings.php'));
        exit;
    }
}

admin_header('Delivery fee');
?>
<h1>Delivery fee</h1>
<section class="card narrow">
  <p>New orders use this amount. Existing orders keep the fee saved when they were placed.</p>
  <?php if ($error): ?><p class="notice notice-error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Delivery amount (KES)
      <input type="number" name="delivery_fee" min="0" max="99999999.99" step="0.01" value="<?= e($deliveryFee) ?>" required>
    </label>
    <button class="btn" type="submit">Save delivery fee</button>
  </form>
</section>
<?php admin_footer();
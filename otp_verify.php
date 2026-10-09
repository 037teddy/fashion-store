<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/storefront_layout.php';

$userId = (int) ($_SESSION['pending_otp_user_id'] ?? 0);
if ($userId < 1) {
    redirect('login.php');
}
$next = (string) ($_SESSION['pending_otp_next'] ?? 'index.php');
$next = safe_customer_next($next);
$userStmt = db()->prepare('SELECT id, full_name, email, role, login_otp_hash, login_otp_expires, login_otp_attempts, login_otp_sent_at FROM users WHERE id = ? AND role = \'customer\'');
$userStmt->execute([$userId]);
$user = $userStmt->fetch();
if (!$user) {
    unset($_SESSION['pending_otp_user_id'], $_SESSION['pending_otp_next']);
    redirect('login.php');
}

$error = '';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if ((string) ($_POST['action'] ?? '') === 'resend') {
        $sentAt = $user['login_otp_sent_at'] ? strtotime($user['login_otp_sent_at']) : 0;
        if ($sentAt && $sentAt > time() - 60) {
            $error = 'Please wait a minute before requesting another code.';
        } else {
            $otp = sprintf('%06d', random_int(0, 999999));
            db()->prepare(
                'UPDATE users SET login_otp_hash = ?, login_otp_expires = DATE_ADD(NOW(), INTERVAL 15 MINUTE), login_otp_attempts = 0, login_otp_sent_at = NOW() WHERE id = ?'
            )->execute([password_hash($otp, PASSWORD_DEFAULT), $userId]);
            require_once __DIR__ . '/includes/mailer.php';
            $body = '<p>Hello ' . e($user['full_name']) . ',</p><p>Your Fashion Store sign-in code is:</p>'
                . '<p style="font-size:28px;font-weight:bold;letter-spacing:6px">' . e($otp) . '</p>'
                . '<p>This code expires in 15 minutes.</p>';
            if (send_app_email($user['email'], 'Fashion Store sign-in code', $body)) {
                flash('ok', 'A new verification code has been sent.');
                redirect('otp_verify.php');
            }
            $error = 'We could not send a new code. Try again in one minute.';
        }
    } else {
        $code = trim((string) ($_POST['otp'] ?? ''));
        $expiresAt = $user['login_otp_expires'] ? strtotime($user['login_otp_expires']) : false;
        $attempts = (int) $user['login_otp_attempts'];
        if ($attempts >= 5) {
            $error = 'Too many incorrect codes. Request a new code to continue.';
        } elseif (!$expiresAt || $expiresAt < time()) {
            $error = 'This code has expired. Request a new code to continue.';
        } elseif (preg_match('/^\d{6}$/D', $code) === 1 && $user['login_otp_hash'] && password_verify($code, $user['login_otp_hash'])) {
            db()->prepare('UPDATE users SET login_otp_hash = NULL, login_otp_expires = NULL, login_otp_attempts = 0, login_otp_sent_at = NULL WHERE id = ?')->execute([$userId]);
            unset($_SESSION['pending_otp_user_id'], $_SESSION['pending_otp_next']);
            establish_session($user);
            $_SESSION['otp_verified_user_id'] = $userId;
            redirect($next);
        } else {
            $attempts++;
            db()->prepare('UPDATE users SET login_otp_attempts = ? WHERE id = ?')->execute([$attempts, $userId]);
            $error = $attempts >= 5 ? 'Too many incorrect codes. Request a new code to continue.' : 'Enter the correct 6-digit code.';
        }
    }

    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
}

storefront_header('Verify your email');
?>
<section class="account-shell">
    <div class="account-intro"><p class="eyebrow">ONE MORE STEP</p><h1>Verify your email.</h1><p>Enter the 6-digit code sent to <?= e($user['email']) ?>. It expires in 15 minutes.</p></div>
  <div class="account-form stack">
    <?php if ($error): ?><p class="notice notice-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php foreach (pull_flash() as [$type, $flashMessage]): ?><p class="notice notice-<?= e($type) ?>" role="status"><?= e($flashMessage) ?></p><?php endforeach; ?>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <label for="otp">Verification code<input id="otp" type="text" name="otp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>
      <button class="btn" type="submit">Verify and continue</button>
    </form>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="resend">
      <button class="link-btn" type="submit">Send a new code</button>
    </form>
    <p><a href="<?= e(url('login.php')) ?>">Cancel sign-in</a></p>
  </div>
</section>
<?php storefront_footer();
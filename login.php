<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/storefront_layout.php';

$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
$next = safe_customer_next($next);
$user = current_user();
if ($user && $user['role'] === 'customer') {
    redirect($next);
}
$error = '';
$loginInput = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
  $loginInput = trim((string) ($_POST['login_input'] ?? ''));
  $row = authenticate_user($loginInput, (string) ($_POST['password'] ?? ''), 'customer');
    if ($row) {
    begin_customer_verification($row, $next);
  } else {
    $error = 'Invalid phone number/email or password, or sign-in is temporarily unavailable.';
    }
}

storefront_header('Customer sign in');
?>
<section class="account-shell">
  <div class="account-intro"><p class="eyebrow">GOOD TO SEE YOU</p><h1>Sign in to your account.</h1><p>Track orders and pick up where your shopping left off.</p></div>
  <form method="post" class="account-form stack">
    <?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
    <?php if ($error): ?><p class="notice notice-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <label>Phone number or email<input type="text" name="login_input" autocomplete="username" value="<?= e($loginInput) ?>" placeholder="0712345678 or you@example.com" required></label>
    <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
    <button class="btn" type="submit">Sign in</button>
    <p>New to Fashion Store? <a href="<?= e(url('register.php?next=' . rawurlencode($next))) ?>">Create an account</a></p>
  </form>
</section>
<?php storefront_footer();

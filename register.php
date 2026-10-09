<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/storefront_layout.php';

$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
$next = safe_customer_next($next);
if (current_user() && current_user()['role'] === 'customer') {
    redirect($next);
}
$error = '';
$values = ['full_name' => '', 'email' => '', 'phone' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach ($values as $key => $_) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    $phoneInput = preg_replace('/[\s\-()]/', '', $values['phone']);
    $phone = preg_match('/^(?:\+254[17]\d{8}|07\d{8})$/D', (string) $phoneInput) === 1
        ? normalize_ke_phone($values['phone'])
        : null;

    if ($values['full_name'] === '' || strlen($values['full_name']) > 120) {
        $error = 'Enter your name (up to 120 characters).';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 190) {
        $error = 'Enter a valid email address.';
    } elseif ($phone === null) {
        $error = 'Use a 10-digit 07... number or a +254... Kenyan mobile number.';
    } elseif (!password_meets_requirements($password)) {
        $error = 'Password must have at least 8 characters, including uppercase, lowercase, a number, and a symbol.';
    } elseif ($password !== $confirmation) {
        $error = 'The passwords do not match.';
    } else {
        try {
            $email = strtolower($values['email']);
            $stmt = db()->prepare("INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'customer')");
            $stmt->execute([$values['full_name'], $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
            begin_customer_verification([
                'id' => (int) db()->lastInsertId(),
                'full_name' => $values['full_name'],
                'email' => $email,
            ], $next);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = 'An account already uses that email or phone number.';
            } else {
                throw $exception;
            }
        }
    }
}

storefront_header('Create account');
?>
<section class="account-shell">
  <div class="account-intro"><p class="eyebrow">YOUR STYLE, YOUR WAY</p><h1>Create your account.</h1><p>One account for orders, delivery details, and new-season finds.</p></div>
  <form method="post" class="account-form stack">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <?php if ($error): ?><p class="notice notice-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <label>Full name<input type="text" name="full_name" maxlength="120" autocomplete="name" value="<?= e($values['full_name']) ?>" required></label>
    <label>Email address<input type="email" name="email" maxlength="190" autocomplete="email" value="<?= e($values['email']) ?>" required></label>
    <label>Kenyan mobile number<input type="tel" name="phone" autocomplete="tel" inputmode="tel" pattern="(?:\+254[17][0-9]{8}|07[0-9]{8})" title="Use a 10-digit 07... number or a +254... number" placeholder="0712345678 or +254712345678" value="<?= e($values['phone']) ?>" required></label>
    <label>Password<input type="password" name="password" minlength="8" pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}" title="At least 8 characters with uppercase, lowercase, number, and symbol" autocomplete="new-password" required><small class="muted">At least 8 characters with uppercase, lowercase, number, and symbol.</small></label>
    <label>Confirm password<input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></label>
    <button class="btn" type="submit">Create account</button>
    <p>Already registered? <a href="<?= e(url('login.php?next=' . rawurlencode($next))) ?>">Sign in</a></p>
  </form>
</section>
<?php storefront_footer();

<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_layout.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

  $row = authenticate_user($email, $password, 'admin');
  if ($row) {
    establish_session($row);
        redirect('admin/index.php');
    }
    // Same message for every failure: never reveal whether the email exists.
    $error = 'Invalid email or password.';
}

admin_header('Sign in');
?>
<section class="card narrow">
  <h1>Admin sign in</h1>
  <?php if ($error): ?><p class="notice notice-error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Email <input type="email" name="email" required autocomplete="username"></label>
    <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn" type="submit">Sign in</button>
  </form>
</section>
<?php admin_footer();

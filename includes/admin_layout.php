<?php
declare(strict_types=1);

function admin_header(string $title): void
{
    $user = current_user();
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · Admin</title>
  <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="admin">
<header class="admin-bar">
  <a class="admin-brand" href="<?= e(url('admin/index.php')) ?>">Store admin</a>
  <?php if ($user): ?>
    <nav class="admin-nav" aria-label="Admin">
      <a href="<?= e(url('admin/index.php')) ?>">Products</a>
      <a href="<?= e(url('admin/payments.php')) ?>">Payments</a>
      <a href="<?= e(url('admin/settings.php')) ?>">Delivery fee</a>
    </nav>
    <form method="post" action="<?= e(url('admin/logout.php')) ?>" class="inline">
      <?= csrf_field() ?>
      <span class="muted"><?= e($user['full_name']) ?></span>
      <button class="link-btn" type="submit">Sign out</button>
    </form>
  <?php endif; ?>
</header>
<main class="admin-main">
  <?php foreach (pull_flash() as [$type, $msg]): ?>
    <p class="notice notice-<?= e($type) ?>" role="status"><?= e($msg) ?></p>
  <?php endforeach; ?>
    <?php
}

function admin_footer(): void
{
    echo "</main>\n</body>\n</html>\n";
}

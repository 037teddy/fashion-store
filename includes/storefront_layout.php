<?php
declare(strict_types=1);

require_once __DIR__ . '/storefront.php';

function storefront_header(string $title, string $search = ''): void
{
    $user = current_user();
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · Fashion Store</title>
  <link rel="stylesheet" href="<?= e(url('assets/css/style.css?v=' . filemtime(dirname(__DIR__) . '/assets/css/style.css'))) ?>">
</head>
<body class="storefront">
<div class="store-promo">NEW-SEASON FINDS · DELIVERY ACROSS KENYA</div>
<header class="store-header">
  <a class="store-brand" href="<?= e(url()) ?>" aria-label="Fashion Store home"><span>FASHION</span><b>STORE</b></a>
  <form class="store-search" method="get" action="<?= e(url('index.php')) ?>" role="search">
    <label class="visually-hidden" for="store-search-input">Search fashion</label>
    <input id="store-search-input" type="search" name="q" value="<?= e($search) ?>" placeholder="Search shoes, dresses, accessories">
    <button type="submit" aria-label="Search">Search</button>
  </form>
  <nav class="store-actions" aria-label="Account and cart">
    <?php if ($user && $user['role'] === 'customer'): ?>
      <a href="<?= e(url('orders.php')) ?>"><span class="action-kicker">Welcome</span><strong><?= e($user['full_name']) ?></strong></a>
      <form method="post" action="<?= e(url('logout.php')) ?>" class="store-logout">
        <?= csrf_field() ?><button type="submit">Sign out</button>
      </form>
    <?php else: ?>
      <a href="<?= e(url('login.php')) ?>"><span class="action-kicker">Your account</span><strong>Sign in</strong></a>
    <?php endif; ?>
    <a class="cart-link" href="<?= e(url('cart.php')) ?>" aria-label="Cart, <?= cart_item_count() ?> items">Cart <b><?= cart_item_count() ?></b></a>
  </nav>
</header>
<nav class="store-categories" aria-label="Shop categories">
  <a href="<?= e(url()) ?>">All fashion</a>
  <a href="<?= e(url('index.php?category=shoes')) ?>">Shoes</a>
  <a href="<?= e(url('index.php?category=womens-clothing')) ?>">Women</a>
  <a href="<?= e(url('index.php?category=mens-clothing')) ?>">Men</a>
  <a href="<?= e(url('index.php?category=accessories')) ?>">Accessories</a>
  <a href="<?= e(url('index.php?deal=1')) ?>">Deals</a>
</nav>
<main class="store-main">
  <?php foreach (pull_flash() as [$type, $message]): ?>
    <p class="notice notice-<?= e($type) ?>" role="status"><?= e($message) ?></p>
  <?php endforeach; ?>
    <?php
}

function storefront_footer(): void
{
    ?>
</main>
<footer class="store-footer">
  <a class="store-brand" href="<?= e(url()) ?>"><span>FASHION</span><b>STORE</b></a>
  <p>Style you love, delivered across Kenya.</p>
  <section class="store-contact" aria-label="Contact information">
    <strong>Contact</strong>
    <p><span>Email</span> admin@fashionstore.com</p>
    <p><span>Phone</span> +2547 2844 3116</p>
  </section>
  <a href="<?= e(url('cart.php')) ?>">Your cart</a>
</footer>
</body>
</html>
    <?php
}

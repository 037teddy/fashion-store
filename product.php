<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/storefront.php';
require __DIR__ . '/includes/storefront_layout.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = db()->prepare('SELECT id, name, description, price, promo_price FROM products WHERE id = ? AND is_active = 1');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) {
    http_response_code(404);
    exit('Product not found.');
}
$viewer = current_user();
$ratingError = '';

  $cartError = '';
  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    if ((string) ($_POST['action'] ?? '') === 'rate') {
      $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
      if (!$viewer || $viewer['role'] !== 'customer') {
        $ratingError = 'Sign in with a customer account to rate this product.';
      } elseif ($rating === false || $rating < 1 || $rating > 5) {
        $ratingError = 'Choose a rating from 1 to 5 stars.';
      } else {
        $saveRating = db()->prepare(
          'INSERT INTO product_ratings (product_id, user_id, rating) VALUES (?, ?, ?)
           ON DUPLICATE KEY UPDATE rating = VALUES(rating), updated_at = CURRENT_TIMESTAMP'
        );
        $saveRating->execute([$id, (int) $viewer['id'], $rating]);
        flash('ok', 'Your rating has been saved.');
        redirect('product.php?id=' . $id . '#ratings');
      }
    } else {
      $variantId = (int) ($_POST['variant_id'] ?? 0);
      $belongs = db()->prepare('SELECT id FROM product_variants WHERE id = ? AND product_id = ?');
      $belongs->execute([$variantId, $id]);
      if (!$belongs->fetchColumn()) {
        $cartError = 'Choose an available option first.';
      } elseif (add_to_cart($variantId, max(1, (int) ($_POST['quantity'] ?? 1)))) {
        redirect('cart.php');
      } else {
        $cartError = 'That option is currently unavailable or there is not enough stock.';
      }
    }
  }

  $variantStmt = db()->prepare(
    'SELECT id, sku, size, color, stock, ? AS regular_price,
        COALESCE(price, CASE WHEN ? IS NOT NULL AND ? < ? THEN ? ELSE ? END) AS current_price
     FROM product_variants WHERE product_id = ? AND is_active = 1 ORDER BY size, color, sku'
  );
  $variantStmt->execute([$product['price'], $product['promo_price'], $product['promo_price'], $product['price'], $product['promo_price'], $product['price'], $id]);
  $variants = $variantStmt->fetchAll();

$mediaStmt = db()->prepare(
    "SELECT type, file_path, poster_path FROM product_media WHERE product_id = ?
     ORDER BY (type = 'video') DESC, sort_order, id"
);
$mediaStmt->execute([$id]);
$media = $mediaStmt->fetchAll();
$ratingStmt = db()->prepare('SELECT AVG(rating) AS rating_average, COUNT(*) AS rating_count FROM product_ratings WHERE product_id = ?');
$ratingStmt->execute([$id]);
$ratingSummary = $ratingStmt->fetch();
$myRating = null;
if ($viewer && $viewer['role'] === 'customer') {
  $myRatingStmt = db()->prepare('SELECT rating FROM product_ratings WHERE product_id = ? AND user_id = ?');
  $myRatingStmt->execute([$id, (int) $viewer['id']]);
  $myRating = $myRatingStmt->fetchColumn();
}

// One video at most is shown; it goes first, like the Kilimall gallery.
$items = [];
$firstImage = null;
foreach ($media as $m) {
    if ($m['type'] === 'image') {
        $firstImage ??= UPLOAD_URL . '/' . $m['file_path'];
    }
}
foreach ($media as $m) {
    if ($m['type'] === 'video') {
        if (array_filter($items, fn($i) => $i['type'] === 'video')) { continue; }
        $items[] = [
            'type'   => 'video',
            'src'    => UPLOAD_URL . '/' . $m['file_path'],
            'poster' => $m['poster_path'] ? UPLOAD_URL . '/' . $m['poster_path'] : $firstImage,
        ];
    } else {
        $items[] = ['type' => 'image', 'src' => UPLOAD_URL . '/' . $m['file_path'], 'poster' => null];
    }
}

$onSale = $product['promo_price'] !== null && (float) $product['promo_price'] < (float) $product['price'];
$price = (float) ($onSale ? $product['promo_price'] : $product['price']);
$discount = $onSale ? (int) round((1 - $price / (float) $product['price']) * 100) : 0;
storefront_header($product['name']);
?>
<div class="product">
  <div class="gallery" data-gallery>
    <?php if ($items): ?>
      <ul class="thumbs" aria-label="Product media">
        <?php foreach ($items as $n => $it): ?>
          <li>
            <button type="button" class="thumb<?= $n === 0 ? ' is-active' : '' ?>"
                    data-type="<?= e($it['type']) ?>" data-src="<?= e($it['src']) ?>" data-poster="<?= e((string) $it['poster']) ?>"
                    aria-label="<?= $it['type'] === 'video' ? 'Play product video' : 'Show photo ' . ($n + 1) ?>">
              <img src="<?= e($it['type'] === 'video' ? (string) $it['poster'] : $it['src']) ?>" alt="" loading="lazy">
              <?php if ($it['type'] === 'video'): ?><span class="thumb-play" aria-hidden="true"></span><?php endif; ?>
            </button>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="stage" aria-live="polite"></div>
      <div class="stage-zoom-preview" aria-hidden="true"></div>
    <?php else: ?>
      <div class="stage stage-empty">No photos yet</div>
    <?php endif; ?>
  </div>

  <section class="info">
    <h1><?= e($product['name']) ?></h1>
    <a class="rating-summary" href="#ratings" aria-label="<?= (int) $ratingSummary['rating_count'] > 0 ? e(number_format((float) $ratingSummary['rating_average'], 1) . ' out of 5 stars, ' . (int) $ratingSummary['rating_count'] . ' ratings') : 'No ratings yet; rate this product' ?>">
      <span class="rating-stars" aria-hidden="true">&#9733;</span>
      <span><?= (int) $ratingSummary['rating_count'] > 0 ? e(number_format((float) $ratingSummary['rating_average'], 1)) . ' / 5 · ' . (int) $ratingSummary['rating_count'] . ' rating' . ((int) $ratingSummary['rating_count'] === 1 ? '' : 's') : 'No ratings yet' ?></span>
    </a>
    <p class="price-row">
      <span class="price" id="selected-price"><?= e(money_kes($price)) ?></span>
      <?php if ($onSale): ?>
        <s class="was" id="was-price"><?= e(money_kes((float) $product['price'])) ?></s>
        <span class="off" id="discount-percent">-<?= $discount ?>%</span>
      <?php else: ?>
        <s class="was" id="was-price" hidden></s>
        <span class="off" id="discount-percent" hidden></span>
      <?php endif; ?>
    </p>
    <?php if ($product['description']): ?><p class="desc"><?= nl2br(e($product['description'])) ?></p><?php endif; ?>
    <?php if ($cartError): ?><p class="notice notice-error" role="alert"><?= e($cartError) ?></p><?php endif; ?>
    <?php if ($variants): ?>
    <form method="post" class="purchase-form stack" data-options-form>
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
      <label>Choose an option
        <select name="variant_id" required data-variant-select>
          <option value="">Select size / color</option>
          <?php foreach ($variants as $variant): ?>
            <?php
              $labelParts = [];
              if ($variant['size']) { $labelParts[] = 'Size ' . $variant['size']; }
              if ($variant['color']) { $labelParts[] = 'Color ' . $variant['color']; }
              $label = $labelParts ? implode(' · ', $labelParts) : 'SKU ' . $variant['sku'];
            ?>
            <option value="<?= (int) $variant['id'] ?>" data-price="<?= e((string) $variant['current_price']) ?>" data-regular-price="<?= e((string) $variant['regular_price']) ?>" data-stock="<?= (int) $variant['stock'] ?>" <?= (int) $variant['stock'] < 1 ? 'disabled' : '' ?>><?= e($label) ?><?= (int) $variant['stock'] < 1 ? ' · Out of stock' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Quantity<input type="number" name="quantity" value="1" min="1" max="1" data-quantity-input required></label>
      <p class="stock-note" data-stock-note role="status">Select an option to see availability.</p>
      <button class="btn" type="submit" data-add-button disabled>Add to cart</button>
    </form>
    <?php else: ?>
      <p class="stock-note">This item is not available to order yet.</p>
      <a href="<?= e(url('cart.php')) ?>">View cart</a>
    <?php endif; ?>
  </section>
</div>
<section class="rating-section" id="ratings">
  <div>
    <p class="eyebrow">CUSTOMER FEEDBACK</p>
    <h2>Rate this product</h2>
    <p class="rating-total">
      <?php if ((int) $ratingSummary['rating_count'] > 0): ?>
        Average <?= e(number_format((float) $ratingSummary['rating_average'], 1)) ?> out of 5 from <?= (int) $ratingSummary['rating_count'] ?> rating<?= (int) $ratingSummary['rating_count'] === 1 ? '' : 's' ?>.
      <?php else: ?>Be the first to rate it.<?php endif; ?>
    </p>
  </div>
  <?php if ($ratingError): ?><p class="notice notice-error" role="alert"><?= e($ratingError) ?></p><?php endif; ?>
  <?php if ($viewer && $viewer['role'] === 'customer'): ?>
    <form method="post" class="rating-form">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="rate">
      <fieldset class="rating-fieldset">
        <legend><?= $myRating ? 'Update your rating' : 'Your rating' ?></legend>
        <div class="rating-choices">
          <?php for ($star = 1; $star <= 5; $star++): ?>
            <label><input type="radio" name="rating" value="<?= $star ?>" <?= $myRating !== false && $myRating !== null && (int) $myRating === $star ? 'checked' : '' ?> required><span aria-hidden="true">&#9733;</span><span class="visually-hidden"><?= $star ?> star<?= $star === 1 ? '' : 's' ?></span></label>
          <?php endfor; ?>
        </div>
      </fieldset>
      <button class="btn" type="submit"><?= $myRating ? 'Update rating' : 'Submit rating' ?></button>
    </form>
  <?php else: ?>
    <?php $ratingNext = 'product.php?id=' . $id; ?>
    <p><a class="btn" href="<?= e(url('login.php?next=' . rawurlencode($ratingNext))) ?>">Sign in to rate</a>
      <a href="<?= e(url('register.php?next=' . rawurlencode($ratingNext))) ?>">Create an account</a></p>
  <?php endif; ?>
</section>
<script src="<?= e(url('assets/js/gallery.js?v=' . filemtime(__DIR__ . '/assets/js/gallery.js'))) ?>"></script>
<script src="<?= e(url('assets/js/product-options.js')) ?>"></script>
<?php storefront_footer();

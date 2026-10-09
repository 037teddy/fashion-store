<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/storefront_layout.php';

$search = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$dealsOnly = (string) ($_GET['deal'] ?? '') === '1';
$where = ['p.is_active = 1'];
$params = [];
if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term);
}
if ($category !== '') {
    $where[] = 'c.slug = ?';
    $params[] = $category;
}
if ($dealsOnly) {
    $where[] = 'p.promo_price IS NOT NULL AND p.promo_price < p.price';
}
$sql = "SELECT p.id, p.name, p.description, p.price, p.promo_price, c.name AS category_name,
               (SELECT AVG(r.rating) FROM product_ratings r WHERE r.product_id = p.id) AS rating_average,
               (SELECT COUNT(*) FROM product_ratings r WHERE r.product_id = p.id) AS rating_count,
               (SELECT pm.file_path FROM product_media pm WHERE pm.product_id = p.id AND pm.type = 'image' ORDER BY pm.sort_order, pm.id LIMIT 1) AS image_path,
               COALESCE((SELECT MIN(COALESCE(v.price, CASE WHEN p.promo_price IS NOT NULL AND p.promo_price < p.price THEN p.promo_price ELSE p.price END))
                         FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1 AND v.stock > 0),
                        CASE WHEN p.promo_price IS NOT NULL AND p.promo_price < p.price THEN p.promo_price ELSE p.price END) AS display_price,
               (SELECT COALESCE(SUM(v.stock), 0) FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1) AS stock
        FROM products p LEFT JOIN categories c ON c.id = p.category_id
        WHERE " . implode(' AND ', $where) . " ORDER BY p.created_at DESC, p.id DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
$dealStmt = db()->query(
  "SELECT p.id, p.name, p.price,
      (SELECT AVG(r.rating) FROM product_ratings r WHERE r.product_id = p.id) AS rating_average,
      (SELECT COUNT(*) FROM product_ratings r WHERE r.product_id = p.id) AS rating_count,
      COALESCE((SELECT MIN(COALESCE(v.price, CASE WHEN p.promo_price IS NOT NULL AND p.promo_price < p.price THEN p.promo_price ELSE p.price END))
            FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1 AND v.stock > 0),
           CASE WHEN p.promo_price IS NOT NULL AND p.promo_price < p.price THEN p.promo_price ELSE p.price END) AS display_price,
            (SELECT pm.file_path FROM product_media pm WHERE pm.product_id = p.id AND pm.type = 'image' ORDER BY pm.sort_order, pm.id LIMIT 1) AS image_path,
            (SELECT COALESCE(SUM(v.stock), 0) FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1) AS stock
   FROM products p WHERE p.is_active = 1
   HAVING display_price < p.price ORDER BY p.created_at DESC LIMIT 4"
);
$deals = $dealStmt->fetchAll();
$womenSpotlightImages = [
  'Stylish Black Dress' => 'Stylish black dress.jpg',
  'Stylish Dress' => 'Stylish dress.jpg',
  'Colorful Dresses' => 'colorful dresses.jpg',
];
$womenStmt = db()->prepare(
  "SELECT p.id, p.name, p.price, p.promo_price,
      (SELECT AVG(r.rating) FROM product_ratings r WHERE r.product_id = p.id) AS rating_average,
      (SELECT COUNT(*) FROM product_ratings r WHERE r.product_id = p.id) AS rating_count,
      COALESCE((SELECT MIN(COALESCE(v.price, CASE WHEN p.promo_price IS NOT NULL AND p.promo_price < p.price THEN p.promo_price ELSE p.price END))
            FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1 AND v.stock > 0),
           CASE WHEN p.promo_price IS NOT NULL AND p.promo_price < p.price THEN p.promo_price ELSE p.price END) AS display_price,
      (SELECT COALESCE(SUM(v.stock), 0) FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1) AS stock
   FROM products p JOIN categories c ON c.id = p.category_id
   WHERE p.is_active = 1 AND c.name = 'Women' AND p.name IN (?, ?, ?)
   ORDER BY FIELD(p.name, ?, ?, ?)"
);
$womenStmt->execute([
  'Stylish Black Dress', 'Stylish Dress', 'Colorful Dresses',
  'Stylish Black Dress', 'Stylish Dress', 'Colorful Dresses',
]);
$womenSpotlight = $womenStmt->fetchAll();
$categories = db()->query(
    'SELECT c.name, c.slug, COUNT(p.id) AS product_count FROM categories c
     LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
     GROUP BY c.id, c.name, c.slug ORDER BY c.name LIMIT 6'
)->fetchAll();

storefront_header('Fashion, delivered', $search);
?>
<section class="home-hero">
  <div class="hero-copy"><p class="eyebrow">THE NEW-SEASON EDIT</p><h1>Good style.<br>Every single day.</h1><p>Fresh finds for workdays, weekends, and everywhere in between.</p><a class="hero-cta" href="#shop-now">Shop the collection <span aria-hidden="true">&#8594;</span></a></div>
  <div class="hero-caption"><span>STYLE THAT MOVES WITH YOU</span><span>01 / NEW ARRIVALS</span></div>
</section>
<section class="category-strip" aria-label="Shop by category">
  <div class="section-heading"><div><p class="eyebrow">FIND YOUR LANE</p><h2>Shop by category</h2></div><a href="#shop-now">View all <span aria-hidden="true">&#8594;</span></a></div>
  <div class="category-list">
    <?php if ($categories): foreach ($categories as $cat): ?>
      <a class="category-tile" href="<?= e(url('index.php?category=' . rawurlencode($cat['slug']))) ?>"><span class="category-symbol" aria-hidden="true"><?= e(strtoupper(substr($cat['name'], 0, 1))) ?></span><span><strong><?= e($cat['name']) ?></strong><small><?= (int) $cat['product_count'] ?> styles</small></span><b aria-hidden="true">&#8599;</b></a>
    <?php endforeach; else: ?>
      <a class="category-tile" href="<?= e(url('index.php?category=shoes')) ?>"><span class="category-symbol">S</span><span><strong>Shoes</strong><small>Step out in style</small></span><b aria-hidden="true">&#8599;</b></a>
      <a class="category-tile" href="<?= e(url('index.php?category=womens-clothing')) ?>"><span class="category-symbol">W</span><span><strong>Women</strong><small>New-season looks</small></span><b aria-hidden="true">&#8599;</b></a>
    <?php endif; ?>
  </div>
</section>
<?php if ($deals || $womenSpotlight): ?>
<section class="deal-band">
  <div class="section-heading"><div><p class="eyebrow">A LITTLE SOMETHING EXTRA</p><h2>Worth a second look</h2></div><a href="<?= e(url('index.php?deal=1')) ?>">All deals <span aria-hidden="true">&#8594;</span></a></div>
  <div class="product-grid deal-grid">
    <?php foreach ($deals as $deal): ?>
      <a class="product-card" href="<?= e(url('product.php?id=' . (int) $deal['id'])) ?>">
        <span class="product-visual">
          <?php if ($deal['image_path']): ?><img src="<?= e(UPLOAD_URL . '/' . $deal['image_path']) ?>" alt="<?= e($deal['name']) ?>" loading="lazy">
          <?php else: ?><span class="product-art" aria-hidden="true">FS</span><?php endif; ?>
          <span class="sale-tag">-<?= (int) round((1 - (float) $deal['display_price'] / (float) $deal['price']) * 100) ?>%</span>
        </span>
        <span class="product-card-copy"><strong><?= e($deal['name']) ?></strong><span class="product-card-price"><?= e(money_kes((float) $deal['display_price'])) ?><s><?= e(money_kes((float) $deal['price'])) ?></s></span><small class="product-rating"><?= (int) $deal['rating_count'] > 0 ? '&#9733; ' . e(number_format((float) $deal['rating_average'], 1)) . ' (' . (int) $deal['rating_count'] . ')' : 'No ratings yet' ?></small><small><?= (int) $deal['stock'] > 0 ? 'In stock · Shop now' : 'Stock being updated' ?></small></span>
      </a>
    <?php endforeach; ?>
    <?php foreach ($womenSpotlight as $featured): ?>
      <?php $featuredOnSale = (float) $featured['display_price'] < (float) $featured['price']; ?>
      <a class="product-card" href="<?= e(url('product.php?id=' . (int) $featured['id'])) ?>">
        <span class="product-visual">
          <img src="<?= e(url('images/women/' . $womenSpotlightImages[$featured['name']])) ?>" alt="<?= e($featured['name']) ?>" loading="lazy">
          <?php if ($featuredOnSale): ?><span class="sale-tag">-<?= (int) round((1 - (float) $featured['display_price'] / (float) $featured['price']) * 100) ?>%</span><?php endif; ?>
        </span>
        <span class="product-card-copy"><strong><?= e($featured['name']) ?></strong><span class="product-card-price"><?= e(money_kes((float) $featured['display_price'])) ?><?php if ($featuredOnSale): ?><s><?= e(money_kes((float) $featured['price'])) ?></s><?php endif; ?></span><small class="product-rating"><?= (int) $featured['rating_count'] > 0 ? '&#9733; ' . e(number_format((float) $featured['rating_average'], 1)) . ' (' . (int) $featured['rating_count'] . ')' : 'No ratings yet' ?></small><small><?= (int) $featured['stock'] > 0 ? 'In stock · Shop now' : 'Stock being updated' ?></small></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<section class="catalog-section" id="shop-now">
  <div class="section-heading"><div><p class="eyebrow"><?= $search !== '' ? 'SEARCH RESULTS' : ($category !== '' ? 'CATEGORY EDIT' : ($dealsOnly ? 'SPECIAL PRICES' : 'THE EVERYDAY EDIT')) ?></p><h2><?= $search !== '' ? 'Results for “' . e($search) . '”' : ($category !== '' ? 'Shop this category' : ($dealsOnly ? 'Current deals' : 'Picked for you')) ?></h2></div><span class="result-count"><?= count($products) ?> styles</span></div>
  <?php if ($products): ?>
  <div class="product-grid">
    <?php foreach ($products as $product): ?>
      <?php $onSale = (float) $product['display_price'] < (float) $product['price']; ?>
      <a class="product-card" href="<?= e(url('product.php?id=' . (int) $product['id'])) ?>">
        <span class="product-visual">
          <?php if ($product['image_path']): ?><img src="<?= e(UPLOAD_URL . '/' . $product['image_path']) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
          <?php else: ?><span class="product-art" aria-hidden="true"><?= e(strtoupper(substr($product['name'], 0, 1))) ?></span><?php endif; ?>
          <?php if ($onSale): ?><span class="sale-tag">-<?= (int) round((1 - (float) $product['display_price'] / (float) $product['price']) * 100) ?>%</span><?php endif; ?>
        </span>
        <span class="product-card-copy">
          <small class="product-category"><?= e($product['category_name'] ?: 'Fashion') ?></small>
          <strong><?= e($product['name']) ?></strong>
          <span class="product-card-price"><?= e(money_kes((float) $product['display_price'])) ?><?php if ($onSale): ?><s><?= e(money_kes((float) $product['price'])) ?></s><?php endif; ?></span>
          <small class="product-rating"><?= (int) $product['rating_count'] > 0 ? '&#9733; ' . e(number_format((float) $product['rating_average'], 1)) . ' (' . (int) $product['rating_count'] . ')' : 'No ratings yet' ?></small>
          <small><?= (int) $product['stock'] > 0 ? 'In stock · Choose options' : 'Out of stock' ?></small>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <div class="empty-state"><div class="empty-mark">FS</div><h2>No styles found just yet.</h2><p>Try a different search or browse all fashion.</p><a class="btn" href="<?= e(url()) ?>">View all styles</a></div>
  <?php endif; ?>
</section>
<?php storefront_footer();

<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/images.php';
require __DIR__ . '/../includes/video_generator.php';
require __DIR__ . '/../includes/admin_layout.php';
require_admin();

$productId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = db()->prepare('SELECT id, name FROM products WHERE id = ?');
$stmt->execute([$productId]);
$product = $stmt->fetch();
if (!$product) {
    http_response_code(404);
    exit('Product not found.');
}
$self = url('admin/product_media.php?id=' . $productId);

/** Ordered photos, and a hash that changes whenever the photo list a video depends on changes. */
function product_images(int $productId): array
{
    $s = db()->prepare("SELECT id, file_path FROM product_media WHERE product_id = ? AND type = 'image' ORDER BY sort_order, id");
    $s->execute([$productId]);
    return $s->fetchAll();
}
function video_source_hash(array $images): string
{
    $paths = array_column(array_slice($images, 0, VIDEO_MAX_PHOTOS), 'file_path');
    return hash('sha256', implode('|', $paths) . '|' . VIDEO_SIZE . '|' . VIDEO_SLIDE_SECONDS . '|' . VIDEO_FADE_SECONDS . '|v1');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'upload') {
        $files = $_FILES['images'] ?? null;
        $existing = count(product_images($productId));
        $saved = 0;
        $errors = [];
        if ($files && is_array($files['name'])) {
            $next = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) FROM product_media WHERE product_id = ' . $productId)->fetchColumn();
            foreach ($files['name'] as $i => $origName) {
                if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) { continue; }
                if ($existing + $saved >= MAX_IMAGES_PER_PRODUCT) {
                    $errors[] = 'Limit of ' . MAX_IMAGES_PER_PRODUCT . ' photos reached.';
                    break;
                }
                $file = ['name' => $origName, 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                try {
                    $rel = save_uploaded_image($file, $productId);
                    $ins = db()->prepare("INSERT INTO product_media (product_id, type, source, file_path, sort_order) VALUES (?, 'image', 'uploaded', ?, ?)");
                    $ins->execute([$productId, $rel, ++$next]);
                    $saved++;
                } catch (RuntimeException $ex) {
                    $errors[] = $ex->getMessage();
                }
            }
        }
        if ($saved) { flash('ok', $saved . ' photo' . ($saved > 1 ? 's' : '') . ' added.'); }
        foreach (array_unique($errors) as $msg) { flash('error', $msg); }
        if (!$saved && !$errors) { flash('error', 'Choose at least one photo.'); }

    } elseif ($action === 'delete') {
        $mid = (int) ($_POST['media_id'] ?? 0);
        $s = db()->prepare('SELECT id, file_path, poster_path FROM product_media WHERE id = ? AND product_id = ?');
        $s->execute([$mid, $productId]);
        if ($m = $s->fetch()) {
            db()->prepare('DELETE FROM product_media WHERE id = ?')->execute([$mid]);
            delete_upload($m['file_path']);
            delete_upload($m['poster_path']);
            flash('ok', 'Removed.');
        }

    } elseif ($action === 'generate') {
        $lock = db()->prepare('SELECT GET_LOCK(?, 0)');
        $lock->execute(['video_gen_' . $productId]);
        if (!(int) $lock->fetchColumn()) {
            flash('error', 'A video is already being generated for this product. Wait a moment.');
        } else {
            try {
                $images = product_images($productId);
                if (count($images) < VIDEO_MIN_PHOTOS) {
                    flash('error', 'Add at least ' . VIDEO_MIN_PHOTOS . ' photos first.');
                } else {
                    $abs = array_map(fn($i) => UPLOAD_DIR . '/' . $i['file_path'], array_slice($images, 0, VIDEO_MAX_PHOTOS));
                    $base = bin2hex(random_bytes(6));
                    $relVideo = "videos/{$productId}/slideshow-{$base}.mp4";
                    $relPoster = "videos/{$productId}/slideshow-{$base}.jpg";

                    $r = generate_slideshow($abs, UPLOAD_DIR . '/' . $relVideo, UPLOAD_DIR . '/' . $relPoster);
                    if (!$r['ok']) {
                        flash('error', $r['error']);
                    } else {
                        $old = db()->prepare("SELECT id, file_path, poster_path FROM product_media WHERE product_id = ? AND type = 'video' AND source = 'generated'");
                        $old->execute([$productId]);
                        $oldRows = $old->fetchAll();

                        $ins = db()->prepare(
                            "INSERT INTO product_media (product_id, type, source, file_path, poster_path, duration, source_hash, sort_order)
                             VALUES (?, 'video', 'generated', ?, ?, ?, ?, 0)"
                        );
                        $ins->execute([$productId, $relVideo, $r['poster'] ? $relPoster : null, $r['duration'], video_source_hash($images)]);

                        foreach ($oldRows as $o) {   // replace the previous generated video
                            db()->prepare('DELETE FROM product_media WHERE id = ?')->execute([$o['id']]);
                            delete_upload($o['file_path']);
                            delete_upload($o['poster_path']);
                        }
                        flash('ok', 'Video created (' . $r['duration'] . ' seconds).');
                    }
                }
            } finally {
                db()->prepare('SELECT RELEASE_LOCK(?)')->execute(['video_gen_' . $productId]);
            }
        }
    }
    header('Location: ' . $self);
    exit;
}

$images = product_images($productId);
$vs = db()->prepare("SELECT * FROM product_media WHERE product_id = ? AND type = 'video' ORDER BY (source = 'generated') DESC, id DESC LIMIT 1");
$vs->execute([$productId]);
$video = $vs->fetch() ?: null;
$outdated = $video && $video['source'] === 'generated' && $video['source_hash'] !== video_source_hash($images);
$usable = min(count($images), VIDEO_MAX_PHOTOS);

admin_header('Photos & video · ' . $product['name']);
?>
<p><a href="<?= e(url('admin/index.php')) ?>">&larr; All products</a></p>
<h1><?= e($product['name']) ?></h1>

<section class="card">
  <h2>Photos</h2>
  <?php if ($images): ?>
  <ul class="photo-grid">
    <?php foreach ($images as $n => $img): ?>
      <li>
        <img src="<?= e(UPLOAD_URL . '/' . $img['file_path']) ?>" alt="Photo <?= $n + 1 ?>" loading="lazy">
        <form method="post" onsubmit="return confirm('Remove this photo?')">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $productId ?>">
          <input type="hidden" name="action" value="delete"><input type="hidden" name="media_id" value="<?= (int) $img['id'] ?>">
          <span class="muted"><?= $n + 1 ?><?= $n >= VIDEO_MAX_PHOTOS ? ' (not in video)' : '' ?></span>
          <button class="link-btn danger" type="submit">Remove</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?>
    <p class="muted">No photos yet.</p>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $productId ?>"><input type="hidden" name="action" value="upload">
    <label>Add photos (JPG, PNG or WebP, up to <?= (int) (MAX_IMAGE_BYTES / 1048576) ?> MB each)
      <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required>
    </label>
    <button class="btn btn-secondary" type="submit">Upload</button>
  </form>
</section>

<section class="card">
  <h2>Product video</h2>
  <?php if ($video): ?>
    <video class="admin-video" controls playsinline preload="metadata"
           <?= $video['poster_path'] ? 'poster="' . e(UPLOAD_URL . '/' . $video['poster_path']) . '"' : '' ?>
           src="<?= e(UPLOAD_URL . '/' . $video['file_path']) ?>"></video>
    <p class="muted"><?= $video['source'] === 'generated' ? 'Generated slideshow' : 'Uploaded video' ?>, <?= e((string) $video['duration']) ?> seconds.</p>
    <?php if ($outdated): ?>
      <p class="notice notice-warn">The photos changed since this video was made. Regenerate it to match.</p>
    <?php endif; ?>
  <?php else: ?>
    <p class="muted">No video yet.</p>
  <?php endif; ?>

  <form method="post" id="gen-form" class="stack">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $productId ?>"><input type="hidden" name="action" value="generate">
    <button class="btn" type="submit" id="gen-btn" <?= $usable < VIDEO_MIN_PHOTOS ? 'disabled' : '' ?>>
      <?= $video && $video['source'] === 'generated' ? 'Regenerate video' : 'Generate video' ?>
    </button>
    <p class="muted">
      <?php if ($usable < VIDEO_MIN_PHOTOS): ?>
        Add at least <?= VIDEO_MIN_PHOTOS ?> photos to enable this.
      <?php else: ?>
        Uses the first <?= $usable ?> photos in order, about <?= e(number_format(slideshow_estimated_seconds($usable), 1)) ?> seconds, square 1080 × 1080, no sound.
        This can take up to a minute.
      <?php endif; ?>
    </p>
  </form>
</section>
<script>
  document.getElementById('gen-form').addEventListener('submit', function () {
    var b = document.getElementById('gen-btn');
    b.disabled = true; b.textContent = 'Generating… please wait';
    // the button is disabled after the form data is collected, so the POST still goes through
  });
</script>
<?php admin_footer();

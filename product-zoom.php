<?php
// product-zoom.php
// Standalone product gallery demo with mouse-over zoom for fashion store.

// 1. Pick images: check user's specified paths, fallback to existing store uploads, or fallback to SVG
$candidateImages = [
    'uploads/images/tshirt-front.jpg',
    'uploads/images/tshirt-back.jpg',
    'uploads/images/tshirt-detail.jpg'
];

$storeImages = [
    'uploads/products/1/2eabc10af34e0a0e.jpg',
    'uploads/products/1/63aa2cb20718e07f.jpg',
    'uploads/products/1/b6e692722dacacac.jpg',
    'uploads/products/1/f5a99c480ab8f18c.jpg'
];

function makeSvgPlaceholder(string $title, string $color = '#4f46e5', string $bg = '#f8fafc'): string {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800" width="800" height="800">'
         . '<rect width="100%" height="100%" fill="' . $bg . '"/>'
         . '<defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">'
         . '<stop offset="0%" stop-color="' . $color . '" stop-opacity="0.8"/>'
         . '<stop offset="100%" stop-color="' . $color . '" stop-opacity="0.3"/>'
         . '</linearGradient></defs>'
         . '<path d="M260 210 L340 170 Q400 220 460 170 L540 210 L500 320 L450 300 L450 630 L350 630 L350 300 L300 320 Z" fill="url(#g)" stroke="' . $color . '" stroke-width="4"/>'
         . '<circle cx="400" cy="400" r="40" fill="#ffffff" opacity="0.8"/>'
         . '<text x="50%" y="710" dominant-baseline="middle" text-anchor="middle" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="34" font-weight="700" fill="#334155">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</text>'
         . '</svg>';
    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

$images = [];
foreach ($candidateImages as $img) {
    if (is_file(__DIR__ . '/' . $img)) {
        $images[] = $img;
    }
}

if (empty($images)) {
    foreach ($storeImages as $img) {
        if (is_file(__DIR__ . '/' . $img)) {
            $images[] = $img;
        }
    }
}

if (empty($images)) {
    $images = [
        makeSvgPlaceholder('Front View - Heavyweight Tee', '#ea580c', '#fff7ed'),
        makeSvgPlaceholder('Back View - Heavyweight Tee', '#0284c7', '#f0f9ff'),
        makeSvgPlaceholder('Collar & Stitch Detail', '#16a34a', '#f0fdf4')
    ];
}

$product = [
    'name' => 'Premium Heavyweight Cotton T-Shirt',
    'price' => 800,
    'description' => 'Crafted from 100% premium combed cotton. Features a relaxed tailored fit, reinforced ribbed crew neckline, and double-needle stitching for lasting durability. Perfect for everyday wear.',
    'images' => $images
];

$firstImage = $images[0] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?> - Product Zoom</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 30px 16px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: #1e293b;
    background: #f1f5f9;
}

.product-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(280px, 0.85fr);
    gap: 36px;
    max-width: 1100px;
    margin: 20px auto;
    padding: 28px;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
    position: relative;
    align-items: start;
}

/* Gallery Section */
.product-gallery {
    position: relative;
    min-width: 0;
}

.gallery-stage-wrapper {
    position: relative;
    width: 100%;
}

.main-image-frame {
    position: relative;
    width: 100%;
    aspect-ratio: 1 / 1;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
    cursor: crosshair;
    user-select: none;
}

.main-image {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
    transition: opacity 0.15s ease;
    pointer-events: none;
}

/* Semi-transparent lens indicating the zoom area */
.zoom-lens {
    position: absolute;
    display: none;
    border: 2px solid #ff5722;
    background: rgba(255, 87, 34, 0.2);
    border-radius: 4px;
    pointer-events: none;
    z-index: 10;
    box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.7);
}

/* Magnified flyout preview window */
.zoom-preview {
    position: absolute;
    top: 0;
    left: calc(100% + 24px);
    width: 100%;
    height: 100%;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background-color: #ffffff;
    background-repeat: no-repeat;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.18);
    display: none;
    z-index: 99;
    pointer-events: none;
}

/* Zoom Mode Badge */
.zoom-hint {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
    font-size: 13px;
    color: #64748b;
}

.zoom-hint svg {
    width: 16px;
    height: 16px;
    fill: currentColor;
}

/* Thumbnails */
.thumbnails {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 14px;
}

.thumbnail {
    width: 74px;
    height: 74px;
    padding: 3px;
    background: #ffffff;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: border-color 0.15s ease, transform 0.15s ease;
}

.thumbnail:hover {
    border-color: #cbd5e1;
    transform: translateY(-1px);
}

.thumbnail.active {
    border-color: #ff5722;
    transform: translateY(-2px);
}

.thumbnail img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
}

/* Product Info */
.product-info {
    min-width: 0;
}

.product-info h1 {
    margin: 0 0 12px;
    font-size: 28px;
    line-height: 1.25;
    color: #0f172a;
}

.price {
    margin: 14px 0 18px;
    color: #e64722;
    font-size: 28px;
    font-weight: 700;
}

.description {
    line-height: 1.6;
    color: #475569;
    margin-bottom: 24px;
    font-size: 15px;
}

.action-button {
    display: block;
    width: 100%;
    padding: 14px;
    margin-top: 12px;
    border: 0;
    border-radius: 8px;
    background: #ff5722;
    color: white;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.15s ease, transform 0.1s ease;
}

.action-button:hover {
    background: #ea580c;
}

.action-button:active {
    transform: scale(0.99);
}

.action-button.buy {
    background: #0f172a;
}

.action-button.buy:hover {
    background: #1e293b;
}

.action-button:focus-visible,
.thumbnail:focus-visible {
    outline: 3px solid #2563eb;
    outline-offset: 3px;
}

/* Mobile Modal */
.image-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(15, 23, 42, 0.95);
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.image-modal.open {
    display: flex;
}

.image-modal img {
    max-width: 95%;
    max-height: 85vh;
    object-fit: contain;
    border-radius: 6px;
}

.close-modal {
    position: absolute;
    top: 20px;
    right: 24px;
    border: 0;
    background: transparent;
    color: white;
    font-size: 38px;
    line-height: 1;
    cursor: pointer;
}

/* Responsive */
@media (max-width: 860px) {
    body {
        padding: 14px 10px;
    }

    .product-layout {
        grid-template-columns: minmax(0, 1fr);
        gap: 24px;
        padding: 16px;
        margin: 10px auto;
    }

    .zoom-preview,
    .zoom-lens {
        display: none !important;
    }

    .main-image-frame {
        cursor: zoom-in;
    }

    .zoom-hint {
        display: none;
    }
}
</style>
</head>

<body>

<main class="product-layout">

    <section class="product-gallery" aria-label="Product images">

        <div class="gallery-stage-wrapper">
            <div class="main-image-frame" id="imageFrame">
                <img
                    src="<?= htmlspecialchars($firstImage, ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"
                    class="main-image"
                    id="mainImage"
                >
                <div class="zoom-lens" id="zoomLens"></div>
            </div>

            <!-- Flyout Zoom Preview Window -->
            <div
                class="zoom-preview"
                id="zoomPreview"
                role="region"
                aria-label="Magnified product preview"
            ></div>
        </div>

        <div class="zoom-hint">
            <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
            Hover and move your mouse over the photo to zoom
        </div>

        <div class="thumbnails" aria-label="Choose product image">
            <?php foreach ($images as $index => $image): ?>
                <button
                    type="button"
                    class="thumbnail <?= $index === 0 ? 'active' : '' ?>"
                    data-image="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>"
                    aria-label="View product image <?= $index + 1 ?>"
                    aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"
                >
                    <img
                        src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>"
                        alt=""
                    >
                </button>
            <?php endforeach; ?>
        </div>

    </section>

    <section class="product-info">

        <h1><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h1>

        <p class="price">KSh <?= number_format($product['price']) ?></p>

        <p class="description">
            <?= htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8') ?>
        </p>

        <button type="button" class="action-button" onclick="alert('Added to cart!');">
            Add to Cart
        </button>

        <button type="button" class="action-button buy" onclick="alert('Proceeding to checkout...');">
            Buy Now
        </button>

    </section>

</main>

<!-- Mobile Fullscreen Viewer -->
<div class="image-modal" id="imageModal" role="dialog" aria-modal="true" aria-label="Enlarged product image">
    <button type="button" class="close-modal" id="closeModal" aria-label="Close image viewer">&times;</button>
    <img src="" alt="" id="modalImage">
</div>

<script>
(function() {
    const frame = document.getElementById('imageFrame');
    const mainImage = document.getElementById('mainImage');
    const lens = document.getElementById('zoomLens');
    const zoomPreview = document.getElementById('zoomPreview');
    const thumbnails = document.querySelectorAll('.thumbnail');

    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    const closeModal = document.getElementById('closeModal');

    // Zoom magnification factor (2.5x)
    const zoomLevel = 2.5;

    function isDesktopPointer() {
        return window.matchMedia('(hover: hover) and (pointer: fine) and (min-width: 861px)').matches;
    }

    function syncZoomDimensions() {
        if (!mainImage) return;

        const frameRect = frame.getBoundingClientRect();

        // Calculate lens dimensions proportional to the zoomLevel
        const lensWidth = frameRect.width / zoomLevel;
        const lensHeight = frameRect.height / zoomLevel;

        lens.style.width = lensWidth + 'px';
        lens.style.height = lensHeight + 'px';

        // Pre-configure the preview window background
        zoomPreview.style.backgroundImage = `url("${mainImage.src}")`;
        zoomPreview.style.backgroundSize = `${frameRect.width * zoomLevel}px ${frameRect.height * zoomLevel}px`;
    }

    function handleMouseMove(e) {
        if (!isDesktopPointer() || !mainImage) return;

        const frameRect = frame.getBoundingClientRect();
        const lensWidth = lens.offsetWidth;
        const lensHeight = lens.offsetHeight;

        // Position lens centered on the cursor
        let lensX = e.clientX - frameRect.left - (lensWidth / 2);
        let lensY = e.clientY - frameRect.top - (lensHeight / 2);

        // Clamp lens within frame boundaries
        const maxX = frameRect.width - lensWidth;
        const maxY = frameRect.height - lensHeight;

        lensX = Math.max(0, Math.min(lensX, maxX));
        lensY = Math.max(0, Math.min(lensY, maxY));

        // Move lens with hardware-accelerated transform
        lens.style.transform = `translate3d(${lensX}px, ${lensY}px, 0)`;

        // Calculate ratio across the image (0% to 100%)
        const ratioX = maxX > 0 ? (lensX / maxX) * 100 : 0;
        const ratioY = maxY > 0 ? (lensY / maxY) * 100 : 0;

        zoomPreview.style.backgroundPosition = `${ratioX}% ${ratioY}%`;
    }

    // Activate zoom when mouse enters the main image
    frame.addEventListener('mouseenter', () => {
        if (!isDesktopPointer() || !mainImage) return;
        syncZoomDimensions();
        lens.style.display = 'block';
        zoomPreview.style.display = 'block';
    });

    // Update zoom position as the mouse moves over the image
    frame.addEventListener('mousemove', handleMouseMove);

    // Deactivate zoom when mouse leaves
    frame.addEventListener('mouseleave', () => {
        lens.style.display = 'none';
        zoomPreview.style.display = 'none';
    });

    // Thumbnail click to swap main image
    thumbnails.forEach(btn => {
        btn.addEventListener('click', () => {
            const newSrc = btn.dataset.image;
            if (!newSrc || !mainImage) return;

            mainImage.src = newSrc;
            zoomPreview.style.backgroundImage = `url("${newSrc}")`;

            thumbnails.forEach(t => {
                t.classList.remove('active');
                t.setAttribute('aria-pressed', 'false');
            });
            btn.classList.add('active');
            btn.setAttribute('aria-pressed', 'true');
        });
    });

    // Mobile / Tablet: tap to open fullscreen modal
    frame.addEventListener('click', () => {
        if (isDesktopPointer()) return;
        modalImage.src = mainImage.src;
        modalImage.alt = mainImage.alt;
        modal.classList.add('open');
    });

    function closeModalView() {
        modal.classList.remove('open');
    }

    closeModal.addEventListener('click', closeModalView);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModalView();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeModalView();
    });

    window.addEventListener('resize', () => {
        if (!isDesktopPointer()) {
            lens.style.display = 'none';
            zoomPreview.style.display = 'none';
        }
    });
})();
</script>

</body>
</html>

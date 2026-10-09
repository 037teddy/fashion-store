<?php
declare(strict_types=1);

function cart_quantities(): array
{
    return is_array($_SESSION['cart'] ?? null) ? $_SESSION['cart'] : [];
}

function cart_item_count(): int
{
    return array_sum(array_map('intval', cart_quantities()));
}

function storefront_cart(): array
{
    $quantities = cart_quantities();
    $ids = array_values(array_filter(array_map('intval', array_keys($quantities)), fn($id) => $id > 0));
    if (!$ids) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT v.id AS variant_id, v.product_id, v.sku, v.size, v.color, v.stock,
                v.is_active AS variant_active,
                COALESCE(v.price, CASE WHEN p.promo_price IS NOT NULL AND p.promo_price < p.price THEN p.promo_price ELSE p.price END) AS unit_price,
                p.name, p.is_active AS product_active,
                (SELECT pm.file_path FROM product_media pm WHERE pm.product_id = p.id AND pm.type = 'image' ORDER BY pm.sort_order, pm.id LIMIT 1) AS image_path
         FROM product_variants v JOIN products p ON p.id = v.product_id
         WHERE v.id IN ({$placeholders})"
    );
    $stmt->execute($ids);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['quantity'] = max(1, (int) ($quantities[$row['variant_id']] ?? 1));
        $parts = [];
        if ($row['size']) { $parts[] = 'Size ' . $row['size']; }
        if ($row['color']) { $parts[] = 'Color ' . $row['color']; }
        $row['variant_label'] = $parts ? implode(' · ', $parts) : 'SKU ' . $row['sku'];
        $row['available'] = (bool) $row['variant_active'] && (bool) $row['product_active'] && (int) $row['stock'] >= (int) $row['quantity'];
        $row['line_total'] = (float) $row['unit_price'] * (int) $row['quantity'];
        $rows[(int) $row['variant_id']] = $row;
    }
    return $rows;
}

function cart_subtotal(array $items): float
{
    return array_sum(array_map(fn($item) => (float) $item['line_total'], $items));
}

function add_to_cart(int $variantId, int $quantity): bool
{
    $stmt = db()->prepare(
        'SELECT v.stock FROM product_variants v JOIN products p ON p.id = v.product_id
         WHERE v.id = ? AND v.is_active = 1 AND p.is_active = 1'
    );
    $stmt->execute([$variantId]);
    $stock = $stmt->fetchColumn();
    if ($stock === false || (int) $stock < 1) {
        return false;
    }
    $cart = cart_quantities();
    $requested = max(1, (int) ($cart[$variantId] ?? 0) + $quantity);
    if ($requested > (int) $stock) {
        return false;
    }
    $cart[$variantId] = $requested;
    $_SESSION['cart'] = $cart;
    flash('ok', 'Added to your cart.');
    return true;
}

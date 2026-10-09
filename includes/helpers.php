<?php
declare(strict_types=1);

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function safe_customer_next(string $next): string
{
    if (in_array($next, ['checkout.php', 'orders.php'], true)
        || preg_match('/^product\.php\?id=[1-9][0-9]*$/D', $next) === 1
        || preg_match('/^(?:pay|order_complete)\.php\?number=FS-[0-9]{6}-[A-F0-9]{8}$/Di', $next) === 1) {
        return $next;
    }
    return 'index.php';
}

function money_kes(float $amount): string
{
    return 'KSh ' . number_format($amount, fmod($amount, 1.0) === 0.0 ? 0 : 2);
}

/** Kenyan mobile number -> normalized 2547XXXXXXXX / 2541XXXXXXXX. */
function normalize_ke_phone(string $phone): ?string
{
    $p = preg_replace('/[\s\-()]/', '', $phone);
    return preg_match('/^(?:\+?254|0)([17]\d{8})$/', (string) $p, $m) ? '254' . $m[1] : null;
}

function password_meets_requirements(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[a-z]/', $password) === 1
        && preg_match('/[0-9]/', $password) === 1
        && preg_match('/[^A-Za-z0-9]/', $password) === 1;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function pull_flash(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Your session expired. Go back, refresh the page and try again.');
    }
}

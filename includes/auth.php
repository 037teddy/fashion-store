<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

const AUTH_DUMMY_HASH = '$2y$10$wu50eIATu2qjc2ATegVAMOSQjjzzdt3hrKX5ddGO0M.13WpUn3kJu';

function login_fingerprint(string $email): string
{
    return hash('sha256', strtolower(trim($email)) . "\0" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

function login_is_locked(string $fingerprint): bool
{
    $stmt = db()->prepare('SELECT locked_until > NOW() FROM login_attempts WHERE fingerprint = ?');
    $stmt->execute([$fingerprint]);
    return (bool) $stmt->fetchColumn();
}

function record_login_failure(string $fingerprint): void
{
    $stmt = db()->prepare(
        'INSERT INTO login_attempts (fingerprint, attempts, window_started_at) VALUES (?, 1, NOW())
         ON DUPLICATE KEY UPDATE
           attempts = IF(window_started_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE), 1, attempts + 1),
           locked_until = IF(window_started_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE), NULL, locked_until),
           window_started_at = IF(window_started_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE), NOW(), window_started_at)'
    );
    $stmt->execute([$fingerprint]);
    db()->prepare(
        'UPDATE login_attempts SET locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
         WHERE fingerprint = ? AND attempts >= 5 AND locked_until IS NULL'
    )->execute([$fingerprint]);
}

function authenticate_user(string $identifier, string $password, string $requiredRole): ?array
{
    $identifier = trim($identifier);
    $email = strtolower($identifier);
    $phone = $requiredRole === 'customer' ? normalize_ke_phone($identifier) : null;
    $fingerprint = login_fingerprint($phone ?? $email);
    if (login_is_locked($fingerprint)) {
        password_verify($password, AUTH_DUMMY_HASH);
        return null;
    }

    if ($requiredRole === 'customer') {
        $stmt = db()->prepare('SELECT id, full_name, email, password_hash, role FROM users WHERE email = ? OR phone = ?');
        $stmt->execute([$email, $phone ?? '']);
    } else {
        $stmt = db()->prepare('SELECT id, full_name, email, password_hash, role FROM users WHERE email = ?');
        $stmt->execute([$email]);
    }
    $user = $stmt->fetch();
    $valid = password_verify($password, $user['password_hash'] ?? AUTH_DUMMY_HASH);

    if (!$user || !$valid || $user['role'] !== $requiredRole) {
        record_login_failure($fingerprint);
        return null;
    }

    db()->prepare('DELETE FROM login_attempts WHERE fingerprint = ?')->execute([$fingerprint]);
    return $user;
}

function begin_customer_verification(array $user, string $next): never
{
    $userId = (int) $user['id'];
    unset($_SESSION['uid'], $_SESSION['otp_verified_user_id']);
    $otp = sprintf('%06d', random_int(0, 999999));
    db()->prepare(
        'UPDATE users SET login_otp_hash = ?, login_otp_expires = DATE_ADD(NOW(), INTERVAL 15 MINUTE), login_otp_attempts = 0, login_otp_sent_at = NOW() WHERE id = ?'
    )->execute([password_hash($otp, PASSWORD_DEFAULT), $userId]);

    $_SESSION['pending_otp_user_id'] = $userId;
    $_SESSION['pending_otp_next'] = safe_customer_next($next);
    require_once __DIR__ . '/mailer.php';
    $emailBody = '<p>Hello ' . e((string) $user['full_name']) . ',</p><p>Your Fashion Store verification code is:</p>'
        . '<p style="font-size:28px;font-weight:bold;letter-spacing:6px">' . e($otp) . '</p>'
        . '<p>This code expires in 15 minutes. If you did not request it, you can ignore this email.</p>';
    if (!send_app_email((string) $user['email'], 'Fashion Store verification code', $emailBody)) {
        flash('error', 'We could not send your verification code. Check the email settings or try resending in one minute.');
    }

    redirect('otp_verify.php');
}

function establish_session(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
}

/** The signed-in user, re-read from the database on every request. */
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $stmt = db()->prepare('SELECT id, full_name, email, role FROM users WHERE id = ?');
            $stmt->execute([(int) $_SESSION['uid']]);
            $user = $stmt->fetch() ?: null;
        }
    }
    return $user;
}

/** Server-side admin gate: the role comes from the database, never from the browser. */
function require_admin(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('admin/login.php');
    }
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }
    return $user;
}

function require_customer(): array
{
    $user = current_user();
    if ($user === null) {
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $script = basename((string) (parse_url($requestUri, PHP_URL_PATH) ?: ''));
        $query = parse_url($requestUri, PHP_URL_QUERY);
        $candidate = $script . (is_string($query) && $query !== '' ? '?' . $query : '');
        $next = safe_customer_next($candidate);
        redirect('login.php?next=' . rawurlencode($next));
    }
    if ($user['role'] !== 'customer') {
        http_response_code(403);
        exit('Forbidden');
    }
    if ((int) ($_SESSION['otp_verified_user_id'] ?? 0) !== (int) $user['id']) {
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $script = basename((string) (parse_url($requestUri, PHP_URL_PATH) ?: ''));
        $query = parse_url($requestUri, PHP_URL_QUERY);
        $candidate = $script . (is_string($query) && $query !== '' ? '?' . $query : '');
        begin_customer_verification($user, safe_customer_next($candidate));
    }
    return $user;
}

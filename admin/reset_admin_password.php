<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

require __DIR__ . '/../includes/bootstrap.php';

[$script, $email, $newPassword] = array_pad($argv, 3, null);
if (!$email || !$newPassword) {
    fwrite(STDERR, "Usage: php admin/reset_admin_password.php email password\n");
    exit(1);
}

$email = strtolower(trim($email));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email.\n");
    exit(1);
}
if (strlen($newPassword) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

$stmt = db()->prepare(
    'UPDATE users SET password_hash = ? WHERE email = ? AND role = ?'
);
$stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $email, 'admin']);

if ($stmt->rowCount() !== 1) {
    fwrite(STDERR, "No admin account updated. Check the email and database.\n");
    exit(1);
}

echo "Admin password updated.\n";
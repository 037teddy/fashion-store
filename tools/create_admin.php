<?php
// Creates (or promotes) an administrator account. Run from the command line:
//   C:\xampp\php\php.exe tools\create_admin.php "Full Name" you@example.com 0712345678 "a-long-password"
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit('CLI only'); }

require __DIR__ . '/../includes/bootstrap.php';

[$script, $name, $email, $phone, $password] = array_pad($argv, 5, null);
if (!$name || !$email || !$phone || !$password) {
    fwrite(STDERR, "Usage: php tools/create_admin.php \"Full Name\" email phone password\n");
    exit(1);
}
$email = strtolower(trim($email));
$norm = normalize_ke_phone($phone);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { fwrite(STDERR, "Invalid email.\n"); exit(1); }
if ($norm === null) { fwrite(STDERR, "Invalid Kenyan phone number.\n"); exit(1); }
if (strlen($password) < 8) { fwrite(STDERR, "Password must be at least 8 characters.\n"); exit(1); }

$stmt = db()->prepare(
    "INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'admin')
     ON DUPLICATE KEY UPDATE role = 'admin', password_hash = VALUES(password_hash), full_name = VALUES(full_name)"
);
$stmt->execute([trim($name), $email, $norm, password_hash($password, PASSWORD_DEFAULT)]);
echo "Administrator ready: {$email}\n";

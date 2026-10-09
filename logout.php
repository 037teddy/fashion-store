<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    unset($_SESSION['uid']);
    unset($_SESSION['otp_verified_user_id'], $_SESSION['pending_otp_user_id'], $_SESSION['pending_otp_next']);
    session_regenerate_id(true);
}
redirect('index.php');

<?php
declare(strict_types=1);
// Swift Wallet / M-Pesa gateway configuration for Fashion Store.
// BASE_URL and BASE_PATH are defined in config/config.php (loaded via bootstrap.php).

$swift_api_key  = getenv('SWIFT_API_KEY') ?: '';
$swift_base_url = 'https://swiftwallet.co.ke/v3';

// Build the callback URL automatically from the current host/scheme.
// Works through ngrok/tunnels — Swift Wallet cannot reach localhost directly,
// but polling in check_status.php handles that case gracefully.
if (!defined('SWIFT_CALLBACK_URL')) {
    $scheme = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']))
        ? $_SERVER['HTTP_X_FORWARDED_PROTO']
        : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    // BASE_URL is e.g. "/fashion-store" (no trailing slash)
    define('SWIFT_CALLBACK_URL', $scheme . '://' . $host . BASE_URL . '/payments/callback.php');
}

$swift_stk_initiate_url = $swift_base_url . '/stk-initiate/';
$swift_transactions_url = $swift_base_url . '/transactions/';
$swift_callback_url     = SWIFT_CALLBACK_URL;

<?php
// ============================================================
// create-order.php — Razorpay order creation (FINAL)
// ============================================================

ob_start(); // 🔥 IMPORTANT

error_reporting(E_ALL);
ini_set('display_errors', 0); // 🔥 prevent HTML errors

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php-error.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';

// Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

// Read JSON input
$body = json_decode(file_get_contents('php://input'), true);

if (!isset($body['amount']) || !is_numeric($body['amount']) || (int)$body['amount'] <= 0) {
    jsonResponse(['success' => false, 'error' => 'Invalid or missing amount'], 400);
}

$amountRupees = (int) $body['amount'];
$amountPaise  = $amountRupees * 100;

// Prepare payload
$payload = json_encode([
    'amount'          => $amountPaise,
    'currency'        => CURRENCY,
    'receipt'         => generateOrderNumber(),
    'payment_capture' => 1,
]);

// Razorpay API call
$ch = curl_init('https://api.razorpay.com/v1/orders');

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
    CURLOPT_SSL_VERIFYPEER => false, // local fix
]);

$response = curl_exec($ch);

// Handle curl failure
if ($response === false) {
    $error = curl_error($ch);
    if (function_exists('curl_close')) {
    @curl_close($ch);
}

    jsonResponse([
        'success' => false,
        'error'   => 'Curl failed: ' . $error
    ], 500);
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Decode response
$rzpOrder = json_decode($response, true);

// Handle Razorpay error
if ($httpCode !== 200 || empty($rzpOrder['id'])) {
    $errMsg = $rzpOrder['error']['description'] ?? 'Razorpay order creation failed';

    jsonResponse([
        'success' => false,
        'error'   => $errMsg,
        'debug'   => $response
    ], 500);
}

// Success
jsonResponse([
    'success'  => true,
    'order_id' => $rzpOrder['id'],
    'amount'   => $amountPaise,
    'currency' => CURRENCY,
    'key_id'   => RAZORPAY_KEY_ID,
]);
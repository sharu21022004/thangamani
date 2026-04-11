<?php
// ============================================================
//  api/verify-payment.php — FINAL STABLE (NO PDF)
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mailer.php';

// Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

// ── Parse request ────────────────────────────────────────────
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

if ($body === null) {
    jsonResponse([
        'success' => false,
        'error' => 'Invalid JSON input'
    ], 400);
}

// Debug log
file_put_contents(
    __DIR__ . '/../logs/debug.log',
    json_encode($body) . "\n",
    FILE_APPEND
);

// ── Required fields ──────────────────────────────────────────
$required = [
    'razorpay_order_id',
    'razorpay_payment_id',
    'razorpay_signature',
    'customer_name',
    'customer_phone',
    'customer_email',
    'customer_city',
    'customer_pincode',
    'customer_address',
    'cart_items',
    'total_amount',
];

foreach ($required as $field) {
    if (empty($body[$field])) {
        jsonResponse(['success' => false, 'error' => "Missing field: {$field}"], 400);
    }
}

// ── Validate cart ────────────────────────────────────────────
if (!is_array($body['cart_items']) || count($body['cart_items']) === 0) {
    jsonResponse(['success' => false, 'error' => 'Cart is empty'], 400);
}

$totalAmount = (int) $body['total_amount'];
if ($totalAmount <= 0) {
    jsonResponse(['success' => false, 'error' => 'Invalid total amount'], 400);
}

// ── Signature verification ───────────────────────────────────
$orderId   = $body['razorpay_order_id'];
$paymentId = $body['razorpay_payment_id'];
$signature = $body['razorpay_signature'];

$generatedSignature = hash_hmac(
    'sha256',
    $orderId . "|" . $paymentId,
    RAZORPAY_KEY_SECRET
);

if (!hash_equals($generatedSignature, $signature)) {
    jsonResponse([
        'success' => false,
        'error' => 'Signature verification failed'
    ], 400);
}

// ── Build order ──────────────────────────────────────────────
$orderNumber = generateOrderNumber();

$order = [
    'order_number'      => $orderNumber,
    'payment_id'        => sanitize($paymentId),
    'razorpay_order_id' => sanitize($orderId),
    'customer_name'     => sanitize($body['customer_name']),
    'customer_phone'    => sanitize($body['customer_phone']),
    'customer_email'    => sanitize($body['customer_email']),
    'customer_city'     => sanitize($body['customer_city']),
    'customer_pincode'  => sanitize($body['customer_pincode']),
    'customer_address'  => sanitize($body['customer_address']),
    'cart_items'        => array_map('sanitize', $body['cart_items']),
    'total_amount'      => $totalAmount,
    'created_at'        => date('Y-m-d H:i:s'),
];

// ── Save order ───────────────────────────────────────────────
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0750, true);
}

file_put_contents(
    $logDir . '/orders.jsonl',
    json_encode($order, JSON_UNESCAPED_UNICODE) . "\n",
    FILE_APPEND | LOCK_EX
);

// ── Send emails ──────────────────────────────────────────────
$emailResult = sendOrderEmail($order);

// ── Response ─────────────────────────────────────────────────
jsonResponse([
    'success' => true,
    'order_number' => $orderNumber,
    'email_sent' => $emailResult['success']
]);
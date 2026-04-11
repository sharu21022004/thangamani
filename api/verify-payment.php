<?php
// ============================================================
//  api/verify-payment.php  —  Verify Razorpay payment & send email
//
//  POST /api/verify-payment.php
//  Body (JSON):
//    {
//      "razorpay_order_id":   "order_XXXXX",
//      "razorpay_payment_id": "pay_XXXXX",
//      "razorpay_signature":  "<hmac>",
//      "customer_name":       "John",
//      "customer_phone":      "9876543210",
//      "customer_city":       "Chennai",
//      "customer_pincode":    "600001",
//      "customer_address":    "123 Main St",
//      "cart_items":          ["PeanutBurfi x2 = ₹90", ...],
//      "total_amount":        90
//    }
//
//  Returns (JSON):
//    { "success": true, "order_number": "TM-20250411-4712" }
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

// ── Parse body ────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);

$required = [
    'razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature',
    'customer_name', 'customer_phone', 'customer_city',
    'customer_pincode', 'customer_address', 'cart_items', 'total_amount',
];

foreach ($required as $field) {
    if (empty($body[$field])) {
        jsonResponse(['success' => false, 'error' => "Missing field: {$field}"], 400);
    }
}

// ── Validate cart_items ───────────────────────────────────────
if (!is_array($body['cart_items']) || count($body['cart_items']) === 0) {
    jsonResponse(['success' => false, 'error' => 'Cart is empty'], 400);
}

$totalAmount = (int) $body['total_amount'];
if ($totalAmount <= 0) {
    jsonResponse(['success' => false, 'error' => 'Invalid total amount'], 400);
}

// ── Verify Razorpay signature ─────────────────────────────────
$isValid = verifyRazorpaySignature(
    $body['razorpay_order_id'],
    $body['razorpay_payment_id'],
    $body['razorpay_signature'],
    RAZORPAY_KEY_SECRET
);

if (!$isValid) {
    jsonResponse(['success' => false, 'error' => 'Payment verification failed. Signature mismatch.'], 400);
}

// ── Build order record ────────────────────────────────────────
$orderNumber = generateOrderNumber();

$order = [
    'order_number'     => $orderNumber,
    'payment_id'       => sanitize($body['razorpay_payment_id']),
    'razorpay_order_id'=> sanitize($body['razorpay_order_id']),
    'customer_name'    => sanitize($body['customer_name']),
    'customer_phone'   => sanitize($body['customer_phone']),
    'customer_city'    => sanitize($body['customer_city']),
    'customer_pincode' => sanitize($body['customer_pincode']),
    'customer_address' => sanitize($body['customer_address']),
    'cart_items'       => array_map('sanitize', $body['cart_items']),
    'total_amount'     => $totalAmount,
    'created_at'       => date('Y-m-d H:i:s'),
];

// ── Optionally persist to a log file ─────────────────────────
//  (replace with a real DB insert in production)
$logDir  = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0750, true);
}
file_put_contents(
    $logDir . '/orders.jsonl',
    json_encode($order, JSON_UNESCAPED_UNICODE) . "\n",
    FILE_APPEND | LOCK_EX
);

// ── Send email notification ───────────────────────────────────
$emailResult = sendOrderEmail($order);

// Return success even if email fails (don't block the customer)
jsonResponse([
    'success'      => true,
    'order_number' => $orderNumber,
    'email_sent'   => $emailResult['success'],
    'email_error'  => $emailResult['error'] ?? null,
]);
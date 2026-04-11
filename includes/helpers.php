<?php
// ============================================================
//  includes/helpers.php  —  Shared utility functions
// ============================================================

/**
 * Generate a unique order number in the format TM-YYYYMMDD-XXXX
 */
function generateOrderNumber(): string {
    $date   = date('Ymd');
    $random = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    return "TM-{$date}-{$random}";
}

/**
 * Sanitize a scalar value from user input.
 */
function sanitize(mixed $value): string {
    return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
}

/**
 * Return a JSON response and exit.
 */
function jsonResponse(array $data, int $httpCode = 200): never {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Verify Razorpay payment signature.
 * Signature = HMAC-SHA256(orderId + "|" + paymentId, secret)
 *
 * NOTE: Razorpay sends razorpay_order_id only when you create an order
 * via their Orders API. When using the client-side Checkout without a
 * pre-created order the signature check is skipped server-side; instead
 * you call the Razorpay Fetch Payment API to confirm the payment.
 */
function verifyRazorpaySignature(
    string $orderId,
    string $paymentId,
    string $signature,
    string $secret
): bool {
    $payload  = $orderId . '|' . $paymentId;
    $expected = hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected, $signature);
}

/**
 * Build a plain-text order summary for emails.
 */
function buildOrderSummaryText(array $order): string {
    $lines   = [];
    $lines[] = "Order Number : {$order['order_number']}";
    $lines[] = "Payment ID   : {$order['payment_id']}";
    $lines[] = '';
    $lines[] = 'Customer Details';
    $lines[] = "  Name    : {$order['customer_name']}";
    $lines[] = "  Phone   : {$order['customer_phone']}";
    $lines[] = "  City    : {$order['customer_city']}";
    $lines[] = "  Pincode : {$order['customer_pincode']}";
    $lines[] = "  Address : {$order['customer_address']}";
    $lines[] = '';
    $lines[] = 'Items Ordered';
    foreach ($order['cart_items'] as $item) {
        $lines[] = "  • {$item}";
    }
    $lines[] = '';
    $lines[] = "Total Amount : ₹{$order['total_amount']}";
    return implode("\n", $lines);
}

/**
 * Build an HTML order summary for emails.
 */
function buildOrderSummaryHtml(array $order): string {
    $itemRows = '';
    foreach ($order['cart_items'] as $item) {
        $parts     = explode(' = ', $item);
        $itemName  = htmlspecialchars($parts[0] ?? $item, ENT_QUOTES, 'UTF-8');
        $itemTotal = htmlspecialchars($parts[1] ?? '', ENT_QUOTES, 'UTF-8');
        $itemRows .= "
            <tr>
                <td style='padding:6px 8px;font-size:13px;color:#444;'>{$itemName}</td>
                <td style='padding:6px 8px;font-size:13px;text-align:right;'>{$itemTotal}</td>
            </tr>";
    }

    $orderNumber    = htmlspecialchars($order['order_number'],    ENT_QUOTES, 'UTF-8');
    $paymentId      = htmlspecialchars($order['payment_id'],      ENT_QUOTES, 'UTF-8');
    $customerName   = htmlspecialchars($order['customer_name'],   ENT_QUOTES, 'UTF-8');
    $customerPhone  = htmlspecialchars($order['customer_phone'],  ENT_QUOTES, 'UTF-8');
    $customerCity   = htmlspecialchars($order['customer_city'],   ENT_QUOTES, 'UTF-8');
    $customerPin    = htmlspecialchars($order['customer_pincode'],ENT_QUOTES, 'UTF-8');
    $customerAddr   = nl2br(htmlspecialchars($order['customer_address'], ENT_QUOTES, 'UTF-8'));
    $totalAmount    = htmlspecialchars((string) $order['total_amount'], ENT_QUOTES, 'UTF-8');

    return <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head><meta charset="UTF-8"><title>Order Confirmation</title></head>
    <body style="font-family:sans-serif;background:#f5f5f5;margin:0;padding:20px;">
      <div style="max-width:560px;margin:auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">
        <div style="background:#c8a96e;padding:24px;text-align:center;">
          <h1 style="color:#fff;margin:0;font-size:22px;">Thanga Mani</h1>
          <p style="color:#fff3d6;margin:4px 0 0;font-size:14px;">Order Confirmation</p>
        </div>
        <div style="padding:24px;">
          <div style="background:#eaf3de;border-radius:8px;padding:12px 16px;margin-bottom:20px;text-align:center;">
            ✅ <strong>Payment Successful!</strong> Thank you, {$customerName}!
          </div>

          <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
            <tr><td style="padding:6px 0;color:#888;font-size:12px;">Order Number</td>
                <td style="padding:6px 0;font-family:monospace;font-weight:600;">{$orderNumber}</td></tr>
            <tr><td style="padding:6px 0;color:#888;font-size:12px;">Payment ID</td>
                <td style="padding:6px 0;font-family:monospace;">{$paymentId}</td></tr>
          </table>

          <h3 style="font-size:14px;color:#555;margin-bottom:8px;border-bottom:1px solid #eee;padding-bottom:6px;">Customer Details</h3>
          <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
            <tr><td style="padding:4px 0;color:#888;font-size:12px;width:40%;">Name</td><td style="font-size:13px;">{$customerName}</td></tr>
            <tr><td style="padding:4px 0;color:#888;font-size:12px;">Phone</td><td style="font-size:13px;">{$customerPhone}</td></tr>
            <tr><td style="padding:4px 0;color:#888;font-size:12px;">City</td><td style="font-size:13px;">{$customerCity}</td></tr>
            <tr><td style="padding:4px 0;color:#888;font-size:12px;">Pincode</td><td style="font-size:13px;">{$customerPin}</td></tr>
            <tr><td style="padding:4px 0;color:#888;font-size:12px;">Address</td><td style="font-size:13px;">{$customerAddr}</td></tr>
          </table>

          <h3 style="font-size:14px;color:#555;margin-bottom:8px;border-bottom:1px solid #eee;padding-bottom:6px;">Items Ordered</h3>
          <table style="width:100%;border-collapse:collapse;margin-bottom:16px;">
            {$itemRows}
            <tr style="border-top:2px solid #eee;">
              <td style="padding:10px 8px;font-weight:700;">Total Paid</td>
              <td style="padding:10px 8px;font-weight:700;text-align:right;">₹{$totalAmount}</td>
            </tr>
          </table>
        </div>
        <div style="background:#f7f7f5;padding:16px;text-align:center;font-size:12px;color:#aaa;">
          Thanga Mani — Peanut Burfi Specialists &nbsp;|&nbsp; Thank you for your order!
        </div>
      </div>
    </body>
    </html>
    HTML;
}
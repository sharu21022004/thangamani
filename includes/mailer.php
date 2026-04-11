<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

function sendOrderEmail(array $order): array {

    $mail = new PHPMailer(true);

    try {
        // SMTP CONFIG
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);

        // ---------------- ADMIN EMAIL ----------------
        $mail->addAddress(ADMIN_EMAIL);

        $itemsHtml = '';
        foreach ($order['cart_items'] as $item) {
            $itemsHtml .= "<li>$item</li>";
        }

        $mail->isHTML(true);
        $mail->Subject = 'New Order - ' . $order['order_number'];

        $mail->Body = '
<div style="font-family:Arial;background:#f5f5f5;padding:20px;">
  <div style="max-width:600px;margin:auto;background:#fff;border-radius:8px;overflow:hidden">

    <div style="background:#c8a96e;color:#fff;padding:15px;text-align:center">
      <h2 style="margin:0;">Thanga Mani</h2>
      <p style="margin:5px 0 0;">New Order Received</p>
    </div>

    <div style="padding:20px">

      <h3>Order Details</h3>
      <p><b>Order ID:</b> '.$order['order_number'].'</p>
      <p><b>Payment ID:</b> '.$order['payment_id'].'</p>
      <p><b>Total Amount:</b> ₹'.$order['total_amount'].'</p>

      <hr>

      <h3>Customer Details</h3>
      <p>
      '.$order['customer_name'].'<br>
      '.$order['customer_phone'].'<br>
      '.$order['customer_email'].'<br>
      '.$order['customer_address'].'
      </p>

      <hr>

      <h3>Items</h3>
      <ul>';
      foreach ($order['cart_items'] as $item) {
    $mail->Body .= '<li>'.$item.'</li>';
    $mail->Body .= '
      </ul>

    </div>

    <div style="background:#eee;padding:10px;text-align:center;font-size:12px">
      Thanga Mani Admin Panel
    </div>

  </div>
</div>
';
}

        $mail->send();

        // ---------------- CUSTOMER EMAIL ----------------
        $mail->clearAddresses();
        $mail->addAddress($order['customer_email']);

        $mail->Subject = 'Order Confirmation - Thanga Mani';

        $mail->Body = '
<div style="font-family:Arial;background:#f5f5f5;padding:20px;">
  <div style="max-width:600px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;text-align:center">

    <div style="background:#c8a96e;color:#fff;padding:20px">
      <h2 style="margin:0;">Thank You!</h2>
    </div>

    <div style="padding:20px">

      <p>Hi <b>'.$order['customer_name'].'</b>,</p>

      <p>Your order has been successfully placed.</p>

      <div style="background:#f9f9f9;padding:15px;border-radius:6px;margin:15px 0">
        <p><b>Order Number:</b> '.$order['order_number'].'</p>
        <p><b>Total Paid:</b> ₹'.$order['total_amount'].'</p>
      </div>

      <p>We will contact you shortly regarding your order.</p>

      <p style="margin-top:20px">
        Thank you for choosing <b>Thanga Mani</b>
      </p>

    </div>

    <div style="background:#eee;padding:10px;font-size:12px">
      © '.date('Y').' Thanga Mani
    </div>

  </div>
</div>
';

        $mail->send();

        return ['success' => true];

    } catch (Exception $e) {

        file_put_contents(
            __DIR__ . '/../logs/mail_error.log',
            "ERROR: " . $mail->ErrorInfo . "\n",
            FILE_APPEND
        );

        return [
            'success' => false,
            'error' => $mail->ErrorInfo
        ];
    }
}
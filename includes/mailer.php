<?php
// ============================================================
//  includes/mailer.php  —  Email sending via PHPMailer / SMTP
//
//  Requires PHPMailer installed via Composer:
//    composer require phpmailer/phpmailer
// ============================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

/**
 * Send order confirmation email to the admin and (optionally) the customer.
 *
 * @param  array  $order  Associative array with order details.
 * @return array{success: bool, error?: string}
 */
function sendOrderEmail(array $order): array {
    $mail = new PHPMailer(true);

    try {
        // ── Server settings ──────────────────────────────────
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        // ── Sender / Recipients ──────────────────────────────
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress(ADMIN_EMAIL, 'Thanga Mani Admin');   // always notify admin

        // ── Content ──────────────────────────────────────────
        $mail->isHTML(true);
        $mail->Subject = sprintf(
            '[New Order] %s — ₹%s',
            $order['order_number'],
            $order['total_amount']
        );
        $mail->Body    = buildOrderSummaryHtml($order);
        $mail->AltBody = buildOrderSummaryText($order);   // plain-text fallback

        $mail->send();
        return ['success' => true];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error'   => $mail->ErrorInfo,
        ];
    }
}
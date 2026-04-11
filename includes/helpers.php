<?php
// ============================================================
// helpers.php — clean utility functions
// ============================================================

// Sanitize input
function sanitize($value) {
    return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
}

// Generate order number
function generateOrderNumber() {
    return 'TM-' . date('Ymd') . '-' . rand(1000, 9999);
}

// JSON response (CRITICAL FIX)
function jsonResponse($data, $code = 200) {
    http_response_code($code);

    // 🔥 Remove any unwanted output (fixes "<br>" JSON error)
    if (ob_get_length()) {
        ob_clean();
    }

    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
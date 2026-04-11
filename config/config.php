<?php
// ============================================================
//  config/config.php  —  Thanga Mani Configuration
//  ⚠️  Keep this file out of version control / public access
// ============================================================

define('RAZORPAY_KEY_ID','rzp_test_ScEcYNHjoOciug');   // Replace with live key in production
define('RAZORPAY_KEY_SECRET','bw2AEcGJ897l3o6V1NLRdXiM');  // From Razorpay Dashboard

// SMTP / Email settings (use your actual mail credentials)
define('SMTP_HOST',     'smtp.gmail.com');
define('SMTP_PORT',     587);
define('SMTP_USERNAME', 'sharukeshavalingam21@gmail.com');
define('SMTP_PASSWORD', 'azat megk pikt hefg');       // Gmail App Password
define('SMTP_FROM',     'sharukeshavalingam21@gmail.com');
define('SMTP_FROM_NAME','Thanga Mani');

// Admin email — order notifications go here
define('ADMIN_EMAIL',   'sharukeshavalingam21@gmail.com');

// App settings
define('APP_NAME',      'Thanga Mani');
define('CURRENCY',      'INR');
define('CURRENCY_SYMBOL', '₹');
define('RAZORPAY_THEME_COLOR', '#c8a96e');
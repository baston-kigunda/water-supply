<?php
// ========================================
// FILE: config.php
// PURPOSE: Main configuration file with system settings
// ========================================

// Site configuration
define('SITE_NAME', 'Smart Water Supply Management System');
define('SITE_URL', 'http://localhost/water-supply-management-system');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'water_supply_system');

// System settings
define('WATER_RATE_PER_UNIT', 50.00); // KSh per cubic meter
define('CURRENCY', 'KSh');
define('TIMEZONE', 'Africa/Nairobi');
date_default_timezone_set(TIMEZONE);

// M-Pesa API Configuration (Sandbox)
define('MPESA_CONSUMER_KEY', 'your_consumer_key');
define('MPESA_CONSUMER_SECRET', 'your_consumer_secret');
define('MPESA_PASSKEY', 'your_passkey');
define('MPESA_SHORTCODE', '174379');
define('MPESA_ENVIRONMENT', 'sandbox'); // sandbox or production

// Session configuration
session_start();
?>
<?php
// ========================================
// FILE: config.php
// PURPOSE: Main configuration file with system settings
// ========================================

// Site configuration
define('SITE_NAME', 'Smart Water Supply Management System');

$site_url = 'http://localhost/water%20supply';
if (!empty($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $app_root = realpath(__DIR__ . '/..');
    $document_root = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;

    if ($app_root && $document_root) {
        $normalized_app_root = str_replace('\\', '/', $app_root);
        $normalized_document_root = rtrim(str_replace('\\', '/', $document_root), '/');

        if (strpos($normalized_app_root, $normalized_document_root) === 0) {
            $relative_path = trim(substr($normalized_app_root, strlen($normalized_document_root)), '/');
            $encoded_path = $relative_path === ''
                ? ''
                : '/' . implode('/', array_map('rawurlencode', explode('/', $relative_path)));

            $site_url = $scheme . '://' . $host . $encoded_path;
        }
    }
}

define('SITE_URL', rtrim($site_url, '/'));

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'water_supply_system');

// System settings
define('WATER_RATE_PER_UNIT', 50.00); // KSh per cubic meter
define('NEW_CUSTOMER_INITIAL_UNITS', 50.00); // Opening billed water units for a newly assigned customer
define('CUBIC_CENTIMETERS_PER_CUBIC_METER', 1000000);
define('CURRENCY', 'KSh');
define('TIMEZONE', 'Africa/Nairobi');
define('IOT_SIMULATION_ENABLED', true);
define('IOT_SIMULATION_INTERVAL_SECONDS', 60);
define('IOT_SIMULATION_MIN_USAGE_PER_MINUTE', 0.0002); // cubic meters
define('IOT_SIMULATION_MAX_USAGE_PER_MINUTE', 0.0011); // cubic meters
define('IOT_SIMULATION_MAX_CATCHUP_READINGS', 1440); // one day of missed minute intervals per request
define('IOT_SIMULATION_MAX_READINGS_PER_RUN', 5);
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

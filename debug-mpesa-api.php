<?php
// debug-mpesa-api.php - Comprehensive M-Pesa API Debugger
header('Content-Type: text/html');
echo "<h1>M-Pesa API Debugger</h1>";

// Test 1: Check if API file exists
echo "<h2>1. API File Check</h2>";
$api_file = __DIR__ . '/api/api-payments.php';
if (file_exists($api_file)) {
    echo "✓ API file exists at: " . $api_file . "<br>";
} else {
    echo "✗ API file NOT found at: " . $api_file . "<br>";
}

// Test 2: Test the API directly
echo "<h2>2. Direct API Test</h2>";
$test_payload = [
    'phone' => '254708374149', // Sandbox test number
    'amount' => 10,
    'bill_id' => 1, // Change this to a valid bill ID
    'user_id' => 1  // Change this to a valid user ID
];

$api_url = 'https://pouch-scion-panorama.ngrok-free.dev/watersupply/api/api-payments.php?action=stk_push';
echo "Calling: " . $api_url . "<br>";
echo "Payload: " . json_encode($test_payload) . "<br><br>";

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "HTTP Code: " . $http_code . "<br>";
if ($curl_error) echo "CURL Error: " . $curl_error . "<br>";
echo "Response: " . $response . "<br>";

if ($response) {
    $data = json_decode($response, true);
    echo "<pre>";
    print_r($data);
    echo "</pre>";
}

// Test 3: Check M-Pesa credentials directly
echo "<h2>3. M-Pesa Authentication Test</h2>";

$consumer_key = 'VTyHQW1YRtrVTHu6ld6vAAZfMM39uzMOMqvI6Wt71VGB7hwr';
$consumer_secret = 'bQZo2HX3qGRkzoTy1kY7GnoxYKARLEKktpSGoDODC6vtE4YkuOqvHlQjmUOtV3J3';
$credentials = base64_encode($consumer_key . ':' . $consumer_secret);

$auth_url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
$ch = curl_init($auth_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$auth_response = curl_exec($ch);
$auth_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Auth HTTP Code: " . $auth_http_code . "<br>";
if ($auth_response) {
    $auth_data = json_decode($auth_response, true);
    if (isset($auth_data['access_token'])) {
        echo "✓ Successfully obtained access token!<br>";
        echo "Token: " . substr($auth_data['access_token'], 0, 50) . "...<br>";
    } else {
        echo "✗ Failed to get access token<br>";
        echo "Response: " . $auth_response . "<br>";
    }
}

// Test 4: Check if required columns exist in database
echo "<h2>4. Database Structure Check</h2>";
require_once 'config/database.php';

$tables_to_check = ['payments', 'bills', 'users'];
foreach ($tables_to_check as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($result) > 0) {
        echo "✓ Table '$table' exists<br>";
        
        // Check columns for payments table
        if ($table == 'payments') {
            $required_columns = ['payment_id', 'user_id', 'bill_id', 'amount', 'payment_method', 'transaction_code', 'payment_status'];
            $columns = mysqli_query($conn, "SHOW COLUMNS FROM payments");
            $existing = [];
            while ($col = mysqli_fetch_assoc($columns)) {
                $existing[] = $col['Field'];
            }
            
            foreach ($required_columns as $col) {
                if (in_array($col, $existing)) {
                    echo "  ✓ Column '$col' exists<br>";
                } else {
                    echo "  ✗ Column '$col' MISSING<br>";
                }
            }
        }
    } else {
        echo "✗ Table '$table' does NOT exist<br>";
    }
}

// Test 5: Check for pending bills
echo "<h2>5. Check for Test Data</h2>";
$bill_result = mysqli_query($conn, "SELECT bill_id, user_id, total_amount, bill_status FROM bills WHERE bill_status != 'paid' LIMIT 5");
if (mysqli_num_rows($bill_result) > 0) {
    echo "Found " . mysqli_num_rows($bill_result) . " unpaid bills:<br>";
    while ($bill = mysqli_fetch_assoc($bill_result)) {
        echo "  - Bill ID: {$bill['bill_id']}, User ID: {$bill['user_id']}, Amount: {$bill['total_amount']}, Status: {$bill['bill_status']}<br>";
    }
} else {
    echo "No unpaid bills found. Create a test bill first.<br>";
}

$user_result = mysqli_query($conn, "SELECT user_id, username, email FROM users LIMIT 5");
if (mysqli_num_rows($user_result) > 0) {
    echo "<br>Available users:<br>";
    while ($user = mysqli_fetch_assoc($user_result)) {
        echo "  - User ID: {$user['user_id']}, Username: {$user['username']}<br>";
    }
}

echo "<h2>6. Log Files</h2>";
$log_dir = __DIR__ . '/logs/';
if (file_exists($log_dir)) {
    $log_files = glob($log_dir . 'mpesa_*.log');
    if (count($log_files) > 0) {
        echo "Log files found:<br>";
        foreach ($log_files as $log_file) {
            echo "<a href='$log_file' target='_blank'>" . basename($log_file) . "</a><br>";
            // Show last 5 lines
            $lines = file($log_file);
            $last_lines = array_slice($lines, -5);
            echo "<pre>";
            foreach ($last_lines as $line) {
                echo htmlspecialchars($line);
            }
            echo "</pre>";
        }
    } else {
        echo "No log files found yet.<br>";
    }
} else {
    echo "Log directory doesn't exist: $log_dir<br>";
}
?>
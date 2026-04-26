<?php
// test-stk-verbose.php - Test STK Push with verbose logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html');

echo "<h2>STK Push Test - Verbose Mode</h2>";

// Include config
require_once 'config/database.php';

// M-Pesa configuration
$consumer_key = 'VTyHQW1YRtrVTHu6ld6vAAZfMM39uzMOMqvI6Wt71VGB7hwr';
$consumer_secret = 'bQZo2HX3qGRkzoTy1kY7GnoxYKARLEKktpSGoDODC6vtE4YkuOqvHlQjmUOtV3J3';
$passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
$shortcode = '174379';
$callback_url = 'https://pouch-scion-panorama.ngrok-free.dev/watersupply/api/api-payments.php?action=callback';

$phone = '254708374149';
$amount = 10;
$bill_id = 9;
$user_id = 3;

echo "Phone: $phone<br>";
echo "Amount: $amount<br>";
echo "Bill ID: $bill_id<br>";
echo "User ID: $user_id<br>";
echo "Callback URL: $callback_url<br><br>";

// Get access token
echo "<strong>1. Getting Access Token...</strong><br>";
$credentials = base64_encode($consumer_key . ':' . $consumer_secret);
$auth_url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

$ch = curl_init($auth_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$auth_response = curl_exec($ch);
$auth_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$auth_data = json_decode($auth_response);
if (!isset($auth_data->access_token)) {
    die("Failed to get access token: " . $auth_response);
}
$access_token = $auth_data->access_token;
echo "✓ Access token obtained: " . substr($access_token, 0, 50) . "...<br><br>";

// Prepare STK Push
echo "<strong>2. Initiating STK Push...</strong><br>";
$timestamp = date('YmdHis');
$password = base64_encode($shortcode . $passkey . $timestamp);
$reference = 'TEST' . time();

$stk_data = array(
    'BusinessShortCode' => $shortcode,
    'Password' => $password,
    'Timestamp' => $timestamp,
    'TransactionType' => 'CustomerPayBillOnline',
    'Amount' => $amount,
    'PartyA' => $phone,
    'PartyB' => $shortcode,
    'PhoneNumber' => $phone,
    'CallBackURL' => $callback_url,
    'AccountReference' => $reference,
    'TransactionDesc' => 'Water Bill Payment'
);

echo "Request Data:<br>";
echo "<pre>" . json_encode($stk_data, JSON_PRETTY_PRINT) . "</pre>";

$stk_url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
$ch = curl_init($stk_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Authorization: Bearer ' . $access_token
));
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stk_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$stk_response = curl_exec($ch);
$stk_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $stk_http<br>";
if ($curl_error) echo "CURL Error: $curl_error<br>";
echo "Response: $stk_response<br><br>";

$result = json_decode($stk_response);

if ($result && isset($result->ResponseCode)) {
    if ($result->ResponseCode == '0') {
        echo "<span style='color:green'>✓ STK Push initiated successfully!</span><br>";
        echo "CheckoutRequestID: " . ($result->CheckoutRequestID ?? 'N/A') . "<br>";
        echo "MerchantRequestID: " . ($result->MerchantRequestID ?? 'N/A') . "<br>";
        
        // Save to database
        echo "<br><strong>3. Saving to Database...</strong><br>";
        $checkout_id = $result->CheckoutRequestID ?? null;
        $merchant_id = $result->MerchantRequestID ?? null;
        
        // Check if table has the required columns
        $check_col = mysqli_query($conn, "SHOW COLUMNS FROM payments LIKE 'checkout_request_id'");
        if (mysqli_num_rows($check_col) > 0) {
            $stmt = mysqli_prepare($conn, "INSERT INTO payments (user_id, bill_id, amount, payment_method, transaction_code, payment_status, merchant_request_id, checkout_request_id) VALUES (?, ?, ?, 'mpesa', ?, 'pending', ?, ?)");
            mysqli_stmt_bind_param($stmt, "iidsss", $user_id, $bill_id, $amount, $reference, $merchant_id, $checkout_id);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO payments (user_id, bill_id, amount, payment_method, transaction_code, payment_status) VALUES (?, ?, ?, 'mpesa', ?, 'pending')");
            mysqli_stmt_bind_param($stmt, "iids", $user_id, $bill_id, $amount, $reference);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            echo "✓ Payment record saved. ID: " . mysqli_insert_id($conn) . "<br>";
        } else {
            echo "✗ Failed to save: " . mysqli_error($conn) . "<br>";
        }
    } else {
        echo "<span style='color:red'>✗ STK Push failed!</span><br>";
        echo "Response Code: " . $result->ResponseCode . "<br>";
        echo "Response Description: " . ($result->ResponseDescription ?? 'N/A') . "<br>";
        if (isset($result->errorMessage)) {
            echo "Error Message: " . $result->errorMessage . "<br>";
        }
    }
} else {
    echo "<span style='color:red'>✗ Invalid response from M-Pesa!</span><br>";
}

// Check database connection
echo "<br><strong>4. Database Check...</strong><br>";
if ($conn) {
    echo "✓ Database connected<br>";
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM payments");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "Total payments in database: " . $row['count'] . "<br>";
    }
} else {
    echo "✗ Database connection failed<br>";
}
?>
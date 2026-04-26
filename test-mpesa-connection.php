<?php
// test-mpesa-connection.php
header('Content-Type: text/html');

echo "<h1>M-Pesa Connection Test</h1>";

// Test 1: Check if CURL is enabled
echo "<h3>Test 1: CURL Extension</h3>";
if (function_exists('curl_version')) {
    $curl_version = curl_version();
    echo "✅ CURL is enabled (Version: " . $curl_version['version'] . ")<br>";
} else {
    echo "❌ CURL is NOT enabled. Please enable it in php.ini<br>";
}

// Test 2: Test Access Token Generation
echo "<h3>Test 2: M-Pesa Access Token</h3>";

$consumer_key = 'VTyHQW1YRtrVTHu6ld6vAAZfMM39uzMOMqvI6Wt71VGB7hwr';
$consumer_secret = 'bQZo2HX3qGRkzoTy1kY7GnoxYKARLEKktpSGoDODC6vtE4YkuOqvHlQjmUOtV3J3';
$credentials = base64_encode($consumer_key . ':' . $consumer_secret);

$url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "HTTP Status Code: $http_code<br>";
if ($curl_error) {
    echo "❌ CURL Error: $curl_error<br>";
}

$result = json_decode($response);
if (isset($result->access_token)) {
    echo "✅ Access token obtained successfully!<br>";
    echo "Token: " . substr($result->access_token, 0, 50) . "...<br>";
} else {
    echo "❌ Failed to get access token<br>";
    echo "Response: " . htmlspecialchars($response) . "<br>";
    
    if (strpos($response, 'Invalid consumer key') !== false) {
        echo "<strong>⚠️ Your consumer key/secret may be invalid. Please regenerate them from Safaricom Developer Portal.</strong><br>";
    }
}

// Test 3: Test STK Push with valid token
if (isset($result->access_token)) {
    echo "<h3>Test 3: STK Push Request</h3>";
    
    $access_token = $result->access_token;
    $shortcode = '174379';
    $passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
    $timestamp = date('YmdHis');
    $password = base64_encode($shortcode . $passkey . $timestamp);
    
    $test_phone = '254708374149'; // Sandbox test number
    $test_amount = 10;
    $test_reference = 'TEST' . time();
    
    $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    
    $data = array(
        'BusinessShortCode' => $shortcode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $test_amount,
        'PartyA' => $test_phone,
        'PartyB' => $shortcode,
        'PhoneNumber' => $test_phone,
        'CallBackURL' => 'https://darajambili.herokuapp.com/api/api-payments.php?action=callback',
        'AccountReference' => $test_reference,
        'TransactionDesc' => 'Test Payment'
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Status Code: $http_code<br>";
    if ($curl_error) {
        echo "❌ CURL Error: $curl_error<br>";
    }
    
    $result = json_decode($response);
    echo "Response: <pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
    
    if (isset($result->ResponseCode)) {
        if ($result->ResponseCode == '0') {
            echo "✅ STK Push initiated successfully! Check phone number: $test_phone<br>";
            echo "Checkout Request ID: " . ($result->CheckoutRequestID ?? 'N/A') . "<br>";
        } else {
            echo "❌ STK Push failed with code: " . $result->ResponseCode . "<br>";
            echo "Description: " . ($result->ResponseDescription ?? 'N/A') . "<br>";
        }
    }
}

// Test 4: Check if callback URL is accessible
echo "<h3>Test 4: Callback URL Accessibility</h3>";
$callback_url = 'https://darajambili.herokuapp.com/api/api-payments.php?action=callback';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $callback_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Callback URL: $callback_url<br>";
echo "HTTP Status Code: $http_code<br>";
if ($http_code == 200 || $http_code == 405) {
    echo "✅ Callback URL is accessible<br>";
} else {
    echo "❌ Callback URL is not accessible (HTTP $http_code). M-Pesa cannot send callbacks to this URL.<br>";
}
?>
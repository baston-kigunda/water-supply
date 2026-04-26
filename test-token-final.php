<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>M-Pesa Complete API Test</h1>";

// Credentials
$consumer_key = 'VTyHQW1YRtrVTHu6ld6vAAZfMM39uzMOMqvI6Wt71VGB7hwr';
$consumer_secret = 'bQZo2HX3qGRkzoTy1kY7GnoxYKARLEKktpSGoDODC6vtE4YkuOqvHlQjmUOtV3J3';
$shortcode = '174379';
$passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';

// Step 1: Get Access Token
echo "<h2>Step 1: Getting Access Token</h2>";

$credentials = base64_encode($consumer_key . ':' . $consumer_secret);
$url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "HTTP Status Code: $http_code<br>";
if ($curl_error) echo "CURL Error: $curl_error<br>";

$token_data = json_decode($response, true);
echo "<pre>";
print_r($token_data);
echo "</pre>";

if (!isset($token_data['access_token'])) {
    echo "<h3 style='color:red'>❌ Failed to get access token!</h3>";
    echo "Response: " . htmlspecialchars($response);
    exit;
}

$access_token = $token_data['access_token'];
echo "<h3 style='color:green'>✅ Access token obtained!</h3>";
echo "Token: " . substr($access_token, 0, 50) . "...<br><br>";

// Step 2: Send STK Push
echo "<h2>Step 2: Sending STK Push</h2>";

$timestamp = date('YmdHis');
$password = base64_encode($shortcode . $passkey . $timestamp);
$reference = 'TEST' . time();

$stk_url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

$stk_data = [
    'BusinessShortCode' => $shortcode,
    'Password' => $password,
    'Timestamp' => $timestamp,
    'TransactionType' => 'CustomerPayBillOnline',
    'Amount' => 10,
    'PartyA' => 254708374149,
    'PartyB' => $shortcode,
    'PhoneNumber' => 254708374149,
    'CallBackURL' => 'https://pouch-scion-panorama.ngrok-free.dev/water%20supply/api/api-payments.php?action=callback',
    'AccountReference' => $reference,
    'TransactionDesc' => 'Water Bill Payment'
];

echo "STK Push Request:<br>";
echo "<pre>" . json_encode($stk_data, JSON_PRETTY_PRINT) . "</pre>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $stk_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $access_token
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stk_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "HTTP Status Code: $http_code<br>";
if ($curl_error) echo "CURL Error: $curl_error<br>";

$result = json_decode($response, true);
echo "<h3>Response:</h3>";
echo "<pre>";
print_r($result);
echo "</pre>";

if (isset($result['ResponseCode'])) {
    if ($result['ResponseCode'] == '0') {
        echo "<h3 style='color:green'>✅ STK Push successful!</h3>";
        echo "Checkout Request ID: " . ($result['CheckoutRequestID'] ?? 'N/A') . "<br>";
        echo "Customer Message: " . ($result['CustomerMessage'] ?? 'N/A') . "<br>";
        echo "<p style='color:blue'>Check your phone: 254708374149 should receive an STK push prompt.</p>";
    } else {
        echo "<h3 style='color:red'>❌ STK Push failed!</h3>";
        echo "Response Code: " . $result['ResponseCode'] . "<br>";
        echo "Response Description: " . ($result['ResponseDescription'] ?? 'N/A') . "<br>";
        echo "Error Message: " . ($result['errorMessage'] ?? 'N/A') . "<br>";
    }
} else {
    echo "<h3 style='color:red'>❌ Invalid response structure!</h3>";
}
?>
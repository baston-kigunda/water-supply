<?php
// test-api.php - Direct test of M-Pesa API from PHP
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>M-Pesa API Direct Test</h1>";

$consumer_key = 'VTyHQW1YRtrVTHu6ld6vAAZfMM39uzMOMqvI6Wt71VGB7hwr';
$consumer_secret = 'bQZo2HX3qGRkzoTy1kY7GnoxYKARLEKktpSGoDODC6vtE4YkuOqvHlQjmUOtV3J3';

echo "Testing connection to Safaricom API...<br>";

$credentials = base64_encode($consumer_key . ':' . $consumer_secret);
$url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

rewind($verbose);
$verboseLog = stream_get_contents($verbose);
fclose($verbose);

curl_close($ch);

echo "HTTP Status Code: $http_code<br>";
echo "cURL Error: " . ($curl_error ?: 'None') . "<br>";

if ($response) {
    $result = json_decode($response);
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    
    if (isset($result->access_token)) {
        echo "<h2 style='color:green'>✅ SUCCESS! Token obtained!</h2>";
        echo "Token: " . $result->access_token . "<br>";
        
        // Now try STK Push
        echo "<h2>Testing STK Push...</h2>";
        
        $shortcode = '174379';
        $passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
        $timestamp = date('YmdHis');
        $password = base64_encode($shortcode . $passkey . $timestamp);
        
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
            'AccountReference' => 'TEST' . time(),
            'TransactionDesc' => 'Test Payment'
        ];
        
        $ch2 = curl_init('https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
        curl_setopt($ch2, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $result->access_token
        ]);
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($stk_data));
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
        
        $stk_response = curl_exec($ch2);
        $stk_http = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);
        
        echo "STK Push HTTP: $stk_http<br>";
        echo "<pre>";
        print_r(json_decode($stk_response));
        echo "</pre>";
        
    } else {
        echo "<h2 style='color:red'>❌ FAILED to get token</h2>";
        echo "Response: " . htmlspecialchars($response);
    }
} else {
    echo "<h2 style='color:red'>❌ No response from API</h2>";
}

echo "<h3>cURL Verbose Log:</h3>";
echo "<pre>" . htmlspecialchars($verboseLog) . "</pre>";
?>
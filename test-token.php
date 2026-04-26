<?php
// test-token.php - Test if PHP can get the access token
header('Content-Type: text/html');

echo "<h1>PHP M-Pesa Token Test</h1>";

$consumer_key = 'VTyHQW1YRtrVTHu6ld6vAAZfMM39uzMOMqvI6Wt71VGB7hwr';
$consumer_secret = 'bQZo2HX3qGRkzoTy1kY7GnoxYKARLEKktpSGoDODC6vtE4YkuOqvHlQjmUOtV3J3';

$credentials = base64_encode($consumer_key . ':' . $consumer_secret);
$url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

echo "Attempting to get access token...<br>";
echo "URL: $url<br>";
echo "Using credentials: " . substr($credentials, 0, 20) . "...<br><br>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$curl_info = curl_getinfo($ch);

curl_close($ch);

echo "HTTP Status Code: $http_code<br>";
echo "CURL Error: " . ($curl_error ?: 'None') . "<br>";

if ($response) {
    $result = json_decode($response);
    echo "<h3>Response:</h3>";
    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
    
    if (isset($result->access_token)) {
        echo "<h3 style='color:green'>✅ SUCCESS! Token obtained via PHP!</h3>";
    } else {
        echo "<h3 style='color:red'>❌ FAILED to get token via PHP</h3>";
    }
} else {
    echo "<h3 style='color:red'>❌ No response received</h3>";
}

echo "<h3>CURL Info:</h3>";
echo "<pre>";
print_r($curl_info);
echo "</pre>";
?>
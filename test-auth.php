<?php
// test-auth.php - Standalone authentication test
header('Content-Type: text/html');

echo "<h2>Testing M-Pesa Authentication</h2>";

$consumer_key = 'VTyHQW1YRtrVTHu6ld6vAAZfMM39uzMOMqvI6Wt71VGB7hwr';
$consumer_secret = 'bQZo2HX3qGRkzoTy1kY7GnoxYKARLEKktpSGoDODC6vtE4YkuOqvHlQjmUOtV3J3';

$credentials = base64_encode($consumer_key . ':' . $consumer_secret);
$url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

echo "URL: $url<br>";
echo "Credentials length: " . strlen($credentials) . "<br>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "HTTP Status Code: $http_code<br>";
if ($curl_error) echo "CURL Error: $curl_error<br>";

if ($http_code == 200) {
    // Extract body from response (remove headers)
    $parts = explode("\r\n\r\n", $response, 2);
    $body = isset($parts[1]) ? $parts[1] : $parts[0];
    $result = json_decode($body);
    
    if (isset($result->access_token)) {
        echo "<span style='color:green'>✓ SUCCESS! Access token obtained.</span><br>";
        echo "Token: " . substr($result->access_token, 0, 50) . "...<br>";
    } else {
        echo "<span style='color:red'>✗ No access token in response.</span><br>";
        echo "Response: " . htmlspecialchars($body) . "<br>";
    }
} else {
    echo "<span style='color:red'>✗ Authentication failed with HTTP $http_code</span><br>";
    echo "Response: " . htmlspecialchars($response) . "<br>";
}
?>
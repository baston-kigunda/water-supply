<?php
// test-mpesa-auth.php - Test M-Pesa credentials
header('Content-Type: text/html');

echo "<h2>M-Pesa Credentials Test</h2>";

// Your current credentials
$consumer_key = 'VTyHQW1YRtrVTHu6ld6vAAZfMM39uzMOMqvI6Wt71VGB7hwr';
$consumer_secret = 'bQZo2HX3qGRkzoTy1kY7GnoxYKARLEKktpSGoDODC6vtE4YkuOqvHlQjmUOtV3J3';

$credentials = base64_encode($consumer_key . ':' . $consumer_secret);
$url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

echo "Testing authentication with Safaricom sandbox...<br>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: " . $http_code . "<br>";

if ($http_code == 200) {
    // Extract the body (remove headers)
    $parts = explode("\r\n\r\n", $response, 2);
    $body = isset($parts[1]) ? $parts[1] : $parts[0];
    $result = json_decode($body);
    
    if (isset($result->access_token)) {
        echo "<span style='color:green'>✓ SUCCESS! Your credentials are working!</span><br>";
        echo "Access Token: " . substr($result->access_token, 0, 50) . "...<br>";
    } else {
        echo "<span style='color:red'>✗ Failed to get access token. Response: " . $body . "</span><br>";
    }
} else {
    echo "<span style='color:red'>✗ Authentication failed!</span><br>";
    echo "Your Consumer Key or Secret may be incorrect or expired.<br>";
    echo "Please get new credentials from https://developer.safaricom.co.ke/<br>";
    
    // Show response for debugging
    echo "<br><strong>Response:</strong><br>";
    echo nl2br(htmlspecialchars($response));
}
?>
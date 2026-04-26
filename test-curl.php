<?php
// test-curl.php - Check if cURL is working in PHP
echo "<h1>PHP cURL Test</h1>";

// Check if cURL is enabled
if (function_exists('curl_version')) {
    echo "✅ cURL is enabled<br>";
    $version = curl_version();
    echo "cURL Version: " . $version['version'] . "<br>";
} else {
    echo "❌ cURL is NOT enabled! Please enable it in php.ini<br>";
}

// Test external connection
echo "<h3>Testing connection to Safaricom API...</h3>";

$url = 'https://sandbox.safaricom.co.ke';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "Connection test to safaricom.co.ke: ";
if ($http_code > 0) {
    echo "✅ Connected (HTTP $http_code)<br>";
} else {
    echo "❌ Failed - Error: $curl_error<br>";
}
?>
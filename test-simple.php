<?php
echo "<h1>Simple PHP Test</h1>";
echo "PHP Version: " . phpversion() . "<br>";

if (function_exists('curl_version')) {
    echo "✅ cURL is enabled<br>";
    $version = curl_version();
    echo "cURL Version: " . $version['version'] . "<br>";
} else {
    echo "❌ cURL is NOT enabled<br>";
}

// Test connection to Safaricom
echo "<h2>Testing connection to Safaricom...</h2>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://sandbox.safaricom.co.ke');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "HTTP Status Code: $http_code<br>";
if ($curl_error) {
    echo "cURL Error: $curl_error<br>";
}

if ($http_code > 0) {
    echo "✅ Connection successful!<br>";
} else {
    echo "❌ Connection failed<br>";
}
?>
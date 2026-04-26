<?php
echo "<h1>M-Pesa Connection Diagnostic</h1>";

// Test 1: Can PHP connect to Safaricom?
echo "<h2>Test 1: Direct Connection Test</h2>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://sandbox.safaricom.co.ke');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "Result: " . ($http > 0 ? "✅ Connected (HTTP $http)" : "❌ Failed: $error") . "<br><br>";

// Test 2: DNS Resolution
echo "<h2>Test 2: DNS Resolution</h2>";
$ip = gethostbyname('sandbox.safaricom.co.ke');
echo "IP Address: $ip<br>";
echo ($ip != 'sandbox.safaricom.co.ke') ? "✅ DNS works" : "❌ DNS failed<br><br>";

// Test 3: Check if file_get_contents works (alternative method)
echo "<h2>Test 3: Alternative Connection Method</h2>";
$context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
$result = @file_get_contents('https://sandbox.safaricom.co.ke', false, $context);
echo ($result !== false) ? "✅ file_get_contents works" : "❌ file_get_contents failed<br><br>";

// Test 4: Check your credentials directly
echo "<h2>Test 4: Direct Token Request (using command line)</h2>";
$consumer_key = 'VTyHQW1YRtrVTHu6ld6vAAZfMM39uzMOMqvI6Wt71VGB7hwr';
$consumer_secret = 'bQZo2HX3qGRkzoTy1kY7GnoxYKARLEKktpSGoDODC6vtE4YkuOqvHlQjmUOtV3J3';
$credentials = base64_encode("$consumer_key:$consumer_secret");

// Try using system curl instead of PHP curl
$cmd = 'curl -k -s -X GET "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials" -H "Authorization: Basic ' . $credentials . '"';
$output = shell_exec($cmd);
echo "Command: $cmd<br>";
echo "Response: " . htmlspecialchars($output) . "<br>";

$result = json_decode($output, true);
if (isset($result['access_token'])) {
    echo "✅ SUCCESS! Token obtained via system curl!<br>";
    echo "Token: " . substr($result['access_token'], 0, 50) . "...<br>";
} else {
    echo "❌ Failed to get token via system curl<br>";
}
?>
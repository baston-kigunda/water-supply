<?php
// test-stk-simple.php - Simple STK Push Test
header('Content-Type: application/json');

// Use a valid bill and user from your database
// First, get a valid bill and user
require_once 'config/database.php';

// Get first unpaid bill
$bill_query = "SELECT b.bill_id, b.user_id, b.total_amount FROM bills b WHERE b.bill_status != 'paid' LIMIT 1";
$bill_result = mysqli_query($conn, $bill_query);
$bill = mysqli_fetch_assoc($bill_result);

if (!$bill) {
    // Create a test bill if none exists
    $user_query = "SELECT user_id FROM users LIMIT 1";
    $user_result = mysqli_query($conn, $user_query);
    $user = mysqli_fetch_assoc($user_result);
    
    if ($user) {
        $meter_query = "SELECT meter_id FROM smart_meters LIMIT 1";
        $meter_result = mysqli_query($conn, $meter_query);
        $meter = mysqli_fetch_assoc($meter_result);
        
        if ($meter) {
            $insert = "INSERT INTO bills (meter_id, user_id, billing_month, total_amount, bill_status, due_date) 
                       VALUES ({$meter['meter_id']}, {$user['user_id']}, '2024-01-01', 1000, 'pending', DATE_ADD(NOW(), INTERVAL 30 DAY))";
            mysqli_query($conn, $insert);
            $bill_id = mysqli_insert_id($conn);
            echo "Created test bill ID: $bill_id for user ID: {$user['user_id']}\n";
            $test_bill_id = $bill_id;
            $test_user_id = $user['user_id'];
        } else {
            die("No meters found. Please create a meter first.");
        }
    } else {
        die("No users found. Please create a user first.");
    }
} else {
    $test_bill_id = $bill['bill_id'];
    $test_user_id = $bill['user_id'];
}

$test_payload = [
    'phone' => '254708374149', // Sandbox test number
    'amount' => 10,
    'bill_id' => $test_bill_id,
    'user_id' => $test_user_id
];

$api_url = 'https://pouch-scion-panorama.ngrok-free.dev/watersupply/api/api-payments.php?action=stk_push';

echo "Testing with:\n";
echo "Bill ID: $test_bill_id\n";
echo "User ID: $test_user_id\n";
echo "Phone: {$test_payload['phone']}\n";
echo "Amount: {$test_payload['amount']}\n\n";

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http_code\n";
if ($error) echo "CURL Error: $error\n";
echo "Response: $response\n";

$data = json_decode($response, true);
if ($data) {
    echo "\nDecoded Response:\n";
    print_r($data);
}
?>
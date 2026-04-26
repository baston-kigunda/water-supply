<?php
require_once 'config/database.php';

// Get all consumer users with their meters and bills
echo "=== CONSUMER USERS WITH METERS AND BILLS ===\n";

$users = $conn->query("SELECT user_id, full_name, username, user_role FROM users WHERE user_role = 'consumer' ORDER BY user_id LIMIT 10");
while ($user = $users->fetch_assoc()) {
    echo "\n--- User: {$user['full_name']} (ID: {$user['user_id']}) ---\n";
    
    // Get meters
    $meters = $conn->query("SELECT meter_id, meter_number, meter_status, last_reading FROM smart_meters WHERE user_id = {$user['user_id']}");
    echo "Meters:\n";
    while ($meter = $meters->fetch_assoc()) {
        echo "  - ID: {$meter['meter_id']}, Number: {$meter['meter_number']}, Status: {$meter['meter_status']}, Last Reading: {$meter['last_reading']}\n";
    }
    
    // Get bills
    $bills = $conn->query("SELECT bill_id, billing_month, total_amount, bill_status FROM bills WHERE user_id = {$user['user_id']} ORDER BY bill_id DESC LIMIT 5");
    echo "Bills:\n";
    while ($bill = $bills->fetch_assoc()) {
        echo "  - ID: {$bill['bill_id']}, Month: {$bill['billing_month']}, Amount: {$bill['total_amount']}, Status: {$bill['bill_status']}\n";
    }
}
?>
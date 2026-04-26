<?php
require_once 'config/database.php';

// Check bills with invalid billing_month
echo "=== BILLS WITH INVALID billing_month ===\n";
$result = $conn->query("SELECT bill_id, user_id, meter_id, billing_month, total_amount, bill_status FROM bills WHERE billing_month = '0000-00-00' OR billing_month IS NULL");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}

echo "\n=== Check if there's a meter with meter_id=18 ===\n";
$result = $conn->query("SELECT * FROM smart_meters WHERE meter_id = 18");
$meter = $result->fetch_assoc();
if ($meter) {
    print_r($meter);
} else {
    echo "Meter 18 not found!\n";
}

echo "\n=== Check user 25 (who has bill 32 with meter_id=18) ===\n";
$result = $conn->query("SELECT user_id, full_name, username FROM users WHERE user_id = 25");
$user = $result->fetch_assoc();
if ($user) {
    print_r($user);
} else {
    echo "User 25 not found!\n";
}

echo "\n=== Meter readings for meter_id 1 (user 2) ===\n";
$result = $conn->query("SELECT * FROM meter_readings WHERE meter_id = 1 ORDER BY reading_time DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
?>
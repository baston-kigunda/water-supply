<?php
require_once 'config/database.php';

echo "=== BILLS TABLE SCHEMA ===\n";
$result = $conn->query("DESCRIBE bills");
while ($row = $result->fetch_assoc()) {
    echo "{$row['Field']} - {$row['Type']}\n";
}

echo "\n=== BILLS WITH USER ID 2 ===\n";
$result = $conn->query("SELECT bill_id, user_id, meter_id, billing_month, total_amount, bill_status FROM bills WHERE user_id = 2");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}

echo "\n=== SMART METERS FOR USER ID 2 ===\n";
$result = $conn->query("SELECT meter_id, user_id, meter_number, meter_status, last_reading FROM smart_meters WHERE user_id = 2");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
?>
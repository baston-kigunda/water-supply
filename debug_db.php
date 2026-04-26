<?php
require_once 'config/database.php';

echo "=== SMART METERS ===\n";
$result = $conn->query("SELECT meter_id, user_id, meter_number, meter_status, last_reading FROM smart_meters LIMIT 5");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}

echo "\n=== METER READINGS (recent) ===\n";
$result = $conn->query("SELECT meter_id, reading_value, reading_time, is_automated FROM meter_readings ORDER BY reading_time DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}

echo "\n=== BILLS (recent) ===\n";
$result = $conn->query("SELECT bill_id, user_id, meter_id, billing_month, total_amount, bill_status FROM bills ORDER BY bill_id DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
?>
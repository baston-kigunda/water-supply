<?php
require_once 'config/database.php';

// Check what happens when user ID 2 (baston) logs in - simulate the dashboard queries
$user_id = 2;

echo "=== SIMULATING USER DASHBOARD FOR USER ID $user_id ===\n\n";

// Query 1: Get user's meters
echo "1. Get user's meters:\n";
$stmt = $conn->prepare("SELECT meter_id, meter_number, location FROM smart_meters WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$meters = $stmt->get_result();
echo "Found " . $meters->num_rows . " meters\n";
while ($meter = $meters->fetch_assoc()) {
    print_r($meter);
}

// Query 2: Get billing stats - total billed
echo "\n2. Total billed:\n";
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM bills WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
echo "Total: " . $result->fetch_assoc()['total'] . "\n";

// Query 3: Get billing stats - total paid
echo "\n3. Total paid:\n";
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE user_id = ? AND payment_status = 'completed'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
echo "Total: " . $result->fetch_assoc()['total'] . "\n";

// Query 4: Get pending bills count
echo "\n4. Pending bills count:\n";
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bills WHERE user_id = ? AND bill_status IN ('pending', 'overdue')");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
echo "Count: " . $result->fetch_assoc()['count'] . "\n";

// Query 5: Get latest bill
echo "\n5. Latest bill:\n";
$stmt = $conn->prepare("SELECT bill_id, total_amount, bill_status, due_date FROM bills WHERE user_id = ? ORDER BY bill_id DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$latest_bill = $result->fetch_assoc();
print_r($latest_bill);

// Query 6: Get meter readings for user's meters
echo "\n6. Meter readings for user's meters:\n";
$stmt = $conn->prepare("
    SELECT mr.*, sm.meter_number, sm.user_id as meter_user_id
    FROM meter_readings mr
    JOIN smart_meters sm ON mr.meter_id = sm.meter_id
    WHERE sm.user_id = ?
    ORDER BY mr.reading_time DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
?>
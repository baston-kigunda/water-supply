<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];

echo "=== CURRENT USER ===\n";
echo "User ID: $user_id\n";

$stmt = $conn->prepare("SELECT user_id, full_name, username, user_role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
print_r($user);

echo "\n=== METERS FOR THIS USER ===\n";
$stmt = $conn->prepare("SELECT meter_id, meter_number, location, meter_status, last_reading FROM smart_meters WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$meters = $stmt->get_result();
while ($meter = $meters->fetch_assoc()) {
    print_r($meter);
}

echo "\n=== BILLS FOR THIS USER ===\n";
$stmt = $conn->prepare("SELECT bill_id, meter_id, billing_month, total_amount, bill_status FROM bills WHERE user_id = ? ORDER BY bill_id DESC LIMIT 10");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bills = $stmt->get_result();
while ($bill = $bills->fetch_assoc()) {
    print_r($bill);
}

echo "\n=== BILLING STATS ===\n";
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM bills WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
echo "Total Billed: " . $stmt->get_result()->fetch_assoc()['total'] . "\n";

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE user_id = ? AND payment_status = 'completed'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
echo "Total Paid: " . $stmt->get_result()->fetch_assoc()['total'] . "\n";
?>
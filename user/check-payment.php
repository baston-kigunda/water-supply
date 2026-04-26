<?php
// check-payment.php - Check payment status
require_once '../includes/functions.php';
require_once '../config/database.php';

requireLogin();

$reference = $_GET['reference'] ?? '';
$bill_id = $_GET['bill_id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Check payment status
$stmt = $conn->prepare("
    SELECT payment_status, transaction_code, amount 
    FROM payments 
    WHERE (transaction_code = ? OR checkout_request_id = ?) AND user_id = ?
    ORDER BY id DESC LIMIT 1
");
$stmt->bind_param("ssi", $reference, $reference, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();

if ($payment && $payment['payment_status'] == 'completed') {
    // Payment completed
    header("Location: user-payments.php?message=Payment of " . urlencode(formatCurrency($payment['amount'])) . " completed successfully. Reference: " . urlencode($payment['transaction_code']));
    exit();
} elseif ($payment && $payment['payment_status'] == 'failed') {
    header("Location: user-payments.php?error=Payment failed. Please try again.");
    exit();
} else {
    // Still pending, redirect back to status page
    header("Location: payment-status.php?reference=" . urlencode($reference) . "&bill_id=" . $bill_id);
    exit();
}
?>
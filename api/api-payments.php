<?php
// ========================================
// FILE: api-payments.php
// PURPOSE: Handle M-Pesa payment callbacks and processing
// ========================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');

require_once '../config/database.php';
require_once '../includes/functions.php';

// M-Pesa configuration
define('MPESA_CONSUMER_KEY', 'WEmmiMG7TFPW11mjJcT0coqp11QHLkifIMvgQ3K8gCEp3DyD');
define('MPESA_CONSUMER_SECRET', 'g7yLC8rmkxxA1mDkGQvzf5IAbkr5CQdIBgJAGihpgssD2s5dneBkiVexhYGcBRbk');
define('MPESA_PASSKEY', 'your_passkey');
define('MPESA_SHORTCODE', '174379');
define('MPESA_ENVIRONMENT', 'sandbox'); // sandbox or production

// Log all callbacks for debugging
function logCallback($data) {
    $log_file = '../logs/mpesa_' . date('Y-m-d') . '.log';
    $log_entry = date('Y-m-d H:i:s') . " - " . json_encode($data) . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

logCallback($input);
// Handle different endpoints
$endpoint = isset($_GET['action']) ? $_GET['action'] : '';
switch ($endpoint) {
    case 'stk_push':
        // Initiate STK Push
        handleSTKPush($input);
        break;
        
    case 'callback':
        // M-Pesa callback URL
        handleCallback($input);
        break;
        
    case 'confirm':
        // Payment confirmation
        handleConfirmation($input);
        break;
        
    case 'balance':
        // Check balance
        checkBalance($input);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid endpoint']);
        break;
}

// Handle STK Push
function handleSTKPush($data) {
    global $conn;
    
    $phone = $data['phone'] ?? '';
    $amount = $data['amount'] ?? 0;
    $bill_id = $data['bill_id'] ?? null;
    $user_id = $data['user_id'] ?? null;
    
    if (!$phone || !$amount || !$user_id) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    // Format phone number (remove 0 or +254)
    $phone = preg_replace('/^0/', '254', $phone);
    $phone = preg_replace('/^\+/', '', $phone);
    
    // Generate transaction reference
    $reference = 'WS' . time() . rand(100, 999);
    
    // Here you would integrate with M-Pesa API
    // This is a simplified response for testing
    
    // Save pending payment
    $stmt = mysqli_prepare($conn, "INSERT INTO payments (user_id, bill_id, amount, payment_method, transaction_code, payment_status) VALUES (?, ?, ?, 'mpesa', ?, 'pending')");
    mysqli_stmt_bind_param($stmt, "iids", $user_id, $bill_id, $amount, $reference);
    mysqli_stmt_execute($stmt);
    
    echo json_encode([
        'success' => true,
        'message' => 'STK Push initiated',
        'data' => [
            'reference' => $reference,
            'phone' => $phone,
            'amount' => $amount,
            'status' => 'pending'
        ]
    ]);
}

// Handle M-Pesa callback
function handleCallback($data) {
    global $conn;
    
    // Extract callback data
    $transaction_code = $data['TransID'] ?? $data['transaction_code'] ?? '';
    $amount = $data['TransAmount'] ?? $data['amount'] ?? 0;
    $phone = $data['MSISDN'] ?? $data['phone'] ?? '';
    $status = $data['ResultCode'] ?? 0;
    
    if ($status == 0) {
        // Payment successful
        $update = mysqli_query($conn, "UPDATE payments SET payment_status = 'completed', transaction_code = '$transaction_code' WHERE transaction_code = '$transaction_code'");
        
        if (mysqli_affected_rows($conn) > 0) {
            // Get payment details
            $payment = mysqli_query($conn, "SELECT * FROM payments WHERE transaction_code = '$transaction_code'");
            $payment_data = mysqli_fetch_assoc($payment);
            
            if ($payment_data && $payment_data['bill_id']) {
                updateBillStatusFromPayments((int) $payment_data['bill_id']);
            }
            
            // Create notification
            if ($payment_data['user_id']) {
                createNotification($payment_data['user_id'], 'Payment Successful', "Your payment of KSh $amount has been received. Reference: $transaction_code", 'payment');
            }
            
            echo json_encode(['success' => true, 'message' => 'Payment processed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Payment record not found']);
        }
    } else {
        // Payment failed
        mysqli_query($conn, "UPDATE payments SET payment_status = 'failed' WHERE transaction_code = '$transaction_code'");
        echo json_encode(['success' => false, 'message' => 'Payment failed', 'code' => $status]);
    }
}

// Handle payment confirmation
function handleConfirmation($data) {
    global $conn;
    
    $reference = $data['reference'] ?? '';
    
    if (!$reference) {
        echo json_encode(['success' => false, 'message' => 'Reference required']);
        return;
    }
    
    $result = mysqli_query($conn, "SELECT * FROM payments WHERE transaction_code = '$reference'");
    
    if (mysqli_num_rows($result) > 0) {
        $payment = mysqli_fetch_assoc($result);
        echo json_encode([
            'success' => true,
            'data' => [
                'reference' => $payment['transaction_code'],
                'amount' => $payment['amount'],
                'status' => $payment['payment_status'],
                'date' => $payment['payment_date']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
    }
}

// Check balance (for testing)
function checkBalance($data) {
    global $conn;
    
    $user_id = $data['user_id'] ?? 0;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        return;
    }
    
    $balance = getUserBalance($user_id);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $user_id,
            'balance' => $balance,
            'formatted' => formatCurrency($balance)
        ]
    ]);
}
?>

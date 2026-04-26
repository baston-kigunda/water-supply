<?php
// ========================================
// FILE: api-payments.php
// PURPOSE: Handle M-Pesa payment callbacks and processing
// ========================================

// Skip ngrok warning page - MUST be at the very top
if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'ngrok') !== false) {
    header('ngrok-skip-browser-warning: true');
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

// M-Pesa configuration - Only define if not already defined
if (!defined('MPESA_CONSUMER_KEY')) {
    define('MPESA_CONSUMER_KEY', 'VTyHQW1YRtrVTHu6ld6vAAZfMM39uzMOMqvI6Wt71VGB7hwr');
}
if (!defined('MPESA_CONSUMER_SECRET')) {
    define('MPESA_CONSUMER_SECRET', 'bQZo2HX3qGRkzoTy1kY7GnoxYKARLEKktpSGoDODC6vtE4YkuOqvHlQjmUOtV3J3');
}
if (!defined('MPESA_PASSKEY')) {
    define('MPESA_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919');
}
if (!defined('MPESA_SHORTCODE')) {
    define('MPESA_SHORTCODE', '174379');
}
if (!defined('MPESA_ENVIRONMENT')) {
    define('MPESA_ENVIRONMENT', 'sandbox');
}
if (!defined('MPESA_CALLBACK_URL')) {
    define('MPESA_CALLBACK_URL', 'https://pouch-scion-panorama.ngrok-free.dev/watersupply/api/api-payments.php?action=callback');
}

// Log all callbacks for debugging
function logCallback($data) {
    $log_dir = __DIR__ . '/../logs/';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    $log_file = $log_dir . 'mpesa_' . date('Y-m-d') . '.log';
    $log_entry = date('Y-m-d H:i:s') . " - " . json_encode($data, JSON_PRETTY_PRINT) . PHP_EOL . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
}

logCallback(['endpoint' => $_GET['action'] ?? 'none', 'method' => $_SERVER['REQUEST_METHOD'], 'input' => $input]);

// Handle different endpoints
$endpoint = isset($_GET['action']) ? $_GET['action'] : '';
switch ($endpoint) {
    case 'stk_push':
        handleSTKPush($input);
        break;
    case 'callback':
        handleCallback();
        break;
    case 'confirm':
        handleConfirmation($input);
        break;
    case 'balance':
        checkBalance($input);
        break;
    case 'test':
        echo json_encode([
            'success' => true,
            'message' => 'API is working',
            'timestamp' => date('Y-m-d H:i:s'),
            'environment' => MPESA_ENVIRONMENT,
            'callback_url' => MPESA_CALLBACK_URL
        ]);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid endpoint']);
        break;
}


// Get M-Pesa Access Token - SIMPLIFIED VERSION
function getMpesaAccessToken() {
    $consumer_key = MPESA_CONSUMER_KEY;
    $consumer_secret = MPESA_CONSUMER_SECRET;
    
    $credentials = base64_encode($consumer_key . ':' . $consumer_secret);
    
    $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Log the response for debugging
    logCallback(['Auth Response' => ['http_code' => $http_code, 'response' => $response]]);
    
    if ($http_code == 200) {
        $result = json_decode($response);
        if (isset($result->access_token)) {
            return $result->access_token;
        }
    }
    
    return null;
}



// Handle STK Push - ACTUAL M-PESA INTEGRATION (FIXED VERSION)
function handleSTKPush($data) {
    global $conn;
    
    // Accept both JSON and form data
    if (empty($data)) {
        $data = $_POST;
    }
    
    $phone = $data['phone'] ?? '';
    $amount = $data['amount'] ?? 0;
    $bill_id = $data['bill_id'] ?? null;
    $user_id = $data['user_id'] ?? null;
    
    logCallback(['handleSTKPush called with' => $data]);
    
    // Validate inputs
    if (!$phone || !$amount || !$user_id) {
        echo json_encode([
            'success' => false, 
            'message' => 'Missing required fields. phone: ' . ($phone ? 'yes' : 'no') . ', amount: ' . ($amount ? 'yes' : 'no') . ', user_id: ' . ($user_id ? 'yes' : 'no')
        ]);
        return;
    }
    
    // Format phone number
    $original_phone = $phone;
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) == '0') {
        $phone = '254' . substr($phone, 1);
    } elseif (substr($phone, 0, 3) == '254') {
        $phone = $phone;
    } elseif (substr($phone, 0, 4) == '+254') {
        $phone = '254' . substr($phone, 4);
    }
    
    // Validate phone number
    if (strlen($phone) != 12 || substr($phone, 0, 3) != '254') {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid phone number format. Use 254XXXXXXXXX'
        ]);
        return;
    }
    
    // Validate amount
    $amount = (int) $amount;
    if ($amount < 1 || $amount > 150000) {
        echo json_encode([
            'success' => false, 
            'message' => 'Amount must be between 1 and 150,000 KSh'
        ]);
        return;
    }
    
    // Generate transaction reference
    $reference = 'WS' . time() . rand(100, 999);
    $timestamp = date('YmdHis');
    $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);
    
    // Get access token
    $access_token = getMpesaAccessToken();
    if (!$access_token) {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to authenticate with M-Pesa. Please check your API credentials.'
        ]);
        return;
    }
    
    // Prepare STK Push request
    $url = (MPESA_ENVIRONMENT == 'sandbox') 
        ? 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
        : 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ));
    
    $curl_post_data = array(
        'BusinessShortCode' => MPESA_SHORTCODE,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $amount,
        'PartyA' => $phone,
        'PartyB' => MPESA_SHORTCODE,
        'PhoneNumber' => $phone,
        'CallBackURL' => MPESA_CALLBACK_URL,
        'AccountReference' => $reference,
        'TransactionDesc' => 'Water Bill Payment'
    );
    
    $data_string = json_encode($curl_post_data);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($curl);
    $result = json_decode($response);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);
    
    logCallback([
        'stk_push_request' => $curl_post_data,
        'http_code' => $http_code,
        'response' => $result
    ]);
    
    // Check if STK Push was initiated successfully
    if (isset($result->ResponseCode) && $result->ResponseCode == '0') {
        $merchant_request_id = $result->MerchantRequestID ?? null;
        $checkout_request_id = $result->CheckoutRequestID ?? null;
        
        // Check if checkout_request_id column exists
        $check_column = mysqli_query($conn, "SHOW COLUMNS FROM payments LIKE 'checkout_request_id'");
        if ($check_column && mysqli_num_rows($check_column) > 0) {
            $stmt = mysqli_prepare($conn, "INSERT INTO payments (user_id, bill_id, amount, payment_method, transaction_code, payment_status, merchant_request_id, checkout_request_id) VALUES (?, ?, ?, 'mpesa', ?, 'pending', ?, ?)");
            mysqli_stmt_bind_param($stmt, "iidsss", $user_id, $bill_id, $amount, $reference, $merchant_request_id, $checkout_request_id);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO payments (user_id, bill_id, amount, payment_method, transaction_code, payment_status) VALUES (?, ?, ?, 'mpesa', ?, 'pending')");
            mysqli_stmt_bind_param($stmt, "iids", $user_id, $bill_id, $amount, $reference);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            // Return success response
            echo json_encode([
                'success' => true,
                'message' => 'STK Push initiated successfully. Check your phone for the M-Pesa prompt.',
                'data' => [
                    'reference' => $reference,
                    'phone' => $phone,
                    'amount' => $amount,
                    'status' => 'pending',
                    'checkout_request_id' => $checkout_request_id,
                    'merchant_request_id' => $merchant_request_id
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to save payment record: ' . mysqli_error($conn)
            ]);
        }
    } else {
        // Handle error response
        $error_message = $result->errorMessage ?? $result->ResponseDescription ?? 'Failed to initiate STK Push';
        $response_code = $result->ResponseCode ?? null;
        
        echo json_encode([
            'success' => false,
            'message' => $error_message,
            'response_code' => $response_code,
            'debug_info' => [
                'phone' => $phone,
                'amount' => $amount,
                'reference' => $reference
            ]
        ]);
    }
}

// Handle M-Pesa callback
function handleCallback() {
    global $conn;
    
    $callback_json = file_get_contents('php://input');
    $callback_data = json_decode($callback_json, true);
    
    logCallback(['callback_received' => $callback_data]);
    
    // Respond immediately to M-Pesa
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    
    if (!isset($callback_data['Body']['stkCallback'])) {
        logCallback(['error' => 'Invalid callback structure']);
        return;
    }
    
    $stk_callback = $callback_data['Body']['stkCallback'];
    $result_code = $stk_callback['ResultCode'] ?? 1;
    $result_desc = $stk_callback['ResultDesc'] ?? 'Unknown error';
    $checkout_request_id = $stk_callback['CheckoutRequestID'] ?? '';
    
    $callback_metadata = $stk_callback['CallbackMetadata']['Item'] ?? [];
    
    $amount = 0;
    $transaction_code = '';
    
    foreach ($callback_metadata as $item) {
        switch ($item['Name']) {
            case 'Amount':
                $amount = $item['Value'];
                break;
            case 'MpesaReceiptNumber':
                $transaction_code = $item['Value'];
                break;
        }
    }
    
    // Find payment record
    $stmt = mysqli_prepare($conn, "SELECT * FROM payments WHERE checkout_request_id = ? ORDER BY id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $checkout_request_id);
    mysqli_stmt_execute($stmt);
    $payment_result = mysqli_stmt_get_result($stmt);
    $payment = mysqli_fetch_assoc($payment_result);
    mysqli_stmt_close($stmt);
    
    if (!$payment) {
        logCallback(['error' => 'Payment record not found', 'checkout_request_id' => $checkout_request_id]);
        return;
    }
    
    if ($result_code == 0 && $transaction_code) {
        // Payment successful
        $update_query = "UPDATE payments SET payment_status = 'completed', transaction_code = ?, amount = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "sii", $transaction_code, $amount, $payment['id']);
        
        if (mysqli_stmt_execute($stmt)) {
            if ($payment['bill_id']) {
                // Update bill status if function exists
                if (function_exists('updateBillStatusFromPayments')) {
                    updateBillStatusFromPayments((int) $payment['bill_id']);
                }
            }
            
            logCallback(['success' => 'Payment processed successfully', 'transaction_code' => $transaction_code]);
        }
    } else {
        // Payment failed
        $update_query = "UPDATE payments SET payment_status = 'failed', result_desc = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "si", $result_desc, $payment['id']);
        mysqli_stmt_execute($stmt);
        
        logCallback(['failed' => 'Payment failed', 'result_desc' => $result_desc]);
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
    
    $result = mysqli_query($conn, "SELECT * FROM payments WHERE transaction_code = '$reference' OR checkout_request_id = '$reference' ORDER BY id DESC LIMIT 1");
    
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

// Check balance
function checkBalance($data) {
    global $conn;
    
    $user_id = $data['user_id'] ?? 0;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        return;
    }
    
    // Simple balance calculation
    $query = "SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE user_id = ? AND payment_status = 'completed'";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $balance = $row ? (float)$row['total_paid'] : 0;
    mysqli_stmt_close($stmt);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $user_id,
            'balance' => $balance,
            'formatted' => 'KSh ' . number_format($balance, 2)
        ]
    ]);
}
?>
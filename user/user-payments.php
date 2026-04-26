<?php
// ========================================
// FILE: user-payments.php
// PURPOSE: Make payments and view payment history
// ========================================

require_once '../includes/functions.php';
require_once '../config/database.php';

requireLogin();

if (isAdmin()) {
    header('Location: ../admin/admin-dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Function to get base URL dynamically
function getBaseUrl() {
    // Check if we're using ngrok
    if (strpos($_SERVER['HTTP_HOST'], 'ngrok') !== false) {
        return 'https://' . $_SERVER['HTTP_HOST'] . '/watersupply';
    }
    
    // Check if we're on Heroku
    if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] == 'darajambili.herokuapp.com') {
        return 'https://darajambili.herokuapp.com';
    }
    
    // Local development
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . "://" . $host . "/watersupply";
}

// Handle payment submission with M-Pesa integration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_payment'])) {
    $bill_id = isset($_POST['bill_id']) ? (int) $_POST['bill_id'] : 0;
    $amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0;
    $payment_method = trim($_POST['payment_method'] ?? '');
    $mpesa_number = trim($_POST['mpesa_number'] ?? '');
    
    $allowed_payment_methods = ['mpesa', 'card', 'bank', 'cash'];
    
    // Get bill details with proper error handling
    $bill = getBillPaymentSummary($bill_id, $user_id);
    
    if (!$bill) {
        $error = "Invalid bill selected.";
    } elseif ($bill['bill_status'] == 'paid' || $bill['amount_due'] <= 0) {
        $error = "This bill is already paid or has no outstanding balance.";
    } elseif ($amount <= 0) {
        $error = "Invalid payment amount. Please enter an amount greater than 0.";
    } elseif (!in_array($payment_method, $allowed_payment_methods, true)) {
        $error = "Please select a valid payment method.";
    } elseif ($amount > $bill['amount_due']) {
        $error = "Payment amount cannot exceed the remaining balance of " . formatCurrency($bill['amount_due']) . ".";
    } elseif ($payment_method == 'mpesa' && empty($mpesa_number)) {
        $error = "Please enter your M-Pesa phone number.";
    } else {
        
        if ($payment_method == 'mpesa') {
            // Clean phone number
            $mpesa_number = preg_replace('/[^0-9]/', '', $mpesa_number);
            if (substr($mpesa_number, 0, 1) == '0') {
                $mpesa_number = '254' . substr($mpesa_number, 1);
            } elseif (substr($mpesa_number, 0, 4) == '+254') {
                $mpesa_number = '254' . substr($mpesa_number, 4);
            }
            
            // Validate phone number length
            if (strlen($mpesa_number) != 12) {
                $error = "Invalid phone number format. Please use a valid Kenyan phone number (e.g., 07XXXXXXXX or 254XXXXXXXXX).";
            } else {
                // Integrate with M-Pesa STK Push API
                $mpesa_payload = [
                    'phone' => $mpesa_number,
                    'amount' => (int) $amount,
                    'bill_id' => $bill_id,
                    'user_id' => $user_id
                ];
                
                // Call the STK Push API
                $base_url = getBaseUrl();
                $api_url = $base_url . "/api/api-payments.php?action=stk_push";
                
                // Log for debugging
                error_log("Calling API: " . $api_url);
                error_log("Payload: " . json_encode($mpesa_payload));
                
                // Use JSON payload for better compatibility with the API
                $ch = curl_init($api_url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($mpesa_payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);
                
                error_log("HTTP Response Code: " . $http_code);
                error_log("CURL Error: " . ($curl_error ?: 'None'));
                error_log("Response: " . $response);
                
                if ($response === false) {
                    $error = "Unable to connect to payment service. Please try again later.";
                    error_log("cURL error: " . $curl_error);
                } elseif ($http_code == 200) {
                    $result = json_decode($response, true);
                    
                    if ($result && isset($result['success']) && $result['success'] === true) {
                        // Store payment info in session for polling
                        $_SESSION['pending_payment'] = [
                            'reference' => $result['data']['reference'],
                            'bill_id' => $bill_id,
                            'amount' => $amount,
                            'user_id' => $user_id,
                            'checkout_request_id' => $result['data']['checkout_request_id'] ?? null,
                            'timestamp' => time()
                        ];
                        
                        // Redirect to payment status page
                        header("Location: payment-status.php?reference=" . urlencode($result['data']['reference']) . "&bill_id=" . $bill_id);
                        exit();
                    } else {
                        $error_message = $result['message'] ?? "Failed to initiate M-Pesa payment. Please try again.";
                        $error = $error_message;
                        
                        // Log the error details
                        error_log("M-Pesa API Error: " . json_encode($result));
                    }
                } else {
                    $error = "Payment service is temporarily unavailable. Please try again later.";
                    error_log("M-Pesa API error: HTTP $http_code - " . substr($response, 0, 500));
                }
            }
        } else {
            // Handle other payment methods (card, bank, cash)
            $transaction_code = 'TXN' . date('Ymd') . rand(100000, 999999);
            
            $stmt = $conn->prepare("
                INSERT INTO payments (bill_id, user_id, amount, payment_method, transaction_code, payment_status, payment_date) 
                VALUES (?, ?, ?, ?, ?, 'completed', NOW())
            ");
            $stmt->bind_param("iidss", $bill_id, $user_id, $amount, $payment_method, $transaction_code);
            
            if ($stmt->execute()) {
                // Update bill status
                if (function_exists('updateBillStatusFromPayments')) {
                    updateBillStatusFromPayments($bill_id);
                }
                
                $updated_bill = getBillPaymentSummary($bill_id, $user_id);
                $remaining_balance = $updated_bill ? $updated_bill['amount_due'] : max(0, $bill['amount_due'] - $amount);
                
                if (function_exists('createNotification')) {
                    createNotification(
                        $user_id,
                        "Payment Successful",
                        "Your payment of " . formatCurrency($amount) . " for bill #$bill_id has been received. Remaining balance: " . formatCurrency($remaining_balance) . ". Transaction code: $transaction_code",
                        'payment'
                    );
                }
                
                $message = "Payment successful! Transaction code: $transaction_code";
                
                // Refresh the page to show updated data
                echo "<script>setTimeout(function() { window.location.reload(); }, 2000);</script>";
            } else {
                $error = "Error processing payment: " . $conn->error;
            }
        }
    }
}

// Get unpaid bills for payment dashboard
$stmt = $conn->prepare("
    SELECT
        b.bill_id,
        b.billing_month,
        b.bill_status,
        b.total_amount,
        b.due_date,
        sm.meter_number,
        COALESCE(SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END), 0) as amount_paid
    FROM bills b
    JOIN smart_meters sm ON b.meter_id = sm.meter_id
    LEFT JOIN payments p ON b.bill_id = p.bill_id
    WHERE b.user_id = ? AND b.bill_status IN ('pending', 'overdue')
    GROUP BY b.bill_id, b.billing_month, b.bill_status, b.total_amount, b.due_date, sm.meter_number
    ORDER BY b.due_date ASC, b.bill_id ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unpaid_bills_result = $stmt->get_result();

$unpaid_bills = [];
while ($bill = $unpaid_bills_result->fetch_assoc()) {
    $bill['total_amount'] = (float) $bill['total_amount'];
    $bill['amount_paid'] = (float) ($bill['amount_paid'] ?? 0);
    $bill['amount_due'] = max(0, $bill['total_amount'] - $bill['amount_paid']);

    if ($bill['amount_due'] <= 0) {
        continue;
    }

    $unpaid_bills[] = $bill;
}

// Get payment history with proper error handling
$payments_query = "
    SELECT p.*, b.billing_month, b.total_amount as bill_amount, sm.meter_number
    FROM payments p
    JOIN bills b ON p.bill_id = b.bill_id
    JOIN smart_meters sm ON b.meter_id = sm.meter_id
    WHERE p.user_id = ?
    ORDER BY p.payment_date DESC
";
$stmt = $conn->prepare($payments_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$payments = $stmt->get_result();

// Calculate statistics
$stats = [];

// Total spent
$result = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM payments 
    WHERE user_id = $user_id AND payment_status = 'completed'
");
$stats['total_paid'] = $result->fetch_assoc()['total'];

// This month's payments
$result = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM payments 
    WHERE user_id = $user_id 
    AND payment_status = 'completed'
    AND MONTH(payment_date) = MONTH(CURRENT_DATE())
    AND YEAR(payment_date) = YEAR(CURRENT_DATE())
");
$stats['month_paid'] = $result->fetch_assoc()['total'];
$stats['bills_to_pay'] = count($unpaid_bills);
$stats['amount_due'] = array_reduce($unpaid_bills, function ($carry, $bill) {
    return $carry + $bill['amount_due'];
}, 0.0);

// Last payment
$result = $conn->query("
    SELECT * FROM payments 
    WHERE user_id = $user_id AND payment_status = 'completed' 
    ORDER BY payment_date DESC LIMIT 1
");
$last_payment = $result->fetch_assoc();

// Payment methods breakdown
$methods = $conn->query("
    SELECT payment_method, COUNT(*) as count, SUM(amount) as total
    FROM payments
    WHERE user_id = $user_id AND payment_status = 'completed'
    GROUP BY payment_method
");

$page_title = "Payments";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="user-dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="user-consumption.php">
                                <i class="bi bi-graph-up"></i> Consumption
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="user-bills.php">
                                <i class="bi bi-receipt"></i> My Bills
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="user-payments.php">
                                <i class="bi bi-cash-stack"></i> Payments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="user-report-leak.php">
                                <i class="bi bi-exclamation-triangle"></i> Report Leak
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="user-notifications.php">
                                <i class="bi bi-bell"></i> Notifications
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="user-profile.php">
                                <i class="bi bi-person"></i> Profile
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Payments</h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6>Total Payments</h6>
                                <h3><?php echo formatCurrency($stats['total_paid']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6>This Month</h6>
                                <h3><?php echo formatCurrency($stats['month_paid']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h6>Bills To Pay</h6>
                                <h3><?php echo $stats['bills_to_pay']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6>Amount Due</h6>
                                <h3><?php echo formatCurrency($stats['amount_due']); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($last_payment): ?>
                    <div class="alert alert-light border mb-4">
                        <i class="bi bi-clock-history text-primary"></i>
                        Last payment: <strong><?php echo formatCurrency($last_payment['amount']); ?></strong>
                        on <?php echo date('M d, Y', strtotime($last_payment['payment_date'])); ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Make Payment Form -->
                    <div class="col-md-5 mb-4">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="bi bi-cash"></i> Make a Payment</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($unpaid_bills)): ?>
                                    <form method="POST" onsubmit="return validatePayment()">
                                        <div class="mb-3">
                                            <label for="bill_id" class="form-label">Select Bill</label>
                                            <select class="form-control" id="bill_id" name="bill_id" required onchange="updateBillAmount()">
                                                <option value="">-- Choose Bill --</option>
                                                <?php foreach ($unpaid_bills as $bill): ?>
                                                    <option value="<?php echo $bill['bill_id']; ?>" 
                                                            data-amount-due="<?php echo number_format($bill['amount_due'], 2, '.', ''); ?>"
                                                            data-total-amount="<?php echo number_format($bill['total_amount'], 2, '.', ''); ?>"
                                                            data-paid-amount="<?php echo number_format($bill['amount_paid'], 2, '.', ''); ?>"
                                                            data-meter="<?php echo htmlspecialchars($bill['meter_number']); ?>"
                                                            data-status="<?php echo htmlspecialchars($bill['bill_status']); ?>"
                                                            data-duedate="<?php echo $bill['due_date']; ?>">
                                                        #<?php echo $bill['bill_id']; ?> - 
                                                        <?php echo htmlspecialchars($bill['meter_number']); ?> - 
                                                        <?php echo date('M Y', strtotime($bill['billing_month'])); ?> - 
                                                        Due <?php echo formatCurrency($bill['amount_due']); ?>
                                                        (<?php echo ucfirst($bill['bill_status']); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="amount" class="form-label">Amount (<?php echo CURRENCY; ?>)</label>
                                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="1" required>
                                            <small class="text-muted">Enter the amount you want to pay toward the remaining balance</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="payment_method" class="form-label">Payment Method</label>
                                            <select class="form-control" id="payment_method" name="payment_method" required>
                                                <option value="">-- Select Method --</option>
                                                <option value="mpesa">M-Pesa (STK Push)</option>
                                                <option value="card">Credit/Debit Card</option>
                                                <option value="bank">Bank Transfer</option>
                                                <option value="cash">Cash (Office)</option>
                                            </select>
                                        </div>
                                        
                                        <div id="mpesa_details" style="display: none;" class="mb-3">
                                            <label for="mpesa_number" class="form-label">M-Pesa Phone Number</label>
                                            <input type="tel" class="form-control" id="mpesa_number" name="mpesa_number" placeholder="07XXXXXXXX">
                                            <small class="text-muted">You will receive an STK push prompt on this number.</small>
                                        </div>
                                        
                                        <div class="alert alert-info" id="bill_info" style="display: none;">
                                            <strong>Meter:</strong> <span id="meter_number"></span><br>
                                            <strong>Status:</strong> <span id="bill_status"></span><br>
                                            <strong>Due Date:</strong> <span id="due_date"></span><br>
                                            <strong>Original Bill:</strong> <span id="full_amount"></span><br>
                                            <strong>Already Paid:</strong> <span id="paid_amount"></span><br>
                                            <strong>Remaining:</strong> <span id="remaining_amount"></span>
                                        </div>
                                        
                                        <button type="submit" name="make_payment" class="btn btn-success w-100">
                                            <i class="bi bi-lock"></i> Process Payment
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="alert alert-success">
                                        <i class="bi bi-check-circle"></i>
                                        You have no pending bills. All bills are paid!
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Payment History -->
                    <div class="col-md-7 mb-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Payment History</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($payments && $payments->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table id="paymentsTable" class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Bill #</th>
                                                    <th>Period</th>
                                                    <th>Amount</th>
                                                    <th>Method</th>
                                                    <th>Transaction Code</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($payment = $payments->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo date('M d, Y H:i', strtotime($payment['payment_date'])); ?></td>
                                                        <td>#<?php echo $payment['bill_id']; ?></td>
                                                        <td><?php echo date('M Y', strtotime($payment['billing_month'])); ?></td>
                                                        <td><strong><?php echo formatCurrency($payment['amount']); ?></strong></td>
                                                        <td><?php echo strtoupper($payment['payment_method']); ?></td>
                                                        <td><small><?php echo htmlspecialchars($payment['transaction_code']); ?></small></td>
                                                        <td>
                                                            <span class="badge bg-<?php 
                                                                echo $payment['payment_status'] == 'completed' ? 'success' : 
                                                                    ($payment['payment_status'] == 'pending' ? 'warning' : 'danger'); 
                                                            ?>">
                                                                <?php echo ucfirst($payment['payment_status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        No payment history found.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        function formatCurrencyValue(amount) {
            return '<?php echo CURRENCY; ?> ' + Number(amount).toFixed(2);
        }

        function updateBillAmount() {
            var select = document.getElementById('bill_id');
            if (!select || select.selectedIndex <= 0) {
                document.getElementById('bill_info').style.display = 'none';
                return;
            }

            var option = select.options[select.selectedIndex];
            var amountDue = option.getAttribute('data-amount-due');
            var totalAmount = option.getAttribute('data-total-amount');
            var paidAmount = option.getAttribute('data-paid-amount');
            var meter = option.getAttribute('data-meter');
            var status = option.getAttribute('data-status');
            var dueDate = option.getAttribute('data-duedate');
            
            if (amountDue) {
                document.getElementById('amount').value = parseFloat(amountDue).toFixed(2);
                document.getElementById('full_amount').innerHTML = formatCurrencyValue(totalAmount);
                document.getElementById('paid_amount').innerHTML = formatCurrencyValue(paidAmount);
                document.getElementById('remaining_amount').innerHTML = formatCurrencyValue(amountDue);
                document.getElementById('meter_number').innerHTML = meter;
                document.getElementById('bill_status').innerHTML = status ? status.charAt(0).toUpperCase() + status.slice(1) : '';
                document.getElementById('due_date').innerHTML = new Date(dueDate).toLocaleDateString();
                document.getElementById('bill_info').style.display = 'block';
            }
        }

        function validatePayment() {
            var amount = parseFloat(document.getElementById('amount').value || '0');
            var select = document.getElementById('bill_id');
            var paymentMethod = document.getElementById('payment_method').value;

            if (!select || !select.value) {
                alert('Please select a bill to pay');
                return false;
            }

            var option = select.options[select.selectedIndex];
            var amountDue = parseFloat(option.getAttribute('data-amount-due') || '0');

            if (isNaN(amount) || amount <= 0) {
                alert('Please enter a valid amount greater than 0');
                return false;
            }

            if (amount > amountDue) {
                alert('Payment amount cannot exceed the remaining balance of ' + formatCurrencyValue(amountDue));
                return false;
            }

            if (!paymentMethod) {
                alert('Please select a payment method');
                return false;
            }

            if (paymentMethod === 'mpesa') {
                var mpesaNumber = document.getElementById('mpesa_number').value;
                if (!mpesaNumber) {
                    alert('Please enter your M-Pesa phone number');
                    return false;
                }
                
                var phoneRegex = /^(07|01|\+254|254)\d{8}$/;
                if (!phoneRegex.test(mpesaNumber)) {
                    alert('Please enter a valid Kenyan phone number');
                    return false;
                }
                
                return confirm('You will receive an STK push prompt on ' + mpesaNumber + '.\n\nConfirm payment of ' + formatCurrencyValue(amount) + '?');
            }

            return confirm('Confirm payment of ' + formatCurrencyValue(amount) + '?');
        }

        function selectBillForPayment(billId) {
            var select = document.getElementById('bill_id');
            if (select) {
                for (var i = 0; i < select.options.length; i++) {
                    if (select.options[i].value === billId) {
                        select.selectedIndex = i;
                        updateBillAmount();
                        break;
                    }
                }
            }
        }

        // Initialize event listeners
        var paymentMethod = document.getElementById('payment_method');
        if (paymentMethod) {
            paymentMethod.addEventListener('change', function() {
                var mpesaDetails = document.getElementById('mpesa_details');
                if (mpesaDetails) {
                    mpesaDetails.style.display = this.value === 'mpesa' ? 'block' : 'none';
                }
            });
        }
    </script>

    <?php include '../includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#paymentsTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 10
            });
        });
    </script>
</body>
</html>
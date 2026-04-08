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

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_payment'])) {
    $bill_id = $_POST['bill_id'];
    $amount = $_POST['amount'];
    $payment_method = $_POST['payment_method'];
    
    // Validate amount
    $stmt = $conn->prepare("SELECT total_amount, bill_status FROM bills WHERE bill_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $bill_id, $user_id);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    
    if (!$bill) {
        $error = "Invalid bill selected.";
    } elseif ($bill['bill_status'] == 'paid') {
        $error = "This bill is already paid.";
    } elseif ($amount <= 0) {
        $error = "Invalid payment amount.";
    } elseif ($amount > $bill['total_amount']) {
        $error = "Payment amount cannot exceed bill amount.";
    } else {
        // Generate transaction code
        $transaction_code = strtoupper(uniqid('TXN'));
        
        // Record payment
        $stmt = $conn->prepare("
            INSERT INTO payments (bill_id, user_id, amount, payment_method, transaction_code, payment_status) 
            VALUES (?, ?, ?, ?, ?, 'completed')
        ");
        $stmt->bind_param("iidss", $bill_id, $user_id, $amount, $payment_method, $transaction_code);
        
        if ($stmt->execute()) {
            // Check if bill is fully paid
            $total_paid = $conn->query("
                SELECT COALESCE(SUM(amount), 0) as total 
                FROM payments 
                WHERE bill_id = $bill_id AND payment_status = 'completed'
            ")->fetch_assoc()['total'];
            
            if ($total_paid >= $bill['total_amount']) {
                $conn->query("UPDATE bills SET bill_status = 'paid' WHERE bill_id = $bill_id");
            }
            
            // Create notification
            createNotification(
                $user_id,
                "Payment Successful",
                "Your payment of " . formatCurrency($amount) . " for bill #$bill_id has been received. Transaction code: $transaction_code",
                'payment'
            );
            
            $message = "Payment successful! Transaction code: $transaction_code";
        } else {
            $error = "Error processing payment: " . $conn->error;
        }
    }
}

// Get unpaid bills for payment dropdown
$unpaid_bills = $conn->query("
    SELECT b.*, sm.meter_number 
    FROM bills b
    JOIN smart_meters sm ON b.meter_id = sm.meter_id
    WHERE b.user_id = $user_id AND b.bill_status IN ('pending', 'overdue')
    ORDER BY b.due_date ASC
");

// Get payment history
$payments = $conn->query("
    SELECT p.*, b.billing_month, b.total_amount as bill_amount, sm.meter_number
    FROM payments p
    JOIN bills b ON p.bill_id = b.bill_id
    JOIN smart_meters sm ON b.meter_id = sm.meter_id
    WHERE p.user_id = $user_id
    ORDER BY p.payment_date DESC
");

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
                        <i class="bi bi-check-circle"></i> <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card dashboard-card bg-primary text-white">
                            <div class="card-body">
                                <h6>Total Payments</h6>
                                <h3><?php echo formatCurrency($stats['total_paid']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card dashboard-card bg-success text-white">
                            <div class="card-body">
                                <h6>This Month</h6>
                                <h3><?php echo formatCurrency($stats['month_paid']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card dashboard-card bg-info text-white">
                            <div class="card-body">
                                <h6>Last Payment</h6>
                                <?php if ($last_payment): ?>
                                    <h5><?php echo formatCurrency($last_payment['amount']); ?></h5>
                                    <small><?php echo date('M d, Y', strtotime($last_payment['payment_date'])); ?></small>
                                <?php else: ?>
                                    <h5>No payments yet</h5>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Make Payment Form -->
                    <div class="col-md-5 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="bi bi-cash"></i> Make a Payment</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($unpaid_bills->num_rows > 0): ?>
                                    <form method="POST" onsubmit="return validatePayment()">
                                        <div class="mb-3">
                                            <label for="bill_id" class="form-label">Select Bill</label>
                                            <select class="form-control" id="bill_id" name="bill_id" required onchange="updateBillAmount()">
                                                <option value="">-- Choose Bill --</option>
                                                <?php while ($bill = $unpaid_bills->fetch_assoc()): ?>
                                                    <option value="<?php echo $bill['bill_id']; ?>" 
                                                            data-amount="<?php echo $bill['total_amount']; ?>"
                                                            data-duedate="<?php echo $bill['due_date']; ?>">
                                                        #<?php echo $bill['bill_id']; ?> - 
                                                        <?php echo date('M Y', strtotime($bill['billing_month'])); ?> - 
                                                        <?php echo formatCurrency($bill['total_amount']); ?>
                                                        (<?php echo ucfirst($bill['bill_status']); ?>)
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="amount" class="form-label">Amount (<?php echo CURRENCY; ?>)</label>
                                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="1" required>
                                            <small class="text-muted">You can pay partially or full amount</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="payment_method" class="form-label">Payment Method</label>
                                            <select class="form-control" id="payment_method" name="payment_method" required>
                                                <option value="">-- Select Method --</option>
                                                <option value="mpesa">M-Pesa</option>
                                                <option value="card">Credit/Debit Card</option>
                                                <option value="bank">Bank Transfer</option>
                                                <option value="cash">Cash (Office)</option>
                                            </select>
                                        </div>
                                        
                                        <div id="mpesa_details" style="display: none;" class="mb-3">
                                            <label for="mpesa_number" class="form-label">M-Pesa Phone Number</label>
                                            <input type="text" class="form-control" id="mpesa_number" placeholder="07XXXXXXXX">
                                            <small class="text-muted">You will receive an STK push prompt</small>
                                        </div>
                                        
                                        <div id="card_details" style="display: none;" class="mb-3">
                                            <p class="text-info">You will be redirected to secure payment gateway</p>
                                        </div>
                                        
                                        <div class="alert alert-info" id="bill_info" style="display: none;">
                                            <strong>Bill Due Date:</strong> <span id="due_date"></span><br>
                                            <strong>Full Amount:</strong> <span id="full_amount"></span>
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
                        
                        <!-- Payment Methods Summary -->
                        <?php if ($methods->num_rows > 0): ?>
                            <div class="card dashboard-card mt-4">
                                <div class="card-header">
                                    <h5>Payment Methods Used</h5>
                                </div>
                                <div class="card-body">
                                    <?php while ($method = $methods->fetch_assoc()): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>
                                                <i class="bi bi-<?php 
                                                    echo $method['payment_method'] == 'mpesa' ? 'phone' : 
                                                        ($method['payment_method'] == 'card' ? 'credit-card' : 
                                                        ($method['payment_method'] == 'bank' ? 'bank' : 'cash')); 
                                                ?>"></i>
                                                <?php echo strtoupper($method['payment_method']); ?>
                                            </span>
                                            <span>
                                                <strong><?php echo $method['count']; ?></strong> payments |
                                                <strong><?php echo formatCurrency($method['total']); ?></strong>
                                            </span>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Payment History -->
                    <div class="col-md-7 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Payment History</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($payments->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table id="paymentsTable" class="table table-striped table-hover">
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
                                                        <td>
                                                            <span class="badge bg-info">
                                                                <?php echo strtoupper($payment['payment_method']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <small><?php echo $payment['transaction_code']; ?></small>
                                                        </td>
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
                                        <i class="bi bi-info-circle"></i>
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
        function updateBillAmount() {
            var select = document.getElementById('bill_id');
            var amount = select.options[select.selectedIndex].getAttribute('data-amount');
            var dueDate = select.options[select.selectedIndex].getAttribute('data-duedate');
            
            if (amount) {
                document.getElementById('amount').value = amount;
                document.getElementById('full_amount').innerHTML = '<?php echo CURRENCY; ?> ' + parseFloat(amount).toFixed(2);
                document.getElementById('due_date').innerHTML = new Date(dueDate).toLocaleDateString();
                document.getElementById('bill_info').style.display = 'block';
            } else {
                document.getElementById('bill_info').style.display = 'none';
            }
        }

        function validatePayment() {
            var amount = document.getElementById('amount').value;
            if (amount <= 0) {
                alert('Please enter a valid amount');
                return false;
            }
            return confirm('Confirm payment of <?php echo CURRENCY; ?> ' + amount + '?');
        }

        // Show/hide payment method details
        document.getElementById('payment_method').addEventListener('change', function() {
            var method = this.value;
            document.getElementById('mpesa_details').style.display = method === 'mpesa' ? 'block' : 'none';
            document.getElementById('card_details').style.display = method === 'card' ? 'block' : 'none';
        });

        <?php if (isset($_GET['bill_id'])): ?>
        // Auto-select bill if passed in URL
        window.onload = function() {
            var billId = '<?php echo $_GET['bill_id'] ?? ''; ?>';
            if (billId) {
                var select = document.getElementById('bill_id');
                for (var i = 0; i < select.options.length; i++) {
                    if (select.options[i].value === billId) {
                        select.selectedIndex = i;
                        updateBillAmount();
                        break;
                    }
                }
            }
        }
        <?php endif; ?>
    </script>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
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
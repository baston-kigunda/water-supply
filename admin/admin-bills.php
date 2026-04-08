<?php
// ========================================
// FILE: admin-bills.php
// PURPOSE: Manage water bills (view, generate, modify)
// ========================================

require_once '../includes/functions.php';
require_once '../config/database.php';

requireAdmin();

$message = '';
$error = '';

// Handle bill actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'generate':
                // Generate bills for all active meters
                $billing_month = $_POST['billing_month'];
                $year_month = date('Y-m', strtotime($billing_month . '-01'));
                
                // Get all active meters with users
                $meters = $conn->query("
                    SELECT sm.meter_id, sm.user_id 
                    FROM smart_meters sm 
                    WHERE sm.meter_status = 'active' AND sm.user_id IS NOT NULL
                ");
                
                $generated = 0;
                $failed = 0;
                
                while ($meter = $meters->fetch_assoc()) {
                    if (generateBill($meter['user_id'], $meter['meter_id'], $year_month)) {
                        $generated++;
                    } else {
                        $failed++;
                    }
                }
                
                $message = "Bills generated: $generated successful, $failed failed (insufficient readings)";
                break;
                
            case 'update_status':
                // Update bill status
                $bill_id = $_POST['bill_id'];
                $status = $_POST['status'];
                
                $stmt = $conn->prepare("UPDATE bills SET bill_status=? WHERE bill_id=?");
                $stmt->bind_param("si", $status, $bill_id);
                
                if ($stmt->execute()) {
                    $message = "Bill status updated successfully!";
                } else {
                    $error = "Error updating bill status: " . $conn->error;
                }
                break;
                
            case 'send_reminders':
                // Send payment reminders for overdue bills
                $reminder_count = 0;
                
                // Get overdue bills
                $result = $conn->query("
                    SELECT b.*, u.user_id, u.full_name, u.email, u.phone_number 
                    FROM bills b
                    JOIN users u ON b.user_id = u.user_id
                    WHERE b.bill_status = 'pending' AND b.due_date < CURRENT_DATE()
                ");
                
                while ($bill = $result->fetch_assoc()) {
                    // Create notification
                    createNotification(
                        $bill['user_id'],
                        "Payment Reminder",
                        "Your water bill of " . formatCurrency($bill['total_amount']) . " is overdue. Please pay immediately to avoid service interruption.",
                        'bill'
                    );
                    $reminder_count++;
                }
                
                $message = "Reminders sent to $reminder_count customers";
                break;
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$month_filter = $_GET['month'] ?? date('Y-m');

// Build query
$query = "
    SELECT b.*, u.full_name, u.username, u.email, u.phone_number,
           sm.meter_number,
           COALESCE(p.payment_id, NULL) as has_payment,
           p.payment_date, p.transaction_code
    FROM bills b
    JOIN users u ON b.user_id = u.user_id
    JOIN smart_meters sm ON b.meter_id = sm.meter_id
    LEFT JOIN payments p ON b.bill_id = p.bill_id
    WHERE 1=1
";

if ($status_filter) {
    $query .= " AND b.bill_status = '" . $conn->real_escape_string($status_filter) . "'";
}

if ($month_filter) {
    $query .= " AND DATE_FORMAT(b.billing_month, '%Y-%m') = '" . $conn->real_escape_string($month_filter) . "'";
}

$query .= " ORDER BY b.created_at DESC";

$bills = $conn->query($query);

// Get statistics
$stats = [];

// Total pending amount
$result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM bills WHERE bill_status='pending'");
$stats['pending_amount'] = $result->fetch_assoc()['total'];

// Total overdue amount
$result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM bills WHERE bill_status='overdue'");
$stats['overdue_amount'] = $result->fetch_assoc()['total'];

// Total paid this month
$result = $conn->query("
    SELECT COALESCE(SUM(total_amount), 0) as total 
    FROM bills 
    WHERE bill_status='paid' 
    AND MONTH(created_at) = MONTH(CURRENT_DATE())
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
");
$stats['paid_month'] = $result->fetch_assoc()['total'];

// Bill counts
$result = $conn->query("SELECT bill_status, COUNT(*) as count FROM bills GROUP BY bill_status");
while ($row = $result->fetch_assoc()) {
    $stats[$row['bill_status'] . '_count'] = $row['count'];
}

$page_title = "Manage Bills";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bills - <?php echo SITE_NAME; ?></title>
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
                            <a class="nav-link" href="admin-dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin-users.php">
                                <i class="bi bi-people"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin-meters.php">
                                <i class="bi bi-water"></i> Smart Meters
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="admin-bills.php">
                                <i class="bi bi-receipt"></i> Bills
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin-schedules.php">
                                <i class="bi bi-calendar"></i> Water Schedules
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin-leaks.php">
                                <i class="bi bi-exclamation-triangle"></i> Leak Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin-reports.php">
                                <i class="bi bi-file-text"></i> Reports
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Bills</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-primary me-2" data-bs-toggle="modal" data-bs-target="#generateBillsModal">
                            <i class="bi bi-file-earmark-plus"></i> Generate Bills
                        </button>
                        <button type="button" class="btn btn-sm btn-warning" onclick="sendReminders()">
                            <i class="bi bi-bell"></i> Send Reminders
                        </button>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-primary text-white">
                            <div class="card-body">
                                <h6>Pending Amount</h6>
                                <h3><?php echo formatCurrency($stats['pending_amount']); ?></h3>
                                <small><?php echo $stats['pending_count'] ?? 0; ?> bills</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-danger text-white">
                            <div class="card-body">
                                <h6>Overdue Amount</h6>
                                <h3><?php echo formatCurrency($stats['overdue_amount']); ?></h3>
                                <small><?php echo $stats['overdue_count'] ?? 0; ?> bills</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-success text-white">
                            <div class="card-body">
                                <h6>Paid This Month</h6>
                                <h3><?php echo formatCurrency($stats['paid_month']); ?></h3>
                                <small><?php echo $stats['paid_count'] ?? 0; ?> bills</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-info text-white">
                            <div class="card-body">
                                <h6>Collection Rate</h6>
                                <?php
                                $total_billed = $stats['pending_amount'] + $stats['overdue_amount'] + $stats['paid_month'];
                                $collection_rate = $total_billed > 0 ? round(($stats['paid_month'] / $total_billed) * 100, 2) : 0;
                                ?>
                                <h3><?php echo $collection_rate; ?>%</h3>
                                <small>This month</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card dashboard-card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="status" class="form-label">Filter by Status</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="overdue" <?php echo $status_filter == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                    <option value="disputed" <?php echo $status_filter == 'disputed' ? 'selected' : ''; ?>>Disputed</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="month" class="form-label">Filter by Month</label>
                                <input type="month" class="form-control" id="month" name="month" value="<?php echo $month_filter; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block">Apply Filters</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Bills Table -->
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="billsTable" class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Bill ID</th>
                                        <th>Customer</th>
                                        <th>Meter</th>
                                        <th>Billing Month</th>
                                        <th>Consumption</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Due Date</th>
                                        <th>Payment Info</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($bill = $bills->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?php echo $bill['bill_id']; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($bill['full_name']); ?><br>
                                                <small class="text-muted"><?php echo $bill['username']; ?></small>
                                            </td>
                                            <td><?php echo $bill['meter_number']; ?></td>
                                            <td><?php echo date('M Y', strtotime($bill['billing_month'])); ?></td>
                                            <td><?php echo number_format($bill['units_consumed'], 2); ?> m³</td>
                                            <td><strong><?php echo formatCurrency($bill['total_amount']); ?></strong></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $bill['bill_status'] == 'paid' ? 'success' : 
                                                        ($bill['bill_status'] == 'pending' ? 'warning' : 
                                                        ($bill['bill_status'] == 'overdue' ? 'danger' : 'secondary')); 
                                                ?>">
                                                    <?php echo ucfirst($bill['bill_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo date('M d, Y', strtotime($bill['due_date'])); ?>
                                                <?php if (strtotime($bill['due_date']) < time() && $bill['bill_status'] != 'paid'): ?>
                                                    <br><small class="text-danger">Overdue</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($bill['has_payment']): ?>
                                                    <span class="text-success">
                                                        <i class="bi bi-check-circle"></i> Paid<br>
                                                        <small><?php echo date('M d', strtotime($bill['payment_date'])); ?></small>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Not paid</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="viewBill(<?php echo $bill['bill_id']; ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <?php if ($bill['bill_status'] != 'paid'): ?>
                                                    <button class="btn btn-sm btn-warning" onclick="updateStatus(<?php echo $bill['bill_id']; ?>, '<?php echo $bill['bill_status']; ?>')">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Generate Bills Modal -->
    <div class="modal fade" id="generateBillsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Generate Monthly Bills</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="generate">
                        
                        <div class="mb-3">
                            <label for="billing_month" class="form-label">Billing Month</label>
                            <input type="month" class="form-control" id="billing_month" name="billing_month" value="<?php echo date('Y-m'); ?>" required>
                            <small class="text-muted">Select the month to generate bills for</small>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            Bills will be generated for all active meters with sufficient readings.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Generate Bills</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Bill Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="bill_id" id="status_bill_id">
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">New Status</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="overdue">Overdue</option>
                                <option value="disputed">Disputed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#billsTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25
            });
        });

        function updateStatus(bill_id, current_status) {
            document.getElementById('status_bill_id').value = bill_id;
            document.getElementById('status').value = current_status;
            new bootstrap.Modal(document.getElementById('statusModal')).show();
        }

        function viewBill(bill_id) {
            window.location.href = 'admin-view-bill.php?id=' + bill_id;
        }

        function sendReminders() {
            if (confirm('Send payment reminders to all customers with overdue bills?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'action';
                input.value = 'send_reminders';
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
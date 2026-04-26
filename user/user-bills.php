<?php
// ========================================
// FILE: user-bills.php
// PURPOSE: View and manage water bills
// ========================================

require_once '../includes/functions.php';
require_once '../config/database.php';

requireLogin();

if (isAdmin()) {
    header('Location: ../admin/admin-dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$assigned_meter_result = $conn->query("
    SELECT COUNT(*) as count
    FROM smart_meters
    WHERE user_id = $user_id
");
$has_assigned_meters = ((int) $assigned_meter_result->fetch_assoc()['count']) > 0;

// Handle bill payment redirect
if (isset($_GET['pay'])) {
    $bill_id = $_GET['pay'];
    header("Location: user-payments.php?bill_id=$bill_id");
    exit();
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$year_filter = $_GET['year'] ?? date('Y');

// Get all years for filter
$years = $conn->query("
    SELECT DISTINCT YEAR(billing_month) as year 
    FROM bills 
    WHERE user_id = $user_id 
    ORDER BY year DESC
");

// Build query for bills
$query = "
    SELECT b.*, 
           sm.meter_number,
           p.payment_id,
           p.payment_date,
           p.transaction_code,
           p.payment_method
    FROM bills b
    JOIN smart_meters sm ON b.meter_id = sm.meter_id
    LEFT JOIN payments p ON b.bill_id = p.bill_id
    WHERE b.user_id = $user_id
";

if ($status_filter) {
    $query .= " AND b.bill_status = '$status_filter'";
}

if ($year_filter) {
    $query .= " AND YEAR(b.billing_month) = $year_filter";
}

$query .= " ORDER BY b.billing_month DESC";

$bills = $conn->query($query);

// Calculate statistics
$stats = [];

// Total billed
$result = $conn->query("
    SELECT COALESCE(SUM(total_amount), 0) as total 
    FROM bills 
    WHERE user_id = $user_id
");
$stats['total_billed'] = $result->fetch_assoc()['total'];

// Total paid
$result = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM payments p
    JOIN bills b ON p.bill_id = b.bill_id
    WHERE b.user_id = $user_id AND p.payment_status = 'completed'
");
$stats['total_paid'] = $result->fetch_assoc()['total'];

// Outstanding balance
$stats['outstanding'] = $stats['total_billed'] - $stats['total_paid'];

// Pending bills count
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM bills 
    WHERE user_id = $user_id AND bill_status IN ('pending', 'overdue')
");
$stats['pending_count'] = $result->fetch_assoc()['count'];

$page_title = "My Bills";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bills - <?php echo SITE_NAME; ?></title>
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
                            <a class="nav-link active" href="user-bills.php">
                                <i class="bi bi-receipt"></i> My Bills
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="user-payments.php">
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
                    <h1 class="h2">My Water Bills</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="user-payments.php" class="btn btn-sm btn-success">
                            <i class="bi bi-cash"></i> Make Payment
                        </a>
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="bi bi-arrow-repeat"></i>
                    Bills on this page refresh automatically after a meter is assigned and as new simulated water usage is recorded.
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-primary text-white">
                            <div class="card-body">
                                <h6>Total Billed</h6>
                                <h3><?php echo formatCurrency($stats['total_billed']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-success text-white">
                            <div class="card-body">
                                <h6>Total Paid</h6>
                                <h3><?php echo formatCurrency($stats['total_paid']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-warning text-white">
                            <div class="card-body">
                                <h6>Outstanding</h6>
                                <h3><?php echo formatCurrency($stats['outstanding']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-info text-white">
                            <div class="card-body">
                                <h6>Pending Bills</h6>
                                <h3><?php echo $stats['pending_count']; ?></h3>
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
                                    <option value="">All Bills</option>
                                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="overdue" <?php echo $status_filter == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                    <option value="disputed" <?php echo $status_filter == 'disputed' ? 'selected' : ''; ?>>Disputed</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="year" class="form-label">Filter by Year</label>
                                <select class="form-control" id="year" name="year">
                                    <option value="">All Years</option>
                                    <?php while ($year = $years->fetch_assoc()): ?>
                                        <option value="<?php echo $year['year']; ?>" <?php echo $year['year'] == $year_filter ? 'selected' : ''; ?>>
                                            <?php echo $year['year']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
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
                        <?php if ($bills->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table id="billsTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Bill #</th>
                                            <th>Meter</th>
                                            <th>Month</th>
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
                                                <td><strong>#<?php echo $bill['bill_id']; ?></strong></td>
                                                <td><?php echo $bill['meter_number']; ?></td>
                                                <td><?php echo date('F Y', strtotime($bill['billing_month'])); ?></td>
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
                                                    <?php if ($bill['payment_id']): ?>
                                                        <span class="text-success">
                                                            <i class="bi bi-check-circle"></i> 
                                                            <?php echo date('M d, Y', strtotime($bill['payment_date'])); ?><br>
                                                            <small><?php echo $bill['transaction_code']; ?></small>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not paid</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="viewBill(<?php echo $bill['bill_id']; ?>)">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                    
                                                    <?php if ($bill['bill_status'] != 'paid'): ?>
                                                        <a href="?pay=<?php echo $bill['bill_id']; ?>" class="btn btn-sm btn-success">
                                                            <i class="bi bi-cash"></i> Pay Now
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <button class="btn btn-sm btn-secondary" onclick="downloadBill(<?php echo $bill['bill_id']; ?>)">
                                                        <i class="bi bi-download"></i> PDF
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                No bills found for the selected filters.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bill Details Modal -->
    <div class="modal fade" id="billModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Bill Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="billDetails">
                    <!-- Bill details will be loaded here via AJAX -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printBill()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
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
                order: [[2, 'desc']],
                pageLength: 25
            });
        });

        (function () {
            const refreshMs = <?php echo $has_assigned_meters ? ((int) IOT_SIMULATION_INTERVAL_SECONDS * 1000) + 5000 : 15000; ?>;

            window.setInterval(function () {
                if (document.visibilityState === 'visible') {
                    window.location.reload();
                }
            }, refreshMs);
        })();

        function viewBill(bill_id) {
            $('#billModal').modal('show');
            $('#billDetails').html(`
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `);
            
            // Load bill details via AJAX
            $.ajax({
                url: 'ajax/get-bill-details.php',
                method: 'POST',
                data: { bill_id: bill_id },
                success: function(response) {
                    $('#billDetails').html(response);
                },
                error: function() {
                    $('#billDetails').html('<div class="alert alert-danger">Error loading bill details</div>');
                }
            });
        }

        function downloadBill(bill_id) {
            window.location.href = 'download-bill.php?id=' + bill_id;
        }

        function printBill() {
            var printContents = document.getElementById('billDetails').innerHTML;
            var originalContents = document.body.innerHTML;
            
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
            location.reload();
        }
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>

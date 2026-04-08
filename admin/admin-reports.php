<?php
// ========================================
// FILE: admin-reports.php
// PURPOSE: Generate various system reports and analytics
// ========================================

require_once '../includes/functions.php';
require_once '../config/database.php';

requireAdmin();

$message = '';
$error = '';

// Get report parameters
$report_type = $_GET['type'] ?? 'summary';
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$format = $_GET['format'] ?? 'html';

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate_report'])) {
        $report_type = $_POST['report_type'];
        $date_from = $_POST['date_from'];
        $date_to = $_POST['date_to'];
        $format = $_POST['format'];
        
        // Redirect with parameters
        header("Location: admin-reports.php?type=$report_type&date_from=$date_from&date_to=$date_to&format=$format");
        exit();
    }
}

// Get report data based on type
$report_data = [];
$report_title = '';
$report_headers = [];

switch ($report_type) {
    case 'consumption':
        $report_title = 'Water Consumption Report';
        $report_headers = ['Meter Number', 'Customer', 'Location', 'Previous Reading', 'Current Reading', 'Consumption (m³)', 'Period'];
        
        $query = "
            SELECT 
                sm.meter_number,
                u.full_name as customer_name,
                sm.location,
                mr.reading_value as current_reading,
                LAG(mr.reading_value) OVER (PARTITION BY mr.meter_id ORDER BY mr.reading_time) as previous_reading,
                mr.reading_time,
                DATE(mr.reading_time) as reading_date
            FROM meter_readings mr
            JOIN smart_meters sm ON mr.meter_id = sm.meter_id
            LEFT JOIN users u ON sm.user_id = u.user_id
            WHERE DATE(mr.reading_time) BETWEEN ? AND ?
            ORDER BY mr.reading_time DESC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $consumption = $row['previous_reading'] ? ($row['current_reading'] - $row['previous_reading']) : 0;
            $report_data[] = [
                $row['meter_number'],
                $row['customer_name'] ?? 'Unassigned',
                $row['location'],
                number_format($row['previous_reading'] ?? 0, 2),
                number_format($row['current_reading'], 2),
                number_format($consumption, 2),
                date('Y-m-d H:i', strtotime($row['reading_time']))
            ];
        }
        break;
        
    case 'billing':
        $report_title = 'Billing Report';
        $report_headers = ['Bill #', 'Customer', 'Meter', 'Billing Month', 'Consumption', 'Amount', 'Status', 'Due Date', 'Payment Date'];
        
        $query = "
            SELECT 
                b.bill_id,
                u.full_name as customer_name,
                sm.meter_number,
                DATE_FORMAT(b.billing_month, '%M %Y') as billing_month,
                b.units_consumed,
                b.total_amount,
                b.bill_status,
                b.due_date,
                p.payment_date,
                p.payment_status
            FROM bills b
            JOIN users u ON b.user_id = u.user_id
            JOIN smart_meters sm ON b.meter_id = sm.meter_id
            LEFT JOIN payments p ON b.bill_id = p.bill_id
            WHERE b.created_at BETWEEN ? AND ?
            ORDER BY b.created_at DESC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $date_from . ' 00:00:00', $date_to . ' 23:59:59');
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $report_data[] = [
                '#' . $row['bill_id'],
                $row['customer_name'],
                $row['meter_number'],
                $row['billing_month'],
                number_format($row['units_consumed'], 2) . ' m³',
                formatCurrency($row['total_amount']),
                ucfirst($row['bill_status']),
                date('Y-m-d', strtotime($row['due_date'])),
                $row['payment_date'] ? date('Y-m-d', strtotime($row['payment_date'])) : 'Not paid'
            ];
        }
        break;
        
    case 'revenue':
        $report_title = 'Revenue Report';
        $report_headers = ['Date', 'Payment ID', 'Customer', 'Bill #', 'Amount', 'Method', 'Transaction Code', 'Status'];
        
        $query = "
            SELECT 
                DATE(p.payment_date) as payment_date,
                p.payment_id,
                u.full_name as customer_name,
                p.bill_id,
                p.amount,
                p.payment_method,
                p.transaction_code,
                p.payment_status
            FROM payments p
            JOIN users u ON p.user_id = u.user_id
            WHERE DATE(p.payment_date) BETWEEN ? AND ?
            ORDER BY p.payment_date DESC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $total_revenue = 0;
        while ($row = $result->fetch_assoc()) {
            $total_revenue += $row['amount'];
            $report_data[] = [
                $row['payment_date'],
                '#' . $row['payment_id'],
                $row['customer_name'],
                '#' . $row['bill_id'],
                formatCurrency($row['amount']),
                strtoupper($row['payment_method']),
                $row['transaction_code'] ?? 'N/A',
                ucfirst($row['payment_status'])
            ];
        }
        break;
        
    case 'leaks':
        $report_title = 'Leak Reports Summary';
        $report_headers = ['Report ID', 'Customer', 'Location', 'Priority', 'Status', 'Reported Date', 'Resolved Date', 'Response Time'];
        
        $query = "
            SELECT 
                lr.report_id,
                u.full_name as customer_name,
                lr.location_description,
                lr.priority,
                lr.report_status,
                lr.reported_at,
                lr.resolved_at,
                lr.assigned_to,
                tech.full_name as technician_name
            FROM leak_reports lr
            JOIN users u ON lr.user_id = u.user_id
            LEFT JOIN users tech ON lr.assigned_to = tech.user_id
            WHERE DATE(lr.reported_at) BETWEEN ? AND ?
            ORDER BY 
                CASE lr.priority 
                    WHEN 'emergency' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'medium' THEN 3
                    WHEN 'low' THEN 4
                END,
                lr.reported_at DESC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $response_time = '';
            if ($row['resolved_at']) {
                $hours = round((strtotime($row['resolved_at']) - strtotime($row['reported_at'])) / 3600, 1);
                $response_time = $hours . ' hours';
            } elseif ($row['assigned_to']) {
                $response_time = 'In progress';
            } else {
                $response_time = 'Pending';
            }
            
            $report_data[] = [
                '#' . $row['report_id'],
                $row['customer_name'],
                substr($row['location_description'], 0, 30) . '...',
                ucfirst($row['priority']),
                ucfirst(str_replace('_', ' ', $row['report_status'])),
                date('Y-m-d H:i', strtotime($row['reported_at'])),
                $row['resolved_at'] ? date('Y-m-d H:i', strtotime($row['resolved_at'])) : 'Not resolved',
                $response_time
            ];
        }
        break;
        
    case 'customers':
        $report_title = 'Customer Report';
        $report_headers = ['Customer ID', 'Name', 'Username', 'Email', 'Phone', 'Meters', 'Total Consumption', 'Outstanding Balance', 'Status'];
        
        $query = "
            SELECT 
                u.user_id,
                u.full_name,
                u.username,
                u.email,
                u.phone_number,
                u.account_status,
                COUNT(DISTINCT sm.meter_id) as meter_count,
                COALESCE(SUM(mr.reading_value), 0) as total_consumption,
                COALESCE((
                    SELECT SUM(total_amount) 
                    FROM bills 
                    WHERE user_id = u.user_id AND bill_status IN ('pending', 'overdue')
                ), 0) as outstanding_balance
            FROM users u
            LEFT JOIN smart_meters sm ON u.user_id = sm.user_id
            LEFT JOIN meter_readings mr ON sm.meter_id = mr.meter_id
            WHERE u.user_role = 'consumer'
            GROUP BY u.user_id
            ORDER BY u.full_name
        ";
        
        $result = $conn->query($query);
        
        while ($row = $result->fetch_assoc()) {
            $report_data[] = [
                '#' . $row['user_id'],
                $row['full_name'],
                $row['username'],
                $row['email'],
                $row['phone_number'] ?? 'N/A',
                $row['meter_count'],
                number_format($row['total_consumption'], 2) . ' m³',
                formatCurrency($row['outstanding_balance']),
                ucfirst($row['account_status'])
            ];
        }
        break;
        
    case 'summary':
    default:
        $report_title = 'Executive Summary Report';
        
        // Get summary statistics
        $stats = [];
        
        // Total customers
        $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_role = 'consumer'");
        $stats['total_customers'] = $result->fetch_assoc()['count'];
        
        // Active meters
        $result = $conn->query("SELECT COUNT(*) as count FROM smart_meters WHERE meter_status = 'active'");
        $stats['active_meters'] = $result->fetch_assoc()['count'];
        
        // Total consumption in period
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(reading_value), 0) as total 
            FROM meter_readings 
            WHERE DATE(reading_time) BETWEEN ? AND ?
        ");
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $stats['total_consumption'] = $stmt->get_result()->fetch_assoc()['total'];
        
        // Total revenue in period
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM payments 
            WHERE DATE(payment_date) BETWEEN ? AND ? AND payment_status = 'completed'
        ");
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $stats['total_revenue'] = $stmt->get_result()->fetch_assoc()['total'];
        
        // Pending bills amount
        $result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM bills WHERE bill_status = 'pending'");
        $stats['pending_amount'] = $result->fetch_assoc()['total'];
        
        // Overdue bills amount
        $result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM bills WHERE bill_status = 'overdue'");
        $stats['overdue_amount'] = $result->fetch_assoc()['total'];
        
        // Leak reports in period
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM leak_reports 
            WHERE DATE(reported_at) BETWEEN ? AND ?
        ");
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $stats['leak_reports'] = $stmt->get_result()->fetch_assoc()['count'];
        
        // Resolved leaks
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM leak_reports 
            WHERE DATE(reported_at) BETWEEN ? AND ? 
            AND report_status = 'resolved'
        ");
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $stats['resolved_leaks'] = $stmt->get_result()->fetch_assoc()['count'];
        
        // Payment methods breakdown
        $payment_methods = [];
        $result = $conn->query("
            SELECT payment_method, COUNT(*) as count, SUM(amount) as total
            FROM payments
            WHERE payment_status = 'completed'
            GROUP BY payment_method
        ");
        while ($row = $result->fetch_assoc()) {
            $payment_methods[] = $row;
        }
        break;
}

// Handle CSV export
if ($format == 'csv' && !empty($report_data)) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, $report_headers);
    
    // Add data
    foreach ($report_data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

$page_title = "Generate Reports";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Reports - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                            <a class="nav-link" href="admin-bills.php">
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
                            <a class="nav-link active" href="admin-reports.php">
                                <i class="bi bi-file-text"></i> Reports
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Generate Reports</h1>
                </div>

                <!-- Report Filter Form -->
                <div class="card dashboard-card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-funnel"></i> Report Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-3">
                                <label for="report_type" class="form-label">Report Type</label>
                                <select class="form-control" id="report_type" name="report_type" required>
                                    <option value="summary" <?php echo $report_type == 'summary' ? 'selected' : ''; ?>>Executive Summary</option>
                                    <option value="consumption" <?php echo $report_type == 'consumption' ? 'selected' : ''; ?>>Water Consumption</option>
                                    <option value="billing" <?php echo $report_type == 'billing' ? 'selected' : ''; ?>>Billing Report</option>
                                    <option value="revenue" <?php echo $report_type == 'revenue' ? 'selected' : ''; ?>>Revenue Report</option>
                                    <option value="leaks" <?php echo $report_type == 'leaks' ? 'selected' : ''; ?>>Leak Reports</option>
                                    <option value="customers" <?php echo $report_type == 'customers' ? 'selected' : ''; ?>>Customer Report</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>" required>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>" required>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="format" class="form-label">Format</label>
                                <select class="form-control" id="format" name="format">
                                    <option value="html">HTML (Screen)</option>
                                    <option value="csv">CSV (Download)</option>
                                </select>
                            </div>
                            
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" name="generate_report" class="btn btn-primary d-block">
                                    <i class="bi bi-file-earmark-text"></i> Generate
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Report Results -->
                <?php if ($report_type == 'summary'): ?>
                    <!-- Executive Summary Dashboard -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card dashboard-card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> <?php echo $report_title; ?> (<?php echo $date_from; ?> to <?php echo $date_to; ?>)</h5>
                                </div>
                                <div class="card-body">
                                    <!-- Summary Cards -->
                                    <div class="row mb-4">
                                        <div class="col-md-3">
                                            <div class="card bg-info text-white">
                                                <div class="card-body">
                                                    <h6>Total Customers</h6>
                                                    <h3><?php echo $stats['total_customers']; ?></h3>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="card bg-success text-white">
                                                <div class="card-body">
                                                    <h6>Active Meters</h6>
                                                    <h3><?php echo $stats['active_meters']; ?></h3>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="card bg-primary text-white">
                                                <div class="card-body">
                                                    <h6>Total Consumption</h6>
                                                    <h3><?php echo number_format($stats['total_consumption'], 2); ?> m³</h3>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="card bg-warning text-white">
                                                <div class="card-body">
                                                    <h6>Total Revenue</h6>
                                                    <h3><?php echo formatCurrency($stats['total_revenue']); ?></h3>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Financial Summary -->
                                    <div class="row mb-4">
                                        <div class="col-md-4">
                                            <div class="card border-danger">
                                                <div class="card-header bg-danger text-white">
                                                    <h6>Outstanding Bills</h6>
                                                </div>
                                                <div class="card-body">
                                                    <h4 class="text-danger"><?php echo formatCurrency($stats['pending_amount']); ?></h4>
                                                    <p>Pending Amount</p>
                                                    <h4 class="text-danger"><?php echo formatCurrency($stats['overdue_amount']); ?></h4>
                                                    <p>Overdue Amount</p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <div class="card border-info">
                                                <div class="card-header bg-info text-white">
                                                    <h6>Leak Reports</h6>
                                                </div>
                                                <div class="card-body">
                                                    <h4><?php echo $stats['leak_reports']; ?></h4>
                                                    <p>Total Reports</p>
                                                    <h4><?php echo $stats['resolved_leaks']; ?></h4>
                                                    <p>Resolved</p>
                                                    <div class="progress">
                                                        <?php $resolution_rate = $stats['leak_reports'] > 0 ? round(($stats['resolved_leaks'] / $stats['leak_reports']) * 100) : 0; ?>
                                                        <div class="progress-bar bg-success" style="width: <?php echo $resolution_rate; ?>%">
                                                            <?php echo $resolution_rate; ?>% Resolved
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <div class="card border-success">
                                                <div class="card-header bg-success text-white">
                                                    <h6>Payment Methods</h6>
                                                </div>
                                                <div class="card-body">
                                                    <canvas id="paymentChart" style="height: 200px;"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Quick Stats Table -->
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Metric</th>
                                                    <th>Value</th>
                                                    <th>Period</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>Average Daily Consumption</td>
                                                    <td><?php 
                                                        $days = (strtotime($date_to) - strtotime($date_from)) / 86400 + 1;
                                                        echo number_format($stats['total_consumption'] / $days, 2) . ' m³';
                                                    ?></td>
                                                    <td><?php echo $date_from; ?> to <?php echo $date_to; ?></td>
                                                </tr>
                                                <tr>
                                                    <td>Average Daily Revenue</td>
                                                    <td><?php echo formatCurrency($stats['total_revenue'] / $days); ?></td>
                                                    <td><?php echo $date_from; ?> to <?php echo $date_to; ?></td>
                                                </tr>
                                                <tr>
                                                    <td>Collection Efficiency</td>
                                                    <td><?php 
                                                        $total_billed = $stats['total_revenue'] + $stats['pending_amount'] + $stats['overdue_amount'];
                                                        $efficiency = $total_billed > 0 ? round(($stats['total_revenue'] / $total_billed) * 100, 2) : 0;
                                                        echo $efficiency . '%';
                                                    ?></td>
                                                    <td>Current</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                        // Payment Methods Chart
                        const ctx = document.getElementById('paymentChart').getContext('2d');
                        new Chart(ctx, {
                            type: 'doughnut',
                            data: {
                                labels: <?php 
                                    $labels = [];
                                    $values = [];
                                    foreach ($payment_methods as $pm) {
                                        $labels[] = strtoupper($pm['payment_method']);
                                        $values[] = $pm['total'];
                                    }
                                    echo json_encode($labels);
                                ?>,
                                datasets: [{
                                    data: <?php echo json_encode($values); ?>,
                                    backgroundColor: [
                                        '#28a745',
                                        '#007bff',
                                        '#ffc107',
                                        '#dc3545'
                                    ]
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'bottom'
                                    }
                                }
                            }
                        });
                    </script>
                    
                <?php elseif (!empty($report_data)): ?>
                    <!-- Data Table Report -->
                    <div class="card dashboard-card">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-table"></i> <?php echo $report_title; ?></h5>
                            <a href="?type=<?php echo $report_type; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&format=csv" class="btn btn-sm btn-light">
                                <i class="bi bi-download"></i> Download CSV
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Period:</strong> <?php echo $date_from; ?> to <?php echo $date_to; ?>
                                <span class="ms-3"><strong>Total Records:</strong> <?php echo count($report_data); ?></span>
                            </div>
                            
                            <div class="table-responsive">
                                <table id="reportTable" class="table table-striped table-bordered table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <?php foreach ($report_headers as $header): ?>
                                                <th><?php echo $header; ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $row): ?>
                                            <tr>
                                                <?php foreach ($row as $cell): ?>
                                                    <td><?php echo $cell; ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Report Summary -->
                            <?php if ($report_type == 'revenue'): ?>
                                <div class="alert alert-success mt-3">
                                    <strong>Total Revenue:</strong> <?php echo formatCurrency($total_revenue); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No data found for the selected period. Please adjust your filters.
                    </div>
                <?php endif; ?>
                
                <!-- Quick Report Links -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card dashboard-card">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0"><i class="bi bi-lightning"></i> Quick Reports</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <a href="?type=summary&date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-primary w-100 mb-2">
                                            <i class="bi bi-calendar"></i> This Month Summary
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="?type=revenue&date_from=<?php echo date('Y-m-01', strtotime('-1 month')); ?>&date_to=<?php echo date('Y-m-t', strtotime('-1 month')); ?>" class="btn btn-outline-success w-100 mb-2">
                                            <i class="bi bi-cash"></i> Last Month Revenue
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="?type=leaks&date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-warning w-100 mb-2">
                                            <i class="bi bi-exclamation-triangle"></i> Open Leaks
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="?type=customers&date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-info w-100 mb-2">
                                            <i class="bi bi-people"></i> All Customers
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            <?php if (!empty($report_data) && $report_type != 'summary'): ?>
            $('#reportTable').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                dom: 'Bfrtip',
                buttons: [
                    'copy', 'excel', 'pdf', 'print'
                ]
            });
            <?php endif; ?>
        });
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
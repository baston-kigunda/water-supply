<?php
// ========================================
// FILE: admin-dashboard.php
// PURPOSE: Admin main dashboard with system overview
// ========================================

require_once '../includes/functions.php';
require_once '../config/database.php';

// Require admin login
requireAdmin();

// Get statistics for dashboard
$stats = [];
// Total users
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE user_role = 'consumer'");
$stats['total_users'] = $result->fetch_assoc()['total'];

// Active meters
$result = $conn->query("SELECT COUNT(*) as total FROM smart_meters WHERE meter_status = 'active'");
$stats['active_meters'] = $result->fetch_assoc()['total'];

// Pending bills
$result = $conn->query("SELECT COUNT(*) as total FROM bills WHERE bill_status = 'pending'");
$stats['pending_bills'] = $result->fetch_assoc()['total'];

// Pending leak reports
$result = $conn->query("SELECT COUNT(*) as total FROM leak_reports WHERE report_status = 'pending'");
$stats['pending_leaks'] = $result->fetch_assoc()['total'];

// Total revenue this month
$result = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM payments 
    WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) 
    AND YEAR(payment_date) = YEAR(CURRENT_DATE())
");
$stats['monthly_revenue'] = $result->fetch_assoc()['total'];

// Recent activities
$recent_activities = [];

// Recent users
$result = $conn->query("
    SELECT 'New User' as type, full_name as description, created_at as time 
    FROM users 
    WHERE user_role = 'consumer' 
    ORDER BY created_at DESC LIMIT 5
");
while ($row = $result->fetch_assoc()) {
    $recent_activities[] = $row;
}

// Recent payments
$result = $conn->query("
    SELECT 'Payment' as type, CONCAT('Payment of ', amount, ' received') as description, payment_date as time 
    FROM payments 
    WHERE payment_status = 'completed'
    ORDER BY payment_date DESC LIMIT 5
");
while ($row = $result->fetch_assoc()) {
    $recent_activities[] = $row;
}

// Recent leak reports
$result = $conn->query("
    SELECT 'Leak Report' as type, CONCAT('Leak reported in ', location_description) as description, reported_at as time 
    FROM leak_reports 
    ORDER BY reported_at DESC LIMIT 5
");
while ($row = $result->fetch_assoc()) {
    $recent_activities[] = $row;
}

// Sort activities by time (most recent first)
usort($recent_activities,function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});
$recent_activities = array_slice($recent_activities, 0, 10);

// Monthly consumption chart data
$monthly_data = [];
for ($i = 6; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_name = date('M Y', strtotime("-$i months"));
    
    $result = $conn->query("
        SELECT COALESCE(SUM(units_consumed), 0) as total 
        FROM bills 
        WHERE DATE_FORMAT(billing_month, '%Y-%m') = '$month'
    ");
    $monthly_data[$month_name] = $result->fetch_assoc()['total'];
}

// Page title
$page_title = "Admin Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
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
                            <a class="nav-link active" href="admin-dashboard.php">
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
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.location.reload()">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="card dashboard-card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Users</h6>
                                        <h2 class="mb-0"><?php echo $stats['total_users']; ?></h2>
                                    </div>
                                    <i class="bi bi-people fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card dashboard-card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Active Meters</h6>
                                        <h2 class="mb-0"><?php echo $stats['active_meters']; ?></h2>
                                    </div>
                                    <i class="bi bi-water fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card dashboard-card text-white bg-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Pending Bills</h6>
                                        <h2 class="mb-0"><?php echo $stats['pending_bills']; ?></h2>
                                    </div>
                                    <i class="bi bi-receipt fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card dashboard-card text-white bg-danger">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Pending Leaks</h6>
                                        <h2 class="mb-0"><?php echo $stats['pending_leaks']; ?></h2>
                                    </div>
                                    <i class="bi bi-exclamation-triangle fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Revenue Card -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card dashboard-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Monthly Revenue</h5>
                                        <h3 class="text-success"><?php echo formatCurrency($stats['monthly_revenue']); ?></h3>
                                        <small>Current month</small>
                                    </div>
                                    <i class="bi bi-cash-stack fs-1 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts and Activities -->
                <div class="row">
                    <!-- Consumption Chart -->
                    <div class="col-md-8 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5>Water Consumption Trend (Last 7 Months)</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="consumptionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activities -->
                    <div class="col-md-4 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5>Recent Activities</h5>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <?php if (empty($recent_activities)): ?>
                                    <p class="text-muted">No recent activities</p>
                                <?php else: ?>
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <div class="notification-item p-2 mb-2 border-bottom">
                                            <small class="text-muted"><?php echo date('M d, H:i', strtotime($activity['time'])); ?></small>
                                            <div class="d-flex align-items-center">
                                                <?php if ($activity['type'] == 'New User'): ?>
                                                    <i class="bi bi-person-plus-fill text-success me-2"></i>
                                                <?php elseif ($activity['type'] == 'Payment'): ?>
                                                    <i class="bi bi-cash-stack text-primary me-2"></i>
                                                <?php elseif ($activity['type'] == 'Leak Report'): ?>
                                                    <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>
                                                <?php endif; ?>
                                                <span><?php echo $activity['description']; ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5>Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <a href="admin-users.php?action=add" class="btn btn-outline-primary w-100 mb-2">
                                            <i class="bi bi-person-plus"></i> Add User
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="admin-meters.php?action=add" class="btn btn-outline-success w-100 mb-2">
                                            <i class="bi bi-water"></i> Add Meter
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="admin-schedules.php?action=add" class="btn btn-outline-warning w-100 mb-2">
                                            <i class="bi bi-calendar-plus"></i> New Schedule
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="admin-reports.php" class="btn btn-outline-info w-100 mb-2">
                                            <i class="bi bi-file-text"></i> Generate Report
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

    <script>
        // Consumption Chart
        const ctx = document.getElementById('consumptionChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_keys($monthly_data)); ?>,
                datasets: [{
                    label: 'Water Consumption (m³)',
                    data: <?php echo json_encode(array_values($monthly_data)); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Cubic Meters (m³)'
                        }
                    }
                }
            }
        });
    </script>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
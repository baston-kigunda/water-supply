<?php
// ========================================
// FILE: user-dashboard.php
// PURPOSE: User dashboard with water consumption analytics
// ========================================

require_once '../includes/functions.php';
require_once '../config/database.php';

requireLogin();
if (isAdmin()) {
    header('Location: ../admin/admin-dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user's meters with prepared statement
$stmt = $conn->prepare("
    SELECT meter_id, meter_number, location
    FROM smart_meters
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$meters = $stmt->get_result();
$has_assigned_meters = $meters->num_rows > 0;

// Validate and sanitize selected meter
$selected_meter = 0;
if (isset($_GET['meter_id']) && is_numeric($_GET['meter_id'])) {
    $selected_meter = (int) $_GET['meter_id'];

    $verify_stmt = $conn->prepare("SELECT meter_id FROM smart_meters WHERE meter_id = ? AND user_id = ?");
    $verify_stmt->bind_param("ii", $selected_meter, $user_id);
    $verify_stmt->execute();
    if ($verify_stmt->get_result()->num_rows === 0) {
        $selected_meter = 0;
    }
}

// If no valid meter selected, use first available
if ($selected_meter === 0 && $meters->num_rows > 0) {
    $first_meter = $meters->fetch_assoc();
    $selected_meter = $first_meter['meter_id'];
    $meters->data_seek(0);
}

$selected_meter_details = null;
if ($selected_meter) {
    $selected_meter_stmt = $conn->prepare("
        SELECT meter_number, location
        FROM smart_meters
        WHERE meter_id = ? AND user_id = ?
        LIMIT 1
    ");
    $selected_meter_stmt->bind_param("ii", $selected_meter, $user_id);
    $selected_meter_stmt->execute();
    $selected_meter_details = $selected_meter_stmt->get_result()->fetch_assoc() ?: null;
}

$selected_meter_number = $selected_meter_details['meter_number'] ?? 'Assigned Meter';
$selected_meter_location = $selected_meter_details['location'] ?? '';

$billing_stats = [
    'total_billed' => 0,
    'total_paid' => 0,
    'outstanding' => 0,
    'pending_count' => 0
];

$result = $conn->query("
    SELECT COALESCE(SUM(total_amount), 0) as total
    FROM bills
    WHERE user_id = $user_id
");
$billing_stats['total_billed'] = (float) $result->fetch_assoc()['total'];

$result = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total
    FROM payments
    WHERE user_id = $user_id AND payment_status = 'completed'
");
$billing_stats['total_paid'] = (float) $result->fetch_assoc()['total'];
$billing_stats['outstanding'] = $billing_stats['total_billed'] - $billing_stats['total_paid'];

$result = $conn->query("
    SELECT COUNT(*) as count
    FROM bills
    WHERE user_id = $user_id AND bill_status IN ('pending', 'overdue')
");
$billing_stats['pending_count'] = (int) $result->fetch_assoc()['count'];

$result = $conn->query("
    SELECT bill_id, total_amount, bill_status, due_date
    FROM bills
    WHERE user_id = $user_id
    ORDER BY bill_id DESC
    LIMIT 1
");
$latest_bill = $result->fetch_assoc();

// Validate and sanitize period
$valid_periods = ['week', 'month', 'quarter', 'year'];
$period = isset($_GET['period']) && in_array($_GET['period'], $valid_periods, true)
    ? $_GET['period']
    : 'month';

// Get date range based on period
$end_date = date('Y-m-d');
switch ($period) {
    case 'week':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $group_by = 'DATE(reading_time)';
        $display_format = 'daily';
        break;
    case 'month':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $group_by = 'DATE(reading_time)';
        $display_format = 'daily';
        break;
    case 'quarter':
        $start_date = date('Y-m-d', strtotime('-3 months'));
        $group_by = 'DATE_FORMAT(reading_time, "%Y-%m")';
        $display_format = 'monthly';
        break;
    case 'year':
        $start_date = date('Y-m-d', strtotime('-1 year'));
        $group_by = 'DATE_FORMAT(reading_time, "%Y-%m")';
        $display_format = 'monthly';
        break;
}

// Initialize data arrays
$consumption_data = [];
$chart_labels = [];
$total_consumption = 0;
$avg_daily = 0;
$peak_day = ['date' => 'N/A', 'value' => 0];
$details_rows = [];
$hourly_data = array_fill(0, 24, 0);
$days = max(1, (strtotime($end_date) - strtotime($start_date)) / 86400);

// Get consumption data if meter selected
if ($selected_meter) {
    $start_datetime = $start_date . ' 00:00:00';
    $end_datetime = $end_date . ' 23:59:59';
    $details_rows = getGroupedConsumptionData($selected_meter, $start_datetime, $end_datetime, $group_by);

    foreach ($details_rows as $row) {
        $consumption = $row['consumption'] ?? 0;
        $consumption_data[] = $consumption;
        $total_consumption += $consumption;

        if ($display_format === 'daily') {
            $chart_labels[] = date('M d', strtotime($row['period']));
            $formatted_date = date('M d, Y', strtotime($row['period']));
        } else {
            $chart_labels[] = date('M Y', strtotime($row['period'] . '-01'));
            $formatted_date = date('F Y', strtotime($row['period'] . '-01'));
        }

        if ($consumption > $peak_day['value']) {
            $peak_day['date'] = $formatted_date;
            $peak_day['value'] = $consumption;
        }
    }

    $avg_daily = round($total_consumption / $days, 2);

    $hourly_start = date('Y-m-d H:i:s', strtotime('-7 days'));
    $hourly_end = date('Y-m-d H:i:s');
    $hourly_data = getHourlyConsumptionPattern($selected_meter, $hourly_start, $hourly_end, true);
}

$consumption_chart_data = array_map(function ($volume) {
    return round(cubicMetersToCubicCentimeters($volume), 2);
}, $consumption_data);

$hourly_chart_data = array_map(function ($volume) {
    return round(cubicMetersToCubicCentimeters($volume), 2);
}, $hourly_data);

$page_title = "Water Consumption";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Consumption - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .sidebar {
            min-height: calc(100vh - 60px);
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .nav-link {
            color: #333;
            padding: 10px 20px;
            margin: 5px 0;
            border-radius: 5px;
        }
        .nav-link:hover {
            background-color: #e9ecef;
        }
        .nav-link.active {
            background-color: #0d6efd;
            color: white;
        }
        .nav-link i {
            margin-right: 10px;
        }
        .dashboard-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .chart-container {
            position: relative;
            width: 100%;
            height: 100%;
        }
        .bg-primary { background: linear-gradient(45deg, #0d6efd, #0b5ed7); }
        .bg-success { background: linear-gradient(45deg, #198754, #157347); }
        .bg-warning { background: linear-gradient(45deg, #ffc107, #ffb400); }
        .bg-info { background: linear-gradient(45deg, #0dcaf0, #0bacd0); }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-md-block sidebar">
                <div class="position-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="user-dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="user-consumption.php">
                                <i class="bi bi-graph-up"></i> Consumption
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="user-bills.php">
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
                    <h1 class="h2">Water Consumption Analytics</h1>
                </div>

                <?php if ($selected_meter_details): ?>
                    <div class="alert alert-secondary d-flex justify-content-between flex-wrap gap-2">
                        <span>
                            <i class="bi bi-speedometer2"></i>
                            Showing consumption for meter <strong><?php echo htmlspecialchars($selected_meter_number); ?></strong>
                        </span>
                        <?php if ($selected_meter_location !== ''): ?>
                            <span class="text-muted">Location: <?php echo htmlspecialchars($selected_meter_location); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card dashboard-card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="meter_id" class="form-label">Select Meter</label>
                                <select class="form-control" id="meter_id" name="meter_id" required>
                                    <option value="">Choose a meter...</option>
                                    <?php if ($meters->num_rows > 0): ?>
                                        <?php while ($meter = $meters->fetch_assoc()): ?>
                                            <option value="<?php echo $meter['meter_id']; ?>" <?php echo $meter['meter_id'] == $selected_meter ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($meter['meter_number']); ?> -
                                                <?php echo htmlspecialchars($meter['location']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="period" class="form-label">Time Period</label>
                                <select class="form-control" id="period" name="period">
                                    <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                                    <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                                    <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>Last 3 Months</option>
                                    <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Last Year</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block w-100">
                                    <i class="bi bi-funnel"></i> Apply Filters
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Consumption values and graphs on this page are displayed in <code>cm^3</code>. Billing still uses <code>m^3</code>. This page checks for new assigned meters and updated bill totals automatically.
                </div>

                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-primary text-white">
                            <div class="card-body">
                                <h6>Total Billed</h6>
                                <h3><?php echo formatCurrency($billing_stats['total_billed']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-success text-white">
                            <div class="card-body">
                                <h6>Total Paid</h6>
                                <h3><?php echo formatCurrency($billing_stats['total_paid']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-warning text-white">
                            <div class="card-body">
                                <h6>Outstanding</h6>
                                <h3><?php echo formatCurrency($billing_stats['outstanding']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-info text-white">
                            <div class="card-body">
                                <h6>Pending Bills</h6>
                                <h3><?php echo $billing_stats['pending_count']; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($latest_bill): ?>
                    <div class="alert alert-light border mb-4">
                        <i class="bi bi-receipt"></i>
                        Latest bill: <strong>#<?php echo $latest_bill['bill_id']; ?></strong>,
                        <?php echo formatCurrency($latest_bill['total_amount']); ?>,
                        status <strong><?php echo ucfirst($latest_bill['bill_status']); ?></strong>,
                        due <?php echo date('M d, Y', strtotime($latest_bill['due_date'])); ?>.
                        <a href="user-bills.php" class="alert-link">View all bills</a>
                    </div>
                <?php endif; ?>

                <?php if ($meters->num_rows == 0): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        You don't have any smart meters assigned yet. Please contact administrator.
                    </div>

                <?php elseif ($selected_meter && !empty($consumption_data)): ?>

                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card dashboard-card bg-primary text-white">
                                <div class="card-body">
                                    <h6>Total Consumption</h6>
                                    <h3><?php echo formatVolumeInCubicCentimeters($total_consumption); ?></h3>
                                    <small><?php echo date('M d', strtotime($start_date)); ?> - <?php echo date('M d, Y'); ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card dashboard-card bg-success text-white">
                                <div class="card-body">
                                    <h6>Average Daily</h6>
                                    <h3><?php echo formatVolumeInCubicCentimeters($avg_daily); ?></h3>
                                    <small>Over <?php echo round($days); ?> days</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card dashboard-card bg-warning text-white">
                                <div class="card-body">
                                    <h6>Peak Period</h6>
                                    <h3><?php echo formatVolumeInCubicCentimeters($peak_day['value']); ?></h3>
                                    <small><?php echo $peak_day['date']; ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card dashboard-card bg-info text-white">
                                <div class="card-body">
                                    <h6>Est. Cost</h6>
                                    <h3><?php echo formatCurrency($total_consumption * WATER_RATE_PER_UNIT); ?></h3>
                                    <small>@ <?php echo formatCurrency(WATER_RATE_PER_UNIT); ?>/m^3</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Consumption Chart -->
                    <div class="card dashboard-card mb-4">
                        <div class="card-header">
                            <h5>Consumption Over Time - Meter <?php echo htmlspecialchars($selected_meter_number); ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 400px;">
                                <canvas id="consumptionChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Hourly Pattern Chart -->
                    <div class="card dashboard-card mb-4">
                        <div class="card-header">
                            <h5>Average Hourly Consumption Pattern (7 Days) - Meter <?php echo htmlspecialchars($selected_meter_number); ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="hourlyChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Table -->
                    <div class="card dashboard-card mb-4">
                        <div class="card-header">
                            <h5>Detailed Consumption Data - Meter <?php echo htmlspecialchars($selected_meter_number); ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Period</th>
                                            <th class="text-end">Consumption (cm^3)</th>
                                            <th class="text-end">Estimated Cost</th>
                                            <th class="text-center">Readings</th>
                                            <th>First Reading</th>
                                            <th>Last Reading</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($details_rows)): ?>
                                            <?php foreach ($details_rows as $row): ?>
                                                <tr>
                                                    <td>
                                                        <?php
                                                        if ($display_format === 'daily') {
                                                            echo date('M d, Y', strtotime($row['period']));
                                                        } else {
                                                            echo date('F Y', strtotime($row['period'] . '-01'));
                                                        }
                                                        ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <strong><?php echo number_format(cubicMetersToCubicCentimeters($row['consumption'] ?? 0), 0); ?></strong>
                                                    </td>
                                                    <td class="text-end">
                                                        <?php echo formatCurrency(($row['consumption'] ?? 0) * WATER_RATE_PER_UNIT); ?>
                                                    </td>
                                                    <td class="text-center"><?php echo $row['readings_count']; ?></td>
                                                    <td><?php echo date('M d, H:i', strtotime($row['first_reading'])); ?></td>
                                                    <td><?php echo date('M d, H:i', strtotime($row['last_reading'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center">No detailed data available</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <?php elseif ($selected_meter): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        No consumption data available for meter <strong><?php echo htmlspecialchars($selected_meter_number); ?></strong> in the selected period.
                        <a href="?meter_id=<?php echo $selected_meter; ?>&period=month" class="alert-link">Try last 30 days</a>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <?php if ($selected_meter && !empty($consumption_data)): ?>
    <script>
        const selectedMeterNumber = <?php echo json_encode($selected_meter_number); ?>;

        const formatVolume = (value) => Number(value).toLocaleString(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        });

        // Consumption Chart
        const ctx = document.getElementById('consumptionChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: `Water Consumption - Meter ${selectedMeterNumber} (cm^3)`,
                    data: <?php echo json_encode($consumption_chart_data); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1,
                    fill: true,
                    pointBackgroundColor: 'rgb(75, 192, 192)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: `Meter ${selectedMeterNumber}`
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Consumption: ${formatVolume(context.raw)} cm^3`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Cubic Centimeters (cm^3)'
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Hourly Pattern Chart
        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        const hourlyLabels = [];
        const hourlyValues = [];

        <?php for ($i = 0; $i < 24; $i++): ?>
            hourlyLabels.push('<?php echo sprintf("%02d:00", $i); ?>');
            hourlyValues.push(<?php echo $hourly_chart_data[$i] ?? 0; ?>);
        <?php endfor; ?>

        new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: hourlyLabels,
                datasets: [{
                    label: `Average Consumption - Meter ${selectedMeterNumber} (cm^3)`,
                    data: hourlyValues,
                    backgroundColor: 'rgba(153, 102, 255, 0.7)',
                    borderColor: 'rgb(153, 102, 255)',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: `Meter ${selectedMeterNumber}`
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Avg: ${formatVolume(context.raw)} cm^3`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Cubic Centimeters (cm^3)'
                        }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>

    <script>
        (function () {
            const refreshMs = <?php echo $has_assigned_meters ? ((int) IOT_SIMULATION_INTERVAL_SECONDS * 1000) + 5000 : 15000; ?>;

            window.setInterval(function () {
                if (document.visibilityState === 'visible') {
                    window.location.reload();
                }
            }, refreshMs);
        })();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php include '../includes/footer.php'; ?>
</body>
</html>

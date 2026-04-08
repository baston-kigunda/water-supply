<?php
// ========================================
// FILE: admin-meters.php
// PURPOSE: Manage smart water meters
// ========================================

require_once '../includes/functions.php';
require_once '../config/database.php';

requireAdmin();

$message = '';
$error = '';

// Handle meter actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new meter
                $meter_number = $_POST['meter_number'];
                $user_id = $_POST['user_id'] ?: null;
                $location = $_POST['location'];
                $installation_date = $_POST['installation_date'];
                
                $stmt = $conn->prepare("
                    INSERT INTO smart_meters (meter_number, user_id, location, installation_date, meter_status) 
                    VALUES (?, ?, ?, ?, 'active')
                ");
                $stmt->bind_param("siss", $meter_number, $user_id, $location, $installation_date);
                
                if ($stmt->execute()) {
                    $message = "Meter added successfully!";
                } else {
                    $error = "Error adding meter: " . $conn->error;
                }
                break;
                
            case 'assign':
                // Assign meter to user
                $meter_id = $_POST['meter_id'];
                $user_id = $_POST['user_id'];
                
                $stmt = $conn->prepare("UPDATE smart_meters SET user_id=? WHERE meter_id=?");
                $stmt->bind_param("ii", $user_id, $meter_id);
                
                if ($stmt->execute()) {
                    $message = "Meter assigned successfully!";
                } else {
                    $error = "Error assigning meter: " . $conn->error;
                }
                break;
                
            case 'status':
                // Update meter status
                $meter_id = $_POST['meter_id'];
                $status = $_POST['status'];
                
                $stmt = $conn->prepare("UPDATE smart_meters SET meter_status=? WHERE meter_id=?");
                $stmt->bind_param("si", $status, $meter_id);
                
                if ($stmt->execute()) {
                    $message = "Meter status updated successfully!";
                } else {
                    $error = "Error updating meter status: " . $conn->error;
                }
                break;
        }
    }
}

// Get all meters with user details
$meters = [];
$result = $conn->query("
    SELECT sm.*, u.full_name, u.username, u.email,
           (SELECT COUNT(*) FROM meter_readings WHERE meter_id = sm.meter_id) as reading_count,
           (SELECT reading_value FROM meter_readings WHERE meter_id = sm.meter_id ORDER BY reading_time DESC LIMIT 1) as latest_reading,
           (SELECT reading_time FROM meter_readings WHERE meter_id = sm.meter_id ORDER BY reading_time DESC LIMIT 1) as latest_reading_time
    FROM smart_meters sm
    LEFT JOIN users u ON sm.user_id = u.user_id
    ORDER BY sm.meter_id DESC
");

while ($row = $result->fetch_assoc()) {
    $meters[] = $row;
}

// Get all users for assignment dropdown
$users = [];
$result = $conn->query("SELECT user_id, full_name, username FROM users WHERE user_role = 'consumer' AND account_status = 'active' ORDER BY full_name");
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$page_title = "Manage Smart Meters";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Smart Meters - <?php echo SITE_NAME; ?></title>
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
                            <a class="nav-link active" href="admin-meters.php">
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
                    <h1 class="h2">Manage Smart Meters</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addMeterModal">
                            <i class="bi bi-plus-circle"></i> Add New Meter
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

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-primary text-white">
                            <div class="card-body">
                                <h6>Total Meters</h6>
                                <h3><?php echo count($meters); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-success text-white">
                            <div class="card-body">
                                <h6>Active Meters</h6>
                                <h3><?php echo count(array_filter($meters, function($m) { return $m['meter_status'] == 'active'; })); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-warning text-white">
                            <div class="card-body">
                                <h6>Inactive Meters</h6>
                                <h3><?php echo count(array_filter($meters, function($m) { return $m['meter_status'] == 'inactive'; })); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-info text-white">
                            <div class="card-body">
                                <h6>Assigned Meters</h6>
                                <h3><?php echo count(array_filter($meters, function($m) { return !is_null($m['user_id']); })); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Meters Table -->
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="metersTable" class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Meter Number</th>
                                        <th>Assigned To</th>
                                        <th>Location</th>
                                        <th>Installation Date</th>
                                        <th>Status</th>
                                        <th>Last Reading</th>
                                        <th>Last Reading Time</th>
                                        <th>Readings</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($meters as $meter): ?>
                                        <tr>
                                            <td><?php echo $meter['meter_id']; ?></td>
                                            <td><strong><?php echo $meter['meter_number']; ?></strong></td>
                                            <td>
                                                <?php if ($meter['full_name']): ?>
                                                    <?php echo htmlspecialchars($meter['full_name']); ?><br>
                                                    <small class="text-muted"><?php echo $meter['username']; ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Not assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($meter['location']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($meter['installation_date'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $meter['meter_status'] == 'active' ? 'success' : 
                                                        ($meter['meter_status'] == 'maintenance' ? 'warning' : 'secondary'); 
                                                ?>">
                                                    <?php echo ucfirst($meter['meter_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($meter['latest_reading']): ?>
                                                    <?php echo number_format($meter['latest_reading'], 2); ?> m³
                                                <?php else: ?>
                                                    <span class="text-muted">No data</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($meter['latest_reading_time']): ?>
                                                    <?php echo date('M d, H:i', strtotime($meter['latest_reading_time'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $meter['reading_count']; ?></span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="viewMeterReadings(<?php echo $meter['meter_id']; ?>)">
                                                    <i class="bi bi-graph-up"></i>
                                                </button>
                                                <?php if (!$meter['user_id']): ?>
                                                    <button class="btn btn-sm btn-success" onclick="assignMeter(<?php echo $meter['meter_id']; ?>)">
                                                        <i class="bi bi-person-plus"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-warning" onclick="changeStatus(<?php echo $meter['meter_id']; ?>, '<?php echo $meter['meter_status']; ?>')">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Meter Modal -->
    <div class="modal fade" id="addMeterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Smart Meter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label for="meter_number" class="form-label">Meter Number</label>
                            <input type="text" class="form-control" id="meter_number" name="meter_number" required>
                            <small class="text-muted">Unique identifier for the meter</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="user_id" class="form-label">Assign to User (Optional)</label>
                            <select class="form-control" id="user_id" name="user_id">
                                <option value="">-- Not Assigned --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['user_id']; ?>">
                                        <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo $user['username']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="location" name="location" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="installation_date" class="form-label">Installation Date</label>
                            <input type="date" class="form-control" id="installation_date" name="installation_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Meter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign Meter Modal -->
    <div class="modal fade" id="assignMeterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Meter to User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign">
                        <input type="hidden" name="meter_id" id="assign_meter_id">
                        
                        <div class="mb-3">
                            <label for="assign_user_id" class="form-label">Select User</label>
                            <select class="form-control" id="assign_user_id" name="user_id" required>
                                <option value="">-- Choose User --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['user_id']; ?>">
                                        <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo $user['username']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Assign Meter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Status Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Meter Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="meter_id" id="status_meter_id">
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">New Status</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="maintenance">Maintenance</option>
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
            $('#metersTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 10
            });
        });

        function assignMeter(meter_id) {
            document.getElementById('assign_meter_id').value = meter_id;
            new bootstrap.Modal(document.getElementById('assignMeterModal')).show();
        }

        function changeStatus(meter_id, current_status) {
            document.getElementById('status_meter_id').value = meter_id;
            document.getElementById('status').value = current_status;
            new bootstrap.Modal(document.getElementById('statusModal')).show();
        }

        function viewMeterReadings(meter_id) {
            // Redirect to meter readings page or show modal with readings
            window.location.href = 'admin-meter-readings.php?meter_id=' + meter_id;
        }
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
<?php
// ========================================
// FILE: admin-schedules.php
// PURPOSE: Manage water supply schedules
// ========================================

require_once '../includes/functions.php';
require_once '../config/database.php';

requireAdmin();

$message = '';
$error = '';

// Handle schedule actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new schedule
                $area_name = $_POST['area_name'];
                $supply_date = $_POST['supply_date'];
                $start_time = $_POST['start_time'];
                $end_time = $_POST['end_time'];
                $status = $_POST['status'];
                
                $stmt = $conn->prepare("
                    INSERT INTO water_schedules (area_name, supply_date, start_time, end_time, status, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("sssssi", $area_name, $supply_date, $start_time, $end_time, $status, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    // Notify users in this area
                    $schedule_id = $conn->insert_id;
                    
                    // Get users in this area (based on address containing area name)
                    $users = $conn->query("
                        SELECT user_id FROM users 
                        WHERE address LIKE '%" . $conn->real_escape_string($area_name) . "%'
                        AND user_role = 'consumer'
                    ");
                    
                    while ($user = $users->fetch_assoc()) {
                        createNotification(
                            $user['user_id'],
                            "New Water Supply Schedule",
                            "Water will be supplied to $area_name on " . date('d M Y', strtotime($supply_date)) . 
                            " from " . date('H:i', strtotime($start_time)) . " to " . date('H:i', strtotime($end_time)),
                            'supply'
                        );
                    }
                    
                    $message = "Schedule added successfully!";
                } else {
                    $error = "Error adding schedule: " . $conn->error;
                }
                break;
                
            case 'edit':
                // Edit schedule
                $schedule_id = $_POST['schedule_id'];
                $area_name = $_POST['area_name'];
                $supply_date = $_POST['supply_date'];
                $start_time = $_POST['start_time'];
                $end_time = $_POST['end_time'];
                $status = $_POST['status'];
                
                $stmt = $conn->prepare("
                    UPDATE water_schedules 
                    SET area_name=?, supply_date=?, start_time=?, end_time=?, status=?
                    WHERE schedule_id=?
                ");
                $stmt->bind_param("sssssi", $area_name, $supply_date, $start_time, $end_time, $status, $schedule_id);
                
                if ($stmt->execute()) {
                    $message = "Schedule updated successfully!";
                } else {
                    $error = "Error updating schedule: " . $conn->error;
                }
                break;
                
            case 'delete':
                // Delete schedule
                $schedule_id = $_POST['schedule_id'];
                
                $stmt = $conn->prepare("DELETE FROM water_schedules WHERE schedule_id=?");
                $stmt->bind_param("i", $schedule_id);
                
                if ($stmt->execute()) {
                    $message = "Schedule deleted successfully!";
                } else {
                    $error = "Error deleting schedule: " . $conn->error;
                }
                break;
                
            case 'update_status':
                // Update schedule status
                $schedule_id = $_POST['schedule_id'];
                $status = $_POST['status'];
                
                $stmt = $conn->prepare("UPDATE water_schedules SET status=? WHERE schedule_id=?");
                $stmt->bind_param("si", $status, $schedule_id);
                
                if ($stmt->execute()) {
                    // Get schedule details for notification
                    $schedule = $conn->query("SELECT * FROM water_schedules WHERE schedule_id=$schedule_id")->fetch_assoc();
                    
                    // Notify users in this area
                    $users = $conn->query("
                        SELECT user_id FROM users 
                        WHERE address LIKE '%" . $conn->real_escape_string($schedule['area_name']) . "%'
                        AND user_role = 'consumer'
                    ");
                    
                    while ($user = $users->fetch_assoc()) {
                        createNotification(
                            $user['user_id'],
                            "Water Supply Status Update",
                            "Water supply status for " . $schedule['area_name'] . " on " . date('d M Y', strtotime($schedule['supply_date'])) . 
                            " has been updated to: " . ucfirst($status),
                            'supply'
                        );
                    }
                    
                    $message = "Schedule status updated successfully!";
                } else {
                    $error = "Error updating schedule status: " . $conn->error;
                }
                break;
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? date('Y-m-d');

// Build query
$query = "
    SELECT ws.*, u.full_name as created_by_name
    FROM water_schedules ws
    LEFT JOIN users u ON ws.created_by = u.user_id
    WHERE 1=1
";

if ($status_filter) {
    $query .= " AND ws.status = '" . $conn->real_escape_string($status_filter) . "'";
}

if ($date_filter) {
    $query .= " AND ws.supply_date = '" . $conn->real_escape_string($date_filter) . "'";
}

$query .= " ORDER BY ws.supply_date DESC, ws.start_time ASC";

$schedules = $conn->query($query);

// Get today's schedules
$today = $conn->query("
    SELECT * FROM water_schedules 
    WHERE supply_date = CURRENT_DATE() 
    ORDER BY start_time
");

// Get upcoming schedules (next 7 days)
$upcoming = $conn->query("
    SELECT * FROM water_schedules 
    WHERE supply_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
    AND status = 'scheduled'
    ORDER BY supply_date, start_time
");

$page_title = "Manage Water Schedules";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Water Schedules - <?php echo SITE_NAME; ?></title>
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
                            <a class="nav-link" href="admin-bills.php">
                                <i class="bi bi-receipt"></i> Bills
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="admin-schedules.php">
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
                    <h1 class="h2">Manage Water Supply Schedules</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                            <i class="bi bi-calendar-plus"></i> Add New Schedule
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

                <!-- Today's Schedules -->
                <div class="card dashboard-card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-calendar-day"></i> Today's Schedules (<?php echo date('d M Y'); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($today->num_rows > 0): ?>
                            <div class="row">
                                <?php while ($schedule = $today->fetch_assoc()): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card <?php 
                                            echo $schedule['status'] == 'active' ? 'border-success' : 
                                                ($schedule['status'] == 'completed' ? 'border-secondary' : 'border-warning'); 
                                        ?>">
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($schedule['area_name']); ?></h6>
                                                <p class="mb-1">
                                                    <i class="bi bi-clock"></i> 
                                                    <?php echo date('H:i', strtotime($schedule['start_time'])); ?> - 
                                                    <?php echo date('H:i', strtotime($schedule['end_time'])); ?>
                                                </p>
                                                <p class="mb-1">
                                                    <span class="badge bg-<?php 
                                                        echo $schedule['status'] == 'active' ? 'success' : 
                                                            ($schedule['status'] == 'completed' ? 'secondary' : 
                                                            ($schedule['status'] == 'scheduled' ? 'primary' : 'danger')); 
                                                    ?>">
                                                        <?php echo ucfirst($schedule['status']); ?>
                                                    </span>
                                                </p>
                                                <?php if ($schedule['status'] == 'scheduled'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="updateScheduleStatus(<?php echo $schedule['schedule_id']; ?>, 'active')">
                                                        Start Supply
                                                    </button>
                                                <?php elseif ($schedule['status'] == 'active'): ?>
                                                    <button class="btn btn-sm btn-secondary" onclick="updateScheduleStatus(<?php echo $schedule['schedule_id']; ?>, 'completed')">
                                                        Mark Complete
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No schedules for today.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Schedules -->
                <div class="card dashboard-card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-calendar-week"></i> Upcoming Schedules (Next 7 Days)</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($upcoming->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Area</th>
                                            <th>Time</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($schedule = $upcoming->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('d M Y', strtotime($schedule['supply_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($schedule['area_name']); ?></td>
                                                <td><?php echo date('H:i', strtotime($schedule['start_time'])); ?> - <?php echo date('H:i', strtotime($schedule['end_time'])); ?></td>
                                                <td><span class="badge bg-primary"><?php echo ucfirst($schedule['status']); ?></span></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No upcoming schedules.</p>
                        <?php endif; ?>
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
                                    <option value="scheduled" <?php echo $status_filter == 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="date" class="form-label">Filter by Date</label>
                                <input type="date" class="form-control" id="date" name="date" value="<?php echo $date_filter; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block">Apply Filters</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- All Schedules Table -->
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="schedulesTable" class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Area</th>
                                        <th>Date</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($schedule = $schedules->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?php echo $schedule['schedule_id']; ?></td>
                                            <td><?php echo htmlspecialchars($schedule['area_name']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($schedule['supply_date'])); ?></td>
                                            <td><?php echo date('H:i', strtotime($schedule['start_time'])); ?></td>
                                            <td><?php echo date('H:i', strtotime($schedule['end_time'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $schedule['status'] == 'active' ? 'success' : 
                                                        ($schedule['status'] == 'completed' ? 'secondary' : 
                                                        ($schedule['status'] == 'scheduled' ? 'primary' : 'danger')); 
                                                ?>">
                                                    <?php echo ucfirst($schedule['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $schedule['created_by_name'] ?? 'System'; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($schedule['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="editSchedule(<?php echo htmlspecialchars(json_encode($schedule)); ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning" onclick="updateStatus(<?php echo $schedule['schedule_id']; ?>, '<?php echo $schedule['status']; ?>')">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteSchedule(<?php echo $schedule['schedule_id']; ?>, '<?php echo $schedule['area_name']; ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
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

    <!-- Add Schedule Modal -->
    <div class="modal fade" id="addScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Water Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label for="area_name" class="form-label">Area/Location</label>
                            <input type="text" class="form-control" id="area_name" name="area_name" required>
                            <small class="text-muted">Enter the area or zone name</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="supply_date" class="form-label">Supply Date</label>
                            <input type="date" class="form-control" id="supply_date" name="supply_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_time" class="form-label">Start Time</label>
                                <input type="time" class="form-control" id="start_time" name="start_time" value="06:00" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_time" class="form-label">End Time</label>
                                <input type="time" class="form-control" id="end_time" name="end_time" value="18:00" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="scheduled">Scheduled</option>
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Schedule Modal -->
    <div class="modal fade" id="editScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="schedule_id" id="edit_schedule_id">
                        
                        <div class="mb-3">
                            <label for="edit_area_name" class="form-label">Area/Location</label>
                            <input type="text" class="form-control" id="edit_area_name" name="area_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_supply_date" class="form-label">Supply Date</label>
                            <input type="date" class="form-control" id="edit_supply_date" name="supply_date" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_start_time" class="form-label">Start Time</label>
                                <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_end_time" class="form-label">End Time</label>
                                <input type="time" class="form-control" id="edit_end_time" name="end_time" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-control" id="edit_status" name="status" required>
                                <option value="scheduled">Scheduled</option>
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Schedule Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="schedule_id" id="update_schedule_id">
                        
                        <div class="mb-3">
                            <label for="update_status" class="form-label">New Status</label>
                            <select class="form-control" id="update_status" name="status" required>
                                <option value="scheduled">Scheduled</option>
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
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

    <!-- Delete Schedule Modal -->
    <div class="modal fade" id="deleteScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="schedule_id" id="delete_schedule_id">
                        <p>Are you sure you want to delete the schedule for <strong id="delete_schedule_name"></strong>?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
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
            $('#schedulesTable').DataTable({
                order: [[2, 'desc'], [3, 'asc']],
                pageLength: 25
            });
        });

        function editSchedule(schedule) {
            document.getElementById('edit_schedule_id').value = schedule.schedule_id;
            document.getElementById('edit_area_name').value = schedule.area_name;
            document.getElementById('edit_supply_date').value = schedule.supply_date;
            document.getElementById('edit_start_time').value = schedule.start_time;
            document.getElementById('edit_end_time').value = schedule.end_time;
            document.getElementById('edit_status').value = schedule.status;
            
            new bootstrap.Modal(document.getElementById('editScheduleModal')).show();
        }

        function updateScheduleStatus(schedule_id, status) {
            document.getElementById('update_schedule_id').value = schedule_id;
            document.getElementById('update_status').value = status;
            new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
        }

        function updateStatus(schedule_id, current_status) {
            updateScheduleStatus(schedule_id, current_status);
        }

        function deleteSchedule(schedule_id, area_name) {
            document.getElementById('delete_schedule_id').value = schedule_id;
            document.getElementById('delete_schedule_name').textContent = area_name;
            new bootstrap.Modal(document.getElementById('deleteScheduleModal')).show();
        }
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
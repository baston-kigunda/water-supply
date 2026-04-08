<?php
// ========================================
// FILE: admin-leaks.php
// PURPOSE: Manage leak reports and assign technicians
// ========================================

require_once '../includes/functions.php';
require_once '../config/database.php';

requireAdmin();

$message = '';
$error = '';

// Handle leak report actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'assign':
                // Assign technician to leak report
                $report_id = $_POST['report_id'];
                $assigned_to = $_POST['assigned_to'];
                
                $stmt = $conn->prepare("
                    UPDATE leak_reports 
                    SET assigned_to=?, report_status='assigned' 
                    WHERE report_id=?
                ");
                $stmt->bind_param("ii", $assigned_to, $report_id);
                
                if ($stmt->execute()) {
                    // Get report details for notification
                    $report = $conn->query("SELECT * FROM leak_reports WHERE report_id=$report_id")->fetch_assoc();
                    
                    // Notify technician
                    createNotification(
                        $assigned_to,
                        "New Leak Report Assigned",
                        "You have been assigned to investigate a leak report in " . $report['location_description'],
                        'leak'
                    );
                    
                    // Notify user who reported
                    createNotification(
                        $report['user_id'],
                        "Leak Report Assigned",
                        "A technician has been assigned to investigate your leak report.",
                        'leak'
                    );
                    
                    $message = "Technician assigned successfully!";
                } else {
                    $error = "Error assigning technician: " . $conn->error;
                }
                break;
                
            case 'update_status':
                // Update leak report status
                $report_id = $_POST['report_id'];
                $status = $_POST['status'];
                $resolution_notes = $_POST['resolution_notes'] ?? '';
                
                $stmt = $conn->prepare("
                    UPDATE leak_reports 
                    SET report_status=?, resolution_notes=?, resolved_at=NOW() 
                    WHERE report_id=?
                ");
                $stmt->bind_param("ssi", $status, $resolution_notes, $report_id);
                
                if ($stmt->execute()) {
                    // Get report details
                    $report = $conn->query("SELECT * FROM leak_reports WHERE report_id=$report_id")->fetch_assoc();
                    
                    // Notify user
                    $status_message = $status == 'resolved' ? "has been resolved" : "has been marked as false alarm";
                    createNotification(
                        $report['user_id'],
                        "Leak Report Update",
                        "Your leak report " . $status_message . ". " . ($resolution_notes ? "Notes: " . $resolution_notes : ""),
                        'leak'
                    );
                    
                    $message = "Report status updated successfully!";
                } else {
                    $error = "Error updating report status: " . $conn->error;
                }
                break;
        }
    }
}

// Get all leak reports with details
$leak_reports = $conn->query("
    SELECT lr.*, 
           u.full_name as reporter_name, u.phone_number as reporter_phone, u.email as reporter_email,
           sm.meter_number,
           tech.full_name as technician_name
    FROM leak_reports lr
    JOIN users u ON lr.user_id = u.user_id
    LEFT JOIN smart_meters sm ON lr.meter_id = sm.meter_id
    LEFT JOIN users tech ON lr.assigned_to = tech.user_id
    ORDER BY 
        CASE lr.priority 
            WHEN 'emergency' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
        END,
        lr.reported_at DESC
");

// Get technicians for assignment
$technicians = $conn->query("
    SELECT user_id, full_name, username 
    FROM users 
    WHERE user_role = 'staff' AND account_status = 'active'
    ORDER BY full_name
");

// Get statistics
$stats = [];

// Count by status
$result = $conn->query("SELECT report_status, COUNT(*) as count FROM leak_reports GROUP BY report_status");
while ($row = $result->fetch_assoc()) {
    $stats[$row['report_status'] . '_count'] = $row['count'];
}

// Count by priority
$result = $conn->query("SELECT priority, COUNT(*) as count FROM leak_reports WHERE report_status != 'resolved' GROUP BY priority");
while ($row = $result->fetch_assoc()) {
    $stats['priority_' . $row['priority']] = $row['count'];
}

// Average resolution time (in hours)
$result = $conn->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, reported_at, resolved_at)) as avg_time 
    FROM leak_reports 
    WHERE resolved_at IS NOT NULL
");
$stats['avg_resolution_time'] = round($result->fetch_assoc()['avg_time'] ?? 0, 1);

$page_title = "Manage Leak Reports";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Leak Reports - <?php echo SITE_NAME; ?></title>
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
                            <a class="nav-link" href="admin-schedules.php">
                                <i class="bi bi-calendar"></i> Water Schedules
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="admin-leaks.php">
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
                    <h1 class="h2">Manage Leak Reports</h1>
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
                        <div class="card dashboard-card bg-danger text-white">
                            <div class="card-body">
                                <h6>Pending Reports</h6>
                                <h3><?php echo $stats['pending_count'] ?? 0; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-warning text-white">
                            <div class="card-body">
                                <h6>In Progress</h6>
                                <h3><?php echo ($stats['assigned_count'] ?? 0) + ($stats['in_progress_count'] ?? 0); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-success text-white">
                            <div class="card-body">
                                <h6>Resolved</h6>
                                <h3><?php echo $stats['resolved_count'] ?? 0; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-info text-white">
                            <div class="card-body">
                                <h6>Avg. Resolution</h6>
                                <h3><?php echo $stats['avg_resolution_time']; ?> hrs</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Priority Summary -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5>Active Reports by Priority</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="p-3 bg-danger text-white rounded">
                                            <h6>Emergency</h6>
                                            <h3><?php echo $stats['priority_emergency'] ?? 0; ?></h3>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="p-3 bg-warning text-white rounded">
                                            <h6>High</h6>
                                            <h3><?php echo $stats['priority_high'] ?? 0; ?></h3>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="p-3 bg-info text-white rounded">
                                            <h6>Medium</h6>
                                            <h3><?php echo $stats['priority_medium'] ?? 0; ?></h3>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="p-3 bg-secondary text-white rounded">
                                            <h6>Low</h6>
                                            <h3><?php echo $stats['priority_low'] ?? 0; ?></h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Leak Reports Table -->
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="leaksTable" class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Priority</th>
                                        <th>Reporter</th>
                                        <th>Location</th>
                                        <th>Description</th>
                                        <th>Meter</th>
                                        <th>Status</th>
                                        <th>Technician</th>
                                        <th>Reported</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($report = $leak_reports->fetch_assoc()): ?>
                                        <tr class="<?php 
                                            echo $report['priority'] == 'emergency' ? 'table-danger' : 
                                                ($report['priority'] == 'high' ? 'table-warning' : ''); 
                                        ?>">
                                            <td>#<?php echo $report['report_id']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $report['priority'] == 'emergency' ? 'danger' : 
                                                        ($report['priority'] == 'high' ? 'warning' : 
                                                        ($report['priority'] == 'medium' ? 'info' : 'secondary')); 
                                                ?>">
                                                    <?php echo ucfirst($report['priority']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($report['reporter_name']); ?><br>
                                                <small class="text-muted"><?php echo $report['reporter_phone']; ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($report['location_description']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($report['leak_description'], 0, 50)) . '...'; ?></td>
                                            <td><?php echo $report['meter_number'] ?? 'N/A'; ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $report['report_status'] == 'resolved' ? 'success' : 
                                                        ($report['report_status'] == 'pending' ? 'danger' : 
                                                        ($report['report_status'] == 'assigned' ? 'warning' : 
                                                        ($report['report_status'] == 'in_progress' ? 'info' : 'secondary'))); 
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $report['report_status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($report['technician_name']): ?>
                                                    <?php echo htmlspecialchars($report['technician_name']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo date('M d, H:i', strtotime($report['reported_at'])); ?>
                                                <?php 
                                                $hours_ago = (time() - strtotime($report['reported_at'])) / 3600;
                                                if ($hours_ago > 24 && $report['report_status'] != 'resolved'): 
                                                ?>
                                                    <br><small class="text-danger"><?php echo round($hours_ago); ?> hrs ago</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($report['report_status'] == 'pending'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="assignTechnician(<?php echo $report['report_id']; ?>)">
                                                        <i class="bi bi-person-plus"></i> Assign
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($report['report_status'] != 'resolved' && $report['report_status'] != 'false_alarm'): ?>
                                                    <button class="btn btn-sm btn-warning" onclick="updateStatus(<?php echo $report['report_id']; ?>, '<?php echo $report['report_status']; ?>')">
                                                        <i class="bi bi-pencil"></i> Update
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-sm btn-info" onclick="viewDetails(<?php echo htmlspecialchars(json_encode($report)); ?>)">
                                                    <i class="bi bi-eye"></i>
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

    <!-- Assign Technician Modal -->
    <div class="modal fade" id="assignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Technician</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign">
                        <input type="hidden" name="report_id" id="assign_report_id">
                        
                        <div class="mb-3">
                            <label for="assigned_to" class="form-label">Select Technician</label>
                            <select class="form-control" id="assigned_to" name="assigned_to" required>
                                <option value="">-- Choose Technician --</option>
                                <?php while ($tech = $technicians->fetch_assoc()): ?>
                                    <option value="<?php echo $tech['user_id']; ?>">
                                        <?php echo htmlspecialchars($tech['full_name']); ?> (<?php echo $tech['username']; ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Assign</button>
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
                    <h5 class="modal-title">Update Report Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="report_id" id="update_report_id">
                        
                        <div class="mb-3">
                            <label for="update_status" class="form-label">Status</label>
                            <select class="form-control" id="update_status" name="status" required>
                                <option value="pending">Pending</option>
                                <option value="assigned">Assigned</option>
                                <option value="in_progress">In Progress</option>
                                <option value="resolved">Resolved</option>
                                <option value="false_alarm">False Alarm</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="resolution_notes" class="form-label">Resolution Notes</label>
                            <textarea class="form-control" id="resolution_notes" name="resolution_notes" rows="3"></textarea>
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

    <!-- View Details Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Leak Report Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Report ID:</strong> <span id="view_id"></span></p>
                            <p><strong>Priority:</strong> <span id="view_priority"></span></p>
                            <p><strong>Status:</strong> <span id="view_status"></span></p>
                            <p><strong>Reported By:</strong> <span id="view_reporter"></span></p>
                            <p><strong>Phone:</strong> <span id="view_phone"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Location:</strong> <span id="view_location"></span></p>
                            <p><strong>Meter:</strong> <span id="view_meter"></span></p>
                            <p><strong>Reported At:</strong> <span id="view_reported_at"></span></p>
                            <p><strong>Technician:</strong> <span id="view_technician"></span></p>
                            <p><strong>Resolved At:</strong> <span id="view_resolved_at"></span></p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <p><strong>Description:</strong></p>
                            <p class="border p-3" id="view_description"></p>
                        </div>
                    </div>
                    <div class="row mt-3" id="view_notes_row">
                        <div class="col-md-12">
                            <p><strong>Resolution Notes:</strong></p>
                            <p class="border p-3" id="view_notes"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
            $('#leaksTable').DataTable({
                order: [[8, 'desc']],
                pageLength: 25
            });
        });

        function assignTechnician(report_id) {
            document.getElementById('assign_report_id').value = report_id;
            new bootstrap.Modal(document.getElementById('assignModal')).show();
        }

        function updateStatus(report_id, current_status) {
            document.getElementById('update_report_id').value = report_id;
            document.getElementById('update_status').value = current_status;
            new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
        }

        function viewDetails(report) {
            document.getElementById('view_id').textContent = '#' + report.report_id;
            document.getElementById('view_priority').textContent = report.priority.charAt(0).toUpperCase() + report.priority.slice(1);
            document.getElementById('view_status').textContent = report.report_status.replace('_', ' ');
            document.getElementById('view_reporter').textContent = report.reporter_name;
            document.getElementById('view_phone').textContent = report.reporter_phone || 'N/A';
            document.getElementById('view_location').textContent = report.location_description;
            document.getElementById('view_meter').textContent = report.meter_number || 'N/A';
            document.getElementById('view_reported_at').textContent = new Date(report.reported_at).toLocaleString();
            document.getElementById('view_technician').textContent = report.technician_name || 'Not assigned';
            document.getElementById('view_resolved_at').textContent = report.resolved_at ? new Date(report.resolved_at).toLocaleString() : 'Not resolved';
            document.getElementById('view_description').textContent = report.leak_description;
            
            if (report.resolution_notes) {
                document.getElementById('view_notes').textContent = report.resolution_notes;
                document.getElementById('view_notes_row').style.display = 'flex';
            } else {
                document.getElementById('view_notes_row').style.display = 'none';
            }
            
            new bootstrap.Modal(document.getElementById('viewModal')).show();
        }
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
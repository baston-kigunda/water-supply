<?php
// ========================================
// FILE: user-report-leak.php
// PURPOSE: Report water leaks and track progress
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

// Handle leak report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_leak'])) {
    $meter_id = $_POST['meter_id'] ?: null;
    $location = $_POST['location'];
    $description = $_POST['description'];
    $priority = $_POST['priority'];
    
    // Validate
    if (empty($location) || empty($description)) {
        $error = "Please fill in all required fields.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO leak_reports (user_id, meter_id, location_description, leak_description, priority) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisss", $user_id, $meter_id, $location, $description, $priority);
        
        if ($stmt->execute()) {
            $report_id = $conn->insert_id;
            
            // Create notification
            createNotification(
                $user_id,
                "Leak Report Submitted",
                "Your leak report #$report_id has been submitted successfully. We'll assign a technician shortly.",
                'leak'
            );
            
            // Notify admin/staff (simplified - you could notify all staff)
            $staff = $conn->query("SELECT user_id FROM users WHERE user_role IN ('admin', 'staff')");
            while ($staff_user = $staff->fetch_assoc()) {
                createNotification(
                    $staff_user['user_id'],
                    "New Leak Report",
                    "A new leak report has been submitted with $priority priority.",
                    'leak'
                );
            }
            
            $message = "Leak report submitted successfully! Report ID: #$report_id";
        } else {
            $error = "Error submitting report: " . $conn->error;
        }
    }
}

// Get user's meters for dropdown
$meters = $conn->query("
    SELECT meter_id, meter_number, location 
    FROM smart_meters 
    WHERE user_id = $user_id AND meter_status = 'active'
");

// Get user's recent leak reports
$reports = $conn->query("
    SELECT lr.*, 
           u.full_name as technician_name,
           DATEDIFF(NOW(), lr.reported_at) as days_ago
    FROM leak_reports lr
    LEFT JOIN users u ON lr.assigned_to = u.user_id
    WHERE lr.user_id = $user_id
    ORDER BY lr.reported_at DESC
    LIMIT 10
");

$page_title = "Report a Leak";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report a Leak - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
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
                            <a class="nav-link" href="user-payments.php">
                                <i class="bi bi-cash-stack"></i> Payments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="user-report-leak.php">
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
                    <h1 class="h2">Report a Water Leak</h1>
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

                <div class="row">
                    <!-- Report Form -->
                    <div class="col-md-6 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Report a Leak</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" onsubmit="return validateLeakReport()">
                                    <div class="mb-3">
                                        <label for="meter_id" class="form-label">Associated Meter (Optional)</label>
                                        <select class="form-control" id="meter_id" name="meter_id">
                                            <option value="">-- Not associated with a meter --</option>
                                            <?php while ($meter = $meters->fetch_assoc()): ?>
                                                <option value="<?php echo $meter['meter_id']; ?>">
                                                    <?php echo $meter['meter_number']; ?> - <?php echo htmlspecialchars($meter['location']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="location" class="form-label">Exact Location <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="location" name="location" 
                                               placeholder="e.g., Outside near the main pipe, Kitchen, Bathroom" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="priority" class="form-label">Priority <span class="text-danger">*</span></label>
                                        <select class="form-control" id="priority" name="priority" required>
                                            <option value="low">Low - Minor drip, can wait</option>
                                            <option value="medium" selected>Medium - Steady flow, needs attention</option>
                                            <option value="high">High - Significant water loss</option>
                                            <option value="emergency">Emergency - Flooding, major burst</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="description" name="description" rows="4" 
                                                  placeholder="Please describe the leak in detail. How long has it been happening? How severe is it?" required></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Upload Photo (Optional)</label>
                                        <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                                        <small class="text-muted">You can take a photo of the leak for better assessment</small>
                                    </div>
                                    
                                    <div class="alert alert-warning">
                                        <i class="bi bi-info-circle"></i>
                                        <strong>Note:</strong> For emergency leaks (burst pipes, flooding), please also call our emergency line: <strong>+254 700 000 000</strong>
                                    </div>
                                    
                                    <button type="submit" name="report_leak" class="btn btn-danger w-100">
                                        <i class="bi bi-send"></i> Submit Leak Report
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Emergency Contact Card -->
                        <div class="card dashboard-card mt-4 bg-danger text-white">
                            <div class="card-body">
                                <h5><i class="bi bi-telephone"></i> Emergency Contact</h5>
                                <p class="mb-1">24/7 Emergency Line:</p>
                                <h3>+254 700 000 000</h3>
                                <p class="mb-0 small">For immediate assistance with major leaks</p>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Reports -->
                    <div class="col-md-6 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Your Recent Reports</h5>
                            </div>
                            <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                                <?php if ($reports->num_rows > 0): ?>
                                    <?php while ($report = $reports->fetch_assoc()): ?>
                                        <div class="border rounded p-3 mb-3 <?php 
                                            echo $report['priority'] == 'emergency' ? 'border-danger' : 
                                                ($report['priority'] == 'high' ? 'border-warning' : ''); 
                                        ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <span class="badge bg-<?php 
                                                        echo $report['priority'] == 'emergency' ? 'danger' : 
                                                            ($report['priority'] == 'high' ? 'warning' : 
                                                            ($report['priority'] == 'medium' ? 'info' : 'secondary')); 
                                                    ?> mb-2">
                                                        <?php echo ucfirst($report['priority']); ?>
                                                    </span>
                                                    <span class="badge bg-<?php 
                                                        echo $report['report_status'] == 'resolved' ? 'success' : 
                                                            ($report['report_status'] == 'assigned' ? 'primary' : 
                                                            ($report['report_status'] == 'in_progress' ? 'warning' : 
                                                            ($report['report_status'] == 'pending' ? 'danger' : 'secondary'))); 
                                                    ?> mb-2">
                                                        <?php echo ucfirst(str_replace('_', ' ', $report['report_status'])); ?>
                                                    </span>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo $report['days_ago']; ?> days ago
                                                </small>
                                            </div>
                                            
                                            <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($report['location_description']); ?></p>
                                            <p class="mb-2"><strong>Description:</strong> <?php echo htmlspecialchars(substr($report['leak_description'], 0, 100)) . '...'; ?></p>
                                            
                                            <?php if ($report['technician_name']): ?>
                                                <div class="alert alert-success py-1 px-2 mb-0">
                                                    <small>
                                                        <i class="bi bi-person-check"></i>
                                                        Assigned to: <?php echo $report['technician_name']; ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($report['resolution_notes']): ?>
                                                <div class="alert alert-info py-1 px-2 mb-0 mt-2">
                                                    <small>
                                                        <i class="bi bi-check-circle"></i>
                                                        Resolution: <?php echo $report['resolution_notes']; ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-sm btn-link p-0 mt-2" onclick="viewReportDetails(<?php echo $report['report_id']; ?>)">
                                                View Details
                                            </button>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle"></i>
                                        You haven't submitted any leak reports yet.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Tips Card -->
                        <div class="card dashboard-card mt-4 bg-info text-white">
                            <div class="card-body">
                                <h5><i class="bi bi-lightbulb"></i> Leak Prevention Tips</h5>
                                <ul class="mb-0 small">
                                    <li>Check taps regularly for drips</li>
                                    <li>Inspect exposed pipes for corrosion</li>
                                    <li>Monitor your water bill for unexplained increases</li>
                                    <li>Know where your main shut-off valve is located</li>
                                    <li>Fix minor leaks immediately before they worsen</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Report Details Modal -->
    <div class="modal fade" id="reportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Leak Report Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="reportDetails">
                    Loading...
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function validateLeakReport() {
            var location = document.getElementById('location').value;
            var description = document.getElementById('description').value;
            var priority = document.getElementById('priority').value;
            
            if (!location || !description) {
                alert('Please fill in all required fields');
                return false;
            }
            
            var confirmMsg = 'Submit this leak report?\n\n';
            confirmMsg += 'Priority: ' + priority.charAt(0).toUpperCase() + priority.slice(1) + '\n';
            confirmMsg += 'Location: ' + location + '\n';
            confirmMsg += 'Description: ' + description.substring(0, 100) + (description.length > 100 ? '...' : '');
            
            return confirm(confirmMsg);
        }

        function viewReportDetails(report_id) {
            $('#reportModal').modal('show');
            $('#reportDetails').html('Loading report details...');
            
            // In a real implementation, you would load details via AJAX
            // For now, we'll just show a placeholder
            $('#reportDetails').html(`
                <div class="text-center">
                    <p>Report #${report_id} details would be displayed here.</p>
                    <p>This would include full description, status history, technician notes, etc.</p>
                </div>
            `);
        }
    </script>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
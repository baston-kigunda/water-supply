<?php
// ========================================
// FILE: user-profile.php
// PURPOSE: View and update user profile
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

// Get current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = $_POST['full_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $address = $_POST['address'];
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } else {
            // Check if email exists for other users
            $check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $check->bind_param("si", $email, $user_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = "Email already in use by another account";
            } else {
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET full_name = ?, email = ?, phone_number = ?, address = ? 
                    WHERE user_id = ?
                ");
                $stmt->bind_param("ssssi", $full_name, $email, $phone, $address, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['user_name'] = $full_name;
                    $message = "Profile updated successfully!";
                    
                    // Refresh user data
                    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                } else {
                    $error = "Error updating profile: " . $conn->error;
                }
            }
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        
        // Verify current password
        if (!password_verify($current, $user['password_hash'])) {
            $error = "Current password is incorrect";
        } elseif (strlen($new) < 6) {
            $error = "New password must be at least 6 characters";
        } elseif ($new !== $confirm) {
            $error = "New passwords do not match";
        } else {
            $new_hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_hash, $user_id);
            
            if ($stmt->execute()) {
                $message = "Password changed successfully!";
            } else {
                $error = "Error changing password: " . $conn->error;
            }
        }
    }
}

// Get user statistics
$stats = [];

// Total consumption
$result = $conn->query("
    SELECT COALESCE(SUM(units_consumed), 0) as total 
    FROM bills 
    WHERE user_id = $user_id
");
$stats['total_consumption'] = $result->fetch_assoc()['total'];

// Total payments
$result = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM payments 
    WHERE user_id = $user_id AND payment_status = 'completed'
");
$stats['total_paid'] = $result->fetch_assoc()['total'];

// Member since
$stats['member_since'] = date('F Y', strtotime($user['created_at']));

// Last login
$stats['last_login'] = $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never';

$page_title = "My Profile";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo SITE_NAME; ?></title>
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
                            <a class="nav-link active" href="user-profile.php">
                                <i class="bi bi-person"></i> Profile
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">My Profile</h1>
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
                    <!-- Profile Information -->
                    <div class="col-md-4 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-person-circle"></i> Profile Summary</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="display-1 text-primary mb-3">
                                    <i class="bi bi-person-circle"></i>
                                </div>
                                <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                                <p class="text-muted">@<?php echo $user['username']; ?></p>
                                
                                <hr>
                                
                                <div class="text-start">
                                    <p><i class="bi bi-envelope"></i> <?php echo $user['email']; ?></p>
                                    <p><i class="bi bi-telephone"></i> <?php echo $user['phone_number'] ?? 'Not provided'; ?></p>
                                    <p><i class="bi bi-geo-alt"></i> <?php echo $user['address'] ?? 'Not provided'; ?></p>
                                    <p><i class="bi bi-calendar"></i> Member since: <?php echo $stats['member_since']; ?></p>
                                    <p><i class="bi bi-clock-history"></i> Last login: <?php echo $stats['last_login']; ?></p>
                                </div>
                                
                                <hr>
                                
                                <div class="row">
                                    <div class="col-6">
                                        <h5><?php echo number_format($stats['total_consumption'], 2); ?></h5>
                                        <small>Total m³</small>
                                    </div>
                                    <div class="col-6">
                                        <h5><?php echo formatCurrency($stats['total_paid']); ?></h5>
                                        <small>Total Paid</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Profile Form -->
                    <div class="col-md-8 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Edit Profile</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="full_name" class="form-label">Full Name</label>
                                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                                   value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="username" class="form-label">Username</label>
                                            <input type="text" class="form-control" id="username" value="<?php echo $user['username']; ?>" disabled>
                                            <small class="text-muted">Username cannot be changed</small>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo $user['email']; ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" id="phone" name="phone" 
                                                   value="<?php echo $user['phone_number']; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo $user['address']; ?></textarea>
                                    </div>
                                    
                                    <button type="submit" name="update_profile" class="btn btn-success">
                                        <i class="bi bi-save"></i> Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Change Password Form -->
                        <div class="card dashboard-card mt-4">
                            <div class="card-header bg-warning">
                                <h5 class="mb-0"><i class="bi bi-key"></i> Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="new_password" class="form-label">New Password</label>
                                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                                            <small class="text-muted">Minimum 6 characters</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" name="change_password" class="btn btn-warning">
                                        <i class="bi bi-key"></i> Change Password
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Account Information -->
                        <div class="card dashboard-card mt-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Account Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Account Status:</strong> 
                                            <span class="badge bg-success"><?php echo ucfirst($user['account_status']); ?></span>
                                        </p>
                                        <p><strong>Account Type:</strong> 
                                            <span class="badge bg-primary"><?php echo ucfirst($user['user_role']); ?></span>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Account Created:</strong> <?php echo date('F d, Y', strtotime($user['created_at'])); ?></p>
                                        <p><strong>Last Updated:</strong> <?php echo date('F d, Y', strtotime($user['updated_at'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
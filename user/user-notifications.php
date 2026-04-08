<?php
// ========================================
// FILE: user-notifications.php
// PURPOSE: View and manage all notifications
// ========================================

require_once '../includes/functions.php';
require_once '../config/database.php';

requireLogin();

if (isAdmin()) {
    header('Location: ../admin/admin-dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle mark as read
if (isset($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    markNotificationAsRead($notification_id, $user_id);
    header('Location: user-notifications.php');
    exit();
}

// Handle mark all as read
if (isset($_GET['mark_all_read'])) {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
    header('Location: user-notifications.php');
    exit();
}

// Handle delete notification
if (isset($_GET['delete'])) {
    $notification_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    header('Location: user-notifications.php');
    exit();
}

// Handle clear all
if (isset($_GET['clear_all'])) {
    $conn->query("DELETE FROM notifications WHERE user_id = $user_id");
    header('Location: user-notifications.php');
    exit();
}

// Get filter
$type_filter = $_GET['type'] ?? 'all';

// Build query
$query = "SELECT * FROM notifications WHERE user_id = $user_id";
if ($type_filter != 'all') {
    $query .= " AND notification_type = '$type_filter'";
}
$query .= " ORDER BY created_at DESC";

$notifications = $conn->query($query);

// Get counts by type
$counts = [];
$result = $conn->query("
    SELECT notification_type, COUNT(*) as count, 
           SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread
    FROM notifications 
    WHERE user_id = $user_id 
    GROUP BY notification_type
");
while ($row = $result->fetch_assoc()) {
    $counts[$row['notification_type']] = $row;
}

// Total unread
$total_unread = $conn->query("
    SELECT COUNT(*) as count FROM notifications 
    WHERE user_id = $user_id AND is_read = 0
")->fetch_assoc()['count'];

$page_title = "Notifications";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - <?php echo SITE_NAME; ?></title>
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
                            <a class="nav-link active" href="user-notifications.php">
                                <i class="bi bi-bell"></i> Notifications
                                <?php if ($total_unread > 0): ?>
                                    <span class="badge bg-danger"><?php echo $total_unread; ?></span>
                                <?php endif; ?>
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
                    <h1 class="h2">Notifications</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($notifications->num_rows > 0): ?>
                            <a href="?mark_all_read=1" class="btn btn-sm btn-success me-2">
                                <i class="bi bi-check-all"></i> Mark All Read
                            </a>
                            <a href="?clear_all=1" class="btn btn-sm btn-danger" onclick="return confirm('Clear all notifications?')">
                                <i class="bi bi-trash"></i> Clear All
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Filter Tabs -->
                <ul class="nav nav-tabs mb-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $type_filter == 'all' ? 'active' : ''; ?>" href="?type=all">
                            All
                            <?php if ($total_unread > 0): ?>
                                <span class="badge bg-danger"><?php echo $total_unread; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $type_filter == 'bill' ? 'active' : ''; ?>" href="?type=bill">
                            Bills
                            <?php if (isset($counts['bill']['unread']) && $counts['bill']['unread'] > 0): ?>
                                <span class="badge bg-danger"><?php echo $counts['bill']['unread']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $type_filter == 'payment' ? 'active' : ''; ?>" href="?type=payment">
                            Payments
                            <?php if (isset($counts['payment']['unread']) && $counts['payment']['unread'] > 0): ?>
                                <span class="badge bg-danger"><?php echo $counts['payment']['unread']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $type_filter == 'leak' ? 'active' : ''; ?>" href="?type=leak">
                            Leaks
                            <?php if (isset($counts['leak']['unread']) && $counts['leak']['unread'] > 0): ?>
                                <span class="badge bg-danger"><?php echo $counts['leak']['unread']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $type_filter == 'supply' ? 'active' : ''; ?>" href="?type=supply">
                            Water Supply
                            <?php if (isset($counts['supply']['unread']) && $counts['supply']['unread'] > 0): ?>
                                <span class="badge bg-danger"><?php echo $counts['supply']['unread']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $type_filter == 'system' ? 'active' : ''; ?>" href="?type=system">
                            System
                            <?php if (isset($counts['system']['unread']) && $counts['system']['unread'] > 0): ?>
                                <span class="badge bg-danger"><?php echo $counts['system']['unread']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>

                <!-- Notifications List -->
                <div class="card dashboard-card">
                    <div class="card-body">
                        <?php if ($notifications->num_rows > 0): ?>
                            <div class="list-group">
                                <?php while ($notif = $notifications->fetch_assoc()): ?>
                                    <div class="list-group-item list-group-item-action <?php echo $notif['is_read'] ? '' : 'list-group-item-primary'; ?>">
                                        <div class="d-flex w-100 justify-content-between align-items-start">
                                            <div class="d-flex">
                                                <div class="me-3">
                                                    <?php
                                                    $icon = 'bell';
                                                    $color = 'primary';
                                                    if ($notif['notification_type'] == 'bill') {
                                                        $icon = 'receipt';
                                                        $color = 'warning';
                                                    } elseif ($notif['notification_type'] == 'leak') {
                                                        $icon = 'exclamation-triangle';
                                                        $color = 'danger';
                                                    } elseif ($notif['notification_type'] == 'payment') {
                                                        $icon = 'cash-stack';
                                                        $color = 'success';
                                                    } elseif ($notif['notification_type'] == 'supply') {
                                                        $icon = 'water';
                                                        $color = 'info';
                                                    }
                                                    ?>
                                                    <div class="rounded-circle bg-<?php echo $color; ?> bg-opacity-10 p-3">
                                                        <i class="bi bi-<?php echo $icon; ?> text-<?php echo $color; ?> fs-4"></i>
                                                    </div>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1 <?php echo $notif['is_read'] ? '' : 'fw-bold'; ?>">
                                                        <?php echo $notif['title']; ?>
                                                        <?php if (!$notif['is_read']): ?>
                                                            <span class="badge bg-primary ms-2">New</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <p class="mb-1"><?php echo nl2br($notif['message']); ?></p>
                                                    <small class="text-muted">
                                                        <i class="bi bi-clock"></i> 
                                                        <?php echo date('M d, Y H:i', strtotime($notif['created_at'])); ?>
                                                        (<?php 
                                                            $diff = time() - strtotime($notif['created_at']);
                                                            if ($diff < 60) echo 'Just now';
                                                            elseif ($diff < 3600) echo floor($diff/60) . ' minutes ago';
                                                            elseif ($diff < 86400) echo floor($diff/3600) . ' hours ago';
                                                            else echo floor($diff/86400) . ' days ago';
                                                        ?>)
                                                    </small>
                                                </div>
                                            </div>
                                            <div>
                                                <?php if (!$notif['is_read']): ?>
                                                    <a href="?mark_read=<?php echo $notif['notification_id']; ?>" class="btn btn-sm btn-outline-success" title="Mark as read">
                                                        <i class="bi bi-check"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="?delete=<?php echo $notif['notification_id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this notification?')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                        
                                        <!-- Notification Type Badge -->
                                        <div class="mt-2">
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst($notif['notification_type']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-bell-slash fs-1 text-muted"></i>
                                <h5 class="mt-3 text-muted">No notifications found</h5>
                                <p class="text-muted">You're all caught up!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notification Settings Card -->
                <div class="card dashboard-card mt-4">
                    <div class="card-header">
                        <h5><i class="bi bi-gear"></i> Notification Settings</h5>
                    </div>
                    <div class="card-body">
                        <form id="settingsForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Email Notifications</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="email_bills" checked>
                                        <label class="form-check-label" for="email_bills">
                                            Bill reminders
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="email_payments" checked>
                                        <label class="form-check-label" for="email_payments">
                                            Payment confirmations
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="email_leaks" checked>
                                        <label class="form-check-label" for="email_leaks">
                                            Leak alerts
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="email_supply" checked>
                                        <label class="form-check-label" for="email_supply">
                                            Supply schedules
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>SMS Notifications</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="sms_bills">
                                        <label class="form-check-label" for="sms_bills">
                                            Bill reminders
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="sms_payments">
                                        <label class="form-check-label" for="sms_payments">
                                            Payment confirmations
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="sms_leaks" checked>
                                        <label class="form-check-label" for="sms_leaks">
                                            Emergency leak alerts
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="sms_supply">
                                        <label class="form-check-label" for="sms_supply">
                                            Supply schedules
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary mt-3" onclick="alert('Settings saved (demo only)')">
                                Save Settings
                            </button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
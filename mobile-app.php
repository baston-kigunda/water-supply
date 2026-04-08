<?php
// ========================================
// FILE: mobile-app.php
// PURPOSE: Mobile-optimized PWA interface
// ========================================

require_once 'includes/functions.php';
require_once 'config/database.php';

// Check if user is logged in
$is_logged_in = isLoggedIn();
$user = null;

if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $result = mysqli_query($conn, "SELECT * FROM users WHERE user_id = $user_id");
    $user = mysqli_fetch_assoc($result);
    
    // Get user statistics
    $stats = [];
    
    // Total consumption
    $cons = mysqli_query($conn, "SELECT COALESCE(SUM(units_consumed), 0) as total FROM bills WHERE user_id = $user_id");
    $stats['total_consumption'] = mysqli_fetch_assoc($cons)['total'];
    
    // Outstanding balance
    $balance = mysqli_query($conn, "SELECT COALESCE(SUM(total_amount), 0) as total FROM bills WHERE user_id = $user_id AND bill_status IN ('pending', 'overdue')");
    $stats['outstanding'] = mysqli_fetch_assoc($balance)['total'];
    
    // Pending bills count
    $pending = mysqli_query($conn, "SELECT COUNT(*) as count FROM bills WHERE user_id = $user_id AND bill_status = 'pending'");
    $stats['pending_bills'] = mysqli_fetch_assoc($pending)['count'];
    
    // Recent notifications
    $notifications = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 5");
    
    // Get user's meters
    $meters = mysqli_query($conn, "SELECT * FROM smart_meters WHERE user_id = $user_id");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#007bff">
    <link rel="manifest" href="manifest.json">
    <title>Smart Water - Mobile App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f5f7fb;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            padding-bottom: 70px;
        }
        
        /* Header */
        .app-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 20px 16px;
            border-radius: 0 0 20px 20px;
        }
        
        .app-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }
        
        .app-header p {
            font-size: 14px;
            opacity: 0.9;
            margin: 5px 0 0;
        }
        
        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        
        .stat-card:active {
            transform: scale(0.98);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }
        
        .stat-label {
            font-size: 12px;
            color: #6c757d;
            margin: 0;
        }
        
        /* Quick Actions */
        .action-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .action-btn {
            background: white;
            border: none;
            border-radius: 16px;
            padding: 12px 8px;
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .action-btn:active {
            transform: scale(0.95);
            background: #f0f0f0;
        }
        
        .action-btn i {
            font-size: 24px;
            display: block;
            margin-bottom: 6px;
        }
        
        .action-btn span {
            font-size: 12px;
            display: block;
        }
        
        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            display: flex;
            justify-content: space-around;
            padding: 10px 16px 20px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
            border-top: 1px solid #e9ecef;
        }
        
        .nav-item {
            text-align: center;
            text-decoration: none;
            color: #6c757d;
            font-size: 12px;
            transition: color 0.2s;
        }
        
        .nav-item i {
            font-size: 22px;
            display: block;
            margin-bottom: 4px;
        }
        
        .nav-item.active {
            color: #007bff;
        }
        
        .nav-item:active {
            transform: scale(0.95);
        }
        
        /* Notification Item */
        .notification-item {
            background: white;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 8px;
            border-left: 4px solid #007bff;
        }
        
        /* Meter Card */
        .meter-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
        }
        
        /* Pull to Refresh */
        .refresh-indicator {
            text-align: center;
            padding: 10px;
            color: #6c757d;
            font-size: 12px;
        }
        
        /* Install Banner */
        .install-banner {
            background: #e9ecef;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        @media (max-width: 480px) {
            .action-btn span {
                font-size: 10px;
            }
            .stat-value {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>

<?php if ($is_logged_in && $user): ?>
    <!-- App Header -->
    <div class="app-header">
        <h1>Hello, <?php echo htmlspecialchars($user['full_name']); ?>!</h1>
        <p>Welcome to Smart Water Management</p>
    </div>
    
    <div class="container py-3">
        <!-- Install Banner (shows if not installed) -->
        <div id="installBanner" class="install-banner" style="display: none;">
            <div>
                <strong>Install App</strong><br>
                <small>Install for better experience</small>
            </div>
            <button id="installBtn" class="btn btn-sm btn-primary">Install</button>
        </div>
        
        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stat-value"><?php echo number_format($stats['total_consumption'], 2); ?> m³</p>
                            <p class="stat-label">Total Consumption</p>
                        </div>
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stat-value text-danger">KSh <?php echo number_format($stats['outstanding'], 2); ?></p>
                            <p class="stat-label">Outstanding Balance</p>
                        </div>
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                            <i class="fas fa-credit-card"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="action-grid">
            <a href="user/user-consumption.php" class="action-btn">
                <i class="fas fa-chart-line text-primary"></i>
                <span>Consumption</span>
            </a>
            <a href="user/user-bills.php" class="action-btn">
                <i class="fas fa-receipt text-warning"></i>
                <span>Bills</span>
            </a>
            <a href="user/user-payments.php" class="action-btn">
                <i class="fas fa-cash-stack text-success"></i>
                <span>Pay Bill</span>
            </a>
            <a href="user/user-report-leak.php" class="action-btn">
                <i class="fas fa-exclamation-triangle text-danger"></i>
                <span>Report Leak</span>
            </a>
        </div>
        
        <!-- My Meters -->
        <?php if (mysqli_num_rows($meters) > 0): ?>
        <div class="mb-4">
            <h6 class="fw-bold mb-3"><i class="fas fa-water me-2"></i>My Meters</h6>
            <?php while ($meter = mysqli_fetch_assoc($meters)): ?>
                <div class="meter-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?php echo $meter['meter_number']; ?></strong>
                            <p class="mb-0 small text-muted"><?php echo htmlspecialchars($meter['location']); ?></p>
                        </div>
                        <span class="badge bg-success">Active</span>
                    </div>
                    <hr class="my-2">
                    <div class="row text-center">
                        <div class="col-6">
                            <small class="text-muted">Last Reading</small>
                            <p class="mb-0 fw-bold"><?php echo number_format($meter['last_reading'], 2); ?> m³</p>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Last Update</small>
                            <p class="mb-0 small"><?php echo $meter['last_reading_time'] ? date('M d, H:i', strtotime($meter['last_reading_time'])) : 'No data'; ?></p>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
        
        <!-- Recent Notifications -->
        <div>
            <h6 class="fw-bold mb-3"><i class="fas fa-bell me-2"></i>Recent Notifications</h6>
            <?php if (mysqli_num_rows($notifications) > 0): ?>
                <?php while ($notif = mysqli_fetch_assoc($notifications)): ?>
                    <div class="notification-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong><?php echo $notif['title']; ?></strong>
                                <p class="mb-0 small"><?php echo substr($notif['message'], 0, 80); ?>...</p>
                            </div>
                            <small class="text-muted"><?php echo date('H:i', strtotime($notif['created_at'])); ?></small>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-bell-slash fa-2x mb-2"></i>
                    <p>No notifications</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
<?php else: ?>
    <!-- Guest View - Login Prompt -->
    <div class="app-header text-center">
        <i class="fas fa-tint fa-3x mb-3"></i>
        <h1>Smart Water</h1>
        <p>Manage your water consumption easily</p>
    </div>
    
    <div class="container py-5">
        <div class="text-center">
            <a href="login.php" class="btn btn-primary btn-lg w-100 mb-3">Login</a>
            <a href="register.php" class="btn btn-outline-primary btn-lg w-100">Create Account</a>
        </div>
    </div>
<?php endif; ?>

<!-- Bottom Navigation -->
<div class="bottom-nav">
    <a href="mobile-app.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'mobile-app.php' ? 'active' : ''; ?>">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
    <a href="<?php echo $is_logged_in ? 'user/user-consumption.php' : 'login.php'; ?>" class="nav-item">
        <i class="fas fa-chart-line"></i>
        <span>Usage</span>
    </a>
    <a href="<?php echo $is_logged_in ? 'user/user-bills.php' : 'login.php'; ?>" class="nav-item">
        <i class="fas fa-receipt"></i>
        <span>Bills</span>
    </a>
    <a href="<?php echo $is_logged_in ? 'user/user-profile.php' : 'login.php'; ?>" class="nav-item">
        <i class="fas fa-user"></i>
        <span>Profile</span>
    </a>
</div>

<script>
    // PWA Installation
    let deferredPrompt;
    
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        document.getElementById('installBanner').style.display = 'flex';
    });
    
    document.getElementById('installBtn')?.addEventListener('click', async () => {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            if (outcome === 'accepted') {
                document.getElementById('installBanner').style.display = 'none';
            }
            deferredPrompt = null;
        }
    });
    
    // Register Service Worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js')
            .then(reg => console.log('Service Worker registered', reg))
            .catch(err => console.log('Service Worker failed', err));
    }
    
    // Request Notification Permission
    if ('Notification' in window && Notification.permission !== 'granted') {
        setTimeout(() => {
            Notification.requestPermission();
        }, 5000);
    }
    
    // Pull to Refresh
    let startY = 0;
    let refreshing = false;
    
    document.body.addEventListener('touchstart', (e) => {
        if (window.scrollY === 0) {
            startY = e.touches[0].pageY;
        }
    });
    
    document.body.addEventListener('touchmove', (e) => {
        if (window.scrollY === 0 && !refreshing) {
            const moveY = e.touches[0].pageY;
            const diff = moveY - startY;
            if (diff > 80) {
                refreshing = true;
                window.location.reload();
            }
        }
    });
    
    // Offline indicator
    window.addEventListener('offline', () => {
        const banner = document.createElement('div');
        banner.className = 'alert alert-warning text-center fixed-top mt-2';
        banner.style.margin = '0 10px';
        banner.innerHTML = '<i class="fas fa-wifi"></i> You are offline. Some features may be limited.';
        document.body.prepend(banner);
        setTimeout(() => banner.remove(), 3000);
    });
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>
<!-- ========================================
     FILE: index.php
     PURPOSE: Landing page / homepage
     ======================================== -->
<?php
require_once 'includes/functions.php';

// Redirect to dashboard if logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin/admin-dashboard.php');
    } else {
        header('Location: user/user-dashboard.php');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container mt-5">
            <div class="row">
                <div class="col-md-12 text-center">
                    <h1>Welcome to Smart Water Supply Management System</h1>
                    <p class="lead">Efficient, reliable, and transparent water distribution for your community</p>
                </div>
            </div>
            
            <div class="row mt-5">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title">Smart Metering</h5>
                            <p class="card-text">Real-time water consumption monitoring with smart meters</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title">Digital Payments</h5>
                            <p class="card-text">Pay your water bills easily via M-Pesa and other methods</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title">Leak Detection</h5>
                            <p class="card-text">Automated leak detection and instant reporting</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-md-12 text-center">
                    <a href="login.php" class="btn btn-primary btn-lg">Login</a>
                    <a href="register.php" class="btn btn-success btn-lg">Register</a>
                </div>
            </div>
        </div>
    </main>
    <?php include 'includes/footer.php';?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
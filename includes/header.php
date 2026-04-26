<?php

// ========================================
// FILE: header.php
// PURPOSE: Website header with navigation
// ========================================

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>/index.php">
                <i class="bi bi-water"></i> <?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <!-- User Dropdown with Profile and Logout Together -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> <?php echo $_SESSION['user_name'] ?? 'User'; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="user-profile.php">
                                        <i class="bi bi-person"></i> My Profile
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <!-- FIXED: This path WILL work -->
                                    <a class="dropdown-item text-danger" href="../logout.php">
                                        <i class="bi bi-box-arrow-right"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo SITE_URL; ?>/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo SITE_URL; ?>/register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <?php if (isset($_SESSION['user_id']) && defined('IOT_SIMULATION_ENABLED') && IOT_SIMULATION_ENABLED): ?>
        <script>
            (function () {
                const simulationEndpoint = <?php echo json_encode(rtrim(SITE_URL, '/') . '/api/api-simulator.php'); ?>;
                const intervalMs = <?php echo (int) IOT_SIMULATION_INTERVAL_SECONDS * 1000; ?>;
                let requestInFlight = false;

                function pingMeterSimulation() {
                    if (document.visibilityState === 'hidden' || requestInFlight) {
                        return;
                    }

                    requestInFlight = true;
                    fetch(simulationEndpoint, {
                        method: 'GET',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).catch(function () {
                        return null;
                    }).finally(function () {
                        requestInFlight = false;
                    });
                }

                window.setTimeout(pingMeterSimulation, 1500);
                window.setInterval(pingMeterSimulation, intervalMs);
            })();
        </script>
    <?php endif; ?>

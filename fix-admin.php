<?php
// ========================================
// FILE: fix-admin-now.php
// PURPOSE: Fix admin login instantly with WORKING password
// ========================================

require_once 'config/database.php';

// The ACTUAL password you will use
$password = "Admin123";

// Generate the hash automatically
$hash = password_hash($password, PASSWORD_DEFAULT);

// Check if admin exists
$check = mysqli_query($conn, "SELECT * FROM users WHERE email = 'admin@watersupply.com'");

if (mysqli_num_rows($check) > 0) {
    // Update existing admin
    $query = "UPDATE users SET password_hash = '$hash' WHERE email = 'admin@watersupply.com'";
    $action = "updated";
} else {
    // Create new admin
    $query = "INSERT INTO users (username, email, password_hash, full_name, user_role) 
              VALUES ('admin', 'admin@watersupply.com', '$hash', 'System Administrator', 'admin')";
    $action = "created";
}

if (mysqli_query($conn, $query)) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Admin Login Fixed</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
        <style>
            body { background: #f4f6f9; padding: 50px; }
            .success-box { 
                background: white; 
                border-radius: 10px; 
                padding: 30px; 
                max-width: 500px; 
                margin: 0 auto;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
                border-left: 5px solid #28a745;
            }
            .password-box {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                font-size: 24px;
                text-align: center;
                margin: 20px 0;
                border: 2px dashed #28a745;
            }
        </style>
    </head>
    <body>
        <div class='success-box'>
            <h2 class='text-success'>✅ Admin Login Fixed!</h2>
            <p>Admin account has been <strong>$action</strong> successfully.</p>
            
            <div class='password-box'>
                <strong style='font-size: 18px;'>USE THESE CREDENTIALS:</strong><br>
                <span style='font-size: 20px;'>📧 admin@watersupply.com</span><br>
                <span style='font-size: 28px; color: #28a745;'>🔑 Admin123</span>
            </div>
            
            <div class='alert alert-info'>
                <strong>⚠️ IMPORTANT:</strong> Your password is <strong>Admin123</strong> (not hashed)
            </div>
            
            <a href='login.php' class='btn btn-success btn-lg w-100'>Go to Login Page</a>
            
            <hr>
            <p class='text-muted small'>After logging in, DELETE this file: fix-admin-now.php</p>
        </div>
    </body>
    </html>";
} else {
    echo "<div class='alert alert-danger'>Error: " . mysqli_error($conn) . "</div>";
}
?>
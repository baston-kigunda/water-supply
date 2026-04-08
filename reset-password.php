<?php
// ========================================
// FILE: reset-password.php
// PURPOSE: Set new password (WORKING VERSION)
// ========================================

require_once 'includes/functions.php';
require_once 'config/database.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$message = '';
$error = '';
$show_form = false;
$email = '';
$token = '';

// Get parameters from URL
if (isset($_GET['token']) && isset($_GET['email'])) {
    $token = $_GET['token'];
    $email = $_GET['email'];
    
    // Verify token
    $query = "SELECT * FROM users WHERE email = '$email' AND reset_token = '$token' AND reset_expires > NOW()";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) == 1) {
        $show_form = true;
    } else {
        $error = "Invalid or expired reset link. Please request a new one.";
    }
} else {
    $error = "No reset token provided";
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $email = $_POST['email'];
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    
    if ($password !== $confirm) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        // Verify token again
        $check = "SELECT * FROM users WHERE email = '$email' AND reset_token = '$token' AND reset_expires > NOW()";
        $check_result = mysqli_query($conn, $check);
        
        if (mysqli_num_rows($check_result) == 1) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Update password and clear token
            $update = "UPDATE users SET password_hash = '$hash', reset_token = NULL, reset_expires = NULL WHERE email = '$email'";
            
            if (mysqli_query($conn, $update)) {
                header("Location: login.php?reset=success");
                exit();
            } else {
                $error = "Error updating password. Please try again.";
            }
        } else {
            $error = "Invalid or expired reset link";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .reset-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .reset-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
            padding: 40px;
        }
        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .reset-header h2 {
            color: #333;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .reset-header p {
            color: #666;
        }
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 16px;
        }
        .form-control:focus {
            border-color: #28a745;
            box-shadow: none;
        }
        .btn-reset {
            background: #28a745;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: transform 0.3s;
        }
        .btn-reset:hover {
            background: #218838;
            transform: translateY(-2px);
            color: white;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #666;
            text-decoration: none;
        }
        .back-link a:hover {
            color: #28a745;
        }
        .alert {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <h2>Create New Password</h2>
                <p>Enter your new password below</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($show_form): ?>
                <form method="POST" action="" onsubmit="return validateForm()">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" id="password" class="form-control" 
                        placeholder="Enter new password" required>
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" 
                       placeholder="Confirm new password" required>
                    </div>
                    
                    <button type="submit" name="reset_password" class="btn btn-reset">
                        Reset Password
                    </button>
                    
                    <div class="back-link">
                        <a href="login.php">← Back to Login</a>
                    </div>
                </form>
                
                <script>
                    function validateForm() {
                        var pass = document.getElementById('password').value;
                        var confirm = document.getElementById('confirm_password').value;
                        
                        if (pass.length < 6) {
                            alert('Password must be at least 6 characters');
                            return false;
                        }
                        
                        if (pass !== confirm) {
                            alert('Passwords do not match');
                            return false;
                        }
                        
                        return true;
                    }
                </script>
                
            <?php elseif (!$error): ?>
                <div class="alert alert-warning">
                    No reset token provided. Please request a new reset link.
                </div>
                <a href="forgot-password.php" class="btn btn-primary w-100">Request New Link</a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
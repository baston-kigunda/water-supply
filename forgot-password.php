<?php
// ========================================
// FILE: forgot-password.php
// PURPOSE: Request password reset (WORKING VERSION)
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // Check if email exists
    $query = "SELECT * FROM users WHERE email = '$email'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        // Generate token
        $token = md5(time() . $email . rand(100000, 999999));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Save token
        $update = "UPDATE users SET reset_token = '$token', reset_expires = '$expires' WHERE email = '$email'";
        
        if (mysqli_query($conn, $update)) {
            // Create clean link WITHOUT any encoding issues
            $reset_link = "http://localhost/water-supply-management-system/reset-password.php?token=$token&email=$email";
            
            // Simple working message with DIRECT link
            $message = "
                <div class='alert alert-success text-center'>
                    <h5 class='mb-3'>✅ Password Reset Link Generated</h5>
                    <a href='$reset_link' 
                       class='btn btn-success btn-lg mb-3'
                       style='font-size: 18px; padding: 12px 30px; text-decoration: none;'>
                        Click Here to Reset Password
                    </a>
                    <p class='mt-2 mb-0'>
                        <small class='text-muted'>Link will expire in 1 hour</small>
                    </p>
                    <p class='mt-3 mb-0'>
                        <small>Or copy this link:<br>$reset_link</small>
                    </p>
                </div>
            ";
        } else {
            $error = "Database error occurred";
        }
    } else {
        $error = "Email not found in our system";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .forgot-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .forgot-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
            padding: 40px;
        }
        .forgot-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .forgot-header h2 {
            color: #333;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .forgot-header p {
            color: #666;
        }
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 16px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: none;
        }
        .btn-send {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: transform 0.3s;
        }
        .btn-send:hover {
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
            color: #667eea;
        }
        .btn-success {
            background: #28a745;
            border: none;
            padding: 12px 30px;
            font-size: 18px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-success:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="forgot-container">
        <div class="forgot-card">
            <div class="forgot-header">
                <h2>Reset Password</h2>
                <p>Enter your email to receive reset link</p>
            </div>
            
            <?php if ($message): ?>
                <?php echo $message; ?>
                <div class="back-link mt-3">
                    <a href="login.php">← Back to Login</a>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" 
                               placeholder="Enter your email" required>
                    </div>
                    
                    <button type="submit" class="btn btn-send">
                        Send Reset Link
                    </button>
                    
                    <div class="back-link">
                        <a href="login.php">← Back to Login</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>


<!-- ========================================
     FILE: login.php
     PURPOSE: User login page with reset password option
     ======================================== -->
<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $result = loginUser($username, $password);
    
    if ($result['success']) {
        if ($result['role'] === 'admin') {
            header('Location: admin/admin-dashboard.php');
        } else {
            header('Location: user/user-dashboard.php');
        }
        exit();
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .login-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
            padding: 2.5rem;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(145deg, #2c3e50, #3498db);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2.5rem;
            color: white;
        }
        
        .login-header h2 {
            color: #333;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: #666;
            margin: 0;
        }
        
        .form-control {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52,152,219,0.25);
        }
        
        .input-group-text {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px 0 0 10px;
            border-right: none;
        }
        
        .btn-login {
            background: linear-gradient(145deg, #2c3e50, #3498db);
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52,152,219,0.4);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .login-footer a {
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .login-footer a:hover {
            color: #3498db;
            text-decoration: underline;
        }
        
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .divider span {
            padding: 0 1rem;
            color: #666;
            font-size: 0.9rem;
        }
        
        .register-link {
            text-align: center;
            margin-top: 1rem;
        }
        
        .register-link a {
            color: #3498db;
            font-weight: 600;
            text-decoration: none;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        /* Alert styling */
        .alert {
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-icon">
                    <i class="fas fa-tint"></i>
                </div>
                <h2>Welcome Back</h2>
                <p>Sign in to your account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['registered'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Registration successful! Please login.
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['reset'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Password reset successful! Please login with your new password.
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Username or Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" 
                               placeholder="Enter your username or email" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Enter your password" required>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember">
                        <label class="form-check-label" for="remember">
                            Remember me
                        </label>
                    </div>
                    <a href="forgot-password.php" class="text-primary">
                        <i class="fas fa-key"></i> Forgot Password?
                    </a>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
                
                <div class="divider">
                    <span>OR</span>
                </div>
                
                <div class="register-link">
                    Don't have an account? <a href="register.php">Create one now</a>
                </div>
            </form>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
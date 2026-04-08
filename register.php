<!-- ========================================
     FILE: register.php
     PURPOSE: New user registration page with benefits panel
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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'username' => $_POST['username'] ?? '',
        'email' => $_POST['email'] ?? '',
        'password' => $_POST['password'] ?? '',
        'full_name' => $_POST['full_name'] ?? '',
        'phone_number' => $_POST['phone_number'] ?? '',
        'address' => $_POST['address'] ?? ''
    ];
    
    $result = registerUser($data);
    
    if ($result['success']) {
        $success = $result['message'];
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
    <title>Register - <?php echo SITE_NAME; ?></title>
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
        
        .register-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .register-card {
            display: flex;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            max-width: 1100px;
            width: 100%;
        }
        
        /* Benefits Panel - Left Side */
        .benefits-panel {
            flex: 1;
            background: linear-gradient(145deg, #2c3e50 0%, #3498db 100%);
            padding: 3rem 2rem;
            color: white;
            display: flex;
            flex-direction: column;
        }
        
        .benefits-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .benefits-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2.5rem;
        }
        
        .benefits-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .benefits-subtitle {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .benefits-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .benefits-list li {
            display: flex;
            align-items: flex-start;
            margin-bottom: 2rem;
            padding: 1rem;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            transition: transform 0.3s, background 0.3s;
        }
        
        .benefits-list li:hover {
            transform: translateX(5px);
            background: rgba(255,255,255,0.15);
        }
        
        .benefits-list i {
            font-size: 1.8rem;
            margin-right: 1rem;
            color: #ffd700;
        }
        
        .benefit-content h4 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.3rem;
        }
        
        .benefit-content p {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }
        
        /* Form Panel - Right Side */
        .form-panel {
            flex: 1;
            padding: 3rem;
            background: white;
        }
        
        .form-panel h2 {
            color: #333;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .form-panel .lead {
            color: #666;
            margin-bottom: 2rem;
        }
        
        .form-control, .form-select {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52,152,219,0.25);
        }
        
        .btn-register {
            background: linear-gradient(145deg, #2c3e50, #3498db);
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52,152,219,0.4);
        }
        
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #666;
        }
        
        .login-link a {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .register-card {
                flex-direction: column;
            }
            
            .benefits-panel {
                padding: 2rem;
            }
            
            .form-panel {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="register-container">
        <div class="register-card">
            <!-- Left panel - Benefits -->
            <div class="benefits-panel">
                <div class="benefits-header">
                    <div class="benefits-icon">
                        <i class="fas fa-tint"></i>
                    </div>
                    <h2 class="benefits-title">Join Smart Water</h2>
                    <p class="benefits-subtitle">
                        Experience the future of water management
                    </p>
                </div>
                <ul class="benefits-list">
                    <li>
                        <i class="fas fa-chart-line"></i>
                        <div class="benefit-content">
                            <h4>Real-time Monitoring</h4>
                            <p>Track your water consumption 24/7 with smart meters</p>
                        </div>
                    </li>
                    <li>
                        <i class="fas fa-credit-card"></i>
                        <div class="benefit-content">
                            <h4>Digital Payments</h4>
                            <p>Pay bills easily via M-Pesa, cards, or bank transfer</p>
                        </div>
                    </li>
                    <li>
                        <i class="fas fa-exclamation-triangle"></i>
                        <div class="benefit-content">
                            <h4>Instant Leak Alerts</h4>
                            <p>Get notified immediately about potential leaks</p>
                        </div>
                    </li>
                    <li>
                        <i class="fas fa-calendar-check"></i>
                        <div class="benefit-content">
                            <h4>Supply Schedule</h4>
                            <p>Never miss water supply with timely notifications</p>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Right panel - Registration Form -->
            <div class="form-panel">
                <h2>Create Account</h2>
                <p class="lead">Fill in your details to get started</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?> <a href="login.php">Login here</a></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   placeholder="" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                   placeholder="">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2" 
                                  placeholder=""></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="" required>
                        <small class="text-muted">Password must be at least 6 characters long</small>
                    </div>
                    
                    <button type="submit" class="btn btn-register">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                    
                    <div class="login-link">
                        Already have an account? <a href="login.php">Sign in</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
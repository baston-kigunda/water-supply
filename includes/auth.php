<?php
// ========================================
// FILE: auth.php
// PURPOSE: Authentication functions for login/register/logout
// ========================================

require_once __DIR__ . '/functions.php';

function loginUser($username, $password) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT user_id, username, email, password_hash, full_name, user_role, account_status 
        FROM users 
        WHERE (username = ? OR email = ?) AND account_status = 'active'
    ");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password_hash'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['user_role'];
            $_SESSION['login_time'] = time();
            
            // Update last login
            $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $updateStmt->bind_param("i", $user['user_id']);
            $updateStmt->execute();
            
            return ['success' => true, 'role' => $user['user_role']];
        }
    }
    
    return ['success' => false, 'message' => 'Invalid username or password'];
}

function registerUser($data) {
    global $conn;
    
    // Validate input
    if (empty($data['username']) || empty($data['email']) || empty($data['password']) || empty($data['full_name'])) {
        return ['success' => false, 'message' => 'All fields are required'];
    }
    
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email format'];
    }
    
    if (strlen($data['password']) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters'];
    }
    
    // Check if username or email exists
    $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
    $checkStmt->bind_param("ss", $data['username'], $data['email']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        return ['success' => false, 'message' => 'Username or email already exists'];
    }
    
    // Hash password
    $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Insert user
    $stmt = $conn->prepare("
        INSERT INTO users (username, email, password_hash, full_name, phone_number, address, user_role) 
        VALUES (?, ?, ?, ?, ?, ?, 'consumer')
    ");
    $stmt->bind_param("ssssss", $data['username'], $data['email'], $password_hash, $data['full_name'], $data['phone_number'], $data['address']);
    
    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        
        // Create welcome notification
        createNotification(
            $user_id,
            "Welcome to Smart Water Supply System",
            "Thank you for registering. You can now manage your water account online.",
            'system'
        );
        
        return ['success' => true, 'message' => 'Registration successful'];
    }
    
    return ['success' => false, 'message' => 'Registration failed'];
}

function logoutUser() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    return true;
}
?>
<?php
// ========================================
// FILE: api-auth.php
// PURPOSE: Handle API authentication and user login
// ========================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../includes/auth.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'login':
        apiLogin($input);
        break;
        
    case 'register':
        apiRegister($input);
        break;
        
    case 'logout':
        apiLogout($input);
        break;
        
    case 'verify':
        verifyToken($input);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function apiLogin($data) {
    global $conn;
    
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    
    if (!$username || !$password) {
        echo json_encode(['success' => false, 'message' => 'Username and password required']);
        return;
    }
    
    $result = loginUser($username, $password);
    
    if ($result['success']) {
        // Generate API token
        $token = bin2hex(random_bytes(32));
        $user_id = $_SESSION['user_id'];
        
        // Save token
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        mysqli_query($conn, "INSERT INTO api_tokens (user_id, token, expires_at) VALUES ($user_id, '$token', '$expires')");
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user_id' => $user_id,
                'user_name' => $_SESSION['user_name'],
                'user_role' => $_SESSION['user_role'],
                'token' => $token,
                'expires' => $expires
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
}

function apiRegister($data) {
    $user_data = [
        'username' => $data['username'] ?? '',
        'email' => $data['email'] ?? '',
        'password' => $data['password'] ?? '',
        'full_name' => $data['full_name'] ?? '',
        'phone_number' => $data['phone'] ?? '',
        'address' => $data['address'] ?? ''
    ];
    
    $result = registerUser($user_data);
    echo json_encode($result);
}

function apiLogout($data) {
    $token = $data['token'] ?? '';
    
    if ($token) {
        global $conn;
        mysqli_query($conn, "DELETE FROM api_tokens WHERE token = '$token'");
    }
    
    logoutUser();
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
}

function verifyToken($data) {
    global $conn;
    
    $token = $data['token'] ?? '';
    
    if (!$token) {
        echo json_encode(['success' => false, 'message' => 'Token required']);
        return;
    }
    
    $result = mysqli_query($conn, "SELECT * FROM api_tokens WHERE token = '$token' AND expires_at > NOW()");
    
    if (mysqli_num_rows($result) > 0) {
        $token_data = mysqli_fetch_assoc($result);
        
        // Get user data
        $user = mysqli_query($conn, "SELECT user_id, username, email, full_name, user_role FROM users WHERE user_id = {$token_data['user_id']}");
        $user_data = mysqli_fetch_assoc($user);
        
        echo json_encode([
            'success' => true,
            'message' => 'Token valid',
            'data' => $user_data
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
    }
}
?>
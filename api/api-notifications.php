<?php
// ========================================
// FILE: api-notifications.php
// PURPOSE: Send push notifications to mobile apps
// ========================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');

require_once '../config/database.php';
require_once '../includes/functions.php';

define('FCM_API_KEY', 'your-firebase-cloud-messaging-api-key');

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'send':
        sendNotification($input);
        break;
        
    case 'register':
        registerDevice($input);
        break;
        
    case 'get_user':
        getUserNotifications($input);
        break;
        
    case 'mark_read':
        markAsRead($input);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

// Send push notification via FCM
function sendNotification($data) {
    $user_id = $data['user_id'] ?? 0;
    $title = $data['title'] ?? 'Notification';
    $message = $data['message'] ?? '';
    $type = $data['type'] ?? 'system';
    
    if (!$user_id || !$message) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    // Save to database
    createNotification($user_id, $title, $message, $type);
    
    // Get user's device tokens
    global $conn;
    $tokens = mysqli_query($conn, "SELECT device_token FROM user_devices WHERE user_id = $user_id");
    
    $sent_count = 0;
    
    while ($token = mysqli_fetch_assoc($tokens)) {
        // Send to FCM
        $fcm_data = [
            'to' => $token['device_token'],
            'notification' => [
                'title' => $title,
                'body' => $message,
                'sound' => 'default',
                'click_action' => 'FCM_PLUGIN_ACTIVITY'
            ],
            'data' => [
                'type' => $type,
                'user_id' => $user_id,
                'timestamp' => time()
            ]
        ];
        
        $headers = [
            'Authorization: key=' . FCM_API_KEY,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fcm_data));
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        $response = json_decode($result, true);
        if ($response && $response['success'] == 1) {
            $sent_count++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Notification sent to $sent_count devices",
        'data' => [
            'user_id' => $user_id,
            'title' => $title,
            'devices' => $sent_count
        ]
    ]);
}

// Register device for push notifications
function registerDevice($data) {
    global $conn;
    
    $user_id = $data['user_id'] ?? 0;
    $device_token = $data['device_token'] ?? '';
    $device_type = $data['device_type'] ?? 'android'; // android or ios
    
    if (!$user_id || !$device_token) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    // Check if device already registered
    $check = mysqli_query($conn, "SELECT id FROM user_devices WHERE user_id = $user_id AND device_token = '$device_token'");
    
    if (mysqli_num_rows($check) == 0) {
        // Delete old token if exists
        mysqli_query($conn, "DELETE FROM user_devices WHERE device_token = '$device_token'");
        
        // Insert new token
        $insert = mysqli_query($conn, "INSERT INTO user_devices (user_id, device_token, device_type) VALUES ($user_id, '$device_token', '$device_type')");
        
        if ($insert) {
            echo json_encode(['success' => true, 'message' => 'Device registered successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => true, 'message' => 'Device already registered']);
    }
}

// Get user notifications
function getUserNotifications($data) {
    global $conn;
    
    $user_id = $data['user_id'] ?? 0;
    $limit = $data['limit'] ?? 20;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        return;
    }
    
    $result = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC LIMIT $limit");
    
    $notifications = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = [
            'id' => $row['notification_id'],
            'title' => $row['title'],
            'message' => $row['message'],
            'type' => $row['notification_type'],
            'is_read' => (bool)$row['is_read'],
            'created_at' => $row['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $notifications
    ]);
}

// Mark notification as read
function markAsRead($data) {
    global $conn;
    
    $user_id = $data['user_id'] ?? 0;
    $notification_id = $data['notification_id'] ?? 0;
    
    if (!$user_id || !$notification_id) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    $update = mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE notification_id = $notification_id AND user_id = $user_id");
    
    if (mysqli_affected_rows($conn) > 0) {
        echo json_encode(['success' => true, 'message' => 'Marked as read']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Notification not found']);
    }
}
?>
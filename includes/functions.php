<?php
// ========================================
// FILE: functions.php
// PURPOSE: Core utility functions for the entire system
// ========================================

require_once __DIR__ . '/../config/database.php';

// User authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');
}

function isStaff() {
    return (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'staff');
}

function isConsumer() {
    return (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'consumer');
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . SITE_URL . '/user/user-dashboard.php');
        exit();
    }
}

// Notification functions
function createNotification($user_id, $title, $message, $type = 'system') {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, notification_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $title, $message, $type);
    
    return $stmt->execute();
}

function getUserNotifications($user_id, $limit = 10) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

function markNotificationAsRead($notification_id, $user_id) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    
    return $stmt->execute();
}

// Meter reading functions
function recordMeterReading($meter_id, $reading_value, $is_automated = true) {
    global $conn;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert reading
        $stmt = $conn->prepare("INSERT INTO meter_readings (meter_id, reading_value, is_automated) VALUES (?, ?, ?)");
        $stmt->bind_param("idi", $meter_id, $reading_value, $is_automated);
        $stmt->execute();
        
        // Update meter last reading
        $stmt = $conn->prepare("UPDATE smart_meters SET last_reading = ?, last_reading_time = NOW() WHERE meter_id = ?");
        $stmt->bind_param("di", $reading_value, $meter_id);
        $stmt->execute();
        
        // Check for abnormal consumption (potential leak)
        checkForLeaks($meter_id, $reading_value);
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error recording meter reading: " . $e->getMessage());
        return false;
    }
}

function checkForLeaks($meter_id, $current_reading) {
    global $conn;
    
    // Get previous reading from 1 hour ago
    $stmt = $conn->prepare("
        SELECT reading_value FROM meter_readings 
        WHERE meter_id = ? 
        AND reading_time >= NOW() - INTERVAL 1 HOUR 
        ORDER BY reading_time DESC LIMIT 1
    ");
    $stmt->bind_param("i", $meter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $hourly_consumption = $current_reading - $row['reading_value'];
        
        // If consumption > 500 liters per hour, potential leak
        if ($hourly_consumption > 0.5) { // 0.5 cubic meters = 500 liters
            $stmt = $conn->prepare("
                SELECT user_id, location FROM smart_meters WHERE meter_id = ?
            ");
            $stmt->bind_param("i", $meter_id);
            $stmt->execute();
            $meter_result = $stmt->get_result();
            $meter = $meter_result->fetch_assoc();
            
            if ($meter) {
                // Create leak report
                $stmt = $conn->prepare("
                    INSERT INTO leak_reports (user_id, meter_id, location_description, leak_description, priority) 
                    VALUES (?, ?, ?, ?, 'high')
                ");
                $location = $meter['location'] ?? 'Unknown location';
                $description = "Abnormally high water consumption detected: " . $hourly_consumption . " m³ in the last hour";
                $stmt->bind_param("isss", $meter['user_id'], $meter_id, $location, $description);
                $stmt->execute();
                
                // Notify user
                createNotification(
                    $meter['user_id'],
                    "Potential Leak Detected",
                    "Unusually high water consumption detected. Please check for leaks.",
                    'leak'
                );
            }
        }
    }
}

// Billing functions
function generateBill($user_id, $meter_id, $billing_month) {
    global $conn;
    
    // Get meter readings for the month
    $stmt = $conn->prepare("
        SELECT reading_value, reading_time FROM meter_readings 
        WHERE meter_id = ? 
        AND DATE_FORMAT(reading_time, '%Y-%m') = ?
        ORDER BY reading_time ASC
    ");
    $stmt->bind_param("is", $meter_id, $billing_month);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $readings = $result->fetch_all(MYSQLI_ASSOC);
    
    if (count($readings) < 2) {
        return false; // Not enough readings
    }
    
    $first_reading = $readings[0]['reading_value'];
    $last_reading = end($readings)['reading_value'];
    $units_consumed = $last_reading - $first_reading;
    $total_amount = $units_consumed * WATER_RATE_PER_UNIT;
    
    // Check if bill already exists
    $stmt = $conn->prepare("
        SELECT bill_id FROM bills 
        WHERE user_id = ? AND meter_id = ? AND billing_month = ?
    ");
    $stmt->bind_param("iis", $user_id, $meter_id, $billing_month);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing bill
        $stmt = $conn->prepare("
            UPDATE bills 
            SET previous_reading = ?, current_reading = ?, units_consumed = ?, total_amount = ?
            WHERE user_id = ? AND meter_id = ? AND billing_month = ?
        ");
        $stmt->bind_param("dddiiss", $first_reading, $last_reading, $units_consumed, $total_amount, $user_id, $meter_id, $billing_month);
    } else {
        // Create new bill
        $due_date = date('Y-m-d', strtotime('+30 days'));
        $stmt = $conn->prepare("
            INSERT INTO bills (user_id, meter_id, billing_month, previous_reading, current_reading, units_consumed, rate_per_unit, total_amount, due_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisddddds", $user_id, $meter_id, $billing_month, $first_reading, $last_reading, $units_consumed, WATER_RATE_PER_UNIT, $total_amount, $due_date);
    }
    
    if ($stmt->execute()) {
        // Notify user
        createNotification(
            $user_id,
            "New Bill Generated",
            "Your water bill for " . date('F Y', strtotime($billing_month . '-01')) . " is " . CURRENCY . " " . number_format($total_amount, 2),
            'bill'
        );
        return true;
    }
    
    return false;
}

// Format currency
function formatCurrency($amount) {
    return CURRENCY . ' ' . number_format($amount, 2);
}

// Get user's current balance
function getUserBalance($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total_billed,
               COALESCE(SUM(CASE WHEN bill_status = 'paid' THEN total_amount ELSE 0 END), 0) as total_paid
        FROM bills 
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total_billed'] - $row['total_paid'];
}
?>
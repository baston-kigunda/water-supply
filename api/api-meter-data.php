<?php
// ========================================
// FILE: api-meter-data.php
// PURPOSE: Receive real-time data from IoT smart meters
// ========================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../includes/functions.php';

// API Key for authentication (should match what's in your meters)
define('API_KEY', 'your-secret-api-key-here');

// Get authorization header
$headers = getallheaders();
$auth_key = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';

// Verify API key
if ($auth_key !== API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

// If no JSON, try form data
if (!$input) {
    $input = $_POST;
}

// Log received data for debugging
error_log("Meter data received: " . print_r($input, true));

// Validate required fields
if (!isset($input['meter_id']) || !isset($input['reading'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$meter_id = mysqli_real_escape_string($conn, $input['meter_id']);
$reading = floatval($input['reading']);
$timestamp = isset($input['timestamp']) ? $input['timestamp'] : date('Y-m-d H:i:s');
$battery = isset($input['battery']) ? floatval($input['battery']) : null;
$signal = isset($input['signal']) ? intval($input['signal']) : null;

// Check if meter exists
$check = mysqli_query($conn, "SELECT * FROM smart_meters WHERE meter_number = '$meter_id'");

if (mysqli_num_rows($check) == 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Meter not found']);
    exit();
}

$meter = mysqli_fetch_assoc($check);
if (recordMeterReadingAt((int) $meter['meter_id'], $reading, $timestamp, true, true, true)) {
    echo json_encode([
        'success' => true,
        'message' => 'Reading recorded successfully',
        'data' => [
            'meter_id' => $meter_id,
            'reading' => $reading,
            'timestamp' => $timestamp,
            'status' => 'success'
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
}
?>

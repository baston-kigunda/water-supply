<?php
// ========================================
// FILE: api-simulator.php
// PURPOSE: Trigger minute-based simulated IoT readings for assigned meters
// ========================================

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit();
}

$stats = runAutomatedMeterSimulation(true);

echo json_encode([
    'success' => true,
    'message' => 'IoT simulation processed successfully',
    'data' => $stats,
    'server_time' => date('Y-m-d H:i:s')
]);
?>

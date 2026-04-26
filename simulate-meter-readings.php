<?php
// ========================================
// FILE: simulate-meter-readings.php
// PURPOSE: CLI/web runner for simulated IoT meter readings
// ========================================

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$stats = runAutomatedMeterSimulation(true);

if (PHP_SAPI === 'cli') {
    echo "IoT simulation processed at " . date('Y-m-d H:i:s') . PHP_EOL;
    echo "Meters processed: " . ($stats['meters_processed'] ?? 0) . PHP_EOL;
    echo "Readings created: " . ($stats['readings_created'] ?? 0) . PHP_EOL;
    exit(0);
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'IoT simulation processed successfully',
    'data' => $stats,
    'server_time' => date('Y-m-d H:i:s')
]);
?>

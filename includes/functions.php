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
    return recordMeterReadingAt($meter_id, $reading_value, null, $is_automated);
}

function recordMeterReadingAt($meter_id, $reading_value, $reading_time = null, $is_automated = true, $sync_billing = true, $run_leak_check = true) {
    global $conn;

    $reading_time = normalizeMeterReadingTimestamp($reading_time);
    $meter_id = (int) $meter_id;
    $reading_value = (float) $reading_value;
    $is_automated = $is_automated ? 1 : 0;

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("SELECT user_id, last_reading FROM smart_meters WHERE meter_id = ?");
        $stmt->bind_param("i", $meter_id);
        $stmt->execute();
        $meter = $stmt->get_result()->fetch_assoc();

        if (!$meter) {
            throw new Exception("Meter not found.");
        }

        $billing_start_reading = $meter['last_reading'] !== null ? (float) $meter['last_reading'] : (float) $reading_value;
        $reading_value = max($reading_value, $billing_start_reading);

        // Insert reading
        $stmt = $conn->prepare("INSERT INTO meter_readings (meter_id, reading_value, reading_time, is_automated) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("idsi", $meter_id, $reading_value, $reading_time, $is_automated);
        $stmt->execute();
        
        // Update meter last reading
        $stmt = $conn->prepare("UPDATE smart_meters SET last_reading = ?, last_reading_time = ? WHERE meter_id = ?");
        $stmt->bind_param("dsi", $reading_value, $reading_time, $meter_id);
        $stmt->execute();
        
        // Check for abnormal consumption (potential leak)
        if ($run_leak_check) {
            checkForLeaks($meter_id, $reading_value);
        }

        if ($sync_billing && !empty($meter['user_id'])) {
            syncMeterBilling((int) $meter['user_id'], $meter_id, date('Y-m', strtotime($reading_time)), $billing_start_reading);
        }
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error recording meter reading: " . $e->getMessage());
        return false;
    }
}

function normalizeMeterReadingTimestamp($reading_time = null) {
    if (empty($reading_time)) {
        return date('Y-m-d H:i:s');
    }

    $timestamp = strtotime((string) $reading_time);
    if ($timestamp === false) {
        return date('Y-m-d H:i:s');
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function getMeterSimulationBaselineReading($meter_id) {
    global $conn;

    $meter_id = (int) $meter_id;

    $stmt = $conn->prepare("SELECT last_reading FROM smart_meters WHERE meter_id = ? LIMIT 1");
    $stmt->bind_param("i", $meter_id);
    $stmt->execute();
    $meter = $stmt->get_result()->fetch_assoc();

    if ($meter && $meter['last_reading'] !== null) {
        return (float) $meter['last_reading'];
    }

    $stmt = $conn->prepare("
        SELECT current_reading
        FROM bills
        WHERE meter_id = ?
        ORDER BY billing_month DESC, bill_id DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $meter_id);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();

    if ($bill && $bill['current_reading'] !== null) {
        return (float) $bill['current_reading'];
    }

    return 0.0;
}

function ensureMeterSimulationStarted($meter_id, $user_id = null) {
    global $conn;

    $meter_id = (int) $meter_id;
    $stmt = $conn->prepare("
        SELECT user_id, meter_status, last_reading, last_reading_time
        FROM smart_meters
        WHERE meter_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $meter_id);
    $stmt->execute();
    $meter = $stmt->get_result()->fetch_assoc();

    if (!$meter) {
        return false;
    }

    $assigned_user_id = $user_id !== null ? (int) $user_id : (int) ($meter['user_id'] ?? 0);
    if ($assigned_user_id <= 0 || $meter['meter_status'] !== 'active') {
        return true;
    }

    $stmt = $conn->prepare("
        SELECT reading_value, reading_time
        FROM meter_readings
        WHERE meter_id = ?
        ORDER BY reading_time DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $meter_id);
    $stmt->execute();
    $latest_reading = $stmt->get_result()->fetch_assoc();

    if ($latest_reading) {
        $latest_value = (float) $latest_reading['reading_value'];
        $latest_time = normalizeMeterReadingTimestamp($latest_reading['reading_time']);
        $stmt = $conn->prepare("UPDATE smart_meters SET last_reading = ?, last_reading_time = ? WHERE meter_id = ?");
        $stmt->bind_param("dsi", $latest_value, $latest_time, $meter_id);
        return $stmt->execute();
    }

    $baseline_reading = getMeterSimulationBaselineReading($meter_id);
    $reading_time = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO meter_readings (meter_id, reading_value, reading_time, is_automated) VALUES (?, ?, ?, 1)");
    $stmt->bind_param("ids", $meter_id, $baseline_reading, $reading_time);
    if (!$stmt->execute()) {
        return false;
    }

    $stmt = $conn->prepare("UPDATE smart_meters SET last_reading = ?, last_reading_time = ? WHERE meter_id = ?");
    $stmt->bind_param("dsi", $baseline_reading, $reading_time, $meter_id);
    if (!$stmt->execute()) {
        return false;
    }

    return syncMeterBilling($assigned_user_id, $meter_id, date('Y-m', strtotime($reading_time)), $baseline_reading);
}

function calculateSimulatedWaterUsageIncrement($meter_id, $reading_time) {
    $hour = (int) date('G', strtotime($reading_time));
    $base_usage = IOT_SIMULATION_MIN_USAGE_PER_MINUTE + (
        (IOT_SIMULATION_MAX_USAGE_PER_MINUTE - IOT_SIMULATION_MIN_USAGE_PER_MINUTE) * (mt_rand(1, 1000) / 1000)
    );

    $hour_multiplier = 1.0;
    if (($hour >= 5 && $hour <= 8) || ($hour >= 18 && $hour <= 22)) {
        $hour_multiplier = 1.9;
    } elseif ($hour >= 9 && $hour <= 17) {
        $hour_multiplier = 1.2;
    } elseif ($hour >= 0 && $hour <= 4) {
        $hour_multiplier = 0.35;
    }

    $meter_multiplier = 1 + (($meter_id % 5) * 0.08);
    return round(max(0.00005, $base_usage * $hour_multiplier * $meter_multiplier), 5);
}

function runAutomatedMeterSimulation($force = false) {
    global $conn;

    static $already_ran = false;

    if (!IOT_SIMULATION_ENABLED) {
        return [
            'enabled' => false,
            'meters_processed' => 0,
            'readings_created' => 0
        ];
    }

    if ($already_ran && !$force) {
        return [
            'enabled' => true,
            'meters_processed' => 0,
            'readings_created' => 0
        ];
    }

    $already_ran = true;
    $now = time();
    $stats = [
        'enabled' => true,
        'meters_processed' => 0,
        'readings_created' => 0
    ];

    $result = $conn->query("
        SELECT meter_id, user_id, meter_number, meter_status, last_reading, last_reading_time
        FROM smart_meters
        WHERE user_id IS NOT NULL AND meter_status = 'active'
        ORDER BY meter_id ASC
    ");

    if (!$result) {
        return $stats;
    }

    while ($meter = $result->fetch_assoc()) {
        $meter_id = (int) $meter['meter_id'];
        $user_id = (int) $meter['user_id'];

        if (!ensureMeterSimulationStarted($meter_id, $user_id)) {
            continue;
        }

        $stmt = $conn->prepare("
            SELECT last_reading, last_reading_time
            FROM smart_meters
            WHERE meter_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $meter_id);
        $stmt->execute();
        $state = $stmt->get_result()->fetch_assoc();

        if (!$state || empty($state['last_reading_time'])) {
            continue;
        }

        $last_timestamp = strtotime($state['last_reading_time']);
        if ($last_timestamp === false) {
            continue;
        }

        $intervals_due = (int) floor(($now - $last_timestamp) / IOT_SIMULATION_INTERVAL_SECONDS);
        if ($intervals_due <= 0) {
            continue;
        }

        $intervals_due = min($intervals_due, IOT_SIMULATION_MAX_CATCHUP_READINGS, IOT_SIMULATION_MAX_READINGS_PER_RUN);
        $current_reading = (float) $state['last_reading'];
        $stats['meters_processed']++;

        for ($step = 1; $step <= $intervals_due; $step++) {
            $reading_time = date('Y-m-d H:i:s', $last_timestamp + ($step * IOT_SIMULATION_INTERVAL_SECONDS));
            $current_reading += calculateSimulatedWaterUsageIncrement($meter_id, $reading_time);

            if (!recordMeterReadingAt($meter_id, $current_reading, $reading_time, true, true, false)) {
                break;
            }

            $stats['readings_created']++;
        }
    }

    return $stats;
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

function getGroupedConsumptionData($meter_id, $start_datetime, $end_datetime, $group_by) {
    global $conn;

    $allowed_groupings = [
        'DATE(reading_time)',
        'DATE_FORMAT(reading_time, "%Y-%m")'
    ];

    if (!in_array($group_by, $allowed_groupings, true)) {
        return [];
    }

    $sql = "
        SELECT
            period,
            ROUND(SUM(consumption_delta), 2) as consumption,
            COUNT(*) as readings_count,
            MIN(reading_time) as first_reading,
            MAX(reading_time) as last_reading
        FROM (
            SELECT
                {$group_by} as period,
                reading_time,
                GREATEST(
                    reading_value - COALESCE(
                        LAG(reading_value) OVER (PARTITION BY meter_id ORDER BY reading_time),
                        reading_value
                    ),
                    0
                ) as consumption_delta
            FROM meter_readings
            WHERE meter_id = ? AND reading_time <= ?
        ) usage_rows
        WHERE reading_time BETWEEN ? AND ?
        GROUP BY period
        HAVING consumption > 0
        ORDER BY period
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Error preparing grouped consumption query: " . $conn->error);
        return [];
    }

    $stmt->bind_param("isss", $meter_id, $end_datetime, $start_datetime, $end_datetime);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getHourlyConsumptionPattern($meter_id, $start_datetime, $end_datetime, $average_by_day = false) {
    global $conn;

    $hourly_data = array_fill(0, 24, 0.0);
    $aggregation = $average_by_day
        ? "COALESCE(ROUND(SUM(consumption_delta) / NULLIF(COUNT(DISTINCT reading_day), 0), 2), 0)"
        : "COALESCE(ROUND(SUM(consumption_delta), 2), 0)";

    $sql = "
        SELECT
            reading_hour,
            {$aggregation} as hourly_consumption
        FROM (
            SELECT
                DATE(reading_time) as reading_day,
                HOUR(reading_time) as reading_hour,
                reading_time,
                GREATEST(
                    reading_value - COALESCE(
                        LAG(reading_value) OVER (PARTITION BY meter_id ORDER BY reading_time),
                        reading_value
                    ),
                    0
                ) as consumption_delta
            FROM meter_readings
            WHERE meter_id = ? AND reading_time <= ?
        ) hourly_rows
        WHERE reading_time BETWEEN ? AND ?
        GROUP BY reading_hour
        ORDER BY reading_hour
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Error preparing hourly consumption query: " . $conn->error);
        return $hourly_data;
    }

    $stmt->bind_param("isss", $meter_id, $end_datetime, $start_datetime, $end_datetime);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $hourly_data[(int) $row['reading_hour']] = (float) $row['hourly_consumption'];
    }

    return $hourly_data;
}

// Billing functions
function ensureMeterBillingStarted($user_id, $meter_id, $billing_month = null, $baseline_reading = null, $initial_units = 0.0) {
    global $conn;

    $billing_month = $billing_month ?: date('Y-m');

    $stmt = $conn->prepare("
        SELECT bill_id FROM bills
        WHERE user_id = ? AND meter_id = ? AND billing_month = ?
    ");
    $stmt->bind_param("iis", $user_id, $meter_id, $billing_month);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        return true;
    }

    if ($baseline_reading === null) {
        $stmt = $conn->prepare("
            SELECT last_reading
            FROM smart_meters
            WHERE meter_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $meter_id);
        $stmt->execute();
        $meter = $stmt->get_result()->fetch_assoc();

        $baseline_reading = $meter && $meter['last_reading'] !== null
            ? (float) $meter['last_reading']
            : 0.0;
    } else {
        $baseline_reading = (float) $baseline_reading;
    }

    $initial_units = max(0, (float) $initial_units);
    $due_date = date('Y-m-d', strtotime('+30 days'));
    $units_consumed = $initial_units;
    $total_amount = $units_consumed * WATER_RATE_PER_UNIT;
    $current_reading = $baseline_reading + $units_consumed;
    $rate_per_unit = WATER_RATE_PER_UNIT;

    $stmt = $conn->prepare("
        INSERT INTO bills (user_id, meter_id, billing_month, previous_reading, current_reading, units_consumed, rate_per_unit, total_amount, due_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "iisddddds",
        $user_id,
        $meter_id,
        $billing_month,
        $baseline_reading,
        $current_reading,
        $units_consumed,
        $rate_per_unit,
        $total_amount,
        $due_date
    );

    if (!$stmt->execute()) {
        return false;
    }

    if ($initial_units > 0) {
        createNotification(
            $user_id,
            "Opening Water Bill Added",
            "Your first bill for " . date('F Y', strtotime($billing_month . '-01')) . " includes " . number_format($initial_units, 2) . " m3 of water worth " . CURRENCY . " " . number_format($total_amount, 2) . ".",
            'bill'
        );
    }

    return true;
}

function syncMeterBilling($user_id, $meter_id, $billing_month = null, $baseline_reading = null) {
    $billing_month = $billing_month ?: date('Y-m');

    if (!ensureMeterBillingStarted($user_id, $meter_id, $billing_month, $baseline_reading)) {
        return false;
    }

    return generateBill($user_id, $meter_id, $billing_month, false);
}

function resolveBillStatus($bill_id, $current_status, $total_amount) {
    global $conn;

    if (!in_array($current_status, ['pending', 'overdue', 'paid'], true)) {
        return $current_status;
    }

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_paid
        FROM payments
        WHERE bill_id = ? AND payment_status = 'completed'
    ");
    $stmt->bind_param("i", $bill_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $total_paid = (float) ($result['total_paid'] ?? 0);

    if ($total_paid >= $total_amount && $total_amount > 0) {
        return 'paid';
    }

    if ($current_status === 'paid' && $total_paid < $total_amount) {
        return 'pending';
    }

    return $current_status;
}

function generateBill($user_id, $meter_id, $billing_month, $notify_user = true) {
    global $conn;

    $month_start = $billing_month . '-01 00:00:00';
    $month_end = date('Y-m-d H:i:s', strtotime($month_start . ' +1 month'));

    $stmt = $conn->prepare("
        SELECT bill_id, previous_reading, current_reading, bill_status
        FROM bills
        WHERE user_id = ? AND meter_id = ? AND billing_month = ?
        LIMIT 1
    ");
    $stmt->bind_param("iis", $user_id, $meter_id, $billing_month);
    $stmt->execute();
    $existing_bill = $stmt->get_result()->fetch_assoc();

    // Get only the reading boundaries needed for billing instead of loading the entire month into memory
    $stmt = $conn->prepare("
        SELECT
            COUNT(*) as reading_count,
            (
                SELECT mr_first.reading_value
                FROM meter_readings mr_first
                WHERE mr_first.meter_id = ? AND mr_first.reading_time >= ? AND mr_first.reading_time < ?
                ORDER BY mr_first.reading_time ASC
                LIMIT 1
            ) as first_reading_value,
            (
                SELECT mr_last.reading_value
                FROM meter_readings mr_last
                WHERE mr_last.meter_id = ? AND mr_last.reading_time >= ? AND mr_last.reading_time < ?
                ORDER BY mr_last.reading_time DESC
                LIMIT 1
            ) as last_reading_value
        FROM meter_readings
        WHERE meter_id = ? AND reading_time >= ? AND reading_time < ?
    ");
    $stmt->bind_param(
        "issississ",
        $meter_id,
        $month_start,
        $month_end,
        $meter_id,
        $month_start,
        $month_end,
        $meter_id,
        $month_start,
        $month_end
    );
    $stmt->execute();
    $reading_summary = $stmt->get_result()->fetch_assoc();
    $reading_count = (int) ($reading_summary['reading_count'] ?? 0);
    $first_monthly_reading = isset($reading_summary['first_reading_value'])
        ? (float) $reading_summary['first_reading_value']
        : 0.0;
    $last_monthly_reading = isset($reading_summary['last_reading_value'])
        ? (float) $reading_summary['last_reading_value']
        : 0.0;
    
    if (!$existing_bill && $reading_count < 2) {
        return false; // Not enough readings
    }

    if ($existing_bill) {
        $first_reading = (float) $existing_bill['previous_reading'];
        $last_reading = $reading_count > 0
            ? max($last_monthly_reading, $first_reading)
            : max((float) $existing_bill['current_reading'], $first_reading);
    } else {
        $first_reading = $first_monthly_reading;
        $last_reading = $last_monthly_reading;
    }

    $units_consumed = max(0, $last_reading - $first_reading);
    $total_amount = $units_consumed * WATER_RATE_PER_UNIT;

    if ($existing_bill) {
        // Update existing bill
        $bill_id = (int) $existing_bill['bill_id'];
        $bill_status = resolveBillStatus($bill_id, $existing_bill['bill_status'], $total_amount);
        $stmt = $conn->prepare("
            UPDATE bills 
            SET previous_reading = ?, current_reading = ?, units_consumed = ?, rate_per_unit = ?, total_amount = ?, bill_status = ?
            WHERE bill_id = ?
        ");
        $rate_per_unit = WATER_RATE_PER_UNIT;
        $stmt->bind_param("dddddsi", $first_reading, $last_reading, $units_consumed, $rate_per_unit, $total_amount, $bill_status, $bill_id);
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
        if ($notify_user) {
            createNotification(
                $user_id,
                "New Bill Generated",
                "Your water bill for " . date('F Y', strtotime($billing_month . '-01')) . " is " . CURRENCY . " " . number_format($total_amount, 2),
                'bill'
            );
        }
        return true;
    }
    
    return false;
}

// Format currency
function formatCurrency($amount) {
    return CURRENCY . ' ' . number_format($amount, 2);
}

function cubicMetersToCubicCentimeters($volume_in_cubic_meters) {
    return (float) $volume_in_cubic_meters * CUBIC_CENTIMETERS_PER_CUBIC_METER;
}

function formatVolumeInCubicCentimeters($volume_in_cubic_meters, $decimals = 0) {
    return number_format(cubicMetersToCubicCentimeters($volume_in_cubic_meters), $decimals) . ' cm^3';
}

function getBillPaymentSummary($bill_id, $user_id = null) {
    global $conn;

    $sql = "
        SELECT
            b.bill_id,
            b.user_id,
            b.bill_status,
            b.total_amount,
            b.due_date,
            COALESCE(SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END), 0) as amount_paid
        FROM bills b
        LEFT JOIN payments p ON b.bill_id = p.bill_id
        WHERE b.bill_id = ?
    ";

    if ($user_id !== null) {
        $sql .= " AND b.user_id = ?";
    }

    $sql .= "
        GROUP BY b.bill_id, b.user_id, b.bill_status, b.total_amount, b.due_date
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    if ($user_id !== null) {
        $stmt->bind_param("ii", $bill_id, $user_id);
    } else {
        $stmt->bind_param("i", $bill_id);
    }

    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();

    if (!$bill) {
        return null;
    }

    $bill['total_amount'] = (float) $bill['total_amount'];
    $bill['amount_paid'] = (float) ($bill['amount_paid'] ?? 0);
    $bill['amount_due'] = max(0, $bill['total_amount'] - $bill['amount_paid']);

    return $bill;
}

function updateBillStatusFromPayments($bill_id) {
    global $conn;

    $bill = getBillPaymentSummary($bill_id);
    if (!$bill) {
        return false;
    }

    $next_status = $bill['bill_status'];
    if ($bill['amount_due'] <= 0.00001) {
        $next_status = 'paid';
    } elseif ($bill['bill_status'] === 'paid') {
        $next_status = 'pending';
    }

    if ($next_status === $bill['bill_status']) {
        return true;
    }

    $stmt = $conn->prepare("UPDATE bills SET bill_status = ? WHERE bill_id = ?");
    $stmt->bind_param("si", $next_status, $bill_id);

    return $stmt->execute();
}

// Get user's current balance
function getUserBalance($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT
            COALESCE((SELECT SUM(total_amount) FROM bills WHERE user_id = ?), 0) as total_billed,
            COALESCE((SELECT SUM(amount) FROM payments WHERE user_id = ? AND payment_status = 'completed'), 0) as total_paid
    ");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total_billed'] - $row['total_paid'];
}
?>

<?php
/**
 * Timekeeping Functions
 *
 * This file handles all check-in and check-out logic, including:
 * - Recording when children arrive and leave
 * - Calculating how long they've been at the center
 * - Calculating overtime charges
 */

require_once __DIR__ . '/../config.php';

/**
 * Get the timekeeping file for a specific month
 * Creates the file if it doesn't exist yet
 *
 * @param string $yearMonth - Format: "2025-01"
 * @return string - Full path to the timekeeping file
 */
function getTimekeepingFile($yearMonth) {
    $filepath = TIMEKEEPING_DIR . '/' . $yearMonth . '.json';

    // If file doesn't exist, create it with empty records
    if (!file_exists($filepath)) {
        $initialData = [
            'month' => $yearMonth,
            'records' => []
        ];
        saveJsonFile($filepath, $initialData);
    }

    return $filepath;
}

/**
 * Get today's date in YYYY-MM-DD format
 *
 * @return string
 */
function getTodayDate() {
    return date('Y-m-d');
}

/**
 * Get current month in YYYY-MM format
 *
 * @return string
 */
function getCurrentMonth() {
    return date('Y-m');
}

/**
 * Check if a child is currently checked in today
 *
 * @param string $childId - The child's ID
 * @return array|null - The active record if checked in, null otherwise
 */
function getActiveCheckIn($childId) {
    $today = getTodayDate();
    $month = getCurrentMonth();
    $filepath = getTimekeepingFile($month);
    $data = loadJsonFile($filepath);

    // Loop through today's records to find an active check-in
    foreach ($data['records'] as $record) {
        if ($record['child_id'] === $childId &&
            $record['date'] === $today &&
            empty($record['check_out_time'])) {
            return $record; // Found active check-in
        }
    }

    return null; // Not currently checked in
}

/**
 * Check in a child
 *
 * @param string $childId - The child's ID
 * @param string $childName - The child's name (for messages)
 * @return array - Result with 'success' and 'message'
 */
function checkInChild($childId, $childName) {
    // First, check if already checked in
    $activeCheckIn = getActiveCheckIn($childId);
    if ($activeCheckIn) {
        return [
            'success' => false,
            'message' => $childName . ' is already checked in at ' . formatTime($activeCheckIn['check_in_time'])
        ];
    }

    // Get current time
    $now = date('H:i:s');
    $today = getTodayDate();
    $month = getCurrentMonth();

    // Load timekeeping file for this month
    $filepath = getTimekeepingFile($month);
    $data = loadJsonFile($filepath);

    // Create new record
    $newRecord = [
        'id' => generateId('rec_'),
        'child_id' => $childId,
        'date' => $today,
        'check_in_time' => $now,
        'check_out_time' => null,
        'duration_hours' => null,
        'overage_minutes' => 0,
        'overage_charge' => 0,
        'late_pickup_minutes' => 0,
        'late_pickup_charge' => 0,
        'checked_in_by' => 'parent',
        'checked_out_by' => null,
        'notes' => ''
    ];

    // Add the new record
    $data['records'][] = $newRecord;

    // Save the file
    if (saveJsonFile($filepath, $data)) {
        return [
            'success' => true,
            'message' => $childName . ' checked in at ' . formatTime($now)
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Error saving check-in. Please try again.'
        ];
    }
}

/**
 * Check out a child and calculate any overage charges
 *
 * @param string $childId - The child's ID
 * @param string $childName - The child's name (for messages)
 * @return array - Result with 'success', 'message', 'overage_minutes', 'overage_charge'
 */
function checkOutChild($childId, $childName) {
    // First, check if actually checked in
    $activeCheckIn = getActiveCheckIn($childId);
    if (!$activeCheckIn) {
        return [
            'success' => false,
            'message' => $childName . ' is not currently checked in.'
        ];
    }

    // Get current time
    $now = date('H:i:s');
    $month = getCurrentMonth();

    // Load timekeeping file
    $filepath = getTimekeepingFile($month);
    $data = loadJsonFile($filepath);

    // Find and update the record
    foreach ($data['records'] as &$record) {
        if ($record['id'] === $activeCheckIn['id']) {
            // Update checkout time
            $record['check_out_time'] = $now;
            $record['checked_out_by'] = 'parent';

            // Calculate duration in hours
            $durationHours = calculateHours($record['check_in_time'], $now);
            $record['duration_hours'] = $durationHours;

            // Calculate overage if over MAX_HOURS_PER_DAY
            $overageMinutes = 0;
            $overageCharge = 0;

            if ($durationHours > MAX_HOURS_PER_DAY) {
                // Calculate how many minutes over the limit
                $overageHours = $durationHours - MAX_HOURS_PER_DAY;
                $overageMinutes = round($overageHours * 60);
                $overageCharge = $overageMinutes * OVERAGE_RATE_PER_MINUTE;
            }

            $record['overage_minutes'] = $overageMinutes;
            $record['overage_charge'] = $overageCharge;

            // Calculate late pickup fee if checked out after closing time
            $latePickupMinutes = 0;
            $latePickupCharge = 0;

            $closingTime = strtotime($record['date'] . ' ' . DAYCARE_CLOSING_TIME);
            $checkOutDateTime = strtotime($record['date'] . ' ' . $now);

            if ($checkOutDateTime > $closingTime) {
                // Calculate how many minutes after closing time
                $lateSeconds = $checkOutDateTime - $closingTime;
                $latePickupMinutes = ceil($lateSeconds / 60); // Round up to nearest minute
                $latePickupCharge = $latePickupMinutes * LATE_PICKUP_RATE_PER_MINUTE;
            }

            $record['late_pickup_minutes'] = $latePickupMinutes;
            $record['late_pickup_charge'] = $latePickupCharge;

            // Save the updated data
            if (saveJsonFile($filepath, $data)) {
                $message = $childName . ' checked out at ' . formatTime($now);
                $message .= '<br>Duration: ' . number_format($durationHours, 2) . ' hours';

                if ($overageMinutes > 0) {
                    $message .= '<br><strong style="color: #d9534f;">OVERTIME: ' . $overageMinutes . ' minutes</strong>';
                    $message .= '<br><strong style="color: #d9534f;">Overtime charge: $' . number_format($overageCharge, 2) . '</strong>';
                }

                if ($latePickupMinutes > 0) {
                    $message .= '<br><strong style="color: #d9534f;">LATE PICKUP: ' . $latePickupMinutes . ' minutes after 4:30 PM</strong>';
                    $message .= '<br><strong style="color: #d9534f;">Late pickup charge: $' . number_format($latePickupCharge, 2) . '</strong>';
                }

                // Show total charges if there are any fees
                $totalCharges = $overageCharge + $latePickupCharge;
                if ($totalCharges > 0) {
                    $message .= '<br><strong style="color: #d9534f; font-size: 1.1em;">TOTAL ADDITIONAL CHARGES: $' . number_format($totalCharges, 2) . '</strong>';
                }

                return [
                    'success' => true,
                    'message' => $message,
                    'overage_minutes' => $overageMinutes,
                    'overage_charge' => $overageCharge,
                    'late_pickup_minutes' => $latePickupMinutes,
                    'late_pickup_charge' => $latePickupCharge
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error saving check-out. Please try again.'
                ];
            }
        }
    }

    return [
        'success' => false,
        'message' => 'Error processing check-out.'
    ];
}

/**
 * Get all children currently checked in (across all families)
 * Useful for admin dashboard
 *
 * @return array - Array of checked-in children with their info
 */
function getCurrentlyCheckedIn() {
    $today = getTodayDate();
    $month = getCurrentMonth();
    $filepath = getTimekeepingFile($month);
    $timekeepingData = loadJsonFile($filepath);
    $familiesData = loadJsonFile(FAMILIES_FILE);

    $checkedInChildren = [];

    // Find all active check-ins for today
    foreach ($timekeepingData['records'] as $record) {
        if ($record['date'] === $today && empty($record['check_out_time'])) {
            // Find the child's info
            foreach ($familiesData['families'] as $family) {
                foreach ($family['children'] as $child) {
                    if ($child['id'] === $record['child_id']) {
                        $checkedInChildren[] = [
                            'child' => $child,
                            'family' => $family['name'],
                            'check_in_time' => $record['check_in_time'],
                            'hours_so_far' => calculateHours($record['check_in_time'], date('H:i:s'))
                        ];
                        break 2; // Break out of both loops
                    }
                }
            }
        }
    }

    return $checkedInChildren;
}

/**
 * Get all records for a specific child within a date range
 *
 * @param string $childId - The child's ID
 * @param string $startDate - Start date (YYYY-MM-DD)
 * @param string $endDate - End date (YYYY-MM-DD)
 * @return array - Array of records
 */
function getChildRecords($childId, $startDate, $endDate) {
    $records = [];

    // Figure out which months we need to check
    $startMonth = substr($startDate, 0, 7); // "2025-01"
    $endMonth = substr($endDate, 0, 7);

    // Get records from each month
    $currentMonth = $startMonth;
    while ($currentMonth <= $endMonth) {
        $filepath = getTimekeepingFile($currentMonth);
        if (file_exists($filepath)) {
            $data = loadJsonFile($filepath);

            foreach ($data['records'] as $record) {
                if ($record['child_id'] === $childId &&
                    $record['date'] >= $startDate &&
                    $record['date'] <= $endDate) {
                    $records[] = $record;
                }
            }
        }

        // Move to next month
        $date = strtotime($currentMonth . '-01');
        $date = strtotime('+1 month', $date);
        $currentMonth = date('Y-m', $date);
    }

    return $records;
}

?>

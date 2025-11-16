<?php
/**
 * Reports Interface
 *
 * This page allows staff to:
 * 1. View attendance records for children
 * 2. Generate printable reports
 * 3. Filter by date range
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/timekeeping.php';

// Require staff login
requireStaffLogin();

$staff = getCurrentStaff();
$familiesData = loadJsonFile(FAMILIES_FILE);

// Get filter parameters
$childId = $_GET['child_id'] ?? 'all';
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Get records
$records = [];
$selectedChildName = null;
$selectedFamilyName = null;

if ($childId === 'all') {
    // Get records for all children
    foreach ($familiesData['families'] as $family) {
        foreach ($family['children'] as $child) {
            $childRecords = getChildRecords($child['id'], $startDate, $endDate);
            foreach ($childRecords as $record) {
                $record['child_name'] = $child['first_name'] . ' ' . $child['last_name'];
                $record['family_name'] = $family['name'];
                $records[] = $record;
            }
        }
    }
} else {
    // Get records for specific child
    $records = getChildRecords($childId, $startDate, $endDate);

    // Add child and family name
    foreach ($familiesData['families'] as $family) {
        foreach ($family['children'] as $child) {
            if ($child['id'] === $childId) {
                $selectedChildName = $child['first_name'] . ' ' . $child['last_name'];
                $selectedFamilyName = $family['name'];
                foreach ($records as &$record) {
                    $record['child_name'] = $selectedChildName;
                    $record['family_name'] = $selectedFamilyName;
                }
                break 2;
            }
        }
    }
}

// Sort records by date (oldest first)
usort($records, function($a, $b) {
    return strcmp($a['date'], $b['date']);
});

// No filtering - show all records in date range
$displayRecords = $records;

// Calculate totals
$totalHours = 0;
$totalOverageCharges = 0;
$totalLatePickupCharges = 0;
foreach ($displayRecords as $record) {
    if ($record['duration_hours']) {
        $totalHours += $record['duration_hours'];
        $totalOverageCharges += $record['overage_charge'] ?? 0;
        $totalLatePickupCharges += $record['late_pickup_charge'] ?? 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Aracely's Daycare Timekeeper</title>
    <link rel="stylesheet" href="/css/styles.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            body {
                background: white;
                padding: 0;
            }
            .card {
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Logo -->
        <div style="text-align: center; margin-bottom: 20px;" class="no-print">
            <img src="/images/logo.png" alt="Aracely's Daycare" style="max-width: 200px; height: auto;">
        </div>
        <!-- Header -->
        <div class="header no-print">
            <div>
                <h1>Aracely's Daycare - Reports</h1>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($staff['name']); ?></div>
                <a href="/admin.php" class="btn btn-secondary" style="padding: 8px 20px; font-size: 0.9em;">
                    Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="card no-print">
            <h3>Report Filters</h3>
            <form method="GET" action="/reports.php" id="filterForm">
                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 20px; align-items: end;">
                    <div class="form-group">
                        <label>Select Child</label>
                        <select name="child_id" onchange="document.getElementById('filterForm').submit()">
                            <option value="all" <?php echo $childId === 'all' ? 'selected' : ''; ?>>
                                All Children
                            </option>
                            <?php foreach ($familiesData['families'] as $family): ?>
                                <?php foreach ($family['children'] as $child): ?>
                                    <option
                                        value="<?php echo $child['id']; ?>"
                                        <?php echo $childId === $child['id'] ? 'selected' : ''; ?>
                                    >
                                        <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name'] . ' (' . $family['name'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?php echo $startDate; ?>" onchange="document.getElementById('filterForm').submit()">
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?php echo $endDate; ?>" onchange="document.getElementById('filterForm').submit()">
                    </div>
                </div>
            </form>
        </div>

        <!-- Report Content -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
                <div>
                    <h2>
                        <?php if ($selectedChildName): ?>
                            Attendance Report - <?php echo htmlspecialchars($selectedChildName); ?>
                        <?php else: ?>
                            Attendance Report
                        <?php endif; ?>
                    </h2>
                    <p style="color: #4a5568;">
                        <?php if ($selectedChildName): ?>
                            <?php echo htmlspecialchars($selectedFamilyName); ?> |
                        <?php endif; ?>
                        <?php echo date('M j, Y', strtotime($startDate)); ?> - <?php echo date('M j, Y', strtotime($endDate)); ?>
                    </p>
                </div>
                <button onclick="window.print()" class="btn btn-success no-print">
                    🖨️ Print Report
                </button>
            </div>

            <!-- Summary Stats -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
                <div style="background: #f7fafc; padding: 20px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 2em; font-weight: bold; color: #5a67d8;">
                        <?php echo count($displayRecords); ?>
                    </div>
                    <div style="color: #4a5568;">Total Visits</div>
                </div>
                <div style="background: #f7fafc; padding: 20px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 2em; font-weight: bold; color: #5a67d8;">
                        <?php echo number_format($totalHours, 1); ?>
                    </div>
                    <div style="color: #4a5568;">Total Hours</div>
                </div>
                <div style="background: #f7fafc; padding: 20px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 2em; font-weight: bold; color: <?php echo $totalOverageCharges > 0 ? '#e53e3e' : '#5a67d8'; ?>">
                        $<?php echo number_format($totalOverageCharges, 2); ?>
                    </div>
                    <div style="color: #4a5568;">Overtime Fees</div>
                </div>
                <div style="background: #f7fafc; padding: 20px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 2em; font-weight: bold; color: <?php echo $totalLatePickupCharges > 0 ? '#e53e3e' : '#5a67d8'; ?>">
                        $<?php echo number_format($totalLatePickupCharges, 2); ?>
                    </div>
                    <div style="color: #4a5568;">Late Pickup Fees</div>
                </div>
            </div>

            <!-- Records Table -->
            <?php if (empty($displayRecords)): ?>
                <p style="text-align: center; color: #718096; padding: 40px;">
                    No records found for the selected filters.
                </p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th class="sortable" onclick="sortTable(0, 'date')">Date</th>
                            <?php if ($childId === 'all'): ?>
                                <th class="sortable" onclick="sortTable(1, 'text')">Child</th>
                                <th class="sortable" onclick="sortTable(2, 'text')">Family</th>
                                <th class="sortable" onclick="sortTable(3, 'time')">Check-in</th>
                                <th class="sortable" onclick="sortTable(4, 'time')">Check-out</th>
                                <th class="sortable" onclick="sortTable(5, 'number')">Duration</th>
                                <th class="sortable" onclick="sortTable(6, 'number')">Overtime</th>
                                <th class="sortable" onclick="sortTable(7, 'number')">Late Pickup</th>
                                <th class="sortable" onclick="sortTable(8, 'number')">Total Fees</th>
                            <?php else: ?>
                                <th class="sortable" onclick="sortTable(1, 'time')">Check-in</th>
                                <th class="sortable" onclick="sortTable(2, 'time')">Check-out</th>
                                <th class="sortable" onclick="sortTable(3, 'number')">Duration</th>
                                <th class="sortable" onclick="sortTable(4, 'number')">Overtime</th>
                                <th class="sortable" onclick="sortTable(5, 'number')">Late Pickup</th>
                                <th class="sortable" onclick="sortTable(6, 'number')">Total Fees</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($displayRecords as $record): ?>
                            <?php
                            $overageCharge = $record['overage_charge'] ?? 0;
                            $latePickupCharge = $record['late_pickup_charge'] ?? 0;
                            $totalFees = $overageCharge + $latePickupCharge;
                            ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($record['date'])); ?></td>
                                <?php if ($childId === 'all'): ?>
                                    <td><?php echo htmlspecialchars($record['child_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['family_name']); ?></td>
                                <?php endif; ?>
                                <td><?php echo formatTime($record['check_in_time']); ?></td>
                                <td>
                                    <?php echo $record['check_out_time'] ? formatTime($record['check_out_time']) : '<em>Still checked in</em>'; ?>
                                </td>
                                <td>
                                    <?php echo $record['duration_hours'] ? number_format($record['duration_hours'], 2) . ' hrs' : '-'; ?>
                                </td>
                                <td>
                                    <?php if ($overageCharge > 0): ?>
                                        <span style="color: #e53e3e; font-weight: bold;">
                                            <?php echo ($record['overage_minutes'] ?? 0); ?> min<br>
                                            $<?php echo number_format($overageCharge, 2); ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($latePickupCharge > 0): ?>
                                        <span style="color: #e53e3e; font-weight: bold;">
                                            <?php echo ($record['late_pickup_minutes'] ?? 0); ?> min<br>
                                            $<?php echo number_format($latePickupCharge, 2); ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($totalFees > 0): ?>
                                        <span style="color: #e53e3e; font-weight: bold;">
                                            $<?php echo number_format($totalFees, 2); ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f7fafc; font-weight: bold;">
                            <td colspan="<?php echo $childId === 'all' ? '5' : '3'; ?>">TOTALS</td>
                            <td><?php echo number_format($totalHours, 2); ?> hrs</td>
                            <td style="color: #e53e3e;">
                                $<?php echo number_format($totalOverageCharges, 2); ?>
                            </td>
                            <td style="color: #e53e3e;">
                                $<?php echo number_format($totalLatePickupCharges, 2); ?>
                            </td>
                            <td style="color: #e53e3e; font-size: 1.1em;">
                                $<?php echo number_format($totalOverageCharges + $totalLatePickupCharges, 2); ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>

            <!-- Print Footer -->
            <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #e2e8f0; text-align: center; color: #718096; display: none;" class="print-only">
                <p>Generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
                <p>Daycare Timekeeper - Attendance Report</p>
            </div>
        </div>
    </div>

    <style>
        @media print {
            .print-only {
                display: block !important;
            }
        }

        /* Sortable table headers */
        th.sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
            padding-right: 25px;
        }

        th.sortable:hover {
            background: #e2e8f0;
        }

        th.sortable::after {
            content: '↕';
            position: absolute;
            right: 8px;
            opacity: 0.3;
        }

        th.sortable.asc::after {
            content: '↑';
            opacity: 1;
        }

        th.sortable.desc::after {
            content: '↓';
            opacity: 1;
        }
    </style>

    <script>
        // Table sorting functionality
        let currentSort = {column: null, direction: 'asc'};

        function sortTable(columnIndex, type) {
            const table = document.querySelector('table tbody');
            const rows = Array.from(table.querySelectorAll('tr'));

            // Determine sort direction
            if (currentSort.column === columnIndex) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.column = columnIndex;
                currentSort.direction = 'asc';
            }

            // Sort rows
            rows.sort((a, b) => {
                let aVal = a.cells[columnIndex].textContent.trim();
                let bVal = b.cells[columnIndex].textContent.trim();

                // Handle different data types
                if (type === 'number') {
                    // Extract numbers from strings like "$45.00" or "30 min"
                    aVal = parseFloat(aVal.replace(/[^0-9.-]/g, '')) || 0;
                    bVal = parseFloat(bVal.replace(/[^0-9.-]/g, '')) || 0;
                } else if (type === 'date') {
                    aVal = new Date(aVal);
                    bVal = new Date(bVal);
                } else if (type === 'time') {
                    // Convert time to comparable number (e.g., "2:30 PM" -> 1430)
                    aVal = convertTimeToNumber(aVal);
                    bVal = convertTimeToNumber(bVal);
                }

                if (currentSort.direction === 'asc') {
                    return aVal > bVal ? 1 : -1;
                } else {
                    return aVal < bVal ? 1 : -1;
                }
            });

            // Re-append sorted rows
            rows.forEach(row => table.appendChild(row));

            // Update header indicators
            document.querySelectorAll('th.sortable').forEach(th => {
                th.classList.remove('asc', 'desc');
            });
            const header = document.querySelectorAll('th.sortable')[getSortableHeaderIndex(columnIndex)];
            if (header) {
                header.classList.add(currentSort.direction);
            }
        }

        function convertTimeToNumber(timeStr) {
            if (!timeStr || timeStr === '-' || timeStr.includes('Still checked in')) return 0;
            const match = timeStr.match(/(\d+):(\d+)\s*(AM|PM)/i);
            if (!match) return 0;
            let hours = parseInt(match[1]);
            const minutes = parseInt(match[2]);
            const period = match[3].toUpperCase();

            if (period === 'PM' && hours !== 12) hours += 12;
            if (period === 'AM' && hours === 12) hours = 0;

            return hours * 100 + minutes;
        }

        function getSortableHeaderIndex(columnIndex) {
            // Map actual column index to sortable header index (accounting for conditional columns)
            const allHeaders = document.querySelectorAll('th');
            let sortableIndex = 0;
            for (let i = 0; i < allHeaders.length; i++) {
                if (i === columnIndex) return sortableIndex;
                if (allHeaders[i].classList.contains('sortable')) sortableIndex++;
            }
            return -1;
        }
    </script>
</body>
</html>

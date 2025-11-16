<?php
/**
 * Admin Dashboard
 *
 * This page allows staff members to:
 * 1. Log in with their staff PIN
 * 2. View current check-ins
 * 3. Manage families and children
 * 4. Access reports
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/timekeeping.php';

$message = '';
$messageType = '';

// Handle logout
if (isset($_GET['logout'])) {
    logout();
    header('Location: /admin.php');
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $pin = $_POST['pin'] ?? '';
        $result = loginStaff($pin);

        if ($result['success']) {
            header('Location: /admin.php');
            exit;
        } else {
            $message = $result['message'];
            $messageType = 'error';
        }
    }

    // Handle adding new family
    if ($_POST['action'] === 'add_family' && isStaffLoggedIn()) {
        $familyName = trim($_POST['family_name']);
        $familyPin = $_POST['family_pin'];
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);

        if (strlen($familyPin) >= 4) {
            $data = loadJsonFile(FAMILIES_FILE);

            $newFamily = [
                'id' => generateId('fam_'),
                'name' => $familyName,
                'pin_hash' => hashPin($familyPin),
                'contact_phone' => $phone,
                'contact_email' => $email,
                'children' => []
            ];

            $data['families'][] = $newFamily;

            if (saveJsonFile(FAMILIES_FILE, $data)) {
                $message = 'Family "' . $familyName . '" added successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error saving family. Please try again.';
                $messageType = 'error';
            }
        } else {
            $message = 'PIN must be at least 4 digits.';
            $messageType = 'error';
        }
    }

    // Handle adding new child
    if ($_POST['action'] === 'add_child' && isStaffLoggedIn()) {
        $familyId = $_POST['family_id'];
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $birthDate = $_POST['birth_date'];
        $notes = trim($_POST['notes'] ?? '');

        $data = loadJsonFile(FAMILIES_FILE);

        foreach ($data['families'] as &$family) {
            if ($family['id'] === $familyId) {
                $newChild = [
                    'id' => generateId('child_'),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'birth_date' => $birthDate,
                    'photo_base64' => '',
                    'notes' => $notes
                ];

                $family['children'][] = $newChild;
                break;
            }
        }

        if (saveJsonFile(FAMILIES_FILE, $data)) {
            $message = 'Child "' . $firstName . ' ' . $lastName . '" added successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error saving child. Please try again.';
            $messageType = 'error';
        }
    }

    // Handle editing family
    if ($_POST['action'] === 'edit_family' && isStaffLoggedIn()) {
        $familyId = $_POST['family_id'];
        $familyName = trim($_POST['family_name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $newPin = $_POST['family_pin'] ?? '';

        $data = loadJsonFile(FAMILIES_FILE);

        foreach ($data['families'] as &$family) {
            if ($family['id'] === $familyId) {
                $family['name'] = $familyName;
                $family['contact_phone'] = $phone;
                $family['contact_email'] = $email;

                // Only update PIN if a new one was provided
                if (!empty($newPin) && strlen($newPin) >= 4) {
                    $family['pin_hash'] = hashPin($newPin);
                }
                break;
            }
        }

        if (saveJsonFile(FAMILIES_FILE, $data)) {
            $message = 'Family "' . $familyName . '" updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error updating family. Please try again.';
            $messageType = 'error';
        }
    }

    // Handle editing child
    if ($_POST['action'] === 'edit_child' && isStaffLoggedIn()) {
        $familyId = $_POST['family_id'];
        $childId = $_POST['child_id'];
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $birthDate = $_POST['birth_date'];
        $notes = trim($_POST['notes'] ?? '');

        $data = loadJsonFile(FAMILIES_FILE);

        // Handle image upload
        $imageBase64 = null;
        if (isset($_FILES['child_photo']) && $_FILES['child_photo']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['child_photo']['tmp_name'];
            $fileType = $_FILES['child_photo']['type'];

            // Validate that it's an image
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($fileType, $allowedTypes)) {
                // Read the file and convert to base64
                $imageData = file_get_contents($fileTmpPath);
                $imageBase64 = 'data:' . $fileType . ';base64,' . base64_encode($imageData);
            }
        }

        foreach ($data['families'] as &$family) {
            if ($family['id'] === $familyId) {
                foreach ($family['children'] as &$child) {
                    if ($child['id'] === $childId) {
                        $child['first_name'] = $firstName;
                        $child['last_name'] = $lastName;
                        $child['birth_date'] = $birthDate;
                        $child['notes'] = $notes;

                        // Update photo if a new one was uploaded
                        if ($imageBase64) {
                            $child['photo'] = $imageBase64;
                        }

                        break 2;
                    }
                }
            }
        }

        if (saveJsonFile(FAMILIES_FILE, $data)) {
            $message = 'Child "' . $firstName . ' ' . $lastName . '" updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error updating child. Please try again.';
            $messageType = 'error';
        }
    }

    // Handle deleting child
    if ($_POST['action'] === 'delete_child' && isStaffLoggedIn()) {
        $familyId = $_POST['family_id'];
        $childId = $_POST['child_id'];
        $childName = $_POST['child_name'] ?? 'Child';

        $data = loadJsonFile(FAMILIES_FILE);

        foreach ($data['families'] as &$family) {
            if ($family['id'] === $familyId) {
                $family['children'] = array_filter($family['children'], function($child) use ($childId) {
                    return $child['id'] !== $childId;
                });
                // Re-index array after filtering
                $family['children'] = array_values($family['children']);
                break;
            }
        }

        if (saveJsonFile(FAMILIES_FILE, $data)) {
            $message = 'Child "' . $childName . '" deleted successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error deleting child. Please try again.';
            $messageType = 'error';
        }
    }

    // Handle deleting family
    if ($_POST['action'] === 'delete_family' && isStaffLoggedIn()) {
        $familyId = $_POST['family_id'];
        $familyName = $_POST['family_name'] ?? 'Family';

        $data = loadJsonFile(FAMILIES_FILE);

        $data['families'] = array_filter($data['families'], function($family) use ($familyId) {
            return $family['id'] !== $familyId;
        });
        // Re-index array after filtering
        $data['families'] = array_values($data['families']);

        if (saveJsonFile(FAMILIES_FILE, $data)) {
            $message = 'Family "' . $familyName . '" deleted successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error deleting family. Please try again.';
            $messageType = 'error';
        }
    }

    // Handle adding new staff member
    if ($_POST['action'] === 'add_staff' && isStaffLoggedIn()) {
        $staffName = trim($_POST['staff_name']);
        $staffPin = $_POST['staff_pin'];
        $role = $_POST['role'] ?? 'staff';

        if (strlen($staffPin) >= 4) {
            $data = loadJsonFile(USERS_FILE);

            $newStaff = [
                'id' => generateId('staff_'),
                'name' => $staffName,
                'pin_hash' => hashPin($staffPin),
                'role' => $role,
                'created_at' => date('Y-m-d')
            ];

            $data['staff'][] = $newStaff;

            if (saveJsonFile(USERS_FILE, $data)) {
                $message = 'Staff member "' . $staffName . '" added successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error saving staff member. Please try again.';
                $messageType = 'error';
            }
        } else {
            $message = 'PIN must be at least 4 digits.';
            $messageType = 'error';
        }
    }

    // Handle editing staff member
    if ($_POST['action'] === 'edit_staff' && isStaffLoggedIn()) {
        $staffId = $_POST['staff_id'];
        $staffName = trim($_POST['staff_name']);
        $role = $_POST['role'];
        $newPin = $_POST['staff_pin'] ?? '';

        $data = loadJsonFile(USERS_FILE);

        foreach ($data['staff'] as &$staffMember) {
            if ($staffMember['id'] === $staffId) {
                $staffMember['name'] = $staffName;
                $staffMember['role'] = $role;

                // Only update PIN if a new one was provided
                if (!empty($newPin) && strlen($newPin) >= 4) {
                    $staffMember['pin_hash'] = hashPin($newPin);
                }
                break;
            }
        }

        if (saveJsonFile(USERS_FILE, $data)) {
            $message = 'Staff member "' . $staffName . '" updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error updating staff member. Please try again.';
            $messageType = 'error';
        }
    }

    // Handle deleting staff member
    if ($_POST['action'] === 'delete_staff' && isStaffLoggedIn()) {
        $staffId = $_POST['staff_id'];
        $staffName = $_POST['staff_name'] ?? 'Staff';

        // Prevent deleting yourself
        $currentStaff = getCurrentStaff();
        if ($currentStaff['id'] === $staffId) {
            $message = 'You cannot delete your own account!';
            $messageType = 'error';
        } else {
            $data = loadJsonFile(USERS_FILE);

            // Make sure at least one admin remains
            $adminCount = 0;
            $deletingAdmin = false;
            foreach ($data['staff'] as $staffMember) {
                if ($staffMember['role'] === 'admin') {
                    $adminCount++;
                }
                if ($staffMember['id'] === $staffId && $staffMember['role'] === 'admin') {
                    $deletingAdmin = true;
                }
            }

            if ($deletingAdmin && $adminCount <= 1) {
                $message = 'Cannot delete the last admin account!';
                $messageType = 'error';
            } else {
                $data['staff'] = array_filter($data['staff'], function($staffMember) use ($staffId) {
                    return $staffMember['id'] !== $staffId;
                });
                // Re-index array after filtering
                $data['staff'] = array_values($data['staff']);

                if (saveJsonFile(USERS_FILE, $data)) {
                    $message = 'Staff member "' . $staffName . '" deleted successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error deleting staff member. Please try again.';
                    $messageType = 'error';
                }
            }
        }
    }

    // Handle editing check-in/out times
    if ($_POST['action'] === 'edit_times' && isStaffLoggedIn()) {
        $recordId = $_POST['record_id'];
        $month = $_POST['month'];
        $checkInTime = $_POST['check_in_time'];
        $checkOutTime = $_POST['check_out_time'] ?? null;

        $filepath = getTimekeepingFile($month);
        $data = loadJsonFile($filepath);

        foreach ($data['records'] as &$record) {
            if ($record['id'] === $recordId) {
                $record['check_in_time'] = $checkInTime;

                if (!empty($checkOutTime)) {
                    $record['check_out_time'] = $checkOutTime;

                    // Recalculate duration and overage
                    $durationHours = calculateHours($checkInTime, $checkOutTime);
                    $record['duration_hours'] = $durationHours;

                    $overageMinutes = 0;
                    $overageCharge = 0;

                    if ($durationHours > MAX_HOURS_PER_DAY) {
                        $overageHours = $durationHours - MAX_HOURS_PER_DAY;
                        $overageMinutes = round($overageHours * 60);
                        $overageCharge = $overageMinutes * OVERAGE_RATE_PER_MINUTE;
                    }

                    $record['overage_minutes'] = $overageMinutes;
                    $record['overage_charge'] = $overageCharge;

                    // Recalculate late pickup fee
                    $latePickupMinutes = 0;
                    $latePickupCharge = 0;

                    $closingTime = strtotime($record['date'] . ' ' . DAYCARE_CLOSING_TIME);
                    $checkOutDateTime = strtotime($record['date'] . ' ' . $checkOutTime);

                    if ($checkOutDateTime > $closingTime) {
                        $lateSeconds = $checkOutDateTime - $closingTime;
                        $latePickupMinutes = ceil($lateSeconds / 60);
                        $latePickupCharge = $latePickupMinutes * LATE_PICKUP_RATE_PER_MINUTE;
                    }

                    $record['late_pickup_minutes'] = $latePickupMinutes;
                    $record['late_pickup_charge'] = $latePickupCharge;
                }

                break;
            }
        }

        if (saveJsonFile($filepath, $data)) {
            $message = 'Times updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error updating times. Please try again.';
            $messageType = 'error';
        }
    }

    // Handle updating settings
    if ($_POST['action'] === 'update_settings' && isStaffLoggedIn()) {
        $closingTime = $_POST['closing_time'];
        $maxHours = floatval($_POST['max_hours']);
        $overageRate = floatval($_POST['overage_rate']);
        $latePickupRate = floatval($_POST['late_pickup_rate']);
        $timezone = $_POST['timezone'];
        $daycareName = trim($_POST['daycare_name']);

        // Validate closing time format
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $closingTime)) {
            $closingTime .= ':00'; // Add seconds if not provided
        }

        $newSettings = [
            'daycare_closing_time' => $closingTime,
            'max_hours_per_day' => $maxHours,
            'overage_rate_per_minute' => $overageRate,
            'late_pickup_rate_per_minute' => $latePickupRate,
            'timezone' => $timezone,
            'daycare_name' => $daycareName
        ];

        if (saveSettings($newSettings)) {
            $message = 'Settings updated successfully! Please refresh the page for changes to take effect.';
            $messageType = 'success';
        } else {
            $message = 'Error saving settings. Please try again.';
            $messageType = 'error';
        }
    }

    // Handle adding new check-in/out record
    if ($_POST['action'] === 'add_record' && isStaffLoggedIn()) {
        $childId = $_POST['child_id'];
        $date = $_POST['date'];
        $checkInTime = $_POST['check_in_time'];
        $checkOutTime = $_POST['check_out_time'] ?? null;

        // Get child name from families data
        $familiesData = loadJsonFile(FAMILIES_FILE);
        $childName = '';
        foreach ($familiesData['families'] as $family) {
            foreach ($family['children'] as $child) {
                if ($child['id'] === $childId) {
                    $childName = $child['first_name'] . ' ' . $child['last_name'];
                    break 2;
                }
            }
        }

        // Get the month from the date (YYYY-MM format)
        $month = substr($date, 0, 7);
        $filepath = getTimekeepingFile($month);
        $data = loadJsonFile($filepath);

        // Check if child already has a record for this date
        $existingRecord = false;
        foreach ($data['records'] as $record) {
            if ($record['child_id'] === $childId && $record['date'] === $date) {
                $existingRecord = true;
                break;
            }
        }

        if ($existingRecord) {
            $message = 'A record already exists for this child on this date. Please edit the existing record instead.';
            $messageType = 'error';
        } else {
            // Calculate duration and overage if check-out time is provided
            $durationHours = null;
            $overageMinutes = 0;
            $overageCharge = 0;
            $latePickupMinutes = 0;
            $latePickupCharge = 0;

            if (!empty($checkOutTime)) {
                $durationHours = calculateHours($checkInTime, $checkOutTime);

                if ($durationHours > MAX_HOURS_PER_DAY) {
                    $overageHours = $durationHours - MAX_HOURS_PER_DAY;
                    $overageMinutes = round($overageHours * 60);
                    $overageCharge = $overageMinutes * OVERAGE_RATE_PER_MINUTE;
                }

                // Calculate late pickup fee
                $closingTime = strtotime($date . ' ' . DAYCARE_CLOSING_TIME);
                $checkOutDateTime = strtotime($date . ' ' . $checkOutTime);

                if ($checkOutDateTime > $closingTime) {
                    $lateSeconds = $checkOutDateTime - $closingTime;
                    $latePickupMinutes = ceil($lateSeconds / 60);
                    $latePickupCharge = $latePickupMinutes * LATE_PICKUP_RATE_PER_MINUTE;
                }
            }

            // Create new record
            $newRecord = [
                'id' => generateId('rec_'),
                'child_id' => $childId,
                'child_name' => $childName,
                'date' => $date,
                'check_in_time' => $checkInTime,
                'check_out_time' => $checkOutTime,
                'duration_hours' => $durationHours,
                'overage_minutes' => $overageMinutes,
                'overage_charge' => $overageCharge,
                'late_pickup_minutes' => $latePickupMinutes,
                'late_pickup_charge' => $latePickupCharge,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => 'staff'
            ];

            $data['records'][] = $newRecord;

            if (saveJsonFile($filepath, $data)) {
                $message = 'Record added successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error adding record. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

$staff = getCurrentStaff();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Aracely's Daycare Timekeeper</title>
    <link rel="stylesheet" href="/css/styles.css">
    <script src="/js/calendar.js"></script>
</head>
<body>
    <div class="container">
        <?php if (!$staff): ?>
            <!-- ADMIN LOGIN SCREEN -->
            <div class="card card-small" style="margin-top: 50px;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <img src="/images/logo.png" alt="Aracely's Daycare" style="max-width: 200px; height: auto;">
                </div>
                <h1>Staff Login</h1>
                <p class="text-center" style="margin-bottom: 30px; color: #4a5568;">
                    Enter your staff PIN to access the admin dashboard.
                </p>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/admin.php">
                    <input type="hidden" name="action" value="login">

                    <div class="form-group">
                        <label for="pin">Staff PIN</label>
                        <input
                            type="password"
                            id="pin"
                            name="pin"
                            class="pin-input"
                            maxlength="6"
                            required
                            autofocus
                            placeholder="••••••"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary btn-full btn-large">
                        Staff Sign In
                    </button>
                </form>

                <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px solid #e2e8f0;">
                    <p style="color: #718096; font-size: 0.9em;">
                        Parents can <a href="/index.php" style="color: #5a67d8; font-weight: 600;">check in here</a>
                    </p>
                </div>
            </div>

        <?php else: ?>
            <!-- ADMIN DASHBOARD -->
            <div style="text-align: center; margin-bottom: 20px;">
                <img src="/images/logo.png" alt="Aracely's Daycare" style="max-width: 200px; height: auto;">
            </div>
            <div class="header">
                <div>
                    <h1>Aracely's Daycare - Admin Dashboard</h1>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($staff['name']); ?> (Staff)</div>
                    <a href="?logout=1" class="btn btn-secondary" style="padding: 8px 20px; font-size: 0.9em;">
                        Sign Out
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Stats Dashboard -->
            <?php
            $checkedInChildren = getCurrentlyCheckedIn();
            $familiesData = loadJsonFile(FAMILIES_FILE);
            $totalFamilies = count($familiesData['families'] ?? []);
            $totalChildren = 0;
            foreach ($familiesData['families'] as $fam) {
                $totalChildren += count($fam['children']);
            }
            ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($checkedInChildren); ?></div>
                    <div class="stat-label">Currently Checked In</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalChildren; ?></div>
                    <div class="stat-label">Total Children</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalFamilies; ?></div>
                    <div class="stat-label">Total Families</div>
                </div>
            </div>

            <!-- Currently Checked In -->
            <div class="card">
                <h2>Currently Checked In</h2>
                <?php if (empty($checkedInChildren)): ?>
                    <p style="color: #718096; text-align: center; padding: 40px;">
                        No children are currently checked in.
                    </p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Child Name</th>
                                <th>Family</th>
                                <th>Check-in Time</th>
                                <th>Hours So Far</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($checkedInChildren as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['child']['first_name'] . ' ' . $item['child']['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['family']); ?></td>
                                    <td><?php echo formatTime($item['check_in_time']); ?></td>
                                    <td><?php echo number_format($item['hours_so_far'], 2); ?></td>
                                    <td>
                                        <?php if ($item['hours_so_far'] > MAX_HOURS_PER_DAY): ?>
                                            <span style="color: #e53e3e; font-weight: bold;">⚠️ OVERTIME</span>
                                        <?php else: ?>
                                            <span style="color: #48bb78;">✓ Within Limit</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Navigation Tabs -->
            <div class="nav-tabs">
                <button class="nav-tab active" onclick="showTab('families')">Manage Families</button>
                <button class="nav-tab" onclick="showTab('staff')">Manage Staff</button>
                <button class="nav-tab" onclick="showTab('times')">Edit Times</button>
                <button class="nav-tab" onclick="showTab('settings')">Settings</button>
                <a href="/reports.php" class="nav-tab" style="text-decoration: none; display: inline-block;">Reports</a>
            </div>

            <!-- Manage Families Tab -->
            <div id="tab-families" class="tab-content">
                <!-- Add New Family -->
                <div class="card">
                    <h3>Add New Family</h3>
                    <form method="POST" action="/admin.php">
                        <input type="hidden" name="action" value="add_family">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label>Family Name</label>
                                <input type="text" name="family_name" required placeholder="Smith Family">
                            </div>
                            <div class="form-group">
                                <label>Family PIN (4-6 digits)</label>
                                <input type="password" name="family_pin" required minlength="4" maxlength="6" placeholder="1234">
                            </div>
                            <div class="form-group">
                                <label>Contact Phone</label>
                                <input type="tel" name="phone" placeholder="555-0123">
                            </div>
                            <div class="form-group">
                                <label>Contact Email</label>
                                <input type="email" name="email" placeholder="family@example.com">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success">Add Family</button>
                    </form>
                </div>

                <!-- Existing Families -->
                <div class="card">
                    <h3>Existing Families</h3>
                    <?php foreach ($familiesData['families'] as $family): ?>
                        <div style="background: #f7fafc; padding: 20px; border-radius: 10px; margin-bottom: 20px; border: 2px solid #e2e8f0;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                <h3 style="color: #2d3748; margin: 0;">
                                    <?php echo htmlspecialchars($family['name']); ?>
                                </h3>
                                <div style="display: flex; gap: 10px;">
                                    <button onclick="toggleEdit('family-<?php echo $family['id']; ?>')" class="btn btn-primary" style="padding: 8px 15px; font-size: 0.9em;">
                                        ✏️ Edit Family
                                    </button>
                                    <button onclick="confirmDeleteFamily('<?php echo $family['id']; ?>', '<?php echo htmlspecialchars($family['name'], ENT_QUOTES); ?>')" class="btn btn-danger" style="padding: 8px 15px; font-size: 0.9em;">
                                        🗑️ Delete Family
                                    </button>
                                </div>
                            </div>

                            <p style="color: #4a5568; margin-bottom: 15px;">
                                📞 <?php echo htmlspecialchars($family['contact_phone']); ?> |
                                ✉️ <?php echo htmlspecialchars($family['contact_email']); ?>
                            </p>

                            <!-- Edit Family Form (Hidden by default) -->
                            <div id="family-<?php echo $family['id']; ?>" class="hidden" style="margin-bottom: 20px; padding: 15px; background: white; border-radius: 8px; border: 2px solid #5a67d8;">
                                <h4 style="color: #5a67d8; margin-bottom: 15px;">Edit Family Details</h4>
                                <form method="POST" action="/admin.php">
                                    <input type="hidden" name="action" value="edit_family">
                                    <input type="hidden" name="family_id" value="<?php echo $family['id']; ?>">

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                        <div class="form-group">
                                            <label>Family Name</label>
                                            <input type="text" name="family_name" value="<?php echo htmlspecialchars($family['name']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>New PIN (leave empty to keep current)</label>
                                            <input type="password" name="family_pin" minlength="4" maxlength="6" placeholder="Optional">
                                        </div>
                                        <div class="form-group">
                                            <label>Contact Phone</label>
                                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($family['contact_phone']); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Contact Email</label>
                                            <input type="email" name="email" value="<?php echo htmlspecialchars($family['contact_email']); ?>">
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                                        <button type="submit" class="btn btn-success">Save Changes</button>
                                        <button type="button" onclick="toggleEdit('family-<?php echo $family['id']; ?>')" class="btn btn-secondary">Cancel</button>
                                    </div>
                                </form>
                            </div>

                            <!-- Children List -->
                            <div style="margin-top: 15px;">
                                <strong style="color: #4a5568;">Children:</strong>
                                <?php if (empty($family['children'])): ?>
                                    <p style="color: #718096; margin-top: 5px;">No children added yet.</p>
                                <?php else: ?>
                                    <div style="margin-top: 10px;">
                                        <?php foreach ($family['children'] as $child): ?>
                                            <div style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 10px; border: 1px solid #e2e8f0;">
                                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                                    <div style="display: flex; gap: 15px; align-items: center;">
                                                        <?php if (!empty($child['photo'])): ?>
                                                            <img src="<?php echo $child['photo']; ?>" alt="<?php echo htmlspecialchars($child['first_name']); ?>" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0;">
                                                        <?php endif; ?>
                                                        <div>
                                                            <strong style="color: #2d3748;">
                                                                <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                                                            </strong>
                                                            <span style="color: #718096; margin-left: 10px;">
                                                                DOB: <?php echo $child['birth_date']; ?>
                                                            </span>
                                                            <?php if ($child['notes']): ?>
                                                                <br><em style="color: #718096; font-size: 0.9em;">Note: <?php echo htmlspecialchars($child['notes']); ?></em>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div style="display: flex; gap: 5px;">
                                                        <button onclick="toggleEdit('child-<?php echo $child['id']; ?>')" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.85em;">
                                                            ✏️ Edit
                                                        </button>
                                                        <button onclick="confirmDeleteChild('<?php echo $family['id']; ?>', '<?php echo $child['id']; ?>', '<?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name'], ENT_QUOTES); ?>')" class="btn btn-danger" style="padding: 5px 10px; font-size: 0.85em;">
                                                            🗑️
                                                        </button>
                                                    </div>
                                                </div>

                                                <!-- Edit Child Form (Hidden by default) -->
                                                <div id="child-<?php echo $child['id']; ?>" class="hidden" style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #e2e8f0;">
                                                    <h5 style="color: #5a67d8; margin-bottom: 10px;">Edit Child</h5>
                                                    <form method="POST" action="/admin.php" enctype="multipart/form-data">
                                                        <input type="hidden" name="action" value="edit_child">
                                                        <input type="hidden" name="family_id" value="<?php echo $family['id']; ?>">
                                                        <input type="hidden" name="child_id" value="<?php echo $child['id']; ?>">

                                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                                            <div class="form-group">
                                                                <label>First Name</label>
                                                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($child['first_name']); ?>" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Last Name</label>
                                                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($child['last_name']); ?>" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Birth Date</label>
                                                                <input type="date" name="birth_date" value="<?php echo $child['birth_date']; ?>" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Notes</label>
                                                                <input type="text" name="notes" value="<?php echo htmlspecialchars($child['notes']); ?>" placeholder="Optional">
                                                            </div>
                                                        </div>

                                                        <!-- Photo Upload Section -->
                                                        <div class="form-group" style="margin-top: 10px;">
                                                            <label>Child Photo</label>
                                                            <?php if (!empty($child['photo'])): ?>
                                                                <div style="margin-bottom: 10px;">
                                                                    <img src="<?php echo $child['photo']; ?>" alt="Current photo" style="max-width: 120px; max-height: 120px; border-radius: 10px; border: 2px solid #e2e8f0;">
                                                                    <p style="color: #718096; font-size: 0.9em; margin-top: 5px;">Current photo (upload a new one to replace)</p>
                                                                </div>
                                                            <?php endif; ?>
                                                            <input type="file" name="child_photo" accept="image/jpeg,image/png,image/gif,image/webp" style="padding: 8px;">
                                                            <small style="color: #4a5568; display: block; margin-top: 5px;">
                                                                Accepted formats: JPG, PNG, GIF, WEBP. Image will be stored as base64.
                                                            </small>
                                                        </div>

                                                        <div style="display: flex; gap: 10px; margin-top: 10px;">
                                                            <button type="submit" class="btn btn-success" style="padding: 8px 15px; font-size: 0.9em;">Save</button>
                                                            <button type="button" onclick="toggleEdit('child-<?php echo $child['id']; ?>')" class="btn btn-secondary" style="padding: 8px 15px; font-size: 0.9em;">Cancel</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Add Child Form -->
                            <details style="margin-top: 15px;">
                                <summary style="cursor: pointer; color: #5a67d8; font-weight: 600;">
                                    + Add Child to This Family
                                </summary>
                                <form method="POST" action="/admin.php" style="margin-top: 15px; padding: 15px; background: white; border-radius: 8px;">
                                    <input type="hidden" name="action" value="add_child">
                                    <input type="hidden" name="family_id" value="<?php echo $family['id']; ?>">

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                        <div class="form-group">
                                            <label>First Name</label>
                                            <input type="text" name="first_name" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Last Name</label>
                                            <input type="text" name="last_name" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Birth Date</label>
                                            <input type="date" name="birth_date" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Notes (allergies, etc.)</label>
                                            <input type="text" name="notes" placeholder="Optional">
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Add Child</button>
                                </form>
                            </details>

                            <!-- Hidden Delete Family Form -->
                            <form id="delete-family-<?php echo $family['id']; ?>" method="POST" action="/admin.php" style="display: none;">
                                <input type="hidden" name="action" value="delete_family">
                                <input type="hidden" name="family_id" value="<?php echo $family['id']; ?>">
                                <input type="hidden" name="family_name" value="<?php echo htmlspecialchars($family['name']); ?>">
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Manage Staff Tab -->
            <div id="tab-staff" class="tab-content hidden">
                <!-- Add New Staff Member -->
                <div class="card">
                    <h3>Add New Staff Member</h3>
                    <form method="POST" action="/admin.php">
                        <input type="hidden" name="action" value="add_staff">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label>Staff Name</label>
                                <input type="text" name="staff_name" required placeholder="John Doe">
                            </div>
                            <div class="form-group">
                                <label>Staff PIN (4-6 digits)</label>
                                <input type="password" name="staff_pin" required minlength="4" maxlength="6" placeholder="1234">
                            </div>
                            <div class="form-group">
                                <label>Role</label>
                                <select name="role" required>
                                    <option value="staff">Staff</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success">Add Staff Member</button>
                    </form>
                </div>

                <!-- Existing Staff Members -->
                <div class="card">
                    <h3>Existing Staff Members</h3>
                    <?php
                    $usersData = loadJsonFile(USERS_FILE);
                    $currentStaffId = getCurrentStaff()['id'];
                    foreach ($usersData['staff'] as $staffMember):
                    ?>
                        <div style="background: #f7fafc; padding: 20px; border-radius: 10px; margin-bottom: 20px; border: 2px solid #e2e8f0;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                <div>
                                    <h3 style="color: #2d3748; margin: 0 0 5px 0;">
                                        <?php echo htmlspecialchars($staffMember['name']); ?>
                                        <?php if ($staffMember['id'] === $currentStaffId): ?>
                                            <span style="background: #5a67d8; color: white; padding: 3px 10px; border-radius: 5px; font-size: 0.7em; margin-left: 10px;">YOU</span>
                                        <?php endif; ?>
                                    </h3>
                                    <p style="color: #4a5568; margin: 0;">
                                        <span style="font-weight: 600;">Role:</span>
                                        <span style="background: <?php echo $staffMember['role'] === 'admin' ? '#ed8936' : '#48bb78'; ?>; color: white; padding: 2px 8px; border-radius: 5px; font-size: 0.9em; margin-left: 5px;">
                                            <?php echo ucfirst($staffMember['role']); ?>
                                        </span>
                                        <span style="margin-left: 15px; color: #718096;">
                                            Created: <?php echo date('M j, Y', strtotime($staffMember['created_at'])); ?>
                                        </span>
                                    </p>
                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <button onclick="toggleEdit('staff-<?php echo $staffMember['id']; ?>')" class="btn btn-primary" style="padding: 8px 15px; font-size: 0.9em;">
                                        ✏️ Edit
                                    </button>
                                    <button onclick="confirmDeleteStaff('<?php echo $staffMember['id']; ?>', '<?php echo htmlspecialchars($staffMember['name'], ENT_QUOTES); ?>')" class="btn btn-danger" style="padding: 8px 15px; font-size: 0.9em;">
                                        🗑️ Delete
                                    </button>
                                </div>
                            </div>

                            <!-- Edit Staff Form (Hidden by default) -->
                            <div id="staff-<?php echo $staffMember['id']; ?>" class="hidden" style="margin-top: 20px; padding: 15px; background: white; border-radius: 8px; border: 2px solid #5a67d8;">
                                <h4 style="color: #5a67d8; margin-bottom: 15px;">Edit Staff Member</h4>
                                <form method="POST" action="/admin.php">
                                    <input type="hidden" name="action" value="edit_staff">
                                    <input type="hidden" name="staff_id" value="<?php echo $staffMember['id']; ?>">

                                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                                        <div class="form-group">
                                            <label>Staff Name</label>
                                            <input type="text" name="staff_name" value="<?php echo htmlspecialchars($staffMember['name']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>New PIN (leave empty to keep current)</label>
                                            <input type="password" name="staff_pin" minlength="4" maxlength="6" placeholder="Optional">
                                        </div>
                                        <div class="form-group">
                                            <label>Role</label>
                                            <select name="role" required>
                                                <option value="staff" <?php echo $staffMember['role'] === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                                <option value="admin" <?php echo $staffMember['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                                        <button type="submit" class="btn btn-success">Save Changes</button>
                                        <button type="button" onclick="toggleEdit('staff-<?php echo $staffMember['id']; ?>')" class="btn btn-secondary">Cancel</button>
                                    </div>
                                </form>
                            </div>

                            <!-- Hidden Delete Staff Form -->
                            <form id="delete-staff-<?php echo $staffMember['id']; ?>" method="POST" action="/admin.php" style="display: none;">
                                <input type="hidden" name="action" value="delete_staff">
                                <input type="hidden" name="staff_id" value="<?php echo $staffMember['id']; ?>">
                                <input type="hidden" name="staff_name" value="<?php echo htmlspecialchars($staffMember['name']); ?>">
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Edit Times Tab -->
            <div id="tab-times" class="tab-content hidden">
                <?php
                // Get parameters
                $selectedDate = $_GET['date'] ?? getTodayDate();
                $filterChildId = $_GET['child_filter'] ?? 'all';

                // Load all data
                $familiesData = loadJsonFile(FAMILIES_FILE);

                // Get all records for current month (for calendar)
                $calendarMonth = date('Y-m', strtotime($selectedDate));
                $calendarFilepath = getTimekeepingFile($calendarMonth);
                $calendarData = loadJsonFile($calendarFilepath);

                // Count records by date for calendar
                $recordsByDate = [];
                foreach ($calendarData['records'] as $record) {
                    if (!isset($recordsByDate[$record['date']])) {
                        $recordsByDate[$record['date']] = 0;
                    }
                    $recordsByDate[$record['date']]++;
                }

                // Get records for selected date
                $selectedMonth = date('Y-m', strtotime($selectedDate));
                $selectedFilepath = getTimekeepingFile($selectedMonth);
                $selectedData = loadJsonFile($selectedFilepath);

                $dateRecords = [];
                foreach ($selectedData['records'] as $record) {
                    if ($record['date'] === $selectedDate) {
                        // Find child info
                        foreach ($familiesData['families'] as $family) {
                            foreach ($family['children'] as $child) {
                                if ($child['id'] === $record['child_id']) {
                                    // Apply filter
                                    if ($filterChildId === 'all' || $filterChildId === $child['id']) {
                                        $dateRecords[] = [
                                            'record' => $record,
                                            'child' => $child,
                                            'family' => $family['name']
                                        ];
                                    }
                                    break 2;
                                }
                            }
                        }
                    }
                }
                ?>

                <!-- Filter Section -->
                <div class="card">
                    <h3>Filter Options</h3>
                    <form method="GET" action="/admin.php" style="display: grid; grid-template-columns: 2fr auto; gap: 20px; align-items: end;">
                        <input type="hidden" name="date" value="<?php echo $selectedDate; ?>">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Filter by Child</label>
                            <select name="child_filter" onchange="this.form.submit()">
                                <option value="all" <?php echo $filterChildId === 'all' ? 'selected' : ''; ?>>All Children</option>
                                <?php foreach ($familiesData['families'] as $family): ?>
                                    <?php foreach ($family['children'] as $child): ?>
                                        <option value="<?php echo $child['id']; ?>" <?php echo $filterChildId === $child['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name'] . ' (' . $family['name'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Apply Filter</button>
                    </form>
                </div>

                <!-- Calendar View -->
                <div class="card">
                    <h3>Select Date</h3>
                    <script>
                        // Generate calendar with record counts
                        const recordsByDate = <?php echo json_encode($recordsByDate); ?>;
                        const currentYear = <?php echo date('Y', strtotime($selectedDate)); ?>;
                        const currentMonth = <?php echo date('n', strtotime($selectedDate)) - 1; ?>; // JS months are 0-indexed

                        function updateCalendar() {
                            const calendarHtml = generateCalendar(currentDate.getFullYear(), currentDate.getMonth(), recordsByDate);
                            document.getElementById('calendar-display').innerHTML = calendarHtml;
                        }

                        // Override selectDate to keep child filter
                        function selectDate(dateStr) {
                            const urlParams = new URLSearchParams(window.location.search);
                            urlParams.set('date', dateStr);
                            // Keep existing child filter
                            const childFilter = '<?php echo $filterChildId; ?>';
                            if (childFilter && childFilter !== 'all') {
                                urlParams.set('child_filter', childFilter);
                            }
                            window.location.search = urlParams.toString();
                        }

                        currentDate = new Date(currentYear, currentMonth, 1);
                        selectedDate = '<?php echo $selectedDate; ?>';

                        // Generate initial calendar
                        document.addEventListener('DOMContentLoaded', function() {
                            updateCalendar();
                        });
                    </script>
                    <div id="calendar-display"></div>
                </div>

                <!-- Selected Date Records -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0;">Records for <?php echo date('F j, Y', strtotime($selectedDate)); ?></h3>
                        <button onclick="toggleEdit('add-new-record')" class="btn btn-success" style="padding: 8px 20px; font-size: 0.95em;">
                            ➕ Add New Record
                        </button>
                    </div>

                    <!-- Add New Record Form (Hidden by default) -->
                    <div id="add-new-record" class="hidden" style="background: #f0fff4; padding: 20px; border-radius: 10px; margin-bottom: 20px; border: 2px solid #48bb78;">
                        <h4 style="color: #22543d; margin-bottom: 15px;">Add New Check-In/Out Record</h4>
                        <form method="POST" action="/admin.php?date=<?php echo $selectedDate; ?>&child_filter=<?php echo $filterChildId; ?>">
                            <input type="hidden" name="action" value="add_record">
                            <input type="hidden" name="date" value="<?php echo $selectedDate; ?>">

                            <div class="form-group">
                                <label>Select Child *</label>
                                <select name="child_id" required>
                                    <option value="">-- Select a child --</option>
                                    <?php foreach ($familiesData['families'] as $family): ?>
                                        <?php foreach ($family['children'] as $child): ?>
                                            <option value="<?php echo $child['id']; ?>">
                                                <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name'] . ' (' . $family['name'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label>Check-In Time *</label>
                                    <input type="time" name="check_in_time" required>
                                </div>
                                <div class="form-group">
                                    <label>Check-Out Time (optional)</label>
                                    <input type="time" name="check_out_time">
                                    <small style="color: #4a5568; display: block; margin-top: 5px;">
                                        Leave empty if child is still checked in
                                    </small>
                                </div>
                            </div>

                            <div style="display: flex; gap: 10px; margin-top: 10px;">
                                <button type="submit" class="btn btn-success">Add Record</button>
                                <button type="button" onclick="toggleEdit('add-new-record')" class="btn btn-secondary">Cancel</button>
                            </div>
                        </form>
                    </div>

                    <?php if (empty($dateRecords)): ?>
                        <p style="text-align: center; color: #718096; padding: 40px;">
                            No check-ins recorded for this date<?php echo $filterChildId !== 'all' ? ' with selected filter' : ''; ?>.
                        </p>
                    <?php else: ?>
                        <?php foreach ($dateRecords as $item): ?>
                            <div style="background: #f7fafc; padding: 20px; border-radius: 10px; margin-bottom: 20px; border: 2px solid #e2e8f0;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                                    <div>
                                        <h3 style="color: #2d3748; margin: 0 0 5px 0;">
                                            <?php echo htmlspecialchars($item['child']['first_name'] . ' ' . $item['child']['last_name']); ?>
                                        </h3>
                                        <p style="color: #4a5568; margin: 0;">
                                            Family: <?php echo htmlspecialchars($item['family']); ?>
                                        </p>
                                    </div>
                                    <button onclick="toggleEdit('time-<?php echo $item['record']['id']; ?>')" class="btn btn-primary" style="padding: 8px 15px; font-size: 0.9em;">
                                        ✏️ Edit Times
                                    </button>
                                </div>

                                <!-- Display Current Times -->
                                <div style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                                        <div>
                                            <strong style="color: #4a5568;">Check-In:</strong>
                                            <p style="font-size: 1.2em; color: #2d3748; margin: 5px 0 0 0;">
                                                <?php echo formatTime($item['record']['check_in_time']); ?>
                                            </p>
                                        </div>
                                        <div>
                                            <strong style="color: #4a5568;">Check-Out:</strong>
                                            <p style="font-size: 1.2em; color: #2d3748; margin: 5px 0 0 0;">
                                                <?php echo $item['record']['check_out_time'] ? formatTime($item['record']['check_out_time']) : '<em style="color: #718096;">Not checked out</em>'; ?>
                                            </p>
                                        </div>
                                        <div>
                                            <strong style="color: #4a5568;">Duration:</strong>
                                            <p style="font-size: 1.2em; color: #2d3748; margin: 5px 0 0 0;">
                                                <?php
                                                if ($item['record']['duration_hours']) {
                                                    echo number_format($item['record']['duration_hours'], 2) . ' hrs';
                                                    if ($item['record']['overage_minutes'] > 0) {
                                                        echo ' <span style="color: #e53e3e; font-weight: bold;">(+' . $item['record']['overage_minutes'] . ' min OT)</span>';
                                                    }
                                                } else {
                                                    echo '<em style="color: #718096;">In progress</em>';
                                                }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit Form (Hidden by default) -->
                                <div id="time-<?php echo $item['record']['id']; ?>" class="hidden" style="padding: 15px; background: white; border-radius: 8px; border: 2px solid #5a67d8;">
                                    <h4 style="color: #5a67d8; margin-bottom: 15px;">Edit Times</h4>
                                    <form method="POST" action="/admin.php?date=<?php echo $selectedDate; ?>&child_filter=<?php echo $filterChildId; ?>">
                                        <input type="hidden" name="action" value="edit_times">
                                        <input type="hidden" name="record_id" value="<?php echo $item['record']['id']; ?>">
                                        <input type="hidden" name="month" value="<?php echo $selectedMonth; ?>">

                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                            <div class="form-group">
                                                <label>Check-In Time</label>
                                                <input type="time" name="check_in_time" value="<?php echo $item['record']['check_in_time']; ?>" required step="60">
                                            </div>
                                            <div class="form-group">
                                                <label>Check-Out Time (leave empty if not checked out)</label>
                                                <input type="time" name="check_out_time" value="<?php echo $item['record']['check_out_time'] ?? ''; ?>" step="60">
                                            </div>
                                        </div>
                                        <div style="display: flex; gap: 10px; margin-top: 10px;">
                                            <button type="submit" class="btn btn-success">Save Changes</button>
                                            <button type="button" onclick="toggleEdit('time-<?php echo $item['record']['id']; ?>')" class="btn btn-secondary">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Settings Tab -->
            <div id="tab-settings" class="tab-content hidden">
                <div class="card">
                    <h3>Daycare Settings</h3>
                    <p style="color: #4a5568; margin-bottom: 20px;">
                        Configure important business rules and settings for your daycare. Changes take effect immediately after saving.
                    </p>

                    <?php
                    $currentSettings = loadSettings();
                    ?>

                    <form method="POST" action="/admin.php">
                        <input type="hidden" name="action" value="update_settings">

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label>Daycare Name</label>
                                <input type="text" name="daycare_name" value="<?php echo htmlspecialchars($currentSettings['daycare_name']); ?>" required>
                                <small style="color: #4a5568; display: block; margin-top: 5px;">
                                    This appears on reports and documents
                                </small>
                            </div>

                            <div class="form-group">
                                <label>Timezone</label>
                                <select name="timezone" required>
                                    <option value="America/New_York" <?php echo $currentSettings['timezone'] === 'America/New_York' ? 'selected' : ''; ?>>Eastern Time</option>
                                    <option value="America/Chicago" <?php echo $currentSettings['timezone'] === 'America/Chicago' ? 'selected' : ''; ?>>Central Time</option>
                                    <option value="America/Denver" <?php echo $currentSettings['timezone'] === 'America/Denver' ? 'selected' : ''; ?>>Mountain Time</option>
                                    <option value="America/Phoenix" <?php echo $currentSettings['timezone'] === 'America/Phoenix' ? 'selected' : ''; ?>>Arizona Time</option>
                                    <option value="America/Los_Angeles" <?php echo $currentSettings['timezone'] === 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time</option>
                                    <option value="America/Anchorage" <?php echo $currentSettings['timezone'] === 'America/Anchorage' ? 'selected' : ''; ?>>Alaska Time</option>
                                    <option value="Pacific/Honolulu" <?php echo $currentSettings['timezone'] === 'Pacific/Honolulu' ? 'selected' : ''; ?>>Hawaii Time</option>
                                </select>
                                <small style="color: #4a5568; display: block; margin-top: 5px;">
                                    Your local timezone for accurate timestamps
                                </small>
                            </div>
                        </div>

                        <hr style="margin: 30px 0; border: none; border-top: 2px solid #e2e8f0;">

                        <h4 style="color: #2d3748; margin-bottom: 20px;">Operating Hours & Fees</h4>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label>Daycare Closing Time</label>
                                <input type="time" name="closing_time" value="<?php echo substr($currentSettings['daycare_closing_time'], 0, 5); ?>" required>
                                <small style="color: #4a5568; display: block; margin-top: 5px;">
                                    Late pickup fees apply after this time
                                </small>
                            </div>

                            <div class="form-group">
                                <label>Maximum Hours Per Day</label>
                                <input type="number" name="max_hours" value="<?php echo $currentSettings['max_hours_per_day']; ?>" step="0.5" min="1" max="24" required>
                                <small style="color: #4a5568; display: block; margin-top: 5px;">
                                    Overtime fees apply beyond this duration
                                </small>
                            </div>

                            <div class="form-group">
                                <label>Overtime Rate (per minute)</label>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span style="font-size: 1.2em; color: #2d3748;">$</span>
                                    <input type="number" name="overage_rate" value="<?php echo number_format($currentSettings['overage_rate_per_minute'], 2); ?>" step="0.01" min="0" required style="flex: 1;">
                                </div>
                                <small style="color: #4a5568; display: block; margin-top: 5px;">
                                    Charged for each minute over maximum hours
                                </small>
                            </div>

                            <div class="form-group">
                                <label>Late Pickup Rate (per minute)</label>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span style="font-size: 1.2em; color: #2d3748;">$</span>
                                    <input type="number" name="late_pickup_rate" value="<?php echo number_format($currentSettings['late_pickup_rate_per_minute'], 2); ?>" step="0.01" min="0" required style="flex: 1;">
                                </div>
                                <small style="color: #4a5568; display: block; margin-top: 5px;">
                                    Charged for each minute after closing time
                                </small>
                            </div>
                        </div>

                        <hr style="margin: 30px 0; border: none; border-top: 2px solid #e2e8f0;">

                        <div style="background: #fffaf0; border: 2px solid #f59e0b; border-radius: 10px; padding: 15px; margin-bottom: 20px;">
                            <div style="display: flex; align-items: start; gap: 10px;">
                                <span style="font-size: 1.5em;">⚠️</span>
                                <div>
                                    <strong style="color: #92400e;">Important:</strong>
                                    <p style="color: #78350f; margin: 5px 0 0 0;">
                                        Changes to closing time and rates will apply to all future check-outs. Existing records will not be recalculated automatically.
                                        You may need to manually edit times for pending records.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success" style="padding: 12px 30px; font-size: 1em;">
                            💾 Save Settings
                        </button>
                    </form>
                </div>
            </div>

            <!-- Tab Switching Script -->
            <script>
                function showTab(tabName) {
                    // Hide all tabs
                    document.querySelectorAll('.tab-content').forEach(tab => {
                        tab.classList.add('hidden');
                    });

                    // Remove active class from all nav tabs
                    document.querySelectorAll('.nav-tab').forEach(tab => {
                        tab.classList.remove('active');
                    });

                    // Show selected tab
                    document.getElementById('tab-' + tabName).classList.remove('hidden');

                    // Add active class to clicked nav tab
                    // Handle both click events and programmatic calls
                    if (event && event.target) {
                        event.target.classList.add('active');
                    } else {
                        // If called programmatically, find the button by tab name
                        document.querySelectorAll('.nav-tab').forEach(tab => {
                            if (tab.textContent.toLowerCase().includes(tabName)) {
                                tab.classList.add('active');
                            }
                        });
                    }
                }

                // Check URL parameters on page load and show appropriate tab
                function initializeTabs() {
                    const urlParams = new URLSearchParams(window.location.search);
                    const hasDate = urlParams.has('date');
                    const hasChildFilter = urlParams.has('child_filter');

                    // If date or child_filter parameters exist, show Edit Times tab
                    if (hasDate || hasChildFilter) {
                        // Hide all tabs first
                        document.querySelectorAll('.tab-content').forEach(tab => {
                            tab.classList.add('hidden');
                        });
                        document.querySelectorAll('.nav-tab').forEach(tab => {
                            tab.classList.remove('active');
                        });

                        // Show Edit Times tab
                        document.getElementById('tab-times').classList.remove('hidden');

                        // Mark Edit Times button as active
                        document.querySelectorAll('.nav-tab').forEach(tab => {
                            if (tab.textContent.includes('Edit Times')) {
                                tab.classList.add('active');
                            }
                        });
                    }
                }

                // Initialize tabs when page loads
                document.addEventListener('DOMContentLoaded', initializeTabs);

                // Toggle edit form visibility
                function toggleEdit(elementId) {
                    var element = document.getElementById(elementId);
                    if (element.classList.contains('hidden')) {
                        element.classList.remove('hidden');
                    } else {
                        element.classList.add('hidden');
                    }
                }

                // Confirm and delete family
                function confirmDeleteFamily(familyId, familyName) {
                    if (confirm('Are you sure you want to delete "' + familyName + '" and all their children? This cannot be undone!')) {
                        document.getElementById('delete-family-' + familyId).submit();
                    }
                }

                // Confirm and delete child
                function confirmDeleteChild(familyId, childId, childName) {
                    if (confirm('Are you sure you want to delete "' + childName + '"? This cannot be undone!')) {
                        // Create a temporary form to submit the delete request
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '/admin.php';

                        var actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'action';
                        actionInput.value = 'delete_child';
                        form.appendChild(actionInput);

                        var familyInput = document.createElement('input');
                        familyInput.type = 'hidden';
                        familyInput.name = 'family_id';
                        familyInput.value = familyId;
                        form.appendChild(familyInput);

                        var childInput = document.createElement('input');
                        childInput.type = 'hidden';
                        childInput.name = 'child_id';
                        childInput.value = childId;
                        form.appendChild(childInput);

                        var nameInput = document.createElement('input');
                        nameInput.type = 'hidden';
                        nameInput.name = 'child_name';
                        nameInput.value = childName;
                        form.appendChild(nameInput);

                        document.body.appendChild(form);
                        form.submit();
                    }
                }

                // Confirm and delete staff member
                function confirmDeleteStaff(staffId, staffName) {
                    if (confirm('Are you sure you want to delete "' + staffName + '"? This cannot be undone!')) {
                        document.getElementById('delete-staff-' + staffId).submit();
                    }
                }
            </script>
        <?php endif; ?>
    </div>
</body>
</html>

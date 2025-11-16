<?php
/**
 * Parent Login and Check-in/out Interface
 *
 * This is the main page for parents to:
 * 1. Log in with their PIN
 * 2. See their children
 * 3. Check children in and out
 */

// Load required files
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/timekeeping.php';

// Initialize variables for messages
$message = '';
$messageType = '';

// Handle logout
if (isset($_GET['logout'])) {
    logout();
    header('Location: /index.php');
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $pin = $_POST['pin'] ?? '';
        $result = loginParent($pin);

        if ($result['success']) {
            // Login successful - reload page to show check-in interface
            header('Location: /index.php');
            exit;
        } else {
            $message = $result['message'];
            $messageType = 'error';
        }
    }

    // Handle check-in
    if ($_POST['action'] === 'checkin' && isParentLoggedIn()) {
        $childId = $_POST['child_id'];
        $childName = $_POST['child_name'];
        $result = checkInChild($childId, $childName);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    }

    // Handle check-out
    if ($_POST['action'] === 'checkout' && isParentLoggedIn()) {
        $childId = $_POST['child_id'];
        $childName = $_POST['child_name'];
        $result = checkOutChild($childId, $childName);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';

        // Check for overage or late pickup warning
        if ($result['success'] && ($result['overage_minutes'] > 0 || $result['late_pickup_minutes'] > 0)) {
            $messageType = 'warning';
        }
    }
}

// Get current family if logged in
$family = getCurrentFamily();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aracely's Daycare Timekeeper - Parent Portal</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <div class="container">
        <?php if (!$family): ?>
            <!-- LOGIN SCREEN -->
            <div class="card card-small" style="margin-top: 50px;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <img src="/images/logo.png" alt="Aracely's Daycare" style="max-width: 200px; height: auto;">
                </div>
                <h1>Aracely's Daycare Timekeeper</h1>
                <p class="text-center" style="margin-bottom: 30px; color: #4a5568;">
                    Welcome! Please enter your family PIN to check in or out.
                </p>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/index.php">
                    <input type="hidden" name="action" value="login">

                    <div class="form-group">
                        <label for="pin">Enter Your PIN</label>
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
                        Sign In
                    </button>
                </form>

                <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px solid #e2e8f0;">
                    <p style="color: #718096; font-size: 0.9em;">
                        Staff members can <a href="/admin.php" style="color: #5a67d8; font-weight: 600;">log in here</a>
                    </p>
                </div>
            </div>

        <?php else: ?>
            <!-- CHECK-IN/OUT SCREEN -->
            <div style="text-align: center; margin-bottom: 20px;">
                <img src="/images/logo.png" alt="Aracely's Daycare" style="max-width: 200px; height: auto;">
            </div>
            <div class="header">
                <div>
                    <h1>Aracely's Daycare Timekeeper</h1>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($family['name']); ?></div>
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

            <div class="card">
                <h2>Your Children</h2>
                <p style="color: #4a5568; margin-bottom: 20px;">
                    Tap a child to check them in or out.
                </p>

                <div class="children-grid">
                    <?php foreach ($family['children'] as $child): ?>
                        <?php
                        // Check if this child is currently checked in
                        $activeCheckIn = getActiveCheckIn($child['id']);
                        $isCheckedIn = $activeCheckIn !== null;
                        ?>

                        <div class="child-card <?php echo $isCheckedIn ? 'checked-in' : ''; ?>">
                            <!-- Child Photo -->
                            <?php if (!empty($child['photo'])): ?>
                                <img
                                    src="<?php echo $child['photo']; ?>"
                                    alt="<?php echo htmlspecialchars($child['first_name']); ?>"
                                    class="child-photo"
                                >
                            <?php else: ?>
                                <div class="child-photo-placeholder">
                                    <?php echo strtoupper(substr($child['first_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>

                            <!-- Child Name -->
                            <div class="child-name">
                                <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                            </div>

                            <!-- Status Badge -->
                            <?php if ($isCheckedIn): ?>
                                <div class="child-status status-checked-in">
                                    ✓ Checked in at <?php echo formatTime($activeCheckIn['check_in_time']); ?>
                                </div>
                            <?php else: ?>
                                <div class="child-status status-not-here">
                                    Not checked in today
                                </div>
                            <?php endif; ?>

                            <!-- Check In/Out Buttons -->
                            <div style="margin-top: 15px;">
                                <?php if (!$isCheckedIn): ?>
                                    <!-- Check In Button -->
                                    <form method="POST" action="/index.php" style="display: inline;">
                                        <input type="hidden" name="action" value="checkin">
                                        <input type="hidden" name="child_id" value="<?php echo $child['id']; ?>">
                                        <input type="hidden" name="child_name" value="<?php echo htmlspecialchars($child['first_name']); ?>">
                                        <button type="submit" class="btn btn-success btn-full">
                                            Check In
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <!-- Check Out Button -->
                                    <form method="POST" action="/index.php" style="display: inline;">
                                        <input type="hidden" name="action" value="checkout">
                                        <input type="hidden" name="child_id" value="<?php echo $child['id']; ?>">
                                        <input type="hidden" name="child_name" value="<?php echo htmlspecialchars($child['first_name']); ?>">
                                        <button type="submit" class="btn btn-danger btn-full">
                                            Check Out
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Information Card -->
            <div class="card">
                <h3>Important Information</h3>
                <ul style="color: #4a5568; line-height: 2;">
                    <li>Maximum stay time: <strong><?php echo MAX_HOURS_PER_DAY; ?> hours per day</strong></li>
                    <li>Overtime charge: <strong>$<?php echo number_format(OVERAGE_RATE_PER_MINUTE, 2); ?> per minute</strong> over <?php echo MAX_HOURS_PER_DAY; ?> hours</li>
                    <li>Daycare closes at: <strong><?php echo formatTime(DAYCARE_CLOSING_TIME); ?></strong></li>
                    <li style="color: #d9534f; font-weight: 600;">Late pickup fee: <strong>$<?php echo number_format(LATE_PICKUP_RATE_PER_MINUTE, 2); ?> per minute</strong> after closing time</li>
                    <li>Please pick up your child before <?php echo formatTime(DAYCARE_CLOSING_TIME); ?> to avoid late fees</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

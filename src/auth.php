<?php
/**
 * Authentication System
 *
 * This file handles logging in and logging out for both parents and staff.
 * It keeps track of who is currently logged in using PHP sessions.
 */

require_once __DIR__ . '/../config.php';

/**
 * Check if a parent is logged in
 *
 * @return bool - True if parent is logged in, false otherwise
 */
function isParentLoggedIn() {
    // Check if the session has a 'family_id' variable set
    return isset($_SESSION['family_id']);
}

/**
 * Check if a staff member is logged in
 *
 * @return bool - True if staff is logged in, false otherwise
 */
function isStaffLoggedIn() {
    // Check if the session has a 'staff_id' variable set
    return isset($_SESSION['staff_id']);
}

/**
 * Get the currently logged in family data
 *
 * @return array|null - Family data or null if not logged in
 */
function getCurrentFamily() {
    if (!isParentLoggedIn()) {
        return null;
    }

    // Load all families from the JSON file
    $data = loadJsonFile(FAMILIES_FILE);

    // Find the family that matches the logged in family_id
    foreach ($data['families'] as $family) {
        if ($family['id'] === $_SESSION['family_id']) {
            return $family;
        }
    }

    return null;
}

/**
 * Get the currently logged in staff member data
 *
 * @return array|null - Staff data or null if not logged in
 */
function getCurrentStaff() {
    if (!isStaffLoggedIn()) {
        return null;
    }

    // Load all staff from the JSON file
    $data = loadJsonFile(USERS_FILE);

    // Find the staff member that matches the logged in staff_id
    foreach ($data['staff'] as $staff) {
        if ($staff['id'] === $_SESSION['staff_id']) {
            return $staff;
        }
    }

    return null;
}

/**
 * Attempt to log in a parent with their PIN
 *
 * @param string $pin - The PIN entered by the parent
 * @return array - Array with 'success' (bool) and 'message' (string)
 */
function loginParent($pin) {
    // Load all families
    $data = loadJsonFile(FAMILIES_FILE);

    // Loop through each family to find a matching PIN
    foreach ($data['families'] as $family) {
        // password_verify() checks if the PIN matches the hashed version
        // This is more secure than storing PINs in plain text
        if (password_verify($pin, $family['pin_hash'])) {
            // Success! Store the family_id in the session
            $_SESSION['family_id'] = $family['id'];
            $_SESSION['user_type'] = 'parent';
            $_SESSION['family_name'] = $family['name'];

            return [
                'success' => true,
                'message' => 'Welcome back, ' . $family['name'] . '!'
            ];
        }
    }

    // No matching PIN found
    return [
        'success' => false,
        'message' => 'Invalid PIN. Please try again.'
    ];
}

/**
 * Attempt to log in a staff member with their PIN
 *
 * @param string $pin - The PIN entered by the staff member
 * @return array - Array with 'success' (bool) and 'message' (string)
 */
function loginStaff($pin) {
    // Load all staff
    $data = loadJsonFile(USERS_FILE);

    // Loop through each staff member to find a matching PIN
    foreach ($data['staff'] as $staff) {
        // Check if the PIN matches the hashed version
        if (password_verify($pin, $staff['pin_hash'])) {
            // Success! Store the staff_id in the session
            $_SESSION['staff_id'] = $staff['id'];
            $_SESSION['user_type'] = 'staff';
            $_SESSION['staff_name'] = $staff['name'];
            $_SESSION['staff_role'] = $staff['role'];

            return [
                'success' => true,
                'message' => 'Welcome back, ' . $staff['name'] . '!'
            ];
        }
    }

    // No matching PIN found
    return [
        'success' => false,
        'message' => 'Invalid PIN. Please try again.'
    ];
}

/**
 * Log out the current user (parent or staff)
 */
function logout() {
    // Clear all session variables
    $_SESSION = [];

    // Destroy the session completely
    session_destroy();

    // Start a new empty session
    session_start();
}

/**
 * Create a hashed version of a PIN for secure storage
 * This is a helper function you can use when adding new users/families
 *
 * @param string $pin - The plain text PIN
 * @return string - The hashed PIN
 */
function hashPin($pin) {
    // password_hash() creates a secure one-way hash
    // Even if someone gets the hash, they can't reverse it to get the PIN
    return password_hash($pin, PASSWORD_DEFAULT);
}

/**
 * Require parent login - redirect to login page if not logged in
 * Use this at the top of pages that only parents should access
 */
function requireParentLogin() {
    if (!isParentLoggedIn()) {
        header('Location: /index.php');
        exit;
    }
}

/**
 * Require staff login - redirect to admin login if not logged in
 * Use this at the top of pages that only staff should access
 */
function requireStaffLogin() {
    if (!isStaffLoggedIn()) {
        header('Location: /admin.php');
        exit;
    }
}

?>

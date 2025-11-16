<?php
/**
 * Daycare Timekeeper Configuration File
 *
 * This file contains all the important settings for your application.
 * Think of it like a control panel for your app.
 */

// Start a session - this keeps track of who's logged in
// Sessions are like temporary memory that remembers a user between page loads
session_start();

// Define the root directory of the application
// __DIR__ is a special PHP constant that means "the directory this file is in"
define('ROOT_DIR', __DIR__);

// Define where our data files are stored
define('DATA_DIR', ROOT_DIR . '/data');
define('TIMEKEEPING_DIR', DATA_DIR . '/timekeeping');

// File paths for our JSON data files
define('FAMILIES_FILE', DATA_DIR . '/families.json');
define('USERS_FILE', DATA_DIR . '/users.json');
define('SETTINGS_FILE', DATA_DIR . '/settings.json');

// Load settings from file (with defaults if file doesn't exist)
// Note: We need to define loadSettings() function first, then load settings
// This creates a chicken-and-egg problem, so we'll do a simple inline load here
$_appSettings = [];
if (file_exists(DATA_DIR . '/settings.json')) {
    $_appSettings = json_decode(file_get_contents(DATA_DIR . '/settings.json'), true) ?: [];
}

// Default settings
$_defaultSettings = [
    'daycare_closing_time' => '16:30:00',
    'max_hours_per_day' => 8.5,
    'overage_rate_per_minute' => 1.00,
    'late_pickup_rate_per_minute' => 1.00,
    'timezone' => 'America/Denver',
    'daycare_name' => 'Aracely\'s Daycare'
];

// Merge with defaults
$_appSettings = array_merge($_defaultSettings, $_appSettings);

// Define constants from settings
define('MAX_HOURS_PER_DAY', $_appSettings['max_hours_per_day']);
define('OVERAGE_RATE_PER_MINUTE', $_appSettings['overage_rate_per_minute']);
define('DAYCARE_CLOSING_TIME', $_appSettings['daycare_closing_time']);
define('LATE_PICKUP_RATE_PER_MINUTE', $_appSettings['late_pickup_rate_per_minute']);
define('DAYCARE_NAME', $_appSettings['daycare_name']);

// Timezone setting - load from settings
date_default_timezone_set($_appSettings['timezone']);

/**
 * Helper function to load JSON data from a file
 *
 * @param string $filepath - The path to the JSON file
 * @return array - The data from the file as a PHP array
 */
function loadJsonFile($filepath) {
    // Check if the file exists
    if (!file_exists($filepath)) {
        return []; // Return empty array if file doesn't exist
    }

    // Read the file contents
    $content = file_get_contents($filepath);

    // Convert JSON string to PHP array
    // 'true' parameter means convert to associative array instead of object
    $data = json_decode($content, true);

    // Return the data, or empty array if something went wrong
    return $data ? $data : [];
}

/**
 * Helper function to save data to a JSON file
 *
 * @param string $filepath - Where to save the file
 * @param array $data - The data to save
 * @return bool - True if successful, false if failed
 */
function saveJsonFile($filepath, $data) {
    // Convert PHP array to formatted JSON string
    // JSON_PRETTY_PRINT makes it readable by humans
    // JSON_UNESCAPED_SLASHES prevents escaping forward slashes
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    // Write the JSON string to the file
    // Returns true on success, false on failure
    return file_put_contents($filepath, $json) !== false;
}

/**
 * Helper function to generate a unique ID
 * Uses current timestamp + random number to ensure uniqueness
 *
 * @param string $prefix - Optional prefix (like 'fam_' or 'child_')
 * @return string - A unique ID
 */
function generateId($prefix = '') {
    return $prefix . time() . '_' . rand(1000, 9999);
}

/**
 * Helper function to format time nicely
 *
 * @param string $time - Time string (like "14:30:00")
 * @return string - Formatted time (like "2:30 PM")
 */
function formatTime($time) {
    return date('g:i A', strtotime($time));
}

/**
 * Helper function to calculate hours between two times
 *
 * @param string $startTime - Start time (like "08:00:00")
 * @param string $endTime - End time (like "16:30:00")
 * @return float - Hours as decimal (like 8.5)
 */
function calculateHours($startTime, $endTime) {
    $start = strtotime($startTime);
    $end = strtotime($endTime);
    $seconds = $end - $start;
    return round($seconds / 3600, 2); // Convert seconds to hours
}

/**
 * Load settings from settings.json file
 * If file doesn't exist or is corrupted, returns default settings
 *
 * @return array - Settings array
 */
function loadSettings() {
    $defaultSettings = [
        'daycare_closing_time' => '16:30:00',
        'max_hours_per_day' => 8.5,
        'overage_rate_per_minute' => 1.00,
        'late_pickup_rate_per_minute' => 1.00,
        'timezone' => 'America/Denver',
        'daycare_name' => 'Aracely\'s Daycare'
    ];

    if (!file_exists(SETTINGS_FILE)) {
        // Create settings file with defaults
        saveJsonFile(SETTINGS_FILE, $defaultSettings);
        return $defaultSettings;
    }

    $settings = loadJsonFile(SETTINGS_FILE);

    // Merge with defaults in case any settings are missing
    return array_merge($defaultSettings, $settings);
}

/**
 * Save settings to settings.json file
 *
 * @param array $settings - Settings array to save
 * @return bool - True if successful, false if failed
 */
function saveSettings($settings) {
    return saveJsonFile(SETTINGS_FILE, $settings);
}

/**
 * Get a specific setting value
 *
 * @param string $key - The setting key to retrieve
 * @param mixed $default - Default value if setting doesn't exist
 * @return mixed - The setting value
 */
function getSetting($key, $default = null) {
    $settings = loadSettings();
    return $settings[$key] ?? $default;
}

?>

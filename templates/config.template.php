<?php
/**
 * Enhanced Evernode Configuration Template
 * Copy this file to config/config.php and customize for your host
 */

// ⚠️ REQUIRED: Change these values for your host
define('HOST_DOMAIN', 'YOUR_DOMAIN_HERE.com');
define('XAHAU_ADDRESS', 'rYOUR_XAHAU_ADDRESS_HERE');
define('ADMIN_PASSWORD', 'CHANGE_THIS_SECURE_PASSWORD');

// Optional: Customize these values
define('COMMISSION_RATE', 0.20);           // 20% commission rate
define('LICENSE_PRICE_USD', 49.99);        // Premium license price
define('CACHE_DURATION', 30);              // API cache duration in seconds
define('API_VERSION', '3.0');
define('SYSTEM_NAME', 'evernode-enhanced');

// Auto-detected values (usually don't need to change)
define('HOST_IP', $_SERVER['SERVER_ADDR'] ?? 'localhost');
define('DATA_DIR', __DIR__ . '/../data/');
define('CACHE_DIR', __DIR__ . '/../data/cache/');

// Derived values
define('COMMISSION_AMOUNT_USD', LICENSE_PRICE_USD * COMMISSION_RATE);

// Directory setup
$required_dirs = [DATA_DIR, CACHE_DIR];
foreach ($required_dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Database connection
function getDB() {
    $db_path = DATA_DIR . 'hosts.db';
    $db = new SQLite3($db_path);
    $db->exec('PRAGMA foreign_keys = ON');
    createTables($db);
    return $db;
}

function createTables($db) {
    // Database schema here...
}
?>

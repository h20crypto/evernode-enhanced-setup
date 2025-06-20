<?php
/**
 * Shared Configuration for All API Endpoints
 * Evernode Enhanced Setup - Unified API System
 */

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    http_response_code(403);
    die('Direct access not allowed');
}

// ===========================================
// CORE CONFIGURATION
// ===========================================

define('CACHE_DURATION', 30); // seconds
define('HOST_IP', $_SERVER['SERVER_ADDR'] ?? 'localhost');
define('HOST_DOMAIN', $_SERVER['HTTP_HOST'] ?? 'localhost');
define('DATA_DIR', __DIR__ . '/../data/');
define('CACHE_DIR', __DIR__ . '/../data/cache/');
define('API_VERSION', '3.0');
define('SYSTEM_NAME', 'evernode-enhanced');

// Commission configuration (20% as confirmed)
define('COMMISSION_RATE', 0.20);
define('LICENSE_PRICE_USD', 49.99);
define('COMMISSION_AMOUNT_USD', LICENSE_PRICE_USD * COMMISSION_RATE); // $10.00

// ===========================================
// DIRECTORY SETUP
// ===========================================

// Ensure required directories exist
$required_dirs = [DATA_DIR, CACHE_DIR];
foreach ($required_dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// ===========================================
// DATABASE CONNECTION
// ===========================================

function getDB() {
    $db_path = DATA_DIR . 'hosts.db';
    $db = new SQLite3($db_path);
    
    // Enable foreign keys
    $db->exec('PRAGMA foreign_keys = ON');
    
    // Create tables if they don't exist
    createTables($db);
    
    return $db;
}

function createTables($db) {
    // Hosts table for network discovery
    $db->exec('CREATE TABLE IF NOT EXISTS hosts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        domain TEXT UNIQUE NOT NULL,
        ip_address TEXT,
        xahau_address TEXT,
        enhanced BOOLEAN DEFAULT 0,
        quality_score INTEGER DEFAULT 0,
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status TEXT DEFAULT "unknown",
        version TEXT,
        capabilities TEXT, -- JSON array
        response_time INTEGER DEFAULT 0,
        region TEXT DEFAULT "unknown",
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )');
    
    // Deployments table for tracking tenant applications
    $db->exec('CREATE TABLE IF NOT EXISTS deployments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        container_name TEXT NOT NULL,
        app_type TEXT,
        tenant_user TEXT,
        host_domain TEXT,
        image_name TEXT,
        status TEXT DEFAULT "running",
        deployed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP,
        cost_evr DECIMAL(10,6),
        cost_usd DECIMAL(10,2)
    )');
    
    // Commission earnings table
    $db->exec('CREATE TABLE IF NOT EXISTS earnings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        host_domain TEXT NOT NULL,
        license_sale_id TEXT,
        commission_amount_usd DECIMAL(10,2),
        commission_amount_evr DECIMAL(10,6),
        tenant_address TEXT,
        earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        payment_status TEXT DEFAULT "pending",
        transaction_hash TEXT
    )');
    
    // API usage tracking
    $db->exec('CREATE TABLE IF NOT EXISTS api_usage (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        endpoint TEXT NOT NULL,
        ip_address TEXT,
        user_agent TEXT,
        response_time_ms INTEGER,
        status_code INTEGER,
        called_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )');
    
    // Network discovery cache
    $db->exec('CREATE TABLE IF NOT EXISTS network_cache (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cache_key TEXT UNIQUE NOT NULL,
        cache_data TEXT, -- JSON
        expires_at TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )');
}

// ===========================================
// CACHING FUNCTIONS
// ===========================================

function getCachedData($key, $max_age = null) {
    $max_age = $max_age ?? CACHE_DURATION;
    $file = CACHE_DIR . md5($key) . '.cache';
    
    if (file_exists($file) && (time() - filemtime($file)) < $max_age) {
        $data = file_get_contents($file);
        return json_decode($data, true);
    }
    
    return null;
}

function setCachedData($key, $data, $ttl = null) {
    $ttl = $ttl ?? CACHE_DURATION;
    $file = CACHE_DIR . md5($key) . '.cache';
    
    $cache_data = [
        'data' => $data,
        'cached_at' => time(),
        'expires_at' => time() + $ttl
    ];
    
    file_put_contents($file, json_encode($cache_data));
    return true;
}

function clearCache($pattern = '*') {
    $files = glob(CACHE_DIR . $pattern . '.cache');
    foreach ($files as $file) {
        unlink($file);
    }
    return count($files);
}

// ===========================================
// DATABASE CACHING (for expensive queries)
// ===========================================

function getDBCachedData($key, $max_age = 300) { // 5 minutes default
    $db = getDB();
    $stmt = $db->prepare('SELECT cache_data FROM network_cache WHERE cache_key = ? AND expires_at > datetime("now")');
    $stmt->bindValue(1, $key, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        return json_decode($row['cache_data'], true);
    }
    
    return null;
}

function setDBCachedData($key, $data, $ttl = 300) {
    $db = getDB();
    $expires_at = date('Y-m-d H:i:s', time() + $ttl);
    
    $stmt = $db->prepare('INSERT OR REPLACE INTO network_cache (cache_key, cache_data, expires_at) VALUES (?, ?, ?)');
    $stmt->bindValue(1, $key, SQLITE3_TEXT);
    $stmt->bindValue(2, json_encode($data), SQLITE3_TEXT);
    $stmt->bindValue(3, $expires_at, SQLITE3_TEXT);
    
    return $stmt->execute();
}

// ===========================================
// UTILITY FUNCTIONS
// ===========================================

function logAPIUsage($endpoint, $response_time_ms = 0, $status_code = 200) {
    try {
        $db = getDB();
        $stmt = $db->prepare('INSERT INTO api_usage (endpoint, ip_address, user_agent, response_time_ms, status_code) VALUES (?, ?, ?, ?, ?)');
        $stmt->bindValue(1, $endpoint, SQLITE3_TEXT);
        $stmt->bindValue(2, $_SERVER['REMOTE_ADDR'] ?? 'unknown', SQLITE3_TEXT);
        $stmt->bindValue(3, $_SERVER['HTTP_USER_AGENT'] ?? 'unknown', SQLITE3_TEXT);
        $stmt->bindValue(4, $response_time_ms, SQLITE3_INTEGER);
        $stmt->bindValue(5, $status_code, SQLITE3_INTEGER);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to log API usage: " . $e->getMessage());
    }
}

function getHostInfo() {
    static $host_info = null;
    
    if ($host_info === null) {
        $cached = getCachedData('host_info', 60); // Cache for 1 minute
        
        if ($cached) {
            $host_info = $cached;
        } else {
            // Get host information from Evernode CLI
            $host_info = [
                'domain' => HOST_DOMAIN,
                'ip_address' => HOST_IP,
                'enhanced' => true,
                'version' => API_VERSION,
                'system' => SYSTEM_NAME,
                'capabilities' => [
                    'auto-discovery',
                    'unified-pricing', 
                    'commission-tracking',
                    'real-time-monitoring'
                ],
                'commission_rate' => COMMISSION_RATE,
                'license_price' => LICENSE_PRICE_USD,
                'last_updated' => date('c')
            ];
            
            // Try to get Xahau address from Evernode CLI
            $xahau_address = shell_exec('evernode config hostaddress 2>/dev/null');
            if ($xahau_address && trim($xahau_address) !== '') {
                $host_info['xahau_address'] = trim($xahau_address);
            } else {
                $host_info['xahau_address'] = 'rYourHostAddress123'; // Fallback
            }
            
            setCachedData('host_info', $host_info, 60);
        }
    }
    
    return $host_info;
}

function formatResponse($success, $data = null, $error = null, $extra = []) {
    $response = array_merge([
        'success' => $success,
        'timestamp' => date('c'),
        'version' => API_VERSION,
        'system' => SYSTEM_NAME
    ], $extra);
    
    if ($success && $data !== null) {
        $response['data'] = $data;
    }
    
    if (!$success && $error !== null) {
        $response['error'] = $error;
    }
    
    return $response;
}

function validateEVRAddress($address) {
    // Basic XRPL address validation
    return preg_match('/^r[a-zA-Z0-9]{24,34}$/', $address);
}

function getCurrentEVRPrice() {
    $cached = getCachedData('evr_price', 300); // 5 minutes
    
    if ($cached) {
        return $cached;
    }
    
    // Fallback price if API fails
    return [
        'rate' => 0.22,
        'source' => 'fallback',
        'confidence' => 'low'
    ];
}

// ===========================================
// ERROR HANDLING
// ===========================================

function handleAPIError($message, $code = 500, $endpoint = 'unknown') {
    http_response_code($code);
    
    $error_response = formatResponse(false, null, $message, [
        'error_code' => $code,
        'endpoint' => $endpoint
    ]);
    
    echo json_encode($error_response);
    
    // Log the error
    error_log("API Error [{$endpoint}]: {$message}");
    
    exit;
}

// ===========================================
// INITIALIZATION
// ===========================================

// Set timezone
date_default_timezone_set('UTC');

// Initialize database on first load
try {
    $db = getDB();
    $db->close();
} catch (Exception $e) {
    error_log("Database initialization failed: " . $e->getMessage());
}

// Clean old cache files (run occasionally)
if (rand(1, 100) === 1) { // 1% chance
    $old_files = glob(CACHE_DIR . '*.cache');
    foreach ($old_files as $file) {
        if (time() - filemtime($file) > 3600) { // 1 hour old
            unlink($file);
        }
    }
 define('CACHE_DURATION', 30); // seconds - your current setting
define('CRYPTO_CACHE_DURATION', 300); // 5 minutes for crypto prices (more stable)

// Then update your getCachedData function to optionally use the longer duration:
function getCachedData($key, $max_age = null) {
    $max_age = $max_age ?? CACHE_DURATION;
    $file = CACHE_DIR . md5($key) . '.cache';
    
    if (file_exists($file) && (time() - filemtime($file)) < $max_age) {
        $data = file_get_contents($file);
        return json_decode($data, true);
    }
    
    return null;
}                        
}

?>

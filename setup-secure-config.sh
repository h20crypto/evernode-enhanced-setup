#!/bin/bash
#
# Enhanced Evernode Secure Configuration Setup
# Prompts user for their specific information and creates secure config
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

echo -e "${GREEN}"
cat << "EOF"
🔐 Enhanced Evernode Secure Configuration Setup
=============================================
This script will configure your Enhanced Evernode host
with YOUR specific information (never shared publicly)
EOF
echo -e "${NC}"

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   print_error "Please run as non-root user with sudo access"
   exit 1
fi

# Ensure we're in the right directory
if [[ ! -f "config-template.php" ]]; then
    print_error "config-template.php not found. Please run from the Enhanced Evernode directory."
    exit 1
fi

print_status "🔍 Gathering your host information..."

# Get domain automatically but allow override
AUTO_DOMAIN=$(hostname -f 2>/dev/null || hostname)
echo ""
print_status "Auto-detected domain: $AUTO_DOMAIN"
read -p "Your domain (press Enter to use auto-detected): " USER_DOMAIN
DOMAIN=${USER_DOMAIN:-$AUTO_DOMAIN}

# Get external IP
AUTO_IP=$(curl -s ifconfig.me 2>/dev/null || echo "unknown")
print_status "Auto-detected external IP: $AUTO_IP"
read -p "Your external IP (press Enter to use auto-detected): " USER_IP
IP=${USER_IP:-$AUTO_IP}

# Get Evernode configuration
print_status "🔗 Checking your Evernode configuration..."
EVERNODE_ADDRESS=$(evernode config account 2>/dev/null | grep "Address:" | awk '{print $2}' || echo "")
EVERNODE_INSTANCES=$(evernode totalins 2>/dev/null || echo "")

if [[ -n "$EVERNODE_ADDRESS" ]]; then
    print_success "Found Evernode address: $EVERNODE_ADDRESS"
    read -p "Use this address? (Y/n): " USE_AUTO_ADDRESS
    if [[ "$USE_AUTO_ADDRESS" =~ ^[Nn] ]]; then
        read -p "Enter your Xahau address: " EVERNODE_ADDRESS
    fi
else
    print_warning "Could not auto-detect Evernode address"
    read -p "Enter your Xahau address: " EVERNODE_ADDRESS
fi

if [[ -n "$EVERNODE_INSTANCES" ]]; then
    print_success "Found instance limit: $EVERNODE_INSTANCES"
    read -p "Use this limit? (Y/n): " USE_AUTO_INSTANCES
    if [[ "$USE_AUTO_INSTANCES" =~ ^[Nn] ]]; then
        read -p "Enter your instance limit: " EVERNODE_INSTANCES
    fi
else
    print_warning "Could not auto-detect instance limit"
    read -p "Enter your instance limit (default 3): " EVERNODE_INSTANCES
    EVERNODE_INSTANCES=${EVERNODE_INSTANCES:-3}
fi

# Generate secure passwords
print_status "🔐 Generating secure credentials..."
ADMIN_PASSWORD=$(openssl rand -base64 24 | tr -d "=+/" | cut -c1-20)
ADMIN_PASSWORD_HASH=$(php -r "echo password_hash('$ADMIN_PASSWORD', PASSWORD_ARGON2ID);")
API_SECRET=$(openssl rand -hex 32)

# Show generated password
echo ""
print_success "🔑 Your secure admin password: $ADMIN_PASSWORD"
print_warning "⚠️  SAVE THIS PASSWORD - you'll need it to access admin features!"
echo ""
read -p "Press Enter when you've saved the password..."

# Prompt for commission settings
echo ""
print_status "💰 Commission configuration"
read -p "Commission rate (default 20% = 0.20): " COMMISSION_RATE
COMMISSION_RATE=${COMMISSION_RATE:-0.20}

read -p "License price in USD (default 49.99): " LICENSE_PRICE
LICENSE_PRICE=${LICENSE_PRICE:-49.99}

# Create config file
print_status "📝 Creating your configuration file..."

# Create config directory if it doesn't exist
sudo mkdir -p /var/www/html/config

# Generate the config file
cat > temp_config.php << EOF
<?php
/**
 * Enhanced Evernode Configuration
 * Generated on $(date)
 * KEEP THIS FILE SECURE - Contains sensitive information
 */

// Prevent direct access
if (basename(\$_SERVER['PHP_SELF']) == basename(__FILE__)) {
    http_response_code(403);
    die('Direct access not allowed');
}

// ===========================================
// YOUR HOST CONFIGURATION
// ===========================================

define('HOST_DOMAIN', '${DOMAIN}');
define('HOST_IP', '${IP}');
define('XAHAU_ADDRESS', '${EVERNODE_ADDRESS}');
define('EVERNODE_INSTANCE_LIMIT', ${EVERNODE_INSTANCES});

// Security (Generated securely)
define('ADMIN_PASSWORD_HASH', '${ADMIN_PASSWORD_HASH}');
define('API_SECRET_KEY', '${API_SECRET}');

// Commission settings
define('COMMISSION_RATE', ${COMMISSION_RATE});
define('LICENSE_PRICE_USD', ${LICENSE_PRICE});
define('COMMISSION_AMOUNT_USD', LICENSE_PRICE_USD * COMMISSION_RATE);

// ===========================================
// SYSTEM CONFIGURATION
// ===========================================

define('CACHE_DURATION', 30);
define('DATA_DIR', __DIR__ . '/../data/');
define('CACHE_DIR', __DIR__ . '/../data/cache/');
define('API_VERSION', '3.0');
define('SYSTEM_NAME', 'evernode-enhanced');

// ===========================================
// DIRECTORY SETUP
// ===========================================

\$required_dirs = [DATA_DIR, CACHE_DIR];
foreach (\$required_dirs as \$dir) {
    if (!file_exists(\$dir)) {
        mkdir(\$dir, 0755, true);
    }
}

// Include the rest of the configuration functions
require_once __DIR__ . '/config-functions.php';
?>
EOF

# Move config file with proper permissions
sudo mv temp_config.php /var/www/html/config/config.php
sudo chown www-data:www-data /var/www/html/config/config.php
sudo chmod 600 /var/www/html/config/config.php

# Create config functions file
sudo cat > /var/www/html/config/config-functions.php << 'EOF'
<?php
/**
 * Enhanced Evernode Configuration Functions
 * Shared functions for configuration management
 */

// ===========================================
// DATABASE CONNECTION
// ===========================================

function getDB() {
    $db_path = DATA_DIR . 'hosts.db';
    $db = new SQLite3($db_path);
    
    $db->exec('PRAGMA foreign_keys = ON');
    createTables($db);
    
    return $db;
}

function createTables($db) {
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
        capabilities TEXT,
        response_time INTEGER DEFAULT 0,
        region TEXT DEFAULT "unknown",
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )');
    
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
}

// ===========================================
// HELPER FUNCTIONS
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
    $file = CACHE_DIR . md5($key) . '.cache';
    file_put_contents($file, json_encode($data));
}

function getHostInfo() {
    static $host_info = null;
    
    if ($host_info === null) {
        $cached = getCachedData('host_info', 60);
        
        if ($cached) {
            $host_info = $cached;
        } else {
            $detected_address = trim(shell_exec('evernode config account | grep "Address:" | awk \'{print $2}\' 2>/dev/null'));
            $xahau_address = $detected_address ?: XAHAU_ADDRESS;
            
            $detected_limit = trim(shell_exec('evernode totalins 2>/dev/null'));
            $instance_limit = $detected_limit ?: EVERNODE_INSTANCE_LIMIT;
            
            $host_info = [
                'domain' => HOST_DOMAIN,
                'ip_address' => HOST_IP,
                'xahau_address' => $xahau_address,
                'enhanced' => true,
                'version' => API_VERSION,
                'system' => SYSTEM_NAME,
                'instance_limit' => $instance_limit,
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
            
            setCachedData('host_info', $host_info, 60);
        }
    }
    
    return $host_info;
}

function verifyAdminPassword($password) {
    return password_verify($password, ADMIN_PASSWORD_HASH);
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
?>
EOF

sudo chown www-data:www-data /var/www/html/config/config-functions.php
sudo chmod 644 /var/www/html/config/config-functions.php

# Create credentials file for user reference
cat > ~/.evernode-credentials << EOF
Enhanced Evernode Host Credentials
=================================
Generated: $(date)

Domain: ${DOMAIN}
IP: ${IP}
Xahau Address: ${EVERNODE_ADDRESS}
Instance Limit: ${EVERNODE_INSTANCES}

Admin Password: ${ADMIN_PASSWORD}
Commission Rate: ${COMMISSION_RATE}
License Price: \$${LICENSE_PRICE}

⚠️  KEEP THIS FILE SECURE
⚠️  Admin password is needed to access host management features
EOF

chmod 600 ~/.evernode-credentials

# Set proper permissions
print_status "🔒 Setting secure file permissions..."
sudo chown -R www-data:www-data /var/www/html/
sudo find /var/www/html -type f -name "*.php" -exec chmod 644 {} \;
sudo find /var/www/html -type f -name "*.html" -exec chmod 644 {} \;
sudo find /var/www/html -type d -exec chmod 755 {} \;

# Secure config directory
sudo chmod 700 /var/www/html/config/
sudo chmod 600 /var/www/html/config/config.php

print_success "✅ Secure configuration created!"
echo ""
print_success "📋 Your credentials have been saved to: ~/.evernode-credentials"
print_success "🔑 Your admin password: $ADMIN_PASSWORD"
echo ""
print_warning "⚠️  IMPORTANT: Keep your admin password safe!"
print_warning "⚠️  Your configuration contains sensitive information"
echo ""
print_status "🧪 Testing configuration..."

# Test the configuration
if php -f /var/www/html/config/config.php; then
    print_success "✅ Configuration file is valid"
else
    print_error "❌ Configuration file has errors"
fi

print_success "🎉 Enhanced Evernode host configured successfully!"
print_status "🚀 You can now run the main installation script"

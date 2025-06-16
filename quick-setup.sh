#!/bin/bash

# Enhanced Evernode Quick Setup Script - Updated with Dhali Oracle Integration
# Version: 2.0 with Cluster Manager

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Configuration
DOMAIN=${1:-$(hostname -f)}
INSTALL_DIR="/var/www/html"
NGINX_SITE="/etc/nginx/sites-available/enhanced-evernode"
API_DIR="$INSTALL_DIR/api"
CLUSTER_DIR="$INSTALL_DIR/cluster"
DATA_DIR="$INSTALL_DIR/data"

echo -e "${BLUE}🚀 Enhanced Evernode Setup with Dhali Oracle Integration${NC}"
echo "=================================================="
echo "Domain: $DOMAIN"
echo "Install Directory: $INSTALL_DIR"
echo ""

# Check if running as root
if [[ $EUID -eq 0 ]]; then
    print_error "This script should not be run as root for security reasons"
    exit 1
fi

# Check for required commands
check_requirements() {
    print_info "Checking system requirements..."
    
    local required_commands=("curl" "nginx" "php" "node" "npm")
    local missing_commands=()
    
    for cmd in "${required_commands[@]}"; do
        if ! command -v $cmd &> /dev/null; then
            missing_commands+=($cmd)
        fi
    done
    
    if [ ${#missing_commands[@]} -ne 0 ]; then
        print_error "Missing required commands: ${missing_commands[*]}"
        print_info "Please install the missing dependencies and try again"
        exit 1
    fi
    
    print_status "All requirements satisfied"
}

# Install system dependencies
install_dependencies() {
    print_info "Installing/updating system dependencies..."
    
    # Update package list
    sudo apt update
    
    # Install required packages
    sudo apt install -y nginx php-fpm php-cli php-json php-curl php-mbstring nodejs npm
    
    # Install global npm packages for Evernode
    sudo npm install -g evernode evdevkit
    
    print_status "System dependencies installed"
}

# Setup web server directories
setup_directories() {
    print_info "Setting up directory structure..."
    
    # Create main directories
    sudo mkdir -p $INSTALL_DIR
    sudo mkdir -p $API_DIR
    sudo mkdir -p $CLUSTER_DIR
    sudo mkdir -p $DATA_DIR
    sudo mkdir -p $INSTALL_DIR/assets
    sudo mkdir -p $INSTALL_DIR/tools
    sudo mkdir -p $INSTALL_DIR/dhali_cache
    
    # Set permissions
    sudo chown -R www-data:www-data $INSTALL_DIR
    sudo chmod -R 755 $INSTALL_DIR
    sudo chmod -R 766 $DATA_DIR
    sudo chmod -R 766 $INSTALL_DIR/dhali_cache
    
    print_status "Directory structure created"
}

# Copy enhanced host files
copy_host_files() {
    print_info "Installing enhanced host files..."
    
    # Copy main landing page
    if [ -f "index.html" ]; then
        sudo cp index.html $INSTALL_DIR/
        print_status "Main landing page installed"
    else
        print_warning "index.html not found, skipping"
    fi
    
    # Copy existing API files
    if [ -d "api" ]; then
        sudo cp -r api/* $API_DIR/
        print_status "API files installed"
    else
        print_warning "api directory not found, creating basic structure"
        sudo mkdir -p $API_DIR
    fi
    
    print_status "Enhanced host files installed"
}

# Install cluster management files
install_cluster_files() {
    print_info "Installing cluster management system..."
    
    # Copy cluster directory if exists
    if [ -d "cluster" ]; then
        sudo cp -r cluster/* $CLUSTER_DIR/
        print_status "Cluster management files installed"
    else
        print_warning "cluster directory not found, creating from templates"
        
        # Create basic cluster directory structure
        sudo mkdir -p $CLUSTER_DIR
        
        # Create placeholder files if they don't exist
        sudo tee $CLUSTER_DIR/index.html > /dev/null << 'EOF'
<!DOCTYPE html>
<html><head><title>Cluster Manager</title></head>
<body><h1>Cluster Manager Coming Soon</h1><p>The cluster management interface will be available here.</p></body></html>
EOF
    fi
    
    # Set permissions for cluster files
    sudo chown -R www-data:www-data $CLUSTER_DIR
    sudo chmod -R 644 $CLUSTER_DIR/*.html
    
    print_status "Cluster management system installed"
}

# Install Dhali Oracle integration
install_dhali_integration() {
    print_info "Setting up Dhali Oracle integration..."
    
    # Create crypto-rates-optimized.php if it doesn't exist
    if [ ! -f "$API_DIR/crypto-rates-optimized.php" ]; then
        print_warning "crypto-rates-optimized.php not found, creating template"
        
        sudo tee $API_DIR/crypto-rates-optimized.php > /dev/null << 'EOF'
<?php
// crypto-rates-optimized.php - Template for Dhali Oracle integration
// TODO: Replace with actual implementation
header('Content-Type: application/json');

class OptimizedDhaliRates {
    private $payment_claim = 'YOUR_DHALI_PAYMENT_CLAIM_HERE'; // ← UPDATE THIS!
    
    public function getRates($mode = 'balanced') {
        // Fallback rates for testing
        return [
            'xrp' => [
                'rate' => 0.42,
                'amount_for_license' => 119.05,
                'display' => '~119 XRP',
                'source' => 'fallback'
            ],
            'evr' => [
                'rate' => 0.22,
                'amount_for_license' => 227.23,
                'display' => '~227 EVR',
                'source' => 'estimated'
            ],
            'usd' => [
                'rate' => 1.00,
                'amount_for_license' => 49.99,
                'display' => '$49.99 USDC',
                'source' => 'fixed'
            ],
            'license_usd' => 49.99,
            'mode' => $mode,
            'timestamp' => time(),
            'last_updated' => date('Y-m-d H:i:s'),
            'costs_incurred' => 0
        ];
    }
}

$mode = $_GET['mode'] ?? 'balanced';
$rates = new OptimizedDhaliRates();
echo json_encode($rates->getRates($mode));
?>
EOF
    fi
    
    # Create xahau-nft-licenses.php if it doesn't exist
    if [ ! -f "$API_DIR/xahau-nft-licenses.php" ]; then
        print_warning "xahau-nft-licenses.php not found, creating template"
        
        sudo tee $API_DIR/xahau-nft-licenses.php > /dev/null << 'EOF'
<?php
// xahau-nft-licenses.php - Template for NFT license management
// TODO: Replace with actual implementation
header('Content-Type: application/json');

// Basic template response
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'generate_payment':
            echo json_encode([
                'dest_tag' => rand(100000, 999999),
                'address' => 'rYourXahauAddress', // ← UPDATE THIS!
                'exact_amount' => '119.05',
                'rate_used' => 0.42,
                'currency' => $input['currency'] ?? 'xrp'
            ]);
            break;
            
        default:
            echo json_encode(['error' => 'Not implemented yet']);
    }
} else {
    echo json_encode(['error' => 'Not implemented yet']);
}
?>
EOF
    fi
    
    # Set permissions
    sudo chmod +x $API_DIR/*.php
    
    print_status "Dhali Oracle integration templates installed"
    print_warning "Remember to update API files with your actual Dhali payment claim and Xahau address!"
}

# Configure enhanced features
configure_enhanced_features() {
    print_info "Configuring enhanced Evernode features..."
    
    # Create enhanced configuration
    sudo tee $INSTALL_DIR/enhanced-config.json > /dev/null << EOF
{
    "version": "2.0",
    "features": {
        "enhanced_host": true,
        "cluster_manager": true,
        "dhali_oracle": true,
        "real_time_monitoring": true,
        "nft_licenses": true
    },
    "endpoints": {
        "instance_count": "/api/instance-count.php",
        "host_info": "/api/host-info.php",
        "crypto_rates": "/api/crypto-rates-optimized.php",
        "nft_licenses": "/api/xahau-nft-licenses.php"
    },
    "cluster": {
        "paywall": "/cluster/paywall.html",
        "wizard": "/cluster/wizard.html",
        "calculator": "/cluster/roi-calculator.html"
    },
    "dhali": {
        "payment_claim": "UPDATE_WITH_YOUR_CLAIM",
        "endpoints": {
            "xrpl_raw": "https://run.api.dhali.io/d74e99cb-166d-416b-b171-4d313e0f079d/",
            "xrpl_stats": "https://run.api.dhali.io/c74e147c-a14c-4038-a6aa-9619d2c92596/",
            "xahau_raw": "https://run.api.dhali.io/f642bad0-acaf-4b2e-852b-66d9a6b6b1ef/"
        }
    },
    "install_date": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "domain": "$DOMAIN"
}
EOF
    
    sudo chown www-data:www-data $INSTALL_DIR/enhanced-config.json
    
    print_status "Enhanced features configured"
}

# Setup Nginx configuration
configure_nginx() {
    print_info "Configuring Nginx web server..."
    
    # Create Nginx site configuration
    sudo tee $NGINX_SITE > /dev/null << EOF
server {
    listen 80;
    server_name $DOMAIN;
    root $INSTALL_DIR;
    index index.html index.php;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    
    # CORS headers for API
    add_header Access-Control-Allow-Origin "*" always;
    add_header Access-Control-Allow-Methods "GET, POST, OPTIONS" always;
    add_header Access-Control-Allow-Headers "Origin, Content-Type, Accept" always;
    
    # Main site
    location / {
        try_files \$uri \$uri/ =404;
    }
    
    # API endpoints
    location /api/ {
        try_files \$uri \$uri/ =404;
        
        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            include fastcgi_params;
        }
    }
    
    # Cluster management
    location /cluster/ {
        try_files \$uri \$uri/ =404;
        
        # Enable PHP for cluster APIs if needed
        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            include fastcgi_params;
        }
    }
    
    # Data directory protection
    location /data/ {
        deny all;
        return 403;
    }
    
    # Cache directory protection  
    location /dhali_cache/ {
        deny all;
        return 403;
    }
    
    # Static assets
    location /assets/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # Gzip compression
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;
}
EOF
    
    # Enable the site
    sudo ln -sf $NGINX_SITE /etc/nginx/sites-enabled/
    
    # Remove default site if it exists
    sudo rm -f /etc/nginx/sites-enabled/default
    
    # Test Nginx configuration
    sudo nginx -t
    
    # Restart services
    sudo systemctl restart nginx
    sudo systemctl restart php8.1-fpm
    
    print_status "Nginx configured and restarted"
}

# Create maintenance scripts
create_maintenance_scripts() {
    print_info "Creating maintenance scripts..."
    
    # Create cleanup script
    sudo tee $INSTALL_DIR/tools/cleanup-expired-payments.php > /dev/null << 'EOF'
#!/usr/bin/env php
<?php
// Cleanup expired payments script
require_once __DIR__ . '/../api/xahau-nft-licenses.php';

echo "🧹 Cleaning up expired payments...\n";

try {
    $manager = new XahauNFTLicenseManager();
    $cleaned = $manager->cleanupExpiredPayments();
    echo "✅ Cleaned up {$cleaned} expired payments\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
EOF
    
    # Create health check script
    sudo tee $INSTALL_DIR/tools/health-check.sh > /dev/null << 'EOF'
#!/bin/bash
# Health check script for Enhanced Evernode

echo "🏥 Enhanced Evernode Health Check"
echo "================================="

# Check web server
if curl -f -s http://localhost/ > /dev/null; then
    echo "✅ Web server: Online"
else
    echo "❌ Web server: Offline"
fi

# Check APIs
if curl -f -s http://localhost/api/crypto-rates-optimized.php > /dev/null; then
    echo "✅ Crypto rates API: Working"
else
    echo "❌ Crypto rates API: Failed"
fi

# Check data directories
if [ -w "/var/www/html/data" ]; then
    echo "✅ Data directory: Writable"
else
    echo "❌ Data directory: Not writable"
fi

# Check disk space
DISK_USAGE=$(df /var/www/html | awk 'NR==2 {print $5}' | sed 's/%//')
if [ $DISK_USAGE -lt 80 ]; then
    echo "✅ Disk space: ${DISK_USAGE}% used"
else
    echo "⚠️  Disk space: ${DISK_USAGE}% used (warning)"
fi

echo ""
echo "🎯 Quick Links:"
echo "   Main site: http://$(hostname -f)/"
echo "   ROI Calculator: http://$(hostname -f)/cluster/roi-calculator.html"
echo "   License Purchase: http://$(hostname -f)/cluster/paywall.html"
EOF
    
    # Make scripts executable
    sudo chmod +x $INSTALL_DIR/tools/*.php
    sudo chmod +x $INSTALL_DIR/tools/*.sh
    
    # Add cron job for cleanup (run daily at 2 AM)
    (crontab -l 2>/dev/null; echo "0 2 * * * /usr/bin/php $INSTALL_DIR/tools/cleanup-expired-payments.php") | crontab -
    
    print_status "Maintenance scripts created and scheduled"
}

# Generate installation report
generate_report() {
    print_info "Generating installation report..."
    
    sudo tee $INSTALL_DIR/INSTALLATION_REPORT.md > /dev/null << EOF
# Enhanced Evernode Installation Report

**Installation Date:** $(date)
**Domain:** $DOMAIN
**Version:** 2.0 (with Dhali Oracle Integration)

## 🎯 What's Installed

### Core Features
- ✅ Enhanced host landing page
- ✅ Real-time instance monitoring
- ✅ Professional host interface
- ✅ Cluster management system (NEW)
- ✅ Dhali Oracle integration (NEW)
- ✅ NFT license system (NEW)

### API Endpoints
- \`/api/instance-count.php\` - Real-time instance data
- \`/api/host-info.php\` - Host information
- \`/api/crypto-rates-optimized.php\` - Live crypto pricing (NEW)
- \`/api/xahau-nft-licenses.php\` - NFT license management (NEW)

### Cluster Features
- \`/cluster/roi-calculator.html\` - ROI calculator with live pricing
- \`/cluster/paywall.html\` - NFT license purchase page
- \`/cluster/wizard.html\` - Cluster creation wizard (coming soon)

## 🔧 Configuration Required

### 1. Update Dhali Oracle Integration
Edit \`/var/www/html/api/crypto-rates-optimized.php\`:
\`\`\`php
private \$payment_claim = 'YOUR_ACTUAL_DHALI_CLAIM_HERE';
\`\`\`

### 2. Update Xahau Wallet Address  
Edit \`/var/www/html/api/xahau-nft-licenses.php\`:
\`\`\`php
private \$xahau_address = 'rYourActualXahauAddress';
\`\`\`

### 3. Test Your Integration
\`\`\`bash
# Test crypto rates
curl http://$DOMAIN/api/crypto-rates-optimized.php?mode=balanced

# Test health check
sudo /var/www/html/tools/health-check.sh
\`\`\`

## 💰 Revenue Streams

1. **Enhanced Host Premium** - Attract more tenants with professional interface
2. **NFT License Sales** - \$49.99 per Cluster Manager license
3. **Network Effects** - More enhanced hosts = more license demand

## 🚀 Next Steps

1. Configure Dhali Oracle payment channel
2. Set your Xahau wallet address
3. Test the complete license purchase flow
4. Share ROI calculator with potential customers
5. Onboard other hosts to the enhanced network

## 📊 Monitoring

- **Health Check:** \`sudo /var/www/html/tools/health-check.sh\`
- **Cleanup Expired:** Runs automatically daily at 2 AM
- **API Stats:** \`curl http://$DOMAIN/api/xahau-nft-licenses.php?action=stats\`

## 🌐 Access Your System

- **Main Site:** http://$DOMAIN/
- **ROI Calculator:** http://$DOMAIN/cluster/roi-calculator.html  
- **License Purchase:** http://$DOMAIN/cluster/paywall.html
- **Enhanced Config:** http://$DOMAIN/enhanced-config.json

---
**Enhanced Evernode v2.0** - Transforming Evernode hosting into enterprise-grade infrastructure! 🚀
EOF
    
    print_status "Installation report generated: $INSTALL_DIR/INSTALLATION_REPORT.md"
}

# Main installation flow
main() {
    echo ""
    print_info "Starting Enhanced Evernode installation with Dhali Oracle integration..."
    echo ""
    
    check_requirements
    install_dependencies
    setup_directories
    copy_host_files
    install_cluster_files
    install_dhali_integration
    configure_enhanced_features
    configure_nginx
    create_maintenance_scripts
    generate_report
    
    echo ""
    print_status "🎉 Enhanced Evernode installation completed successfully!"
    echo ""
    print_info "🌟 Your enhanced Evernode host is now ready with:"
    print_info "   • Professional landing page at http://$DOMAIN/"
    print_info "   • Cluster Manager ROI calculator"
    print_info "   • NFT license purchase system"
    print_info "   • Dhali Oracle real-time pricing"
    echo ""
    print_warning "⚠️  IMPORTANT: Update your Dhali payment claim and Xahau address in API files!"
    print_info "📋 See $INSTALL_DIR/INSTALLATION_REPORT.md for complete setup instructions"
    echo ""
    print_info "🚀 Run health check: sudo $INSTALL_DIR/tools/health-check.sh"
    echo ""
}

# Run main installation
main "$@"

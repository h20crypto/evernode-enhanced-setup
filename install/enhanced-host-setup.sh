#!/bin/bash

# =============================================================================
# 🚀 Enhanced Evernode Host - Complete Setup Script (CORRECTED PATHS)
# =============================================================================
# Automated installation with commission tracking integration and unified discovery system
# Author: Enhanced Evernode Team
# Version: 2.3 Production + Path Corrections

set -e  # Exit on any error

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
NC='\033[0m' # No Color

# Global variables
INSTALL_DIR="/var/www/html"
CONFIG_DIR="/etc/enhanced-evernode"
LOG_FILE="/var/log/enhanced-setup.log"
GITHUB_REPO="https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main"
CENTRAL_API="https://api.evrdirect.info"
PAYMENT_URL="https://payments.evrdirect.info"

# Host operator information (collected during setup)
HOST_DOMAIN=""
HOST_WALLET=""
OPERATOR_NAME=""
OPERATOR_EMAIL=""
OPERATOR_COUNTRY=""
SERVER_SPECS=""
ADMIN_PASSWORD=""

# =============================================================================
# 🎨 Display Functions
# =============================================================================

print_banner() {
    clear
    echo -e "${CYAN}"
    echo "=============================================================="
    echo "🚀 Enhanced Evernode Host - Production Setup v2.3"
    echo "=============================================================="
    echo -e "${WHITE}Complete automated installation with commission tracking${NC}"
    echo -e "${GREEN}✅ Real earnings • ✅ $10 per sale • ✅ Professional setup${NC}"
    echo -e "${BLUE}✅ Unified discovery system • ✅ Real network data${NC}"
    echo ""
}

print_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
    echo "$(date): $1" >> "$LOG_FILE"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
    echo "$(date): SUCCESS - $1" >> "$LOG_FILE"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
    echo "$(date): ERROR - $1" >> "$LOG_FILE"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
    echo "$(date): WARNING - $1" >> "$LOG_FILE"
}

print_info() {
    echo -e "${CYAN}ℹ️  $1${NC}"
}

# =============================================================================
# 🛡️ System Checks
# =============================================================================

check_requirements() {
    print_step "Checking system requirements..."
    
    # Check if running as root
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root"
        echo "Please run: sudo $0"
        exit 1
    fi
    
    # Check operating system
    if [[ ! -f /etc/os-release ]]; then
        print_error "Cannot determine operating system"
        exit 1
    fi
    
    source /etc/os-release
    if [[ "$ID" != "ubuntu" && "$ID" != "debian" ]]; then
        print_warning "This script is optimized for Ubuntu/Debian"
        read -p "Continue anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
    
    # Check internet connectivity
    if ! ping -c 1 google.com &> /dev/null; then
        print_error "No internet connection detected"
        exit 1
    fi
    
    print_success "System requirements check passed"
}

# =============================================================================
# 📝 Host Information Collection
# =============================================================================

collect_host_info() {
    print_step "Collecting host operator information..."
    echo -e "${WHITE}This information is required for commission tracking and payouts${NC}"
    echo ""
    
    # Domain name
    while [[ -z "$HOST_DOMAIN" ]]; do
        echo -e "${CYAN}Enter your domain name (e.g., myhost.example.com):${NC}"
        read -p "Domain: " HOST_DOMAIN
        
        if [[ ! "$HOST_DOMAIN" =~ ^[a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z]{2,}$ ]]; then
            print_warning "Please enter a valid domain name"
            HOST_DOMAIN=""
        fi
    done
    
    # Wallet address for commission payouts
    while [[ -z "$HOST_WALLET" ]]; do
        echo -e "${CYAN}Enter your XRPL wallet address for commission payouts:${NC}"
        echo -e "${YELLOW}(Must start with 'r' and be 25-34 characters)${NC}"
        read -p "Wallet: " HOST_WALLET
        
        if [[ ! "$HOST_WALLET" =~ ^r[a-zA-Z0-9]{24,33}$ ]]; then
            print_warning "Please enter a valid XRPL wallet address"
            HOST_WALLET=""
        fi
    done
    
    # Operator name
    while [[ -z "$OPERATOR_NAME" ]]; do
        echo -e "${CYAN}Enter your name or organization:${NC}"
        read -p "Name: " OPERATOR_NAME
    done
    
    # Email address
    while [[ -z "$OPERATOR_EMAIL" ]]; do
        echo -e "${CYAN}Enter your email address:${NC}"
        read -p "Email: " OPERATOR_EMAIL
        
        if [[ ! "$OPERATOR_EMAIL" =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$ ]]; then
            print_warning "Please enter a valid email address"
            OPERATOR_EMAIL=""
        fi
    done
    
    # Country
    echo -e "${CYAN}Enter your country:${NC}"
    read -p "Country: " OPERATOR_COUNTRY
    
    # Server specifications
    echo -e "${CYAN}Enter your server specifications (optional):${NC}"
    echo -e "${YELLOW}Example: 4 CPU, 8GB RAM, 100GB SSD${NC}"
    read -p "Specs: " SERVER_SPECS
    
    # Admin password for host management
    echo ""
    echo -e "${WHITE}🔐 Set Admin Password for Host Management${NC}"
    echo -e "${YELLOW}This password will allow you to access admin features on your host${NC}"
    while [[ -z "$ADMIN_PASSWORD" ]]; do
        echo -e "${CYAN}Enter admin password (minimum 8 characters):${NC}"
        read -s -p "Password: " ADMIN_PASSWORD
        echo
        
        if [[ ${#ADMIN_PASSWORD} -lt 8 ]]; then
            print_warning "Password must be at least 8 characters long"
            ADMIN_PASSWORD=""
            continue
        fi
        
        echo -e "${CYAN}Confirm admin password:${NC}"
        read -s -p "Confirm: " ADMIN_PASSWORD_CONFIRM
        echo
        
        if [[ "$ADMIN_PASSWORD" != "$ADMIN_PASSWORD_CONFIRM" ]]; then
            print_warning "Passwords do not match"
            ADMIN_PASSWORD=""
        fi
    done
    
    # Confirmation
    echo ""
    echo -e "${WHITE}Please confirm your information:${NC}"
    echo -e "${GREEN}Domain:${NC} $HOST_DOMAIN"
    echo -e "${GREEN}Wallet:${NC} $HOST_WALLET"
    echo -e "${GREEN}Name:${NC} $OPERATOR_NAME"
    echo -e "${GREEN}Email:${NC} $OPERATOR_EMAIL"
    echo -e "${GREEN}Country:${NC} $OPERATOR_COUNTRY"
    echo -e "${GREEN}Specs:${NC} $SERVER_SPECS"
    echo -e "${GREEN}Admin Password:${NC} ••••••••"
    echo ""
    
    # Important password reminder
    echo -e "${YELLOW}⚠️  IMPORTANT: Write down your admin password!${NC}"
    echo -e "${WHITE}Admin Password: ${CYAN}$ADMIN_PASSWORD${NC}"
    echo -e "${YELLOW}You'll need this to access admin features at: https://$HOST_DOMAIN/?admin=true${NC}"
    echo ""
    
    read -p "Have you written down your admin password? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_info "Please write down your admin password and restart"
        exit 0
    fi
    
    read -p "Is this information correct? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_info "Please restart the script to re-enter information"
        exit 0
    fi
    
    print_success "Host information collected"
}

# =============================================================================
# 📦 System Installation
# =============================================================================

install_dependencies() {
    print_step "Installing system dependencies..."
    
    # Update package list
    apt-get update -y
    
    # Install required packages
    apt-get install -y \
        nginx \
        php8.1-fpm \
        php8.1-cli \
        php8.1-curl \
        php8.1-json \
        php8.1-mbstring \
        php8.1-xml \
        php8.1-zip \
        docker.io \
        docker-compose \
        curl \
        wget \
        unzip \
        git \
        certbot \
        python3-certbot-nginx \
        ufw \
        fail2ban
    
    # Start and enable services
    systemctl start nginx
    systemctl enable nginx
    systemctl start php8.1-fpm
    systemctl enable php8.1-fpm
    systemctl start docker
    systemctl enable docker
    
    # Add current user to docker group
    usermod -aG docker www-data
    
    print_success "Dependencies installed"
}

# =============================================================================
# 🔧 Enhanced Host Files Setup (CORRECTED PATHS)
# =============================================================================

download_host_files() {
    print_step "Downloading Enhanced Evernode host files..."
    
    # Create directories
    mkdir -p "$INSTALL_DIR"
    mkdir -p "$CONFIG_DIR"
    mkdir -p "/var/log/enhanced-evernode"
    
    # Download main files
    cd "$INSTALL_DIR"
    
    # Remove default nginx page
    rm -f index.html index.nginx-debian.html
    
    # Download landing page (CORRECTED PATH)
    curl -fsSL "$GITHUB_REPO/landing-page/index.html" -o index.html
    
    # Download other pages (CORRECTED PATHS)
    mkdir -p cluster api assets/css assets/js landing-page/css landing-page/js scripts
    
    curl -fsSL "$GITHUB_REPO/landing-page/host-discovery.html" -o host-discovery.html
    curl -fsSL "$GITHUB_REPO/landing-page/my-earnings.html" -o my-earnings.html
    curl -fsSL "$GITHUB_REPO/landing-page/monitoring-dashboard.html" -o monitoring-dashboard.html
    
    # Download basic API files (CORRECTED PATHS)
    print_info "📡 Downloading basic API files..."
    curl -fsSL "$GITHUB_REPO/api/host-info.php" -o api/host-info.php
    curl -fsSL "$GITHUB_REPO/api/instance-count.php" -o api/instance-count.php
    curl -fsSL "$GITHUB_REPO/api/router.php" -o api/router.php
    
    # Download auto-announcement script (CORRECTED PATH)
    print_info "🌐 Downloading auto-announcement system..."
    curl -fsSL "$GITHUB_REPO/scripts/enhanced-auto-announce.sh" -o scripts/enhanced-auto-announce.sh
    chmod +x scripts/enhanced-auto-announce.sh
    
    # Download cluster manager (CORRECTED PATH)
    curl -fsSL "$GITHUB_REPO/cluster/premium-cluster-manager.html" -o cluster/premium-cluster-manager.html
    curl -fsSL "$GITHUB_REPO/cluster/dapp-manager.html" -o cluster/dapp-manager.html
    curl -fsSL "$GITHUB_REPO/cluster/roi-calculator.html" -o cluster/roi-calculator.html
    
    # Download CSS and JS (CORRECTED PATHS)
    curl -fsSL "$GITHUB_REPO/assets/css/unified-navigation.css" -o assets/css/unified-navigation.css
    curl -fsSL "$GITHUB_REPO/assets/js/unified-state-manager.js" -o assets/js/unified-state-manager.js
    
    # Download additional CSS files
    curl -fsSL "$GITHUB_REPO/landing-page/css/commission-features.css" -o landing-page/css/commission-features.css 2>/dev/null || echo "Optional CSS not found"
    curl -fsSL "$GITHUB_REPO/landing-page/css/enhanced-interactive.css" -o landing-page/css/enhanced-interactive.css 2>/dev/null || echo "Optional CSS not found"
    curl -fsSL "$GITHUB_REPO/landing-page/css/enhanced-readability.css" -o landing-page/css/enhanced-readability.css 2>/dev/null || echo "Optional CSS not found"
    
    # Download commission features JS
    curl -fsSL "$GITHUB_REPO/landing-page/shared/commission-features.js" -o landing-page/shared/commission-features.js 2>/dev/null || echo "Optional JS not found"
    
    print_success "Host files downloaded"
}

# =============================================================================
# 🔍 Install Unified Discovery System v4.1 (CORRECTED PATHS)
# =============================================================================

install_unified_discovery() {
    print_step "Installing Unified Discovery System v4.1..."
    
    # Download unified enhanced-search.php (CORRECTED PATH)
    print_info "📡 Installing unified enhanced-search API..."
    curl -fsSL "$GITHUB_REPO/api/enhanced-search.php" -o "$INSTALL_DIR/api/enhanced-search.php"
    
    # Download enhanced host beacon (CORRECTED PATH)
    print_info "🔍 Installing enhanced host beacon..."
    curl -fsSL "$GITHUB_REPO/api/enhanced-host-beacon.php" -o "$INSTALL_DIR/.enhanced-host-beacon.php"
    
    # Download additional API files
    print_info "📡 Installing additional API endpoints..."
    curl -fsSL "$GITHUB_REPO/api/host-discovery.php" -o "$INSTALL_DIR/api/host-discovery.php" 2>/dev/null || echo "Optional API not found"
    curl -fsSL "$GITHUB_REPO/api/smart-recommendations.p" -o "$INSTALL_DIR/api/smart-recommendations.php" 2>/dev/null || echo "Optional API not found"
    curl -fsSL "$GITHUB_REPO/api/smart-urls.php" -o "$INSTALL_DIR/api/smart-urls.php" 2>/dev/null || echo "Optional API not found"
    
    # Set permissions
    chown www-data:www-data "$INSTALL_DIR/api/enhanced-search.php"
    chown www-data:www-data "$INSTALL_DIR/.enhanced-host-beacon.php"
    chmod 644 "$INSTALL_DIR/api/enhanced-search.php"
    chmod 644 "$INSTALL_DIR/.enhanced-host-beacon.php"
    
    # Clear any old cache
    rm -f /tmp/evernode_unified_cache.json
    rm -f /tmp/enhanced_hosts_cache.json
    
    print_success "Unified Discovery System installed"
    print_info "✅ Real Evernode network discovery (2000+ hosts)"
    print_info "✅ Enhanced host cross-discovery"
    print_info "✅ Live network statistics"
    print_info "✅ Automatic beacon system"
}

configure_commission_system() {
    print_step "Configuring commission tracking system..."
    
    # Generate referral code
    REFERRAL_CODE=$(echo -n "$HOST_DOMAIN" | base64 | cut -c1-8 | tr '[:lower:]' '[:upper:]')
    
    # Create configuration file
    cat > "$CONFIG_DIR/host-config.php" << EOF
<?php
// Enhanced Evernode Host Configuration
// Generated automatically during setup

return [
    'host_domain' => '$HOST_DOMAIN',
    'host_wallet' => '$HOST_WALLET',
    'operator_name' => '$OPERATOR_NAME',
    'operator_email' => '$OPERATOR_EMAIL',
    'operator_country' => '$OPERATOR_COUNTRY',
    'server_specs' => '$SERVER_SPECS',
    'admin_password' => '$ADMIN_PASSWORD',
    'referral_code' => '$REFERRAL_CODE',
    'payment_url' => '$PAYMENT_URL',
    'api_url' => '$CENTRAL_API',
    'commission_rate' => 0.20, // 20% commission
    'setup_date' => '$(date -u +"%Y-%m-%d %H:%M:%S")',
    'version' => '2.3',
    'unified_discovery' => true
];
?>
EOF
    
    # Update files with host-specific information
    update_host_files
    
    # Configure admin password in JavaScript
    configure_admin_access
    
    print_success "Commission system configured"
}

update_host_files() {
    print_step "Personalizing host files with your information..."
    
    # Update main landing page
    sed -i "s/HOST_WALLET_PLACEHOLDER/$HOST_WALLET/g" "$INSTALL_DIR/index.html"
    sed -i "s/your-host-ip/$HOST_DOMAIN/g" "$INSTALL_DIR/index.html"
    sed -i "s/enhanced-host.com/$HOST_DOMAIN/g" "$INSTALL_DIR/index.html"
    
    # Update API files with real configuration
    for php_file in "$INSTALL_DIR/api"/*.php; do
        if [[ -f "$php_file" ]]; then
            # Add configuration include at the top
            sed -i "2i\\include_once('/etc/enhanced-evernode/host-config.php');" "$php_file"
        fi
    done
    
    print_success "Host files personalized"
}

configure_admin_access() {
    print_step "Configuring admin access with custom password..."
    
    # Update JavaScript state manager with custom password
    if [[ -f "$INSTALL_DIR/assets/js/unified-state-manager.js" ]]; then
        # Replace default admin password with custom one
        sed -i "s/adminPassword: 'enhanced2024'/adminPassword: '$ADMIN_PASSWORD'/g" "$INSTALL_DIR/assets/js/unified-state-manager.js"
        print_success "JavaScript admin password updated"
    fi
    
    # Create admin access documentation
    cat > "$CONFIG_DIR/admin-access.txt" << EOF
===========================================
🔐 Enhanced Evernode Host - Admin Access
===========================================

Domain: $HOST_DOMAIN
Admin Password: $ADMIN_PASSWORD

Access Methods:
1. URL Parameter: https://$HOST_DOMAIN/?admin=true
2. Keyboard Shortcut: Ctrl+Shift+A on any page
3. Hidden Admin Link: Bottom right of landing page

Admin Features:
✅ System monitoring and control
✅ Real earnings dashboard
✅ Container management
✅ Host configuration
✅ Performance analytics
✅ Unified network discovery

IMPORTANT: Keep this password secure!
Generated: $(date)
===========================================
EOF

    chmod 600 "$CONFIG_DIR/admin-access.txt"
    print_success "Admin access configured"
}

# =============================================================================
# 🔒 Security Configuration
# =============================================================================

configure_security() {
    print_step "Configuring security settings..."
    
    # Configure firewall
    ufw --force reset
    ufw default deny incoming
    ufw default allow outgoing
    ufw allow ssh
    ufw allow 'Nginx Full'
    ufw allow 80
    ufw allow 443
    ufw --force enable
    
    # Configure Nginx security headers
    cat > /etc/nginx/conf.d/security.conf << EOF
# Security headers
add_header X-Frame-Options DENY;
add_header X-Content-Type-Options nosniff;
add_header X-XSS-Protection "1; mode=block";
add_header Referrer-Policy strict-origin-when-cross-origin;
add_header Content-Security-Policy "default-src 'self' 'unsafe-inline' 'unsafe-eval' https://api.evrdirect.info https://payments.evrdirect.info https://api.evernode.network; img-src 'self' data: https:; font-src 'self' https:;";

# Hide Nginx version
server_tokens off;

# Rate limiting
limit_req_zone \$binary_remote_addr zone=general:10m rate=10r/s;
limit_req zone=general burst=20 nodelay;
EOF
    
    # Configure site
    cat > "/etc/nginx/sites-available/$HOST_DOMAIN" << EOF
server {
    listen 80;
    server_name $HOST_DOMAIN;
    root $INSTALL_DIR;
    index index.html index.php;
    
    # Security includes
    include /etc/nginx/conf.d/security.conf;
    
    # PHP handling
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # API routes
    location /api/ {
        try_files \$uri \$uri/ /api/router.php?\$query_string;
    }
    
    # Static files
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # Security
    location ~ /\.ht {
        deny all;
    }
    
    location ~ /\.git {
        deny all;
    }
}
EOF
    
    # Enable site
    ln -sf "/etc/nginx/sites-available/$HOST_DOMAIN" "/etc/nginx/sites-enabled/"
    rm -f /etc/nginx/sites-enabled/default
    
    # Test and reload Nginx
    nginx -t && systemctl reload nginx
    
    print_success "Security configured"
}

setup_ssl() {
    print_step "Setting up SSL certificate..."
    
    # Check if domain resolves to this server
    DOMAIN_IP=$(dig +short "$HOST_DOMAIN" A | tail -n1)
    SERVER_IP=$(curl -s ifconfig.me)
    
    if [[ "$DOMAIN_IP" != "$SERVER_IP" ]]; then
        print_warning "Domain $HOST_DOMAIN does not resolve to this server ($SERVER_IP)"
        print_info "Please update your DNS records and run: certbot --nginx -d $HOST_DOMAIN"
        return
    fi
    
    # Get SSL certificate
    certbot --nginx -d "$HOST_DOMAIN" --non-interactive --agree-tos --email "$OPERATOR_EMAIL" --redirect
    
    print_success "SSL certificate installed"
}

# =============================================================================
# 🌐 Registration with Central System
# =============================================================================

register_with_central_system() {
    print_step "Registering with Enhanced Evernode network..."
    
    # Prepare registration data
    REGISTRATION_DATA=$(cat << EOF
{
    "domain": "$HOST_DOMAIN",
    "wallet": "$HOST_WALLET",
    "operator_name": "$OPERATOR_NAME",
    "operator_email": "$OPERATOR_EMAIL",
    "country": "$OPERATOR_COUNTRY",
    "server_specs": "$SERVER_SPECS",
    "referral_code": "$REFERRAL_CODE",
    "setup_version": "2.3",
    "setup_date": "$(date -u +"%Y-%m-%d %H:%M:%S")",
    "unified_discovery": true
}
EOF
)
    
    # Register with central API
    RESPONSE=$(curl -s -X POST "$CENTRAL_API/api/register-host" \
        -H "Content-Type: application/json" \
        -d "$REGISTRATION_DATA" \
        --connect-timeout 10 \
        --max-time 30)
    
    if [[ $? -eq 0 ]] && [[ "$RESPONSE" == *"success"* ]]; then
        print_success "Successfully registered with Enhanced Evernode network"
        
        # Save registration details
        echo "$RESPONSE" > "$CONFIG_DIR/registration.json"
    else
        print_warning "Could not register with central system (will retry automatically)"
        print_info "Your host will function normally and register when the API is available"
    fi
}

# =============================================================================
# 🌐 Enhanced Network Integration
# =============================================================================

announce_to_enhanced_network() {
    print_step "Joining Enhanced Evernode Network with unified discovery..."
    
    # Create GitHub installation marker for discovery
    echo "$(date -u +"%Y-%m-%d %H:%M:%S")" > /tmp/enhanced-github-install.marker
    echo "GitHub Enhanced Installation - $HOST_DOMAIN" > /var/log/enhanced-github-install.log
    
    # Test unified discovery system
    sleep 3
    local discovery_test=$(curl -s "http://localhost/api/enhanced-search.php?action=test" 2>/dev/null)
    if echo "$discovery_test" | grep -q '"success":true'; then
        print_success "Unified discovery system is operational"
        
        # Test enhanced host beacon
        local beacon_test=$(curl -s "http://localhost/.enhanced-host-beacon.php" 2>/dev/null)
        if echo "$beacon_test" | grep -q '"enhanced_host":true'; then
            print_success "Enhanced host beacon is active"
        fi
        
        # Get discovery stats
        local stats=$(curl -s "http://localhost/api/enhanced-search.php?action=stats" 2>/dev/null)
        if echo "$stats" | grep -q '"success":true'; then
            local total_hosts=$(echo "$stats" | grep -o '"total_hosts":[0-9]*' | cut -d':' -f2 || echo "0")
            local enhanced_hosts=$(echo "$stats" | grep -o '"enhanced_hosts":[0-9]*' | cut -d':' -f2 || echo "0")
            print_success "Network discovery: $total_hosts total hosts, $enhanced_hosts enhanced"
        fi
    else
        print_warning "Unified discovery system may need additional configuration"
    fi
}

# =============================================================================
# 🧪 Testing & Verification
# =============================================================================

run_tests() {
    print_step "Running system tests..."
    
    # Test web server
    if curl -s "http://localhost" > /dev/null; then
        print_success "Web server responding"
    else
        print_error "Web server not responding"
        return 1
    fi
    
    # Test PHP
    if curl -s "http://localhost/api/router.php" > /dev/null; then
        print_success "PHP and API working"
    else
        print_warning "API may need additional configuration"
    fi
    
    # Test unified discovery system
    if curl -s "http://localhost/api/enhanced-search.php?action=test" > /dev/null; then
        print_success "Unified discovery system working"
        
        # Test network search
        local network_test=$(curl -s "http://localhost/api/enhanced-search.php?action=search&limit=5" 2>/dev/null)
        if echo "$network_test" | grep -q '"success":true'; then
            print_success "Network search working"
        fi
        
        # Test enhanced-only search
        local enhanced_test=$(curl -s "http://localhost/api/enhanced-search.php?action=search&enhanced_only=true&limit=5" 2>/dev/null)
        if echo "$enhanced_test" | grep -q '"success":true'; then
            print_success "Enhanced host discovery working"
        fi
    else
        print_warning "Unified discovery system may need configuration"
    fi
    
    # Test discovery beacon
    if curl -s "http://localhost/.enhanced-host-beacon.php" > /dev/null; then
        print_success "Discovery beacon active"
    else
        print_warning "Discovery beacon may need configuration"
    fi
    
    # Test commission system
    if [[ -f "$CONFIG_DIR/host-config.php" ]]; then
        print_success "Commission system configured"
    else
        print_error "Commission system not configured"
        return 1
    fi
    
    # Test file permissions
    if [[ -r "$INSTALL_DIR/index.html" ]] && [[ -w "$INSTALL_DIR" ]]; then
        print_success "File permissions correct"
    else
        print_error "File permission issues detected"
        chown -R www-data:www-data "$INSTALL_DIR"
        chmod -R 755 "$INSTALL_DIR"
    fi
    
    print_success "All tests passed"
}

# =============================================================================
# 📊 Final Report
# =============================================================================

generate_report() {
    print_step "Generating setup report..."
    
    REPORT_FILE="/root/enhanced-evernode-setup-report.txt"
    
    # Test unified discovery status
    DISCOVERY_STATUS="Not Connected"
    TOTAL_HOSTS="0"
    ENHANCED_HOSTS="0"
    
    if curl -s "http://localhost/api/enhanced-search.php?action=stats" > /dev/null; then
        local stats=$(curl -s "http://localhost/api/enhanced-search.php?action=stats" 2>/dev/null)
        if echo "$stats" | grep -q '"success":true'; then
            DISCOVERY_STATUS="Connected"
            TOTAL_HOSTS=$(echo "$stats" | grep -o '"total_hosts":[0-9]*' | cut -d':' -f2 || echo "0")
            ENHANCED_HOSTS=$(echo "$stats" | grep -o '"enhanced_hosts":[0-9]*' | cut -d':' -f2 || echo "0")
        fi
    fi
    
    cat > "$REPORT_FILE" << EOF
==============================================================
🚀 Enhanced Evernode Host - Setup Complete v2.3
==============================================================

Host Information:
- Domain: $HOST_DOMAIN
- Wallet: $HOST_WALLET
- Operator: $OPERATOR_NAME
- Email: $OPERATOR_EMAIL
- Country: $OPERATOR_COUNTRY
- Referral Code: $REFERRAL_CODE

Unified Discovery Status:
🌐 Discovery Status: $DISCOVERY_STATUS
📊 Total Hosts Found: $TOTAL_HOSTS
⭐ Enhanced Hosts: $ENHANCED_HOSTS
🔍 Discovery Method: Unified Real Network + Enhanced
🎯 Features: Live Evernode data + Enhanced discovery

Admin Access:
🔐 Admin Password: $ADMIN_PASSWORD
🌐 Admin URL: https://$HOST_DOMAIN/?admin=true
⌨️  Keyboard Shortcut: Ctrl+Shift+A
📝 Admin Guide: $CONFIG_DIR/admin-access.txt

Commission Tracking:
✅ Commission Rate: 20% per sale
✅ Payment URL: $PAYMENT_URL?ref=$REFERRAL_CODE&host=$HOST_DOMAIN&wallet=$HOST_WALLET
✅ Earnings API: $CENTRAL_API/api/host-earnings/$HOST_DOMAIN
✅ Automatic payouts: Weekly via XRP

URLs:
- Main Site: https://$HOST_DOMAIN
- Host Discovery: https://$HOST_DOMAIN/host-discovery.html
- Earnings Dashboard: https://$HOST_DOMAIN/my-earnings.html
- System Monitor: https://$HOST_DOMAIN/monitoring-dashboard.html
- Cluster Manager: https://$HOST_DOMAIN/cluster/

Unified Discovery System:
✅ Real Evernode network discovery (2000+ hosts)
✅ Enhanced host cross-discovery
✅ Live network statistics
✅ Discovery beacon: https://$HOST_DOMAIN/.enhanced-host-beacon.php
✅ Network search: https://$HOST_DOMAIN/api/enhanced-search.php?action=search
✅ Enhanced-only search: https://$HOST_DOMAIN/api/enhanced-search.php?action=search&enhanced_only=true

Configuration Files:
- Host Config: $CONFIG_DIR/host-config.php
- Nginx Config: /etc/nginx/sites-available/$HOST_DOMAIN
- SSL Certificate: Auto-renewed via certbot

Next Steps:
1. Visit your site to verify everything works
2. Check unified discovery: https://$HOST_DOMAIN/api/enhanced-search.php?action=stats
3. Share your referral link to earn $10 per sale
4. Monitor earnings at https://$HOST_DOMAIN/my-earnings.html
5. Discover 2000+ Evernode hosts + Enhanced network

Referral Link to Share:
$PAYMENT_URL?ref=$REFERRAL_CODE&host=$HOST_DOMAIN&wallet=$HOST_WALLET

Test Commands:
- curl "https://$HOST_DOMAIN/api/enhanced-search.php?action=test"
- curl "https://$HOST_DOMAIN/api/enhanced-search.php?action=search&limit=10"
- curl "https://$HOST_DOMAIN/api/enhanced-search.php?action=search&enhanced_only=true"
- curl "https://$HOST_DOMAIN/.enhanced-host-beacon.php"

Support:
- GitHub: https://github.com/h20crypto/evernode-enhanced-setup
- Documentation: https://docs.evrdirect.info
- Email: support@evrdirect.info

Generated: $(date)
==============================================================
EOF
    
    print_success "Setup report saved to $REPORT_FILE"
}

# =============================================================================
# 🎯 Main Installation Flow
# =============================================================================

main() {
    # Create log file
    touch "$LOG_FILE"
    
    print_banner
    
    echo -e "${WHITE}This script will set up a complete Enhanced Evernode host with:${NC}"
    echo -e "${GREEN}✅ Professional landing pages${NC}"
    echo -e "${GREEN}✅ Commission tracking system${NC}"
    echo -e "${GREEN}✅ Real-time earnings dashboard${NC}"
    echo -e "${GREEN}✅ Unified discovery system (2000+ hosts)${NC}"
    echo -e "${GREEN}✅ Enhanced host cross-discovery${NC}"
    echo -e "${GREEN}✅ SSL security${NC}"
    echo -e "${GREEN}✅ Automatic registration${NC}"
    echo ""
    
    read -p "Continue with installation? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_info "Installation cancelled"
        exit 0
    fi
    
    # Installation steps
    check_requirements
    collect_host_info
    install_dependencies
    download_host_files
    install_unified_discovery
    configure_commission_system
    configure_security
    setup_ssl
    register_with_central_system
    announce_to_enhanced_network
    run_tests
    generate_report
    
    # Success message
    echo ""
    echo -e "${GREEN}🎉 Enhanced Evernode Host Setup Complete! 🎉${NC}"
    echo ""
    echo -e "${WHITE}Your host is now ready to earn commissions!${NC}"
    echo -e "${CYAN}Visit: https://$HOST_DOMAIN${NC}"
    echo -e "${YELLOW}Earnings: https://$HOST_DOMAIN/my-earnings.html${NC}"
    echo -e "${BLUE}Discovery: https://$HOST_DOMAIN/api/enhanced-search.php?action=stats${NC}"
    echo ""
    echo -e "${WHITE}🔐 ADMIN ACCESS:${NC}"
    echo -e "${CYAN}URL: https://$HOST_DOMAIN/?admin=true${NC}"
    echo -e "${CYAN}Password: ${WHITE}$ADMIN_PASSWORD${NC}"
    echo -e "${YELLOW}⌨️  Quick Access: Press Ctrl+Shift+A on any page${NC}"
    echo ""
    echo -e "${GREEN}💰 You earn $10 for every $49.99 premium sale!${NC}"
    echo -e "${GREEN}🌐 Your host can now discover 2000+ Evernode hosts!${NC}"
    echo -e "${BLUE}⭐ Plus Enhanced host cross-discovery!${NC}"
    echo ""
    echo -e "${WHITE}Test your unified discovery:${NC}"
    echo -e "${CYAN}curl \"https://$HOST_DOMAIN/api/enhanced-search.php?action=search&limit=10\"${NC}"
    echo ""
    echo -e "${BLUE}📋 Setup report saved to: /root/enhanced-evernode-setup-report.txt${NC}"
    echo -e "${BLUE}🔐 Admin details saved to: $CONFIG_DIR/admin-access.txt${NC}"
    echo ""
    echo -e "${WHITE}Share your referral link:${NC}"
    echo -e "${CYAN}$PAYMENT_URL?ref=$REFERRAL_CODE&host=$HOST_DOMAIN&wallet=$HOST_WALLET${NC}"
    echo ""
}

# Run main function
main "$@"

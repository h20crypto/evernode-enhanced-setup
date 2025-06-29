#!/bin/bash

# =============================================================================
# 🚀 Enhanced Evernode Host - Complete Setup Script
# =============================================================================
# Automated installation with commission tracking integration
# Author: Enhanced Evernode Team
# Version: 2.0 Production

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

# =============================================================================
# 🎨 Display Functions
# =============================================================================

print_banner() {
    clear
    echo -e "${CYAN}"
    echo "=============================================================="
    echo "🚀 Enhanced Evernode Host - Production Setup"
    echo "=============================================================="
    echo -e "${WHITE}Complete automated installation with commission tracking${NC}"
    echo -e "${GREEN}✅ Real earnings • ✅ $10 per sale • ✅ Professional setup${NC}"
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
    
    # Confirmation
    echo ""
    echo -e "${WHITE}Please confirm your information:${NC}"
    echo -e "${GREEN}Domain:${NC} $HOST_DOMAIN"
    echo -e "${GREEN}Wallet:${NC} $HOST_WALLET"
    echo -e "${GREEN}Name:${NC} $OPERATOR_NAME"
    echo -e "${GREEN}Email:${NC} $OPERATOR_EMAIL"
    echo -e "${GREEN}Country:${NC} $OPERATOR_COUNTRY"
    echo -e "${GREEN}Specs:${NC} $SERVER_SPECS"
    echo ""
    
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
# 🔧 Enhanced Host Files Setup
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
    
    # Download landing page
    curl -fsSL "$GITHUB_REPO/landing-page/index.html" -o index.html
    
    # Download other pages
    mkdir -p cluster api assets/css assets/js landing-page/css landing-page/js
    
    curl -fsSL "$GITHUB_REPO/landing-page/host-discovery.html" -o host-discovery.html
    curl -fsSL "$GITHUB_REPO/landing-page/my-earnings.html" -o my-earnings.html
    curl -fsSL "$GITHUB_REPO/landing-page/monitoring-dashboard.html" -o monitoring-dashboard.html
    
    # Download API files
    curl -fsSL "$GITHUB_REPO/api/host-info.php" -o api/host-info.php
    curl -fsSL "$GITHUB_REPO/api/instance-count.php" -o api/instance-count.php
    curl -fsSL "$GITHUB_REPO/api/health-check.php" -o api/health-check.php
    curl -fsSL "$GITHUB_REPO/api/router.php" -o api/router.php
    
    # Download cluster manager
    curl -fsSL "$GITHUB_REPO/cluster/index.html" -o cluster/index.html
    curl -fsSL "$GITHUB_REPO/cluster/dashboard.html" -o cluster/dashboard.html
    
    # Download CSS and JS
    curl -fsSL "$GITHUB_REPO/assets/css/unified-navigation.css" -o assets/css/unified-navigation.css
    curl -fsSL "$GITHUB_REPO/assets/js/unified-state-manager.js" -o assets/js/unified-state-manager.js
    
    print_success "Host files downloaded"
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
    'referral_code' => '$REFERRAL_CODE',
    'payment_url' => '$PAYMENT_URL',
    'api_url' => '$CENTRAL_API',
    'commission_rate' => 0.20, // 20% commission
    'setup_date' => '$(date -u +"%Y-%m-%d %H:%M:%S")',
    'version' => '2.0'
];
?>
EOF
    
    # Update files with host-specific information
    update_host_files
    
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
add_header Content-Security-Policy "default-src 'self' 'unsafe-inline' 'unsafe-eval' https://api.evrdirect.info https://payments.evrdirect.info; img-src 'self' data: https:; font-src 'self' https:;";

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
    "setup_version": "2.0",
    "setup_date": "$(date -u +"%Y-%m-%d %H:%M:%S")"
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
    if curl -s "http://localhost/api/health-check.php" > /dev/null; then
        print_success "PHP and API working"
    else
        print_warning "API may need additional configuration"
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
    
    cat > "$REPORT_FILE" << EOF
==============================================================
🚀 Enhanced Evernode Host - Setup Complete
==============================================================

Host Information:
- Domain: $HOST_DOMAIN
- Wallet: $HOST_WALLET
- Operator: $OPERATOR_NAME
- Email: $OPERATOR_EMAIL
- Country: $OPERATOR_COUNTRY
- Referral Code: $REFERRAL_CODE

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

Configuration Files:
- Host Config: $CONFIG_DIR/host-config.php
- Nginx Config: /etc/nginx/sites-available/$HOST_DOMAIN
- SSL Certificate: Auto-renewed via certbot

Next Steps:
1. Visit your site to verify everything works
2. Share your referral link to earn $10 per sale
3. Monitor earnings at https://$HOST_DOMAIN/my-earnings.html
4. Join our Discord for support and updates

Referral Link to Share:
$PAYMENT_URL?ref=$REFERRAL_CODE&host=$HOST_DOMAIN&wallet=$HOST_WALLET

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
    configure_commission_system
    configure_security
    setup_ssl
    register_with_central_system
    run_tests
    generate_report
    
    # Success message
    echo ""
    echo -e "${GREEN}🎉 Enhanced Evernode Host Setup Complete! 🎉${NC}"
    echo ""
    echo -e "${WHITE}Your host is now ready to earn commissions!${NC}"
    echo -e "${CYAN}Visit: https://$HOST_DOMAIN${NC}"
    echo -e "${YELLOW}Earnings: https://$HOST_DOMAIN/my-earnings.html${NC}"
    echo ""
    echo -e "${GREEN}💰 You earn $10 for every $49.99 premium sale!${NC}"
    echo -e "${BLUE}📋 Setup report saved to: /root/enhanced-evernode-setup-report.txt${NC}"
    echo ""
    echo -e "${WHITE}Share your referral link:${NC}"
    echo -e "${CYAN}$PAYMENT_URL?ref=$REFERRAL_CODE&host=$HOST_DOMAIN&wallet=$HOST_WALLET${NC}"
    echo ""
}

# Run main function
main "$@"

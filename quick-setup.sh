#!/bin/bash

# Enhanced Evernode Setup Script - User Friendly Version with Secure Configuration
# Supports both root and non-root execution

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Print functions
print_header() {
    echo -e "${BLUE}🚀 Enhanced Evernode Setup with Secure Configuration${NC}"
    echo "=================================================="
    echo "Domain: $(hostname -f 2>/dev/null || hostname)"
    echo "Install Directory: /var/www/html"
    echo ""
}

print_info() {
    echo -e "${CYAN}ℹ️  $1${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

# NEW: Secure configuration setup
configure_user_settings() {
    print_info "🔐 Configuring your Enhanced Evernode host..."
    echo ""
    print_info "Please provide your host information for secure setup:"
    echo ""
    
    # Get user's domain
    DEFAULT_DOMAIN=$(hostname -f 2>/dev/null || hostname)
    read -p "Enter your domain [$DEFAULT_DOMAIN]: " USER_DOMAIN
    USER_DOMAIN=${USER_DOMAIN:-$DEFAULT_DOMAIN}
    
    # Get user's Xahau address
    EVERNODE_ADDRESS=$(evernode config account 2>/dev/null | grep "Address:" | awk '{print $2}' || echo "")
    if [ -n "$EVERNODE_ADDRESS" ]; then
        read -p "Enter your Xahau address [$EVERNODE_ADDRESS]: " USER_ADDRESS
        USER_ADDRESS=${USER_ADDRESS:-$EVERNODE_ADDRESS}
    else
        read -p "Enter your Xahau address: " USER_ADDRESS
        while [ -z "$USER_ADDRESS" ]; do
            print_warning "Xahau address is required!"
            read -p "Enter your Xahau address: " USER_ADDRESS
        done
    fi
    
    # Get user's admin password
    while true; do
        read -s -p "Create admin password (min 8 characters): " USER_PASSWORD
        echo ""
        if [ ${#USER_PASSWORD} -lt 8 ]; then
            print_warning "Password must be at least 8 characters!"
            continue
        fi
        read -s -p "Confirm admin password: " USER_PASSWORD_CONFIRM
        echo ""
        if [ "$USER_PASSWORD" = "$USER_PASSWORD_CONFIRM" ]; then
            break
        else
            print_warning "Passwords don't match! Try again."
        fi
    done
    
    # Get user's operator name (optional)
    read -p "Enter your name/organization [Enhanced Host]: " USER_OPERATOR
    USER_OPERATOR=${USER_OPERATOR:-"Enhanced Host"}
    
    # Get instance limit
    EVERNODE_INSTANCES=$(evernode totalins 2>/dev/null || echo "5")
    read -p "Enter your instance limit [$EVERNODE_INSTANCES]: " USER_INSTANCES
    USER_INSTANCES=${USER_INSTANCES:-$EVERNODE_INSTANCES}
    
    echo ""
    print_success "Configuration collected successfully!"
    print_info "Domain: $USER_DOMAIN"
    print_info "Xahau Address: $USER_ADDRESS"
    print_info "Operator: $USER_OPERATOR"
    print_info "Instance Limit: $USER_INSTANCES"
    echo ""
    
    # Generate secure hash for password
    USER_PASSWORD_HASH=$(php -r "echo password_hash('$USER_PASSWORD', PASSWORD_ARGON2ID);" 2>/dev/null || echo "$USER_PASSWORD")
    
    # Generate session secret
    SESSION_SECRET=$(openssl rand -hex 32 2>/dev/null || echo "CHANGE_THIS_SECRET_$(date +%s)")
    
    print_status "Secure configuration prepared"
}

# NEW: Apply user configuration to downloaded files
apply_user_configuration() {
    print_info "🔧 Applying your configuration to Enhanced Evernode files..."
    
    # Update landing page with user's password
    if [ -f "/var/www/html/index.html" ]; then
        sudo sed -i "s/password === 'CHANGE_THIS_PASSWORD'/password === '$USER_PASSWORD'/g" /var/www/html/index.html
        sudo sed -i "s/enhanced2024/$USER_PASSWORD/g" /var/www/html/index.html
    fi
    
    # Update any unified state manager
    if [ -f "/var/www/html/assets/js/unified-state-manager.js" ]; then
        sudo sed -i "s/adminPassword: 'CHANGE_THIS_PASSWORD'/adminPassword: '$USER_PASSWORD'/g" /var/www/html/assets/js/unified-state-manager.js
        sudo sed -i "s/enhanced2024/$USER_PASSWORD/g" /var/www/html/assets/js/unified-state-manager.js
    fi
    
    # Create secure config.php
    sudo tee /var/www/html/config.php > /dev/null << CONFIGEOF
<?php
/**
 * Enhanced Evernode Configuration
 * Generated during installation: $(date)
 */

// Host Information
define('HOST_DOMAIN', '$USER_DOMAIN');
define('XAHAU_ADDRESS', '$USER_ADDRESS');
define('HOST_OPERATOR', '$USER_OPERATOR');

// Admin Security
define('ADMIN_PASSWORD_HASH', '$USER_PASSWORD_HASH');
define('SESSION_SECRET', '$SESSION_SECRET');

// Host Configuration
define('INSTANCE_LIMIT', $USER_INSTANCES);
define('COMMISSION_RATE', 0.20);           // 20% commission
define('LEASE_AMOUNT_EVR', '0.005');       // EVR per hour

// Enhanced Features
define('ENHANCED_FEATURES', [
    'Enhanced',
    'Discovery', 
    'Cluster Manager',
    'Real-time Monitoring',
    'Auto Deploy Commands',
    'Commission System'
]);

// API Configuration
define('API_RATE_LIMIT', 100);             // Requests per minute
define('CACHE_DURATION', 300);             // 5 minutes

// Contact Information
define('CONTACT_EMAIL', 'admin@$USER_DOMAIN');
define('SUPPORT_URL', 'https://$USER_DOMAIN/');

// Installation Info
define('INSTALL_DATE', '$(date)');
define('INSTALL_VERSION', '3.1.0-secure');
?>
CONFIGEOF
    
    # Update API files to use configuration
    if [ -f "/var/www/html/api/host-info.php" ]; then
        # Add config include at the top of API files
        sudo sed -i '1i<?php include_once "../config.php"; ?>' /var/www/html/api/host-info.php 2>/dev/null || true
    fi
    
    # Update enhanced-hosts.json with user's info
    if [ -f "/var/www/html/data/enhanced-hosts.json" ]; then
        sudo sed -i "s/rYOUR_XAHAU_ADDRESS_HERE/$USER_ADDRESS/g" /var/www/html/data/enhanced-hosts.json
        sudo sed -i "s/your-domain.com/$USER_DOMAIN/g" /var/www/html/data/enhanced-hosts.json
        sudo sed -i "s/Your Name or Organization/$USER_OPERATOR/g" /var/www/html/data/enhanced-hosts.json
    fi
    
    # Protect the config file
    sudo chmod 600 /var/www/html/config.php
    sudo chown www-data:www-data /var/www/html/config.php
    
    print_status "User configuration applied successfully"
}

# Check root and provide options
check_permissions() {
    if [[ $EUID -eq 0 ]]; then
        print_warning "Running as root detected."
        print_info "For security, it's recommended to run as non-root user."
        echo ""
        echo "Options:"
        echo "1. Continue as root (works but not recommended)"
        echo "2. Create non-root user and switch"
        echo "3. Exit and run manually"
        echo ""
        
        # Check for force flag
        if [[ "$1" == "--force-root" ]] || [[ "$1" == "-f" ]]; then
            print_warning "Force flag detected. Continuing as root..."
            return 0
        fi
        
        read -p "Choose option (1/2/3): " -n 1 -r
        echo ""
        
        case $REPLY in
            1)
                print_warning "Continuing as root. Please ensure this is a dedicated Evernode server."
                sleep 2
                return 0
                ;;
            2)
                print_info "Creating evernode user..."
                if ! id "evernode" &>/dev/null; then
                    adduser --disabled-password --gecos "" evernode
                    usermod -aG sudo evernode
                fi
                print_success "Switching to evernode user..."
                exec su evernode -c "$(readlink -f "$0") --as-user"
                ;;
            3)
                print_info "Exiting. You can run manually with individual commands."
                print_info "See: https://github.com/h20crypto/evernode-enhanced-setup"
                exit 0
                ;;
            *)
                print_error "Invalid option. Exiting."
                exit 1
                ;;
        esac
    else
        print_success "Running as non-root user: $(whoami)"
        # Check if user has sudo access for necessary commands
        if ! sudo -n true 2>/dev/null; then
            print_warning "User needs sudo access for system modifications."
            print_info "Some operations require elevated privileges."
        fi
    fi
}

# Install dependencies
install_dependencies() {
    print_info "Installing dependencies..."
    
    # Update package list
    if command -v apt-get >/dev/null 2>&1; then
        sudo apt-get update -qq
        sudo apt-get install -y curl wget nginx php-fpm php-curl php-json php-sqlite3 sqlite3 jq
    elif command -v yum >/dev/null 2>&1; then
        sudo yum update -y
        sudo yum install -y curl wget nginx php-fpm php-curl php-json jq
    else
        print_warning "Package manager not detected. Please install: curl, wget, nginx, php-fpm manually"
    fi
    
    print_status "Dependencies installed"
}

# Create directory structure
setup_directories() {
    print_info "Setting up directory structure..."
    
    # Create main directories
    sudo mkdir -p /var/www/html/{api,cluster,data,assets,tools,css,js,widgets}
    sudo mkdir -p /opt/evernode-enhanced/{logs,backups,dhali_cache}
    
    # Set ownership based on running user
    if [[ $EUID -eq 0 ]]; then
        chown -R www-data:www-data /var/www/html
        chown -R www-data:www-data /opt/evernode-enhanced
    else
        sudo chown -R www-data:www-data /var/www/html
        sudo chown -R www-data:www-data /opt/evernode-enhanced
    fi
    
    # Set permissions
    sudo chmod -R 755 /var/www/html
    sudo chmod -R 766 /var/www/html/data
    sudo chmod -R 766 /opt/evernode-enhanced/dhali_cache
    
    print_status "Directory structure created"
}

# Download enhanced files
download_enhanced_files() {
    print_info "Downloading enhanced Evernode files..."
    
    # Define github_base FIRST
    local github_base="https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main"
    
    # Download landing page files FIRST (most important)
    print_info "📄 Downloading landing page..."
    sudo curl -fsSL "$github_base/landing-page/index.html" -o /var/www/html/index.html
    sudo curl -fsSL "$github_base/landing-page/monitoring-dashboard.html" -o /var/www/html/monitoring-dashboard.html
    sudo curl -fsSL "$github_base/landing-page/my-earnings.html" -o /var/www/html/my-earnings.html
    sudo curl -fsSL "$github_base/landing-page/leaderboard.html" -o /var/www/html/leaderboard.html
    sudo curl -fsSL "$github_base/landing-page/host-discovery.html" -o /var/www/html/host-discovery.html
    
    # Download enhanced interactive components
    print_info "✨ Downloading enhanced interactive components..."
    sudo curl -fsSL "$github_base/landing-page/css/enhanced-interactive.css" -o /var/www/html/css/enhanced-interactive.css 2>/dev/null || print_warning "Enhanced CSS not found"
    sudo curl -fsSL "$github_base/landing-page/js/enhanced-interactive.js" -o /var/www/html/js/enhanced-interactive.js 2>/dev/null || print_warning "Enhanced JS not found"
    sudo curl -fsSL "$github_base/landing-page/js/autonomous-discovery.js" -o /var/www/html/js/autonomous-discovery.js 2>/dev/null || print_warning "Autonomous JS not found"
    
    # Download commission features (NEW)
    print_info "💰 Downloading commission features..."
    sudo curl -fsSL "$github_base/landing-page/css/commission-features.css" -o /var/www/html/css/commission-features.css 2>/dev/null || print_warning "Commission CSS not found - create landing-page/css/commission-features.css"
    sudo curl -fsSL "$github_base/landing-page/js/commission-features.js" -o /var/www/html/js/commission-features.js 2>/dev/null || print_warning "Commission JS not found - create landing-page/js/commission-features.js"
    
    # Download API files
    print_info "🔧 Downloading API files..."
    sudo curl -fsSL "$github_base/landing-page/api/host-info.php" -o /var/www/html/api/host-info.php
    sudo curl -fsSL "$github_base/landing-page/api/instance-count.php" -o /var/www/html/api/instance-count.php
    
    # Download smart features APIs (only if they exist)
    print_info "🤖 Downloading smart APIs..."
    sudo curl -fsSL "$github_base/landing-page/api/smart-urls.php" -o /var/www/html/api/smart-urls.php 2>/dev/null || print_warning "Smart URLs API not found (optional)"
    sudo curl -fsSL "$github_base/landing-page/api/deployment-status.php" -o /var/www/html/api/deployment-status.php 2>/dev/null || print_warning "Deployment Status API not found (optional)"
    sudo curl -fsSL "$github_base/landing-page/api/host-discovery.php" -o /var/www/html/api/host-discovery.php 2>/dev/null || print_warning "Host Discovery API not found (optional)"
    sudo curl -fsSL "$github_base/landing-page/api/smart-recommendations.php" -o /var/www/html/api/smart-recommendations.php 2>/dev/null || print_warning "Smart Recommendations API not found (optional)"
    
    # Download cluster files
    print_info "🚀 Downloading cluster management..."
    sudo curl -fsSL "$github_base/cluster/create.html" -o /var/www/html/cluster/create.html
    sudo curl -fsSL "$github_base/cluster/paywall.html" -o /var/www/html/cluster/paywall.html
    sudo curl -fsSL "$github_base/cluster/roi-calculator.html" -o /var/www/html/cluster/roi-calculator.html 2>/dev/null || print_warning "ROI calculator not found (optional)"
    sudo curl -fsSL "$github_base/cluster/dapp-manager.html" -o /var/www/html/dapp-manager.html
    sudo curl -fsSL "$github_base/cluster/dashboard.html" -o /var/www/html/cluster/dashboard.html 2>/dev/null || print_warning "Cluster dashboard not found (optional)"
    sudo curl -fsSL "$github_base/cluster/index.html" -o /var/www/html/cluster/index.html 2>/dev/null || print_warning "Cluster index not found (optional)"
    
    # Download live pricing widgets
    print_info "💰 Downloading live pricing widgets..."
    sudo curl -fsSL "$github_base/landing-page/widgets/live-pricing.js" -o /var/www/html/widgets/live-pricing.js 2>/dev/null || print_warning "Live pricing JS not found (optional)"
    sudo curl -fsSL "$github_base/landing-page/widgets/live-pricing.css" -o /var/www/html/widgets/live-pricing.css 2>/dev/null || print_warning "Live pricing CSS not found (optional)"
    
    # Download enhanced configuration (this will have placeholder data)
    print_info "⚙️ Downloading configuration templates..."
    sudo curl -fsSL "$github_base/data/enhanced-hosts.json" -o /var/www/html/data/enhanced-hosts.json 2>/dev/null || print_warning "Enhanced hosts config not found (optional)"
    
    # Optional: Download management tools (only if they exist)
    print_info "🔧 Downloading optional tools..."
    sudo curl -fsSL "$github_base/tools/discovery-manager" -o /usr/local/bin/discovery-manager 2>/dev/null || print_warning "Discovery manager not found (optional)"
    sudo chmod +x /usr/local/bin/discovery-manager 2>/dev/null || true
    
    print_status "Enhanced files downloaded"
}

# Install Node.js for payment API
install_nodejs_payment_api() {
    print_info "🚀 Installing professional payment system..."
    
    # Install Node.js if not present
    if ! command -v node &> /dev/null; then
        curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
        sudo apt-get install -y nodejs
    fi
    
    # Set up payment API
    sudo mkdir -p /var/www/html/payment-api/{src,scripts}
    
    # Download payment API files from your repo
    print_info "📥 Downloading payment API..."
    sudo curl -fsSL "$github_base/payment-api/package.json" -o /var/www/html/payment-api/package.json 2>/dev/null || print_warning "Payment API package.json not found (optional)"
    sudo curl -fsSL "$github_base/payment-api/src/index.ts" -o /var/www/html/payment-api/src/index.ts 2>/dev/null || print_warning "Payment API source not found (optional)"
    sudo curl -fsSL "$github_base/payment-api/scripts/install-and-start.sh" -o /var/www/html/payment-api/scripts/install-and-start.sh 2>/dev/null || print_warning "Payment API scripts not found (optional)"

    # Set permissions and install if files exist
    if [ -f "/var/www/html/payment-api/package.json" ]; then
        sudo chmod +x /var/www/html/payment-api/scripts/install-and-start.sh
        sudo chown -R www-data:www-data /var/www/html/payment-api
        
        cd /var/www/html/payment-api
        sudo npm install 2>/dev/null || print_warning "Payment API npm install failed (optional feature)"
        cd - >/dev/null
        
        print_status "Payment API installation complete"
    else
        print_info "Payment API files not found - skipping (optional feature)"
    fi
}

# Configure web server
configure_webserver() {
    print_info "Configuring web server..."
    
    # Detect PHP version
    PHP_VERSION=$(php -v | head -n1 | cut -d' ' -f2 | cut -d'.' -f1,2)
    print_info "🐘 PHP version detected: $PHP_VERSION"
    
    # Find PHP-FPM socket
    FPM_SOCKET=""
    for version in $PHP_VERSION 8.3 8.2 8.1 8.0; do
        if [[ -S "/var/run/php/php${version}-fpm.sock" ]]; then
            FPM_SOCKET="/var/run/php/php${version}-fpm.sock"
            break
        fi
    done
    
    if [[ -z "$FPM_SOCKET" ]]; then
        print_error "Could not find PHP-FPM socket"
        exit 1
    fi
    
    print_success "🔌 Using PHP-FPM socket: $FPM_SOCKET"
    
    # Create nginx configuration
    sudo tee /etc/nginx/sites-available/default > /dev/null << NGINXEOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    
    root /var/www/html;
    index index.html index.htm index.php;
    
    server_name _;
    
    # Enhanced security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
    
    # CORS headers for API
    add_header Access-Control-Allow-Origin "*" always;
    add_header Access-Control-Allow-Methods "GET, POST, OPTIONS" always;
    add_header Access-Control-Allow-Headers "Origin, Content-Type, Accept" always;
    
    location / {
        try_files \$uri \$uri/ =404;
    }
    
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$FPM_SOCKET;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # API endpoint optimization
    location /api/ {
        try_files \$uri \$uri/ =404;
        location ~ \.php\$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:$FPM_SOCKET;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_cache_bypass 1;
            fastcgi_no_cache 1;
        }
    }
    
    # Payment API proxy (if available)
    location /api/payment/ {
        proxy_pass http://localhost:3000/api/;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
    }
    
    # Deny access to sensitive files
    location ~ /\.(ht|git) {
        deny all;
    }
    
    # Protect config files
    location ~ /config\.php {
        deny all;
    }
}
NGINXEOF
    
    # Start services
    sudo systemctl enable nginx php${PHP_VERSION}-fpm
    sudo systemctl restart php${PHP_VERSION}-fpm
    sudo systemctl restart nginx
    
    print_status "Web server configured and started"
}

# Set final permissions
set_permissions() {
    print_info "Setting final permissions..."
    
    # Set ownership
    sudo chown -R www-data:www-data /var/www/html
    
    # Set file permissions
    sudo find /var/www/html -type f -name "*.html" -exec chmod 644 {} \;
    sudo find /var/www/html -type f -name "*.php" -exec chmod 644 {} \;
    sudo find /var/www/html -type f -name "*.css" -exec chmod 644 {} \;
    sudo find /var/www/html -type f -name "*.js" -exec chmod 644 {} \;
    sudo find /var/www/html -type d -exec chmod 755 {} \;
    
    # Protect sensitive files
    sudo chmod 600 /var/www/html/config.php 2>/dev/null || true
    
    print_status "Permissions set correctly"
}

# Test installation
test_installation() {
    print_info "Testing installation..."
    
    local domain=$(hostname -f 2>/dev/null || hostname)
    
    # Test main page
    if curl -f -s http://localhost/ > /dev/null; then
        print_success "✅ Main page: Accessible"
    else
        print_warning "⚠️  Main page: Not accessible"
    fi
    
    # Test API
    if curl -f -s http://localhost/api/host-info.php > /dev/null; then
        print_success "✅ Host info API: Working"
    else
        print_warning "⚠️  Host info API: Not working"
    fi
    
    # Test configuration
    if [ -f "/var/www/html/config.php" ]; then
        print_success "✅ Configuration: Created and secured"
    else
        print_warning "⚠️  Configuration: Missing"
    fi
    
    # Test services
    if systemctl is-active --quiet nginx; then
        print_success "✅ Nginx: Running"
    else
        print_warning "⚠️  Nginx: Not running"
    fi
    
    print_info "🌐 Your enhanced Evernode host is available at:"
    print_success "   http://$USER_DOMAIN/"
    print_success "   http://$(curl -s ifconfig.me 2>/dev/null)/ (external IP)"
}

# Generate final report
generate_report() {
    print_info "Generating installation report..."
    
    cat > /tmp/evernode-enhanced-report.txt << EOF
🚀 Enhanced Evernode Installation Complete!
==========================================

Installation Date: $(date)
Domain: $USER_DOMAIN
Xahau Address: $USER_ADDRESS
Operator: $USER_OPERATOR
Instance Limit: $USER_INSTANCES
Installation User: $(whoami)

✅ What's Installed:
• Enhanced landing page with professional design
• Real-time monitoring dashboard
• Earnings tracking and leaderboard system
• Host discovery network with commission features
• Cluster management system (requires NFT license)
• Complete API backend with Xahau integration
• Commission tracking system for license sales
• Secure configuration with your personal settings

🔐 Security Features:
• Password-protected admin access
• Secure configuration file (600 permissions)
• Session security with random secrets
• Protected API endpoints

📁 File Structure:
• Main site: /var/www/html/
• APIs: /var/www/html/api/
• Cluster system: /var/www/html/cluster/
• Configuration: /var/www/html/config.php (SECURED)
• Interactive features: /var/www/html/css/ & /var/www/html/js/

🔧 Admin Access:
• Password: [SET BY YOU DURING INSTALLATION]
• Access via: Ctrl+Shift+A or ?admin=true parameter

🌐 Your Enhanced Host URLs:
• Main: http://$USER_DOMAIN/
• Host Discovery: http://$USER_DOMAIN/host-discovery.html
• dApp Manager: http://$USER_DOMAIN/dapp-manager.html
• Monitoring: http://$USER_DOMAIN/monitoring-dashboard.html
• Earnings: http://$USER_DOMAIN/my-earnings.html
• Leaderboard: http://$USER_DOMAIN/leaderboard.html

💰 Commission Features:
• Enhanced hosts earn 15% commission on cluster license sales
• Automatic tracking via smart contracts
• Passive income opportunity for premium hosts

🎯 Support:
• GitHub: https://github.com/h20crypto/evernode-enhanced-setup
• Enhanced Features: Real-time monitoring, NFT licenses, competitive leaderboards

Happy hosting! 🚀
EOF

    sudo cp /tmp/evernode-enhanced-report.txt /var/www/html/installation-report.txt
    sudo chown www-data:www-data /var/www/html/installation-report.txt
    
    print_status "Installation report saved to /var/www/html/installation-report.txt"
}

# Main installation function
main() {
    print_header
    
    # Handle script arguments
    check_permissions "$1"
    
    print_info "🌟 Starting Enhanced Evernode installation with secure configuration..."
    echo ""
    
    # NEW: Get user configuration FIRST
    configure_user_settings
    echo ""
    
    install_dependencies
    setup_directories
    download_enhanced_files
    
    # NEW: Apply user configuration to downloaded files
    apply_user_configuration
    echo ""
    
    install_nodejs_payment_api
    configure_webserver
    set_permissions
    test_installation
    generate_report
    
    echo ""
    print_success "🎉 Enhanced Evernode installation completed successfully!"
    echo ""
    print_info "🌟 Your enhanced Evernode host is now ready with:"
    print_info "   • Professional landing page with YOUR configuration"
    print_info "   • Real-time monitoring and analytics"
    print_info "   • Earnings tracking and leaderboards"
    print_info "   • Host discovery network with commission features"
    print_info "   • NFT-based cluster management system"
    print_info "   • Commission earning system for license sales"
    print_info "   • Secure admin access with YOUR password"
    echo ""
    print_info "🔧 View installation report:"
    print_info "   http://$USER_DOMAIN/installation-report.txt"
    echo ""
    print_info "🔐 Admin Access:"
    print_info "   Use Ctrl+Shift+A or add ?admin=true to any page"
    print_info "   Enter your password when prompted"
    echo ""
    print_success "🚀 Your enhanced host is ready to compete and earn commissions!"
    echo ""
}

# Run main installation
main "$@"

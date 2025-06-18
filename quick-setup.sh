#!/bin/bash

# Enhanced Evernode Setup Script - User Friendly Version
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
    echo -e "${BLUE}🚀 Enhanced Evernode Setup with Dhali Oracle Integration${NC}"
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
        sudo apt-get install -y curl wget nginx php-fpm php-curl php-json jq
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
    sudo mkdir -p /var/www/html/{api,cluster,data,assets,tools}
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
    sudo curl -fsSL "$github_base/cluster/dapp-manager.html" -o /var/www/html/cluster/dapp-manager.html
    local github_base="https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main"

     # Download enhanced interactive components
    print_info "✨ Downloading smart features..."
    sudo mkdir -p /var/www/html/css /var/www/html/js
    sudo curl -fsSL "$github_base/landing-page/css/enhanced-interactive.css" -o /var/www/html/css/enhanced-interactive.css 2>/dev/null || print_warning "Enhanced CSS not found"
    sudo curl -fsSL "$github_base/landing-page/js/autonomous-discovery.js" -o /var/www/html/js/autonomous-discovery.js 2>/dev/null || print_warning "Autonomous JS not found"
    
    # Download smart features APIs
    print_info "🤖 Downloading autonomous discovery APIs..."
    sudo curl -fsSL "$github_base/landing-page/api/smart-urls.php" -o /var/www/html/api/smart-urls.php 2>/dev/null || print_warning "Smart URLs API not found"
    sudo curl -fsSL "$github_base/landing-page/api/deployment-status.php" -o /var/www/html/api/deployment-status.php 2>/dev/null || print_warning "Deployment Status API not found"
    sudo curl -fsSL "$github_base/landing-page/api/host-discovery.php" -o /var/www/html/api/host-discovery.php 2>/dev/null || print_warning "Host Discovery API not found"
    sudo curl -fsSL "$github_base/landing-page/api/smart-recommendations.php" -o /var/www/html/api/smart-recommendations.php 2>/dev/null || print_warning "Smart Recommendations API not found"
    
    # Download management tools
    print_info "🔧 Downloading discovery management tools..."
    sudo curl -fsSL "$github_base/tools/discovery-manager" -o /usr/local/bin/discovery-manager 2>/dev/null || print_warning "Discovery manager not found"
    sudo chmod +x /usr/local/bin/discovery-manager 2>/dev/null || true
    
    # Download service files
    print_info "⚙️ Downloading discovery services..."
    sudo mkdir -p /etc/systemd/system
    sudo curl -fsSL "$github_base/services/evernode-discovery.service" -o /etc/systemd/system/evernode-discovery.service 2>/dev/null || print_warning "Discovery service not found"
    sudo curl -fsSL "$github_base/services/evernode-discovery" -o /usr/local/bin/evernode-discovery 2>/dev/null || print_warning "Discovery daemon not found"
    sudo chmod +x /usr/local/bin/evernode-discovery 2>/dev/null || true
    sudo systemctl daemon-reload 2>/dev/null || true
    
    # Download landing page files
    print_info "📄 Downloading landing page..."
   sudo curl -fsSL "$github_base/landing-page/index.html" -o /var/www/html/index.html
    sudo curl -fsSL "$github_base/landing-page/monitoring-dashboard.html" -o /var/www/html/monitoring-dashboard.html
    sudo curl -fsSL "$github_base/landing-page/my-earnings.html" -o /var/www/html/my-earnings.html
    sudo curl -fsSL "$github_base/landing-page/leaderboard.html" -o /var/www/html/leaderboard.html
    sudo curl -fsSL "$github_base/landing-page/host-discovery.html" -o /var/www/html/host-discovery.html
    sudo curl -fsSL "$github_base/landing-page/premium-dapp-manager.html" -o /var/www/html/premium-dapp-manager.html
    
    # Download API files
    print_info "🔧 Downloading API files..."
    sudo curl -fsSL "$github_base/landing-page/api/host-info.php" -o /var/www/html/api/host-info.php
    sudo curl -fsSL "$github_base/landing-page/api/instance-count.php" -o /var/www/html/api/instance-count.php
    
    # Download cluster files
    print_info "🚀 Downloading cluster management..."
    sudo curl -fsSL "$github_base/cluster/create.html" -o /var/www/html/cluster/create.html
    sudo curl -fsSL "$github_base/cluster/paywall.html" -o /var/www/html/cluster/paywall.html
    sudo curl -fsSL "$github_base/cluster/roi-calculator.html" -o /var/www/html/cluster/roi-calculator.html 2>/dev/null || true
 
     # Download live pricing widgets
    print_info "💰 Downloading live pricing widgets..."
    sudo mkdir -p /var/www/html/widgets
    sudo curl -fsSL "$github_base/landing-page/widgets/live-pricing.js" -o /var/www/html/widgets/live-pricing.js
    sudo curl -fsSL "$github_base/landing-page/widgets/live-pricing.css" -o /var/www/html/widgets/live-pricing.css
    
    # Download enhanced configuration
    print_info "⚙️ Downloading configuration..."
    sudo curl -fsSL "$github_base/data/enhanced-hosts.json" -o /var/www/html/data/enhanced-hosts.json 2>/dev/null || true
    
    print_status "Enhanced files downloaded"
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
    
    # Deny access to sensitive files
    location ~ /\.(ht|git) {
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
    sudo find /var/www/html -type d -exec chmod 755 {} \;
    
    # Make PHP files executable
    sudo chmod +x /var/www/html/api/*.php
    
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
    
    # Test services
    if systemctl is-active --quiet nginx; then
        print_success "✅ Nginx: Running"
    else
        print_warning "⚠️  Nginx: Not running"
    fi
    
    print_info "🌐 Your enhanced Evernode host is available at:"
    print_success "   http://$domain/"
    print_success "   http://$(curl -s ifconfig.me 2>/dev/null)/ (external IP)"
}

# Generate final report
generate_report() {
    print_info "Generating installation report..."
    
    cat > /tmp/evernode-enhanced-report.txt << EOF
🚀 Enhanced Evernode Installation Complete!
==========================================

Installation Date: $(date)
Domain: $(hostname -f 2>/dev/null || hostname)
Installation User: $(whoami)

✅ What's Installed:
• Enhanced landing page with professional design
• Real-time monitoring dashboard
• Earnings tracking and leaderboard system
• Host discovery network
• Cluster management system (requires NFT license)
• Complete API backend with Xahau integration

📁 File Structure:
• Main site: /var/www/html/
• APIs: /var/www/html/api/
• Cluster system: /var/www/html/cluster/
• Configuration: /var/www/html/data/

🔧 Next Steps:
1. Visit your host URL to see the enhanced interface
2. Update Xahau addresses in API configuration files
3. Configure Dhali Oracle payment claims (if using cluster licenses)
4. Test all functionality and navigation
5. Share your enhanced host with the Evernode community!

🌐 Your Enhanced Host URLs:
• Main: http://$(hostname -f 2>/dev/null || hostname)/
• Monitoring: http://$(hostname -f 2>/dev/null || hostname)/monitoring-dashboard.html
• Earnings: http://$(hostname -f 2>/dev/null || hostname)/my-earnings.html
• Leaderboard: http://$(hostname -f 2>/dev/null || hostname)/leaderboard.html

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
    
    print_info "🌟 Starting Enhanced Evernode installation..."
    echo ""
    
    install_dependencies
    setup_directories
    download_enhanced_files
    configure_webserver
    set_permissions
    test_installation
    generate_report
    
    echo ""
    print_success "🎉 Enhanced Evernode installation completed successfully!"
    echo ""
    print_info "🌟 Your enhanced Evernode host is now ready with:"
    print_info "   • Professional landing page"
    print_info "   • Real-time monitoring and analytics"
    print_info "   • Earnings tracking and leaderboards"
    print_info "   • Host discovery network"
    print_info "   • NFT-based cluster management system"
    echo ""
    print_info "🔧 View installation report:"
    print_info "   http://$(hostname -f 2>/dev/null || hostname)/installation-report.txt"
    echo ""
    print_success "🚀 Your enhanced host is ready to compete!"
    echo ""
}

# Run main installation
main "$@"

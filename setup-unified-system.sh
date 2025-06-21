#!/bin/bash
#
# Enhanced Evernode Unified System Installation
# Complete setup in one command
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
🚀 Enhanced Evernode Unified System Setup
========================================
Professional hosting platform with unified interface
EOF
echo -e "${NC}"

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   print_error "Please run as non-root user with sudo access"
   exit 1
fi

# Set GitHub base URL
github_base="https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main"

print_status "Starting Enhanced Evernode Unified Installation..."

# 1. Run base installation first
print_status "📦 Installing base Enhanced Evernode system..."
if [[ -f "quick-setup.sh" ]]; then
    sudo bash quick-setup.sh
else
    print_warning "quick-setup.sh not found, downloading..."
    curl -fsSL "$github_base/quick-setup.sh" -o quick-setup.sh
    chmod +x quick-setup.sh
    sudo bash quick-setup.sh
fi

# 2. API Consolidation
print_status "🔧 Consolidating API structure..."

# Backup existing APIs
if [[ -d "/var/www/html/api" ]]; then
    sudo cp -r /var/www/html/api "/var/www/html/api-backup-$(date +%Y%m%d)" 2>/dev/null || true
fi

if [[ -d "/var/www/html/landing-page/api" ]]; then
    sudo cp -r /var/www/html/landing-page/api "/var/www/html/landing-page-api-backup-$(date +%Y%m%d)" 2>/dev/null || true
fi

# Move APIs to consolidated directory
sudo mkdir -p /var/www/html/api
if [[ -d "/var/www/html/landing-page/api" ]]; then
    sudo mv /var/www/html/landing-page/api/* /var/www/html/api/ 2>/dev/null || true
    sudo rm -rf /var/www/html/landing-page/api 2>/dev/null || true
fi

# Download unified API router
print_status "📡 Installing unified API router..."
sudo curl -fsSL "$github_base/api/router.php" -o /var/www/html/api/router.php

# 3. Deploy Unified Assets
print_status "🎨 Deploying unified assets..."

# Create asset directories
sudo mkdir -p /var/www/html/assets/{css,js}

# Download unified CSS
sudo curl -fsSL "$github_base/assets/css/unified-navigation.css" -o /var/www/html/assets/css/unified-navigation.css

# Download unified JavaScript
sudo curl -fsSL "$github_base/assets/js/unified-state-manager.js" -o /var/www/html/assets/js/unified-state-manager.js

# 4. Update Pages with Unified Navigation
print_status "📄 Updating pages with unified navigation..."

# Backup existing pages
sudo cp /var/www/html/landing-page/index.html "/var/www/html/landing-page/index-backup-$(date +%Y%m%d).html" 2>/dev/null || true
sudo cp /var/www/html/cluster/dapp-manager.html "/var/www/html/cluster/dapp-manager-backup-$(date +%Y%m%d).html" 2>/dev/null || true

# Download updated pages
sudo curl -fsSL "$github_base/landing-page/index.html" -o /var/www/html/landing-page/index.html
sudo curl -fsSL "$github_base/cluster/dapp-manager.html" -o /var/www/html/cluster/dapp-manager.html

# Download other updated pages
page_files=(
    "monitoring-dashboard.html"
    "my-earnings.html"
    "host-discovery.html"
    "leaderboard.html"
)

for page in "${page_files[@]}"; do
    if curl -fsSL "$github_base/landing-page/$page" -o "/tmp/$page" 2>/dev/null; then
        sudo mv "/tmp/$page" "/var/www/html/landing-page/$page"
        print_success "✅ Updated $page"
    else
        print_warning "⚠️ $page not found (optional)"
    fi
done

# 5. Configuration Setup
print_status "⚙️ Setting up unified configuration..."
sudo mkdir -p /var/www/html/config
sudo curl -fsSL "$github_base/config/unified-config.php" -o /var/www/html/config/unified-config.php 2>/dev/null || true

# 6. Set Proper Permissions
print_status "🔒 Setting proper permissions..."
sudo chown -R www-data:www-data /var/www/html/
sudo find /var/www/html -type f -name "*.php" -exec chmod 644 {} \;
sudo find /var/www/html -type f -name "*.html" -exec chmod 644 {} \;
sudo find /var/www/html -type f -name "*.css" -exec chmod 644 {} \;
sudo find /var/www/html -type f -name "*.js" -exec chmod 644 {} \;
sudo find /var/www/html -type d -exec chmod 755 {} \;

# 7. Restart Services
print_status "🔄 Restarting web services..."
sudo systemctl restart nginx
sudo systemctl restart php*-fpm 2>/dev/null || true

# 8. Test Installation
print_status "🧪 Testing unified system..."

local_domain=$(hostname -f 2>/dev/null || hostname)
external_ip=$(curl -s ifconfig.me 2>/dev/null || echo "unknown")

# Test main page
if curl -f -s http://localhost/ > /dev/null; then
    print_success "✅ Landing page: Accessible"
else
    print_warning "⚠️ Landing page: Check configuration"
fi

# Test API router
if curl -f -s "http://localhost/api/router.php?endpoint=health" > /dev/null; then
    print_success "✅ API router: Working"
else
    print_warning "⚠️ API router: Check installation"
fi

# Test unified assets
if curl -f -s http://localhost/assets/css/unified-navigation.css > /dev/null; then
    print_success "✅ Unified assets: Available"
else
    print_warning "⚠️ Unified assets: Check deployment"
fi

echo ""
print_success "🎉 Enhanced Evernode Unified System Installation Complete!"
echo -e "${GREEN}================================================================${NC}"
echo ""
print_success "🌐 Your Enhanced Evernode Host URLs:"
print_success "   Public Landing: http://$local_domain/"
print_success "   External Access: http://$external_ip/"
print_success "   Admin Access: http://$local_domain/?admin=true"
print_success "   dApp Manager: http://$local_domain/cluster/dapp-manager.html"
print_success "   API Health: http://$local_domain/api/router.php?endpoint=health"
echo ""
print_success "🎯 Unified Features Active:"
print_success "   ✅ Consistent navigation across all pages"
print_success "   ✅ Smart role detection (tenant vs host owner)"
print_success "   ✅ Consolidated API structure with router"
print_success "   ✅ Real-time data synchronization"
print_success "   ✅ Professional admin access controls"
echo ""
print_success "👑 Admin Access Methods:"
print_success "   • URL Parameter: ?admin=true"
print_success "   • Keyboard Shortcut: Ctrl+Shift+A"
print_success "   • Password: enhanced2024 (⚠️ CHANGE THIS!)"
echo ""
print_warning "🔐 IMPORTANT: Change admin password in:"
print_warning "   • /var/www/html/assets/js/unified-state-manager.js"
print_warning "   • /var/www/html/config/unified-config.php"
echo ""
print_status "📋 Next Steps:"
print_status "   1. Test all URLs above"
print_status "   2. Update admin password"
print_status "   3. Configure your EVR addresses"
print_status "   4. Share your enhanced host with the network!"
echo ""

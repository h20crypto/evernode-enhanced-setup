#!/bin/bash

# 🌐 EVERNODE ENHANCED HOST - QUICK SETUP
# Professional one-command setup for Evernode host operators

set -e

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}🌐 Enhanced Evernode Host - Professional Setup${NC}"
echo -e "${BLUE}=============================================${NC}"
echo ""

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}❌ This script must be run as root (use sudo)${NC}"
   exit 1
fi

# Get host information
echo -e "${YELLOW}📋 Gathering host information...${NC}"
HOST_IP=$(curl -s https://ipinfo.io/ip 2>/dev/null || curl -s https://ifconfig.me 2>/dev/null || echo "unknown")
HOSTNAME=$(hostname -f 2>/dev/null || hostname)
OS_VERSION=$(lsb_release -ds 2>/dev/null || cat /etc/os-release | grep PRETTY_NAME | cut -d'"' -f2)

echo -e "${GREEN}🌐 Host IP: $HOST_IP${NC}"
echo -e "${GREEN}🏷️ Hostname: $HOSTNAME${NC}"
echo -e "${GREEN}💻 OS: $OS_VERSION${NC}"
echo ""

# Install prerequisites
echo -e "${YELLOW}📦 Installing prerequisites...${NC}"
apt-get update >/dev/null 2>&1
apt-get install -y curl wget nginx php-fpm php-cli php-json jq certbot python3-certbot-nginx git >/dev/null 2>&1

# Detect PHP version
PHP_VERSION=$(php -v | head -n 1 | cut -d ' ' -f 2 | cut -d '.' -f 1,2)
echo -e "${GREEN}🐘 PHP version detected: $PHP_VERSION${NC}"

# Create web directory structure
echo -e "${YELLOW}📁 Setting up directory structure...${NC}"
mkdir -p /var/www/html/api
mkdir -p /opt/evernode-enhanced/{logs,backups}

# Copy files from current directory or download from GitHub
echo -e "${YELLOW}📄 Deploying landing page and API...${NC}"
if [[ -f "landing-page/index.html" ]]; then
    echo "  Using local files..."
    cp landing-page/index.html /var/www/html/
    cp landing-page/api/instance-count.php /var/www/html/api/
else
    echo "  Downloading from GitHub..."
    curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/landing-page/index.html > /var/www/html/index.html
    curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/landing-page/api/instance-count.php > /var/www/html/api/instance-count.php
fi

# Set proper ownership and permissions
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html
chmod +x /var/www/html/api/instance-count.php

# Configure Nginx with proper PHP-FPM socket detection
echo -e "${YELLOW}⚙️ Configuring web server...${NC}"

# Find the correct PHP-FPM socket
FPM_SOCKET=""
if [[ -S "/var/run/php/php${PHP_VERSION}-fpm.sock" ]]; then
    FPM_SOCKET="/var/run/php/php${PHP_VERSION}-fpm.sock"
elif [[ -S "/var/run/php/php8.3-fpm.sock" ]]; then
    FPM_SOCKET="/var/run/php/php8.3-fpm.sock"
elif [[ -S "/var/run/php/php8.1-fpm.sock" ]]; then
    FPM_SOCKET="/var/run/php/php8.1-fpm.sock"
elif [[ -S "/var/run/php/php8.2-fpm.sock" ]]; then
    FPM_SOCKET="/var/run/php/php8.2-fpm.sock"
else
    echo -e "${RED}❌ Could not find PHP-FPM socket${NC}"
    ls -la /var/run/php/ 2>/dev/null || echo "No PHP sockets found"
    exit 1
fi

echo -e "${GREEN}🔌 Using PHP-FPM socket: $FPM_SOCKET${NC}"

# Create Nginx configuration
cat > /etc/nginx/sites-available/default << NGINXEOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    
    root /var/www/html;
    index index.html index.htm index.php;
    
    server_name _;
    
    # Logging
    access_log /var/log/nginx/evernode-access.log;
    error_log /var/log/nginx/evernode-error.log;
    
    location / {
        try_files \$uri \$uri/ =404;
        add_header Cache-Control "no-cache, must-revalidate";
    }
    
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${FPM_SOCKET};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        
        # Timeout settings
        fastcgi_connect_timeout 60;
        fastcgi_send_timeout 60;
        fastcgi_read_timeout 60;
    }
    
    location /api/ {
        add_header Access-Control-Allow-Origin *;
        add_header Access-Control-Allow-Methods "GET, POST, OPTIONS";
        add_header Access-Control-Allow-Headers "Content-Type";
        
        location ~ \.php\$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:${FPM_SOCKET};
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            include fastcgi_params;
        }
    }
    
    # Security headers
    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";
    
    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }
}
NGINXEOF

# Start and enable services
echo -e "${YELLOW}🚀 Starting services...${NC}"
systemctl enable nginx php${PHP_VERSION}-fpm >/dev/null 2>&1
systemctl restart php${PHP_VERSION}-fpm
systemctl restart nginx

# Install debug tools globally
echo -e "${YELLOW}🔧 Installing debug tools...${NC}"
if [[ -f "evernode-debug-api" ]]; then
    cp evernode-debug-api /usr/local/bin/evernode-debug-api
else
    curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/evernode-debug-api > /usr/local/bin/evernode-debug-api
fi

if [[ -f "fix instance count API" ]]; then
    cp "fix instance count API" /usr/local/bin/fix-instance-count
else
    curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/fix%20instance%20count%20API > /usr/local/bin/fix-instance-count
fi

chmod +x /usr/local/bin/evernode-debug-api
chmod +x /usr/local/bin/fix-instance-count

# Wait for services to fully start
echo -e "${YELLOW}⏳ Waiting for services to start...${NC}"
sleep 5

# Test installation
echo -e "${YELLOW}🧪 Testing installation...${NC}"

# Test PHP
echo "<?php echo 'PHP OK'; ?>" > /tmp/test.php
PHP_TEST=$(php /tmp/test.php 2>/dev/null)
rm /tmp/test.php

if [[ "$PHP_TEST" == "PHP OK" ]]; then
    echo -e "${GREEN}✅ PHP is working${NC}"
else
    echo -e "${RED}❌ PHP test failed${NC}"
fi

# Test API
API_RESPONSE=$(curl -s -w "%{http_code}" http://localhost/api/instance-count.php 2>/dev/null)
HTTP_CODE="${API_RESPONSE: -3}"
HTTP_BODY="${API_RESPONSE%???}"

if [[ "$HTTP_CODE" == "200" ]]; then
    echo -e "${GREEN}✅ API is working${NC}"
    
    # Parse API response for summary
    TOTAL=$(echo "$HTTP_BODY" | jq -r '.total' 2>/dev/null)
    USED=$(echo "$HTTP_BODY" | jq -r '.used' 2>/dev/null)
    AVAILABLE=$(echo "$HTTP_BODY" | jq -r '.available' 2>/dev/null)
    
    if [[ "$TOTAL" != "null" ]] && [[ -n "$TOTAL" ]]; then
        echo -e "${BLUE}📊 Current instance status: $USED/$TOTAL used, $AVAILABLE available${NC}"
    fi
else
    echo -e "${RED}❌ API test failed (HTTP $HTTP_CODE)${NC}"
    echo "Response: $HTTP_BODY"
fi

# Test landing page
LANDING_TEST=$(curl -s http://localhost/ | grep -c "Enhanced Evernode Host" || echo "0")
if [[ "$LANDING_TEST" -gt 0 ]]; then
    echo -e "${GREEN}✅ Landing page is working${NC}"
else
    echo -e "${RED}❌ Landing page test failed${NC}"
fi

echo ""
echo -e "${GREEN}🎉 ENHANCED SETUP COMPLETE!${NC}"
echo ""
echo -e "${BLUE}📋 Your Enhanced Evernode Host:${NC}"
echo -e "${GREEN}   🌐 Landing Page: http://$HOST_IP${NC}"
echo -e "${GREEN}   📊 API Endpoint: http://$HOST_IP/api/instance-count.php${NC}"
echo -e "${GREEN}   🏷️ Hostname: $HOSTNAME${NC}"
echo -e "${GREEN}   💻 OS: $OS_VERSION${NC}"
echo -e "${GREEN}   🐘 PHP: $PHP_VERSION${NC}"
echo ""
echo -e "${YELLOW}📖 Available Commands:${NC}"
echo -e "${GREEN}   • evernode-debug-api         - Debug API and test all data sources${NC}"
echo -e "${GREEN}   • fix-instance-count        - Fix container counting logic${NC}"
echo ""
echo -e "${BLUE}🎯 Test your installation:${NC}"
echo -e "${GREEN}   curl http://localhost/api/instance-count.php${NC}"
echo -e "${GREEN}   evernode-debug-api${NC}"
echo ""
echo -e "${BLUE}🚀 Your professional Evernode host is ready!${NC}"
echo -e "${BLUE}📚 Documentation: https://github.com/h20crypto/evernode-enhanced-setup${NC}"

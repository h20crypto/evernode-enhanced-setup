#!/bin/bash

# 🌟 ENHANCED EVERNODE LANDING PAGE SETUP v2.0
# Professional setup with real-time monitoring and modern UI

set -e

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
PURPLE='\033[0;35m'
NC='\033[0m'

echo -e "${PURPLE}🌟 Enhanced Evernode Host Setup v2.0${NC}"
echo "========================================"
echo ""

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}❌ This script must be run as root (use sudo)${NC}"
   exit 1
fi

# Install required packages
echo -e "${YELLOW}📦 Installing components...${NC}"
apt-get update >/dev/null 2>&1
apt-get install -y nginx php-fpm php-cli php-json jq curl >/dev/null 2>&1

# Detect PHP-FPM version
echo -e "${YELLOW}🔍 Detecting PHP-FPM version...${NC}"
PHP_VERSION=$(php -v | head -n 1 | cut -d ' ' -f 2 | cut -d '.' -f 1,2)
echo "Detected PHP version: $PHP_VERSION"

# Find correct PHP-FPM socket
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
    exit 1
fi

echo -e "${GREEN}✅ Using PHP-FPM socket: $FPM_SOCKET${NC}"

# Create directories
echo -e "${YELLOW}📁 Setting up directories...${NC}"
mkdir -p /var/www/html/api
mkdir -p /opt/evernode-enhanced/{scripts,logs}

# Download enhanced landing page from GitHub
echo -e "${YELLOW}📥 Downloading enhanced landing page...${NC}"
curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/landing-page/index.html -o /var/www/html/index.html

# Download enhanced API
echo -e "${YELLOW}📥 Downloading enhanced API...${NC}"
curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/landing-page/api/instance-count.php -o /var/www/html/api/instance-count.php

# Create enhanced debug script
echo -e "${YELLOW}🔍 Creating enhanced debug tools...${NC}"
curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/evernode-debug-api -o /usr/local/bin/evernode-debug-api
chmod +x /usr/local/bin/evernode-debug-api

# Get server IP addresses
echo -e "${YELLOW}🔍 Detecting server IP addresses...${NC}"
IPV4=$(curl -s -4 ifconfig.me 2>/dev/null)
IPV6=$(ip -6 addr show | grep -oP '(?<=inet6\s)2a0a[^/]+' | head -1)

echo "IPv4: ${IPV4:-Not available}"
echo "IPv6: ${IPV6:-Not available}"

# Configure Nginx with enhanced settings
echo -e "${YELLOW}🔧 Configuring enhanced Nginx...${NC}"
cat > /etc/nginx/sites-available/evernode-host << NGINXEOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;

    # Support domain and IP access
    server_name h20cryptonode3.dev ${IPV4} ${IPV6} localhost _;

    root /var/www/html;
    index index.html index.htm index.php;

    # Enhanced logging
    access_log /var/log/nginx/evernode-access.log;
    error_log /var/log/nginx/evernode-error.log;

    # Main location with caching headers
    location / {
        try_files \$uri \$uri/ =404;
        add_header Cache-Control "no-cache, must-revalidate";
    }

    # PHP handling with correct socket
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${FPM_SOCKET};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        
        # Enhanced timeout settings
        fastcgi_connect_timeout 60;
        fastcgi_send_timeout 60;
        fastcgi_read_timeout 60;
    }

    # API specific settings with CORS
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

# Remove default site and enable enhanced configuration
echo -e "${YELLOW}🔗 Configuring site...${NC}"
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/evernode-host /etc/nginx/sites-enabled/

# Test Nginx configuration
echo -e "${YELLOW}🧪 Testing Nginx configuration...${NC}"
if nginx -t; then
    echo -e "${GREEN}✅ Nginx configuration is valid${NC}"
else
    echo -e "${RED}❌ Nginx configuration error${NC}"
    nginx -t
    exit 1
fi

# Set enhanced permissions
echo -e "${YELLOW}🔐 Setting enhanced permissions...${NC}"
chown -R www-data:www-data /var/www/html
chmod -R 644 /var/www/html
chmod 755 /var/www/html /var/www/html/api
chmod +x /var/www/html/api/instance-count.php

# Start services with enhanced configuration
echo -e "${YELLOW}🚀 Starting enhanced services...${NC}"
systemctl enable nginx >/dev/null 2>&1
systemctl enable php${PHP_VERSION}-fpm >/dev/null 2>&1
systemctl restart php${PHP_VERSION}-fpm
systemctl restart nginx

# Wait for services to start
sleep 3

# Test PHP processing
echo -e "${YELLOW}🧪 Testing PHP processing...${NC}"
echo "<?php phpinfo(); ?>" > /var/www/html/test.php
chown www-data:www-data /var/www/html/test.php

if curl -s http://localhost/test.php | grep -q "PHP Version"; then
    echo -e "${GREEN}✅ PHP is working correctly${NC}"
    rm /var/www/html/test.php
else
    echo -e "${RED}❌ PHP is not working${NC}"
fi

# Test enhanced API
echo -e "${YELLOW}🧪 Testing enhanced API...${NC}"
API_RESPONSE=$(curl -s http://localhost/api/instance-count.php)
if echo "$API_RESPONSE" | grep -q '"total"'; then
    echo -e "${GREEN}✅ Enhanced API is working${NC}"
else
    echo -e "${RED}❌ API not working${NC}"
    echo "Response: $API_RESPONSE"
fi

# Test external access
echo -e "${YELLOW}🌐 Testing external access...${NC}"
if [[ -n "$IPV4" ]]; then
    if curl -s -m 10 http://$IPV4/ | grep -q "Enhanced Evernode Host"; then
        echo -e "${GREEN}✅ IPv4 access working: http://$IPV4/${NC}"
    else
        echo -e "${YELLOW}⚠️ IPv4 access may have issues${NC}"
    fi
fi

echo ""
echo -e "${GREEN}✅ ENHANCED EVERNODE HOST SETUP COMPLETE!${NC}"
echo ""
echo -e "${PURPLE}🌟 Enhanced Features Installed:${NC}"
echo -e "${GREEN}   ✅ Modern glassmorphism UI with animations${NC}"
echo -e "${GREEN}   ✅ Real-time container monitoring (30s updates)${NC}"
echo -e "${GREEN}   ✅ Accurate container counting${NC}"
echo -e "${GREEN}   ✅ One-click deployment commands${NC}"
echo -e "${GREEN}   ✅ Professional debug tools${NC}"
echo -e "${GREEN}   ✅ Mobile responsive design${NC}"
echo -e "${GREEN}   ✅ Hidden debug mode (click availability card 5x)${NC}"
echo ""
echo -e "${BLUE}🌐 Access your enhanced landing page:${NC}"
echo -e "${GREEN}   • http://localhost/${NC}"
if [[ -n "$IPV4" ]]; then
    echo -e "${GREEN}   • http://$IPV4/${NC}"
fi
if [[ -n "$IPV6" ]]; then
    echo -e "${GREEN}   • http://[$IPV6]/${NC}"
fi
echo ""
echo -e "${BLUE}📊 Enhanced API endpoints:${NC}"
echo -e "${GREEN}   • http://localhost/api/instance-count.php${NC}"
if [[ -n "$IPV4" ]]; then
    echo -e "${GREEN}   • http://$IPV4/api/instance-count.php${NC}"
fi
echo ""
echo -e "${BLUE}🔧 Debug and maintenance tools:${NC}"
echo -e "${GREEN}   • evernode-debug-api     - Comprehensive diagnostics${NC}"
echo ""

# Test API with real data and show current status
echo -e "${BLUE}📈 Current Enhanced Host Status:${NC}"
API_RESPONSE=$(curl -s http://localhost/api/instance-count.php 2>/dev/null)
if [[ $? -eq 0 ]] && [[ -n "$API_RESPONSE" ]]; then
    echo -e "${GREEN}✅ Real-time API is working${NC}"
    
    # Parse and show key data
    TOTAL=$(echo "$API_RESPONSE" | jq -r '.total' 2>/dev/null)
    USED=$(echo "$API_RESPONSE" | jq -r '.used' 2>/dev/null)
    AVAILABLE=$(echo "$API_RESPONSE" | jq -r '.available' 2>/dev/null)
    SOURCE=$(echo "$API_RESPONSE" | jq -r '.data_source' 2>/dev/null)
    STATUS_MSG=$(echo "$API_RESPONSE" | jq -r '.status_message' 2>/dev/null)
    
    if [[ "$TOTAL" != "null" ]] && [[ "$TOTAL" != "" ]]; then
        echo -e "${BLUE}Real-time Instance Data:${NC}"
        echo -e "${GREEN}   📊 Total Slots: $TOTAL${NC}"
        echo -e "${GREEN}   🔄 Used Slots: $USED${NC}"
        echo -e "${GREEN}   ✅ Available: $AVAILABLE${NC}"
        echo -e "${GREEN}   📡 Data Source: $SOURCE${NC}"
        echo -e "${GREEN}   💬 Status: $STATUS_MSG${NC}"
    fi
else
    echo -e "${YELLOW}⚠️ API will use fallback data - this is normal${NC}"
fi

echo ""
echo -e "${PURPLE}🎯 Your Enhanced Evernode Host is ready!${NC}"
echo -e "${BLUE}Features:${NC}"
echo -e "${GREEN}• Live monitoring updates every 30 seconds${NC}"
echo -e "${GREEN}• Copy deployment commands with one click${NC}"
echo -e "${GREEN}• Professional UI with smooth animations${NC}"
echo -e "${GREEN}• Mobile-friendly responsive design${NC}"
echo -e "${GREEN}• Hidden debug mode for troubleshooting${NC}"
echo ""
echo -e "${BLUE}Need help? Run: ${GREEN}evernode-debug-api${NC}"

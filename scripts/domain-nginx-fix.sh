#!/bin/bash
# Enhanced fix-domain-nginx script with proper socket detection v2.1

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}🌐 Fixing Domain and Nginx Configuration v2.1${NC}"
echo "=================================================="

# Enhanced PHP-FPM socket detection
echo -e "${YELLOW}🔍 Detecting PHP-FPM version and socket...${NC}"

# Method 1: Check running PHP-FPM processes
PHP_VERSION=$(ps aux | grep php-fpm | grep -v grep | head -1 | grep -oP 'php\K[0-9.]+' | head -1)

# Method 2: Check installed packages if method 1 fails
if [[ -z "$PHP_VERSION" ]]; then
    PHP_VERSION=$(dpkg -l | grep php-fpm | head -1 | awk '{print $2}' | grep -oP '\d+\.\d+')
fi

# Method 3: Default to available version
if [[ -z "$PHP_VERSION" ]]; then
    PHP_VERSION=$(ls /etc/php/ | grep -E '^[0-9]+\.[0-9]+$' | sort -V | tail -1)
fi

echo "Detected PHP version: ${PHP_VERSION:-unknown}"

# Enhanced socket detection with multiple fallbacks
FPM_SOCKET=""

# Check for direct socket files
for sock in "/var/run/php/php${PHP_VERSION}-fpm.sock" "/var/run/php/php-fpm.sock" "/run/php/php${PHP_VERSION}-fpm.sock" "/run/php/php-fpm.sock"; do
    if [[ -S "$sock" ]]; then
        FPM_SOCKET="$sock"
        echo -e "${GREEN}✅ Found socket: $FPM_SOCKET${NC}"
        break
    fi
done

# Check for symlinks
if [[ -z "$FPM_SOCKET" ]]; then
    for sock in "/var/run/php/php-fpm.sock" "/run/php/php-fpm.sock"; do
        if [[ -L "$sock" ]]; then
            REAL_SOCKET=$(readlink -f "$sock")
            if [[ -S "$REAL_SOCKET" ]]; then
                FPM_SOCKET="$sock"  # Use the symlink path
                echo -e "${GREEN}✅ Found symlink socket: $FPM_SOCKET -> $REAL_SOCKET${NC}"
                break
            fi
        fi
    done
fi

# If still no socket found, try to start PHP-FPM
if [[ -z "$FPM_SOCKET" ]]; then
    echo -e "${YELLOW}⚠️ No socket found, trying to start PHP-FPM...${NC}"
    
    # Try to start PHP-FPM service
    if [[ -n "$PHP_VERSION" ]]; then
        systemctl start php${PHP_VERSION}-fpm
        systemctl enable php${PHP_VERSION}-fpm
        sleep 2
        
        # Check again for socket
        for sock in "/var/run/php/php${PHP_VERSION}-fpm.sock" "/var/run/php/php-fpm.sock"; do
            if [[ -S "$sock" ]] || [[ -L "$sock" ]]; then
                FPM_SOCKET="$sock"
                echo -e "${GREEN}✅ Started PHP-FPM and found socket: $FPM_SOCKET${NC}"
                break
            fi
        done
    fi
fi

# Final check
if [[ -z "$FPM_SOCKET" ]]; then
    echo -e "${RED}❌ Could not find or create PHP-FPM socket${NC}"
    echo "Available sockets in /var/run/php/:"
    ls -la /var/run/php/ 2>/dev/null || echo "Directory not found"
    echo "Available sockets in /run/php/:"
    ls -la /run/php/ 2>/dev/null || echo "Directory not found"
    
    echo -e "${YELLOW}💡 Trying manual PHP-FPM installation/restart...${NC}"
    apt-get update -qq
    apt-get install -y php-fpm
    systemctl restart php*-fpm
    systemctl enable php*-fpm
    
    echo "Checking again after restart..."
    ls -la /var/run/php/ /run/php/ 2>/dev/null
    
    exit 1
fi

# Get server IP addresses
echo -e "${YELLOW}🔍 Detecting server IP addresses...${NC}"
IPV4=$(curl -s -4 ifconfig.me 2>/dev/null)
IPV6=$(ip -6 addr show | grep -oP '(?<=inet6\s)2a0a[^/]+' | head -1)

echo "IPv4: ${IPV4:-Not available}"
echo "IPv6: ${IPV6:-Not available}"

# Create enhanced Nginx configuration
echo -e "${YELLOW}⚙️ Creating enhanced Nginx configuration...${NC}"
cat > /etc/nginx/sites-available/evernode-host << NGINXEOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;

    # Support domain and IP access
    server_name h20cryptoxah.click h20cryptonode3.dev ${IPV4} ${IPV6} localhost _;

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

    # PHP handling with detected socket
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

# Enable site and test configuration
echo -e "${YELLOW}🔗 Enabling site and testing configuration...${NC}"
ln -sf /etc/nginx/sites-available/evernode-host /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Test nginx configuration
if nginx -t; then
    echo -e "${GREEN}✅ Nginx configuration is valid${NC}"
    systemctl reload nginx
    echo -e "${GREEN}✅ Nginx reloaded successfully${NC}"
else
    echo -e "${RED}❌ Nginx configuration has errors${NC}"
    nginx -t
    exit 1
fi

# Ensure PHP-FPM is running
echo -e "${YELLOW}🔄 Ensuring PHP-FPM is running...${NC}"
if [[ -n "$PHP_VERSION" ]]; then
    systemctl restart php${PHP_VERSION}-fpm
    systemctl enable php${PHP_VERSION}-fpm
    
    if systemctl is-active php${PHP_VERSION}-fpm >/dev/null; then
        echo -e "${GREEN}✅ PHP-FPM is running${NC}"
    else
        echo -e "${RED}❌ PHP-FPM failed to start${NC}"
        systemctl status php${PHP_VERSION}-fpm
    fi
fi

# Test API functionality
echo -e "${YELLOW}🧪 Testing API functionality...${NC}"
sleep 2  # Give services time to start

API_RESPONSE=$(curl -s http://localhost/api/instance-count.php 2>/dev/null)
if [[ $? -eq 0 ]] && echo "$API_RESPONSE" | jq . >/dev/null 2>&1; then
    echo -e "${GREEN}✅ API is working correctly${NC}"
    echo "$API_RESPONSE" | jq -r '.status_message // "API functional"'
else
    echo -e "${RED}❌ API not working${NC}"
    echo "Response: $API_RESPONSE"
    
    # Check PHP error logs
    echo "PHP-FPM error log (last 5 lines):"
    tail -5 /var/log/php${PHP_VERSION}-fpm.log 2>/dev/null || echo "No PHP-FPM logs found"
    
    echo "Nginx error log (last 5 lines):"
    tail -5 /var/log/nginx/evernode-error.log 2>/dev/null || echo "No Nginx error logs found"
fi

# Test external access
echo -e "${YELLOW}🌐 Testing external access...${NC}"
if [[ -n "$IPV4" ]]; then
    echo "Testing IPv4 access..."
    if curl -s -m 10 http://$IPV4/ | grep -q "Enhanced Evernode Host\|Evernode"; then
        echo -e "${GREEN}✅ IPv4 access working: http://$IPV4/${NC}"
    else
        echo -e "${YELLOW}⚠️ IPv4 access may have issues${NC}"
    fi
fi

echo ""
echo -e "${GREEN}✅ ENHANCED DOMAIN AND NGINX FIX COMPLETE!${NC}"
echo ""
echo -e "${BLUE}🌐 Access your enhanced landing page:${NC}"
echo -e "${GREEN}   • http://localhost/${NC}"
if [[ -n "$IPV4" ]]; then
    echo -e "${GREEN}   • http://$IPV4/${NC}"
fi
echo -e "${GREEN}   • http://h20cryptoxah.click/ (if domain DNS is configured)${NC}"
echo ""
echo -e "${BLUE}📊 Enhanced API endpoints:${NC}"
echo -e "${GREEN}   • http://localhost/api/instance-count.php${NC}"
if [[ -n "$IPV4" ]]; then
    echo -e "${GREEN}   • http://$IPV4/api/instance-count.php${NC}"
fi
echo ""

# Show current instance data
echo -e "${BLUE}📈 Current Instance Data:${NC}"
curl -s http://localhost/api/instance-count.php | jq . 2>/dev/null || curl -s http://localhost/api/instance-count.php

echo ""
echo -e "${BLUE}🎯 Configuration Summary:${NC}"
echo -e "${GREEN}   • PHP Version: ${PHP_VERSION}${NC}"
echo -e "${GREEN}   • Socket Path: ${FPM_SOCKET}${NC}"
echo -e "${GREEN}   • Domain: h20cryptoxah.click${NC}"
echo -e "${GREEN}   • IPv4: ${IPV4:-Not available}${NC}"
echo ""
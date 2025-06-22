#!/bin/bash

# 🌐 DOMAIN AND NGINX FIX FOR EVERNODE HOST
# Fixes 502 Bad Gateway and sets up domain properly

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}🌐 Fixing Domain and Nginx Configuration${NC}"
echo "=========================================="
echo ""

# Add to your existing nginx configuration
location /api/payment/ {
    proxy_pass http://localhost:3000/api/;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
}

# Keep all your existing PHP API routes
location /api/ {
    try_files $uri $uri/ /api/index.php?$query_string;
}

# Check current PHP-FPM version
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
    echo "Available sockets:"
    ls -la /var/run/php/ 2>/dev/null || echo "No PHP sockets found"
    exit 1
fi

echo -e "${GREEN}✅ Using PHP-FPM socket: $FPM_SOCKET${NC}"

# Get server IP addresses
echo -e "${YELLOW}🔍 Detecting server IP addresses...${NC}"
IPV4=$(curl -s -4 ifconfig.me 2>/dev/null)
IPV6=$(ip -6 addr show | grep -oP '(?<=inet6\s)2a0a[^/]+' | head -1)

echo "IPv4: ${IPV4:-Not available}"
echo "IPv6: ${IPV6:-Not available}"

# Create proper Nginx configuration
echo -e "${YELLOW}⚙️ Creating proper Nginx configuration...${NC}"
cat > /etc/nginx/sites-available/evernode-host << NGINXEOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;

    # Support both domain and IP access
    server_name h20cryptonode3.dev ${IPV4} ${IPV6} localhost _;

    root /var/www/html;
    index index.html index.htm index.php;

    # Logging for debugging
    access_log /var/log/nginx/evernode-access.log;
    error_log /var/log/nginx/evernode-error.log;

    # Main location
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
        
        # Timeout settings
        fastcgi_connect_timeout 60;
        fastcgi_send_timeout 60;
        fastcgi_read_timeout 60;
    }

    # API specific settings
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

# Remove default site and enable our configuration
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

# Ensure PHP-FPM is running
echo -e "${YELLOW}🚀 Starting PHP-FPM...${NC}"
systemctl enable php${PHP_VERSION}-fpm
systemctl restart php${PHP_VERSION}-fpm
systemctl status php${PHP_VERSION}-fpm --no-pager -l

# Restart Nginx
echo -e "${YELLOW}🔄 Restarting Nginx...${NC}"
systemctl restart nginx
systemctl status nginx --no-pager -l

# Wait for services
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
    echo "Checking PHP-FPM status:"
    systemctl status php${PHP_VERSION}-fpm --no-pager -l
fi

# Test API
echo -e "${YELLOW}🧪 Testing API...${NC}"
API_RESPONSE=$(curl -s http://localhost/api/instance-count.php)
if echo "$API_RESPONSE" | grep -q '"total"'; then
    echo -e "${GREEN}✅ API is working${NC}"
    echo "API Response:"
    echo "$API_RESPONSE" | jq . 2>/dev/null || echo "$API_RESPONSE"
else
    echo -e "${RED}❌ API not working${NC}"
    echo "Response: $API_RESPONSE"
    
    # Check PHP error logs
    echo "PHP-FPM error log:"
    tail -5 /var/log/php${PHP_VERSION}-fpm.log 2>/dev/null || echo "No PHP-FPM logs found"
    
    echo "Nginx error log:"
    tail -5 /var/log/nginx/evernode-error.log 2>/dev/null || echo "No Nginx error logs found"
fi

# Test external access
echo -e "${YELLOW}🌐 Testing external access...${NC}"
if [[ -n "$IPV4" ]]; then
    echo "Testing IPv4 access..."
    if curl -s -m 10 http://$IPV4/ | grep -q "Enhanced Evernode Host"; then
        echo -e "${GREEN}✅ IPv4 access working: http://$IPV4/${NC}"
    else
        echo -e "${YELLOW}⚠️ IPv4 access may have issues${NC}"
    fi
fi

# Domain DNS check
echo -e "${YELLOW}🔍 Checking domain DNS...${NC}"
DOMAIN_IP=$(dig +short h20cryptonode3.dev A 2>/dev/null)
DOMAIN_IPV6=$(dig +short h20cryptonode3.dev AAAA 2>/dev/null)

echo "Domain DNS results:"
echo "  A record: ${DOMAIN_IP:-Not found}"
echo "  AAAA record: ${DOMAIN_IPV6:-Not found}"
echo "  Server IPv4: ${IPV4:-Not available}"
echo "  Server IPv6: ${IPV6:-Not available}"

if [[ "$DOMAIN_IP" == "$IPV4" ]] || [[ "$DOMAIN_IPV6" == "$IPV6" ]]; then
    echo -e "${GREEN}✅ Domain DNS is correctly configured${NC}"
else
    echo -e "${YELLOW}⚠️ Domain DNS may need updating${NC}"
    echo "To fix domain access, update DNS records:"
    echo "  A record: h20cryptonode3.dev → $IPV4"
    echo "  AAAA record: h20cryptonode3.dev → $IPV6"
fi

echo ""
echo -e "${GREEN}✅ DOMAIN AND NGINX FIX COMPLETE!${NC}"
echo ""
echo -e "${BLUE}🌐 Access your landing page:${NC}"
echo -e "${GREEN}   • http://localhost/${NC}"
if [[ -n "$IPV4" ]]; then
    echo -e "${GREEN}   • http://$IPV4/${NC}"
fi
if [[ -n "$IPV6" ]]; then
    echo -e "${GREEN}   • http://[$IPV6]/${NC}"
fi
echo -e "${GREEN}   • http://h20cryptonode3.dev/ (if DNS is configured)${NC}"
echo ""
echo -e "${BLUE}📊 API endpoints:${NC}"
echo -e "${GREEN}   • http://localhost/api/instance-count.php${NC}"
if [[ -n "$IPV4" ]]; then
    echo -e "${GREEN}   • http://$IPV4/api/instance-count.php${NC}"
fi
echo ""

# Show current instance data
echo -e "${BLUE}📈 Current Instance Data:${NC}"
curl -s http://localhost/api/instance-count.php | jq . 2>/dev/null || curl -s http://localhost/api/instance-count.php

echo ""
echo -e "${BLUE}🎯 Next Steps:${NC}"
echo -e "${GREEN}1. Test landing page access${NC}"
echo -e "${GREEN}2. Update DNS if domain doesn't work${NC}"
echo -e "${GREEN}3. Consider adding SSL certificate${NC}"

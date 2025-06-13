#!/bin/bash

# 🌟 EVERNODE ENHANCED HOST - PROFESSIONAL QUICK SETUP v2.0
# One-command setup for professional Evernode host operators with modern UI

set -e

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

echo -e "${PURPLE}🌟 Enhanced Evernode Host - Professional Setup v2.0${NC}"
echo -e "${PURPLE}====================================================${NC}"
echo ""

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}❌ This script must be run as root (use sudo)${NC}"
   echo -e "${YELLOW}💡 Run: sudo $0${NC}"
   exit 1
fi

# Enhanced host information gathering
echo -e "${YELLOW}📋 Gathering enhanced host information...${NC}"
HOST_IP=$(curl -s -4 ifconfig.me 2>/dev/null || curl -s https://ipinfo.io/ip 2>/dev/null || echo "unknown")
HOST_IPV6=$(ip -6 addr show | grep -oP '(?<=inet6\s)2a0a[^/]+' | head -1)
HOSTNAME=$(hostname -f 2>/dev/null || hostname)
OS_VERSION=$(lsb_release -ds 2>/dev/null || cat /etc/os-release | grep PRETTY_NAME | cut -d'"' -f2)
MEMORY_GB=$(free -h | awk '/^Mem:/ {print $2}')
DISK_SPACE=$(df -h / | awk 'NR==2 {print $4}')
CPU_CORES=$(nproc)

echo -e "${CYAN}🌐 IPv4: ${HOST_IP}${NC}"
echo -e "${CYAN}🌐 IPv6: ${HOST_IPV6:-Not configured}${NC}"
echo -e "${CYAN}🏷️ Hostname: ${HOSTNAME}${NC}"
echo -e "${CYAN}💻 OS: ${OS_VERSION}${NC}"
echo -e "${CYAN}🧠 Memory: ${MEMORY_GB}${NC}"
echo -e "${CYAN}💾 Disk Available: ${DISK_SPACE}${NC}"
echo -e "${CYAN}⚡ CPU Cores: ${CPU_CORES}${NC}"
echo ""

# Install enhanced prerequisites
echo -e "${YELLOW}📦 Installing enhanced components...${NC}"
apt-get update >/dev/null 2>&1
apt-get install -y curl wget nginx php-fpm php-cli php-json jq certbot python3-certbot-nginx git htop unzip >/dev/null 2>&1

# Enhanced PHP version detection
PHP_VERSION=$(php -v | head -n 1 | cut -d ' ' -f 2 | cut -d '.' -f 1,2)
echo -e "${GREEN}🐘 PHP version detected: ${PHP_VERSION}${NC}"

# Create enhanced directory structure
echo -e "${YELLOW}📁 Setting up enhanced directory structure...${NC}"
mkdir -p /var/www/html/api
mkdir -p /opt/evernode-enhanced/{logs,backups,scripts,configs}
mkdir -p /var/log/evernode-enhanced

# Deploy enhanced files with fallback logic
echo -e "${YELLOW}📄 Deploying enhanced landing page and API...${NC}"
if [[ -f "landing-page/index.html" ]]; then
    echo "  ✅ Using local enhanced files..."
    cp landing-page/index.html /var/www/html/
    cp landing-page/api/instance-count.php /var/www/html/api/
else
    echo "  📥 Downloading enhanced files from GitHub..."
    # Download the enhanced landing page
    curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/landing-page/index.html > /var/www/html/index.html || {
        echo -e "${RED}❌ Failed to download landing page${NC}"
        exit 1
    }
    
    # Download the enhanced API
    curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/landing-page/api/instance-count.php > /var/www/html/api/instance-count.php || {
        echo -e "${RED}❌ Failed to download API${NC}"
        exit 1
    }
fi

# Update host address in landing page (if specific address needed)
if [[ "$HOST_IP" != "unknown" ]] && [[ -n "$HOST_IP" ]]; then
    # Replace example host address with actual host IP for demonstration
    sed -i "s/rExampleHostAddress[a-zA-Z0-9]*/r${HOST_IP//./}Host/g" /var/www/html/index.html 2>/dev/null || true
fi

# Set enhanced ownership and permissions
chown -R www-data:www-data /var/www/html
chmod -R 644 /var/www/html
chmod 755 /var/www/html /var/www/html/api
chmod +x /var/www/html/api/instance-count.php

# Enhanced PHP-FPM socket detection
echo -e "${YELLOW}⚙️ Configuring enhanced web server...${NC}"

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

echo -e "${GREEN}🔌 Using PHP-FPM socket: ${FPM_SOCKET}${NC}"

# Create enhanced Nginx configuration
cat > /etc/nginx/sites-available/evernode-enhanced << NGINXEOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    
    # Support domain and IP access
    server_name ${HOSTNAME} ${HOST_IP} ${HOST_IPV6} localhost _;
    
    root /var/www/html;
    index index.html index.htm index.php;
    
    # Enhanced logging
    access_log /var/log/nginx/evernode-access.log;
    error_log /var/log/nginx/evernode-error.log;
    
    # Main location with enhanced caching
    location / {
        try_files \$uri \$uri/ =404;
        add_header Cache-Control "no-cache, must-revalidate";
        
        # Enhanced security headers
        add_header X-Frame-Options DENY;
        add_header X-Content-Type-Options nosniff;
        add_header X-XSS-Protection "1; mode=block";
        add_header Referrer-Policy "strict-origin-when-cross-origin";
    }
    
    # Enhanced PHP handling
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${FPM_SOCKET};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        
        # Enhanced timeout settings
        fastcgi_connect_timeout 60;
        fastcgi_send_timeout 60;
        fastcgi_read_timeout 60;
        fastcgi_buffering on;
        fastcgi_buffer_size 16k;
        fastcgi_buffers 16 16k;
    }
    
    # Enhanced API configuration with CORS
    location /api/ {
        add_header Access-Control-Allow-Origin *;
        add_header Access-Control-Allow-Methods "GET, POST, OPTIONS";
        add_header Access-Control-Allow-Headers "Content-Type, Authorization";
        add_header Access-Control-Max-Age 86400;
        
        # Handle preflight requests
        if (\$request_method = 'OPTIONS') {
            return 204;
        }
        
        location ~ \.php\$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:${FPM_SOCKET};
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            include fastcgi_params;
        }
    }
    
    # Enhanced static file handling
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
    
    # Deny access to hidden files and sensitive directories
    location ~ /\. {
        deny all;
    }
    
    location ~ /(config|logs|backups)/ {
        deny all;
    }
}
NGINXEOF

# Remove default site and enable enhanced configuration
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/evernode-enhanced /etc/nginx/sites-enabled/

# Test Nginx configuration
echo -e "${YELLOW}🧪 Testing enhanced Nginx configuration...${NC}"
if nginx -t; then
    echo -e "${GREEN}✅ Enhanced Nginx configuration is valid${NC}"
else
    echo -e "${RED}❌ Nginx configuration error${NC}"
    nginx -t
    exit 1
fi

# Start and enable enhanced services
echo -e "${YELLOW}🚀 Starting enhanced services...${NC}"
systemctl enable nginx php${PHP_VERSION}-fpm >/dev/null 2>&1
systemctl restart php${PHP_VERSION}-fpm
systemctl restart nginx

# Install enhanced debug and management tools
echo -e "${YELLOW}🔧 Installing enhanced debug tools...${NC}"

# Enhanced debug tool
if [[ -f "evernode-debug-api" ]]; then
    cp evernode-debug-api /usr/local/bin/evernode-debug-api
else
    curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/evernode-debug-api > /usr/local/bin/evernode-debug-api
fi

# Enhanced domain fix tool
if [[ -f "Domain and Nginx Fix" ]]; then
    cp "Domain and Nginx Fix" /usr/local/bin/fix-domain-nginx
else
    curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/Domain%20and%20Nginx%20Fix > /usr/local/bin/fix-domain-nginx
fi

# Instance count fix tool
if [[ -f "fix instance count API" ]]; then
    cp "fix instance count API" /usr/local/bin/fix-instance-count
else
    curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/fix%20instance%20count%20API > /usr/local/bin/fix-instance-count
fi

# Make all tools executable
chmod +x /usr/local/bin/evernode-debug-api
chmod +x /usr/local/bin/fix-domain-nginx
chmod +x /usr/local/bin/fix-instance-count

# Create enhanced monitoring script
cat > /usr/local/bin/evernode-monitor << 'MONITOREOF'
#!/bin/bash
# Enhanced Evernode monitoring script

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${BLUE}🌟 Enhanced Evernode Host Monitor${NC}"
echo "================================="
echo ""

# System status
echo -e "${YELLOW}System Status:${NC}"
echo "  CPU Usage: $(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)%"
echo "  Memory: $(free -h | awk '/^Mem:/ {printf "%.1f%% used", $3/$2 * 100.0}')"
echo "  Disk: $(df -h / | awk 'NR==2 {print $5 " used"}')"
echo ""

# Service status
echo -e "${YELLOW}Service Status:${NC}"
systemctl is-active nginx >/dev/null && echo -e "  ${GREEN}✅ Nginx: Running${NC}" || echo -e "  ${RED}❌ Nginx: Not running${NC}"
systemctl is-active php*-fpm >/dev/null && echo -e "  ${GREEN}✅ PHP-FPM: Running${NC}" || echo -e "  ${RED}❌ PHP-FPM: Not running${NC}"
echo ""

# API status
echo -e "${YELLOW}API Status:${NC}"
API_RESPONSE=$(curl -s http://localhost/api/instance-count.php)
if echo "$API_RESPONSE" | jq . >/dev/null 2>&1; then
    echo -e "  ${GREEN}✅ API: Working${NC}"
    TOTAL=$(echo "$API_RESPONSE" | jq -r '.total')
    USED=$(echo "$API_RESPONSE" | jq -r '.used')
    AVAILABLE=$(echo "$API_RESPONSE" | jq -r '.available')
    echo "     Total: $TOTAL | Used: $USED | Available: $AVAILABLE"
else
    echo -e "  ${RED}❌ API: Not responding${NC}"
fi
MONITOREOF

chmod +x /usr/local/bin/evernode-monitor

# Enhanced service startup wait
echo -e "${YELLOW}⏳ Waiting for enhanced services to start...${NC}"
sleep 8

# Enhanced testing suite
echo -e "${YELLOW}🧪 Running enhanced test suite...${NC}"

# Test PHP
echo "<?php echo 'PHP OK'; ?>" > /tmp/test.php
PHP_TEST=$(php /tmp/test.php 2>/dev/null)
rm /tmp/test.php

if [[ "$PHP_TEST" == "PHP OK" ]]; then
    echo -e "${GREEN}✅ PHP is working${NC}"
else
    echo -e "${RED}❌ PHP test failed${NC}"
fi

# Test enhanced API
API_RESPONSE=$(curl -s -w "%{http_code}" http://localhost/api/instance-count.php 2>/dev/null)
HTTP_CODE="${API_RESPONSE: -3}"
HTTP_BODY="${API_RESPONSE%???}"

if [[ "$HTTP_CODE" == "200" ]]; then
    echo -e "${GREEN}✅ Enhanced API is working${NC}"
    
    # Parse enhanced API response
    if echo "$HTTP_BODY" | jq . >/dev/null 2>&1; then
        TOTAL=$(echo "$HTTP_BODY" | jq -r '.total' 2>/dev/null)
        USED=$(echo "$HTTP_BODY" | jq -r '.used' 2>/dev/null)
        AVAILABLE=$(echo "$HTTP_BODY" | jq -r '.available' 2>/dev/null)
        DATA_SOURCE=$(echo "$HTTP_BODY" | jq -r '.data_source' 2>/dev/null)
        STATUS_MSG=$(echo "$HTTP_BODY" | jq -r '.status_message' 2>/dev/null)
        
        if [[ "$TOTAL" != "null" ]] && [[ -n "$TOTAL" ]]; then
            echo -e "${BLUE}📊 Real-time instance status:${NC}"
            echo -e "${CYAN}   📈 Total: ${TOTAL} | Used: ${USED} | Available: ${AVAILABLE}${NC}"
            echo -e "${CYAN}   📡 Source: ${DATA_SOURCE}${NC}"
            echo -e "${CYAN}   💬 Status: ${STATUS_MSG}${NC}"
        fi
    fi
else
    echo -e "${RED}❌ Enhanced API test failed (HTTP $HTTP_CODE)${NC}"
    echo "Response: $HTTP_BODY"
fi

# Test enhanced landing page
LANDING_TEST=$(curl -s http://localhost/ | grep -c "Enhanced Evernode Host" || echo "0")
if [[ "$LANDING_TEST" -gt 0 ]]; then
    echo -e "${GREEN}✅ Enhanced landing page is working${NC}"
else
    echo -e "${RED}❌ Landing page test failed${NC}"
fi

# Test external access
echo -e "${YELLOW}🌐 Testing external access...${NC}"
if [[ "$HOST_IP" != "unknown" ]] && [[ -n "$HOST_IP" ]]; then
    EXTERNAL_TEST=$(curl -s -m 10 http://$HOST_IP/ | grep -c "Enhanced Evernode Host" || echo "0")
    if [[ "$EXTERNAL_TEST" -gt 0 ]]; then
        echo -e "${GREEN}✅ External access working${NC}"
    else
        echo -e "${YELLOW}⚠️ External access may have issues${NC}"
    fi
fi

echo ""
echo -e "${PURPLE}🎉 ENHANCED EVERNODE HOST SETUP COMPLETE!${NC}"
echo ""
echo -e "${BLUE}🌟 Enhanced Features Installed:${NC}"
echo -e "${GREEN}   ✅ Modern glassmorphism UI with animations${NC}"
echo -e "${GREEN}   ✅ Real-time container monitoring (30s updates)${NC}"
echo -e "${GREEN}   ✅ Accurate container counting technology${NC}"
echo -e "${GREEN}   ✅ One-click deployment commands${NC}"
echo -e "${GREEN}   ✅ Professional debug tools${NC}"
echo -e "${GREEN}   ✅ Mobile responsive design${NC}"
echo -e "${GREEN}   ✅ Hidden debug mode (click availability 5x)${NC}"
echo -e "${GREEN}   ✅ Enhanced security headers${NC}"
echo -e "${GREEN}   ✅ CORS-enabled API${NC}"
echo -e "${GREEN}   ✅ Comprehensive monitoring tools${NC}"
echo ""
echo -e "${BLUE}📋 Your Enhanced Evernode Host Details:${NC}"
echo -e "${CYAN}   🌐 Landing Page: http://${HOST_IP}${NC}"
echo -e "${CYAN}   📊 API Endpoint: http://${HOST_IP}/api/instance-count.php${NC}"
echo -e "${CYAN}   🏷️ Hostname: ${HOSTNAME}${NC}"
echo -e "${CYAN}   💻 OS: ${OS_VERSION}${NC}"
echo -e "${CYAN}   🐘 PHP: ${PHP_VERSION}${NC}"
echo -e "${CYAN}   🧠 Memory: ${MEMORY_GB}${NC}"
echo -e "${CYAN}   ⚡ CPU Cores: ${CPU_CORES}${NC}"
echo ""
echo -e "${YELLOW}🛠️ Enhanced Management Commands:${NC}"
echo -e "${GREEN}   • evernode-debug-api         - Comprehensive API diagnostics${NC}"
echo -e "${GREEN}   • fix-domain-nginx          - Fix domain and Nginx issues${NC}"
echo -e "${GREEN}   • fix-instance-count        - Fix container counting logic${NC}"
echo -e "${GREEN}   • evernode-monitor          - Real-time system monitoring${NC}"
echo ""
echo -e "${BLUE}🧪 Test Your Enhanced Installation:${NC}"
echo -e "${CYAN}   curl http://localhost/api/instance-count.php | jq .${NC}"
echo -e "${CYAN}   evernode-debug-api${NC}"
echo -e "${CYAN}   evernode-monitor${NC}"
echo ""
echo -e "${PURPLE}🚀 Your professional Enhanced Evernode Host is ready!${NC}"
echo -e "${BLUE}📚 Documentation: https://github.com/h20crypto/evernode-enhanced-setup${NC}"
echo -e "${BLUE}🎯 Features: Real-time monitoring, modern UI, professional tools${NC}"
# Add these lines to your existing quick-setup.sh
echo "🔍 Adding host discovery features..."

# Create host discovery API endpoint
cat > /var/www/html/api/host-info.php << 'EOF'
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$xahau_address = trim(shell_exec('evernode config account | grep "Address:" | awk \'{print $2}\' 2>/dev/null') ?: 'unknown');
$total_instances = intval(shell_exec('evernode config resources | grep "Instances:" | awk \'{print $2}\' 2>/dev/null') ?: 0);
$used_instances = intval(shell_exec('ls /home/ | grep sashi | wc -l 2>/dev/null') ?: 0);

echo json_encode([
    'xahau_address' => $xahau_address,
    'enhanced' => true,
    'cluster_support' => file_exists('/var/www/html/api/cluster-extension.php'),
    'instances' => [
        'total' => $total_instances,
        'available' => max(0, $total_instances - $used_instances)
    ],
    'features' => ['cluster-management', 'real-time-monitoring', 'enhanced-syntax'],
    'domain' => $_SERVER['HTTP_HOST'] ?? 'unknown',
    'version' => 'enhanced-v2.1',
    'last_updated' => date('c')
]);
?>
EOF

# Add discovery widget to main page
cat >> /var/www/html/index.html << 'EOF'
<!-- Enhanced Host Discovery Widget -->
<div class="discovery-section" style="background: #f8f9fa; padding: 30px; margin: 30px 0; border-radius: 15px;">
    <h3>🔍 Discover Other Enhanced Hosts</h3>
    <p>Find cluster-capable hosts for your distributed applications</p>
    <button onclick="discoverHosts()" class="btn" style="background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 5px;">
        Find Enhanced Hosts
    </button>
    <div id="discoveredHosts" style="margin-top: 20px;"></div>
</div>

<script>
async function discoverHosts() {
    document.getElementById('discoveredHosts').innerHTML = '🔍 Searching...';
    
    const knownHosts = [
        'h20cryptonode3.dev',
        'evernode1.zerp.network', 
        'x1.buildonevernode.cloud'
    ];
    
    const results = [];
    for (const domain of knownHosts) {
        try {
            const response = await fetch(`https://${domain}/api/host-info.php`);
            const data = await response.json();
            if (data.enhanced) results.push(data);
        } catch (e) { /* ignore offline hosts */ }
    }
    
    document.getElementById('discoveredHosts').innerHTML = results.length > 0 
        ? results.map(host => `
            <div style="background: white; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #4CAF50;">
                <strong>${host.domain}</strong><br>
                <small>Available: ${host.instances.available}/${host.instances.total} slots</small><br>
                <small>Address: ${host.xahau_address}</small>
            </div>
        `).join('')
        : '<p>No other enhanced hosts found online.</p>';
}
</script>
EOF

# Add cluster management capabilities
echo "Installing cluster management features..."

# Copy cluster manager files
wget -O /var/www/html/api/cluster-manager.php \
  https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/api/cluster-manager.php

# Install evdevkit if not present
if ! command -v evdevkit &> /dev/null; then
    npm install -g @hotpocket/evdevkit
fi

# Set permissions
chmod +x /var/www/html/api/cluster-manager.php
echo "✅ Host discovery features added!"

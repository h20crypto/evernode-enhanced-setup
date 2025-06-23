#!/bin/bash
# update-existing-hosts.sh
# Updates existing Enhanced Evernode hosts to Discovery System v3.0

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}🔄 Updating to Enhanced Discovery System v3.0${NC}"
echo "=============================================="
echo -e "${GREEN}✅ Advanced sorting & filtering${NC}"
echo -e "${GREEN}✅ Fixed deploy commands (correct evdevkit syntax)${NC}"
echo -e "${GREEN}✅ Premium cluster management${NC}"
echo -e "${GREEN}✅ Real-time EVR pricing${NC}"
echo -e "${GREEN}✅ Reputation-based quality scoring${NC}"
echo ""

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}❌ This script must be run as root (use sudo)${NC}"
   exit 1
fi

# Detect current domain
CURRENT_DOMAIN=""
if [ -d "/etc/nginx/sites-enabled" ]; then
    CURRENT_DOMAIN=$(grep -r "server_name" /etc/nginx/sites-enabled/ 2>/dev/null | grep -v "_" | grep -v "localhost" | head -1 | sed 's/.*server_name[[:space:]]*\([^[:space:];]*\).*/\1/')
fi

if [ -z "$CURRENT_DOMAIN" ] || [ "$CURRENT_DOMAIN" = "server_name" ]; then
    CURRENT_DOMAIN=$(find /etc/letsencrypt/live/ -maxdepth 1 -type d 2>/dev/null | grep -v "README" | head -1 | xargs basename 2>/dev/null)
fi

if [ -z "$CURRENT_DOMAIN" ] || [ "$CURRENT_DOMAIN" = "server_name" ]; then
    echo -e "${YELLOW}❓ Could not auto-detect domain. Please enter your domain:${NC}"
    read -p "Domain: " CURRENT_DOMAIN
fi

if [ -z "$CURRENT_DOMAIN" ]; then
    CURRENT_DOMAIN=$(hostname -f 2>/dev/null || hostname || echo "localhost")
fi

echo -e "${BLUE}🎯 Updating domain: $CURRENT_DOMAIN${NC}"
echo ""

# Backup existing files
echo -e "${YELLOW}📋 Creating backup...${NC}"
BACKUP_DIR="/tmp/evernode-backup-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Backup existing discovery files if they exist
[ -f "/var/www/html/host-discovery.html" ] && cp "/var/www/html/host-discovery.html" "$BACKUP_DIR/"
[ -f "/var/www/html/api/evernode-stats-cached.php" ] && cp "/var/www/html/api/evernode-stats-cached.php" "$BACKUP_DIR/"
[ -f "/var/www/html/api/enhanced-search.php" ] && cp "/var/www/html/api/enhanced-search.php" "$BACKUP_DIR/"

echo -e "${GREEN}✅ Backup created at: $BACKUP_DIR${NC}"

# Create directories
mkdir -p /var/www/html/api
mkdir -p /var/www/html/cluster

# Download latest files from GitHub
GITHUB_REPO="https://raw.githubusercontent.com/h20crypto/evernode-enhanced-discovery/main"

echo -e "${BLUE}📥 Downloading Enhanced Discovery v3.0...${NC}"

# Download enhanced APIs
curl -fsSL "$GITHUB_REPO/api/evernode-stats-cached.php" -o /var/www/html/api/evernode-stats-cached.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Stats API updated${NC}"
else
    echo -e "${RED}❌ Failed to download Stats API${NC}"
fi

curl -fsSL "$GITHUB_REPO/api/enhanced-search.php" -o /var/www/html/api/enhanced-search.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Search API updated${NC}"
else
    echo -e "${RED}❌ Failed to download Search API${NC}"
fi

# Download enhanced discovery page
curl -fsSL "$GITHUB_REPO/pages/host-discovery.html" -o /var/www/html/host-discovery.html
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Discovery page updated${NC}"
else
    echo -e "${RED}❌ Failed to download Discovery page${NC}"
fi

# Download cluster manager
curl -fsSL "$GITHUB_REPO/pages/cluster-manager.html" -o /var/www/html/cluster-manager.html
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Cluster manager added${NC}"
else
    echo -e "${RED}❌ Failed to download Cluster manager${NC}"
fi

# Update navigation in existing landing page
echo -e "${BLUE}🔗 Updating navigation...${NC}"
if [ -f "/var/www/html/index.html" ]; then
    # Check if cluster manager link already exists
    if ! grep -q "cluster-manager.html" /var/www/html/index.html; then
        # Add cluster manager link to navigation
        if grep -q "host-discovery.html" /var/www/html/index.html; then
            sed -i 's|host-discovery.html.*">.*Discovery.*</a>|host-discovery.html" class="nav-link">🔍 Discovery</a>\n                    <a href="/cluster-manager.html" class="nav-link">🏗️ Cluster Manager</a>|g' /var/www/html/index.html
            echo -e "${GREEN}✅ Navigation updated with cluster manager${NC}"
        else
            echo -e "${YELLOW}⚠️ Could not update navigation automatically${NC}"
        fi
    else
        echo -e "${GREEN}✅ Navigation already includes cluster manager${NC}"
    fi
else
    echo -e "${YELLOW}⚠️ No index.html found - navigation update skipped${NC}"
fi

# Set correct permissions
echo -e "${BLUE}🔐 Setting permissions...${NC}"
chown -R www-data:www-data /var/www/html/api/
chown -R www-data:www-data /var/www/html/cluster/
chown www-data:www-data /var/www/html/host-discovery.html
chown www-data:www-data /var/www/html/cluster-manager.html
chmod 644 /var/www/html/api/*.php
chmod 644 /var/www/html/*.html

# Clear any existing caches
echo -e "${BLUE}🧹 Clearing caches...${NC}"
rm -f /tmp/evernode_stats_cache.json
rm -f /tmp/evr_price_cache.json

# Restart services
echo -e "${BLUE}🚀 Restarting services...${NC}"
systemctl restart nginx
systemctl restart php*-fpm

# Test the updated system
echo ""
echo -e "${BLUE}🧪 Testing updated system...${NC}"

# Test Stats API
if curl -s http://localhost/api/evernode-stats-cached.php | jq '.success' > /dev/null 2>&1; then
    version=$(curl -s http://localhost/api/evernode-stats-cached.php | jq -r '.version // "unknown"')
    evr_price=$(curl -s http://localhost/api/evernode-stats-cached.php | jq -r '.pricing.evr_price_usd // "unknown"')
    echo -e "${GREEN}✅ Stats API working (version: $version, EVR: \$$evr_price)${NC}"
else
    echo -e "${RED}❌ Stats API failed${NC}"
fi

# Test Search API
if curl -s "http://localhost/api/enhanced-search.php?action=search&limit=1" | jq '.success' > /dev/null 2>&1; then
    version=$(curl -s "http://localhost/api/enhanced-search.php?action=search&limit=1" | jq -r '.version // "unknown"')
    echo -e "${GREEN}✅ Search API working (version: $version)${NC}"
else
    echo -e "${RED}❌ Search API failed${NC}"
fi

# Test Discovery Page
if curl -f http://localhost/host-discovery.html > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Discovery page working${NC}"
else
    echo -e "${RED}❌ Discovery page failed${NC}"
fi

# Test Cluster Manager
if curl -f http://localhost/cluster-manager.html > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Cluster manager working${NC}"
else
    echo -e "${RED}❌ Cluster manager failed${NC}"
fi

echo ""
echo -e "${GREEN}🎉 Enhanced Discovery System v3.0 Update Complete!${NC}"
echo ""
echo -e "${BLUE}🔗 Updated URLs:${NC}"
echo -e "${GREEN}   🔍 Discovery: https://$CURRENT_DOMAIN/host-discovery.html${NC}"
echo -e "${GREEN}   🏗️ Cluster Manager: https://$CURRENT_DOMAIN/cluster-manager.html${NC}"
echo -e "${GREEN}   📊 Stats API: https://$CURRENT_DOMAIN/api/evernode-stats-cached.php${NC}"
echo -e "${GREEN}   🔍 Search API: https://$CURRENT_DOMAIN/api/enhanced-search.php${NC}"
echo ""
echo -e "${BLUE}🆕 New Features:${NC}"
echo -e "${GREEN}   • Advanced sorting by country, CPU, cost, memory, disk, reputation${NC}"
echo -e "${GREEN}   • Fixed deploy commands with correct evdevkit syntax${NC}"
echo -e "${GREEN}   • Auto-filled domain names in copy commands${NC}"
echo -e "${GREEN}   • Premium cluster management integration${NC}"
echo -e "${GREEN}   • Real-time EVR pricing from Evernode market API${NC}"
echo -e "${GREEN}   • Reputation-based quality scoring (no version)${NC}"
echo ""
echo -e "${BLUE}📋 Backup Location: $BACKUP_DIR${NC}"
echo -e "${GREEN}🚀 Your host is now running Enhanced Discovery v3.0!${NC}"

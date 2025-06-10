#!/bin/bash

# 🌐 EVERNODE ENHANCED HOST - QUICK SETUP
# One-command setup for host operators
# Deploys landing page and runs complete enhancement installer

set -e

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}🌐 Enhanced Evernode Host - Quick Setup${NC}"
echo -e "${BLUE}=====================================${NC}"
echo ""

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}❌ This script must be run as root (use sudo)${NC}"
   exit 1
fi

# Get host information
echo -e "${YELLOW}📋 Gathering host information...${NC}"
HOST_IP=$(curl -s https://ipinfo.io/ip 2>/dev/null || echo "unknown")
HOSTNAME=$(hostname -f 2>/dev/null || hostname)

echo -e "${GREEN}🌐 Host IP: $HOST_IP${NC}"
echo -e "${GREEN}🏷️ Hostname: $HOSTNAME${NC}"
echo ""

# Install prerequisites
echo -e "${YELLOW}📦 Installing prerequisites...${NC}"
apt-get update >/dev/null 2>&1
apt-get install -y curl wget nginx certbot python3-certbot-nginx >/dev/null 2>&1

# Create web directory
mkdir -p /var/www/html
chown www-data:www-data /var/www/html

# Download and deploy landing page
echo -e "${YELLOW}📄 Deploying professional landing page...${NC}"
curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/landing-page/index.html > /var/www/html/index.html

# Start nginx
systemctl start nginx >/dev/null 2>&1
systemctl enable nginx >/dev/null 2>&1

echo -e "${GREEN}✅ Landing page deployed${NC}"

# Download and run the main enhancement installer
echo -e "${YELLOW}🔧 Running complete enhancement installer...${NC}"
echo ""
curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/install.sh | bash

echo ""
echo -e "${GREEN}🎉 SETUP COMPLETE!${NC}"
echo ""
echo -e "${BLUE}📋 Your Enhanced Evernode Host:${NC}"
echo -e "${GREEN}   🌐 Landing Page: http://$HOST_IP${NC}"
echo -e "${GREEN}   🏷️ Hostname: $HOSTNAME${NC}"
echo -e "${GREEN}   🔧 Enhanced Features: ACTIVE${NC}"
echo ""
echo -e "${YELLOW}📖 Available Commands:${NC}"
echo -e "${GREEN}   • evernode-enhanced-status    - Show system status${NC}"
echo -e "${GREEN}   • evernode-port-status       - Check port mappings${NC}"
echo -e "${GREEN}   • evernode-containers        - Manage containers${NC}"
echo -e "${GREEN}   • evernode-ssl               - Manage SSL certificates${NC}"
echo ""
echo -e "${BLUE}🎯 Example Enhanced Deployment:${NC}"
echo -e "${GREEN}   evdevkit acquire -i n8nio/n8n:latest--gptcp1--5678--ssl--true--domain--yourdomain.com rYourHost -m 24${NC}"
echo ""
echo -e "${BLUE}🚀 Your host is now ready for enhanced Docker deployments!${NC}"

#!/bin/bash

# 🚀 Evernode Enhanced Payment API Installation Script
set -e

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}🚀 Installing Evernode Enhanced Payment API...${NC}"

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo -e "${YELLOW}📦 Installing Node.js...${NC}"
    curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
    sudo apt-get install -y nodejs
fi

# Check Node.js version
NODE_VERSION=$(node --version | cut -d'v' -f2 | cut -d'.' -f1)
if [ "$NODE_VERSION" -lt 16 ]; then
    echo -e "${RED}❌ Node.js 16+ required. Current version: $(node --version)${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Node.js $(node --version) detected${NC}"

# Install dependencies
echo -e "${YELLOW}📦 Installing dependencies...${NC}"
npm install

# Create .env file if it doesn't exist
if [ ! -f .env ]; then
    echo -e "${YELLOW}⚙️ Creating environment configuration...${NC}"
    cat > .env << EOF
# Xumm API Credentials
XUMM_API_KEY=your_xumm_api_key_here
XUMM_API_SECRET=your_xumm_api_secret_here

# Receiving wallet for payments
RECEIVING_WALLET=rYourWalletAddressHere

# Server configuration
PORT=3000
NODE_ENV=production
EOF
    echo -e "${RED}⚠️ Please edit .env file with your Xumm API credentials${NC}"
    echo -e "${BLUE}Get credentials at: https://apps.xumm.dev${NC}"
fi

# Build TypeScript
echo -e "${YELLOW}🔨 Building TypeScript...${NC}"
npm run build

# Create systemd service
echo -e "${YELLOW}⚙️ Creating systemd service...${NC}"
sudo tee /etc/systemd/system/evernode-payment-api.service > /dev/null << EOF
[Unit]
Description=Evernode Enhanced Payment API
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=$(pwd)
Environment=NODE_ENV=production
ExecStart=/usr/bin/node dist/index.js
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

# Set proper permissions
sudo chown -R www-data:www-data .
sudo chmod +x scripts/install-and-start.sh

# Start and enable service
echo -e "${YELLOW}🔄 Starting service...${NC}"
sudo systemctl daemon-reload
sudo systemctl enable evernode-payment-api
sudo systemctl start evernode-payment-api

# Check service status
if sudo systemctl is-active --quiet evernode-payment-api; then
    echo -e "${GREEN}✅ Payment API service started successfully${NC}"
    echo -e "${GREEN}📊 Status: http://localhost:3000/api/status${NC}"
    echo -e "${GREEN}💰 Rates: http://localhost:3000/api/crypto-rates${NC}"
else
    echo -e "${RED}❌ Service failed to start. Check logs with:${NC}"
    echo -e "${RED}   sudo journalctl -u evernode-payment-api -f${NC}"
    exit 1
fi

echo -e "${BLUE}🎉 Installation complete!${NC}"
echo -e "${YELLOW}⚠️ Remember to:${NC}"
echo -e "${YELLOW}   1. Update .env with your Xumm API credentials${NC}"
echo -e "${YELLOW}   2. Restart the service: sudo systemctl restart evernode-payment-api${NC}"
echo -e "${YELLOW}   3. Configure nginx proxy in your main setup${NC}"

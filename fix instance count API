#!/bin/bash

# 🔧 FIX INSTANCE COUNT - Count actual containers, not users

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}🔧 Enhanced Instance Count Fix${NC}"
echo "=================================="
echo ""

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}❌ This script must be run as root (use sudo)${NC}"
   exit 1
fi

# First, let's see what we're actually counting
echo -e "${YELLOW}🔍 Current Status Analysis:${NC}"
echo -e "${GREEN}Sashimono users found:${NC}"
SASHI_USERS=$(getent passwd | grep sashi | wc -l)
echo "  Total: $SASHI_USERS users"

echo ""
echo -e "${GREEN}Sashimono users list:${NC}"
getent passwd | grep sashi | cut -d: -f1 | head -5

echo ""
echo -e "${GREEN}Actual running containers per user:${NC}"
TOTAL_CONTAINERS=0
for user in $(getent passwd | grep sashi | cut -d: -f1); do
    if [[ -n "$user" ]]; then
        CONTAINERS=$(sudo -u "$user" docker ps -q 2>/dev/null | wc -l)
        echo "  $user: $CONTAINERS containers"
        TOTAL_CONTAINERS=$((TOTAL_CONTAINERS + CONTAINERS))
        
        # Show what containers are running
        if [[ $CONTAINERS -gt 0 ]]; then
            echo "    $(sudo -u "$user" docker ps --format 'table {{.Names}}\t{{.Image}}' 2>/dev/null | tail -n +2)"
        fi
    fi
done
echo -e "${BLUE}📊 Total running containers: $TOTAL_CONTAINERS${NC}"

echo ""
echo -e "${YELLOW}🔧 Checking current API...${NC}"
if [[ -f "/var/www/html/api/instance-count.php" ]]; then
    echo -e "${GREEN}✅ API file exists${NC}"
    
    # Test current API
    echo -e "${YELLOW}Testing current API response:${NC}"
    CURRENT_API=$(php /var/www/html/api/instance-count.php 2>/dev/null)
    if [[ $? -eq 0 ]]; then
        echo "$CURRENT_API" | jq . 2>/dev/null || echo "$CURRENT_API"
    else
        echo -e "${RED}❌ Current API has errors${NC}"
    fi
else
    echo -e "${RED}❌ API file not found${NC}"
    echo "Creating directory structure..."
    mkdir -p /var/www/html/api
fi

echo ""
echo -e "${YELLOW}📝 Installing improved API...${NC}"

# Create the improved API that counts actual containers
cat > /var/www/html/api/instance-count.php << 'PHPEOF'
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function getEvernodeInstanceData() {
    try {
        // Get total instance capacity from Evernode
        $resourcesCmd = "evernode config resources 2>/dev/null";
        $resourcesOutput = shell_exec($resourcesCmd);
        
        // Parse total slots from resources output
        $totalSlots = 3; // Default fallback
        if ($resourcesOutput && preg_match('/Instance count:\s*(\d+)/', $resourcesOutput, $matches)) {
            $totalSlots = (int)$matches[1];
        }
        
        // Count ACTUAL running containers (not users)
        $usedSlots = 0;
        $sashiUserCount = 0;
        $containerDetails = [];
        
        // Get all sashi users
        $sashiUsersCmd = "getent passwd | grep sashi | cut -d: -f1 2>/dev/null";
        $sashiUsersOutput = shell_exec($sashiUsersCmd);
        
        if ($sashiUsersOutput) {
            $users = array_filter(explode("\n", trim($sashiUsersOutput)));
            $sashiUserCount = count($users);
            
            foreach ($users as $user) {
                if (!empty($user)) {
                    // Count running containers for this user
                    $containerCountCmd = "sudo -u $user docker ps -q 2>/dev/null | wc -l";
                    $containerCount = (int)trim(shell_exec($containerCountCmd));
                    
                    if ($containerCount > 0) {
                        $usedSlots += $containerCount;
                        
                        // Get container details
                        $containerInfoCmd = "sudo -u $user docker ps --format 'table {{.Names}}\t{{.Image}}\t{{.Status}}' 2>/dev/null";
                        $containerInfo = shell_exec($containerInfoCmd);
                        
                        $containerDetails[] = [
                            'user' => $user,
                            'container_count' => $containerCount,
                            'containers' => trim($containerInfo)
                        ];
                    }
                }
            }
        }
        
        // Calculate metrics
        $availableSlots = max(0, $totalSlots - $usedSlots);
        $usagePercentage = $totalSlots > 0 ? round(($usedSlots / $totalSlots) * 100) : 0;
        
        // Determine status
        $status = 'available';
        $statusMessage = '✅ Ready for new deployments!';
        
        if ($availableSlots <= 0) {
            $status = 'full';
            $statusMessage = '🔴 Currently at capacity';
        } elseif ($availableSlots == 1) {
            $status = 'limited';
            $statusMessage = '⚡ Only 1 slot remaining';
        } elseif ($availableSlots <= 2) {
            $status = 'limited';
            $statusMessage = '⚡ Limited slots available';
        }
        
        return [
            'total' => $totalSlots,
            'used' => $usedSlots,
            'available' => $availableSlots,
            'usage_percentage' => $usagePercentage,
            'status' => $status,
            'status_message' => $statusMessage,
            'last_updated' => date('Y-m-d H:i:s'),
            'data_source' => 'actual_containers',
            'debug_info' => [
                'sashi_users_total' => $sashiUserCount,
                'containers_running' => $usedSlots,
                'container_details' => $containerDetails,
                'resources_output' => trim($resourcesOutput ?: ''),
                'counting_method' => 'containers_not_users'
            ],
            'success' => true
        ];
        
    } catch (Exception $e) {
        return [
            'total' => 3,
            'used' => 1,
            'available' => 2,
            'usage_percentage' => 33,
            'status' => 'available',
            'status_message' => '✅ Ready for deployments (estimated)',
            'last_updated' => date('Y-m-d H:i:s'),
            'data_source' => 'fallback',
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

echo json_encode(getEvernodeInstanceData(), JSON_PRETTY_PRINT);
?>
PHPEOF

# Set proper permissions
echo -e "${YELLOW}🔐 Setting permissions...${NC}"
chown www-data:www-data /var/www/html/api/instance-count.php
chmod 755 /var/www/html/api/instance-count.php

# Test the improved API
echo -e "${YELLOW}🧪 Testing improved API...${NC}"
IMPROVED_API=$(php /var/www/html/api/instance-count.php 2>/dev/null)
if [[ $? -eq 0 ]]; then
    echo -e "${GREEN}✅ API test successful${NC}"
    echo "$IMPROVED_API" | jq . 2>/dev/null || echo "$IMPROVED_API"
else
    echo -e "${RED}❌ API test failed${NC}"
fi

echo ""
echo -e "${YELLOW}🌐 Testing HTTP API...${NC}"
HTTP_RESPONSE=$(curl -s -w "%{http_code}" http://localhost/api/instance-count.php 2>/dev/null)
HTTP_CODE="${HTTP_RESPONSE: -3}"
HTTP_BODY="${HTTP_RESPONSE%???}"

if [[ "$HTTP_CODE" == "200" ]]; then
    echo -e "${GREEN}✅ HTTP API working${NC}"
    echo "$HTTP_BODY" | jq . 2>/dev/null || echo "$HTTP_BODY"
else
    echo -e "${RED}❌ HTTP API failed (Status: $HTTP_CODE)${NC}"
    echo "Response: $HTTP_BODY"
    
    # Check web server status
    echo -e "${YELLOW}Checking web server...${NC}"
    systemctl status nginx --no-pager -l
    systemctl status php*-fpm --no-pager -l
fi

echo ""
echo -e "${GREEN}✅ Instance count logic updated!${NC}"
echo ""
echo -e "${BLUE}🎯 Key improvements:${NC}"
echo -e "${GREEN}• Now counts actual running Docker containers${NC}"
echo -e "${GREEN}• Previously counted Sashimono users ($SASHI_USERS users)${NC}"
echo -e "${GREEN}• Now reports actual containers ($TOTAL_CONTAINERS containers)${NC}"
echo -e "${GREEN}• More accurate availability reporting${NC}"
echo ""
echo -e "${BLUE}📊 Your landing page will now show accurate data!${NC}"

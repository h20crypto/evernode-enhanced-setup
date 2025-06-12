#!/bin/bash

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}🔍 Enhanced Evernode API Debug Tool${NC}"
echo "====================================="
echo ""

echo -e "${YELLOW}Testing Evernode CLI commands:${NC}"
echo -e "${GREEN}evernode config totalins:${NC}"
TOTAL_INS=$(evernode config totalins 2>/dev/null)
if [[ -n "$TOTAL_INS" ]]; then
    echo "  Result: $TOTAL_INS"
else
    echo "  Command not available"
fi

echo -e "${GREEN}evernode config activeins:${NC}"
ACTIVE_INS=$(evernode config activeins 2>/dev/null)
if [[ -n "$ACTIVE_INS" ]]; then
    echo "  Result: $ACTIVE_INS"
else
    echo "  Command not available"
fi

echo -e "${GREEN}evernode info:${NC}"
INFO_OUTPUT=$(evernode info 2>/dev/null)
if [[ -n "$INFO_OUTPUT" ]]; then
    echo "$INFO_OUTPUT" | head -10
else
    echo "  Command not available"
fi

echo -e "${GREEN}evernode config leaseamt:${NC}"
LEASE_AMT=$(evernode config leaseamt 2>/dev/null)
if [[ -n "$LEASE_AMT" ]]; then
    echo "  Result: $LEASE_AMT"
else
    echo "  Command not available"
fi

echo -e "${GREEN}evernode config resources:${NC}"
RESOURCES=$(evernode config resources 2>/dev/null)
if [[ -n "$RESOURCES" ]]; then
    echo "$RESOURCES"
else
    echo "  Command not available"
fi

echo ""
echo -e "${YELLOW}Testing container counting methods:${NC}"
echo -e "${GREEN}Sashimono users total:${NC}"
SASHI_COUNT=$(getent passwd | grep sashi | wc -l)
echo "  Found: $SASHI_COUNT users"

if [[ $SASHI_COUNT -gt 0 ]]; then
    echo -e "${GREEN}Sashimono user list:${NC}"
    getent passwd | grep sashi | cut -d: -f1 | head -5
    
    echo -e "${GREEN}Actual running containers:${NC}"
    TOTAL_CONTAINERS=0
    for user in $(getent passwd | grep sashi | cut -d: -f1 2>/dev/null); do
        if [[ -n "$user" ]]; then
            CONTAINERS=$(sudo -u "$user" docker ps -q 2>/dev/null | wc -l)
            echo "  $user: $CONTAINERS containers"
            TOTAL_CONTAINERS=$((TOTAL_CONTAINERS + CONTAINERS))
            
            # Show running container details
            if [[ $CONTAINERS -gt 0 ]]; then
                echo "    Running: $(sudo -u "$user" docker ps --format '{{.Names}} ({{.Image}})' 2>/dev/null | tr '\n' ' ')"
            fi
        fi
    done
    echo -e "${BLUE}  Total running containers: $TOTAL_CONTAINERS${NC}"
    
    # Show the counting discrepancy
    if [[ $TOTAL_CONTAINERS -ne $SASHI_COUNT ]]; then
        echo -e "${YELLOW}  ⚠️ Discrepancy: $SASHI_COUNT users vs $TOTAL_CONTAINERS containers${NC}"
    else
        echo -e "${GREEN}  ✅ User count matches container count${NC}"
    fi
else
    echo "  No sashimono users found"
fi

echo ""
echo -e "${YELLOW}Host configuration check:${NC}"
echo -e "${GREEN}Host config directories:${NC}"
CONFIG_DIRS=$(ls -la /home/*/evernode-host 2>/dev/null)
if [[ -n "$CONFIG_DIRS" ]]; then
    echo "$CONFIG_DIRS" | head -5
else
    echo "  No config directories found"
fi

echo -e "${GREEN}Host registration files:${NC}"
REG_FILES=$(find /home -name ".host-reg-token" 2>/dev/null)
if [[ -n "$REG_FILES" ]]; then
    echo "$REG_FILES" | head -3
else
    echo "  No registration tokens found"
fi

echo ""
echo -e "${YELLOW}Testing API directly:${NC}"
if [[ -f "/var/www/html/api/instance-count.php" ]]; then
    echo -e "${GREEN}✅ API file exists at /var/www/html/api/instance-count.php${NC}"
    
    echo -e "${GREEN}Testing PHP execution:${NC}"
    API_OUTPUT=$(php /var/www/html/api/instance-count.php 2>&1)
    API_EXIT_CODE=$?
    
    if [[ $API_EXIT_CODE -eq 0 ]]; then
        echo -e "${GREEN}✅ PHP execution successful${NC}"
        
        # Try to parse JSON
        if echo "$API_OUTPUT" | jq . >/dev/null 2>&1; then
            echo -e "${GREEN}✅ Valid JSON output${NC}"
            echo "$API_OUTPUT" | jq .
        else
            echo -e "${YELLOW}⚠️ Output is not valid JSON:${NC}"
            echo "$API_OUTPUT"
        fi
    else
        echo -e "${RED}❌ PHP execution failed${NC}"
        echo "Error: $API_OUTPUT"
    fi
else
    echo -e "${RED}❌ API file not found at /var/www/html/api/instance-count.php${NC}"
fi

echo ""
echo -e "${YELLOW}Testing HTTP API:${NC}"
HTTP_RESPONSE=$(curl -s -w "%{http_code}" http://localhost/api/instance-count.php 2>/dev/null)
if [[ $? -eq 0 ]]; then
    HTTP_CODE="${HTTP_RESPONSE: -3}"
    HTTP_BODY="${HTTP_RESPONSE%???}"
    
    echo -e "${GREEN}HTTP Status Code: $HTTP_CODE${NC}"
    
    if [[ "$HTTP_CODE" == "200" ]]; then
        echo -e "${GREEN}✅ HTTP API working${NC}"
        
        if echo "$HTTP_BODY" | jq . >/dev/null 2>&1; then
            echo -e "${GREEN}✅ Valid JSON response${NC}"
            echo "$HTTP_BODY" | jq .
        else
            echo -e "${YELLOW}⚠️ Response is not valid JSON:${NC}"
            echo "$HTTP_BODY"
        fi
    else
        echo -e "${RED}❌ HTTP API failed${NC}"
        echo "Response: $HTTP_BODY"
    fi
else
    echo -e "${RED}❌ Could not connect to HTTP API${NC}"
fi

echo ""
echo -e "${YELLOW}Web server status:${NC}"
echo -e "${GREEN}Nginx status:${NC}"
NGINX_STATUS=$(systemctl is-active nginx 2>/dev/null)
if [[ "$NGINX_STATUS" == "active" ]]; then
    echo -e "${GREEN}✅ Nginx is running${NC}"
else
    echo -e "${RED}❌ Nginx is not running ($NGINX_STATUS)${NC}"
fi

echo -e "${GREEN}PHP-FPM status:${NC}"
PHP_FPM_STATUS=$(systemctl is-active php*-fpm 2>/dev/null | head -1)
if [[ "$PHP_FPM_STATUS" == "active" ]]; then
    echo -e "${GREEN}✅ PHP-FPM is running${NC}"
else
    echo -e "${RED}❌ PHP-FPM is not running ($PHP_FPM_STATUS)${NC}"
fi

echo ""
echo -e "${YELLOW}File permissions check:${NC}"
if [[ -f "/var/www/html/index.html" ]]; then
    echo -e "${GREEN}✅ Landing page exists${NC}"
    ls -la /var/www/html/index.html
else
    echo -e "${RED}❌ Landing page missing${NC}"
fi

if [[ -f "/var/www/html/api/instance-count.php" ]]; then
    echo -e "${GREEN}✅ API file exists${NC}"
    ls -la /var/www/html/api/instance-count.php
else
    echo -e "${RED}❌ API file missing${NC}"
fi

echo ""
echo -e "${BLUE}📊 Summary Report:${NC}"
echo "================================"
echo -e "${GREEN}• Sashimono users: ${SASHI_COUNT:-0}${NC}"
echo -e "${GREEN}• Running containers: ${TOTAL_CONTAINERS:-0}${NC}"
echo -e "${GREEN}• Nginx status: $NGINX_STATUS${NC}"
echo -e "${GREEN}• PHP-FPM status: $PHP_FPM_STATUS${NC}"
echo -e "${GREEN}• API HTTP status: ${HTTP_CODE:-Failed}${NC}"
echo -e "${GREEN}• Data accuracy: $([ "${TOTAL_CONTAINERS:-0}" -eq "${SASHI_COUNT:-0}" ] && echo "Match" || echo "Mismatch")${NC}"

echo ""
echo -e "${BLUE}🔧 Recommendations:${NC}"
if [[ "${TOTAL_CONTAINERS:-0}" -ne "${SASHI_COUNT:-0}" ]]; then
    echo -e "${YELLOW}• Run 'fix-instance-count' to improve accuracy${NC}"
fi

if [[ "$HTTP_CODE" != "200" ]]; then
    echo -e "${YELLOW}• Check web server configuration${NC}"
    echo -e "${YELLOW}• Verify PHP-FPM is running${NC}"
    echo -e "${YELLOW}• Check file permissions${NC}"
fi

if [[ -z "$TOTAL_INS" ]] && [[ -z "$ACTIVE_INS" ]]; then
    echo -e "${YELLOW}• Evernode CLI commands not available${NC}"
    echo -e "${YELLOW}• API will use fallback counting methods${NC}"
fi

echo ""
echo -e "${GREEN}🎯 Debug complete! Use this information to troubleshoot issues.${NC}"

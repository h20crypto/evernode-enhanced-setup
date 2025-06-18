#!/bin/bash

# Autonomous Host Discovery Setup & Automation
# This sets up the self-sustaining discovery system

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${BLUE}🤖 Setting Up Autonomous Host Discovery${NC}"
echo "============================================="
echo ""

# Create the discovery API
echo -e "${YELLOW}📡 Installing Host Discovery API...${NC}"
sudo cp host-discovery.php /var/www/html/api/

# Create discovery automation script
cat > /usr/local/bin/evernode-discovery << 'EOF'
#!/bin/bash

# Evernode Enhanced Host Discovery Daemon
# Runs periodic discovery to find other enhanced hosts

DISCOVERY_LOG="/var/log/evernode-discovery.log"
DISCOVERY_INTERVAL=${1:-3600}  # Default 1 hour

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$DISCOVERY_LOG"
}

run_discovery() {
    log_message "🔍 Starting enhanced host discovery..."
    
    # Run discovery with refresh
    RESULT=$(curl -s "http://localhost/api/host-discovery.php?action=discover&refresh=true")
    
    if echo "$RESULT" | grep -q '"success":true'; then
        HOST_COUNT=$(echo "$RESULT" | jq -r '.total_discovered // 0')
        log_message "✅ Discovery completed: Found $HOST_COUNT enhanced hosts"
        
        # Log quality distribution
        HIGH_QUALITY=$(echo "$RESULT" | jq '[.hosts[] | select(.quality_score >= 80)] | length')
        AVAILABLE=$(echo "$RESULT" | jq '[.hosts[] | select(.availability > 0)] | length')
        
        log_message "📊 Network stats: $HIGH_QUALITY high-quality, $AVAILABLE available"
    else
        log_message "❌ Discovery failed: $RESULT"
    fi
}

announce_self() {
    log_message "📢 Announcing self to network..."
    
    RESULT=$(curl -s "http://localhost/api/host-discovery.php?action=announce")
    
    if echo "$RESULT" | grep -q '"success":true'; then
        log_message "✅ Self-announcement completed"
    else
        log_message "❌ Self-announcement failed: $RESULT"
    fi
}

# Main discovery loop
log_message "🚀 Starting Evernode Discovery Daemon (interval: ${DISCOVERY_INTERVAL}s)"

while true; do
    run_discovery
    announce_self
    
    log_message "😴 Sleeping for $DISCOVERY_INTERVAL seconds..."
    sleep "$DISCOVERY_INTERVAL"
done
EOF

sudo chmod +x /usr/local/bin/evernode-discovery

# Create systemd service for automatic discovery
cat > /etc/systemd/system/evernode-discovery.service << 'EOF'
[Unit]
Description=Evernode Enhanced Host Discovery Service
After=network.target
Wants=network.target

[Service]
Type=simple
User=www-data
Group=www-data
ExecStart=/usr/local/bin/evernode-discovery 3600
Restart=always
RestartSec=30
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

# Create discovery cron job as backup
cat > /etc/cron.d/evernode-discovery << 'EOF'
# Evernode Discovery - runs every hour
0 * * * * www-data /usr/bin/curl -s "http://localhost/api/host-discovery.php?action=discover&refresh=true" > /dev/null 2>&1

# Self-announcement - runs every 30 minutes  
*/30 * * * * www-data /usr/bin/curl -s "http://localhost/api/host-discovery.php?action=announce" > /dev/null 2>&1
EOF

# Create discovery management script
cat > /usr/local/bin/discovery-manager << 'EOF'
#!/bin/bash

# Discovery Management Tool

case "$1" in
    start)
        echo "🚀 Starting discovery service..."
        sudo systemctl enable evernode-discovery
        sudo systemctl start evernode-discovery
        echo "✅ Discovery service started"
        ;;
    stop)
        echo "🛑 Stopping discovery service..."
        sudo systemctl stop evernode-discovery
        sudo systemctl disable evernode-discovery
        echo "✅ Discovery service stopped"
        ;;
    status)
        echo "📊 Discovery Service Status:"
        sudo systemctl status evernode-discovery --no-pager
        echo ""
        echo "📈 Recent Discovery Stats:"
        curl -s "http://localhost/api/host-discovery.php?action=status" | jq .
        ;;
    discover)
        echo "🔍 Running manual discovery..."
        curl -s "http://localhost/api/host-discovery.php?action=discover&refresh=true" | jq .
        ;;
    announce)
        echo "📢 Announcing to network..."
        curl -s "http://localhost/api/host-discovery.php?action=announce" | jq .
        ;;
    peers)
        echo "🤝 Current peer network:"
        curl -s "http://localhost/api/host-discovery.php?action=peers" | jq .
        ;;
    logs)
        echo "📋 Recent discovery logs:"
        tail -n 50 /var/log/evernode-discovery.log
        ;;
    *)
        echo "Usage: discovery-manager {start|stop|status|discover|announce|peers|logs}"
        echo ""
        echo "Commands:"
        echo "  start    - Start automatic discovery service"
        echo "  stop     - Stop automatic discovery service"  
        echo "  status   - Show service status and network stats"
        echo "  discover - Run manual discovery now"
        echo "  announce - Announce this host to network"
        echo "  peers    - Show current peer network"
        echo "  logs     - Show recent discovery logs"
        exit 1
        ;;
esac
EOF

sudo chmod +x /usr/local/bin/discovery-manager

# Update the existing host recommendations API to use discovery
cat > /var/www/html/api/smart-recommendations.php << 'EOF'
<?php
/**
 * Smart Recommendations using Autonomous Discovery
 * Automatically recommends hosts discovered by the discovery system
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

class SmartRecommendations {
    private $discovery_cache = '/tmp/enhanced_hosts_cache.json';
    
    public function getRecommendations($max_hosts = 3, $exclude_full = true) {
        // Load discovered hosts
        $discovered_hosts = $this->loadDiscoveredHosts();
        
        if (empty($discovered_hosts)) {
            // Trigger discovery if no hosts found
            $this->triggerDiscovery();
            $discovered_hosts = $this->loadDiscoveredHosts();
        }
        
        // Filter and sort recommendations
        $recommendations = [];
        
        foreach ($discovered_hosts as $host) {
            // Skip if excluding full hosts and this host is full
            if ($exclude_full && isset($host['availability']) && $host['availability'] <= 0) {
                continue;
            }
            
            // Skip low quality hosts
            if ($host['quality_score'] < 50) {
                continue;
            }
            
            // Transform to recommendation format
            $recommendation = [
                'name' => $this->generateHostName($host),
                'host' => $host['host'],
                'location' => $host['location'] ?? 'Unknown',
                'features' => $host['features'] ?? [],
                'quality_score' => $host['quality_score'],
                'availability' => $host['availability'] ?? null,
                'capacity' => $host['capacity'] ?? null,
                'lease_rate' => $host['lease_rate'] ?? 'Unknown',
                'response_time' => $host['response_time'] ?? null,
                'uptime_score' => $host['uptime_score'] ?? 0,
                'last_checked' => $host['last_checked'] ?? null,
                'discovered_automatically' => true
            ];
            
            $recommendations[] = $recommendation;
            
            if (count($recommendations) >= $max_hosts) {
                break;
            }
        }
        
        return $recommendations;
    }
    
    private function loadDiscoveredHosts() {
        if (file_exists($this->discovery_cache)) {
            $data = json_decode(file_get_contents($this->discovery_cache), true);
            return $data ?: [];
        }
        
        return [];
    }
    
    private function triggerDiscovery() {
        // Trigger background discovery
        $discovery_url = "http://localhost/api/host-discovery.php?action=discover&refresh=true";
        
        // Use curl in background to avoid blocking
        $cmd = "curl -s '$discovery_url' > /dev/null 2>&1 &";
        shell_exec($cmd);
    }
    
    private function generateHostName($host_data) {
        // Generate friendly names for discovered hosts
        $location = $host_data['location'] ?? 'Unknown';
        $quality = $host_data['quality_score'] ?? 0;
        
        $prefix = '';
        if ($quality >= 90) $prefix = 'Premium';
        elseif ($quality >= 80) $prefix = 'Professional';
        elseif ($quality >= 70) $prefix = 'Enhanced';
        else $prefix = 'Standard';
        
        return "$prefix Host ($location)";
    }
    
    public function getNetworkStats() {
        $hosts = $this->loadDiscoveredHosts();
        
        $stats = [
            'total_discovered' => count($hosts),
            'high_quality' => count(array_filter($hosts, function($h) { 
                return ($h['quality_score'] ?? 0) >= 80; 
            })),
            'available_now' => count(array_filter($hosts, function($h) { 
                return ($h['availability'] ?? 0) > 0; 
            })),
            'last_discovery' => file_exists($this->discovery_cache) ? 
                date('Y-m-d H:i:s', filemtime($this->discovery_cache)) : 'Never',
            'network_locations' => $this->getUniqueLocations($hosts),
            'average_quality' => $this->calculateAverageQuality($hosts)
        ];
        
        return $stats;
    }
    
    private function getUniqueLocations($hosts) {
        $locations = array_unique(array_map(function($h) { 
            return $h['location'] ?? 'Unknown'; 
        }, $hosts));
        
        return array_values($locations);
    }
    
    private function calculateAverageQuality($hosts) {
        if (empty($hosts)) return 0;
        
        $total_quality = array_sum(array_map(function($h) { 
            return $h['quality_score'] ?? 0; 
        }, $hosts));
        
        return round($total_quality / count($hosts), 1);
    }
}

// Handle API requests
$recommendations = new SmartRecommendations();

switch ($_GET['action'] ?? 'list') {
    case 'list':
        $max_hosts = isset($_GET['max_hosts']) ? (int)$_GET['max_hosts'] : 3;
        $exclude_full = isset($_GET['exclude_full']) ? (bool)$_GET['exclude_full'] : true;
        
        $hosts = $recommendations->getRecommendations($max_hosts, $exclude_full);
        
        echo json_encode([
            'success' => true,
            'hosts' => $hosts,
            'total_recommended' => count($hosts),
            'timestamp' => date('Y-m-d H:i:s'),
            'source' => 'autonomous_discovery'
        ]);
        break;
        
    case 'stats':
        $stats = $recommendations->getNetworkStats();
        
        echo json_encode([
            'success' => true,
            'network_stats' => $stats,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
EOF

# Create enhanced landing page integration
cat > /var/www/html/js/autonomous-discovery.js << 'EOF'
// Autonomous Discovery Integration for Landing Page
// Add this to your existing landing page JavaScript

class AutonomousDiscovery {
    constructor() {
        this.discoveryInterval = null;
        this.lastDiscoveryTime = null;
        this.networkStats = null;
    }
    
    // Initialize autonomous discovery on page load
    async init() {
        console.log('🤖 Initializing Autonomous Discovery...');
        
        // Load initial network stats
        await this.loadNetworkStats();
        
        // Start periodic discovery updates
        this.startPeriodicUpdates();
        
        // Integrate with existing availability checker
        this.integrateWithAvailabilityChecker();
    }
    
    async loadNetworkStats() {
        try {
            const response = await fetch('/api/smart-recommendations.php?action=stats');
            const data = await response.json();
            
            if (data.success) {
                this.networkStats = data.network_stats;
                this.updateNetworkDisplay();
            }
        } catch (error) {
            console.error('Failed to load network stats:', error);
        }
    }
    
    async discoverAndRecommend() {
        try {
            const response = await fetch('/api/smart-recommendations.php?action=list&max_hosts=3');
            const data = await response.json();
            
            if (data.success && data.hosts.length > 0) {
                this.displayAutonomousRecommendations(data.hosts);
                return data.hosts;
            }
        } catch (error) {
            console.error('Failed to get autonomous recommendations:', error);
        }
        
        return [];
    }
    
    displayAutonomousRecommendations(hosts) {
        const container = document.getElementById('autonomousRecommendations') || this.createRecommendationsContainer();
        
        container.innerHTML = `
            <h4>🤖 Automatically Discovered Enhanced Hosts</h4>
            <p style="font-size: 0.9rem; opacity: 0.8; margin-bottom: 15px;">
                Found ${hosts.length} quality hosts through autonomous network discovery
            </p>
            
            ${hosts.map(host => `
                <div style="background: rgba(255,255,255,0.1); border-radius: 8px; padding: 15px; margin: 10px 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div style="flex: 1; min-width: 200px;">
                        <div style="font-weight: bold; margin-bottom: 5px;">
                            ${this.getQualityIcon(host.quality_score)} ${host.name}
                        </div>
                        <div style="font-size: 0.9rem; opacity: 0.8;">
                            ${host.location} • ${host.lease_rate} • Quality: ${host.quality_score}/100
                        </div>
                        <div style="font-size: 0.8rem; margin-top: 5px;">
                            ${host.features.join(', ')}
                        </div>
                    </div>
                    <button onclick="deployToDiscoveredHost('${host.host}', '${host.name}')" 
                            style="background: #2196F3; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; white-space: nowrap;">
                        Deploy Here
                    </button>
                </div>
            `).join('')}
            
            <div style="background: rgba(33, 150, 243, 0.2); padding: 15px; border-radius: 8px; margin-top: 15px; font-size: 0.9rem;">
                <strong>🌐 Network Discovery:</strong> These hosts were automatically discovered and verified by our enhanced host network. 
                Discovery updates every hour to ensure current availability.
            </div>
        `;
        
        container.style.display = 'block';
    }
    
    createRecommendationsContainer() {
        const container = document.createElement('div');
        container.id = 'autonomousRecommendations';
        container.style.cssText = `
            background: rgba(33, 150, 243, 0.2);
            border-left: 4px solid #2196F3;
            padding: 20px;
            margin: 20px 0;
            border-radius: 10px;
            display: none;
        `;
        
        // Insert after existing alternatives or at end of main content
        const existingAlternatives = document.getElementById('simpleAlternatives');
        if (existingAlternatives) {
            existingAlternatives.parentNode.insertBefore(container, existingAlternatives.nextSibling);
        } else {
            document.querySelector('.container, main, body').appendChild(container);
        }
        
        return container;
    }
    
    getQualityIcon(score) {
        if (score >= 90) return '🏆';
        if (score >= 80) return '⭐';
        if (score >= 70) return '✅';
        return '🔸';
    }
    
    updateNetworkDisplay() {
        if (!this.networkStats) return;
        
        // Update or create network stats display
        let statsDisplay = document.getElementById('networkStats');
        if (!statsDisplay) {
            statsDisplay = document.createElement('div');
            statsDisplay.id = 'networkStats';
            statsDisplay.style.cssText = `
                background: rgba(76, 175, 80, 0.2);
                border-left: 4px solid #4CAF50;
                padding: 15px;
                margin: 15px 0;
                border-radius: 8px;
                font-size: 0.9rem;
            `;
            
            // Add to appropriate location
            const availabilityCard = document.querySelector('.availability-card, .hero-content');
            if (availabilityCard) {
                availabilityCard.appendChild(statsDisplay);
            }
        }
        
        statsDisplay.innerHTML = `
            <strong>🌐 Enhanced Host Network:</strong> 
            ${this.networkStats.total_discovered} hosts discovered • 
            ${this.networkStats.available_now} available now • 
            Avg quality: ${this.networkStats.average_quality}/100 • 
            Locations: ${this.networkStats.network_locations.join(', ')}
        `;
    }
    
    startPeriodicUpdates() {
        // Update network stats every 5 minutes
        this.discoveryInterval = setInterval(async () => {
            await this.loadNetworkStats();
        }, 5 * 60 * 1000);
    }
    
    integrateWithAvailabilityChecker() {
        // Override or enhance existing availability checker
        const originalChecker = window.checkAndShowAlternatives;
        
        window.checkAndShowAlternatives = async () => {
            // Run original checker first
            if (originalChecker) {
                originalChecker();
            }
            
            // Check our host status
            try {
                const response = await fetch('/api/instance-count.php');
                const data = await response.json();
                
                if (data.success && data.available === 0) {
                    // Host is full - show autonomous recommendations
                    const recommendations = await this.discoverAndRecommend();
                    
                    if (recommendations.length > 0) {
                        console.log(`🤖 Showing ${recommendations.length} automatically discovered alternatives`);
                    }
                } else {
                    // Host has capacity - hide autonomous recommendations
                    const container = document.getElementById('autonomousRecommendations');
                    if (container) {
                        container.style.display = 'none';
                    }
                }
            } catch (error) {
                console.error('Availability check failed:', error);
            }
        };
    }
    
    // Clean up intervals when page unloads
    destroy() {
        if (this.discoveryInterval) {
            clearInterval(this.discoveryInterval);
        }
    }
}

// Global function for deployment to discovered hosts
window.deployToDiscoveredHost = function(hostAddress, hostName) {
    const deployCommand = `evdevkit acquire -i wordpress:latest ${hostAddress} -m 48`;
    
    copyToClipboard(deployCommand).then(() => {
        const button = event.target;
        const originalText = button.textContent;
        button.textContent = '✅ Copied!';
        button.style.background = '#4CAF50';
        
        // Show deployment instructions
        alert(`✅ Command copied!\n\n📋 Paste this in your terminal:\n${deployCommand}\n\n🚀 Your app will deploy to ${hostName}\n\n🤖 This host was automatically discovered by our network`);
        
        setTimeout(() => {
            button.textContent = originalText;
            button.style.background = '';
        }, 3000);
    });
};

// Initialize autonomous discovery when page loads
document.addEventListener('DOMContentLoaded', () => {
    window.autonomousDiscovery = new AutonomousDiscovery();
    window.autonomousDiscovery.init();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.autonomousDiscovery) {
        window.autonomousDiscovery.destroy();
    }
});
EOF

# Set proper permissions
sudo chown -R www-data:www-data /var/www/html/api/
sudo chmod 644 /var/www/html/api/*.php
sudo chmod 644 /var/www/html/js/*.js
sudo chmod 755 /usr/local/bin/discovery-manager
sudo chmod 755 /usr/local/bin/evernode-discovery

# Start the discovery service
echo -e "${YELLOW}🚀 Starting Discovery Service...${NC}"
sudo systemctl daemon-reload
sudo systemctl enable evernode-discovery
sudo systemctl start evernode-discovery

echo ""
echo -e "${GREEN}✅ Autonomous Discovery System Installed!${NC}"
echo ""
echo -e "${BLUE}🤖 What this system does:${NC}"
echo -e "${GREEN}  • Automatically discovers other enhanced hosts every hour${NC}"
echo -e "${GREEN}  • Tests each host for quality and enhanced features${NC}"
echo -e "${GREEN}  • Recommends best hosts when you're at capacity${NC}"
echo -e "${GREEN}  • Self-announces to other enhanced hosts${NC}"
echo -e "${GREEN}  • Builds a collaborative network without manual setup${NC}"
echo ""
echo -e "${BLUE}🛠️ Management commands:${NC}"
echo -e "${GREEN}  discovery-manager status    - Check system status${NC}"
echo -e "${GREEN}  discovery-manager discover  - Run discovery now${NC}"
echo -e "${GREEN}  discovery-manager peers     - Show current network${NC}"
echo -e "${GREEN}  discovery-manager logs      - View discovery logs${NC}"
echo ""
echo -e "${BLUE}📊 Test the system:${NC}"
echo -e "${GREEN}  curl http://localhost/api/host-discovery.php?action=status${NC}"
echo -e "${GREEN}  curl http://localhost/api/smart-recommendations.php?action=stats${NC}"
echo ""
echo -e "${YELLOW}🌐 Your host will now automatically discover and recommend other enhanced hosts!${NC}"

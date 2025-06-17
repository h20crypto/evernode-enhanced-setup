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
    
    // ... rest of the JavaScript methods
}

// Global function for deployment to discovered hosts
window.deployToDiscoveredHost = function(hostAddress, hostName) {
    const deployCommand = `evdevkit acquire -i wordpress:latest ${hostAddress} -m 48`;
    
    copyToClipboard(deployCommand).then(() => {
        const button = event.target;
        const originalText = button.textContent;
        button.textContent = '✅ Copied!';
        button.style.background = '#4CAF50';
        
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

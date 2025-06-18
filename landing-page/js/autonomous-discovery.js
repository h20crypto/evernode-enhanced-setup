// Enhanced Autonomous Discovery Integration for Landing Page
// Complete version with all advanced features

class AutonomousDiscovery {
    constructor() {
        this.discoveryInterval = null;
        this.lastDiscoveryTime = null;
        this.networkStats = null;
        this.discoveredHosts = [];
        this.isDiscovering = false;
        this.discoveryFrequency = 30 * 60 * 1000; // 30 minutes
    }
    
    // Initialize autonomous discovery on page load
    async init() {
        console.log('🤖 Initializing Autonomous Discovery...');
        
        // Load initial network stats
        await this.loadNetworkStats();
        
        // Load cached recommendations if available
        this.loadRecommendationsCache();
        
        // Start periodic discovery updates
        this.startPeriodicUpdates();
        
        // Integrate with existing availability checker
        this.integrateWithAvailabilityChecker();
        
        // Set up event listeners
        this.setupEventListeners();
        
        console.log('✅ Autonomous Discovery initialized');
    }
    
    async loadNetworkStats() {
        try {
            const response = await fetch('/api/smart-recommendations.php?action=stats');
            const data = await response.json();
            
            if (data.success) {
                this.networkStats = data.network_stats;
                this.updateNetworkDisplay();
                console.log('📊 Network stats loaded:', this.networkStats);
            }
        } catch (error) {
            console.error('Failed to load network stats:', error);
        }
    }
    
    startPeriodicUpdates() {
        // Clear any existing interval
        if (this.discoveryInterval) {
            clearInterval(this.discoveryInterval);
        }
        
        // Update every 30 minutes
        this.discoveryInterval = setInterval(() => {
            console.log('🔄 Running periodic discovery update...');
            this.discoverAndRecommend();
        }, this.discoveryFrequency);
        
        // Initial discovery after 10 seconds
        setTimeout(() => {
            console.log('🚀 Running initial discovery...');
            this.discoverAndRecommend();
        }, 10000);
    }
    
    integrateWithAvailabilityChecker() {
        // Hook into existing availability checking
        const originalUpdateFunction = window.updateInstanceAvailability;
        
        if (typeof originalUpdateFunction === 'function') {
            window.updateInstanceAvailability = async () => {
                await originalUpdateFunction();
                
                // After checking our availability, check if we should show recommendations
                setTimeout(() => this.checkAndShowAlternatives(), 1000);
            };
            
            console.log('🔗 Integrated with existing availability checker');
        } else {
            console.log('ℹ️ No existing availability checker found');
        }
    }
    
    setupEventListeners() {
        // Listen for deployment events
        document.addEventListener('deploymentStarted', (event) => {
            this.trackDeployment(event.detail);
        });
        
        // Listen for capacity changes
        document.addEventListener('capacityChanged', (event) => {
            if (event.detail.available === 0) {
                this.showRecommendations();
            }
        });
        
        // Listen for page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.shouldRefreshDiscovery()) {
                this.discoverAndRecommend();
            }
        });
        
        console.log('👂 Event listeners set up');
    }
    
    shouldRefreshDiscovery() {
        if (!this.lastDiscoveryTime) return true;
        
        // Refresh if last discovery was more than 15 minutes ago
        return (Date.now() - this.lastDiscoveryTime) > (15 * 60 * 1000);
    }
    
    async discoverAndRecommend() {
        if (this.isDiscovering) {
            console.log('⏳ Discovery already in progress, skipping...');
            return [];
        }
        
        this.isDiscovering = true;
        this.lastDiscoveryTime = Date.now();
        
        try {
            console.log('🔍 Starting discovery and recommendations...');
            
            const response = await fetch('/api/smart-recommendations.php?action=list&max_hosts=3');
            const data = await response.json();
            
            if (data.success && data.hosts.length > 0) {
                this.discoveredHosts = data.hosts;
                this.updateRecommendationsCache();
                
                console.log(`✅ Discovered ${data.hosts.length} quality hosts`);
                
                // Only display if we should show alternatives
                const shouldShow = await this.shouldShowRecommendations();
                if (shouldShow) {
                    this.displayAutonomousRecommendations(data.hosts);
                }
                
                return data.hosts;
            } else {
                console.log('ℹ️ No recommendations available');
            }
        } catch (error) {
            console.error('❌ Failed to get autonomous recommendations:', error);
        } finally {
            this.isDiscovering = false;
        }
        
        return [];
    }
    
    async shouldShowRecommendations() {
        // Check if our host is at capacity
        try {
            const response = await fetch('/api/instance-count.php');
            const data = await response.json();
            
            if (data.success && data.available !== undefined) {
                return data.available <= 0;
            }
        } catch (error) {
            console.error('Could not check availability:', error);
        }
        
        // Fallback: check DOM for availability indicators
        const availabilityElement = document.querySelector('[data-available]');
        if (availabilityElement) {
            const available = parseInt(availabilityElement.dataset.available) || 0;
            return available <= 0;
        }
        
        return false;
    }
    
    async checkAndShowAlternatives() {
        console.log('🔍 Checking if alternatives should be shown...');
        
        const shouldShow = await this.shouldShowRecommendations();
        
        if (shouldShow) {
            console.log('📢 Host at capacity - showing recommendations');
            await this.showRecommendations();
        } else {
            console.log('✅ Host has capacity - hiding recommendations');
            this.hideRecommendations();
        }
    }
    
    async showRecommendations() {
        let hosts = this.discoveredHosts;
        
        // Get fresh recommendations if none cached
        if (!hosts || hosts.length === 0) {
            hosts = await this.discoverAndRecommend();
        }
        
        if (hosts && hosts.length > 0) {
            this.displayAutonomousRecommendations(hosts);
        }
    }
    
    hideRecommendations() {
        const container = document.getElementById('autonomousRecommendations');
        if (container) {
            container.style.display = 'none';
            container.classList.remove('visible');
        }
    }
    
    displayAutonomousRecommendations(hosts) {
        const container = document.getElementById('autonomousRecommendations') || this.createRecommendationsContainer();
        
        const networkQuality = this.calculateNetworkQuality(hosts);
        const discoveryTime = new Date().toLocaleTimeString();
        
        container.innerHTML = `
            <div class="smart-recommendations-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <h4 style="margin: 0;">🤖 Automatically Discovered Enhanced Hosts</h4>
                <div class="discovery-status discovering" style="display: inline-flex; align-items: center; gap: 8px; padding: 5px 10px; background: rgba(76, 175, 80, 0.2); border-radius: 15px; font-size: 0.8rem;">
                    Network Quality: ${networkQuality}/100
                </div>
            </div>
            
            <p style="font-size: 0.9rem; opacity: 0.8; margin-bottom: 15px;">
                Found ${hosts.length} quality hosts through autonomous network discovery
            </p>
            
            ${hosts.map(host => `
                <div class="discovered-host-item" style="background: rgba(255,255,255,0.1); border-radius: 8px; padding: 15px; margin: 10px 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; transition: all 0.3s ease;">
                    <div style="flex: 1; min-width: 200px;">
                        <div style="font-weight: bold; margin-bottom: 5px; display: flex; align-items: center; flex-wrap: wrap; gap: 5px;">
                            <span>${this.getQualityIcon(host.quality_score)} ${host.name}</span>
                            <span class="host-quality-indicator quality-${this.getQualityClass(host.quality_score)}" style="display: inline-block; padding: 2px 6px; border-radius: 10px; font-size: 0.7rem; font-weight: bold;">${host.quality_score}/100</span>
                        </div>
                        <div style="font-size: 0.9rem; opacity: 0.8; margin-bottom: 3px;">
                            📍 ${host.location} • 💰 ${host.lease_rate}${host.availability ? ` • 🔓 ${host.availability} available` : ''}
                        </div>
                        <div style="font-size: 0.8rem; opacity: 0.7;">
                            ✨ ${host.features.join(', ')}
                        </div>
                        ${host.response_time ? `<div style="font-size: 0.8rem; opacity: 0.6; margin-top: 2px;">⚡ ${host.response_time}ms response</div>` : ''}
                    </div>
                    <button onclick="deployToDiscoveredHost('${host.host}', '${host.name}', ${host.quality_score})" 
                            class="deploy-to-host-btn"
                            style="background: linear-gradient(135deg, #2196F3, #1976D2); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; white-space: nowrap; font-size: 0.9rem; transition: all 0.3s ease;">
                        Deploy Here
                    </button>
                </div>
            `).join('')}
            
            <div class="network-discovery-info" style="background: rgba(33, 150, 243, 0.2); padding: 15px; border-radius: 8px; margin-top: 15px; font-size: 0.9rem; border: 1px solid rgba(33, 150, 243, 0.3);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <strong>🌐 Network Discovery:</strong> These hosts were automatically discovered and verified by our enhanced host network.
                    </div>
                    <div class="discovery-timestamp" style="font-size: 0.8rem; opacity: 0.7; font-style: italic;">
                        Last updated: ${discoveryTime}
                    </div>
                </div>
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.1); font-size: 0.8rem; opacity: 0.8;">
                    🔄 Auto-discovery runs every 30 minutes • 🏆 Quality scoring based on features, performance & uptime
                </div>
            </div>
        `;
        
        container.style.display = 'block';
        container.classList.add('visible');
        
        // Add hover effects
        this.addHoverEffects(container);
        
        console.log(`📱 Displayed ${hosts.length} recommendations`);
    }
    
    addHoverEffects(container) {
        const hostItems = container.querySelectorAll('.discovered-host-item');
        hostItems.forEach(item => {
            item.addEventListener('mouseenter', () => {
                item.style.background = 'rgba(255,255,255,0.15)';
                item.style.transform = 'translateX(5px)';
            });
            
            item.addEventListener('mouseleave', () => {
                item.style.background = 'rgba(255,255,255,0.1)';
                item.style.transform = 'translateX(0)';
            });
        });
    }
    
    createRecommendationsContainer() {
        const container = document.createElement('div');
        container.id = 'autonomousRecommendations';
        container.className = 'autonomous-recommendations';
        container.style.cssText = `
            background: rgba(33, 150, 243, 0.2);
            border-left: 4px solid #2196F3;
            padding: 20px;
            margin: 20px 0;
            border-radius: 10px;
            display: none;
            animation: slideUp 0.4s ease-out;
        `;
        
        // Insert after existing alternatives or at end of main content
        const existingAlternatives = document.getElementById('simpleAlternatives');
        const mainContainer = document.querySelector('.container, main, .main-content') || document.body;
        
        if (existingAlternatives) {
            existingAlternatives.parentNode.insertBefore(container, existingAlternatives.nextSibling);
        } else {
            mainContainer.appendChild(container);
        }
        
        console.log('📦 Created recommendations container');
        return container;
    }
    
    getQualityIcon(score) {
        if (score >= 90) return '🏆';
        if (score >= 80) return '⭐';
        if (score >= 70) return '✅';
        return '🔸';
    }
    
    getQualityClass(score) {
        if (score >= 90) return 'premium';
        if (score >= 80) return 'professional';
        if (score >= 70) return 'enhanced';
        return 'standard';
    }
    
    calculateNetworkQuality(hosts) {
        if (!hosts || hosts.length === 0) return 0;
        
        const avgQuality = hosts.reduce((sum, host) => sum + (host.quality_score || 0), 0) / hosts.length;
        return Math.round(avgQuality);
    }
    
    updateRecommendationsCache() {
        try {
            const cacheData = {
                hosts: this.discoveredHosts,
                timestamp: Date.now(),
                networkStats: this.networkStats
            };
            
            localStorage.setItem('autonomousDiscovery', JSON.stringify(cacheData));
            console.log('💾 Cached recommendations');
        } catch (error) {
            console.warn('Could not cache recommendations:', error);
        }
    }
    
    loadRecommendationsCache() {
        try {
            const cached = localStorage.getItem('autonomousDiscovery');
            if (cached) {
                const data = JSON.parse(cached);
                
                // Use cache if less than 1 hour old
                if (Date.now() - data.timestamp < 60 * 60 * 1000) {
                    this.discoveredHosts = data.hosts || [];
                    this.networkStats = data.networkStats || null;
                    console.log(`📚 Loaded ${this.discoveredHosts.length} hosts from cache`);
                    return true;
                }
            }
        } catch (error) {
            console.warn('Could not load cached recommendations:', error);
        }
        
        return false;
    }
    
    trackDeployment(deploymentInfo) {
        console.log('📊 Tracking deployment:', deploymentInfo);
        
        // Track deployment to discovered host
        if (deploymentInfo.discoveredHost) {
            console.log(`🚀 Deployment to discovered host: ${deploymentInfo.hostName} (Quality: ${deploymentInfo.qualityScore})`);
            
            // Optional: Send analytics to your own API
            this.sendDeploymentAnalytics(deploymentInfo);
        }
    }
    
    async sendDeploymentAnalytics(deploymentInfo) {
        try {
            await fetch('/api/deployment-status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'track_referral',
                    target_host: deploymentInfo.hostAddress,
                    host_name: deploymentInfo.hostName,
                    quality_score: deploymentInfo.qualityScore,
                    app_type: deploymentInfo.appType,
                    timestamp: Date.now()
                })
            });
            
            console.log('📈 Analytics sent');
        } catch (error) {
            console.log('📈 Analytics failed (optional):', error);
        }
    }
    
    updateNetworkDisplay() {
        if (!this.networkStats) return;
        
        // Update any network stats displays on the page
        const statsElements = document.querySelectorAll('[data-network-stat]');
        statsElements.forEach(element => {
            const statType = element.dataset.networkStat;
            if (this.networkStats[statType] !== undefined) {
                element.textContent = this.networkStats[statType];
            }
        });
        
        console.log('📊 Updated network display elements');
    }
    
    // Get current discovery status for external use
    getStatus() {
        return {
            isDiscovering: this.isDiscovering,
            lastDiscoveryTime: this.lastDiscoveryTime,
            discoveredHostsCount: this.discoveredHosts.length,
            networkStats: this.networkStats,
            cacheAge: this.getCacheAge()
        };
    }
    
    getCacheAge() {
        try {
            const cached = localStorage.getItem('autonomousDiscovery');
            if (cached) {
                const data = JSON.parse(cached);
                return Date.now() - data.timestamp;
            }
        } catch (error) {
            // Ignore cache errors
        }
        
        return null;
    }
    
    // Force refresh of discovery
    async forceRefresh() {
        console.log('🔄 Forcing discovery refresh...');
        
        // Clear cache
        try {
            localStorage.removeItem('autonomousDiscovery');
        } catch (error) {
            // Ignore cache errors
        }
        
        this.discoveredHosts = [];
        return await this.discoverAndRecommend();
    }
    
    destroy() {
        console.log('🧹 Cleaning up Autonomous Discovery...');
        
        if (this.discoveryInterval) {
            clearInterval(this.discoveryInterval);
            this.discoveryInterval = null;
        }
        
        // Remove event listeners
        document.removeEventListener('deploymentStarted', this.trackDeployment);
        document.removeEventListener('capacityChanged', this.checkAndShowAlternatives);
        document.removeEventListener('visibilitychange', this.handleVisibilityChange);
        
        // Hide recommendations
        this.hideRecommendations();
        
        console.log('✅ Cleanup complete');
    }
}

// Enhanced global function for deployment to discovered hosts
window.deployToDiscoveredHost = function(hostAddress, hostName, qualityScore = 0) {
    const deployCommand = `evdevkit acquire -i wordpress:latest ${hostAddress} -m 48`;
    
    console.log(`🚀 Deploying to discovered host: ${hostName} (${hostAddress})`);
    
    copyToClipboard(deployCommand).then(() => {
        const button = event.target;
        const originalText = button.textContent;
        const originalBg = button.style.background;
        
        // Update button to show success
        button.textContent = '✅ Copied!';
        button.style.background = 'linear-gradient(135deg, #4CAF50, #45a049)';
        
        // Dispatch deployment event for tracking
        document.dispatchEvent(new CustomEvent('deploymentStarted', {
            detail: {
                hostAddress: hostAddress,
                hostName: hostName,
                qualityScore: qualityScore,
                discoveredHost: true,
                appType: 'wordpress',
                timestamp: Date.now()
            }
        }));
        
        // Show enhanced success message
        const message = `✅ Command copied successfully!\n\n📋 Paste this in your terminal:\n${deployCommand}\n\n🚀 Your WordPress site will deploy to ${hostName}\n🏆 Host Quality Score: ${qualityScore}/100\n🤖 Discovered through our autonomous network\n\n💡 Tip: Your app will be ready in about 2-3 minutes!`;
        
        if (typeof showNotification === 'function') {
            showNotification(message, 'success', 8000);
        } else {
            alert(message);
        }
        
        // Reset button after delay
        setTimeout(() => {
            button.textContent = originalText;
            button.style.background = originalBg;
        }, 3000);
        
        console.log(`✅ Deployment command copied for ${hostName}`);
        
    }).catch(() => {
        const button = event.target;
        const originalText = button.textContent;
        const originalBg = button.style.background;
        
        button.style.background = 'linear-gradient(135deg, #f44336, #d32f2f)';
        button.textContent = '❌ Failed';
        
        console.error(`❌ Failed to copy deployment command for ${hostName}`);
        
        setTimeout(() => {
            button.textContent = originalText;
            button.style.background = originalBg;
        }, 2000);
    });
};

// Enhanced copy to clipboard utility with better browser support
async function copyToClipboard(text) {
    try {
        // Modern clipboard API
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return;
        }
        
        // Fallback for older browsers or non-secure contexts
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        const successful = document.execCommand('copy');
        document.body.removeChild(textArea);
        
        if (!successful) {
            throw new Error('Copy command failed');
        }
        
    } catch (error) {
        console.error('Copy to clipboard failed:', error);
        throw error;
    }
}

// Global access to autonomous discovery instance
window.getAutonomousDiscovery = function() {
    return window.autonomousDiscovery;
};

// Initialize autonomous discovery when page loads
document.addEventListener('DOMContentLoaded', () => {
    console.log('🌟 Page loaded - initializing Autonomous Discovery...');
    
    window.autonomousDiscovery = new AutonomousDiscovery();
    window.autonomousDiscovery.init();
    
    // Expose useful methods globally for debugging
    window.refreshDiscovery = () => window.autonomousDiscovery.forceRefresh();
    window.discoveryStatus = () => window.autonomousDiscovery.getStatus();
    
    console.log('💡 Debug commands available: refreshDiscovery(), discoveryStatus()');
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.autonomousDiscovery) {
        window.autonomousDiscovery.destroy();
    }
});

// Handle page visibility changes for better performance
document.addEventListener('visibilitychange', () => {
    if (window.autonomousDiscovery && !document.hidden) {
        // Refresh discovery when page becomes visible if it's been a while
        const status = window.autonomousDiscovery.getStatus();
        const cacheAge = status.cacheAge;
        
        if (!cacheAge || cacheAge > 15 * 60 * 1000) { // 15 minutes
            console.log('🔄 Page visible again - refreshing discovery...');
            window.autonomousDiscovery.discoverAndRecommend();
        }
    }
});

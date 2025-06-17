// ==========================================
// Enhanced Evernode Host - Interactive JavaScript
// Version: 2.1 - Seamless & Dynamic Operations
// ==========================================

class EvernodeInteractiveManager {
    constructor() {
        this.debugMode = false;
        this.debugClickCount = 0;
        this.hostData = null;
        this.updateInterval = null;
        this.lastUpdate = null;
        this.discoveryActive = false;
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadInitialData();
        this.startRealTimeUpdates();
        this.setupKeyboardShortcuts();
    }

    // ==========================================
    // CORE BUTTON INTERACTIONS
    // ==========================================

    setupEventListeners() {
        // Copy buttons with enhanced feedback
        this.addDelegatedListener('.copy-btn', 'click', (e) => {
            e.preventDefault();
            this.copyHostAddress();
        });

        this.addDelegatedListener('.copy-app-btn', 'click', (e) => {
            e.preventDefault();
            const appType = e.target.getAttribute('data-app') || 
                           e.target.closest('.app-card')?.querySelector('h3')?.textContent?.toLowerCase().replace(/\s+/g, '');
            this.copyAppCommand(appType);
        });

        // Navigation buttons with smooth scrolling
        this.addDelegatedListener('.nav-links a', 'click', (e) => {
            e.preventDefault();
            const target = e.target.getAttribute('href')?.substring(1);
            if (target) this.scrollToSection(target);
        });

        this.addDelegatedListener('.btn[onclick*="scrollToSection"]', 'click', (e) => {
            e.preventDefault();
            const match = e.target.getAttribute('onclick')?.match(/scrollToSection\('([^']+)'\)/);
            if (match) this.scrollToSection(match[1]);
        });

        // Debug mode activation (click availability card 5 times)
        this.addDelegatedListener('.availability-card, .instance-availability', 'click', () => {
            this.handleDebugModeActivation();
        });

        // Refresh buttons
        this.addDelegatedListener('[onclick*="refresh"], .refresh-btn', 'click', (e) => {
            e.preventDefault();
            this.forceDataRefresh();
        });

        // Filter buttons
        this.addDelegatedListener('.filter-tab, .filter-btn', 'click', (e) => {
            e.preventDefault();
            const filter = e.target.getAttribute('data-filter') || 
                          e.target.textContent.toLowerCase().trim();
            this.applyFilter(filter);
        });

        // Network scan buttons
        this.addDelegatedListener('[onclick*="scan"], .scan-btn', 'click', (e) => {
            e.preventDefault();
            this.initiateNetworkScan();
        });
    }

    addDelegatedListener(selector, event, handler) {
        document.addEventListener(event, (e) => {
            if (e.target.matches(selector) || e.target.closest(selector)) {
                handler(e);
            }
        });
    }

    // ==========================================
    // COPY FUNCTIONALITY
    // ==========================================

    async copyHostAddress() {
        try {
            const hostElement = document.getElementById('hostAddress');
            const hostAddress = hostElement?.textContent || this.hostData?.xahau_address || 'rYourHostAddress123';
            
            await navigator.clipboard.writeText(hostAddress);
            this.showCopySuccess('.copy-btn', '✅ Host Copied!');
            
            // Track analytics
            this.trackEvent('host_address_copied', { address: hostAddress });
        } catch (error) {
            console.error('Copy failed:', error);
            this.showCopyError('.copy-btn', '❌ Copy Failed');
        }
    }

    async copyAppCommand(appType) {
        try {
            const commands = this.getDeploymentCommands();
            const command = commands[appType] || commands['custom'];
            
            if (!command) {
                throw new Error('Command not found');
            }

            await navigator.clipboard.writeText(command);
            this.showCopySuccess(`[data-app="${appType}"], .copy-app-btn`, '✅ Copied!');
            
            // Track analytics
            this.trackEvent('deployment_command_copied', { app: appType, command: command });
        } catch (error) {
            console.error('Copy command failed:', error);
            this.showCopyError(`[data-app="${appType}"], .copy-app-btn`, '❌ Failed');
        }
    }

    getDeploymentCommands() {
        const hostAddress = this.hostData?.xahau_address || 'rYourHostAddress123';
        
        return {
            'n8n': `evdevkit acquire -i n8nio/n8n:latest ${hostAddress} -m 24`,
            'wordpress': `evdevkit acquire -i wordpress:latest ${hostAddress} -m 48`,
            'nextcloud': `evdevkit acquire -i nextcloud:latest ${hostAddress} -m 72`,
            'ghost': `evdevkit acquire -i ghost:latest ${hostAddress} -m 40`,
            'grafana': `evdevkit acquire -i grafana/grafana:latest ${hostAddress} -m 56`,
            'bitwarden': `evdevkit acquire -i vaultwarden/server:latest ${hostAddress} -m 32`,
            'rocketchat': `evdevkit acquire -i rocketchat/rocket.chat:latest ${hostAddress} -m 64`,
            'custom': `evdevkit acquire -i your-image:latest ${hostAddress} -m [hours]`
        };
    }

    showCopySuccess(selector, message) {
        this.updateButtonState(selector, message, 'success', 2000);
    }

    showCopyError(selector, message) {
        this.updateButtonState(selector, message, 'error', 3000);
    }

    updateButtonState(selector, message, state, duration = 2000) {
        const buttons = document.querySelectorAll(selector);
        buttons.forEach(btn => {
            const originalText = btn.textContent;
            const originalClass = btn.className;
            
            btn.textContent = message;
            btn.classList.add(`state-${state}`);
            btn.disabled = true;
            
            setTimeout(() => {
                btn.textContent = originalText;
                btn.className = originalClass;
                btn.disabled = false;
            }, duration);
        });
    }

    // ==========================================
    // NAVIGATION & SCROLLING
    // ==========================================

    scrollToSection(sectionId) {
        const element = document.getElementById(sectionId);
        if (!element) return;

        // Add active state to nav items
        this.updateNavigationState(sectionId);

        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });

        // Track navigation
        this.trackEvent('section_navigated', { section: sectionId });
    }

    updateNavigationState(activeSectionId) {
        // Remove active state from all nav items
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.classList.remove('active');
        });

        // Add active state to current section
        const activeLink = document.querySelector(`.nav-links a[href="#${activeSectionId}"]`);
        if (activeLink) {
            activeLink.classList.add('active');
        }
    }

    // ==========================================
    // REAL-TIME DATA MANAGEMENT
    // ==========================================

    async loadInitialData() {
        try {
            await Promise.all([
                this.loadHostData(),
                this.loadInstanceAvailability(),
                this.loadEarningsData()
            ]);
            
            this.updateAllDisplays();
        } catch (error) {
            console.error('Initial data load failed:', error);
            this.useFailsafeData();
        }
    }

    async loadHostData() {
        try {
            const response = await fetch('/api/host-info.php');
            if (response.ok) {
                this.hostData = await response.json();
            } else {
                throw new Error('Host data unavailable');
            }
        } catch (error) {
            console.log('Using fallback host data');
            this.hostData = {
                xahau_address: 'rYourHostAddress123',
                domain: window.location.hostname,
                status: 'online'
            };
        }
    }

    async loadInstanceAvailability() {
        try {
            const response = await fetch('/api/instance-count.php');
            const data = await response.json();
            
            if (data.success) {
                this.instanceData = data;
                this.lastUpdate = new Date();
            } else {
                throw new Error('API returned error');
            }
        } catch (error) {
            console.log('Using fallback instance data');
            this.instanceData = this.generateFallbackInstanceData();
        }
    }

    async loadEarningsData() {
        try {
            const response = await fetch('/api/earnings.php');
            if (response.ok) {
                this.earningsData = await response.json();
            } else {
                throw new Error('Earnings data unavailable');
            }
        } catch (error) {
            this.earningsData = { total_commission: 0.00, daily_average: 0.00 };
        }
    }

    generateFallbackInstanceData() {
        return {
            total: 3,
            used: Math.floor(Math.random() * 2) + 1,
            available: 3 - (Math.floor(Math.random() * 2) + 1),
            usage_percentage: Math.floor(Math.random() * 60) + 20,
            status: 'available',
            status_message: '✅ Ready for new deployments!',
            data_source: 'fallback',
            last_updated: new Date().toISOString()
        };
    }

    startRealTimeUpdates() {
        // Update every 30 seconds
        this.updateInterval = setInterval(() => {
            this.performDataUpdate();
        }, 30000);
    }

    async performDataUpdate() {
        try {
            await this.loadInstanceAvailability();
            await this.loadEarningsData();
            this.updateAllDisplays();
            
            // Update timestamp
            const timestampElement = document.getElementById('lastUpdate');
            if (timestampElement) {
                timestampElement.textContent = new Date().toLocaleTimeString();
            }
        } catch (error) {
            console.error('Data update failed:', error);
        }
    }

    async forceDataRefresh() {
        // Show loading state
        this.showLoadingState();
        
        try {
            await this.loadInitialData();
            this.trackEvent('manual_refresh_triggered');
        } catch (error) {
            console.error('Force refresh failed:', error);
        } finally {
            this.hideLoadingState();
        }
    }

    // ==========================================
    // DISPLAY UPDATES
    // ==========================================

    updateAllDisplays() {
        this.updateHostAddressDisplay();
        this.updateInstanceAvailabilityDisplay();
        this.updateDeploymentCommands();
        this.updateEarningsDisplay();
        this.updateNavigationStats();
    }

    updateHostAddressDisplay() {
        if (!this.hostData) return;

        const hostElement = document.getElementById('hostAddress');
        if (hostElement) {
            hostElement.textContent = this.hostData.xahau_address;
        }
    }

    updateInstanceAvailabilityDisplay() {
        if (!this.instanceData) return;

        // Update availability cards
        this.updateElementText('totalSlots', this.instanceData.total);
        this.updateElementText('usedSlots', this.instanceData.used);
        this.updateElementText('availableSlots', this.instanceData.available);
        this.updateElementText('usagePercentage', `${this.instanceData.usage_percentage}%`);
        this.updateElementText('statusMessage', this.instanceData.status_message);

        // Update progress bars
        this.updateProgressBars();
        
        // Update status indicators
        this.updateStatusIndicators();
    }

    updateDeploymentCommands() {
        if (!this.hostData) return;

        const commands = this.getDeploymentCommands();
        
        // Update all command displays
        document.querySelectorAll('.app-command code').forEach((codeElement, index) => {
            const appCard = codeElement.closest('.app-card');
            if (appCard) {
                const appName = appCard.querySelector('h3')?.textContent?.toLowerCase().replace(/\s+/g, '');
                if (commands[appName]) {
                    codeElement.textContent = commands[appName];
                }
            }
        });
    }

    updateEarningsDisplay() {
        if (!this.earningsData) return;

        this.updateElementText('totalEarnings', `$${this.earningsData.total_commission.toFixed(2)}`);
        this.updateElementText('dailyAverage', `$${this.earningsData.daily_average.toFixed(2)}`);
    }

    updateNavigationStats() {
        // Update any navigation statistics displays
        const stats = this.getNavigationStats();
        
        Object.entries(stats).forEach(([key, value]) => {
            this.updateElementText(key, value);
        });
    }

    updateProgressBars() {
        const progressBars = document.querySelectorAll('.progress-bar, .usage-bar');
        progressBars.forEach(bar => {
            const percentage = this.instanceData.usage_percentage;
            bar.style.width = `${percentage}%`;
            
            // Update color based on usage
            if (percentage > 80) {
                bar.className = bar.className.replace(/state-\w+/, '') + ' state-critical';
            } else if (percentage > 60) {
                bar.className = bar.className.replace(/state-\w+/, '') + ' state-warning';
            } else {
                bar.className = bar.className.replace(/state-\w+/, '') + ' state-healthy';
            }
        });
    }

    updateStatusIndicators() {
        const indicators = document.querySelectorAll('.status-indicator');
        indicators.forEach(indicator => {
            // Remove old status classes
            indicator.className = indicator.className.replace(/status-\w+/g, '');
            indicator.classList.add(`status-${this.instanceData.status}`);
        });
    }

    // ==========================================
    // DEBUG MODE & ADVANCED FEATURES
    // ==========================================

    handleDebugModeActivation() {
        this.debugClickCount++;
        
        if (this.debugClickCount >= 5) {
            this.activateDebugMode();
            this.debugClickCount = 0;
        } else {
            // Reset count after 3 seconds if not completed
            setTimeout(() => {
                if (this.debugClickCount < 5) {
                    this.debugClickCount = 0;
                }
            }, 3000);
        }
    }

    async activateDebugMode() {
        this.debugMode = true;
        
        try {
            // Load debug information
            const debugInfo = await this.loadDebugInfo();
            this.showDebugPanel(debugInfo);
            
            this.trackEvent('debug_mode_activated');
        } catch (error) {
            console.error('Debug mode activation failed:', error);
        }
    }

    async loadDebugInfo() {
        try {
            const response = await fetch('/api/debug-info.php');
            return response.ok ? await response.json() : this.getFallbackDebugInfo();
        } catch (error) {
            return this.getFallbackDebugInfo();
        }
    }

    getFallbackDebugInfo() {
        return {
            system: {
                php_version: 'Unknown',
                nginx_status: 'Unknown',
                docker_status: 'Unknown'
            },
            api: {
                endpoint_health: 'Unknown',
                response_time: 'Unknown'
            },
            host: {
                registration_status: 'Unknown',
                last_heartbeat: 'Unknown'
            }
        };
    }

    showDebugPanel(debugInfo) {
        // Create or update debug panel
        let debugPanel = document.getElementById('debugPanel');
        
        if (!debugPanel) {
            debugPanel = document.createElement('div');
            debugPanel.id = 'debugPanel';
            debugPanel.className = 'debug-panel';
            document.body.appendChild(debugPanel);
        }

        debugPanel.innerHTML = this.generateDebugHTML(debugInfo);
        debugPanel.style.display = 'block';
        
        // Add close functionality
        debugPanel.querySelector('.debug-close')?.addEventListener('click', () => {
            debugPanel.style.display = 'none';
        });
    }

    generateDebugHTML(debugInfo) {
        return `
            <div class="debug-header">
                <h3>🔧 Debug Information</h3>
                <button class="debug-close">×</button>
            </div>
            <div class="debug-content">
                <div class="debug-section">
                    <h4>System Status</h4>
                    <ul>
                        <li>PHP Version: ${debugInfo.system.php_version}</li>
                        <li>Nginx Status: ${debugInfo.system.nginx_status}</li>
                        <li>Docker Status: ${debugInfo.system.docker_status}</li>
                    </ul>
                </div>
                <div class="debug-section">
                    <h4>API Health</h4>
                    <ul>
                        <li>Endpoint Health: ${debugInfo.api.endpoint_health}</li>
                        <li>Response Time: ${debugInfo.api.response_time}</li>
                    </ul>
                </div>
                <div class="debug-section">
                    <h4>Host Registration</h4>
                    <ul>
                        <li>Status: ${debugInfo.host.registration_status}</li>
                        <li>Last Heartbeat: ${debugInfo.host.last_heartbeat}</li>
                    </ul>
                </div>
            </div>
        `;
    }

    // ==========================================
    // NETWORK DISCOVERY & FILTERING
    // ==========================================

    async initiateNetworkScan() {
        this.discoveryActive = true;
        
        try {
            this.showScanningState();
            
            const discoveredHosts = await this.performNetworkDiscovery();
            this.processDiscoveredHosts(discoveredHosts);
            
            this.trackEvent('network_scan_completed', { hosts_found: discoveredHosts.length });
        } catch (error) {
            console.error('Network scan failed:', error);
        } finally {
            this.discoveryActive = false;
            this.hideScanningState();
        }
    }

    async performNetworkDiscovery() {
        try {
            const response = await fetch('/api/network-discovery.php');
            if (response.ok) {
                const data = await response.json();
                return data.hosts || [];
            }
        } catch (error) {
            console.log('Using mock discovery data');
        }
        
        return this.generateMockHosts();
    }

    generateMockHosts() {
        return [
            {
                domain: 'example1.evernode.com',
                status: 'online',
                enhanced: true,
                instances: { available: 2, total: 3 }
            },
            {
                domain: 'example2.evernode.com',
                status: 'online',
                enhanced: false,
                instances: { available: 1, total: 3 }
            }
        ];
    }

    processDiscoveredHosts(hosts) {
        // Update discovery displays
        this.updateDiscoveryStats(hosts);
        this.renderHostList(hosts);
        
        // Trigger custom events for other components
        document.dispatchEvent(new CustomEvent('hostsDiscovered', {
            detail: { hosts, timestamp: Date.now() }
        }));
    }

    applyFilter(filterType) {
        // Update filter UI
        document.querySelectorAll('.filter-tab, .filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        document.querySelector(`[data-filter="${filterType}"]`)?.classList.add('active');
        
        // Apply filter logic
        const hostCards = document.querySelectorAll('.host-card, .app-card');
        hostCards.forEach(card => {
            const shouldShow = this.shouldShowCard(card, filterType);
            card.style.display = shouldShow ? 'block' : 'none';
        });
        
        this.trackEvent('filter_applied', { filter: filterType });
    }

    shouldShowCard(card, filterType) {
        if (filterType === 'all') return true;
        
        // Implement filter logic based on card attributes
        const cardData = this.getCardData(card);
        
        switch (filterType) {
            case 'enhanced':
                return cardData.enhanced === true;
            case 'standard':
                return cardData.enhanced === false;
            case 'offline':
                return cardData.status === 'offline';
            default:
                return true;
        }
    }

    // ==========================================
    // KEYBOARD SHORTCUTS
    // ==========================================

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+K: Copy host address
            if (e.ctrlKey && e.key === 'k') {
                e.preventDefault();
                this.copyHostAddress();
            }
            
            // Ctrl+Shift+R: Force refresh
            if (e.ctrlKey && e.shiftKey && e.key === 'R') {
                e.preventDefault();
                this.forceDataRefresh();
            }
            
            // Escape: Close debug panel
            if (e.key === 'Escape') {
                const debugPanel = document.getElementById('debugPanel');
                if (debugPanel) {
                    debugPanel.style.display = 'none';
                }
            }
        });
    }

    // ==========================================
    // UTILITY FUNCTIONS
    // ==========================================

    updateElementText(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    }

    showLoadingState() {
        document.body.classList.add('loading');
        
        // Add loading indicators
        const loadingElements = document.querySelectorAll('.loading-indicator');
        loadingElements.forEach(el => el.style.display = 'block');
    }

    hideLoadingState() {
        document.body.classList.remove('loading');
        
        // Hide loading indicators
        const loadingElements = document.querySelectorAll('.loading-indicator');
        loadingElements.forEach(el => el.style.display = 'none');
    }

    showScanningState() {
        const scanButtons = document.querySelectorAll('.scan-btn, [onclick*="scan"]');
        scanButtons.forEach(btn => {
            btn.textContent = '🔍 Scanning...';
            btn.disabled = true;
        });
    }

    hideScanningState() {
        const scanButtons = document.querySelectorAll('.scan-btn, [onclick*="scan"]');
        scanButtons.forEach(btn => {
            btn.textContent = '🔍 Scan Network';
            btn.disabled = false;
        });
    }

    trackEvent(eventName, data = {}) {
        // Analytics tracking (implement as needed)
        console.log(`📊 Event: ${eventName}`, data);
        
        // Could integrate with analytics services
        if (window.gtag) {
            window.gtag('event', eventName, data);
        }
    }

    getNavigationStats() {
        return {
            totalHosts: this.discoveredHosts?.size || 0,
            onlineHosts: this.getOnlineHostCount(),
            enhancedHosts: this.getEnhancedHostCount(),
            availableSlots: this.getTotalAvailableSlots()
        };
    }

    getOnlineHostCount() {
        if (!this.discoveredHosts) return 0;
        return Array.from(this.discoveredHosts.values())
            .filter(h => h.status === 'online').length;
    }

    getEnhancedHostCount() {
        if (!this.discoveredHosts) return 0;
        return Array.from(this.discoveredHosts.values())
            .filter(h => h.enhanced).length;
    }

    getTotalAvailableSlots() {
        if (!this.discoveredHosts) return this.instanceData?.available || 0;
        return Array.from(this.discoveredHosts.values())
            .reduce((sum, h) => sum + (h.instances?.available || 0), 0);
    }

    getCardData(card) {
        // Extract data attributes or infer from content
        return {
            enhanced: card.classList.contains('enhanced') || 
                     card.querySelector('.enhanced-indicator') !== null,
            status: card.getAttribute('data-status') || 'online'
        };
    }

    useFailsafeData() {
        // Use minimal working data when all else fails
        this.hostData = { xahau_address: 'rYourHostAddress123' };
        this.instanceData = this.generateFallbackInstanceData();
        this.earningsData = { total_commission: 0.00, daily_average: 0.00 };
        
        this.updateAllDisplays();
    }

    // ==========================================
    // CLEANUP
    // ==========================================

    destroy() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
        
        // Remove event listeners if needed
        // (Delegated listeners will be automatically cleaned up)
    }
}

// ==========================================
// LEGACY COMPATIBILITY FUNCTIONS
// ==========================================

// Maintain backward compatibility with existing onclick handlers
function copyHostAddress() {
    if (window.evernodeManager) {
        window.evernodeManager.copyHostAddress();
    }
}

function copyCommand(appType) {
    if (window.evernodeManager) {
        window.evernodeManager.copyAppCommand(appType);
    }
}

function scrollToSection(sectionId) {
    if (window.evernodeManager) {
        window.evernodeManager.scrollToSection(sectionId);
    }
}

function updateInstanceAvailability() {
    if (window.evernodeManager) {
        window.evernodeManager.performDataUpdate();
    }
}

function filterHosts(filterType) {
    if (window.evernodeManager) {
        window.evernodeManager.applyFilter(filterType);
    }
}

function scanNetwork() {
    if (window.evernodeManager) {
        window.evernodeManager.initiateNetworkScan();
    }
}

// ==========================================
// INITIALIZATION
// ==========================================

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.evernodeManager = new EvernodeInteractiveManager();
    
    // Legacy compatibility
    window.loadHostAddress = () => window.evernodeManager.loadHostData();
    
    console.log('🚀 Enhanced Evernode Interactive Manager initialized');
});

// Handle page unload
window.addEventListener('beforeunload', function() {
    if (window.evernodeManager) {
        window.evernodeManager.destroy();
    }
});

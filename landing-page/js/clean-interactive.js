// ==========================================
// Clean Interactive JavaScript System
// Seamless button interactions and UI management
// ==========================================

class CleanInteractiveSystem {
    constructor() {
        this.state = {
            debugMode: false,
            debugClickCount: 0,
            currentData: {},
            updateInProgress: false,
            copyStates: new Map()
        };
        
        this.config = {
            updateInterval: 30000, // 30 seconds
            copyTimeout: 2000, // 2 seconds
            animationDuration: 300, // 300ms
            debugClickThreshold: 5
        };
        
        this.init();
    }

    // ==========================================
    // INITIALIZATION
    // ==========================================
    
    init() {
        this.setupEventDelegation();
        this.setupKeyboardShortcuts();
        this.startDataUpdates();
        this.setupPricingIntegration();
        console.log('✨ Clean Interactive System initialized');
    }

    setupEventDelegation() {
        // Single event listener for all interactive elements
        document.addEventListener('click', this.handleClick.bind(this));
        document.addEventListener('keydown', this.handleKeyboard.bind(this));
        
        // Smooth scrolling for navigation
        document.addEventListener('click', (e) => {
            if (e.target.matches('a[href^="#"]')) {
                e.preventDefault();
                this.smoothScrollTo(e.target.getAttribute('href').slice(1));
            }
        });
    }

    // ==========================================
    // INTERACTIVE BUTTON HANDLING
    // ==========================================
    
    handleClick(e) {
        const button = e.target.closest('button, .btn, .copy-btn, .copy-app-btn');
        if (!button) return;

        // Prevent double-clicks
        if (button.disabled) return;

        // Handle different button types
        if (button.matches('.copy-btn')) {
            this.handleCopyHostAddress(e);
        } else if (button.matches('.copy-app-btn')) {
            this.handleCopyAppCommand(e);
        } else if (button.matches('.refresh-btn, [data-action="refresh"]')) {
            this.handleRefresh(e);
        } else if (button.matches('.debug-btn, [data-action="debug"]')) {
            this.handleDebugMode(e);
        } else if (button.matches('.availability-card, .instance-availability')) {
            this.handleDebugActivation(e);
        } else if (button.getAttribute('onclick')) {
            // Handle legacy onclick handlers
            this.handleLegacyOnclick(e);
        }
    }

    async handleCopyHostAddress(e) {
        e.preventDefault();
        const button = e.target.closest('.copy-btn');
        
        try {
            // Get host address from data or API
            const hostAddress = await this.getHostAddress();
            
            await navigator.clipboard.writeText(hostAddress);
            this.showCopySuccess(button, '✅ Copied!');
            this.trackEvent('host_address_copied', { address: hostAddress });
            
        } catch (error) {
            console.error('Copy host address failed:', error);
            this.showCopyError(button, '❌ Failed');
        }
    }

    async handleCopyAppCommand(e) {
        e.preventDefault();
        const button = e.target.closest('.copy-app-btn');
        const appCard = button.closest('.app-card');
        
        try {
            // Determine app type
            const appType = this.getAppType(button, appCard);
            
            // Get deployment command
            const command = await this.getDeploymentCommand(appType);
            
            await navigator.clipboard.writeText(command);
            this.showCopySuccess(button, '✅ Copied!');
            this.trackEvent('deployment_command_copied', { app: appType, command });
            
        } catch (error) {
            console.error('Copy app command failed:', error);
            this.showCopyError(button, '❌ Failed');
        }
    }

    async handleRefresh(e) {
        e.preventDefault();
        const button = e.target.closest('button, .btn');
        
        if (this.state.updateInProgress) return;
        
        this.state.updateInProgress = true;
        this.showButtonLoading(button, '🔄 Refreshing...');
        
        try {
            await this.forceDataUpdate();
            this.showButtonSuccess(button, '✅ Updated!');
            this.trackEvent('manual_refresh');
            
        } catch (error) {
            console.error('Refresh failed:', error);
            this.showButtonError(button, '❌ Failed');
        } finally {
            this.state.updateInProgress = false;
        }
    }

    handleDebugActivation(e) {
        this.state.debugClickCount++;
        
        if (this.state.debugClickCount >= this.config.debugClickThreshold) {
            this.activateDebugMode();
            this.state.debugClickCount = 0;
        }
        
        // Reset counter after 3 seconds
        setTimeout(() => {
            this.state.debugClickCount = 0;
        }, 3000);
    }

    handleLegacyOnclick(e) {
        e.preventDefault();
        const onclick = e.target.getAttribute('onclick');
        
        // Parse and execute common onclick patterns safely
        if (onclick.includes('copyCommand')) {
            const match = onclick.match(/copyCommand\(['"]([^'"]+)['"]\)/);
            if (match) {
                this.copyCommand(match[1]);
            }
        } else if (onclick.includes('scrollToSection')) {
            const match = onclick.match(/scrollToSection\(['"]([^'"]+)['"]\)/);
            if (match) {
                this.smoothScrollTo(match[1]);
            }
        } else if (onclick.includes('refreshData')) {
            this.forceDataUpdate();
        }
    }

    // ==========================================
    // DATA MANAGEMENT
    // ==========================================
    
    async getHostAddress() {
        // Try to get from cached data first
        if (this.state.currentData.xahau_address) {
            return this.state.currentData.xahau_address;
        }
        
        try {
            const response = await fetch('/api/host-info');
            const data = await response.json();
            return data.xahau_address || 'rYourHostAddress123';
        } catch (error) {
            console.warn('Failed to fetch host address:', error);
            return 'rYourHostAddress123'; // Fallback
        }
    }

    async getDeploymentCommand(appType) {
        const hostAddress = await this.getHostAddress();
        
        const commands = {
            'n8n': `evdevkit acquire -i n8nio/n8n:latest ${hostAddress} -m 24`,
            'wordpress': `evdevkit acquire -i wordpress:latest ${hostAddress} -m 48`,
            'nextcloud': `evdevkit acquire -i nextcloud:latest ${hostAddress} -m 72`,
            'ghost': `evdevkit acquire -i ghost:latest ${hostAddress} -m 40`,
            'grafana': `evdevkit acquire -i grafana/grafana:latest ${hostAddress} -m 56`,
            'bitwarden': `evdevkit acquire -i vaultwarden/server:latest ${hostAddress} -m 32`,
            'rocketchat': `evdevkit acquire -i rocketchat/rocket.chat:latest ${hostAddress} -m 64`,
            'custom': `evdevkit acquire -i your-image:latest ${hostAddress} -m [hours]`
        };
        
        return commands[appType] || commands.custom;
    }

    getAppType(button, appCard) {
        // Try data attribute first
        let appType = button.getAttribute('data-app');
        
        if (!appType && appCard) {
            // Extract from app card title
            const title = appCard.querySelector('h3')?.textContent?.toLowerCase();
            if (title) {
                appType = title.replace(/\s+/g, '').replace(/[^a-z0-9]/g, '');
            }
        }
        
        if (!appType) {
            // Extract from onclick attribute
            const onclick = button.getAttribute('onclick');
            const match = onclick?.match(/copyCommand\(['"]([^'"]+)['"]\)/);
            appType = match?.[1] || 'custom';
        }
        
        return appType || 'custom';
    }

    // ==========================================
    // REAL-TIME DATA UPDATES
    // ==========================================
    
    startDataUpdates() {
        // Initial update
        this.updateData();
        
        // Continuous updates
        setInterval(() => {
            if (!this.state.updateInProgress) {
                this.updateData();
            }
        }, this.config.updateInterval);
    }

    async updateData() {
        try {
            const [instanceData, hostData, pricingData] = await Promise.all([
                this.fetchInstanceData(),
                this.fetchHostData(),
                this.fetchPricingData()
            ]);
            
            this.state.currentData = {
                ...this.state.currentData,
                ...instanceData,
                ...hostData,
                ...pricingData,
                last_updated: new Date().toISOString()
            };
            
            this.updateUI();
            
        } catch (error) {
            console.warn('Data update failed:', error);
            this.useFallbackData();
        }
    }

    async forceDataUpdate() {
        this.state.updateInProgress = true;
        
        try {
            // Clear cache and force fresh data
            await this.clearDataCache();
            await this.updateData();
            
        } finally {
            this.state.updateInProgress = false;
        }
    }

    async fetchInstanceData() {
        const response = await fetch('/api/instance-count.php?nocache=' + Date.now());
        return await response.json();
    }

    async fetchHostData() {
        const response = await fetch('/api/host-info?nocache=' + Date.now());
        return await response.json();
    }

    async fetchPricingData() {
        if (window.UnifiedPricing) {
            return {
                pricing: window.UnifiedPricing.getCurrentPricing(),
                network_stats: window.UnifiedPricing.getNetworkStats()
            };
        }
        return {};
    }

    updateUI() {
        this.updateInstanceDisplay();
        this.updatePricingDisplay();
        this.updateStatusIndicators();
        this.updateTimestamps();
    }

    // ==========================================
    // UI UPDATE HELPERS
    // ==========================================
    
    updateInstanceDisplay() {
        const data = this.state.currentData;
        
        // Update availability numbers
        this.updateElement('#totalInstances', data.total);
        this.updateElement('#usedInstances', data.used);
        this.updateElement('#availableInstances', data.available);
        this.updateElement('#usagePercentage', data.usage_percentage + '%');
        
        // Update progress bars
        this.updateProgressBar('.availability-progress', data.usage_percentage);
        
        // Update status messages
        this.updateElement('.status-message', data.status_message);
        this.updateElement('.availability-status', data.status);
    }

    updatePricingDisplay() {
        if (!window.UnifiedPricing) return;
        
        const pricing = window.UnifiedPricing.generatePricingDisplay();
        if (!pricing) return;
        
        // Update pricing elements
        this.updateElement('.price-usd-hourly', pricing.usd.hourly);
        this.updateElement('.price-usd-daily', pricing.usd.daily);
        this.updateElement('.price-usd-monthly', pricing.usd.monthly);
        this.updateElement('.price-xrp-hourly', pricing.xrp.hourly);
        this.updateElement('.price-xrp-daily', pricing.xrp.daily);
        
        // Update cloud comparison
        if (pricing.comparison) {
            this.updateElement('.cloud-savings', pricing.comparison.savings_percent);
            this.updateElement('.cloud-savings-usd', pricing.comparison.savings_usd);
        }
    }

    updateStatusIndicators() {
        const data = this.state.currentData;
        
        // Update connection status
        const statusElement = document.querySelector('.connection-status');
        if (statusElement) {
            statusElement.className = 'connection-status ' + 
                (data.last_updated ? 'online' : 'offline');
            statusElement.textContent = data.last_updated ? '🟢 Online' : '🔴 Offline';
        }
        
        // Update confidence indicators
        const confidenceElement = document.querySelector('.price-confidence');
        if (confidenceElement && data.pricing) {
            confidenceElement.textContent = data.pricing.confidence === 'high' ? 
                '🟢 High Confidence' : '🟡 Low Confidence';
        }
    }

    updateTimestamps() {
        const timestamp = this.state.currentData.last_updated;
        if (!timestamp) return;
        
        const timeAgo = this.getTimeAgo(new Date(timestamp));
        this.updateElement('.last-updated', `Updated ${timeAgo}`);
    }

    // ==========================================
    // BUTTON STATE MANAGEMENT
    // ==========================================
    
    showCopySuccess(button, message) {
        this.setButtonState(button, message, 'success');
    }

    showCopyError(button, message) {
        this.setButtonState(button, message, 'error');
    }

    showButtonLoading(button, message) {
        this.setButtonState(button, message, 'loading');
    }

    showButtonSuccess(button, message) {
        this.setButtonState(button, message, 'success');
    }

    showButtonError(button, message) {
        this.setButtonState(button, message, 'error');
    }

    setButtonState(button, message, state) {
        const originalText = button.dataset.originalText || button.textContent;
        const originalClass = button.dataset.originalClass || button.className;
        
        // Store original state if not already stored
        if (!button.dataset.originalText) {
            button.dataset.originalText = originalText;
            button.dataset.originalClass = originalClass;
        }
        
        // Apply new state
        button.textContent = message;
        button.className = originalClass + ` btn-${state}`;
        button.disabled = state === 'loading';
        
        // Reset after timeout (except for loading state)
        if (state !== 'loading') {
            setTimeout(() => {
                button.textContent = originalText;
                button.className = originalClass;
                button.disabled = false;
            }, this.config.copyTimeout);
        }
    }

    // ==========================================
    // UTILITY FUNCTIONS
    // ==========================================
    
    updateElement(selector, value) {
        const elements = document.querySelectorAll(selector);
        elements.forEach(el => {
            if (value !== undefined && value !== null) {
                el.textContent = value;
            }
        });
    }

    updateProgressBar(selector, percentage) {
        const bars = document.querySelectorAll(selector);
        bars.forEach(bar => {
            bar.style.width = `${Math.min(percentage, 100)}%`;
        });
    }

    smoothScrollTo(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    }

    getTimeAgo(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        
        if (seconds < 60) return 'just now';
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
        return `${Math.floor(seconds / 86400)}d ago`;
    }

    activateDebugMode() {
        this.state.debugMode = true;
        document.body.classList.add('debug-mode');
        
        this.showNotification('🔧 Debug mode activated! Advanced features unlocked.', 'info');
        
        // Add debug panel
        this.createDebugPanel();
    }

    createDebugPanel() {
        if (document.querySelector('.debug-panel')) return;
        
        const debugPanel = document.createElement('div');
        debugPanel.className = 'debug-panel';
        debugPanel.innerHTML = `
            <div class="debug-header">
                <h3>🔧 Debug Panel</h3>
                <button onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
            <div class="debug-content">
                <button onclick="window.CleanInteractive.forceDataUpdate()">Force Update</button>
                <button onclick="window.CleanInteractive.clearDataCache()">Clear Cache</button>
                <button onclick="console.log(window.CleanInteractive.state)">Log State</button>
                <button onclick="window.CleanInteractive.exportData()">Export Data</button>
            </div>
        `;
        
        document.body.appendChild(debugPanel);
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => notification.classList.add('show'), 100);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+K: Copy host address
            if (e.ctrlKey && e.key === 'k') {
                e.preventDefault();
                this.getHostAddress().then(address => {
                    navigator.clipboard.writeText(address);
                    this.showNotification('Host address copied!', 'success');
                });
            }
            
            // Ctrl+Shift+R: Force refresh
            if (e.ctrlKey && e.shiftKey && e.key === 'R') {
                e.preventDefault();
                this.forceDataUpdate();
                this.showNotification('Forcing data refresh...', 'info');
            }
            
            // Ctrl+Shift+D: Toggle debug mode
            if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                e.preventDefault();
                this.activateDebugMode();
            }
        });
    }

    setupPricingIntegration() {
        // Listen for pricing system events
        if (window.UnifiedPricing) {
            window.UnifiedPricing.subscribe((event, data) => {
                if (event === 'price-update') {
                    this.updatePricingDisplay();
                }
            });
        }
    }

    trackEvent(eventName, data = {}) {
        // Simple event tracking
        console.log(`📊 Event: ${eventName}`, data);
        
        // Send to analytics if available
        if (window.gtag) {
            window.gtag('event', eventName, data);
        }
    }

    async clearDataCache() {
        // Clear API cache
        const cacheUrls = [
            '/api/instance-count.php',
            '/api/host-info',
            '/api/pricing-data'
        ];
        
        if ('caches' in window) {
            const cache = await caches.open('evernode-api');
            await Promise.all(cacheUrls.map(url => cache.delete(url)));
        }
    }

    exportData() {
        const exportData = {
            timestamp: new Date().toISOString(),
            state: this.state,
            config: this.config
        };
        
        const blob = new Blob([JSON.stringify(exportData, null, 2)], {
            type: 'application/json'
        });
        
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `evernode-debug-${Date.now()}.json`;
        a.click();
        
        URL.revokeObjectURL(url);
    }

    useFallbackData() {
        // Use reasonable fallback data when APIs fail
        this.state.currentData = {
            ...this.state.currentData,
            total: 50,
            used: 15,
            available: 35,
            usage_percentage: 30,
            status: 'available',
            status_message: '✅ Ready for new deployments!',
            last_updated: new Date().toISOString()
        };
        
        this.updateUI();
        this.showNotification('Using fallback data - some APIs unavailable', 'warning');
    }
}

// ==========================================
// GLOBAL INITIALIZATION
// ==========================================

// Initialize when DOM is ready
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        window.CleanInteractive = new CleanInteractiveSystem();
        console.log('✨ Clean Interactive System ready');
    });
}

// CSS for notifications and debug panel
const style = document.createElement('style');
style.textContent = `
    /* Button States */
    .btn-success {
        background: #28a745 !important;
        border-color: #28a745 !important;
    }
    
    .btn-error {
        background: #dc3545 !important;
        border-color: #dc3545 !important;
    }
    
    .btn-loading {
        background: #6c757d !important;
        border-color: #6c757d !important;
        opacity: 0.8;
    }

    /* Notifications */
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: rgba(0, 0, 0, 0.9);
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        z-index: 10000;
        transform: translateX(400px);
        opacity: 0;
        transition: all 0.3s ease;
        max-width: 300px;
    }
    
    .notification.show {
        transform: translateX(0);
        opacity: 1;
    }
    
    .notification-success {
        background: rgba(40, 167, 69, 0.95) !important;
    }
    
    .notification-error {
        background: rgba(220, 53, 69, 0.95) !important;
    }
    
    .notification-warning {
        background: rgba(255, 193, 7, 0.95) !important;
        color: #000 !important;
    }
    
    .notification-info {
        background: rgba(23, 162, 184, 0.95) !important;
    }

    /* Debug Panel */
    .debug-panel {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: rgba(0, 0, 0, 0.95);
        border: 2px solid #00ff88;
        border-radius: 12px;
        padding: 0;
        z-index: 10000;
        min-width: 200px;
        backdrop-filter: blur(10px);
    }
    
    .debug-header {
        background: rgba(0, 255, 136, 0.2);
        padding: 10px 15px;
        border-bottom: 1px solid rgba(0, 255, 136, 0.3);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .debug-header h3 {
        margin: 0;
        color: #00ff88;
        font-size: 1rem;
    }
    
    .debug-header button {
        background: none;
        border: none;
        color: #00ff88;
        font-size: 1.2rem;
        cursor: pointer;
        padding: 0;
        width: 20px;
        height: 20px;
    }
    
    .debug-content {
        padding: 15px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .debug-content button {
        background: rgba(0, 255, 136, 0.1);
        border: 1px solid rgba(0, 255, 136, 0.3);
        color: #00ff88;
        padding: 8px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }
    
    .debug-content button:hover {
        background: rgba(0, 255, 136, 0.2);
        border-color: #00ff88;
    }

    /* Debug mode styling */
    body.debug-mode .availability-card,
    body.debug-mode .instance-availability {
        border: 2px dashed #00ff88 !important;
        position: relative;
    }
    
    body.debug-mode .availability-card::after,
    body.debug-mode .instance-availability::after {
        content: '🔧 DEBUG';
        position: absolute;
        top: 5px;
        right: 5px;
        background: #00ff88;
        color: #000;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: bold;
    }

    /* Connection status */
    .connection-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.9rem;
    }
    
    .connection-status.online {
        color: #28a745;
    }
    
    .connection-status.offline {
        color: #dc3545;
    }

    /* Price confidence indicator */
    .price-confidence {
        font-size: 0.8rem;
        opacity: 0.8;
    }

    /* Smooth transitions for all interactive elements */
    .btn, .copy-btn, .copy-app-btn {
        transition: all 0.3s ease;
    }
    
    .btn:hover, .copy-btn:hover, .copy-app-btn:hover {
        transform: translateY(-1px);
    }
    
    .btn:active, .copy-btn:active, .copy-app-btn:active {
        transform: translateY(0);
    }

    /* Progress bar animations */
    .availability-progress {
        transition: width 0.8s ease;
    }

    /* Loading animations */
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.6; }
        100% { opacity: 1; }
    }
    
    .btn-loading {
        animation: pulse 1.5s infinite;
    }
`;

if (typeof document !== 'undefined') {
    document.head.appendChild(style);
}

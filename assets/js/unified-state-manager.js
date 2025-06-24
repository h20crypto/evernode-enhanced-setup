// Enhanced Evernode Unified State Manager - Simple Access Control
class EnhancedEvernodeState {
    constructor() {
        this.currentRole = 'tenant';
        this.updateInterval = 600000; // 10 minutes
        this.apiTimeout = 8000;
        this.cacheExpiry = 600000; // 10 minutes cache
        this.init();
    }

    init() {
        console.log('🚀 Enhanced Evernode System - Simple Access Control');
        this.detectUserRole();
        this.loadCachedDataFirst();
        this.startSmartMonitoring();
    }

    detectUserRole() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('admin') === 'true') {
            this.promptAdminAccess();
            return;
        }

        if (localStorage.getItem('enhanced_evernode_admin') === 'true') {
            this.setRole('host_owner');
            return;
        }

        // Show admin shortcut on main page for hosts
        if (window.location.pathname === '/' || window.location.pathname === '/index.html') {
            document.addEventListener('keydown', (e) => {
                if (e.ctrlKey && e.shiftKey && e.key === 'A') {
                    this.promptAdminAccess();
                    e.preventDefault();
                }
            });
        }
    }

    promptAdminAccess() {
        const password = prompt('Host Owner Password:');
        // CHANGE THIS:
if (password === 'enhanced2024') {

// TO THIS:
            if (password === 'CHANGE_THIS_PASSWORD') {
            this.setRole('host_owner');
            alert('👑 Host Owner access granted!\n\nYou now have access to:\n• dApp Manager\n• Leaderboard\n• Commission Dashboard\n• Host Discovery');
        } else if (password !== null) {
            alert('❌ Access denied. Incorrect password.');
        }
    }

    setRole(role) {
        this.currentRole = role;
        
        if (role === 'host_owner') {
            localStorage.setItem('enhanced_evernode_admin', 'true');
            document.body.classList.add('admin-mode');
            this.showAdminBanner();
        } else {
            localStorage.removeItem('enhanced_evernode_admin');
            document.body.classList.remove('admin-mode');
        }
    }

    showAdminBanner() {
        const banner = document.createElement('div');
        banner.style.cssText = 'position:fixed;top:70px;left:0;right:0;background:#10b981;color:white;padding:12px;text-align:center;z-index:999;';
        banner.innerHTML = '👑 Host Owner Mode - Access to dApp Manager, Leaderboard & Commissions <button onclick="this.parentElement.remove()" style="float:right;background:none;border:none;color:white;cursor:pointer;">×</button>';
        document.body.insertBefore(banner, document.body.firstChild);
        setTimeout(() => banner.remove(), 5000);
    }

    loadCachedDataFirst() {
        console.log('📦 Loading cached data first...');
        
        try {
            const cachedData = localStorage.getItem('enhanced_evernode_cache');
            const cacheTime = localStorage.getItem('enhanced_evernode_cache_time');
            
            if (cachedData && cacheTime) {
                const data = JSON.parse(cachedData);
                const age = Date.now() - parseInt(cacheTime);
                
                console.log(`📦 Using cached data (${Math.round(age/1000/60)} minutes old)`);
                this.updateUI(data);
                this.showCacheIndicator(age);
            }
        } catch (error) {
            console.log('📦 No valid cached data, will load fresh');
        }
    }

    async startSmartMonitoring() {
        console.log('📡 Starting smart monitoring (10-minute intervals)...');
        await this.checkAndRefreshData();
        setInterval(() => {
            this.checkAndRefreshData();
        }, this.updateInterval);
    }

    async checkAndRefreshData() {
        const cacheTime = localStorage.getItem('enhanced_evernode_cache_time');
        const now = Date.now();
        
        if (!cacheTime || (now - parseInt(cacheTime)) > this.cacheExpiry) {
            console.log('🔄 Cache expired, fetching fresh data...');
            await this.fetchAndCacheData();
        } else {
            const minutesLeft = Math.round((this.cacheExpiry - (now - parseInt(cacheTime))) / 1000 / 60);
            console.log(`✅ Cache still fresh, next update in ${minutesLeft} minutes`);
        }
    }

    async fetchAndCacheData() {
        try {
            const response = await Promise.race([
                fetch('/api/router.php?endpoint=metrics'),
                new Promise((_, reject) => 
                    setTimeout(() => reject(new Error('timeout')), this.apiTimeout)
                )
            ]);
            
            if (response.ok) {
                const apiData = await response.json();
                if (apiData.success) {
                    const metrics = apiData.metrics || {
                        available_instances: 3,
                        response_time: '45ms',
                        network_rank: '#127',
                        uptime: '99.8%'
                    };
                    
                    localStorage.setItem('enhanced_evernode_cache', JSON.stringify(metrics));
                    localStorage.setItem('enhanced_evernode_cache_time', Date.now().toString());
                    
                    console.log('✅ Fresh data cached successfully');
                    this.updateUI(metrics);
                    this.showCacheIndicator(0);
                    return;
                }
            }
        } catch (error) {
            console.log('📡 API failed, using fallback data');
        }
        
        const fallbackData = {
            available_instances: 3,
            response_time: '45ms',
            network_rank: '#127',
            uptime: '99.8%'
        };
        
        this.updateUI(fallbackData);
    }

    updateUI(metrics) {
        const elements = {
            'available-instances': metrics.available_instances,
            'response-time': metrics.response_time,
            'network-rank': metrics.network_rank,
            'uptime': metrics.uptime
        };

        for (const [id, value] of Object.entries(elements)) {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
                element.style.color = '#00ff88';
            }
        }
    }

    showCacheIndicator(age) {
        const existing = document.getElementById('cache-indicator');
        if (existing) existing.remove();

        const indicator = document.createElement('div');
        indicator.id = 'cache-indicator';
        indicator.style.cssText = 'position:fixed;bottom:20px;right:20px;background:rgba(0,0,0,0.8);color:#00ff88;padding:8px 12px;border-radius:6px;font-size:0.8rem;z-index:1000;';
        
        if (age === 0) {
            indicator.textContent = '🔄 Fresh Data';
            setTimeout(() => indicator.remove(), 3000);
        } else {
            const minutes = Math.round(age / 1000 / 60);
            indicator.textContent = `📦 Cached (${minutes}m ago)`;
        }
        
        document.body.appendChild(indicator);
    }
}

// Global admin toggle function
window.toggleAdminMode = function() {
    if (!window.enhancedState) return;
    
    if (window.enhancedState.currentRole === 'host_owner') {
        localStorage.removeItem('enhanced_evernode_admin');
        window.location.reload();
    } else {
        window.enhancedState.promptAdminAccess();
    }
};

// Manual refresh function
window.refreshSystemData = function() {
    if (window.enhancedState) {
        localStorage.removeItem('enhanced_evernode_cache');
        localStorage.removeItem('enhanced_evernode_cache_time');
        window.enhancedState.fetchAndCacheData();
    }
};

document.addEventListener('DOMContentLoaded', function() {
    window.enhancedState = new EnhancedEvernodeState();
});

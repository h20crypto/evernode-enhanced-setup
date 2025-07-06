/**
 * Enhanced Evernode - Unified State Manager
 * Handles user roles, navigation state, and cross-page functionality
 */

class UnifiedStateManager {
    constructor() {
        this.currentRole = 'tenant'; // Default role
        this.validRoles = ['tenant', 'admin', 'demo'];
        this.navigationItems = [];
        
        this.init();
    }

    init() {
        console.log('🚀 Enhanced Evernode State Manager initializing...');
        
        // Initialize role from localStorage or default
        this.loadUserRole();
        
        // Set up navigation
        this.initializeNavigation();
        
        // Apply role-based visibility
        this.applyRoleVisibility();
        
        // Set up event listeners
        this.setupEventListeners();
        
        // Mark current page as active
        this.setActiveNavigation();
        
        console.log(`✅ State Manager ready - Role: ${this.currentRole}`);
    }

    /**
     * Load user role from localStorage or detect from URL/context
     */
    loadUserRole() {
        try {
            const saved = localStorage.getItem('evernode_user_role');
            const urlRole = this.detectRoleFromURL();
            
            if (saved && this.validRoles.includes(saved)) {
                this.currentRole = saved;
            } else if (urlRole) {
                this.currentRole = urlRole;
                this.saveUserRole();
            }
            
            console.log(`📋 User role loaded: ${this.currentRole}`);
            
        } catch (error) {
            console.warn('Failed to load user role from storage:', error);
            this.currentRole = 'tenant'; // Fallback
        }
    }

    /**
     * Detect role from current URL or context
     */
    detectRoleFromURL() {
        const path = window.location.pathname;
        const hostname = window.location.hostname;
        
        // Admin detection patterns
        if (path.includes('/monitoring') || 
            path.includes('/earnings') || 
            path.includes('/admin') ||
            hostname.includes('admin') ||
            path.includes('/leaderboard')) {
            return 'admin';
        }
        
        // Demo detection patterns  
        if (path.includes('/demo') || 
            hostname.includes('demo') ||
            path.includes('/preview')) {
            return 'demo';
        }
        
        // Default to tenant
        return 'tenant';
    }

    /**
     * Save current role to localStorage
     */
    saveUserRole() {
        try {
            localStorage.setItem('evernode_user_role', this.currentRole);
        } catch (error) {
            console.warn('Failed to save user role:', error);
        }
    }

    /**
     * Switch user role and update UI
     */
    switchRole(newRole) {
        if (!this.validRoles.includes(newRole)) {
            console.error('Invalid role:', newRole);
            return false;
        }

        const oldRole = this.currentRole;
        this.currentRole = newRole;
        this.saveUserRole();
        
        console.log(`🔄 Role switched: ${oldRole} → ${newRole}`);
        
        // Update UI
        this.applyRoleVisibility();
        this.updateRoleIndicator();
        
        // Trigger role change event
        this.triggerRoleChangeEvent(oldRole, newRole);
        
        return true;
    }

    /**
     * Apply role-based visibility to elements
     */
    applyRoleVisibility() {
        // Hide all role-specific elements first
        document.querySelectorAll('.admin-only, .tenant-only, .demo-only').forEach(el => {
            el.style.display = 'none';
        });

        // Show elements for current role
        const roleClass = `.${this.currentRole}-only`;
        document.querySelectorAll(roleClass).forEach(el => {
            el.style.display = el.dataset.originalDisplay || 'block';
        });

        // Special handling for navigation links
        this.updateNavigationVisibility();
        
        console.log(`👁️ Applied visibility for role: ${this.currentRole}`);
    }

    /**
     * Update navigation link visibility based on role
     */
    updateNavigationVisibility() {
        const navLinks = document.querySelectorAll('.nav-link');
        
        navLinks.forEach(link => {
            let shouldShow = true;
            
            // Check role-specific classes
            if (link.classList.contains('admin-only') && this.currentRole !== 'admin') {
                shouldShow = false;
            }
            if (link.classList.contains('tenant-only') && this.currentRole !== 'tenant') {
                shouldShow = false;
            }
            if (link.classList.contains('demo-only') && this.currentRole !== 'demo') {
                shouldShow = false;
            }
            
            // Show/hide the link
            link.style.display = shouldShow ? 'block' : 'none';
        });
    }

    /**
     * Initialize navigation system
     */
    initializeNavigation() {
        this.navigationItems = [
            { 
                label: 'Dashboard', 
                href: '/', 
                icon: '🏠',
                roles: ['tenant', 'admin', 'demo'],
                active: ['/', '/index.html']
            },
            { 
                label: 'Discovery', 
                href: '/host-discovery.html', 
                icon: '🔍',
                roles: ['tenant', 'admin', 'demo'],
                active: ['/host-discovery.html', '/discovery']
            },
            { 
                label: 'ROI Calculator', 
                href: '/cluster/roi-calculator.html', 
                icon: '💰',
                roles: ['tenant'],
                active: ['/cluster/roi-calculator.html']
            },
            { 
                label: 'Monitoring', 
                href: '/monitoring-dashboard.html', 
                icon: '📊',
                roles: ['admin'],
                active: ['/monitoring-dashboard.html', '/monitoring']
            },
            { 
                label: 'Earnings', 
                href: '/my-earnings.html', 
                icon: '💰',
                roles: ['admin'],
                active: ['/my-earnings.html', '/earnings']
            },
            { 
                label: 'Leaderboard', 
                href: '/leaderboard.html', 
                icon: '🏆',
                roles: ['admin'],
                active: ['/leaderboard.html']
            },
            { 
                label: 'Cluster Manager', 
                href: '/cluster/dapp-manager.html', 
                icon: '🏗️',
                roles: ['admin'],
                active: ['/cluster/dapp-manager.html', '/cluster/manager']
            },
            { 
                label: 'Premium', 
                href: '/cluster/paywall.html', 
                icon: '💎',
                roles: ['tenant', 'admin', 'demo'],
                active: ['/cluster/paywall.html', '/premium'],
                special: true
            }
        ];
    }

    /**
     * Set active navigation based on current page
     */
    setActiveNavigation() {
        const currentPath = window.location.pathname;
        
        // Remove existing active classes
        document.querySelectorAll('.nav-link.active').forEach(link => {
            link.classList.remove('active');
        });

        // Find and mark active navigation item
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            
            // Exact match
            if (href === currentPath) {
                link.classList.add('active');
                return;
            }
            
            // Pattern matching for different page variants
            if (currentPath === '/' && (href === '/' || href === '/index.html')) {
                link.classList.add('active');
            } else if (currentPath.includes('discovery') && href.includes('discovery')) {
                link.classList.add('active');
            } else if (currentPath.includes('monitoring') && href.includes('monitoring')) {
                link.classList.add('active');
            } else if (currentPath.includes('earnings') && href.includes('earnings')) {
                link.classList.add('active');
            } else if (currentPath.includes('cluster') && href.includes('cluster')) {
                link.classList.add('active');
            }
        });
    }

    /**
     * Update role indicator in navigation
     */
    updateRoleIndicator() {
        const indicator = document.getElementById('role-indicator');
        if (!indicator) return;

        const roleLabels = {
            'tenant': 'Tenant Mode',
            'admin': 'Host Admin',
            'demo': 'Demo Mode'
        };

        const roleClasses = {
            'tenant': '',
            'admin': 'admin-mode',
            'demo': 'demo-mode'
        };

        indicator.textContent = roleLabels[this.currentRole] || 'Unknown';
        
        // Update classes
        indicator.className = 'role-indicator';
        if (roleClasses[this.currentRole]) {
            indicator.classList.add(roleClasses[this.currentRole]);
        }
    }

    /**
     * Set up global event listeners
     */
    setupEventListeners() {
        // Role toggle button
        const toggleBtn = document.querySelector('.nav-btn[onclick*="toggleAdminMode"]');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleAdminMode();
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            this.handleKeyboardShortcuts(e);
        });

        // Page visibility change
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.refresh();
            }
        });

        // Storage changes (for multi-tab sync)
        window.addEventListener('storage', (e) => {
            if (e.key === 'evernode_user_role' && e.newValue !== this.currentRole) {
                this.currentRole = e.newValue || 'tenant';
                this.applyRoleVisibility();
                this.updateRoleIndicator();
            }
        });
    }

    /**
     * Handle keyboard shortcuts
     */
    handleKeyboardShortcuts(e) {
        // Ctrl+Shift+A = Toggle Admin Mode
        if (e.ctrlKey && e.shiftKey && e.key === 'A') {
            e.preventDefault();
            this.toggleAdminMode();
        }
        
        // Ctrl+Shift+D = Toggle Demo Mode
        if (e.ctrlKey && e.shiftKey && e.key === 'D') {
            e.preventDefault();
            this.toggleDemoMode();
        }
        
        // Ctrl+Shift+T = Tenant Mode
        if (e.ctrlKey && e.shiftKey && e.key === 'T') {
            e.preventDefault();
            this.switchRole('tenant');
        }
    }

    /**
     * Toggle between tenant and admin modes
     */
    toggleAdminMode() {
        if (this.currentRole === 'admin') {
            this.switchRole('tenant');
        } else {
            this.switchRole('admin');
        }
    }

    /**
     * Toggle demo mode
     */
    toggleDemoMode() {
        if (this.currentRole === 'demo') {
            this.switchRole('tenant');
        } else {
            this.switchRole('demo');
        }
    }

    /**
     * Trigger custom role change event
     */
    triggerRoleChangeEvent(oldRole, newRole) {
        const event = new CustomEvent('roleChanged', {
            detail: { oldRole, newRole, timestamp: Date.now() }
        });
        document.dispatchEvent(event);
    }

    /**
     * Refresh the state manager
     */
    refresh() {
        this.applyRoleVisibility();
        this.setActiveNavigation();
        this.updateRoleIndicator();
    }

    /**
     * Get current user role
     */
    getCurrentRole() {
        return this.currentRole;
    }

    /**
     * Check if current user has specific role
     */
    hasRole(role) {
        return this.currentRole === role;
    }

    /**
     * Check if current user can access admin features
     */
    isAdmin() {
        return this.currentRole === 'admin';
    }

    /**
     * Check if current user is in demo mode
     */
    isDemo() {
        return this.currentRole === 'demo';
    }

    /**
     * Get navigation items for current role
     */
    getNavigationItems() {
        return this.navigationItems.filter(item => 
            item.roles.includes(this.currentRole)
        );
    }
}

// Global functions for backward compatibility
function detectUserRole() {
    if (window.stateManager) {
        window.stateManager.loadUserRole();
        window.stateManager.applyRoleVisibility();
    }
}

function toggleAdminMode() {
    if (window.stateManager) {
        window.stateManager.toggleAdminMode();
    }
}

function switchRole(role) {
    if (window.stateManager) {
        return window.stateManager.switchRole(role);
    }
    return false;
}

// Initialize state manager when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Create global state manager instance
    window.stateManager = new UnifiedStateManager();
    
    // Add body class for styling
    document.body.classList.add('has-nav');
    
    // Legacy compatibility
    window.detectUserRole = detectUserRole;
    window.toggleAdminMode = toggleAdminMode;
    window.switchRole = switchRole;
    
    console.log('✅ Enhanced Evernode navigation system ready');
});

// Enhanced Commission Manager Integration
class CommissionManager {
    constructor() {
        this.hostId = window.location.hostname;
        this.referralCode = this.generateReferralCode();
        this.paymentServer = 'https://payments.evrdirect.info';
        this.apiServer = 'https://api.evrdirect.info';
        this.commissionRate = 0.20; // 20% commission rate
        
        // Initialize when state manager is ready
        document.addEventListener('roleChanged', (e) => {
            this.onRoleChanged(e.detail);
        });
    }

    generateReferralCode() {
        const hostDomain = window.location.hostname;
        return btoa(hostDomain).substr(0, 8).toUpperCase();
    }

    onRoleChanged(details) {
        if (details.newRole === 'admin') {
            this.initializeCommissionTracking();
        }
    }

    initializeCommissionTracking() {
        this.setupReferralLinks();
        this.loadCommissionStats();
    }

    setupReferralLinks() {
        const referralParam = `?ref=${this.referralCode}&host=${this.hostId}&source=enhanced_host`;
        const fullReferralUrl = this.paymentServer + referralParam;
        
        // Update all premium links with referral tracking
        document.querySelectorAll('.premium-cluster-link, a[href*="premium"], a[href*="paywall"]').forEach(link => {
            if (!link.href.includes('ref=')) {
                link.href = fullReferralUrl;
                link.target = '_blank';
                
                // Add tracking
                link.addEventListener('click', () => {
                    this.trackReferralClick(link);
                });
            }
        });
    }

    async loadCommissionStats() {
        try {
            const response = await fetch(`${this.apiServer}/api/host-earnings/${this.hostId}`);
            const data = await response.json();
            
            if (data.success && window.stateManager?.isAdmin()) {
                this.updateCommissionDisplays(data.earnings);
            }
        } catch (error) {
            console.log('Commission stats unavailable:', error.message);
        }
    }

    updateCommissionDisplays(stats) {
        // Update various commission displays across pages
        const elements = {
            'total-commissions': `$${stats.total_earned?.toFixed(2) || '0.00'}`,
            'referral-count': stats.referral_count || 0,
            'monthly-earnings': `$${stats.monthly_earnings?.toFixed(2) || '0.00'}`,
            'conversion-rate': `${stats.conversion_rate || 0}%`
        };

        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value;
        });
    }

    trackReferralClick(link) {
        const source = link.getAttribute('data-commission-source') || 'navigation';
        
        fetch('/api/track-referral.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                host_id: this.hostId,
                referral_code: this.referralCode,
                action: 'click',
                source: source,
                commission_rate: this.commissionRate,
                timestamp: Date.now()
            })
        }).catch(err => console.log('Referral tracking failed:', err));
    }
}

// Initialize commission manager
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.hostname !== 'localhost') {
        window.commissionManager = new CommissionManager();
    }
});

// Export for external use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { UnifiedStateManager, CommissionManager };
}

// Original navigation structure
{ 
    label: 'ROI Calculator', 
    href: '/cluster/roi-calculator.html', 
    icon: '💰',
    roles: ['tenant'],
    active: ['/cluster/roi-calculator.html']
}

// =============================================================================
// 🔧 UNIFIED ADMIN SYSTEM - Apply to ALL Enhanced Evernode Pages
// =============================================================================
// This fixes admin access consistency across all pages

// 1. UPDATE: /var/www/html/assets/js/unified-state-manager.js
// Replace entire file with this unified admin system:

class EnhancedEvernodeState {
    constructor() {
        this.adminPassword = 'fossil'; // Unified password for all pages
        this.isAdmin = false;
        this.init();
    }

    init() {
        console.log('🔐 Unified Admin System initializing...');
        this.detectUserRole();
        this.setupKeyboardShortcuts();
        this.setupAdminIndicator();
        
        // Auto-check admin state every 2 seconds for consistency
        setInterval(() => {
            this.syncAdminState();
        }, 2000);
    }

    detectUserRole() {
        const urlParams = new URLSearchParams(window.location.search);
        
        // Check URL parameter first
        if (urlParams.get('admin') === 'true') {
            this.promptAdminAccess();
            return;
        }
        
        // Check localStorage
        if (localStorage.getItem('enhanced_evernode_admin') === 'true') {
            this.setRole('host_owner');
            return;
        }
        
        // Default to tenant
        this.setRole('tenant');
    }

    promptAdminAccess() {
        const password = prompt('🔐 Host Owner Password:');
        if (password === this.adminPassword) {
            localStorage.setItem('enhanced_evernode_admin', 'true');
            this.setRole('host_owner');
            this.showNotification('👑 Host Owner access granted!');
            
            // Update URL to remove admin parameter after successful login
            if (window.history && window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('admin');
                window.history.replaceState({}, document.title, url.toString());
            }
        } else if (password !== null) {
            this.showNotification('❌ Access denied. Incorrect password.');
        }
    }

    setRole(role) {
        if (role === 'host_owner') {
            this.isAdmin = true;
            this.enableAdminMode();
        } else {
            this.isAdmin = false;
            this.enableTenantMode();
        }
    }

    enableAdminMode() {
        console.log('👑 Admin mode enabled');
        
        // Update body classes
        document.body.classList.add('admin-mode');
        document.body.classList.remove('tenant-mode');
        
        // Show/hide elements
        document.querySelectorAll('.admin-only').forEach(el => {
            el.style.display = 'block';
        });
        document.querySelectorAll('.tenant-only').forEach(el => {
            el.style.display = 'none';
        });
        
        // Update role indicator
        this.updateRoleIndicator('Host Admin', '#10b981');
        
        // Update role banner if exists
        const roleBanner = document.getElementById('role-banner');
        if (roleBanner) {
            roleBanner.textContent = '👑 Host Admin Mode - Full Access to Management Features + Commission Tracking';
            roleBanner.style.display = 'block';
            roleBanner.style.background = 'linear-gradient(135deg, #10b981, #00ff88)';
            roleBanner.style.color = '#000';
        }
        
        // Update navigation active states
        this.updateNavigationForAdmin();
        
        // Show admin indicator
        this.showAdminIndicator();
    }

    enableTenantMode() {
        console.log('👤 Tenant mode enabled');
        
        // Update body classes
        document.body.classList.remove('admin-mode');
        document.body.classList.add('tenant-mode');
        
        // Show/hide elements
        document.querySelectorAll('.admin-only').forEach(el => {
            el.style.display = 'none';
        });
        document.querySelectorAll('.tenant-only').forEach(el => {
            el.style.display = 'block';
        });
        
        // Update role indicator
        this.updateRoleIndicator('Tenant Mode', '#ffffff');
        
        // Hide role banner
        const roleBanner = document.getElementById('role-banner');
        if (roleBanner) {
            roleBanner.style.display = 'none';
        }
        
        // Update navigation for tenant
        this.updateNavigationForTenant();
        
        // Hide admin indicator
        this.hideAdminIndicator();
    }

    updateRoleIndicator(text, color) {
        const roleIndicator = document.getElementById('role-indicator');
        if (roleIndicator) {
            roleIndicator.textContent = text;
            roleIndicator.style.color = color;
            roleIndicator.style.background = color === '#10b981' ? 
                'rgba(16, 185, 129, 0.2)' : 'rgba(255, 255, 255, 0.1)';
        }
    }

    updateNavigationForAdmin() {
        // Update nav links to show admin sections
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            if (link.href.includes('monitoring-dashboard') || 
                link.href.includes('my-earnings') || 
                link.href.includes('cluster/dapp-manager')) {
                link.style.display = 'inline-block';
            }
        });
    }

    updateNavigationForTenant() {
        // Hide admin-only nav links for tenants
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            if (link.href.includes('monitoring-dashboard') || 
                link.href.includes('my-earnings') || 
                link.href.includes('cluster/dapp-manager')) {
                link.style.display = 'none';
            }
        });
    }

    showAdminIndicator() {
        // Remove existing indicator
        const existing = document.getElementById('admin-indicator');
        if (existing) existing.remove();
        
        // Create new admin indicator
        const indicator = document.createElement('div');
        indicator.id = 'admin-indicator';
        indicator.innerHTML = '👑 Admin Mode';
        indicator.style.cssText = `
            position: fixed;
            top: 10px;
            right: 10px;
            background: linear-gradient(135deg, #00ff88, #10b981);
            color: #000;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            z-index: 9999;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,255,136,0.3);
            font-size: 0.9rem;
            animation: slideIn 0.3s ease;
        `;
        indicator.onclick = () => this.toggleAdminMode();
        document.body.appendChild(indicator);
    }

    hideAdminIndicator() {
        const indicator = document.getElementById('admin-indicator');
        if (indicator) indicator.remove();
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+Shift+A for admin toggle
            if (e.ctrlKey && e.shiftKey && e.key === 'A') {
                e.preventDefault();
                this.toggleAdminMode();
            }
            
            // Ctrl+Shift+L for logout (admin only)
            if (e.ctrlKey && e.shiftKey && e.key === 'L' && this.isAdmin) {
                e.preventDefault();
                this.logoutAdmin();
            }
        });
    }

    setupAdminIndicator() {
        // Add hidden admin access link in footer
        setTimeout(() => {
            if (!document.getElementById('hidden-admin-link')) {
                const adminLink = document.createElement('div');
                adminLink.id = 'hidden-admin-link';
                adminLink.innerHTML = '👑';
                adminLink.style.cssText = `
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    font-size: 1.2rem;
                    opacity: 0.3;
                    cursor: pointer;
                    z-index: 1000;
                    transition: opacity 0.3s ease;
                `;
                adminLink.onclick = () => this.toggleAdminMode();
                adminLink.onmouseover = () => adminLink.style.opacity = '0.7';
                adminLink.onmouseout = () => adminLink.style.opacity = '0.3';
                document.body.appendChild(adminLink);
            }
        }, 1000);
    }

    toggleAdminMode() {
        if (this.isAdmin) {
            this.logoutAdmin();
        } else {
            this.promptAdminAccess();
        }
    }

    logoutAdmin() {
        localStorage.removeItem('enhanced_evernode_admin');
        this.setRole('tenant');
        this.showNotification('👤 Switched to tenant view');
    }

    syncAdminState() {
        // Check if admin state changed in another tab
        const storedAdminState = localStorage.getItem('enhanced_evernode_admin') === 'true';
        if (storedAdminState !== this.isAdmin) {
            if (storedAdminState) {
                this.setRole('host_owner');
            } else {
                this.setRole('tenant');
            }
        }
    }

    showNotification(message) {
        // Remove existing notifications
        const existing = document.querySelectorAll('.enhanced-notification');
        existing.forEach(el => el.remove());
        
        const notification = document.createElement('div');
        notification.className = 'enhanced-notification';
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            z-index: 10000;
            font-weight: bold;
            border: 1px solid #00ff88;
            animation: slideIn 0.3s ease;
        `;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
}

// =============================================================================
// 2. GLOBAL FUNCTIONS - Available on all pages
// =============================================================================

function toggleAdminMode() {
    if (window.enhancedState) {
        window.enhancedState.toggleAdminMode();
    }
}

function setAdminMode() {
    if (window.enhancedState) {
        localStorage.setItem('enhanced_evernode_admin', 'true');
        window.enhancedState.setRole('host_owner');
    }
}

function setTenantMode() {
    if (window.enhancedState) {
        localStorage.removeItem('enhanced_evernode_admin');
        window.enhancedState.setRole('tenant');
    }
}

// =============================================================================
// 3. AUTO-INITIALIZE - Runs on every page
// =============================================================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Enhanced Evernode Unified Admin System loading...');
    
    // Initialize unified state manager
    window.enhancedState = new EnhancedEvernodeState();
    
    // Add CSS animations
    if (!document.getElementById('enhanced-admin-styles')) {
        const styles = document.createElement('style');
        styles.id = 'enhanced-admin-styles';
        styles.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            .admin-mode .admin-only { display: block !important; }
            .admin-mode .tenant-only { display: none !important; }
            .tenant-mode .admin-only { display: none !important; }
            .tenant-mode .tenant-only { display: block !important; }
        `;
        document.head.appendChild(styles);
    }
    
    console.log('✅ Enhanced Evernode Unified Admin System ready');
});

// =============================================================================
// 4. ADMIN DEBUGGING - Console helpers
// =============================================================================

window.adminDebug = {
    getAdminState: () => {
        return {
            isAdmin: window.enhancedState?.isAdmin,
            localStorage: localStorage.getItem('enhanced_evernode_admin'),
            password: window.enhancedState?.adminPassword
        };
    },
    forceAdmin: () => {
        localStorage.setItem('enhanced_evernode_admin', 'true');
        if (window.enhancedState) window.enhancedState.setRole('host_owner');
        console.log('👑 Admin mode forced');
    },
    forceTenant: () => {
        localStorage.removeItem('enhanced_evernode_admin');
        if (window.enhancedState) window.enhancedState.setRole('tenant');
        console.log('👤 Tenant mode forced');
    }
};

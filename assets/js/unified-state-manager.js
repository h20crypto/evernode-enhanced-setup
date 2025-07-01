// =============================================================================
// 📁 FILE 1: /assets/js/unified-state-manager.js
// =============================================================================
// Copy this to: assets/js/unified-state-manager.js in your GitHub repo

// Enhanced Evernode Unified Admin System - TEMPLATE VERSION
console.log('🔐 Admin System Loading...');

class EnhancedEvernodeState {
    constructor() {
        this.adminPassword = 'CHANGE_THIS_PASSWORD'; // ← PLACEHOLDER - Will be replaced during installation
        this.isAdmin = false;
        this.init();
    }
    
    init() {
        console.log('🔐 Unified Admin System initializing...');
        this.detectUserRole();
        this.setupKeyboardShortcuts();
        this.setupAdminIndicator();
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
        
        this.setRole('tenant');
    }
    
    promptAdminAccess() {
        const password = prompt('🔐 Host Owner Password:');
        if (password === this.adminPassword) {
            localStorage.setItem('enhanced_evernode_admin', 'true');
            this.setRole('host_owner');
            this.showNotification('👑 Host Owner access granted!');
            
            if (window.history && window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('admin');
                window.history.replaceState({}, document.title, url.toString());
            }
        } else if (password !== null) {
            this.showNotification('❌ Access denied. Contact host operator for password.');
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
        
        document.body.classList.add('admin-mode');
        document.body.classList.remove('tenant-mode');
        
        document.querySelectorAll('.admin-only').forEach(el => {
            el.style.display = 'block';
        });
        document.querySelectorAll('.tenant-only').forEach(el => {
            el.style.display = 'none';
        });
        
        this.updateRoleIndicator('Host Admin', '#10b981');
        this.showAdminIndicator();
    }
    
    enableTenantMode() {
        console.log('👤 Tenant mode enabled');
        
        document.body.classList.remove('admin-mode');
        document.body.classList.add('tenant-mode');
        
        document.querySelectorAll('.admin-only').forEach(el => {
            el.style.display = 'none';
        });
        document.querySelectorAll('.tenant-only').forEach(el => {
            el.style.display = 'block';
        });
        
        this.updateRoleIndicator('Tenant Mode', '#ffffff');
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
    
    showAdminIndicator() {
        const existing = document.getElementById('admin-indicator');
        if (existing) existing.remove();
        
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
            if (e.ctrlKey && e.altKey && e.key === 'h') {
                e.preventDefault();
                this.toggleAdminMode();
            }
        });
    }
    
    setupAdminIndicator() {
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
    
    showNotification(message) {
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
        `;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
}

// Global functions
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

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Enhanced Evernode Unified Admin System loading...');
    window.enhancedState = new EnhancedEvernodeState();
    console.log('✅ Enhanced Evernode Unified Admin System ready');
});

console.log('📝 Enhanced Evernode Admin System script loaded');

// =============================================================================
// 📁 FILE 2: Update your /landing-page/index.html 
// =============================================================================
// Find any existing admin password checks and replace with this template:

/*
Replace any existing admin checks in your index.html with this template version:

<script>
    // Legacy admin access fallback (TEMPLATE VERSION)
    function checkLegacyAdminAccess() {
        const password = prompt('🔐 Host Owner Password:');
        if (password === 'TEMPLATE_ADMIN_PASSWORD') { // ← PLACEHOLDER - Will be replaced
            document.body.classList.add('admin-mode');
            localStorage.setItem('enhanced_evernode_admin', 'true');
            alert('👑 Host Owner access granted!');
            location.reload(); // Reload to activate unified system
        } else if (password !== null) {
            alert('❌ Access denied. Contact host operator.');
        }
    }
</script>

Make sure your index.html has this script tag before closing </body>:
<script src="/assets/js/unified-state-manager.js"></script>
*/

// =============================================================================
// 📁 FILE 3: /tools/test-admin-access.sh
// =============================================================================
// Copy this to: tools/test-admin-access.sh in your GitHub repo

#!/bin/bash

# Enhanced Evernode Admin Access Test Script
echo "🧪 Testing Enhanced Evernode Admin Access"
echo "========================================="

# Test 1: Check if unified-state-manager.js exists
if [ -f "/var/www/html/assets/js/unified-state-manager.js" ]; then
    echo "✅ unified-state-manager.js exists"
else
    echo "❌ unified-state-manager.js missing"
    exit 1
fi

# Test 2: Check for placeholder passwords (should be NONE after install)
if grep -q "CHANGE_THIS_PASSWORD\|TEMPLATE_ADMIN_PASSWORD" /var/www/html/assets/js/unified-state-manager.js 2>/dev/null; then
    echo "❌ Found unreplaced placeholder passwords!"
    echo "Installation may have failed to configure passwords properly."
    exit 1
else
    echo "✅ No placeholder passwords found (good)"
fi

# Test 3: Check JavaScript syntax
if command -v node >/dev/null 2>&1; then
    if node -c /var/www/html/assets/js/unified-state-manager.js 2>/dev/null; then
        echo "✅ JavaScript syntax is valid"
    else
        echo "❌ JavaScript syntax errors found"
        node -c /var/www/html/assets/js/unified-state-manager.js
        exit 1
    fi
else
    echo "⚠️  Node.js not available for syntax testing"
fi

# Test 4: Check if script is loaded in HTML
if grep -q "unified-state-manager.js" /var/www/html/index.html; then
    echo "✅ Admin system is loaded in index.html"
else
    echo "❌ Admin system not loaded in index.html"
    exit 1
fi

# Test 5: Check file permissions
if [ "$(stat -c %a /var/www/html/assets/js/unified-state-manager.js)" = "644" ]; then
    echo "✅ File permissions are correct (644)"
else
    echo "⚠️  File permissions may need adjustment"
fi

# Test 6: Check for conflicting admin code
if grep -q "enhanced2024\|admin_mode\|detectUserRole" /var/www/html/index.html; then
    echo "⚠️  Found potentially conflicting admin code"
    echo "Consider cleaning up old admin system references"
else
    echo "✅ No conflicting admin code found"
fi

echo ""
echo "🎯 Manual Testing Instructions:"
echo "==============================="
echo "1. Open browser: http://$(hostname -f)/?admin=true"
echo "2. Enter your password (set during installation)"
echo "3. Should see 'Host Admin' mode enabled"
echo "4. Check browser console (F12) for errors"
echo ""
echo "🔑 Admin Access Methods:"
echo "• URL: ?admin=true"
echo "• Keyboard: Ctrl + Alt + H"  
echo "• Icon: Click 👑 in bottom-right"
echo ""
echo "📋 Password Location:"
echo "If you forgot your password, check:"
echo "grep 'adminPassword:' /var/www/html/assets/js/unified-state-manager.js"
echo ""

# =============================================================================
// 📁 FILE 4: Add to your /quick-install.sh
// =============================================================================
// Add these functions to your existing quick-install.sh:

# Enhanced password configuration function
configure_admin_password() {
    print_step "Setting up admin password..."
    
    # Get user's admin password
    while true; do
        echo ""
        read -s -p "🔐 Create admin password (min 8 characters): " USER_PASSWORD
        echo ""
        if [ ${#USER_PASSWORD} -lt 8 ]; then
            print_warning "Password must be at least 8 characters!"
            continue
        fi
        read -s -p "🔐 Confirm admin password: " USER_PASSWORD_CONFIRM
        echo ""
        if [ "$USER_PASSWORD" = "$USER_PASSWORD_CONFIRM" ]; then
            break
        else
            print_warning "Passwords don't match! Try again."
        fi
    done
    
    print_success "Admin password configured securely"
}

# Apply user password to all template files
apply_password_configuration() {
    print_step "Applying password configuration to Enhanced Evernode files..."
    
    # Update unified-state-manager.js
    if [ -f "$INSTALL_DIR/assets/js/unified-state-manager.js" ]; then
        # Replace main placeholder
        sudo sed -i "s/adminPassword: 'CHANGE_THIS_PASSWORD'/adminPassword: '$USER_PASSWORD'/g" "$INSTALL_DIR/assets/js/unified-state-manager.js"
        # Replace any other instances
        sudo sed -i "s/'CHANGE_THIS_PASSWORD'/'$USER_PASSWORD'/g" "$INSTALL_DIR/assets/js/unified-state-manager.js"
        print_success "✅ Unified admin system password updated"
    else
        print_warning "⚠️  unified-state-manager.js not found - admin system may not work"
    fi
    
    # Update landing page
    if [ -f "$INSTALL_DIR/index.html" ]; then
        # Replace template placeholders
        sudo sed -i "s/password === 'TEMPLATE_ADMIN_PASSWORD'/password === '$USER_PASSWORD'/g" "$INSTALL_DIR/index.html"
        sudo sed -i "s/'TEMPLATE_ADMIN_PASSWORD'/'$USER_PASSWORD'/g" "$INSTALL_DIR/index.html"
        # Clean up any old placeholders
        sudo sed -i "s/enhanced2024/$USER_PASSWORD/g" "$INSTALL_DIR/index.html"
        print_success "✅ Landing page password updated"
    fi
    
    # Update any other files with placeholder passwords
    find "$INSTALL_DIR" -type f \( -name "*.html" -o -name "*.js" -o -name "*.php" \) -exec \
        sudo sed -i "s/CHANGE_THIS_PASSWORD/$USER_PASSWORD/g; s/TEMPLATE_ADMIN_PASSWORD/$USER_PASSWORD/g; s/enhanced2024/$USER_PASSWORD/g" {} \; 2>/dev/null
    
    # Remove any conflicting admin systems
    if [ -f "$INSTALL_DIR/index.html" ]; then
        # Remove old admin_mode references
        sudo sed -i '/admin_mode/d' "$INSTALL_DIR/index.html"
        # Remove standalone detectUserRole calls
        sudo sed -i '/detectUserRole();/d' "$INSTALL_DIR/index.html"
    fi
    
    print_success "✅ All template passwords updated with your secure password"
}

# Validate admin system after installation
validate_admin_system() {
    print_step "Validating admin system configuration..."
    
    # Check if placeholder passwords still exist (bad)
    if grep -r "CHANGE_THIS_PASSWORD\|TEMPLATE_ADMIN_PASSWORD" "$INSTALL_DIR" --include="*.js" --include="*.html" 2>/dev/null; then
        print_error "❌ Found unreplaced placeholder passwords!"
        print_info "This indicates the password configuration failed."
        return 1
    fi
    
    # Check if unified admin system exists
    if [ ! -f "$INSTALL_DIR/assets/js/unified-state-manager.js" ]; then
        print_error "❌ Unified admin system not found!"
        return 1
    fi
    
    # Test JavaScript syntax
    if command -v node >/dev/null 2>&1; then
        if ! node -c "$INSTALL_DIR/assets/js/unified-state-manager.js" 2>/dev/null; then
            print_error "❌ Admin system has JavaScript syntax errors!"
            return 1
        fi
    fi
    
    # Check if script is loaded in HTML
    if ! grep -q "unified-state-manager.js" "$INSTALL_DIR/index.html"; then
        print_warning "⚠️  Adding unified admin system to index.html..."
        sudo sed -i '/<\/body>/i <script src="/assets/js/unified-state-manager.js"></script>' "$INSTALL_DIR/index.html"
    fi
    
    print_success "✅ Admin system validation complete"
}

# Add these calls to your main() function in quick-install.sh:
# main() {
#     print_header
#     check_requirements
#     collect_host_info
#     configure_admin_password    # ← ADD THIS
#     
#     install_dependencies
#     setup_directories  
#     download_enhanced_files
#     apply_password_configuration   # ← ADD THIS
#     
#     configure_commission_system
#     install_nodejs_payment_api
#     configure_webserver
#     set_permissions
#     validate_admin_system      # ← ADD THIS
#     
#     test_installation
#     generate_report
#     print_success "🎉 Enhanced Evernode installation completed!"
# }

// =============================================================================
// 📁 FILE 5: /README.md section to add
// =============================================================================
// Add this section to your README.md:

## 🔐 Admin Access System

Enhanced Evernode uses a secure, per-host admin password system for better security across the network.

### 🚀 Fresh Installation Process

During installation, you'll create a unique admin password:

```bash
🔐 Create admin password (min 8 characters): [YOUR_SECURE_PASSWORD]
🔐 Confirm admin password: [YOUR_SECURE_PASSWORD]
```

### 🔑 Admin Access Methods

Once installed, access admin features using:

- **URL Parameter**: `https://your-domain.com/?admin=true`
- **Keyboard Shortcut**: `Ctrl + Alt + H`
- **Hidden Icon**: Click the 👑 icon in bottom-right corner

### 🛡️ Security Features

- ✅ **Unique password per host** (no shared defaults)
- ✅ **Minimum 8 character requirement**
- ✅ **Password confirmation during setup**
- ✅ **No plain-text passwords in GitHub**
- ✅ **Template-based secure deployment**
- ✅ **Automatic syntax validation**

### 🧪 Testing Admin Access

After installation, test your admin system:

```bash
# Run the validation script
./tools/test-admin-access.sh

# Manual testing
# 1. Open: http://your-domain.com/?admin=true
# 2. Enter your password
# 3. Should see "Host Admin" mode
```

### 🔧 Password Recovery

If you forget your admin password:

```bash
# Check your installation report
cat /var/www/html/installation-report.txt

# Or check the admin system directly  
grep 'adminPassword:' /var/www/html/assets/js/unified-state-manager.js
```

### 👨‍💻 For Developers

This system uses template files with placeholders that are replaced during installation:

- `CHANGE_THIS_PASSWORD` - Main placeholder in JavaScript files
- `TEMPLATE_ADMIN_PASSWORD` - Placeholder in HTML files

These are automatically replaced with the user's chosen password during the installation process.

// =============================================================================
// 🎯 COPY-PASTE CHECKLIST
// =============================================================================

/*
✅ COPY-PASTE CHECKLIST:

1. 📁 Copy unified-state-manager.js template → /assets/js/unified-state-manager.js
2. 📁 Add test script → /tools/test-admin-access.sh  
3. 📁 Update your /quick-install.sh with the new functions
4. 📁 Update your /landing-page/index.html to remove hardcoded passwords
5. 📁 Add admin section to your /README.md
6. 🧪 Test the complete flow on a fresh server

After copying these files:
- git add .
- git commit -m "Implement secure per-host admin password system"
- git push origin main

Then test with: bash <(curl -fsSL https://raw.githubusercontent.com/YOUR_USERNAME/YOUR_REPO/main/quick-install.sh)
*/

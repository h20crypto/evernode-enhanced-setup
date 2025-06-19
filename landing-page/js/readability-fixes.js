// ==========================================
// Enhanced Evernode Host - Footer Cleanup & Button Visibility
// ==========================================

document.addEventListener('DOMContentLoaded', function() {
    // Clean up duplicate footers and extra content
    cleanupFooter();
    
    // Fix button visibility issues
    fixButtonVisibility();
    
    // Apply enhanced styling
    applyEnhancedStyling();
    
    console.log('✅ Enhanced readability improvements applied');
});

function cleanupFooter() {
    // Remove duplicate footers
    const footers = document.querySelectorAll('.footer');
    if (footers.length > 1) {
        // Keep only the last footer, remove others
        for (let i = 0; i < footers.length - 1; i++) {
            footers[i].remove();
        }
    }
    
    // Remove any extra footer content or duplicate elements
    const extraFooterElements = document.querySelectorAll('.extra-footer-content, .duplicate-footer');
    extraFooterElements.forEach(el => el.remove());
    
    // Clean up any trailing scripts or content after footer
    const footer = document.querySelector('.footer');
    if (footer && footer.nextElementSibling) {
        let nextElement = footer.nextElementSibling;
        while (nextElement) {
            const toRemove = nextElement;
            nextElement = nextElement.nextElementSibling;
            
            // Remove if it's not essential content
            if (!toRemove.matches('script[src], script[type="application/json"]')) {
                const text = toRemove.textContent.trim();
                if (text.length < 50 || text.includes('const realCommands') || text.includes('window.')) {
                    toRemove.remove();
                }
            }
        }
    }
    
    console.log('✅ Footer cleaned up');
}

function fixButtonVisibility() {
    // Find all buttons and ensure they have proper styling
    const buttons = document.querySelectorAll('button, .btn, .copy-btn, .copy-app-btn');
    
    buttons.forEach(button => {
        // Ensure buttons have minimum contrast
        const computedStyle = window.getComputedStyle(button);
        const backgroundColor = computedStyle.backgroundColor;
        
        // If button has poor visibility, apply enhanced styling
        if (backgroundColor === 'rgba(0, 0, 0, 0)' || backgroundColor === 'transparent') {
            button.style.backgroundColor = '#10b981';
            button.style.color = 'white';
            button.style.border = '1px solid #10b981';
        }
        
        // Ensure copy buttons are visible
        if (button.classList.contains('copy-btn') || button.classList.contains('copy-app-btn')) {
            button.style.backgroundColor = '#10b981';
            button.style.color = 'white';
            button.style.border = 'none';
            button.style.padding = '8px 16px';
            button.style.borderRadius = '6px';
            button.style.cursor = 'pointer';
            button.style.fontSize = '0.875rem';
            button.style.fontWeight = '500';
            button.style.transition = 'all 0.3s ease';
        }
        
        // Fix hover states
        button.addEventListener('mouseenter', function() {
            if (this.style.backgroundColor === 'rgb(16, 185, 129)') {
                this.style.backgroundColor = '#34d399';
                this.style.transform = 'scale(1.05)';
            }
        });
        
        button.addEventListener('mouseleave', function() {
            if (this.style.backgroundColor === 'rgb(52, 211, 153)') {
                this.style.backgroundColor = '#10b981';
                this.style.transform = 'scale(1)';
            }
        });
    });
    
    console.log(`✅ Fixed visibility for ${buttons.length} buttons`);
}

function applyEnhancedStyling() {
    // Apply CSS custom properties for better theming
    const root = document.documentElement;
    
    root.style.setProperty('--primary-green', '#10b981');
    root.style.setProperty('--primary-green-light', '#34d399');
    root.style.setProperty('--primary-green-dark', '#047857');
    root.style.setProperty('--text-primary', '#f1f5f9');
    root.style.setProperty('--text-secondary', '#cbd5e1');
    root.style.setProperty('--text-muted', '#94a3b8');
    root.style.setProperty('--bg-primary', 'rgba(30, 41, 59, 0.95)');
    root.style.setProperty('--bg-secondary', 'rgba(51, 65, 85, 0.8)');
    root.style.setProperty('--border-light', 'rgba(148, 163, 184, 0.2)');
    root.style.setProperty('--border-accent', 'rgba(16, 185, 129, 0.4)');
    
    // Fix any elements with poor contrast
    const lowContrastElements = document.querySelectorAll('*');
    lowContrastElements.forEach(element => {
        const computedStyle = window.getComputedStyle(element);
        const color = computedStyle.color;
        const backgroundColor = computedStyle.backgroundColor;
        
        // If text is too bright on light background, adjust
        if (color === 'rgb(0, 255, 136)' || color === 'rgb(0, 204, 255)') {
            element.style.color = '#10b981';
        }
    });
    
    // Ensure navigation is visible
    const navLinks = document.querySelectorAll('.nav-links a');
    navLinks.forEach(link => {
        link.style.color = '#cbd5e1';
        link.style.transition = 'all 0.3s ease';
        
        link.addEventListener('mouseenter', function() {
            this.style.color = '#f1f5f9';
            this.style.backgroundColor = 'rgba(51, 65, 85, 0.8)';
        });
        
        link.addEventListener('mouseleave', function() {
            this.style.color = '#cbd5e1';
            this.style.backgroundColor = 'transparent';
        });
    });
    
    console.log('✅ Enhanced styling applied');
}

// Function to add notification for successful cleanup
function showCleanupNotification() {
    const notification = document.createElement('div');
    notification.className = 'notification success';
    notification.innerHTML = `
        <strong>✅ UI Enhanced!</strong><br>
        Better readability and button visibility applied.
    `;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid #10b981;
        border-radius: 8px;
        padding: 1rem;
        color: #f1f5f9;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        z-index: 1001;
        max-width: 300px;
        animation: slideIn 0.3s ease-out;
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Add slide animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

// Show notification after cleanup
setTimeout(showCleanupNotification, 1000);

// Function to fix specific button issues
function fixSpecificButtons() {
    // Fix deploy command buttons
    const deployButtons = document.querySelectorAll('[onclick*="showRealDeployCommand"], [onclick*="copyRealCommand"]');
    deployButtons.forEach(button => {
        button.style.backgroundColor = '#10b981';
        button.style.color = 'white';
        button.style.border = '2px solid #10b981';
        button.style.padding = '8px 16px';
        button.style.borderRadius = '6px';
        button.style.fontWeight = '600';
    });
    
    // Fix status buttons
    const statusButtons = document.querySelectorAll('.status-badge, .btn-demo');
    statusButtons.forEach(button => {
        if (button.style.backgroundColor === '' || button.style.backgroundColor === 'transparent') {
            button.style.backgroundColor = '#3b82f6';
            button.style.color = 'white';
            button.style.border = '1px solid #3b82f6';
        }
    });
    
    console.log('✅ Fixed specific button styling issues');
}

// Run additional fixes after DOM is fully loaded
window.addEventListener('load', function() {
    setTimeout(fixSpecificButtons, 500);
});

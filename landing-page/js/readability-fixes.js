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
    
    // Clean up visible JavaScript code
    cleanupVisibleJavaScript();
    
    // Fix specific dashboard buttons
    fixDashboardButtons();
    
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
    
    /* Additional button fixes */
    .btn-secondary[href*="monitoring-dashboard"],
    .btn-secondary[onclick*="refreshStatus"],
    .btn[onclick*="refreshStatus"],
    .controls .btn {
        background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
        color: white !important;
        border: 2px solid #3b82f6 !important;
    }
    
    /* Hide any visible JavaScript text */
    body *:last-child {
        /* Check if it contains JavaScript and hide it */
    }
    
    /* Force hide problematic content */
    script:not([src]):not([type="application/json"]) {
        display: none !important;
    }
`;
document.head.appendChild(style);

// Aggressive cleanup function for visible JavaScript
function aggressiveJavaScriptCleanup() {
    // Find all text nodes and check for JavaScript patterns
    const allElements = document.getElementsByTagName('*');
    
    for (let element of allElements) {
        // Check text content of elements
        if (element.textContent && element.textContent.includes('const realCommands')) {
            element.style.display = 'none';
            console.log('Hidden element containing JavaScript code');
        }
        
        // Check for elements that are just JavaScript code
        if (element.innerHTML && 
            (element.innerHTML.includes('function showRealDeployCommand') ||
             element.innerHTML.includes('async function copyRealCommand') ||
             element.innerHTML.includes('const realCommands'))) {
            element.remove();
            console.log('Removed element containing JavaScript code');
        }
    }
    
    // Check the very last elements in the body
    const bodyChildren = Array.from(document.body.children);
    const lastElements = bodyChildren.slice(-3); // Check last 3 elements
    
    lastElements.forEach(element => {
        if (element.textContent && 
            (element.textContent.includes('realCommands') ||
             element.textContent.includes('showRealDeployCommand') ||
             element.textContent.includes('copyRealCommand'))) {
            element.remove();
            console.log('Removed trailing JavaScript element');
        }
    });
}

// Show notification after cleanup
setTimeout(() => {
    showCleanupNotification();
    // Run one final cleanup after notification
    aggressiveJavaScriptCleanup();
}, 1000);

function cleanupVisibleJavaScript() {
    // Find and remove any visible JavaScript code text
    const bodyText = document.body.innerHTML;
    
    // Look for specific JavaScript patterns that shouldn't be visible
    const jsPatterns = [
        /const realCommands = \{[^}]+\};/g,
        /function showRealDeployCommand\([^}]+\}/g,
        /async function copyRealCommand\([^}]+\}/g,
        /console\.log\([^)]+\);/g,
        /setTimeout\([^}]+\}/g,
        /button\.style\.[^;]+;/g
    ];
    
    // Remove visible JavaScript text
    let cleanedHTML = bodyText;
    jsPatterns.forEach(pattern => {
        cleanedHTML = cleanedHTML.replace(pattern, '');
    });
    
    // Find text nodes with JavaScript code
    const walker = document.createTreeWalker(
        document.body,
        NodeFilter.SHOW_TEXT,
        null,
        false
    );
    
    const textNodesToRemove = [];
    let node;
    
    while (node = walker.nextNode()) {
        const text = node.textContent.trim();
        
        // Check if text node contains JavaScript code
        if (text.includes('const realCommands') || 
            text.includes('function showRealDeployCommand') ||
            text.includes('async function copyRealCommand') ||
            text.includes('button.style.background') ||
            text.includes('navigator.clipboard.writeText') ||
            (text.includes('function') && text.includes('{') && text.length > 100)) {
            textNodesToRemove.push(node);
        }
    }
    
    // Remove problematic text nodes
    textNodesToRemove.forEach(node => {
        if (node.parentNode) {
            node.parentNode.removeChild(node);
        }
    });
    
    // Also check for any script tags without src that might be rendering as text
    const visibleScripts = document.querySelectorAll('script:not([src])');
    visibleScripts.forEach(script => {
        if (script.textContent.includes('realCommands') || 
            script.textContent.includes('showRealDeployCommand')) {
            // Keep the script but ensure it's not visible
            script.style.display = 'none';
            
            // If it's accidentally outside a script tag, move it inside one
            if (script.parentNode && script.parentNode.tagName !== 'HEAD') {
                document.head.appendChild(script);
            }
        }
    });
    
    console.log('✅ Cleaned up visible JavaScript code');
}

function fixDashboardButtons() {
    // Fix specific "View Dashboard" and "Refresh Status" buttons
    const dashboardButtons = document.querySelectorAll('a[href*="monitoring-dashboard"], [onclick*="refreshStatus"], .refresh-btn');
    
    dashboardButtons.forEach(button => {
        button.style.background = 'linear-gradient(135deg, #3b82f6, #2563eb)';
        button.style.color = 'white';
        button.style.border = '2px solid #3b82f6';
        button.style.padding = '12px 24px';
        button.style.borderRadius = '8px';
        button.style.fontWeight = '600';
        button.style.textDecoration = 'none';
        button.style.display = 'inline-flex';
        button.style.alignItems = 'center';
        button.style.gap = '8px';
        button.style.transition = 'all 0.3s ease';
        
        // Add hover effect
        button.addEventListener('mouseenter', function() {
            this.style.background = 'linear-gradient(135deg, #2563eb, #1d4ed8)';
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 8px 25px rgba(59, 130, 246, 0.3)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.background = 'linear-gradient(135deg, #3b82f6, #2563eb)';
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
    });
    
    // Fix any buttons in controls section (monitoring dashboard)
    const controlButtons = document.querySelectorAll('.controls .btn');
    controlButtons.forEach(button => {
        if (!button.style.background || button.style.background === 'transparent') {
            button.style.background = 'linear-gradient(135deg, #10b981, #047857)';
            button.style.color = 'white';
            button.style.border = '2px solid #10b981';
            button.style.padding = '12px 24px';
            button.style.fontWeight = '600';
        }
    });
    
    // Fix any remaining buttons with poor contrast
    const allButtons = document.querySelectorAll('button, .btn, [role="button"]');
    allButtons.forEach(button => {
        const computedStyle = window.getComputedStyle(button);
        const backgroundColor = computedStyle.backgroundColor;
        const color = computedStyle.color;
        
        // Check for poor contrast combinations
        if ((backgroundColor === 'rgba(0, 0, 0, 0)' || backgroundColor === 'transparent') && 
            (color === 'rgb(26, 26, 46)' || color === 'rgb(0, 0, 0)')) {
            button.style.background = '#10b981';
            button.style.color = 'white';
            button.style.border = '2px solid #10b981';
        }
        
        // Fix dark text on dark backgrounds
        if (color === 'rgb(26, 26, 46)' || color === 'rgb(0, 0, 0)' || color === '#1a1a2e') {
            button.style.color = 'white';
        }
    });
    
    console.log('✅ Fixed dashboard button styling');
}

// Run additional fixes after DOM is fully loaded
window.addEventListener('load', function() {
    setTimeout(() => {
        fixDashboardButtons();
        cleanupVisibleJavaScript();
        aggressiveJavaScriptCleanup();
    }, 500);
    
    // Also run cleanup after a longer delay to catch any dynamically added content
    setTimeout(() => {
        cleanupVisibleJavaScript();
        aggressiveJavaScriptCleanup();
    }, 2000);
    
    // Final cleanup after everything has loaded
    setTimeout(aggressiveJavaScriptCleanup, 5000);
});

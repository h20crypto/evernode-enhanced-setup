// Commission and Upgrade Features
function checkForUpgradeOpportunity() {
    fetch('/api/host-info.php')
        .then(response => response.json())
        .then(data => {
            const isEnhanced = data.enhanced || false;
            if (!isEnhanced) {
                setTimeout(() => {
                    const upgradeNotice = document.getElementById('upgradeNotice');
                    if (upgradeNotice) {
                        upgradeNotice.classList.add('show');
                    }
                }, 2000);
            }
        })
        .catch(() => {
            setTimeout(() => {
                const upgradeNotice = document.getElementById('upgradeNotice');
                if (upgradeNotice) {
                    upgradeNotice.classList.add('show');
                }
            }, 3000);
        });
}

function startQuickUpgrade() {
    const upgradeCommands = `# 🚀 Upgrade Your Host to Enhanced + Enable Commissions (60 seconds)
curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/quick-setup.sh | sudo bash -s -- --force-root

# ✅ Your host will be transformed with:
# • Modern glassmorphism UI
# • Real-time monitoring  
# • Enhanced APIs
# • Commission tracking system
# • Automatic license sales integration

# 💰 Commission earnings start immediately after upgrade!`;
    
    navigator.clipboard.writeText(upgradeCommands.trim()).then(() => {
        showUpgradeModal(`
            <h3 style="color: #28a745; margin-bottom: 20px;">⚡ Upgrade Commands Copied!</h3>
            
            <div style="background: rgba(0, 255, 136, 0.1); border-radius: 10px; padding: 15px; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <span style="font-size: 1.5rem;">💎</span>
                    <strong style="color: #00cc66;">Commission System Included!</strong>
                </div>
                <p style="margin: 0; color: #00995a; font-size: 0.9rem;">Your commission tracking will be automatically configured.</p>
            </div>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; font-family: monospace; white-space: pre-line; margin-bottom: 20px; font-size: 0.85rem;">${upgradeCommands}</div>
            
            <p><strong>Next steps:</strong></p>
            <ol style="margin: 10px 0; padding-left: 20px;">
                <li>SSH into your server</li>
                <li>Paste and run the copied command</li>
                <li>Wait 60 seconds for completion</li>
                <li>Start earning on every cluster license sale</li>
            </ol>
            
            <div style="background: rgba(255, 215, 0, 0.1); border-radius: 8px; padding: 10px; margin: 15px 0;">
                <strong style="color: #856404;">💰 Commission Rate: 20% ($10.00 per $49.99 license)</strong>
            </div>
            
            <button onclick="closeUpgradeModal()" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-top: 15px;">Got it! Start Earning! 🚀</button>
        `);
    }).catch(() => {
        showUpgradeModal(`
            <h3 style="color: #28a745; margin-bottom: 20px;">⚡ Upgrade Your Host!</h3>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; font-family: monospace; white-space: pre-line; margin-bottom: 20px; font-size: 0.85rem;">${upgradeCommands}</div>
            <p>Copy the command above and run it on your server!</p>
            <button onclick="closeUpgradeModal()" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-top: 15px;">Got it!</button>
        `);
    });
}

function showCommissionDetails() {
    showUpgradeModal(`
        <h3 style="margin-bottom: 20px; color: #00cc66;">💎 Enhanced Host Commission Program</h3>
        
        <div style="background: rgba(0, 255, 136, 0.1); border-radius: 10px; padding: 20px; margin-bottom: 20px;">
            <h4 style="color: #00cc66; margin-bottom: 15px;">How Commission Works:</h4>
            <ol style="margin: 0; padding-left: 20px; color: #00995a;">
                <li><strong>Tenant discovers your enhanced host</strong> through the network</li>
                <li><strong>They deploy apps</strong> and need cluster management</li>
                <li><strong>They purchase a $49.99 cluster license</strong> through your host</li>
                <li><strong>You earn 20% commission ($10.00)</strong> automatically via smart contract</li>
                <li><strong>Passive income</strong> - no work required after setup</li>
            </ol>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px;">
            <div style="background: rgba(255, 215, 0, 0.1); padding: 15px; border-radius: 8px; text-align: center;">
                <div style="font-size: 2rem; font-weight: bold; color: #b8860b;">20%</div>
                <div style="color: #856404; font-size: 0.9rem;">Commission Rate</div>
                <div style="color: #856404; font-size: 0.8rem; margin-top: 5px;">$10.00 per license</div>
            </div>
            <div style="background: rgba(0, 191, 255, 0.1); padding: 15px; border-radius: 8px; text-align: center;">
                <div style="font-size: 2rem; font-weight: bold; color: #0056b3;">$300-600</div>
                <div style="color: #004085; font-size: 0.9rem;">Monthly Average</div>
                <div style="color: #004085; font-size: 0.8rem; margin-top: 5px;">Top hosts earn more</div>
            </div>
        </div>
        
        <div style="background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <h5 style="margin-bottom: 10px; color: #495057;">📈 Real Examples:</h5>
            <ul style="margin: 0; padding-left: 20px; color: #6c757d; font-size: 0.9rem;">
                <li><strong>enhanced-host-1.com:</strong> 47 licenses sold → $470.00/month commission</li>
                <li><strong>premium-node.net:</strong> 28 licenses sold → $280.00/month commission</li>
                <li><strong>pro-evernode.io:</strong> 63 licenses sold → $630.00/month commission</li>
            </ul>
        </div>
        
        <div style="text-align: center;">
            <button onclick="startQuickUpgrade()" style="background: linear-gradient(135deg, #00cc66, #00ff88); color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; margin: 5px;">
                🚀 Upgrade Now & Start Earning
            </button>
            <button onclick="closeUpgradeModal()" style="background: #6c757d; color: white; padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; margin: 5px;">
                Maybe Later
            </button>
        </div>
        
        <p style="text-align: center; font-size: 0.8rem; color: #6c757d; margin-top: 15px;">
            * Commission paid automatically via Xahau smart contracts • No fees or delays
        </p>
    `);
}

function showUpgradeModal(content) {
    const modal = document.createElement('div');
    modal.id = 'upgradeModal';
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: rgba(0,0,0,0.8); z-index: 10000; display: flex; 
        align-items: center; justify-content: center; padding: 20px;
    `;
    
    modal.innerHTML = `
        <div style="background: white; border-radius: 15px; padding: 30px; max-width: 600px; width: 100%; max-height: 80vh; overflow-y: auto;">
            ${content}
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Close on outside click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeUpgradeModal();
        }
    });
}

function closeUpgradeModal() {
    const modal = document.getElementById('upgradeModal');
    if (modal) modal.remove();
}

function addEnhancementOpportunity(hostCard, host) {
    if (host.enhancement_level === 'standard') {
        const opportunity = document.createElement('div');
        opportunity.className = 'enhancement-opportunity';
        opportunity.innerHTML = `
            <strong>⚡ Enhancement Available</strong><br>
            <span style="font-size: 0.8rem;">This host could benefit from modern UI and advanced features</span>
        `;
        hostCard.appendChild(opportunity);
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    checkForUpgradeOpportunity();
});

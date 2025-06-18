// Commission and Upgrade Features
function checkForUpgradeOpportunity() {
    fetch('/api/host-info.php')
        .then(response => response.json())
        .then(data => {
            const isEnhanced = data.enhanced || false;
            if (!isEnhanced) {
                setTimeout(() => {
                    document.getElementById('upgradeNotice').classList.add('show');
                }, 2000);
            }
        })
        .catch(() => {
            setTimeout(() => {
                document.getElementById('upgradeNotice').classList.add('show');
            }, 3000);
        });
}

function startQuickUpgrade() {
    const upgradeCommands = `
# 🚀 Upgrade Your Host to Enhanced + Enable Commissions (60 seconds)
curl -fsSL https://raw.githubusercontent.com/h20crypto/evernode-enhanced-setup/main/quick-setup.sh | sudo bash
    `;
    
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
            <button onclick="closeUpgradeModal()" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-top: 15px;">Got it! Start Earning! 🚀</button>
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
                <li><strong>They purchase a $49.99 cluster license</strong> through your host</li>
                <li><strong>You earn 15% commission ($7.50)</strong> automatically</li>
                <li><strong>Passive income</strong> - no work required after setup</li>
            </ol>
        </div>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px;">
            <div style="background: rgba(255, 215, 0, 0.1); padding: 15px; border-radius: 8px; text-align: center;">
                <div style="font-size: 2rem; font-weight: bold; color: #b8860b;">15%</div>
                <div style="color: #856404; font-size: 0.9rem;">Commission Rate</div>
            </div>
            <div style="background: rgba(0, 191, 255, 0.1); padding: 15px; border-radius: 8px; text-align: center;">
                <div style="font-size: 2rem; font-weight: bold; color: #0056b3;">$200-500</div>
                <div style="color: #004085; font-size: 0.9rem;">Monthly Average</div>
            </div>
        </div>
        <div style="text-align: center;">
            <button onclick="startQuickUpgrade()" style="background: linear-gradient(135deg, #00cc66, #00ff88); color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; margin: 5px;">
                🚀 Upgrade Now & Start Earning
            </button>
        </div>
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
    modal.innerHTML = `<div style="background: white; border-radius: 15px; padding: 30px; max-width: 600px; width: 100%; max-height: 80vh; overflow-y: auto;">${content}</div>`;
    document.body.appendChild(modal);
}

function closeUpgradeModal() {
    const modal = document.getElementById('upgradeModal');
    if (modal) modal.remove();
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    checkForUpgradeOpportunity();
});

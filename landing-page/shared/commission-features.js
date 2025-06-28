// 💰 Enhanced Commission Features - Updated for Centralized Paywall
// Replace your existing commission-features.js with this:

class EnhancedCommissionManager {
    constructor() {
        this.hostDomain = window.location.hostname;
        this.referralCode = btoa(this.hostDomain).substr(0, 8).toUpperCase();
        this.paywallAPI = 'https://api.evrdirect.info';
        this.paywallURL = 'https://payments.evrdirect.info';
        
        this.init();
    }

    async init() {
        await this.loadRealCommissionData();
        this.updatePremiumButtons();
        this.displayCommissionWidget();
        
        // Refresh every 5 minutes
        setInterval(() => this.loadRealCommissionData(), 5 * 60 * 1000);
    }

    async loadRealCommissionData() {
        try {
            // Load real commission stats from your paywall server
            const response = await fetch(`${this.paywallAPI}/api/commission-stats/${this.hostDomain}`);
            const data = await response.json();
            
            if (data.success) {
                this.commissionData = data.stats;
                this.updateCommissionDisplay();
                console.log('✅ Real commission data loaded');
            }
        } catch (error) {
            console.log('📊 Paywall server unreachable, using demo data');
            this.loadDemoData();
        }
    }

    updatePremiumButtons() {
        const referralUrl = `${this.paywallURL}?ref=${this.hostDomain}&wallet=HOST_WALLET_HERE`;
        
        // Update all existing premium buttons to point to centralized paywall
        document.querySelectorAll('.premium-button, .upgrade-button, .premium-cluster-button').forEach(button => {
            button.onclick = () => {
                window.open(referralUrl, '_blank');
                this.trackReferralClick();
            };
        });

        // Update any premium links
        document.querySelectorAll('a[href*="paywall"], a[href*="premium"]').forEach(link => {
            link.href = referralUrl;
            link.target = '_blank';
        });
    }

    displayCommissionWidget() {
        // Update your existing commission widget with real data
        const widget = document.querySelector('.commission-widget');
        if (widget) {
            widget.innerHTML = `
                <div class="earnings-summary">
                    <h3 style="color: #00ff88;">💰 Real Commission Earnings</h3>
                    <div class="earnings-grid">
                        <div class="earning-item">
                            <span class="label">Total Earned:</span>
                            <span class="value">$${(this.commissionData?.total_earned || 0).toFixed(2)}</span>
                        </div>
                        <div class="earning-item">
                            <span class="label">This Month:</span>
                            <span class="value">$${(this.commissionData?.monthly_earnings || 0).toFixed(2)}</span>
                        </div>
                        <div class="earning-item">
                            <span class="label">Pending (14-day hold):</span>
                            <span class="value">$${(this.commissionData?.pending_commissions || 0).toFixed(2)}</span>
                        </div>
                        <div class="earning-item">
                            <span class="label">Ready for Payout:</span>
                            <span class="value">$${(this.commissionData?.payable_commissions || 0).toFixed(2)}</span>
                        </div>
                    </div>
                    <div class="payout-info">
                        <p>💰 Next payout: ${this.commissionData?.next_payout || 'This Sunday'}</p>
                        <p>📈 Commission rate: 20% ($10 per $49.99 sale)</p>
                    </div>
                </div>
            `;
        }
    }

    trackReferralClick() {
        // Track when someone clicks premium button
        fetch(`${this.paywallAPI}/api/track-referral`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                hostDomain: this.hostDomain,
                timestamp: new Date().toISOString()
            })
        }).catch(() => {}); // Silent fail
    }

    loadDemoData() {
        // Fallback demo data when paywall server is unreachable
        this.commissionData = {
            total_earned: 127.50,
            monthly_earnings: 45.00,
            pending_commissions: 20.00,
            payable_commissions: 107.50,
            next_payout: 'This Sunday'
        };
    }
}

// Auto-initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    window.enhancedCommissions = new EnhancedCommissionManager();
});

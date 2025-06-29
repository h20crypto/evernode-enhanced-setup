// 💰 Enhanced Evernode Commission Features - Centralized Paywall
class CommissionManager {
    constructor() {
        this.hostId = window.location.hostname;
        this.referralCode = this.generateReferralCode();
        this.paymentServer = 'https://payments.evrdirect.info';
        this.apiServer = 'https://api.evrdirect.info';
        this.init();
    }

    init() {
        this.loadCommissionStats();
        this.setupReferralLinks();
        this.trackReferrals();
    }

    generateReferralCode() {
        return btoa(this.hostId).substr(0, 8).toUpperCase();
    }

    setupReferralLinks() {
        const referralParam = `?ref=${this.referralCode}&host=${this.hostId}&wallet=HOST_WALLET_PLACEHOLDER`;
        const fullReferralUrl = this.paymentServer + referralParam;
        
        // Update all premium cluster manager links
        document.querySelectorAll('.premium-cluster-link').forEach(link => {
            link.href = fullReferralUrl;
        });
    }

    async loadCommissionStats() {
        try {
            const response = await fetch(`${this.apiServer}/api/commission-stats/${this.hostId}`);
            const data = await response.json();
            
            if (data.success) {
                this.updateCommissionDisplay(data.stats);
            }
        } catch (error) {
            console.log('Commission stats will load later');
        }
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', () => {
    window.commissionManager = new CommissionManager();
});

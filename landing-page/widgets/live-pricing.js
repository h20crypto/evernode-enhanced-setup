/**
 * Live Pricing Widget for Evernode Enhanced Setup
 * Integrates with existing crypto-rates.php API
 * Live rates only - no static fallbacks
 */

class LivePricingIntegration {
    constructor() {
        this.apiUrl = '/api/crypto-rates.php';
        this.updateInterval = 180000; // 3 minutes
        this.listeners = {};
        this.currentRates = null;
        
        this.init();
    }
    
    async init() {
        await this.fetchRates();
        this.startAutoUpdate();
        this.initializeWidgets();
    }
    
    async fetchRates() {
        try {
            const response = await fetch(this.apiUrl);
            const data = await response.json();
            
            if (data.success) {
                this.currentRates = data;
                this.notifyListeners('ratesUpdated', data);
                return data;
            } else {
                throw new Error('API returned error');
            }
        } catch (error) {
            console.error('Live pricing error:', error);
            this.notifyListeners('ratesError', error);
            return null;
        }
    }
    
    async getLicenseRates() {
        if (!this.currentRates) {
            await this.fetchRates();
        }
        return this.currentRates?.license || null;
    }
    
    async getClusterRates() {
        if (!this.currentRates) {
            await this.fetchRates();
        }
        return this.currentRates?.cluster || null;
    }
    
    async getROIData() {
        if (!this.currentRates) {
            await this.fetchRates();
        }
        return this.currentRates?.roi || null;
    }
    
    onRateUpdate(component, callback) {
        if (!this.listeners[component]) {
            this.listeners[component] = [];
        }
        this.listeners[component].push(callback);
    }
    
    notifyListeners(event, data) {
        Object.keys(this.listeners).forEach(component => {
            this.listeners[component].forEach(callback => {
                callback(event, data);
            });
        });
    }
    
    initializeWidgets() {
        // Initialize license pricing widgets
        document.querySelectorAll('[data-live-pricing="license"]').forEach(element => {
            this.initLicenseWidget(element);
        });
        
        // Initialize cluster pricing widgets
        document.querySelectorAll('[data-live-pricing="cluster"]').forEach(element => {
            this.initClusterWidget(element);
        });
        
        // Initialize ROI calculator widgets
        document.querySelectorAll('[data-live-pricing="roi"]').forEach(element => {
            this.initROIWidget(element);
        });
    }
    
    async initLicenseWidget(element) {
        const rates = await this.getLicenseRates();
        if (!rates) {
            element.innerHTML = '<div class="pricing-error">Live rates unavailable</div>';
            return;
        }
        
        const currencies = rates.currencies;
        const html = Object.keys(currencies).map(currency => {
            const data = currencies[currency];
            if (!data.available) {
                return `
                    <div class="currency-option disabled" data-currency="${currency}">
                        <div class="currency-symbol">${currency.toUpperCase()}</div>
                        <div class="currency-error">Rate unavailable</div>
                    </div>
                `;
            }
            
            return `
                <div class="currency-option" data-currency="${currency}" onclick="selectCurrency('${currency}')">
                    <div class="currency-symbol">${currency.toUpperCase()}</div>
                    <div class="currency-amount">${data.display}</div>
                    <div class="currency-rate">$${data.rate.toFixed(4)}</div>
                    <div class="currency-source">${data.source}</div>
                </div>
            `;
        }).join('');
        
        element.innerHTML = `
            <div class="live-pricing-grid">
                ${html}
            </div>
            <div class="pricing-footer">
                ${rates.available_count} of ${Object.keys(currencies).length} payment methods available
            </div>
        `;
    }
    
    async initClusterWidget(element) {
        const rates = await this.getClusterRates();
        if (!rates) return;
        
        // Update cluster cost displays
        element.querySelectorAll('[data-cluster-cost]').forEach(costElement => {
            const currency = costElement.dataset.clusterCost;
            if (rates.currencies[currency]?.available) {
                const cost = rates.currencies[currency].base_cost;
                costElement.textContent = `${cost} ${currency.toUpperCase()}`;
            } else {
                costElement.textContent = 'Rate unavailable';
            }
        });
    }
    
    async initROIWidget(element) {
        const roi = await this.getROIData();
        if (!roi) return;
        
        element.innerHTML = `
            <div class="roi-calculator">
                <div class="roi-metric">
                    <div class="roi-value">${roi.break_even_days}</div>
                    <div class="roi-label">Days to break even</div>
                </div>
                <div class="roi-metric">
                    <div class="roi-value">${roi.efficiency_gain_percent}%</div>
                    <div class="roi-label">Efficiency gain</div>
                </div>
                <div class="roi-calculations">
                    <div>XRP Cost: ${roi.live_calculations.xrp_cost}</div>
                    <div>EVR Cost: ${roi.live_calculations.evr_cost}</div>
                    <div>Payback: ${roi.live_calculations.payback_period}</div>
                </div>
            </div>
        `;
    }
    
    startAutoUpdate() {
        setInterval(() => {
            this.fetchRates();
        }, this.updateInterval);
    }
}

// Global currency selection function
function selectCurrency(currency) {
    document.querySelectorAll('.currency-option').forEach(option => {
        option.classList.remove('selected');
    });
    document.querySelector(`[data-currency="${currency}"]`).classList.add('selected');
    
    // Trigger custom event
    document.dispatchEvent(new CustomEvent('currencySelected', {
        detail: { currency }
    }));
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.livePricing = new LivePricingIntegration();
});

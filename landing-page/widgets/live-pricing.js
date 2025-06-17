// landing-page/widgets/live-pricing.js - Updated for accurate CoinGecko rates

class LivePricingWidget {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.options = {
            updateInterval: 60000, // 1 minute
            showSource: true,
            showConfidence: true,
            apiEndpoint: '../api/crypto-rates-optimized.php',
            ...options
        };
        
        this.isUpdating = false;
        this.lastUpdate = null;
        this.updateTimer = null;
        
        this.init();
    }
    
    init() {
        if (!this.container) {
            console.error('Live pricing container not found');
            return;
        }
        
        this.createWidget();
        this.updatePricing();
        this.startAutoUpdate();
    }
    
    createWidget() {
        this.container.innerHTML = `
            <div class="live-pricing-widget">
                <div class="pricing-header">
                    <h3>🎫 Cluster License Pricing</h3>
                    <div class="pricing-status" id="pricing-status">
                        <span class="status-dot loading"></span>
                        <span class="status-text">Loading rates...</span>
                    </div>
                </div>
                
                <div class="pricing-grid" id="pricing-grid">
                    <!-- Prices will be loaded here -->
                </div>
                
                <div class="pricing-footer">
                    <div class="last-update" id="last-update">Never updated</div>
                    <div class="pricing-source" id="pricing-source"></div>
                </div>
            </div>
        `;
        
        this.addStyles();
    }
    
    async updatePricing() {
        if (this.isUpdating) return;
        
        this.isUpdating = true;
        this.updateStatus('loading', 'Updating rates...');
        
        try {
            const response = await fetch(`${this.options.apiEndpoint}?mode=balanced&t=${Date.now()}`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.renderPricing(data);
                this.updateStatus('success', 'Rates updated');
                this.lastUpdate = Date.now();
            } else {
                this.renderError(data.message || 'Pricing unavailable');
                this.updateStatus('error', 'Pricing unavailable');
            }
            
        } catch (error) {
            console.error('Failed to update pricing:', error);
            this.renderError('Connection failed');
            this.updateStatus('error', 'Connection failed');
        } finally {
            this.isUpdating = false;
            this.updateLastUpdateTime();
        }
    }
    
    renderPricing(data) {
        const grid = document.getElementById('pricing-grid');
        
        grid.innerHTML = `
            <div class="price-card xah-card">
                <div class="price-icon">💎</div>
                <div class="price-currency">XAH</div>
                <div class="price-amount">${data.xah.display}</div>
                <div class="price-usd">$${data.xah.rate.toFixed(4)} USD</div>
                <div class="price-label">Xahau Native</div>
            </div>
            
            <div class="price-card xrp-card">
                <div class="price-icon">🪙</div>
                <div class="price-currency">XRP</div>
                <div class="price-amount">${data.xrp.display}</div>
                <div class="price-usd">$${data.xrp.rate.toFixed(3)} USD</div>
                <div class="price-label">Fast & Reliable</div>
            </div>
            
            <div class="price-card evr-card">
                <div class="price-icon">⚡</div>
                <div class="price-currency">EVR</div>
                <div class="price-amount">${data.evr.display}</div>
                <div class="price-usd">$${data.evr.rate.toFixed(3)} USD</div>
                <div class="price-label">Evernode Token</div>
            </div>
        `;
        
        // Update source info
        const sourceElement = document.getElementById('pricing-source');
        if (this.options.showSource && sourceElement) {
            let sourceText = `Source: ${data.source || 'CoinGecko'}`;
            
            if (this.options.showConfidence && data.confidence) {
                sourceText += ` • Confidence: ${data.confidence.toUpperCase()}`;
            }
            
            if (data.tokens_fetched) {
                sourceText += ` • ${data.tokens_fetched}/3 rates fetched`;
            }
            
            sourceElement.textContent = sourceText;
        }
        
        // Add click handlers for purchase
        grid.querySelectorAll('.price-card').forEach(card => {
            card.addEventListener('click', () => {
                const currency = card.classList.contains('xah-card') ? 'xah' :
                               card.classList.contains('xrp-card') ? 'xrp' : 'evr';
                this.handlePurchaseClick(currency, data[currency]);
            });
        });
    }
    
    renderError(message) {
        const grid = document.getElementById('pricing-grid');
        
        grid.innerHTML = `
            <div class="pricing-error">
                <div class="error-icon">⚠️</div>
                <div class="error-message">${message}</div>
                <button class="retry-btn" onclick="window.livePricing?.updatePricing()">
                    🔄 Retry
                </button>
            </div>
        `;
    }
    
    updateStatus(type, message) {
        const statusDot = document.querySelector('.status-dot');
        const statusText = document.querySelector('.status-text');
        
        if (statusDot && statusText) {
            statusDot.className = `status-dot ${type}`;
            statusText.textContent = message;
        }
    }
    
    updateLastUpdateTime() {
        const updateElement = document.getElementById('last-update');
        if (updateElement && this.lastUpdate) {
            const timeAgo = Math.round((Date.now() - this.lastUpdate) / 1000);
            updateElement.textContent = `Updated ${timeAgo}s ago`;
        }
    }
    
    handlePurchaseClick(currency, priceData) {
        // Redirect to paywall with selected currency
        const params = new URLSearchParams({
            currency: currency,
            amount: priceData.amount_for_license,
            rate: priceData.rate
        });
        
        window.location.href = `../cluster/paywall.html?${params.toString()}`;
    }
    
    startAutoUpdate() {
        if (this.updateTimer) {
            clearInterval(this.updateTimer);
        }
        
        this.updateTimer = setInterval(() => {
            this.updatePricing();
        }, this.options.updateInterval);
        
        // Update time display every 10 seconds
        setInterval(() => {
            this.updateLastUpdateTime();
        }, 10000);
    }
    
    destroy() {
        if (this.updateTimer) {
            clearInterval(this.updateTimer);
            this.updateTimer = null;
        }
    }
    
    addStyles() {
        if (document.getElementById('live-pricing-styles')) return;
        
        const styles = document.createElement('style');
        styles.id = 'live-pricing-styles';
        styles.textContent = `
            .live-pricing-widget {
                background: rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(10px);
                border-radius: 12px;
                padding: 20px;
                border: 1px solid rgba(255, 255, 255, 0.2);
                color: white;
            }
            
            .pricing-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }
            
            .pricing-header h3 {
                margin: 0;
                background: linear-gradient(45deg, #FFD700, #FFA500);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            
            .pricing-status {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 0.9rem;
            }
            
            .status-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
            }
            
            .status-dot.loading {
                background: #ff9800;
                animation: pulse 1.5s infinite;
            }
            
            .status-dot.success {
                background: #4CAF50;
            }
            
            .status-dot.error {
                background: #f44336;
            }
            
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.5; }
            }
            
            .pricing-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 15px;
                margin-bottom: 15px;
            }
            
            .price-card {
                background: rgba(255, 255, 255, 0.1);
                border-radius: 10px;
                padding: 20px;
                text-align: center;
                cursor: pointer;
                transition: all 0.3s ease;
                border: 2px solid transparent;
            }
            
            .price-card:hover {
                background: rgba(255, 255, 255, 0.2);
                border-color: #FFD700;
                transform: translateY(-2px);
            }
            
            .price-icon {
                font-size: 2rem;
                margin-bottom: 8px;
            }
            
            .price-currency {
                font-size: 1.1rem;
                font-weight: bold;
                margin-bottom: 5px;
            }
            
            .price-amount {
                font-size: 1.3rem;
                font-weight: bold;
                color: #FFD700;
                margin-bottom: 5px;
            }
            
            .price-usd {
                font-size: 0.9rem;
                opacity: 0.8;
                margin-bottom: 5px;
            }
            
            .price-label {
                font-size: 0.8rem;
                opacity: 0.7;
            }
            
            .pricing-error {
                grid-column: 1 / -1;
                text-align: center;
                padding: 40px 20px;
                background: rgba(244, 67, 54, 0.1);
                border-radius: 8px;
                border: 1px solid rgba(244, 67, 54, 0.3);
            }
            
            .error-icon {
                font-size: 3rem;
                margin-bottom: 15px;
            }
            
            .error-message {
                font-size: 1.1rem;
                margin-bottom: 20px;
            }
            
            .retry-btn {
                background: linear-gradient(45deg, #FFD700, #FFA500);
                color: #333;
                border: none;
                padding: 10px 20px;
                border-radius: 6px;
                font-weight: bold;
                cursor: pointer;
                transition: transform 0.2s;
            }
            
            .retry-btn:hover {
                transform: translateY(-1px);
            }
            
            .pricing-footer {
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 0.8rem;
                opacity: 0.7;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
                padding-top: 15px;
            }
            
            @media (max-width: 600px) {
                .pricing-grid {
                    grid-template-columns: 1fr;
                }
                
                .pricing-header {
                    flex-direction: column;
                    gap: 10px;
                    text-align: center;
                }
                
                .pricing-footer {
                    flex-direction: column;
                    gap: 5px;
                    text-align: center;
                }
            }
        `;
        
        document.head.appendChild(styles);
    }
}

// Auto-initialize if container exists
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('live-pricing');
    if (container) {
        window.livePricing = new LivePricingWidget('live-pricing', {
            updateInterval: 30000, // Update every 30 seconds
            showSource: true,
            showConfidence: true
        });
    }
});

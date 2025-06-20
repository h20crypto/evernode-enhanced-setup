// ==========================================
// Unified Pricing & ROI Calculator System
// Single source of truth for all pricing data
// ==========================================

class UnifiedPricingSystem {
    constructor() {
        this.cache = new Map();
        this.cacheTimeout = 300000; // 5 minutes
        this.updateInterval = 30000; // 30 seconds
        this.priceHistory = [];
        this.observers = [];
        
        this.config = {
            // Base Evernode costs (in EVR per hour)
            evernode_cost_ranges: {
                cheap: 0.00001,    // Cheapest hosts
                medium: 0.005,     // Medium quality hosts  
                premium: 0.02      // Premium hosts
            },
            default_host_type: 'medium', // Use medium as default
            
            // Cloud comparison rates (USD per hour)
            cloud_rates: {
                aws_t3_micro: 0.0104,
                aws_t3_small: 0.0208,
                aws_t3_medium: 0.0416,
                gcp_e2_micro: 0.0084,
                azure_b1s: 0.0104
            },
            
            // Development time costs (USD per hour)
            developer_rates: {
                junior: 25,
                mid: 50,
                senior: 75,
                consultant: 100
            },
            
            // Cluster license pricing
            cluster_license: {
                price_usd: 49.99,
                commission_rate: 0.20 // 20% for referring hosts ($10.00 per sale)
            }
        };
        
        this.init();
    }

    // ==========================================
    // INITIALIZATION & DATA FETCHING
    // ==========================================
    
    async init() {
        console.log('💰 Initializing Unified Pricing System...');
        
        // Load cached data
        this.loadFromCache();
        
        // Start price monitoring
        await this.updatePrices();
        this.startPriceMonitoring();
        
        // Setup event listeners
        this.setupEventListeners();
        
        console.log('✅ Unified Pricing System ready');
    }

    async updatePrices() {
        try {
            const priceData = await this.fetchCurrentPrices();
            this.cache.set('current_prices', {
                data: priceData,
                timestamp: Date.now()
            });
            
            // Add to history
            this.priceHistory.push({
                ...priceData,
                timestamp: Date.now()
            });
            
            // Keep only last 24 hours of history
            this.priceHistory = this.priceHistory.filter(
                entry => Date.now() - entry.timestamp < 86400000
            );
            
            // Notify observers
            this.notifyObservers('price-update', priceData);
            
            // Save to localStorage
            this.saveToCache();
            
        } catch (error) {
            console.error('❌ Price update failed:', error);
            // Use fallback prices if available
            this.useFallbackPrices();
        }
    }

    async fetchCurrentPrices() {
        const sources = [
            {
                name: 'coingecko_evr',
                url: 'https://api.coingecko.com/api/v3/simple/price?ids=evernode&vs_currencies=usd',
                parser: (data) => data.evernode?.usd
            },
            {
                name: 'coingecko_xrp',
                url: 'https://api.coingecko.com/api/v3/simple/price?ids=ripple&vs_currencies=usd',
                parser: (data) => data.ripple?.usd
            },
            {
                name: 'dhali_oracle',
                url: '/api/crypto-rates.php?component=all',
                parser: (data) => ({
                    evr: data.evr?.rate,
                    xrp: data.xrp?.rate
                })
            }
        ];

        const results = await Promise.allSettled(
            sources.map(source => this.fetchFromSource(source))
        );

        let evrPrices = [];
        let xrpPrices = [];

        results.forEach((result, index) => {
            if (result.status === 'fulfilled' && result.value) {
                const sourceName = sources[index].name;
                
                if (sourceName === 'dhali_oracle' && typeof result.value === 'object') {
                    if (result.value.evr > 0) evrPrices.push({ source: 'dhali_evr', price: result.value.evr });
                    if (result.value.xrp > 0) xrpPrices.push({ source: 'dhali_xrp', price: result.value.xrp });
                } else if (sourceName.includes('evr') && result.value > 0) {
                    evrPrices.push({ source: sourceName, price: result.value });
                } else if (sourceName.includes('xrp') && result.value > 0) {
                    xrpPrices.push({ source: sourceName, price: result.value });
                }
            }
        });

        if (evrPrices.length === 0) {
            throw new Error('No EVR price sources available');
        }

        // Calculate consensus prices
        const evrConsensus = this.calculateMedian(evrPrices.map(p => p.price));
        const xrpConsensus = xrpPrices.length > 0 ? this.calculateMedian(xrpPrices.map(p => p.price)) : 0.42;
        
        return {
            evr_usd: evrConsensus,
            xrp_usd: xrpConsensus,
            evr_sources: evrPrices,
            xrp_sources: xrpPrices,
            confidence: evrPrices.length >= 2 ? 'high' : 'low',
            last_updated: new Date().toISOString()
        };
    }

    async fetchFromSource(source) {
        const response = await fetch(source.url, { timeout: 5000 });
        const data = await response.json();
        return source.parser(data);
    }

    // ==========================================
    // ROI CALCULATIONS
    // ==========================================
    
    calculateClusterROI(params) {
        const {
            instances = 5,
            region = 'global',
            developer_level = 'mid',
            deployment_hours = 24, // cluster duration
            manual_setup_hours = 2.5, // per instance
            maintenance_hours_per_month = 1.5, // per instance
            host_type = 'medium' // cheap, medium, premium
        } = params;

        const pricing = this.getCurrentPricing();
        if (!pricing) return null;

        // Calculate Evernode costs using EVR
        const evr_rate_per_hour = this.config.evernode_cost_ranges[host_type];
        const evernode_hourly_usd = evr_rate_per_hour * pricing.evr_usd;
        const evernode_total = evernode_hourly_usd * instances * deployment_hours;

        // Calculate manual deployment time costs
        const developer_rate = this.config.developer_rates[developer_level];
        const manual_setup_cost = manual_setup_hours * instances * developer_rate;
        
        // Calculate ongoing maintenance costs (monthly)
        const monthly_maintenance_cost = maintenance_hours_per_month * instances * developer_rate;

        // Calculate cloud comparison
        const cloud_hourly = this.config.cloud_rates.aws_t3_medium; // Default comparison
        const cloud_total = cloud_hourly * instances * deployment_hours;

        // Cluster Manager benefits
        const cluster_setup_time = 0.17; // 10 minutes to set up entire cluster
        const cluster_setup_cost = cluster_setup_time * developer_rate;
        const cluster_maintenance_monthly = 0.25 * instances; // 15 minutes per instance monthly

        // Calculate savings
        const setup_time_saved = (manual_setup_hours * instances) - cluster_setup_time;
        const setup_cost_saved = manual_setup_cost - cluster_setup_cost;
        const monthly_time_saved = (maintenance_hours_per_month * instances) - cluster_maintenance_monthly;
        const monthly_cost_saved = monthly_time_saved * developer_rate;

        // ROI calculations
        const license_cost = this.config.cluster_license.price_usd;
        const roi_days = license_cost / (monthly_cost_saved / 30);
        const annual_savings = monthly_cost_saved * 12;

        return {
            costs: {
                evernode_hosting: evernode_total,
                cloud_hosting: cloud_total,
                license_cost: license_cost,
                manual_setup: manual_setup_cost,
                cluster_setup: cluster_setup_cost,
                evr_rate_per_hour: evr_rate_per_hour,
                evr_usd_rate: pricing.evr_usd
            },
            savings: {
                setup_time_hours: setup_time_saved,
                setup_cost_usd: setup_cost_saved,
                monthly_time_hours: monthly_time_saved,
                monthly_cost_usd: monthly_cost_saved,
                annual_cost_usd: annual_savings,
                vs_cloud_percent: ((cloud_total - evernode_total) / cloud_total) * 100
            },
            roi: {
                payback_days: roi_days,
                annual_roi_percent: (annual_savings / license_cost) * 100,
                break_even_date: new Date(Date.now() + (roi_days * 24 * 60 * 60 * 1000))
            },
            comparison: {
                evernode_vs_cloud_savings: cloud_total - evernode_total,
                time_efficiency_improvement: (setup_time_saved / (manual_setup_hours * instances)) * 100
            }
        };
    }

    calculateSingleTenantROI(appType, deploymentHours = 24, hostType = 'medium') {
        const pricing = this.getCurrentPricing();
        if (!pricing) return null;

        const appConfigs = {
            wordpress: { instances: 1, setup_time: 2, monthly_maintenance: 1 },
            nextcloud: { instances: 1, setup_time: 1.5, monthly_maintenance: 0.5 },
            n8n: { instances: 1, setup_time: 1, monthly_maintenance: 0.25 },
            grafana: { instances: 1, setup_time: 1.5, monthly_maintenance: 0.5 },
            ghost: { instances: 1, setup_time: 1, monthly_maintenance: 0.25 }
        };

        const config = appConfigs[appType] || appConfigs.wordpress;
        
        // Evernode costs using EVR
        const evr_rate_per_hour = this.config.evernode_cost_ranges[hostType];
        const evernode_hourly_usd = evr_rate_per_hour * pricing.evr_usd;
        const evernode_total = evernode_hourly_usd * deploymentHours;

        // Cloud equivalent
        const cloud_hourly = this.config.cloud_rates.aws_t3_small;
        const cloud_total = cloud_hourly * deploymentHours;

        // Time value (assuming self-deployment)
        const developer_rate = this.config.developer_rates.mid;
        const manual_setup_cost = config.setup_time * developer_rate;
        const one_click_setup_cost = 0.05 * developer_rate; // 3 minutes

        return {
            costs: {
                evernode_hosting: evernode_total,
                cloud_hosting: cloud_total,
                manual_setup_time: manual_setup_cost,
                one_click_setup_time: one_click_setup_cost,
                evr_rate_per_hour: evr_rate_per_hour,
                evr_usd_rate: pricing.evr_usd
            },
            savings: {
                vs_cloud_usd: cloud_total - evernode_total,
                vs_cloud_percent: ((cloud_total - evernode_total) / cloud_total) * 100,
                setup_time_saved_hours: config.setup_time - 0.05,
                setup_cost_saved: manual_setup_cost - one_click_setup_cost
            },
            monthly_projection: {
                evernode_cost: evernode_hourly_usd * 24 * 30, // 30 days
                cloud_cost: cloud_hourly * 24 * 30,
                maintenance_time_saved: config.monthly_maintenance,
                maintenance_cost_saved: config.monthly_maintenance * developer_rate
            }
        };
    }

    // ==========================================
    // COMMISSION CALCULATIONS
    // ==========================================
    
    calculateCommissionEarnings(referrals = 0, timeframe = 'monthly') {
        const commission_per_sale = this.config.cluster_license.price_usd * this.config.cluster_license.commission_rate;
        
        const multipliers = {
            daily: 1/30,
            weekly: 1/4.33,
            monthly: 1,
            quarterly: 3,
            annually: 12
        };

        const base_monthly_referrals = referrals;
        const projected_referrals = base_monthly_referrals * multipliers[timeframe];
        
        return {
            commission_per_sale: commission_per_sale,
            projected_sales: projected_referrals,
            total_earnings: projected_referrals * commission_per_sale,
            timeframe: timeframe,
            growth_potential: {
                conservative: projected_referrals * 0.8 * commission_per_sale,
                realistic: projected_referrals * commission_per_sale,
                optimistic: projected_referrals * 1.5 * commission_per_sale
            }
        };
    }

    // ==========================================
    // REAL-TIME PRICING UPDATES
    // ==========================================
    
    startPriceMonitoring() {
        // Update prices every 30 seconds
        setInterval(() => {
            this.updatePrices();
        }, this.updateInterval);
        
        console.log('📊 Price monitoring started');
    }

    getCurrentPricing() {
        const cached = this.cache.get('current_prices');
        if (!cached || Date.now() - cached.timestamp > this.cacheTimeout) {
            return this.getFallbackPricing();
        }
        return cached.data;
    }

    getFallbackPricing() {
        // Fallback pricing when APIs are unavailable
        return {
            evr_usd: 0.22, // Conservative EVR estimate
            xrp_usd: 0.42, // Conservative XRP estimate
            evr_sources: [{ source: 'fallback', price: 0.22 }],
            xrp_sources: [{ source: 'fallback', price: 0.42 }],
            confidence: 'low',
            last_updated: new Date().toISOString()
        };
    }

    useFallbackPrices() {
        const fallback = this.getFallbackPricing();
        this.cache.set('current_prices', {
            data: fallback,
            timestamp: Date.now()
        });
        this.notifyObservers('price-update', fallback);
    }

    // ==========================================
    // UI INTEGRATION & OBSERVERS
    // ==========================================
    
    setupEventListeners() {
        // Listen for manual refresh requests
        window.addEventListener('pricing-refresh-requested', () => {
            this.updatePrices();
        });
        
        // Listen for configuration changes
        window.addEventListener('pricing-config-updated', (event) => {
            this.updateConfig(event.detail);
        });
    }

    subscribe(callback) {
        this.observers.push(callback);
        return () => {
            const index = this.observers.indexOf(callback);
            if (index > -1) this.observers.splice(index, 1);
        };
    }

    notifyObservers(event, data) {
        this.observers.forEach(callback => {
            try {
                callback(event, data);
            } catch (error) {
                console.error('Observer callback failed:', error);
            }
        });
    }

    // ==========================================
    // CACHE MANAGEMENT
    // ==========================================
    
    saveToCache() {
        try {
            const cacheData = {
                prices: Array.from(this.cache.entries()),
                history: this.priceHistory.slice(-100), // Last 100 entries
                timestamp: Date.now()
            };
            localStorage.setItem('evernode-pricing-cache', JSON.stringify(cacheData));
        } catch (error) {
            console.warn('Failed to save pricing cache:', error);
        }
    }

    loadFromCache() {
        try {
            const cached = localStorage.getItem('evernode-pricing-cache');
            if (cached) {
                const data = JSON.parse(cached);
                
                // Load cache entries
                this.cache = new Map(data.prices || []);
                
                // Load price history
                this.priceHistory = data.history || [];
                
                console.log('📚 Loaded pricing data from cache');
            }
        } catch (error) {
            console.warn('Failed to load pricing cache:', error);
        }
    }

    // ==========================================
    // UTILITY FUNCTIONS
    // ==========================================
    
    calculateMedian(numbers) {
        if (numbers.length === 0) return 0;
        const sorted = numbers.slice().sort((a, b) => a - b);
        const middle = Math.floor(sorted.length / 2);
        
        if (sorted.length % 2 === 0) {
            return (sorted[middle - 1] + sorted[middle]) / 2;
        } else {
            return sorted[middle];
        }
    }

    formatCurrency(amount, currency = 'USD') {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 2,
            maximumFractionDigits: currency === 'XRP' ? 6 : 2
        }).format(amount);
    }

    formatTime(hours) {
        if (hours < 1) {
            return `${Math.round(hours * 60)} minutes`;
        } else if (hours < 24) {
            return `${hours.toFixed(1)} hours`;
        } else {
            return `${(hours / 24).toFixed(1)} days`;
        }
    }

    updateConfig(newConfig) {
        this.config = { ...this.config, ...newConfig };
        this.saveToCache();
        this.notifyObservers('config-updated', this.config);
    }

    // ==========================================
    // PUBLIC API METHODS
    // ==========================================
    
    // Get current EVR price in USD
    getEVRPrice() {
        const pricing = this.getCurrentPricing();
        return pricing ? pricing.evr_usd : 0.22;
    }

    // Get current XRP price in USD  
    getXRPPrice() {
        const pricing = this.getCurrentPricing();
        return pricing ? pricing.xrp_usd : 0.42;
    }

    // Convert EVR to USD
    evrToUSD(evrAmount) {
        return evrAmount * this.getEVRPrice();
    }

    // Convert USD to EVR
    usdToEVR(usdAmount) {
        return usdAmount / this.getEVRPrice();
    }

    // Get price history for charts
    getPriceHistory(hours = 24) {
        const cutoff = Date.now() - (hours * 60 * 60 * 1000);
        return this.priceHistory.filter(entry => entry.timestamp > cutoff);
    }

    // Get current network pricing stats
    getNetworkStats() {
        const pricing = this.getCurrentPricing();
        const defaultHostType = this.config.default_host_type;
        const hostEVRRate = this.config.evernode_cost_ranges[defaultHostType];
        const hostCostPerHour = hostEVRRate * (pricing?.evr_usd || 0.22);
        
        return {
            evr_usd_rate: pricing?.evr_usd || 0.22,
            xrp_usd_rate: pricing?.xrp_usd || 0.42,
            host_type: defaultHostType,
            evr_cost_per_hour: hostEVRRate,
            evernode_cost_per_hour_usd: hostCostPerHour,
            evernode_cost_per_day_usd: hostCostPerHour * 24,
            evernode_cost_per_month_usd: hostCostPerHour * 24 * 30,
            confidence: pricing?.confidence || 'low',
            last_updated: pricing?.last_updated || new Date().toISOString()
        };
    }

    // Generate pricing display for UI
    generatePricingDisplay(appType = 'custom', hours = 24, hostType = 'medium') {
        const pricing = this.getCurrentPricing();
        if (!pricing) return null;

        const evrCostPerHour = this.config.evernode_cost_ranges[hostType];
        const costPerHour = evrCostPerHour * pricing.evr_usd;
        const totalCost = costPerHour * hours;
        const evrCost = evrCostPerHour * hours;

        return {
            usd: {
                hourly: this.formatCurrency(costPerHour),
                total: this.formatCurrency(totalCost),
                daily: this.formatCurrency(costPerHour * 24),
                monthly: this.formatCurrency(costPerHour * 24 * 30)
            },
            evr: {
                hourly: `${evrCostPerHour} EVR`,
                total: `${evrCost.toFixed(6)} EVR`,
                daily: `${(evrCostPerHour * 24).toFixed(6)} EVR`,
                monthly: `${(evrCostPerHour * 24 * 30).toFixed(4)} EVR`
            },
            host_type: hostType,
            host_ranges: {
                cheap: this.formatCurrency(this.config.evernode_cost_ranges.cheap * pricing.evr_usd * 24),
                medium: this.formatCurrency(this.config.evernode_cost_ranges.medium * pricing.evr_usd * 24), 
                premium: this.formatCurrency(this.config.evernode_cost_ranges.premium * pricing.evr_usd * 24)
            },
            comparison: this.generateCloudComparison(hours, hostType),
            confidence: pricing.confidence,
            last_updated: pricing.last_updated
        };
    }

    generateCloudComparison(hours = 24, hostType = 'medium') {
        const pricing = this.getCurrentPricing();
        if (!pricing) return null;

        const evrCostPerHour = this.config.evernode_cost_ranges[hostType];
        const evernode_cost = evrCostPerHour * pricing.evr_usd * hours;
        const aws_cost = this.config.cloud_rates.aws_t3_medium * hours;
        const savings = aws_cost - evernode_cost;
        const savings_percent = (savings / aws_cost) * 100;

        return {
            evernode_cost: this.formatCurrency(evernode_cost),
            aws_cost: this.formatCurrency(aws_cost),
            savings_usd: this.formatCurrency(savings),
            savings_percent: `${savings_percent.toFixed(1)}%`,
            is_cheaper: savings > 0,
            cost_multiplier: `${(aws_cost / evernode_cost).toFixed(0)}x cheaper`
        };
    }
}

// ==========================================
// GLOBAL INITIALIZATION
// ==========================================

// Initialize unified pricing system
if (typeof window !== 'undefined') {
    window.UnifiedPricing = new UnifiedPricingSystem();
    
    // Make pricing functions globally available
    window.calculateROI = (params) => window.UnifiedPricing.calculateClusterROI(params);
    window.calculateSingleTenantROI = (appType, hours, hostType) => window.UnifiedPricing.calculateSingleTenantROI(appType, hours, hostType);
    window.getEVRPrice = () => window.UnifiedPricing.getEVRPrice();
    window.getXRPPrice = () => window.UnifiedPricing.getXRPPrice();
    window.evrToUSD = (amount) => window.UnifiedPricing.evrToUSD(amount);
    window.usdToEVR = (amount) => window.UnifiedPricing.usdToEVR(amount);
    window.formatCurrency = (amount, currency) => window.UnifiedPricing.formatCurrency(amount, currency);
    
    console.log('💰 Unified Pricing System initialized with EVR support');
} else {
    module.exports = UnifiedPricingSystem;
}

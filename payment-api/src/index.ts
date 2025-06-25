import express from 'express';
import cors from 'cors';
import { Xumm } from 'xumm-sdk';
import dotenv from 'dotenv';
import fs from 'fs';

// 🔐 Load environment from secure location
const SECURE_ENV_PATH = '/opt/evernode-enhanced/secrets/.env';

if (fs.existsSync(SECURE_ENV_PATH)) {
    console.log('🔐 Loading secure environment from:', SECURE_ENV_PATH);
    dotenv.config({ path: SECURE_ENV_PATH });
} else {
    console.error('❌ Secure environment file not found!');
    process.exit(1);
}

// Validate required environment variables
const requiredVars = [
    'XUMM_API_KEY',
    'XUMM_API_SECRET',
    'COMMISSION_WALLET_ADDRESS',
    'COMMISSION_WALLET_SECRET',
    'XRP_RECEIVING_WALLET',
    'SESSION_SECRET',
    'API_SECRET'
];

const missingVars = requiredVars.filter(varName => !process.env[varName]);
if (missingVars.length > 0) {
    console.error('❌ Missing required environment variables:', missingVars.join(', '));
    console.log('💡 Check your /opt/evernode-enhanced/secrets/.env file');
    process.exit(1);
}

// Security: Mask secrets in logs
const maskSecret = (secret: string): string => {
    if (!secret || secret.length < 8) return '[MISSING]';
    return secret.substring(0, 4) + '****' + secret.substring(secret.length - 4);
};

console.log('🔑 Environment validation successful:');
console.log('  Domain:', process.env.DOMAIN);
console.log('  XUMM API Key:', maskSecret(process.env.XUMM_API_KEY || ''));
console.log('  Commission Wallet:', maskSecret(process.env.COMMISSION_WALLET_ADDRESS || ''));
console.log('  Receiving Wallet:', maskSecret(process.env.XRP_RECEIVING_WALLET || ''));
console.log('  Session Secret:', process.env.SESSION_SECRET ? 'Found' : 'Missing');
console.log('  API Secret:', process.env.API_SECRET ? 'Found' : 'Missing');

const app = express();
const PORT = process.env.PORT || 3000;

// Initialize Xumm SDK
const xumm = new Xumm(
    process.env.XUMM_API_KEY || '',
    process.env.XUMM_API_SECRET || ''
);

app.use(cors());
app.use(express.json());

// Security middleware
app.use((req, res, next) => {
    res.removeHeader('X-Powered-By');
    res.setHeader('X-Content-Type-Options', 'nosniff');
    res.setHeader('X-Frame-Options', 'DENY');
    res.setHeader('X-XSS-Protection', '1; mode=block');
    
    // Log requests (but not sensitive data)
    const logSafeUrl = req.url.replace(/[?&](secret|key|wallet)=[^&]*/gi, '$1=***');
    console.log(`${new Date().toISOString()} ${req.method} ${logSafeUrl}`);
    
    next();
});

// Health check endpoint
app.get('/api/status', (req, res) => {
    res.json({
        status: 'healthy',
        timestamp: new Date().toISOString(),
        version: '2.0.0',
        domain: process.env.DOMAIN,
        features: ['commission-payouts', 'nft-licenses', 'multi-currency', 'xumm-live-rates'],
        environment: process.env.NODE_ENV || 'development',
        commission_rate: process.env.COMMISSION_RATE,
        price_usd: process.env.PRICE_USD,
        supported_currencies: ['XRP', 'XAH', 'EVR']
    });
});

// Get crypto rate info (no hardcoded rates - Xumm handles conversion)
app.get('/api/crypto-rates', async (req, res) => {
    try {
        const priceUSD = parseFloat(process.env.PRICE_USD || '49.99');
        const commissionRate = parseFloat(process.env.COMMISSION_RATE || '0.20');
        
        res.json({
            success: true,
            priceUSD: priceUSD,
            commission: {
                rate: commissionRate,
                usd_amount: (priceUSD * commissionRate).toFixed(2)
            },
            supported_currencies: ['XRP', 'XAH', 'EVR'],
            conversion_method: 'xumm_live_rates',
            note: 'Live rates and exact amounts calculated by Xumm at payment time',
            timestamp: new Date().toISOString(),
            evr_issuer: process.env.EVR_ISSUER
        });
    } catch (error) {
        console.error('Rate info error:', error);
        res.status(500).json({
            success: false,
            error: 'Failed to fetch rate information'
        });
    }
});

// Create payment endpoint with Xumm live conversion
app.post('/api/create-payment', async (req, res) => {
    try {
        const { currency, referralCode, hostDomain } = req.body;
        
        if (!currency || !['XRP', 'XAH', 'EVR'].includes(currency.toUpperCase())) {
            return res.status(400).json({ 
                success: false, 
                error: 'Invalid currency. Must be XRP, XAH, or EVR' 
            });
        }
        
        // Get USD price from environment
        const priceUSD = process.env.PRICE_USD || '49.99';
        
        // Get receiving wallet for currency
        const receivingWallet = process.env[`${currency.toUpperCase()}_RECEIVING_WALLET`];
        
        if (!receivingWallet) {
            return res.status(400).json({
                success: false,
                error: `No receiving wallet configured for ${currency}`
            });
        }
        
        // Create payment payload - let Xumm handle conversion
        let paymentPayload: any = {
            TransactionType: 'Payment',
            Destination: receivingWallet,
            Memos: [{
                Memo: {
                    MemoType: Buffer.from('premium_purchase').toString('hex'),
                    MemoData: Buffer.from(JSON.stringify({
                        referralCode: referralCode || 'direct',
                        hostDomain: hostDomain || 'evrdirect.info',
                        currency: currency.toUpperCase(),
                        priceUSD: priceUSD,
                        timestamp: Date.now(),
                        version: '2.0.0'
                    })).toString('hex')
                }
            }]
        };

        // Set amount based on currency type
        if (currency.toUpperCase() === 'XRP') {
            // For XRP, use USD amount and let Xumm convert to XRP drops
            paymentPayload.Amount = {
                currency: 'USD',
                value: priceUSD,
                issuer: 'rvYAfWj5gh67oV6fW32ZzP3Aw4Eubs59B' // Bitstamp USD issuer
            };
        } else if (currency.toUpperCase() === 'XAH') {
            // For XAH (Xahau native), use USD conversion
            paymentPayload.Amount = {
                currency: 'USD', 
                value: priceUSD,
                issuer: 'rvYAfWj5gh67oV6fW32ZzP3Aw4Eubs59B'
            };
        } else if (currency.toUpperCase() === 'EVR') {
            // For EVR token, specify EVR currency with issuer
            paymentPayload.Amount = {
                currency: 'EVR',
                value: priceUSD,
                issuer: process.env.EVR_ISSUER
            };
        }
        
        // Create Xumm payment request
        const payload = await xumm.payload.create(paymentPayload);
        
        console.log(`💰 Payment created: $${priceUSD} USD in ${currency.toUpperCase()}, Referral: ${referralCode || 'direct'}`);
        
        res.json({
            success: true,
            paymentUrl: payload.next.always,
            uuid: payload.uuid,
            priceUSD: priceUSD,
            currency: currency.toUpperCase(),
            referralCode: referralCode || 'direct',
            hostDomain: hostDomain || 'evrdirect.info',
            conversion_method: 'xumm_live_rates',
            note: 'Exact crypto amount calculated by Xumm at current market rates'
        });
        
    } catch (error) {
        console.error('Payment creation error:', error);
        res.status(500).json({
            success: false,
            error: 'Failed to create payment',
            details: process.env.NODE_ENV === 'development' ? error.message : undefined
        });
    }
});

// Check payment status
app.get('/api/check-payment/:uuid', async (req, res) => {
    try {
        const { uuid } = req.params;
        
        if (!uuid) {
            return res.status(400).json({
                success: false,
                error: 'Payment UUID is required'
            });
        }
        
        // Get payment status from Xumm
        const payloadData = await xumm.payload.get(uuid);
        
        res.json({
            success: true,
            uuid: uuid,
            status: payloadData.meta.signed ? 'completed' : 'pending',
            signed: payloadData.meta.signed,
            resolved: payloadData.meta.resolved,
            return_url: payloadData.meta.return_url_app || payloadData.meta.return_url_web,
            timestamp: new Date().toISOString()
        });
        
    } catch (error) {
        console.error('Payment check error:', error);
        res.status(500).json({
            success: false,
            error: 'Failed to check payment status'
        });
    }
});

// Commission registration
app.post('/api/register-referral', async (req, res) => {
    try {
        const { hostDomain, hostWallet } = req.body;
        
        if (!hostDomain || !hostWallet) {
            return res.status(400).json({
                success: false,
                error: 'hostDomain and hostWallet are required'
            });
        }
        
        // In production, you'd save this to a database
        console.log(`📋 Referral registered: ${hostDomain} -> ${maskSecret(hostWallet)}`);
        
        res.json({
            success: true,
            message: 'Referral registered successfully',
            hostDomain,
            hostWallet: maskSecret(hostWallet),
            commissionRate: process.env.COMMISSION_RATE || '0.20',
            priceUSD: process.env.PRICE_USD || '49.99'
        });
        
    } catch (error) {
        console.error('Registration error:', error);
        res.status(500).json({
            success: false,
            error: 'Registration failed'
        });
    }
});

// Get commission stats for a host
app.get('/api/commission-stats/:hostDomain', async (req, res) => {
    try {
        const { hostDomain } = req.params;
        
        // In production, you'd fetch from database
        // For now, return demo data
        const stats = {
            total_earned: 0.00,
            total_earned_usd: 0.00,
            referral_count: 0,
            conversion_rate: 0,
            monthly_earnings: 0.00,
            commission_rate: process.env.COMMISSION_RATE || '0.20',
            supported_currencies: ['XRP', 'XAH', 'EVR']
        };
        
        res.json({
            success: true,
            hostDomain,
            stats,
            timestamp: new Date().toISOString()
        });
        
    } catch (error) {
        console.error('Commission stats error:', error);
        res.status(500).json({
            success: false,
            error: 'Failed to fetch commission stats'
        });
    }
});

// Test Xumm connection
app.get('/api/test-xumm', async (req, res) => {
    try {
        const pong = await xumm.ping();
        res.json({
            success: true,
            xumm_connected: true,
            pong,
            api_key: maskSecret(process.env.XUMM_API_KEY || ''),
            timestamp: new Date().toISOString()
        });
    } catch (error) {
        console.error('Xumm test error:', error);
        res.status(500).json({
            success: false,
            xumm_connected: false,
            error: 'Failed to connect to Xumm',
            details: process.env.NODE_ENV === 'development' ? error.message : undefined
        });
    }
});

// Get current rates from Xumm (optional endpoint for display)
app.get('/api/display-rates', async (req, res) => {
    try {
        // This endpoint can be used to show estimated rates to users
        // But actual conversion still happens in Xumm
        res.json({
            success: true,
            message: 'Live rates calculated by Xumm at payment time',
            priceUSD: process.env.PRICE_USD || '49.99',
            commission_rate: process.env.COMMISSION_RATE || '0.20',
            note: 'Use /api/create-payment to get exact amounts',
            timestamp: new Date().toISOString()
        });
    } catch (error) {
        res.status(500).json({
            success: false,
            error: 'Failed to fetch display rates'
        });
    }
});

// Error handling middleware
app.use((error: any, req: express.Request, res: express.Response, next: express.NextFunction) => {
    console.error('API Error:', error.message);
    
    res.status(500).json({
        success: false,
        error: process.env.NODE_ENV === 'production' ? 'Internal server error' : error.message,
        timestamp: new Date().toISOString()
    });
});

// 404 handler
app.use('*', (req, res) => {
    res.status(404).json({
        success: false,
        error: 'Endpoint not found',
        available_endpoints: [
            'GET /api/status',
            'GET /api/crypto-rates', 
            'POST /api/create-payment',
            'GET /api/check-payment/:uuid',
            'POST /api/register-referral',
            'GET /api/commission-stats/:hostDomain',
            'GET /api/test-xumm'
        ],
        timestamp: new Date().toISOString()
    });
});

// Graceful shutdown
process.on('SIGTERM', () => {
    console.log('🔐 Received SIGTERM, shutting down gracefully');
    process.exit(0);
});

process.on('SIGINT', () => {
    console.log('🔐 Received SIGINT, shutting down gracefully');  
    process.exit(0);
});

app.listen(PORT, () => {
    console.log(`🚀 Enhanced Evernode Payment API running on port ${PORT}`);
    console.log(`🔐 Environment loaded successfully`);
    console.log(`💰 Commission rate: ${process.env.COMMISSION_RATE}%`);
    console.log(`💵 Price: $${process.env.PRICE_USD} USD`);
    console.log(`🌐 Domain: ${process.env.DOMAIN}`);
    console.log(`💎 Ready to process payments with Xumm live conversion!`);
    console.log(`📱 Supported currencies: XRP, XAH, EVR`);
});

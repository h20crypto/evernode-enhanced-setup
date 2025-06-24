import express from 'express';
import cors from 'cors';
import { Xumm } from 'xumm-sdk';
import { Client } from 'xrpl';
import dotenv from 'dotenv';
import crypto from 'crypto';

dotenv.config();

const app = express();
const PORT = process.env.PORT || 3000;

// Initialize Xumm SDK
const xumm = new Xumm(
  process.env.XUMM_API_KEY || '',
  process.env.XUMM_API_SECRET || ''
);

// Initialize XRPL clients for commission payouts
const xrplClient = new Client('wss://xrplcluster.com');
const xahauClient = new Client('wss://xahau.network');

// Commission wallet configuration
const commissionWallet = {
  address: process.env.COMMISSION_WALLET_ADDRESS || '',
  secret: process.env.COMMISSION_WALLET_SECRET || ''
};

app.use(cors());
app.use(express.json());

// Types
interface PaymentRequest {
  currency: string;
  priceUsd: number;
  referralCode?: string;
  hostDomain?: string;
  customerWallet?: string;
}

interface ReferralData {
  code: string;
  hostWallet: string;
  hostDomain: string;
  commissionRate: number;
}

// Rate caching to avoid excessive API calls
interface CachedRate {
  rate: number;
  source: string;
  timestamp: number;
  quality: string;
}

const rateCache = new Map<string, CachedRate>();
const CACHE_DURATION = 5 * 60 * 1000; // 5 minutes

// In-memory storage (replace with database in production)
const referrals = new Map<string, ReferralData>();
const sales = new Map<string, any>();
const nftLicenses = new Map<string, any>();

function getCachedRate(currency: string): CachedRate | null {
  const cached = rateCache.get(currency);
  if (cached && (Date.now() - cached.timestamp) < CACHE_DURATION) {
    return cached;
  }
  return null;
}

function setCachedRate(currency: string, rate: number, source: string, quality: string = 'good') {
  rateCache.set(currency, {
    rate: rate,
    source: source,
    timestamp: Date.now(),
    quality: quality
  });
}

// Health check endpoint
app.get('/api/status', (req, res) => {
  res.json({ 
    success: true, 
    message: 'Evernode Enhanced Payment API with Commission & NFT',
    timestamp: new Date().toISOString(),
    features: ['multi_currency', 'commission_payouts', 'nft_licensing', 'referral_tracking']
  });
});

// Get live crypto rates with multiple sources for accuracy
app.get('/api/crypto-rates', async (req, res) => {
  try {
    const rates: any = {};
    const sources: any = {};
    const cacheInfo: any = {};
    const errors: string[] = [];

    // Check cache first for each currency
    for (const currency of ['XRP', 'XAH', 'EVR']) {
      const cached = getCachedRate(currency);
      if (cached && cached.quality === 'good') { // Only use good quality cached rates
        rates[currency] = cached.rate;
        sources[currency] = `${cached.source} (cached)`;
        cacheInfo[currency] = {
          cached: true,
          age: Math.floor((Date.now() - cached.timestamp) / 1000),
          quality: cached.quality
        };
      }
    }

    // Get XRP rate from multiple sources (if not cached)
    if (!rates.XRP) {
      try {
        // Primary: Xumm API
        const xummRates = await xumm.helpers.getRates('USD');
        if (xummRates.XRP && xummRates.XRP > 0.1 && xummRates.XRP < 10) {
          rates.XRP = xummRates.XRP;
          sources.XRP = 'Xumm API';
          setCachedRate('XRP', rates.XRP, 'Xumm API', 'good');
          cacheInfo.XRP = { cached: false, fresh: true };
        } else {
          throw new Error('Invalid XRP rate from Xumm');
        }
      } catch (error) {
        console.warn('Xumm XRP rate failed, trying CoinGecko...');
        try {
          // Fallback: CoinGecko
          const cgResponse = await fetch('https://api.coingecko.com/api/v3/simple/price?ids=ripple&vs_currencies=usd');
          const cgData = await cgResponse.json();
          if (cgData.ripple?.usd && cgData.ripple.usd > 0.1 && cgData.ripple.usd < 10) {
            rates.XRP = cgData.ripple.usd;
            sources.XRP = 'CoinGecko API';
            setCachedRate('XRP', rates.XRP, 'CoinGecko API', 'good');
            cacheInfo.XRP = { cached: false, fresh: true };
          } else {
            throw new Error('Invalid CoinGecko data');
          }
        } catch (cgError) {
          errors.push('XRP: All sources failed');
          console.error('Failed to get XRP rate from any source');
        }
      }
    }

    // Get EVR rate from official Evernode API (if not cached)
    if (!rates.EVR) {
      try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 8000); // 8 second timeout
        
        const evrResponse = await fetch('https://api.evernode.network/supply/money', {
          signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        
        if (!evrResponse.ok) {
          throw new Error(`Evernode API returned ${evrResponse.status}`);
        }
        
        const evrData = await evrResponse.json();
        
        if (evrData.currentPrice && evrData.currentPrice > 0.01 && evrData.currentPrice < 2) {
          rates.EVR = evrData.currentPrice;
          sources.EVR = 'Evernode Network API (Official)';
          setCachedRate('EVR', rates.EVR, 'Evernode API', 'good');
          cacheInfo.EVR = { cached: false, fresh: true };
        } else {
          throw new Error(`Invalid EVR price: ${evrData.currentPrice}`);
        }
        
      } catch (error) {
        errors.push('EVR: Official Evernode API failed');
        console.error('Failed to get EVR rate from Evernode API:', error.message);
      }
    }

    // Get XAH rate (if not cached)
    if (!rates.XAH) {
      try {
        const xummRates = await xumm.helpers.getRates('USD');
        if (xummRates.XAH && xummRates.XAH > 0.01 && xummRates.XAH < 5) {
          rates.XAH = xummRates.XAH;
          sources.XAH = 'Xumm API';
          setCachedRate('XAH', rates.XAH, 'Xumm API', 'good');
          cacheInfo.XAH = { cached: false, fresh: true };
        } else {
          throw new Error('XAH rate not available or invalid from Xumm');
        }
      } catch (error) {
        // Only use XRP-based estimate if XRP rate is available and recent
        if (rates.XRP && !cacheInfo.XRP?.cached) {
          rates.XAH = parseFloat((rates.XRP * 0.34).toFixed(4));
          sources.XAH = 'Calculated (based on fresh XRP rate)';
          setCachedRate('XAH', rates.XAH, 'XRP-based calculation', 'calculated');
          cacheInfo.XAH = { cached: false, fresh: true, calculated: true };
        } else {
          errors.push('XAH: No reliable rate available');
          console.error('Failed to get XAH rate from any source');
        }
      }
    }

    // Check if we have all required rates
    const requiredCurrencies = ['XRP', 'XAH', 'EVR'];
    const missingCurrencies = requiredCurrencies.filter(currency => !rates[currency]);
    
    if (missingCurrencies.length > 0) {
      return res.status(503).json({
        success: false,
        error: 'Unable to fetch reliable rates for all currencies',
        missing_currencies: missingCurrencies,
        available_rates: Object.keys(rates),
        errors: errors,
        message: 'Payment system temporarily unavailable due to rate feed issues'
      });
    }

    // All rates available - return success
    res.json({
      success: true,
      rates: {
        XRP: parseFloat(rates.XRP.toFixed(4)),
        XAH: parseFloat(rates.XAH.toFixed(4)),
        EVR: parseFloat(rates.EVR.toFixed(6))
      },
      sources: sources,
      cache_info: cacheInfo,
      timestamp: new Date().toISOString(),
      data_quality: 'reliable',
      cache_ttl: 300 // 5 minutes
    });

  } catch (error) {
    console.error('Complete rate system failure:', error);
    
    // Check if we have any good cached rates to fall back to
    const cachedRates: any = {};
    const cachedSources: any = {};
    let validCachedCount = 0;
    
    for (const currency of ['XRP', 'XAH', 'EVR']) {
      const cached = rateCache.get(currency);
      if (cached && cached.quality === 'good' && (Date.now() - cached.timestamp) < (60 * 60 * 1000)) { // 1 hour max for emergency
        cachedRates[currency] = cached.rate;
        cachedSources[currency] = `${cached.source} (stale cache)`;
        validCachedCount++;
      }
    }
    
    if (validCachedCount === 3) {
      // We have all rates from cache (within 1 hour)
      return res.status(200).json({
        success: true,
        rates: cachedRates,
        sources: cachedSources,
        timestamp: new Date().toISOString(),
        data_quality: 'stale_cache',
        warning: 'Using recent cached rates due to API failures - rates may be slightly outdated'
      });
    }
    
    // Complete failure - return error
    res.status(503).json({
      success: false,
      error: 'Rate system completely unavailable',
      message: 'Unable to fetch current cryptocurrency rates. Please try again in a few minutes.',
      available_cached: Object.keys(cachedRates),
      timestamp: new Date().toISOString()
    });
  }
});

// Register referral host
app.post('/api/register-referral', (req, res) => {
  try {
    const { hostDomain, hostWallet, customCode } = req.body;
    
    if (!hostDomain || !hostWallet) {
      return res.status(400).json({
        success: false,
        error: 'Missing hostDomain or hostWallet'
      });
    }

    // Generate referral code
    const referralCode = customCode || generateReferralCode(hostDomain);
    
    referrals.set(referralCode, {
      code: referralCode,
      hostWallet: hostWallet,
      hostDomain: hostDomain,
      commissionRate: 0.20 // 20%
    });

    res.json({
      success: true,
      referralCode: referralCode,
      hostDomain: hostDomain,
      commissionRate: 20
    });

  } catch (error) {
    console.error('Referral registration error:', error);
    res.status(500).json({
      success: false,
      error: 'Failed to register referral'
    });
  }
});

// Create enhanced payment request with referral tracking
app.post('/api/create-payment', async (req, res) => {
  try {
    const { currency, priceUsd, referralCode, hostDomain, customerWallet }: PaymentRequest = req.body;
    
    if (!currency || !priceUsd) {
      return res.status(400).json({
        success: false,
        error: 'Missing currency or priceUsd'
      });
    }

    // Get current rates
    const ratesResponse = await fetch(`http://localhost:${PORT}/api/crypto-rates`);
    const ratesData = await ratesResponse.json();
    const cryptoRate = ratesData.rates[currency.toUpperCase()];
    
    if (!cryptoRate) {
      return res.status(400).json({
        success: false,
        error: `Unsupported currency: ${currency}`
      });
    }

    const amountCrypto = (priceUsd / cryptoRate).toFixed(6);
    const commissionAmount = (parseFloat(amountCrypto) * 0.20).toFixed(6); // 20% commission
    
    // Format amount based on currency
    let paymentAmount: string | object;
    let commissionAmountFormatted: string | object;
    
    if (currency.toUpperCase() === 'EVR') {
      // EVR is an issued currency
      paymentAmount = {
        currency: 'EVR',
        issuer: process.env.EVR_ISSUER || 'rEvernodeIssuerAddress',
        value: amountCrypto
      };
      commissionAmountFormatted = {
        currency: 'EVR',
        issuer: process.env.EVR_ISSUER || 'rEvernodeIssuerAddress',
        value: commissionAmount
      };
    } else {
      // XRP/XAH are native currencies (in drops)
      const drops = String(Math.floor(parseFloat(amountCrypto) * 1000000));
      const commissionDrops = String(Math.floor(parseFloat(commissionAmount) * 1000000));
      paymentAmount = drops;
      commissionAmountFormatted = commissionDrops;
    }

    // Generate unique sale ID
    const saleId = crypto.randomUUID();
    
    // Create memo with referral and sale data
    const memoData = JSON.stringify({
      saleId: saleId,
      referralCode: referralCode || null,
      hostDomain: hostDomain || null,
      currency: currency.toUpperCase(),
      priceUsd: priceUsd,
      commissionAmount: commissionAmount,
      timestamp: Date.now()
    });

    // Create Xumm payment request
    const payload = await xumm.payload.create({
      txjson: {
        TransactionType: 'Payment',
        Destination: getReceivingWallet(currency),
        Amount: paymentAmount,
        Memos: [{
          Memo: {
            MemoType: Buffer.from('premium_purchase').toString('hex'),
            MemoData: Buffer.from(memoData).toString('hex')
          }
        }]
      },
      options: {
        submit: true,
        multisign: false,
        expire: 1440 // 24 hours
      }
    });

    // Store sale data for tracking
    sales.set(saleId, {
      saleId: saleId,
      uuid: payload.uuid,
      currency: currency.toUpperCase(),
      amount: parseFloat(amountCrypto),
      priceUsd: priceUsd,
      commissionAmount: parseFloat(commissionAmount),
      referralCode: referralCode || null,
      hostDomain: hostDomain || null,
      customerWallet: customerWallet || null,
      status: 'pending',
      created: new Date().toISOString()
    });

    res.json({
      success: true,
      saleId: saleId,
      uuid: payload.uuid,
      qr: payload.refs.qr_png,
      websocket: payload.refs.websocket_status,
      amount: parseFloat(amountCrypto),
      currency: currency.toUpperCase(),
      priceUsd: priceUsd,
      commissionAmount: parseFloat(commissionAmount),
      rate: cryptoRate,
      referralInfo: referralCode ? {
        code: referralCode,
        hostWallet: referrals.get(referralCode)?.hostWallet,
        commission: `${commissionAmount} ${currency.toUpperCase()}`
      } : null
    });

  } catch (error) {
    console.error('Payment creation error:', error);
    res.status(500).json({
      success: false,
      error: 'Failed to create payment request'
    });
  }
});

// Enhanced payment status check with automatic processing
app.get('/api/check-payment/:uuid', async (req, res) => {
  try {
    const { uuid } = req.params;
    const payload = await xumm.payload.get(uuid);
    
    if (payload.response.signed && payload.response.txid) {
      // Find sale by UUID
      const sale = Array.from(sales.values()).find(s => s.uuid === uuid);
      
      if (sale && sale.status === 'pending') {
        // Process the successful payment
        await processSuccessfulPayment(sale, payload.response.txid, payload.response.account);
      }
      
      res.json({
        success: true,
        paid: true,
        txid: payload.response.txid,
        saleId: sale?.saleId,
        nftTokenId: sale?.nftTokenId,
        commissionPaid: sale?.commissionTxId ? true : false
      });
    } else {
      res.json({
        success: true,
        paid: false,
        expired: payload.response.expired
      });
    }
  } catch (error) {
    console.error('Payment check error:', error);
    res.status(500).json({
      success: false,
      error: 'Failed to check payment status'
    });
  }
});

// Process successful payment: mint NFT and pay commission
async function processSuccessfulPayment(sale: any, txId: string, customerWallet: string) {
  try {
    console.log(`Processing successful payment for sale ${sale.saleId}`);
    
    // Update sale with customer wallet and transaction
    sale.customerWallet = customerWallet;
    sale.paymentTxId = txId;
    sale.status = 'paid';
    
    // 1. Mint NFT license
    const nftTokenId = await mintPremiumLicense(customerWallet, sale.currency, sale.saleId);
    sale.nftTokenId = nftTokenId;
    
    // 2. Pay commission if referral exists
    if (sale.referralCode) {
      const referralData = referrals.get(sale.referralCode);
      if (referralData) {
        const commissionTxId = await payCommission(
          referralData.hostWallet,
          sale.currency,
          sale.commissionAmount,
          txId
        );
        sale.commissionTxId = commissionTxId;
        console.log(`Commission paid: ${commissionTxId}`);
      }
    }
    
    sale.status = 'completed';
    sale.processed = new Date().toISOString();
    
    console.log(`Sale ${sale.saleId} processed successfully`);
    
  } catch (error) {
    console.error('Payment processing error:', error);
    sale.status = 'error';
    sale.error = error.message;
  }
}

// Mint Premium License NFT
async function mintPremiumLicense(customerWallet: string, currency: string, saleId: string): Promise<string> {
  try {
    // Choose network based on currency
    const client = currency === 'XAH' ? xahauClient : xrplClient;
    
    if (!client.isConnected()) {
      await client.connect();
    }
    
    // Create NFT metadata
    const metadata = {
      name: "Enhanced Evernode Premium License",
      description: "Lifetime access to Premium Cluster Manager",
      image: "https://enhanced-evernode.com/nft/premium-license.png",
      attributes: [
        { trait_type: "License Type", value: "Premium Cluster Manager" },
        { trait_type: "Access Level", value: "Lifetime" },
        { trait_type: "Payment Currency", value: currency },
        { trait_type: "Sale ID", value: saleId },
        { trait_type: "Issue Date", value: new Date().toISOString() }
      ]
    };
    
    // For demo purposes, we'll create a simple NFT
    // In production, upload metadata to IPFS first
    const metadataURI = `https://enhanced-evernode.com/nft/metadata/${saleId}.json`;
    
    const nftTx = {
      TransactionType: 'NFTokenMint',
      Account: commissionWallet.address,
      NFTokenTaxon: 12345, // Premium license taxon
      Flags: 8, // tfTransferable
      URI: Buffer.from(metadataURI).toString('hex'),
      Destination: customerWallet
    };
    
    const wallet = await client.fundWallet();
    const result = await client.submitAndWait(nftTx, { wallet });
    
    // Extract NFT Token ID from transaction result
    const nftTokenId = result.result.meta?.nftoken_id || `DEMO_NFT_${saleId}`;
    
    // Store license info
    nftLicenses.set(nftTokenId, {
      tokenId: nftTokenId,
      owner: customerWallet,
      saleId: saleId,
      currency: currency,
      metadata: metadata,
      created: new Date().toISOString()
    });
    
    return nftTokenId;
    
  } catch (error) {
    console.error('NFT minting error:', error);
    // Return demo token for development
    const demoTokenId = `DEMO_NFT_${saleId}_${Date.now()}`;
    nftLicenses.set(demoTokenId, {
      tokenId: demoTokenId,
      owner: customerWallet,
      saleId: saleId,
      currency: currency,
      demo: true,
      created: new Date().toISOString()
    });
    return demoTokenId;
  }
}

// Pay commission to referring host
async function payCommission(hostWallet: string, currency: string, amount: number, originalTxId: string): Promise<string> {
  try {
    // Choose network and format amount
    const client = currency === 'XAH' ? xahauClient : xrplClient;
    let commissionAmount: string | object;
    
    if (currency === 'EVR') {
      commissionAmount = {
        currency: 'EVR',
        issuer: process.env.EVR_ISSUER || 'rEvernodeIssuerAddress',
        value: amount.toString()
      };
    } else {
      // XRP/XAH in drops
      commissionAmount = String(Math.floor(amount * 1000000));
    }
    
    if (!client.isConnected()) {
      await client.connect();
    }
    
    const commissionTx = {
      TransactionType: 'Payment',
      Account: commissionWallet.address,
      Destination: hostWallet,
      Amount: commissionAmount,
      Memos: [{
        Memo: {
          MemoType: Buffer.from('commission').toString('hex'),
          MemoData: Buffer.from(`Premium referral commission: ${originalTxId}`).toString('hex')
        }
      }]
    };
    
    const wallet = await client.fundWallet();
    const result = await client.submitAndWait(commissionTx, { wallet });
    
    return result.result.hash;
    
  } catch (error) {
    console.error('Commission payment error:', error);
    // Return demo transaction ID for development
    return `DEMO_COMMISSION_${Date.now()}`;
  }
}

// Get host commission history
app.get('/api/commission-history/:hostWallet', (req, res) => {
  try {
    const { hostWallet } = req.params;
    
    // Find all sales with commissions for this host
    const hostCommissions = Array.from(sales.values()).filter(sale => {
      if (!sale.referralCode) return false;
      const referralData = referrals.get(sale.referralCode);
      return referralData?.hostWallet === hostWallet && sale.commissionTxId;
    });
    
    const totalEarned = hostCommissions.reduce((sum, sale) => sum + sale.commissionAmount, 0);
    
    res.json({
      success: true,
      hostWallet: hostWallet,
      commissions: hostCommissions,
      totalEarned: totalEarned,
      count: hostCommissions.length
    });
    
  } catch (error) {
    console.error('Commission history error:', error);
    res.status(500).json({
      success: false,
      error: 'Failed to fetch commission history'
    });
  }
});

// Verify NFT license
app.post('/api/verify-license', (req, res) => {
  try {
    const { wallet } = req.body;
    
    // Find valid licenses for this wallet
    const userLicenses = Array.from(nftLicenses.values()).filter(license => 
      license.owner === wallet
    );
    
    if (userLicenses.length > 0) {
      res.json({
        success: true,
        valid: true,
        licenses: userLicenses,
        premiumFeatures: [
          'advanced_cluster_management',
          'auto_scaling',
          'real_time_monitoring', 
          'priority_support'
        ]
      });
    } else {
      res.json({
        success: true,
        valid: false,
        licenses: []
      });
    }
    
  } catch (error) {
    console.error('License verification error:', error);
    res.status(500).json({
      success: false,
      error: 'Failed to verify license'
    });
  }
});

// Get sales analytics
app.get('/api/analytics', (req, res) => {
  try {
    const allSales = Array.from(sales.values());
    const completedSales = allSales.filter(sale => sale.status === 'completed');
    
    const analytics = {
      totalSales: completedSales.length,
      totalRevenue: completedSales.reduce((sum, sale) => sum + sale.priceUsd, 0),
      totalCommissions: completedSales.reduce((sum, sale) => sum + (sale.commissionAmount || 0), 0),
      currencyBreakdown: {},
      monthlyData: {}
    };
    
    // Currency breakdown
    completedSales.forEach(sale => {
      if (!analytics.currencyBreakdown[sale.currency]) {
        analytics.currencyBreakdown[sale.currency] = { count: 0, revenue: 0 };
      }
      analytics.currencyBreakdown[sale.currency].count++;
      analytics.currencyBreakdown[sale.currency].revenue += sale.priceUsd;
    });
    
    res.json({
      success: true,
      analytics: analytics
    });
    
  } catch (error) {
    console.error('Analytics error:', error);
    res.status(500).json({
      success: false,
      error: 'Failed to fetch analytics'
    });
  }
});

// Utility functions
function generateReferralCode(hostDomain: string): string {
  const hash = crypto.createHash('md5').update(hostDomain).digest('hex');
  return hash.substring(0, 8).toUpperCase();
}

function getReceivingWallet(currency: string): string {
  const wallets = {
    XRP: process.env.XRP_RECEIVING_WALLET || process.env.RECEIVING_WALLET,
    XAH: process.env.XAH_RECEIVING_WALLET || process.env.RECEIVING_WALLET,
    EVR: process.env.EVR_RECEIVING_WALLET || process.env.RECEIVING_WALLET
  };
  
  return wallets[currency.toUpperCase()] || process.env.RECEIVING_WALLET || 'rYourWalletAddressHere';
}

app.listen(PORT, () => {
  console.log(`🚀 Enhanced Evernode Payment API running on port ${PORT}`);
  console.log(`📊 Status: http://localhost:${PORT}/api/status`);
  console.log(`💰 Rates: http://localhost:${PORT}/api/crypto-rates`);
  console.log(`🎫 Features: Commission payouts, NFT licensing, Referral tracking`);
});

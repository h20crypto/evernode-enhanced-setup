import express from 'express';
import cors from 'cors';
import { Xumm } from 'xumm-sdk';
import dotenv from 'dotenv';

dotenv.config();

const app = express();
const PORT = process.env.PORT || 3000;

// Initialize Xumm SDK
const xumm = new Xumm(
  process.env.XUMM_API_KEY || '',
  process.env.XUMM_API_SECRET || ''
);

app.use(cors());
app.use(express.json());

// Health check endpoint
app.get('/api/status', (req, res) => {
  res.json({ 
    success: true, 
    message: 'Evernode Enhanced Payment API is running',
    timestamp: new Date().toISOString()
  });
});

// Get live crypto rates
app.get('/api/crypto-rates', async (req, res) => {
  try {
    const rates = await xumm.helpers.getRates('USD');
    res.json({
      success: true,
      rates: {
        XRP: rates.XRP || 0.5,
        XAH: rates.XAH || 0.01
      },
      timestamp: new Date().toISOString()
    });
  } catch (error) {
    console.error('Rate fetch error:', error);
    res.status(500).json({
      success: false,
      error: 'Failed to fetch rates',
      fallback_rates: {
        XRP: 0.5,
        XAH: 0.01
      }
    });
  }
});

// Create professional payment request
app.post('/api/create-payment', async (req, res) => {
  try {
    const { currency, priceUsd } = req.body;
    
    if (!currency || !priceUsd) {
      return res.status(400).json({
        success: false,
        error: 'Missing currency or priceUsd'
      });
    }

    // Get current rates
    const rates = await xumm.helpers.getRates('USD');
    const cryptoRate = rates[currency.toUpperCase()] || (currency.toLowerCase() === 'xrp' ? 0.5 : 0.01);
    const amountCrypto = (priceUsd / cryptoRate).toFixed(6);
    
    // Convert to drops for XRPL
    const drops = currency.toLowerCase() === 'xrp' 
      ? String(Math.floor(parseFloat(amountCrypto) * 1000000))
      : amountCrypto;

    // Create Xumm payment request
    const payload = await xumm.payload.create({
      txjson: {
        TransactionType: 'Payment',
        Destination: process.env.RECEIVING_WALLET || 'rYourWalletAddressHere',
        Amount: drops,
        DestinationTag: Math.floor(Math.random() * 4294967295) // Random dest tag for tracking
      },
      options: {
        submit: true,
        multisign: false,
        expire: 1440 // 24 hours
      }
    });

    res.json({
      success: true,
      uuid: payload.uuid,
      qr: payload.refs.qr_png,
      websocket: payload.refs.websocket_status,
      amount: parseFloat(amountCrypto),
      currency: currency.toUpperCase(),
      priceUsd: priceUsd,
      rate: cryptoRate,
      destinationTag: payload.uuid
    });

  } catch (error) {
    console.error('Payment creation error:', error);
    res.status(500).json({
      success: false,
      error: 'Failed to create payment request'
    });
  }
});

// Check payment status
app.get('/api/check-payment/:uuid', async (req, res) => {
  try {
    const { uuid } = req.params;
    const payload = await xumm.payload.get(uuid);
    
    if (payload.response.signed) {
      res.json({
        success: true,
        paid: true,
        txid: payload.response.txid,
        license: generateLicense()
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

// Simple license generation (replace with your logic)
function generateLicense(): string {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  let result = '';
  for (let i = 0; i < 16; i++) {
    result += chars.charAt(Math.floor(Math.random() * chars.length));
    if (i === 3 || i === 7 || i === 11) result += '-';
  }
  return result;
}

app.listen(PORT, () => {
  console.log(`🚀 Evernode Enhanced Payment API running on port ${PORT}`);
  console.log(`📊 Status: http://localhost:${PORT}/api/status`);
  console.log(`💰 Rates: http://localhost:${PORT}/api/crypto-rates`);
});

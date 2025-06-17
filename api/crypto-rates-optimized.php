<?php
// api/crypto-rates-optimized.php - Updated with correct CoinGecko endpoints

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

class OptimizedCoinGeckoRates {
    private $license_usd = 49.99;
    private $cache_dir = 'rates_cache/';
    
    // Correct CoinGecko endpoints
    private $coingecko_endpoints = [
        'xrp' => 'https://api.coingecko.com/api/v3/simple/price?ids=ripple&vs_currencies=usd',
        'xah' => 'https://api.coingecko.com/api/v3/simple/price?ids=xahau&vs_currencies=usd', 
        'evr' => 'https://api.coingecko.com/api/v3/simple/price?ids=evernode&vs_currencies=usd'
    ];
    
    private $coin_ids = [
        'xrp' => 'ripple',
        'xah' => 'xahau',
        'evr' => 'evernode'
    ];
    
    public function __construct() {
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }
    
    public function getRates($mode = 'balanced') {
        $cache_duration = $this->getCacheDuration($mode);
        
        // Try to get rates from cache first
        $cached_rates = $this->getCachedRates($cache_duration);
        if ($cached_rates) {
            return $cached_rates;
        }
        
        // Fetch fresh rates from CoinGecko
        $fresh_rates = $this->fetchFreshRates();
        
        if ($fresh_rates['success']) {
            $this->cacheRates($fresh_rates['data']);
            return $fresh_rates['data'];
        }
        
        // All failed, try extended cache
        $extended_cache = $this->getCachedRates(3600); // 1 hour
        if ($extended_cache) {
            $extended_cache['source'] = 'extended_cache';
            $extended_cache['warning'] = 'Using older cached rates';
            return $extended_cache;
        }
        
        // Everything failed
        return $this->getFailureResponse();
    }
    
    private function fetchFreshRates() {
        $rates = [];
        $successful_fetches = 0;
        $errors = [];
        
        foreach ($this->coingecko_endpoints as $token => $url) {
            try {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 8,
                        'user_agent' => 'Enhanced-Evernode-Host/1.0'
                    ]
                ]);
                
                $response = file_get_contents($url, false, $context);
                
                if ($response) {
                    $data = json_decode($response, true);
                    $coin_id = $this->coin_ids[$token];
                    
                    if (isset($data[$coin_id]['usd'])) {
                        $rate = floatval($data[$coin_id]['usd']);
                        
                        if ($this->isValidRate($token, $rate)) {
                            $rates[$token] = $rate;
                            $successful_fetches++;
                        } else {
                            $errors[$token] = "Invalid rate: $rate";
                        }
                    } else {
                        $errors[$token] = "No USD price in response";
                    }
                } else {
                    $errors[$token] = "No response from CoinGecko";
                }
                
                // Be nice to CoinGecko - small delay between requests
                usleep(200000); // 0.2 seconds
                
            } catch (Exception $e) {
                $errors[$token] = $e->getMessage();
            }
        }
        
        // Need at least 2 out of 3 tokens
        if ($successful_fetches >= 2) {
            // Fill missing rates with estimates
            $rates = $this->fillMissingRates($rates);
            
            $response_data = $this->buildRateResponse($rates, 'coingecko', 'high');
            $response_data['tokens_fetched'] = $successful_fetches;
            $response_data['errors'] = $errors;
            
            return ['success' => true, 'data' => $response_data];
        }
        
        return [
            'success' => false, 
            'error' => "Only {$successful_fetches}/3 rates fetched",
            'errors' => $errors
        ];
    }
    
    private function fillMissingRates($rates) {
        // Fallback rates (update these periodically)
        $fallbacks = [
            'xrp' => 2.45,
            'xah' => 0.04,
            'evr' => 0.22
        ];
        
        // If we have XRP, estimate others based on typical ratios
        if (isset($rates['xrp'])) {
            $xrp_rate = $rates['xrp'];
            
            if (!isset($rates['xah'])) {
                $estimated_xah = $xrp_rate * 0.016; // ~1.6% of XRP
                $rates['xah'] = $this->isValidRate('xah', $estimated_xah) ? $estimated_xah : $fallbacks['xah'];
            }
            
            if (!isset($rates['evr'])) {
                $estimated_evr = $xrp_rate * 0.09; // ~9% of XRP
                $rates['evr'] = $this->isValidRate('evr', $estimated_evr) ? $estimated_evr : $fallbacks['evr'];
            }
        }
        
        // Fill any remaining missing rates with fallbacks
        foreach ($fallbacks as $token => $fallback) {
            if (!isset($rates[$token])) {
                $rates[$token] = $fallback;
            }
        }
        
        return $rates;
    }
    
    private function buildRateResponse($rates, $source, $confidence) {
        return [
            'xah' => [
                'rate' => $rates['xah'],
                'amount_for_license' => round($this->license_usd / $rates['xah'], 2),
                'display' => '~' . number_format(round($this->license_usd / $rates['xah'], 0)) . ' XAH',
                'source' => $source
            ],
            'xrp' => [
                'rate' => $rates['xrp'],
                'amount_for_license' => round($this->license_usd / $rates['xrp'], 2),
                'display' => '~' . round($this->license_usd / $rates['xrp'], 1) . ' XRP',
                'source' => $source
            ],
            'evr' => [
                'rate' => $rates['evr'],
                'amount_for_license' => round($this->license_usd / $rates['evr'], 2),
                'display' => '~' . number_format(round($this->license_usd / $rates['evr'], 0)) . ' EVR',
                'source' => $source
            ],
            'license_usd' => $this->license_usd,
            'mode' => 'balanced',
            'timestamp' => time(),
            'last_updated' => date('Y-m-d H:i:s'),
            'confidence' => $confidence,
            'success' => true
        ];
    }
    
    private function isValidRate($token, $rate) {
        $ranges = [
            'xrp' => ['min' => 0.10, 'max' => 50.00],
            'xah' => ['min' => 0.001, 'max' => 5.00],
            'evr' => ['min' => 0.001, 'max' => 10.00]
        ];
        
        if (!isset($ranges[$token])) return false;
        
        $range = $ranges[$token];
        return $rate >= $range['min'] && $rate <= $range['max'];
    }
    
    private function getCacheDuration($mode) {
        $durations = [
            'realtime' => 30,
            'accurate' => 60,
            'balanced' => 120,
            'cheap' => 300
        ];
        
        return $durations[$mode] ?? 120;
    }
    
    private function getCachedRates($max_age) {
        $cache_file = $this->cache_dir . 'coingecko_rates.json';
        
        if (file_exists($cache_file)) {
            $cache_age = time() - filemtime($cache_file);
            
            if ($cache_age < $max_age) {
                $cached_data = json_decode(file_get_contents($cache_file), true);
                
                if ($cached_data && $cached_data['success']) {
                    $cached_data['source'] = 'cache';
                    $cached_data['cache_age'] = $cache_age;
                    return $cached_data;
                }
            }
        }
        
        return null;
    }
    
    private function cacheRates($data) {
        $cache_file = $this->cache_dir . 'coingecko_rates.json';
        file_put_contents($cache_file, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    private function getFailureResponse() {
        return [
            'success' => false,
            'error' => 'All rate sources failed',
            'message' => 'Cryptocurrency pricing temporarily unavailable',
            'recommendation' => 'Please try again in a few minutes',
            'timestamp' => time()
        ];
    }
}

// API endpoint
$mode = $_GET['mode'] ?? 'balanced';
$rates = new OptimizedCoinGeckoRates();
echo json_encode($rates->getRates($mode));
?>

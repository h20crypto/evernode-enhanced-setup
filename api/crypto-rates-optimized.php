<?php
// api/crypto-rates-simple.php - Simple CoinGecko implementation
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

class SimpleCoinGeckoRates {
    private $cache_file = 'cache/rates.json';
    private $cache_duration = 300; // 5 minutes
    private $license_usd = 49.99;
    
    // CoinGecko coin IDs - all are available!
    private $coins = [
        'xrp' => 'ripple',
        'xah' => 'xahau', 
        'evr' => 'evernode'
    ];
    
    // Fallback rates only if CoinGecko is completely down
    private $fallback_rates = [
        'xrp' => 2.45,
        'xah' => 0.04,
        'evr' => 0.22
    ];
    
    public function __construct() {
        // Create cache directory
        if (!is_dir('cache')) {
            mkdir('cache', 0755, true);
        }
    }
    
    public function getRates() {
        // Check cache first
        if ($this->isCacheValid()) {
            return $this->loadFromCache();
        }
        
        // Fetch fresh rates
        return $this->fetchFreshRates();
    }
    
    private function isCacheValid() {
        if (!file_exists($this->cache_file)) {
            return false;
        }
        
        $cache_time = filemtime($this->cache_file);
        return (time() - $cache_time) < $this->cache_duration;
    }
    
    private function loadFromCache() {
        $data = json_decode(file_get_contents($this->cache_file), true);
        $data['source'] = 'cache';
        $data['cache_age'] = time() - filemtime($this->cache_file);
        return $data;
    }
    
    private function fetchFreshRates() {
        try {
            // Build CoinGecko API URL for all three coins
            $coin_ids = implode(',', $this->coins); // ripple,xahau,evernode
            $url = "https://api.coingecko.com/api/v3/simple/price?ids={$coin_ids}&vs_currencies=usd";
            
            // Fetch with timeout
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Evernode-Host-Pricing/1.0'
                ]
            ]);
            
            $response = file_get_contents($url, false, $context);
            
            if (!$response) {
                return $this->getFallbackRates('CoinGecko API request failed');
            }
            
            $coingecko_data = json_decode($response, true);
            
            if (!$coingecko_data) {
                return $this->getFallbackRates('Invalid CoinGecko API response');
            }
            
            // Build our response
            $rates = $this->buildRateResponse($coingecko_data);
            
            // Cache the successful response
            file_put_contents($this->cache_file, json_encode($rates, JSON_PRETTY_PRINT));
            
            return $rates;
            
        } catch (Exception $e) {
            error_log("CoinGecko fetch failed: " . $e->getMessage());
            return $this->getFallbackRates('Exception: ' . $e->getMessage());
        }
    }
    
    private function buildRateResponse($coingecko_data) {
        $rates = [];
        
        // XRP from CoinGecko
        if (isset($coingecko_data['ripple']['usd'])) {
            $xrp_rate = floatval($coingecko_data['ripple']['usd']);
            $rates['xrp'] = [
                'rate' => $xrp_rate,
                'amount_for_license' => round($this->license_usd / $xrp_rate, 2),
                'display' => '~' . round($this->license_usd / $xrp_rate) . ' XRP',
                'source' => 'coingecko_live'
            ];
        } else {
            $rates['xrp'] = $this->getFallbackRate('xrp');
        }
        
        // XAH from CoinGecko (now available!)
        if (isset($coingecko_data['xahau']['usd'])) {
            $xah_rate = floatval($coingecko_data['xahau']['usd']);
            $rates['xah'] = [
                'rate' => $xah_rate,
                'amount_for_license' => round($this->license_usd / $xah_rate, 2),
                'display' => '~' . round($this->license_usd / $xah_rate) . ' XAH',
                'source' => 'coingecko_live'
            ];
        } else {
            $rates['xah'] = $this->getFallbackRate('xah');
        }
        
        // EVR from CoinGecko (now available!)
        if (isset($coingecko_data['evernode']['usd'])) {
            $evr_rate = floatval($coingecko_data['evernode']['usd']);
            $rates['evr'] = [
                'rate' => $evr_rate,
                'amount_for_license' => round($this->license_usd / $evr_rate, 2),
                'display' => '~' . round($this->license_usd / $evr_rate) . ' EVR',
                'source' => 'coingecko_live'
            ];
        } else {
            $rates['evr'] = $this->getFallbackRate('evr');
        }
        
        return [
            'success' => true,
            'license_usd' => $this->license_usd,
            'xrp' => $rates['xrp'],
            'xah' => $rates['xah'],
            'evr' => $rates['evr'],
            'timestamp' => time(),
            'last_updated' => date('Y-m-d H:i:s'),
            'api_source' => 'coingecko',
            'cache_duration' => $this->cache_duration,
            'confidence' => 'high'
        ];
    }
    
    private function getFallbackRate($currency) {
        $rate = $this->fallback_rates[$currency];
        return [
            'rate' => $rate,
            'amount_for_license' => round($this->license_usd / $rate, 2),
            'display' => '~' . round($this->license_usd / $rate) . ' ' . strtoupper($currency),
            'source' => 'fallback_estimate'
        ];
    }
    
    private function getFallbackRates($error_reason) {
        // Return hardcoded fallback rates if CoinGecko is completely down
        return [
            'success' => false,
            'error' => $error_reason,
            'message' => 'CoinGecko temporarily unavailable - using estimated rates',
            'license_usd' => $this->license_usd,
            'xrp' => $this->getFallbackRate('xrp'),
            'xah' => $this->getFallbackRate('xah'),
            'evr' => $this->getFallbackRate('evr'),
            'timestamp' => time(),
            'last_updated' => date('Y-m-d H:i:s'),
            'api_source' => 'fallback_emergency',
            'confidence' => 'low'
        ];
    }
}

// Handle the request
$rates_api = new SimpleCoinGeckoRates();
$result = $rates_api->getRates();

// Add debug info if requested
if (isset($_GET['debug'])) {
    $result['debug'] = [
        'cache_file_exists' => file_exists('cache/rates.json'),
        'cache_permissions' => is_writable('cache/'),
        'php_version' => PHP_VERSION,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>

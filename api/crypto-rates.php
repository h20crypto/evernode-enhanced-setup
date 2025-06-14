<?php
// api/crypto-rates.php - Real-time crypto pricing with smart caching

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

class SmartCryptoRates {
    private $cache_file = 'crypto_rates_cache.json';
    private $cache_duration = 30; // seconds
    
    public function getRates() {
        $cached = $this->getCachedRates();
        
        // Return cached data if still fresh
        if ($cached && (time() - $cached['timestamp']) < $this->cache_duration) {
            return $cached;
        }
        
        // Fetch fresh data and cache it
        return $this->fetchAndCacheRates();
    }
    
    private function getCachedRates() {
        if (!file_exists($this->cache_file)) {
            return null;
        }
        
        $data = file_get_contents($this->cache_file);
        return json_decode($data, true);
    }
    
    private function fetchAndCacheRates() {
        try {
            // Your existing logic - enhanced with error handling
            $xrp_response = $this->fetchWithTimeout('https://api.coingecko.com/api/v3/simple/price?ids=ripple&vs_currencies=usd');
            $xrp_data = json_decode($xrp_response, true);
            $xrp_rate = $xrp_data['ripple']['usd'] ?? 0.42;
            
            // EVR rate (estimated until on major exchanges)
            $evr_rate = 0.02;
            
            $target_usd = 49.99;
            
            $rates = [
                'xrp' => [
                    'rate' => $xrp_rate,
                    'amount' => round($target_usd / $xrp_rate, 2),
                    'display' => '$' . number_format($xrp_rate, 3)
                ],
                'evr' => [
                    'rate' => $evr_rate,
                    'amount' => round($target_usd / $evr_rate, 0),
                    'display' => '$' . number_format($evr_rate, 3)
                ],
                'usdc' => [
                    'rate' => 1.0,
                    'amount' => $target_usd,
                    'display' => '$1.000'
                ],
                'target_usd' => $target_usd,
                'updated' => date('c'),
                'timestamp' => time(),
                'source' => 'live',
                'cache_expires' => time() + $this->cache_duration
            ];
            
            // Cache the fresh data
            $this->saveCache($rates);
            
            return $rates;
            
        } catch (Exception $e) {
            // If fetching fails, try to return stale cache or fallback
            $stale_cache = $this->getCachedRates();
            if ($stale_cache) {
                $stale_cache['source'] = 'stale_cache';
                return $stale_cache;
            }
            
            return $this->getFallbackRates();
        }
    }
    
    private function fetchWithTimeout($url, $timeout = 5) {
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'user_agent' => 'Evernode-Enhanced-Setup/2.0',
                'method' => 'GET'
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception('Failed to fetch data from ' . $url);
        }
        
        return $response;
    }
    
    private function saveCache($data) {
        try {
            file_put_contents($this->cache_file, json_encode($data));
        } catch (Exception $e) {
            // Silently fail if can't write cache
            error_log('Cache write failed: ' . $e->getMessage());
        }
    }
    
    private function getFallbackRates() {
        return [
            'xrp' => [
                'rate' => 0.42,
                'amount' => 119,
                'display' => '$0.420'
            ],
            'evr' => [
                'rate' => 0.02,
                'amount' => 2500,
                'display' => '$0.020'
            ],
            'usdc' => [
                'rate' => 1.0,
                'amount' => 49.99,
                'display' => '$1.000'
            ],
            'target_usd' => 49.99,
            'updated' => date('c'),
            'timestamp' => time(),
            'source' => 'fallback',
            'cache_expires' => time() + 300 // 5 min fallback cache
        ];
    }
    
    // Debug endpoint
    public function getDebugInfo() {
        $cached = $this->getCachedRates();
        
        return [
            'cache_file_exists' => file_exists($this->cache_file),
            'cache_age_seconds' => $cached ? (time() - $cached['timestamp']) : null,
            'cache_expires_in' => $cached ? ($cached['timestamp'] + $this->cache_duration - time()) : null,
            'cache_is_fresh' => $cached ? ((time() - $cached['timestamp']) < $this->cache_duration) : false,
            'cache_duration' => $this->cache_duration,
            'current_time' => time(),
            'cached_data' => $cached
        ];
    }
}

// Handle requests
$cryptoRates = new SmartCryptoRates();

// Check for debug parameter
if (isset($_GET['debug'])) {
    echo json_encode($cryptoRates->getDebugInfo());
} else {
    echo json_encode($cryptoRates->getRates());
}
?>

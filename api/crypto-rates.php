<?php
// api/crypto-rates-optimized.php - Cost-optimized multi-endpoint Dhali integration

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

class OptimizedDhaliRates {
    private $payment_claim = 'eyJ2ZXJzaW9uIjoiMiIsImFjY291bnQiOiJyR3FxVUNuRWN2SmNjN2U3TGJyaVpTUW54M3pmZlVTblIzIiwicHJvdG9jb2wiOiJYQUhMLk1BSU5ORVQiLCJjdXJyZW5jeSI6eyJjb2RlIjoiWEFIIiwic2NhbGUiOjYsImlzc3VlciI6bnVsbH0sImRlc3RpbmF0aW9uX2FjY291bnQiOiJyTGdnVEV3bVRlM2VKZ3lRYkNTazR3UWF6b3cyVGVLcnRSIiwiYXV0aG9yaXplZF90b19jbGFpbSI6IjUwMDAwMDAwIiwic2lnbmF0dXJlIjoiM0MwMjExQ0EzREI5NTIzNkY3NUQ0N0VFNkZBODdDMTBDNjIwNTk1RkM1NENERTJCNjk3MTNGNkU5QkNDRUEyNDZBQzAwMzFBNzZERDJDMUFGRTY5MDMwNDFBQzFCMzdGNTQwQUM3NDdGOUQwQTIxODY0QUVEMDI2RTdCNzFCMDciLCJjaGFubmVsX2lkIjoiODdEMUQzMDE1NDc0NTM1Nzg4MjkzREVFMjY0OEVEMTJBMkZFRkVBRTE3NzAxQzk1QkMwNjUwRDczOTZFM0NCMSJ9';
    
    private $endpoints = [
        'xrpl_raw' => [
            'url' => 'https://run.api.dhali.io/d74e99cb-166d-416b-b171-4d313e0f079d/',
            'cost' => 0.0001, // XRP per call
            'currency' => 'XRP',
            'cache_duration' => 300, // 5 minutes - accurate but not too expensive
            'description' => 'High-accuracy XRPL data'
        ],
        'xrpl_stats' => [
            'url' => 'https://run.api.dhali.io/c74e147c-a14c-4038-a6aa-9619d2c92596/',
            'cost' => 0.00001, // XRP per call  
            'currency' => 'XRP',
            'cache_duration' => 60, // 1 minute - cheap enough for frequent updates
            'description' => '5-minute statistical window (cheaper)'
        ],
        'xahau_raw' => [
            'url' => 'https://run.api.dhali.io/f642bad0-acaf-4b2e-852b-66d9a6b6b1ef/',
            'cost' => 0.0021, // XAH per call
            'currency' => 'XAH', 
            'cache_duration' => 600, // 10 minutes - expensive, use sparingly
            'description' => 'Xahau network data including EVR'
        ]
    ];
    
    private $cache_dir = 'dhali_cache/';
    
    public function __construct() {
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }
    
    public function getRates($mode = 'balanced') {
        switch ($mode) {
            case 'cheap':
                return $this->getCheapRates();
            case 'accurate': 
                return $this->getAccurateRates();
            case 'realtime':
                return $this->getRealtimeRates();
            default:
                return $this->getBalancedRates();
        }
    }
    
    private function getCheapRates() {
        // Use cheapest endpoints with longer cache
        $xrp_data = $this->fetchWithCache('xrpl_stats');
        $evr_data = $this->getCachedOrFallback('xahau_raw', 3600); // Cache EVR for 1 hour
        
        return $this->buildRateResponse($xrp_data, $evr_data, 'cheap');
    }
    
    private function getAccurateRates() {
        // Use most accurate endpoints
        $xrp_data = $this->fetchWithCache('xrpl_raw');
        $evr_data = $this->fetchWithCache('xahau_raw');
        
        return $this->buildRateResponse($xrp_data, $evr_data, 'accurate');
    }
    
    private function getRealtimeRates() {
        // Use fastest updates (most expensive)
        $xrp_data = $this->fetchDirect('xrpl_stats'); // Direct call, no cache
        $evr_data = $this->fetchWithCache('xahau_raw', 300); // 5-min cache for EVR
        
        return $this->buildRateResponse($xrp_data, $evr_data, 'realtime');
    }
    
    private function getBalancedRates() {
        // Good balance of cost vs accuracy (recommended)
        $xrp_data = $this->fetchWithCache('xrpl_stats', 120); // 2-min cache
        $evr_data = $this->fetchWithCache('xahau_raw', 600); // 10-min cache
        
        return $this->buildRateResponse($xrp_data, $evr_data, 'balanced');
    }
    
    private function fetchWithCache($endpoint_key, $custom_cache_duration = null) {
        $endpoint = $this->endpoints[$endpoint_key];
        $cache_duration = $custom_cache_duration ?? $endpoint['cache_duration'];
        $cache_file = $this->cache_dir . $endpoint_key . '.json';
        
        // Check cache first
        if (file_exists($cache_file)) {
            $cache_data = json_decode(file_get_contents($cache_file), true);
            if (time() - $cache_data['timestamp'] < $cache_duration) {
                $cache_data['source'] = 'cache';
                return $cache_data;
            }
        }
        
        // Fetch fresh data
        return $this->fetchDirect($endpoint_key);
    }
    
    private function fetchDirect($endpoint_key) {
        $endpoint = $this->endpoints[$endpoint_key];
        
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'header' => "Payment-Claim: {$this->payment_claim}\r\n"
                ]
            ]);
            
            $response = file_get_contents($endpoint['url'], false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                if ($data) {
                    // Add metadata
                    $cache_data = [
                        'data' => $data,
                        'timestamp' => time(),
                        'endpoint' => $endpoint_key,
                        'cost' => $endpoint['cost'],
                        'source' => 'dhali_live'
                    ];
                    
                    // Cache the result
                    $cache_file = $this->cache_dir . $endpoint_key . '.json';
                    file_put_contents($cache_file, json_encode($cache_data));
                    
                    return $cache_data;
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Dhali fetch failed for {$endpoint_key}: " . $e->getMessage());
            return null;
        }
    }
    
    private function getCachedOrFallback($endpoint_key, $max_age) {
        $cache_file = $this->cache_dir . $endpoint_key . '.json';
        
        if (file_exists($cache_file)) {
            $cache_data = json_decode(file_get_contents($cache_file), true);
            if (time() - $cache_data['timestamp'] < $max_age) {
                $cache_data['source'] = 'cache_extended';
                return $cache_data;
            }
        }
        
        // Try to fetch fresh, but don't fail if it doesn't work
        $fresh_data = $this->fetchDirect($endpoint_key);
        if ($fresh_data) {
            return $fresh_data;
        }
        
        // Use stale cache if available
        if (isset($cache_data)) {
            $cache_data['source'] = 'cache_stale';
            return $cache_data;
        }
        
        return null;
    }
    
    private function buildRateResponse($xrp_data, $evr_data, $mode) {
        // Extract rates from oracle data
        $xrp_rate = $this->extractXRPRate($xrp_data);
        $evr_rate = $this->extractEVRRate($evr_data);
        
        // Fallback to CoinGecko for XRP if needed
        if (!$xrp_rate) {
            $xrp_rate = $this->getCoinGeckoXRP();
        }
        
        // Default EVR rate if not available
        if (!$evr_rate) {
            $evr_rate = 0.22; // Current estimated EVR rate
        }
        
        $license_usd = 49.99;
        
        $response = [
            'xrp' => [
                'rate' => $xrp_rate,
                'amount_for_license' => round($license_usd / $xrp_rate, 2),
                'display' => '~' . round($license_usd / $xrp_rate, 0) . ' XRP',
                'source' => $xrp_data['source'] ?? 'fallback'
            ],
            'evr' => [
                'rate' => $evr_rate,
                'amount_for_license' => round($license_usd / $evr_rate, 2),
                'display' => '~' . round($license_usd / $evr_rate, 0) . ' EVR',
                'source' => $evr_data['source'] ?? 'estimated'
            ],
            'usd' => [
                'rate' => 1.00,
                'amount_for_license' => $license_usd,
                'display' => '$49.99 USDC',
                'source' => 'fixed'
            ],
            'license_usd' => $license_usd,
            'mode' => $mode,
            'timestamp' => time(),
            'last_updated' => date('Y-m-d H:i:s'),
            'costs_incurred' => $this->calculateCostsIncurred($xrp_data, $evr_data)
        ];
        
        return $response;
    }
    
    private function extractXRPRate($xrp_data) {
        if (!$xrp_data || !isset($xrp_data['data'])) return null;
        
        $data = $xrp_data['data'];
        
        // Look for various XRP rate fields
        $rate_fields = ['xrp_usd', 'XRP_USD', 'price', 'rate', 'close'];
        
        foreach ($rate_fields as $field) {
            if (isset($data[$field]) && is_numeric($data[$field])) {
                return floatval($data[$field]);
            }
        }
        
        return null;
    }
    
    private function extractEVRRate($evr_data) {
        if (!$evr_data || !isset($evr_data['data'])) return null;
        
        $data = $evr_data['data'];
        
        // Look for EVR rate fields
        $rate_fields = ['evr_usd', 'EVR_USD', 'evernode_usd'];
        
        foreach ($rate_fields as $field) {
            if (isset($data[$field]) && is_numeric($data[$field])) {
                return floatval($data[$field]);
            }
        }
        
        return null;
    }
    
    private function getCoinGeckoXRP() {
        try {
            $response = file_get_contents('https://api.coingecko.com/api/v3/simple/price?ids=ripple&vs_currencies=usd');
            $data = json_decode($response, true);
            return $data['ripple']['usd'] ?? 0.42;
        } catch (Exception $e) {
            return 0.42; // Fallback
        }
    }
    
    private function calculateCostsIncurred($xrp_data, $evr_data) {
        $total_cost_usd = 0;
        
        if ($xrp_data && $xrp_data['source'] === 'dhali_live') {
            $total_cost_usd += $xrp_data['cost'] * 0.42; // Convert XRP to USD
        }
        
        if ($evr_data && $evr_data['source'] === 'dhali_live') {
            $total_cost_usd += $evr_data['cost'] * 0.22; // Convert XAH to USD
        }
        
        return round($total_cost_usd, 6);
    }
}

// API endpoint
$mode = $_GET['mode'] ?? 'balanced';
$rates = new OptimizedDhaliRates();
echo json_encode($rates->getRates($mode));
?>

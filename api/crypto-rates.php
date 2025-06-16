<?php
// api/crypto-rates.php - Real-time crypto pricing with Dhali Oracle

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

class DhaliCryptoRates {
    private $cache_file = 'crypto_rates_cache.json';
    private $cache_duration = 60; // Cache for 60 seconds
    private $dhali_payment_claim = 'eyJ2ZXJzaW9uIjoiMiIsImFjY291bnQiOiJyR3FxVUNuRWN2SmNjN2U3TGJyaVpTUW54M3pmZlVTblIzIiwicHJvdG9jb2wiOiJYQUhMLk1BSU5ORVQiLCJjdXJyZW5jeSI6eyJjb2RlIjoiWEFIIiwic2NhbGUiOjYsImlzc3VlciI6bnVsbH0sImRlc3RpbmF0aW9uX2FjY291bnQiOiJyTGdnVEV3bVRlM2VKZ3lRYkNTazR3UWF6b3cyVGVLcnRSIiwiYXV0aG9yaXplZF90b19jbGFpbSI6IjUwMDAwMDAwIiwic2lnbmF0dXJlIjoiM0MwMjExQ0EzREI5NTIzNkY3NUQ0N0VFNkZBODdDMTBDNjIwNTk1RkM1NENERTJCNjk3MTNGNkU5QkNDRUEyNDZBQzAwMzFBNzZERDJDMUFGRTY5MDMwNDFBQzFCMzdGNTQwQUM3NDdGOUQwQTIxODY0QUVEMDI2RTdCNzFCMDciLCJjaGFubmVsX2lkIjoiODdEMUQzMDE1NDc0NTM1Nzg4MjkzREVFMjY0OEVEMTJBMkZFRkVBRTE3NzAxQzk1QkMwNjUwRDczOTZFM0NCMSJ9';
    
    public function getRates() {
        $cached = $this->getCachedRates();
        
        // Use cache if fresh (under 60 seconds old)
        if ($cached && (time() - $cached['timestamp']) < $this->cache_duration) {
            return $cached;
        }
        
        return $this->fetchAndCacheRates();
    }
    
    private function getCachedRates() {
        if (!file_exists($this->cache_file)) return null;
        
        $data = file_get_contents($this->cache_file);
        return json_decode($data, true);
    }
    
    private function fetchAndCacheRates() {
        try {
            // Primary: Dhali Oracle for XRPL/Xahau data
            $dhali_xrpl = $this->fetchFromDhaliXRPL();
            $dhali_xahau = $this->fetchFromDhaliXahau();
            
            // Fallback: CoinGecko for XRP if Dhali fails
            $coingecko_backup = $this->fetchFromCoinGecko();
            
            $rates = [
                'xrp' => [
                    'rate' => $dhali_xrpl['xrp_usd'] ?? $coingecko_backup['xrp_usd'] ?? 0.42,
                    'source' => $dhali_xrpl ? 'dhali_oracle' : 'coingecko_backup',
                    'amount_for_license' => 0, // Calculated below
                    'display' => ''
                ],
                'evr' => [
                    'rate' => $dhali_xahau['evr_usd'] ?? 0.22, // Current EVR rate
                    'source' => $dhali_xahau ? 'dhali_oracle' : 'estimated',
                    'amount_for_license' => 0,
                    'display' => ''
                ],
                'usd' => [
                    'rate' => 1.00,
                    'source' => 'fixed',
                    'amount_for_license' => 49.99,
                    'display' => '$49.99 USDC'
                ],
                'license_usd' => 49.99,
                'timestamp' => time(),
                'expires_at' => time() + $this->cache_duration,
                'last_updated' => date('Y-m-d H:i:s')
            ];
            
            // Calculate required amounts for license
            $rates['xrp']['amount_for_license'] = round($rates['license_usd'] / $rates['xrp']['rate'], 2);
            $rates['xrp']['display'] = "~{$rates['xrp']['amount_for_license']} XRP";
            
            $rates['evr']['amount_for_license'] = round($rates['license_usd'] / $rates['evr']['rate'], 2);
            $rates['evr']['display'] = "~{$rates['evr']['amount_for_license']} EVR";
            
            // Cache the result
            file_put_contents($this->cache_file, json_encode($rates));
            
            return $rates;
            
        } catch (Exception $e) {
            error_log("Crypto rates fetch failed: " . $e->getMessage());
            return $this->getFallbackRates();
        }
    }
    
    private function fetchFromDhaliXRPL() {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'header' => "Payment-Claim: {$this->dhali_payment_claim}\r\n"
                ]
            ]);
            
            $response = file_get_contents(
                'https://run.api.dhali.io/d74e99cb-166d-416b-b171-4d313e0f079d/',
                false,
                $context
            );
            
            $data = json_decode($response, true);
            
            // Extract XRP rate from Dhali oracle data
            return [
                'xrp_usd' => $data['xrp_usd'] ?? null,
                'timestamp' => $data['timestamp'] ?? time()
            ];
            
        } catch (Exception $e) {
            error_log("Dhali XRPL fetch failed: " . $e->getMessage());
            return null;
        }
    }
    
    private function fetchFromDhaliXahau() {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'header' => "Payment-Claim: {$this->dhali_payment_claim}\r\n"
                ]
            ]);
            
            $response = file_get_contents(
                'https://run.api.dhali.io/f642bad0-acaf-4b2e-852b-66d9a6b6b1ef/',
                false,
                $context
            );
            
            $data = json_decode($response, true);
            
            // Extract EVR rate from Dhali oracle data
            return [
                'evr_usd' => $data['evr_usd'] ?? null,
                'timestamp' => $data['timestamp'] ?? time()
            ];
            
        } catch (Exception $e) {
            error_log("Dhali Xahau fetch failed: " . $e->getMessage());
            return null;
        }
    }
    
    private function fetchFromCoinGecko() {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'user_agent' => 'Evernode-Enhanced-Setup/1.0'
                ]
            ]);
            
            $response = file_get_contents(
                'https://api.coingecko.com/api/v3/simple/price?ids=ripple&vs_currencies=usd',
                false,
                $context
            );
            
            $data = json_decode($response, true);
            return [
                'xrp_usd' => $data['ripple']['usd'] ?? 0.42
            ];
            
        } catch (Exception $e) {
            error_log("CoinGecko backup fetch failed: " . $e->getMessage());
            return ['xrp_usd' => 0.42];
        }
    }
    
    private function getFallbackRates() {
        return [
            'xrp' => [
                'rate' => 0.42,
                'source' => 'fallback',
                'amount_for_license' => round(49.99 / 0.42, 2),
                'display' => '~119 XRP'
            ],
            'evr' => [
                'rate' => 0.22,
                'source' => 'fallback', 
                'amount_for_license' => round(49.99 / 0.22, 2),
                'display' => '~227 EVR'
            ],
            'usd' => [
                'rate' => 1.00,
                'source' => 'fixed',
                'amount_for_license' => 49.99,
                'display' => '$49.99 USDC'
            ],
            'license_usd' => 49.99,
            'timestamp' => time(),
            'expires_at' => time() + 300, // 5 min fallback cache
            'last_updated' => date('Y-m-d H:i:s'),
            'status' => 'fallback_rates_active'
        ];
    }
    
    // Method for ROI calculator to get current hosting costs
    public function getHostingCostsInUSD($evr_per_hour) {
        $rates = $this->getRates();
        return $evr_per_hour * $rates['evr']['rate'];
    }
}

// API endpoint
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'rates';
    
    switch ($action) {
        case 'rates':
            $pricer = new DhaliCryptoRates();
            echo json_encode($pricer->getRates());
            break;
            
        case 'hosting-cost':
            $evr_rate = floatval($_GET['evr_rate'] ?? 0.005);
            $pricer = new DhaliCryptoRates();
            echo json_encode([
                'evr_per_hour' => $evr_rate,
                'usd_per_hour' => $pricer->getHostingCostsInUSD($evr_rate),
                'updated' => date('c')
            ]);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
}
?>

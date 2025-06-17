<?php
/**
 * Enhanced Crypto Rates API - Live Only, No Static Values
 * Integrates with existing Evernode Enhanced Setup
 * Uses CoinGecko as reliable backup for both XRP and EVR
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include your existing Dhali config if available
$dhali_config_paths = [
    '/opt/evernode-enhanced/config/dhali-config.php',
    '../config/dhali-config.php',
    __DIR__ . '/../config/dhali-config.php'
];

$dhali_configured = false;
foreach ($dhali_config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        if (class_exists('DhaliConfig')) {
            $dhali_configured = DhaliConfig::isConfigured();
        }
        break;
    }
}

class LiveOnlyPricingSystem {
    private $cache_dir = '/tmp/live_pricing_cache/';
    private $base_license_usd = 49.99;
    private $cluster_base_usd = 30.00;
    
    public function __construct() {
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }
    
    public function getAllRates() {
        $license_rates = $this->getLicenseRates();
        $cluster_rates = $this->getClusterRates();
        $roi_data = $this->getROIData();
        
        return [
            'success' => true,
            'version' => 'live_only_v2.1',
            'license' => $license_rates,
            'cluster' => $cluster_rates,
            'roi' => $roi_data,
            'commission' => $this->getCommissionRates(),
            'supported_currencies' => $this->getSupportedCurrencies(),
            'data_sources' => $this->getDataSources(),
            'timestamp' => time(),
            'last_updated' => date('Y-m-d H:i:s'),
            'live_rates_only' => true,
            'no_static_fallbacks' => true
        ];
    }
    
    public function getLicenseRates() {
        $xrp_data = $this->getLiveCurrency('xrp');
        $evr_data = $this->getLiveCurrency('evr');
        
        $currencies = [];
        
        // XRP - only if live rate available
        if ($xrp_data) {
            $xrp_amount = round($this->base_license_usd / $xrp_data['rate'], 2);
            $currencies['xrp'] = [
                'available' => true,
                'rate' => $xrp_data['rate'],
                'amount' => $xrp_amount,
                'display' => '~' . round($xrp_amount) . ' XRP',
                'source' => $xrp_data['source'],
                'network' => 'XRPL',
                'last_updated' => $xrp_data['last_updated']
            ];
        } else {
            $currencies['xrp'] = [
                'available' => false,
                'error' => 'Live rate unavailable',
                'message' => 'XRP payments temporarily disabled - no live rate available'
            ];
        }
        
        // EVR - only if live rate available
        if ($evr_data) {
            $evr_amount = round($this->base_license_usd / $evr_data['rate'], 2);
            $currencies['evr'] = [
                'available' => true,
                'rate' => $evr_data['rate'],
                'amount' => $evr_amount,
                'display' => '~' . round($evr_amount) . ' EVR',
                'source' => $evr_data['source'],
                'network' => 'Evernode',
                'last_updated' => $evr_data['last_updated']
            ];
        } else {
            $currencies['evr'] = [
                'available' => false,
                'error' => 'Live rate unavailable',
                'message' => 'EVR payments temporarily disabled - no live rate available'
            ];
        }
        
        // USD - always available
        $currencies['usd'] = [
            'available' => true,
            'rate' => 1.00,
            'amount' => $this->base_license_usd,
            'display' => '$' . number_format($this->base_license_usd, 2),
            'source' => 'fixed',
            'network' => 'Multiple',
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
        return [
            'base_price_usd' => $this->base_license_usd,
            'currencies' => $currencies,
            'available_count' => count(array_filter($currencies, function($c) { return $c['available']; }))
        ];
    }
    
    public function getClusterRates() {
        $xrp_data = $this->getLiveCurrency('xrp');
        $evr_data = $this->getLiveCurrency('evr');
        
        $currencies = [];
        
        if ($xrp_data) {
            $currencies['xrp'] = [
                'available' => true,
                'rate' => $xrp_data['rate'],
                'base_cost' => round($this->cluster_base_usd / $xrp_data['rate'], 2),
                'hourly_rate' => round(0.25 / $xrp_data['rate'], 4),
                'source' => $xrp_data['source']
            ];
        } else {
            $currencies['xrp'] = ['available' => false];
        }
        
        if ($evr_data) {
            $currencies['evr'] = [
                'available' => true,
                'rate' => $evr_data['rate'],
                'base_cost' => round($this->cluster_base_usd / $evr_data['rate'], 2),
                'hourly_rate' => round(0.25 / $evr_data['rate'], 4),
                'source' => $evr_data['source']
            ];
        } else {
            $currencies['evr'] = ['available' => false];
        }
        
        return [
            'base_cost_usd' => $this->cluster_base_usd,
            'hourly_rate_usd' => 0.25,
            'currencies' => $currencies
        ];
    }
    
    public function getROIData() {
        $xrp_data = $this->getLiveCurrency('xrp');
        $evr_data = $this->getLiveCurrency('evr');
        
        $monthly_savings = 150.00;
        $license_cost = $this->base_license_usd;
        
        return [
            'license_cost_usd' => $license_cost,
            'monthly_savings_usd' => $monthly_savings,
            'break_even_days' => round($license_cost / ($monthly_savings / 30), 1),
            'efficiency_gain_percent' => 2400,
            'current_rates' => [
                'xrp' => $xrp_data ? $xrp_data['rate'] : null,
                'evr' => $evr_data ? $evr_data['rate'] : null
            ],
            'live_calculations' => [
                'xrp_cost' => $xrp_data ? round($license_cost / $xrp_data['rate']) . ' XRP' : 'Rate unavailable',
                'evr_cost' => $evr_data ? round($license_cost / $evr_data['rate']) . ' EVR' : 'Rate unavailable',
                'payback_period' => round($license_cost / ($monthly_savings / 30), 1) . ' days'
            ]
        ];
    }
    
    public function getCommissionRates() {
        return [
            'host_commission_percent' => 5.0,
            'network_fee_percent' => 2.5,
            'developer_revenue_percent' => 92.5,
            'supported_tokens' => ['XRP', 'EVR', 'USD']
        ];
    }
    
    private function getLiveCurrency($symbol) {
        $cache_file = $this->cache_dir . $symbol . '.json';
        $cache_duration = ($symbol === 'xrp') ? 120 : 180; // 2min for XRP, 3min for EVR
        
        // Check cache
        if (file_exists($cache_file)) {
            $cache = json_decode(file_get_contents($cache_file), true);
            if ($cache && (time() - $cache['cache_time']) < $cache_duration) {
                return $cache;
            }
        }
        
        // Try Dhali first (if configured)
        global $dhali_configured;
        if ($dhali_configured && class_exists('DhaliConfig')) {
            $dhali_rate = $this->fetchDhaliRate($symbol);
            if ($dhali_rate) {
                $data = [
                    'rate' => $dhali_rate,
                    'source' => 'dhali_oracle',
                    'cache_time' => time(),
                    'last_updated' => date('Y-m-d H:i:s')
                ];
                file_put_contents($cache_file, json_encode($data));
                return $data;
            }
        }
        
        // Fallback to CoinGecko
        $coingecko_rate = $this->fetchCoinGeckoRate($symbol);
        if ($coingecko_rate) {
            $source = $dhali_configured ? 'coingecko_backup' : 'coingecko_primary';
            $data = [
                'rate' => $coingecko_rate,
                'source' => $source,
                'cache_time' => time(),
                'last_updated' => date('Y-m-d H:i:s')
            ];
            file_put_contents($cache_file, json_encode($data));
            return $data;
        }
        
        // NO STATIC FALLBACKS - return null if no live rate
        error_log("No live rate available for $symbol");
        return null;
    }
    
    private function fetchDhaliRate($symbol) {
        if (!class_exists('DhaliConfig')) return null;
        
        try {
            $payment_claim = DhaliConfig::getPaymentClaim();
            if (!$payment_claim) return null;
            
            $endpoints = [
                'xrp' => 'https://run.api.dhali.io/d74e99cb-166d-416b-b171-4d313e0f079d/',
                'evr' => 'https://run.api.dhali.io/f642bad0-acaf-4b2e-852b-66d9a6b6b1ef/'
            ];
            
            if (!isset($endpoints[$symbol])) return null;
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'header' => "Payment-Claim: {$payment_claim}\r\n"
                ]
            ]);
            
            $response = file_get_contents($endpoints[$symbol], false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                
                // Try multiple possible field names
                $possible_fields = [
                    'xrp' => ['price', 'rate', 'xrp_usd', 'close', 'last'],
                    'evr' => ['evr_usd', 'EVR_USD', 'evernode_usd', 'price', 'rate']
                ];
                
                foreach ($possible_fields[$symbol] as $field) {
                    if (isset($data[$field]) && is_numeric($data[$field]) && $data[$field] > 0) {
                        return floatval($data[$field]);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Dhali API error for $symbol: " . $e->getMessage());
        }
        
        return null;
    }
    
    private function fetchCoinGeckoRate($symbol) {
        try {
            $coin_ids = [
                'xrp' => 'ripple',
                'evr' => 'evernode'
            ];
            
            if (!isset($coin_ids[$symbol])) return null;
            
            $response = file_get_contents(
                "https://api.coingecko.com/api/v3/simple/price?ids={$coin_ids[$symbol]}&vs_currencies=usd",
                false,
                stream_context_create(['http' => ['timeout' => 10]])
            );
            
            if ($response) {
                $data = json_decode($response, true);
                if (isset($data[$coin_ids[$symbol]]['usd']) && $data[$coin_ids[$symbol]]['usd'] > 0) {
                    return floatval($data[$coin_ids[$symbol]]['usd']);
                }
            }
        } catch (Exception $e) {
            error_log("CoinGecko error for $symbol: " . $e->getMessage());
        }
        
        return null;
    }
    
    private function getSupportedCurrencies() {
        $xrp_available = $this->getLiveCurrency('xrp') !== null;
        $evr_available = $this->getLiveCurrency('evr') !== null;
        
        return [
            'xrp' => $xrp_available,
            'evr' => $evr_available,
            'usd' => true,
            'total_available' => ($xrp_available ? 1 : 0) + ($evr_available ? 1 : 0) + 1
        ];
    }
    
    private function getDataSources() {
        global $dhali_configured;
        
        return [
            'primary' => $dhali_configured ? 'dhali_oracle' : 'coingecko',
            'backup' => 'coingecko',
            'dhali_configured' => $dhali_configured,
            'live_only_mode' => true,
            'no_static_fallbacks' => true
        ];
    }
}

// Handle the request
$component = $_GET['component'] ?? 'all';
$pricing = new LiveOnlyPricingSystem();

switch ($component) {
    case 'license':
        echo json_encode(['success' => true] + $pricing->getLicenseRates());
        break;
    case 'cluster':
        echo json_encode(['success' => true] + $pricing->getClusterRates());
        break;
    case 'roi':
        echo json_encode(['success' => true] + $pricing->getROIData());
        break;
    case 'commission':
        echo json_encode(['success' => true] + $pricing->getCommissionRates());
        break;
    case 'sources':
        echo json_encode(['success' => true] + $pricing->getDataSources());
        break;
    default:
        echo json_encode($pricing->getAllRates());
}
?>

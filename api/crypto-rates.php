<?php
/**
 * Enhanced Crypto Rates API - EVR/XRP Pricing
 * Integrated with Evernode Enhanced Setup v3.0
 * Supports 20% commission calculations
 */

require_once 'config.php';

class EnhancedCryptoRatesAPI {
    private $cache_key = 'crypto_rates_v3';
    private $api_timeout = 10;
    
    public function __construct() {
        // Log API usage
        logAPIUsage('crypto-rates');
    }
    
    public function getAllRates() {
        $start_time = microtime(true);
        
        try {
            // Check cache first
            $cached = getCachedData($this->cache_key, CACHE_DURATION);
            if ($cached) {
                $cached['cached'] = true;
                return $this->formatResponse($cached);
            }
            
            // Fetch live rates
            $rates = $this->fetchLiveRates();
            
            // Cache the results
            setCachedData($this->cache_key, $rates, CACHE_DURATION);
            
            $response_time = round((microtime(true) - $start_time) * 1000);
            logAPIUsage('crypto-rates', $response_time, 200);
            
            return $this->formatResponse($rates);
            
        } catch (Exception $e) {
            $response_time = round((microtime(true) - $start_time) * 1000);
            logAPIUsage('crypto-rates', $response_time, 500);
            
            // Return fallback rates on error
            return $this->formatResponse($this->getFallbackRates(), $e->getMessage());
        }
    }
    
    private function fetchLiveRates() {
        $evr_data = $this->fetchEVRPrice();
        $xrp_data = $this->fetchXRPPrice();
        
        $license_calculations = $this->calculateLicensePricing($evr_data, $xrp_data);
        $commission_data = $this->calculateCommissions($evr_data, $xrp_data);
        $hosting_costs = $this->calculateHostingCosts($evr_data);
        
        return [
            'evr' => $evr_data,
            'xrp' => $xrp_data,
            'license_pricing' => $license_calculations,
            'commission' => $commission_data,
            'hosting_costs' => $hosting_costs,
            'last_updated' => date('c'),
            'cache_duration' => CACHE_DURATION,
            'cached' => false
        ];
    }
    
    private function fetchEVRPrice() {
        $sources = [
            [
                'name' => 'coingecko',
                'url' => 'https://api.coingecko.com/api/v3/simple/price?ids=evernode&vs_currencies=usd',
                'parser' => function($data) {
                    return $data['evernode']['usd'] ?? null;
                }
            ]
        ];
        
        foreach ($sources as $source) {
            try {
                $response = $this->fetchWithTimeout($source['url']);
                if ($response) {
                    $data = json_decode($response, true);
                    $price = $source['parser']($data);
                    
                    if ($price && $price > 0) {
                        return [
                            'rate' => (float)$price,
                            'source' => $source['name'],
                            'confidence' => 'high',
                            'symbol' => 'EVR',
                            'last_updated' => date('c')
                        ];
                    }
                }
            } catch (Exception $e) {
                error_log("EVR price fetch failed from {$source['name']}: " . $e->getMessage());
            }
        }
        
        // Fallback if all sources fail
        return [
            'rate' => 0.22,
            'source' => 'fallback',
            'confidence' => 'low',
            'symbol' => 'EVR',
            'last_updated' => date('c')
        ];
    }
    
    private function fetchXRPPrice() {
        $sources = [
            [
                'name' => 'coingecko',
                'url' => 'https://api.coingecko.com/api/v3/simple/price?ids=ripple&vs_currencies=usd',
                'parser' => function($data) {
                    return $data['ripple']['usd'] ?? null;
                }
            ],
            [
                'name' => 'coinbase',
                'url' => 'https://api.coinbase.com/v2/exchange-rates?currency=XRP',
                'parser' => function($data) {
                    return (float)($data['data']['rates']['USD'] ?? null);
                }
            ]
        ];
        
        foreach ($sources as $source) {
            try {
                $response = $this->fetchWithTimeout($source['url']);
                if ($response) {
                    $data = json_decode($response, true);
                    $price = $source['parser']($data);
                    
                    if ($price && $price > 0) {
                        return [
                            'rate' => (float)$price,
                            'source' => $source['name'],
                            'confidence' => 'high',
                            'symbol' => 'XRP',
                            'last_updated' => date('c')
                        ];
                    }
                }
            } catch (Exception $e) {
                error_log("XRP price fetch failed from {$source['name']}: " . $e->getMessage());
            }
        }
        
        // Fallback if all sources fail
        return [
            'rate' => 0.42,
            'source' => 'fallback',
            'confidence' => 'low',
            'symbol' => 'XRP',
            'last_updated' => date('c')
        ];
    }
    
    private function calculateLicensePricing($evr_data, $xrp_data) {
        $license_usd = LICENSE_PRICE_USD;
        
        return [
            'usd' => [
                'amount' => $license_usd,
                'display' => '$' . number_format($license_usd, 2)
            ],
            'evr' => [
                'amount' => round($license_usd / $evr_data['rate'], 2),
                'display' => '~' . round($license_usd / $evr_data['rate']) . ' EVR',
                'rate_used' => $evr_data['rate']
            ],
            'xrp' => [
                'amount' => round($license_usd / $xrp_data['rate'], 2),
                'display' => '~' . round($license_usd / $xrp_data['rate']) . ' XRP',
                'rate_used' => $xrp_data['rate']
            ]
        ];
    }
    
    private function calculateCommissions($evr_data, $xrp_data) {
        $commission_usd = COMMISSION_AMOUNT_USD;
        
        return [
            'rate_percent' => COMMISSION_RATE * 100, // 20%
            'usd' => [
                'amount' => $commission_usd,
                'display' => '$' . number_format($commission_usd, 2)
            ],
            'evr' => [
                'amount' => round($commission_usd / $evr_data['rate'], 6),
                'display' => number_format($commission_usd / $evr_data['rate'], 6) . ' EVR'
            ],
            'xrp' => [
                'amount' => round($commission_usd / $xrp_data['rate'], 6),
                'display' => number_format($commission_usd / $xrp_data['rate'], 6) . ' XRP'
            ],
            'examples' => [
                '1_sale_week' => '$' . number_format($commission_usd * 4, 0) . '/month',
                '5_sales_month' => '$' . number_format($commission_usd * 5, 0) . '/month',
                '30_sales_month' => '$' . number_format($commission_usd * 30, 0) . '/month'
            ]
        ];
    }
    
    private function calculateHostingCosts($evr_data) {
        $host_types = [
            'cheap' => 0.00001,
            'medium' => 0.005,
            'premium' => 0.02
        ];
        
        $costs = [];
        foreach ($host_types as $type => $evr_per_hour) {
            $usd_per_hour = $evr_per_hour * $evr_data['rate'];
            
            $costs[$type] = [
                'evr_per_hour' => $evr_per_hour,
                'usd_per_hour' => $usd_per_hour,
                'daily' => [
                    'evr' => $evr_per_hour * 24,
                    'usd' => $usd_per_hour * 24,
                    'display' => '$' . number_format($usd_per_hour * 24, 3) . '/day'
                ],
                'monthly' => [
                    'evr' => $evr_per_hour * 24 * 30,
                    'usd' => $usd_per_hour * 24 * 30,
                    'display' => '$' . number_format($usd_per_hour * 24 * 30, 2) . '/month'
                ]
            ];
        }
        
        return [
            'rates' => $costs,
            'evr_price_used' => $evr_data['rate'],
            'examples' => [
                'n8n_automation' => [
                    'app' => 'n8n',
                    'cheap_daily' => '$' . number_format($costs['cheap']['daily']['usd'], 4),
                    'medium_daily' => '$' . number_format($costs['medium']['daily']['usd'], 3),
                    'premium_daily' => '$' . number_format($costs['premium']['daily']['usd'], 3)
                ],
                'wordpress_site' => [
                    'app' => 'WordPress',
                    'cheap_daily' => '$' . number_format($costs['cheap']['daily']['usd'], 4),
                    'medium_daily' => '$' . number_format($costs['medium']['daily']['usd'], 3),
                    'premium_daily' => '$' . number_format($costs['premium']['daily']['usd'], 3)
                ]
            ]
        ];
    }
    
    private function fetchWithTimeout($url) {
        $context = stream_context_create([
            'http' => [
                'timeout' => $this->api_timeout,
                'user_agent' => 'Evernode-Enhanced/3.0'
            ]
        ]);
        
        return file_get_contents($url, false, $context);
    }
    
    private function getFallbackRates() {
        return [
            'evr' => [
                'rate' => 0.22,
                'source' => 'fallback',
                'confidence' => 'low',
                'symbol' => 'EVR'
            ],
            'xrp' => [
                'rate' => 0.42,
                'source' => 'fallback',
                'confidence' => 'low',
                'symbol' => 'XRP'
            ],
            'license_pricing' => [
                'usd' => ['amount' => 49.99, 'display' => '$49.99'],
                'evr' => ['amount' => 227, 'display' => '~227 EVR'],
                'xrp' => ['amount' => 119, 'display' => '~119 XRP']
            ],
            'commission' => [
                'rate_percent' => 20,
                'usd' => ['amount' => 10.00, 'display' => '$10.00'],
                'evr' => ['amount' => 45.45, 'display' => '45.45 EVR'],
                'xrp' => ['amount' => 23.81, 'display' => '23.81 XRP']
            ],
            'last_updated' => date('c'),
            'fallback' => true
        ];
    }
    
    private function formatResponse($data, $error = null) {
        $response = formatResponse(true, $data);
        
        if ($error) {
            $response['warning'] = 'Using fallback data: ' . $error;
            $response['fallback_used'] = true;
        }
        
        return $response;
    }
}

// ===========================================
// API ENDPOINT EXECUTION
// ===========================================

try {
    $api = new EnhancedCryptoRatesAPI();
    $result = $api->getAllRates();
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    handleAPIError($e->getMessage(), 500, 'crypto-rates');
}

?>

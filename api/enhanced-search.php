<?php
/**
 * Enhanced Search API v4.1 - CORS-Free Real Evernode Network Data
 * Fetches real data from Evernode API and serves it locally to avoid CORS issues
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

set_time_limit(30);
date_default_timezone_set('UTC');

$action = $_GET['action'] ?? 'search';

// Cache settings
$cache_file = '/tmp/evernode_real_cache.json';
$cache_duration = 300; // 5 minutes cache

function fetchRealEvernodeData($timeout = 15) {
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'user_agent' => 'Enhanced-Evernode-Discovery/4.1',
            'ignore_errors' => true
        ]
    ]);
    
    $url = 'https://api.evernode.network/registry/hosts?limit=1500';
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return false;
    }
    
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['data']) || !is_array($data['data'])) {
        return false;
    }
    
    return $data['data'];
}

function isCacheValid($cache_file, $cache_duration) {
    return file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_duration;
}

function processHostData($rawHosts) {
    $processed = [];
    
    foreach ($rawHosts as $host) {
        // Skip inactive hosts
        if (!isset($host['active']) || $host['active'] !== true) {
            continue;
        }
        
        // Extract domain or use address
        $domain = $host['domain'] ?? $host['address'] ?? 'unknown';
        
        // Convert country code to full name
        $country = getCountryName($host['countryCode'] ?? 'XX');
        
        // Calculate quality score from reputation
        $reputation = intval($host['hostReputation'] ?? 0);
        $quality_score = min(100, max(0, intval($reputation * 100 / 255)));
        
        // Calculate available instances
        $maxInstances = intval($host['maxInstances'] ?? 3);
        $activeInstances = intval($host['activeInstances'] ?? 0);
        $availableInstances = max(0, $maxInstances - $activeInstances);
        
        // Convert hardware specs
        $cpuCores = intval($host['cpuCount'] ?? 4);
        $memoryMb = intval($host['ramMb'] ?? 8192);
        $memoryGb = round($memoryMb / 1024, 1);
        $diskMb = intval($host['diskMb'] ?? 50000);
        $diskGb = round($diskMb / 1024, 1);
        
        // Calculate cost in USD (approximate)
        $leaseAmount = floatval($host['leaseAmount'] ?? 0.001);
        $evrPriceUSD = 0.172; // Approximate EVR price
        $costPerHourUSD = $leaseAmount * $evrPriceUSD;
        
        // Build URI
        $uri = null;
        if ($domain && $domain !== 'unknown' && strpos($domain, '.') !== false) {
            $uri = "https://{$domain}";
        }
        
        // Determine if enhanced (placeholder - would check beacon)
        $enhanced = checkIfEnhanced($domain);
        
        $processed[] = [
            'address' => $host['address'] ?? 'unknown',
            'xahau_address' => $host['address'] ?? 'unknown',
            'domain' => $domain,
            'uri' => $uri,
            'country' => $country,
            'reputation' => $reputation,
            'quality_score' => $quality_score,
            'cpu_cores' => $cpuCores,
            'memory_gb' => $memoryGb,
            'disk_gb' => $diskGb,
            'max_instances' => $maxInstances,
            'active_instances' => $activeInstances,
            'available_instances' => $availableInstances,
            'cost_per_hour_evr' => $leaseAmount,
            'cost_per_hour_usd' => $costPerHourUSD,
            'enhanced' => $enhanced,
            'features' => $enhanced ? ['Enhanced', 'Discovery', 'Real-time'] : ['Basic'],
            'evr_rewards_eligible' => $reputation >= 200,
            'version' => $host['version'] ?? '1.0.0',
            'last_heartbeat' => date('Y-m-d H:i:s', $host['lastHeartbeatIndex'] ?? time()),
            'data_source' => 'real_evernode_network',
            'rating' => generateRating($quality_score),
            'host_rating' => $host['hostRating'] ?? null,
            'host_rating_str' => $host['hostRatingStr'] ?? 'Unknown'
        ];
    }
    
    return $processed;
}

function checkIfEnhanced($domain) {
    $enhanced_domains = [
        'h20cryptoxah.click',
        'yayathewisemushroom2.co',
        'h20cryptonode3.dev',
        'h20cryptonode5.dev'
    ];
    
    return in_array($domain, $enhanced_domains) || strpos($domain, 'h20crypto') !== false;
}

function getCountryName($countryCode) {
    $countries = [
        'US' => 'United States',
        'DE' => 'Germany',
        'CA' => 'Canada',
        'GB' => 'United Kingdom',
        'UK' => 'United Kingdom',
        'FR' => 'France',
        'NL' => 'Netherlands',
        'SG' => 'Singapore',
        'JP' => 'Japan',
        'AU' => 'Australia',
        'CH' => 'Switzerland',
        'AT' => 'Austria',
        'PL' => 'Poland',
        'FI' => 'Finland',
        'KR' => 'South Korea'
    ];
    
    return $countries[$countryCode] ?? $countryCode;
}

function generateRating($score) {
    if ($score >= 95) return '⭐⭐⭐⭐⭐ Enterprise';
    if ($score >= 85) return '⭐⭐⭐⭐ Premium';
    if ($score >= 75) return '⭐⭐⭐ Professional';
    if ($score >= 65) return '⭐⭐ Standard';
    return '⭐ Basic';
}

// Handle different actions
switch ($action) {
    case 'test':
    case 'ping':
        echo json_encode([
            'success' => true,
            'message' => 'Enhanced Search API v4.1 - Real Evernode Network (CORS-Free)',
            'version' => '4.1.0',
            'timestamp' => date('Y-m-d H:i:s'),
            'network_access' => true,
            'data_source' => 'real_evernode_api'
        ]);
        break;
        
    case 'stats':
        // Try cache first
        if (!isset($_GET['refresh']) && isCacheValid($cache_file, $cache_duration)) {
            $cached_data = json_decode(file_get_contents($cache_file), true);
            $hosts = $cached_data['hosts'] ?? [];
        } else {
            // Fetch fresh data
            $rawHosts = fetchRealEvernodeData();
            if ($rawHosts === false) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to fetch from Evernode API',
                    'fallback_available' => true
                ]);
                exit;
            }
            
            $hosts = processHostData($rawHosts);
            
            // Cache the results
            file_put_contents($cache_file, json_encode([
                'hosts' => $hosts,
                'timestamp' => time()
            ]));
        }
        
        // Calculate stats
        $totalHosts = count($hosts);
        $enhancedHosts = count(array_filter($hosts, function($h) { return $h['enhanced']; }));
        $availableHosts = count(array_filter($hosts, function($h) { return $h['available_instances'] > 0; }));
        $rewardEligible = count(array_filter($hosts, function($h) { return $h['evr_rewards_eligible']; }));
        
        echo json_encode([
            'success' => true,
            'total_hosts' => $totalHosts,
            'enhanced_hosts' => $enhancedHosts,
            'available_hosts' => $availableHosts,
            'reward_eligible' => $rewardEligible,
            'network_status' => 'connected',
            'data_source' => 'real_evernode_network',
            'last_updated' => date('Y-m-d H:i:s'),
            'cache_age' => isCacheValid($cache_file, $cache_duration) ? (time() - filemtime($cache_file)) : 0
        ]);
        break;
        
    case 'search':
        $limit = min(intval($_GET['limit'] ?? 50), 200);
        $enhanced_only = isset($_GET['enhanced_only']) && $_GET['enhanced_only'] === 'true';
        $refresh = isset($_GET['refresh']) && $_GET['refresh'] === 'true';
        
        // Try cache first
        if (!$refresh && isCacheValid($cache_file, $cache_duration)) {
            $cached_data = json_decode(file_get_contents($cache_file), true);
            $hosts = $cached_data['hosts'] ?? [];
        } else {
            // Fetch fresh data
            $rawHosts = fetchRealEvernodeData();
            if ($rawHosts === false) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to fetch from Evernode API',
                    'fallback_available' => true,
                    'hosts' => []
                ]);
                exit;
            }
            
            $hosts = processHostData($rawHosts);
            
            // Cache the results
            file_put_contents($cache_file, json_encode([
                'hosts' => $hosts,
                'timestamp' => time()
            ]));
        }
        
        // Apply filters
        if ($enhanced_only) {
            $hosts = array_filter($hosts, function($h) { return $h['enhanced']; });
        }
        
        // Sort by quality score (highest first)
        usort($hosts, function($a, $b) {
            return $b['quality_score'] - $a['quality_score'];
        });
        
        // Apply limit
        $hosts = array_slice($hosts, 0, $limit);
        
        echo json_encode([
            'success' => true,
            'hosts' => array_values($hosts),
            'total_found' => count($hosts),
            'data_source' => 'real_evernode_network',
            'filters_applied' => [
                'enhanced_only' => $enhanced_only,
                'limit' => $limit
            ],
            'timestamp' => date('Y-m-d H:i:s'),
            'cache_age' => isCacheValid($cache_file, $cache_duration) ? (time() - filemtime($cache_file)) : 0
        ]);
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'error' => 'Unknown action: ' . $action,
            'available_actions' => ['test', 'ping', 'stats', 'search'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
}
?>

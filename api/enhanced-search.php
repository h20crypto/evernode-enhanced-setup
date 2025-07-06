<?php
/**
 * Enhanced Search API v5.0 - CORS-Free Real Evernode Network Data
 * Updated for full-featured host discovery with ALL 7,000+ hosts
 * Fetches real data from Evernode API and serves it locally to avoid CORS issues
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

set_time_limit(60); // Increased timeout for large dataset
date_default_timezone_set('UTC');

$action = $_GET['action'] ?? 'search';

// Cache settings
$cache_file = '/tmp/evernode_real_cache.json';
$cache_duration = 300; // 5 minutes cache

function fetchRealEvernodeData($timeout = 30) {
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'user_agent' => 'Enhanced-Evernode-Discovery/5.0',
            'ignore_errors' => true
        ]
    ]);
    
    // Increased limit to get ALL hosts (was 1500, now 10000)
    $url = 'https://api.evernode.network/registry/hosts?limit=10000';
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

function getCountryName($countryCode) {
    $countries = [
        'US' => 'United States',
        'DE' => 'Germany',
        'CH' => 'Switzerland',
        'FR' => 'France',
        'AT' => 'Austria',
        'CA' => 'Canada',
        'NL' => 'Netherlands',
        'GB' => 'United Kingdom',
        'UK' => 'United Kingdom',
        'SG' => 'Singapore',
        'JP' => 'Japan',
        'AU' => 'Australia',
        'PL' => 'Poland',
        'FI' => 'Finland',
        'KR' => 'South Korea',
        'SE' => 'Sweden',
        'NO' => 'Norway',
        'DK' => 'Denmark',
        'BE' => 'Belgium',
        'IT' => 'Italy',
        'ES' => 'Spain',
        'BR' => 'Brazil',
        'IN' => 'India',
        'CN' => 'China',
        'RU' => 'Russia',
        'ZA' => 'South Africa',
        'MX' => 'Mexico',
        'AR' => 'Argentina',
        'CL' => 'Chile',
        'CO' => 'Colombia',
        'PE' => 'Peru',
        'VE' => 'Venezuela',
        'TH' => 'Thailand',
        'VN' => 'Vietnam',
        'MY' => 'Malaysia',
        'ID' => 'Indonesia',
        'PH' => 'Philippines',
        'HK' => 'Hong Kong',
        'TW' => 'Taiwan',
        'NZ' => 'New Zealand',
        'IE' => 'Ireland',
        'LU' => 'Luxembourg',
        'PT' => 'Portugal',
        'GR' => 'Greece',
        'CZ' => 'Czech Republic',
        'HU' => 'Hungary',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'HR' => 'Croatia',
        'BG' => 'Bulgaria',
        'RO' => 'Romania',
        'LT' => 'Lithuania',
        'LV' => 'Latvia',
        'EE' => 'Estonia',
        'MT' => 'Malta',
        'CY' => 'Cyprus',
        'IS' => 'Iceland',
        'TR' => 'Turkey',
        'IL' => 'Israel',
        'AE' => 'United Arab Emirates',
        'SA' => 'Saudi Arabia',
        'QA' => 'Qatar',
        'KW' => 'Kuwait',
        'BH' => 'Bahrain',
        'OM' => 'Oman',
        'JO' => 'Jordan',
        'LB' => 'Lebanon',
        'EG' => 'Egypt',
        'KE' => 'Kenya',
        'NG' => 'Nigeria',
        'GH' => 'Ghana',
        'XX' => 'Unknown'
    ];
    
    return $countries[strtoupper($countryCode)] ?? 'Unknown';
}

function isEnhancedHost($host) {
    // Check for enhanced host indicators
    $domain = $host['domain'] ?? '';
    $email = $host['email'] ?? $host['operator_email'] ?? '';
    
    // Enhanced indicators
    $enhanced_indicators = [
        'evernodeevr@gmail.com',
        'evernode@datacenter-nodes.com',
        'htr18.evernodeevr.pro',
        'datacenter-nodes.com',
        'enhanced',
        'premium',
        'pro'
    ];
    
    foreach ($enhanced_indicators as $indicator) {
        if (stripos($domain . ' ' . $email, $indicator) !== false) {
            return true;
        }
    }
    
    // High reputation hosts (240+) are considered enhanced
    $reputation = intval($host['hostReputation'] ?? 0);
    if ($reputation >= 240) {
        return true;
    }
    
    return false;
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
        
        // Check if enhanced
        $enhanced = isEnhancedHost($host);
        
        // Build URI
        $uri = null;
        if ($domain && $domain !== 'unknown' && strpos($domain, '.') !== false) {
            $uri = 'https://' . $domain;
        }
        
        // Extract additional fields
        $email = $host['email'] ?? $host['operator_email'] ?? null;
        $operator_email = $host['operator_email'] ?? $email;
        $xahau_address = $host['address'] ?? $host['xahau_address'] ?? '';
        $address = $xahau_address;
        
        // CPU model extraction
        $cpu_model = $host['cpu_model'] ?? $host['cpuModel'] ?? 'Not specified';
        if ($cpu_model === 'Not specified' && $cpuCores >= 8) {
            $cpu_model = $cpuCores >= 16 ? 'AMD EPYC' : 'Intel Xeon';
        }
        
        // Calculate accumulated rewards
        $accumulated_rewards = floatval($host['accumulated_rewards'] ?? $host['accumulatedReward'] ?? 0);
        
        // EVR rewards eligibility
        $evr_rewards_eligible = ($host['evr_rewards_eligible'] ?? true) && $reputation >= 50;
        
        // Registration timestamp
        $registration_timestamp = $host['registration_timestamp'] ?? $host['registrationTimestamp'] ?? date('c');
        
        // Domain TLD
        $domain_tld = '';
        if ($domain && strpos($domain, '.') !== false) {
            $parts = explode('.', $domain);
            $domain_tld = end($parts);
        }
        
        // Features array
        $features = [];
        if ($enhanced) $features[] = 'Enhanced';
        if ($reputation >= 240) $features[] = 'Top Host';
        if ($availableInstances > 0) $features[] = 'Available';
        if ($evr_rewards_eligible) $features[] = 'Reward Eligible';
        if ($cpuCores >= 8) $features[] = 'High Performance';
        if ($memoryGb >= 16) $features[] = 'High Memory';
        if ($diskGb >= 100) $features[] = 'Large Storage';
        
        $processed[] = [
            // Basic info
            'domain' => $domain,
            'address' => $address,
            'xahau_address' => $xahau_address,
            'email' => $email,
            'operator_email' => $operator_email,
            'country' => $country,
            'reputation' => $reputation,
            'quality_score' => $quality_score,
            'enhanced' => $enhanced,
            
            // Hardware specs
            'cpu_cores' => $cpuCores,
            'cpu_model' => $cpu_model,
            'memory_gb' => $memoryGb,
            'memory_mb' => $memoryMb,
            'disk_gb' => $diskGb,
            'disk_mb' => $diskMb,
            
            // Instance info
            'max_instances' => $maxInstances,
            'active_instances' => $activeInstances,
            'available_instances' => $availableInstances,
            
            // Cost info
            'lease_amount' => $leaseAmount,
            'cost_per_hour_usd' => $costPerHourUSD,
            'cost_per_hour_evr' => $leaseAmount,
            'moments' => $leaseAmount,
            
            // Rewards
            'accumulated_rewards' => number_format($accumulated_rewards, 6),
            'evr_rewards_eligible' => $evr_rewards_eligible,
            
            // Additional data
            'uri' => $uri,
            'features' => $features,
            'domain_tld' => $domain_tld,
            'registration_timestamp' => $registration_timestamp,
            'description' => $host['description'] ?? '',
            
            // Metadata
            'last_updated' => date('c'),
            'data_source' => 'real_evernode_api'
        ];
    }
    
    return $processed;
}

// Main API logic
switch ($action) {
    case 'test':
    case 'ping':
        echo json_encode([
            'success' => true,
            'message' => 'Enhanced Search API v5.0 is running',
            'timestamp' => date('Y-m-d H:i:s'),
            'features' => [
                'real_evernode_data' => true,
                'local_caching' => true,
                'cors_free' => true,
                'enhanced_detection' => true,
                'full_host_data' => true,
                'advanced_search' => true
            ],
            'endpoints' => [
                'search' => '?action=search&limit=10000',
                'stats' => '?action=stats',
                'enhanced_only' => '?action=search&enhanced_only=true',
                'test' => '?action=test'
            ]
        ]);
        break;
        
    case 'stats':
        // Get fresh data for stats
        $refresh = isset($_GET['refresh']) && $_GET['refresh'] === 'true';
        
        if (!$refresh && isCacheValid($cache_file, $cache_duration)) {
            $cached_data = json_decode(file_get_contents($cache_file), true);
            $hosts = $cached_data['hosts'] ?? [];
        } else {
            // Fetch fresh data
            $rawHosts = fetchRealEvernodeData();
            if ($rawHosts === false) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to fetch from Evernode API'
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
        
        // Calculate statistics
        $total_hosts = count($hosts);
        $enhanced_hosts = count(array_filter($hosts, function($h) { return $h['enhanced']; }));
        $available_hosts = count(array_filter($hosts, function($h) { return $h['available_instances'] > 0; }));
        $reward_eligible = count(array_filter($hosts, function($h) { return $h['evr_rewards_eligible']; }));
        
        $countries = array_unique(array_column($hosts, 'country'));
        $avg_reputation = $total_hosts > 0 ? array_sum(array_column($hosts, 'reputation')) / $total_hosts : 0;
        
        echo json_encode([
            'success' => true,
            'total_hosts' => $total_hosts,
            'enhanced_hosts' => $enhanced_hosts,
            'available_hosts' => $available_hosts,
            'reward_eligible_hosts' => $reward_eligible,
            'countries_count' => count($countries),
            'avg_reputation' => round($avg_reputation, 1),
            'data_source' => 'real_evernode_network',
            'timestamp' => date('Y-m-d H:i:s'),
            'cache_age' => isCacheValid($cache_file, $cache_duration) ? (time() - filemtime($cache_file)) : 0,
            'last_updated' => date('Y-m-d H:i:s', filemtime($cache_file) ?: time())
        ]);
        break;
        
    case 'search':
        $limit = min(intval($_GET['limit'] ?? 100), 10000); // Increased max limit
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
            'cache_age' => isCacheValid($cache_file, $cache_duration) ? (time() - filemtime($cache_file)) : 0,
            'api_version' => '5.0'
        ]);
        break;
        
    case 'clear_cache':
        if (file_exists($cache_file)) {
            unlink($cache_file);
        }
        echo json_encode([
            'success' => true,
            'message' => 'Cache cleared successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'error' => 'Unknown action: ' . $action,
            'available_actions' => ['test', 'ping', 'stats', 'search', 'clear_cache'],
            'timestamp' => date('Y-m-d H:i:s'),
            'api_version' => '5.0'
        ]);
}
?>

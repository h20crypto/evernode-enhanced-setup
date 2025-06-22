<?php
/**
 * Evernode Enhanced Discovery - Search API
 * File: api/enhanced-search.php
 * 
 * Provides advanced search functionality for Evernode hosts
 * Supports search by r-address, domain, email, country with caching
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Configuration
$cache_file = '/tmp/evernode_hosts_cache.json';
$cache_duration = 600; // 10 minutes for host data

$action = $_GET['action'] ?? $_POST['action'] ?? 'search';

switch ($action) {
    case 'search':
        handleSearch();
        break;
    case 'suggestions':
        handleSuggestions();
        break;
    case 'stats':
        handleStats();
        break;
    case 'clear-cache':
        clearCache();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function handleSearch() {
    global $cache_file, $cache_duration;
    
    $query = trim($_GET['query'] ?? $_POST['query'] ?? '');
    $type = $_GET['type'] ?? $_POST['type'] ?? 'all';
    $limit = min(intval($_GET['limit'] ?? 100), 1000);
    $quality_min = intval($_GET['quality_min'] ?? 0);
    
    try {
        // Get hosts data (cached or fresh)
        $hosts_data = getHostsData();
        
        if (!$hosts_data || !isset($hosts_data['data'])) {
            throw new Exception('No host data available');
        }
        
        $hosts = $hosts_data['data'];
        
        // Apply search filter
        if (!empty($query)) {
            $hosts = filterHosts($hosts, $query, $type);
        }
        
        // Apply quality filter
        if ($quality_min > 0) {
            $hosts = array_filter($hosts, function($host) use ($quality_min) {
                return calculateQualityScore($host) >= $quality_min;
            });
        }
        
        // Enhance host data
        $hosts = array_map('enhanceHostData', $hosts);
        
        // Sort by quality score (highest first)
        usort($hosts, function($a, $b) {
            return $b['quality_score'] - $a['quality_score'];
        });
        
        // Limit results
        $hosts = array_slice($hosts, 0, $limit);
        
        echo json_encode([
            'success' => true,
            'hosts' => $hosts,
            'total_found' => count($hosts),
            'query' => $query,
            'type' => $type,
            'quality_min' => $quality_min,
            'timestamp' => time()
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'fallback' => true
        ]);
    }
}

function handleSuggestions() {
    try {
        $hosts_data = getHostsData();
        
        if (!$hosts_data || !isset($hosts_data['data'])) {
            throw new Exception('No host data available');
        }
        
        $hosts = $hosts_data['data'];
        
        // Generate suggestions
        $domains = array_unique(array_filter(array_map(function($host) {
            return $host['domain'] ?? null;
        }, $hosts)));
        
        $countries = array_unique(array_filter(array_map(function($host) {
            return $host['countryCode'] ?? null;
        }, $hosts)));
        
        $emails = array_unique(array_filter(array_map(function($host) {
            $email = $host['email'] ?? '';
            return strpos($email, '@') ? substr($email, strpos($email, '@')) : null;
        }, $hosts)));
        
        // Popular suggestions
        $popular_domains = array_slice(array_count_values(array_filter(array_map(function($host) {
            $domain = $host['domain'] ?? '';
            $parts = explode('.', $domain);
            return count($parts) >= 2 ? $parts[count($parts)-2] . '.' . $parts[count($parts)-1] : null;
        }, $hosts))), 0, 10);
        
        echo json_encode([
            'success' => true,
            'suggestions' => [
                'domains' => array_slice($domains, 0, 20),
                'countries' => $countries,
                'email_domains' => array_slice($emails, 0, 10),
                'popular_domains' => array_keys($popular_domains)
            ],
            'timestamp' => time()
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function handleStats() {
    try {
        $hosts_data = getHostsData();
        
        if (!$hosts_data || !isset($hosts_data['data'])) {
            throw new Exception('No host data available');
        }
        
        $hosts = $hosts_data['data'];
        $total_hosts = count($hosts);
        
        // Calculate statistics
        $countries = [];
        $versions = [];
        $total_quality = 0;
        $enhanced_count = 0;
        $online_count = 0;
        
        foreach ($hosts as $host) {
            $quality = calculateQualityScore($host);
            $total_quality += $quality;
            
            if ($quality >= 70) $enhanced_count++;
            
            // Count countries
            if (!empty($host['countryCode'])) {
                $countries[$host['countryCode']] = ($countries[$host['countryCode']] ?? 0) + 1;
            }
            
            // Count versions
            if (!empty($host['version'])) {
                $versions[$host['version']] = ($versions[$host['version']] ?? 0) + 1;
            }
            
            // Estimate online status
            $last_heartbeat = intval($host['lastHeartbeatIndex'] ?? 0);
            if ((time() - $last_heartbeat) < 3600) $online_count++;
        }
        
        $avg_quality = $total_hosts > 0 ? ($total_quality / $total_hosts) : 0;
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_hosts' => $total_hosts,
                'average_quality' => round($avg_quality, 2),
                'enhanced_hosts' => $enhanced_count,
                'estimated_online' => $online_count,
                'countries_count' => count($countries),
                'versions_count' => count($versions)
            ],
            'distributions' => [
                'countries' => $countries,
                'versions' => $versions
            ],
            'timestamp' => time()
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function clearCache() {
    global $cache_file;
    
    if (file_exists($cache_file)) {
        unlink($cache_file);
        echo json_encode(['success' => true, 'message' => 'Cache cleared']);
    } else {
        echo json_encode(['success' => true, 'message' => 'No cache to clear']);
    }
}

function getHostsData() {
    global $cache_file, $cache_duration;
    
    // Check cache first
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_duration) {
        return json_decode(file_get_contents($cache_file), true);
    }
    
    // Fetch fresh data
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'user_agent' => 'Enhanced-Evernode-Search/1.0'
        ]
    ]);
    
    $data = file_get_contents('https://api.evernode.network/registry/hosts?limit=1000', false, $context);
    
    if ($data === false) {
        return null;
    }
    
    $hosts_data = json_decode($data, true);
    
    // Cache the result
    if ($hosts_data) {
        file_put_contents($cache_file, json_encode($hosts_data));
        chmod($cache_file, 0644);
    }
    
    return $hosts_data;
}

function filterHosts($hosts, $query, $type) {
    $query_lower = strtolower($query);
    
    return array_filter($hosts, function($host) use ($query_lower, $type) {
        switch ($type) {
            case 'address':
                return stripos($host['address'] ?? '', $query_lower) !== false;
                
            case 'domain':
                return stripos($host['domain'] ?? '', $query_lower) !== false;
                
            case 'email':
                return stripos($host['email'] ?? '', $query_lower) !== false;
                
            case 'country':
                return stripos($host['countryCode'] ?? '', $query_lower) !== false ||
                       stripos($host['country'] ?? '', $query_lower) !== false;
                       
            case 'version':
                return stripos($host['version'] ?? '', $query_lower) !== false;
                
            case 'all':
            default:
                return stripos($host['address'] ?? '', $query_lower) !== false ||
                       stripos($host['domain'] ?? '', $query_lower) !== false ||
                       stripos($host['email'] ?? '', $query_lower) !== false ||
                       stripos($host['countryCode'] ?? '', $query_lower) !== false ||
                       stripos($host['country'] ?? '', $query_lower) !== false ||
                       stripos($host['version'] ?? '', $query_lower) !== false;
        }
    });
}

function calculateQualityScore($host) {
    $quality = 0;
    
    // Reputation score (0-40 points)
    $reputation = floatval($host['hostRating'] ?? 0);
    $quality += min($reputation / 500 * 40, 40);
    
    // Version score (0-20 points)
    $version = $host['version'] ?? '';
    if ($version === '1.0.0') {
        $quality += 20;
    } elseif (!empty($version)) {
        $quality += 10;
    }
    
    // CPU score (0-20 points)
    $cpu_count = intval($host['cpuCount'] ?? 0);
    $quality += min($cpu_count / 16 * 20, 20);
    
    // Memory score (0-10 points)
    $memory = floatval($host['memory'] ?? 0);
    $quality += min($memory / 32 * 10, 10);
    
    // Disk score (0-10 points)
    $disk_space = floatval($host['diskSpace'] ?? 0);
    $quality += min($disk_space / 500 * 10, 10);
    
    return round($quality);
}

function enhanceHostData($host) {
    $quality_score = calculateQualityScore($host);
    $is_enhanced = $quality_score >= 70;
    
    // Estimate online status
    $last_heartbeat = intval($host['lastHeartbeatIndex'] ?? 0);
    $is_online = (time() - $last_heartbeat) < 3600;
    
    // Calculate cost in USD
    $evr_rate = floatval($host['leaseAmount'] ?? 0.001);
    $cost_usd = $evr_rate * 0.1825; // EVR to USD
    
    return array_merge($host, [
        'quality_score' => $quality_score,
        'is_enhanced' => $is_enhanced,
        'is_online' => $is_online,
        'cost_usd' => round($cost_usd, 6),
        'cost_per_day' => round($cost_usd * 24, 4),
        'enhancement_features' => getEnhancementFeatures($host, $quality_score),
        'estimated_utilization' => rand(0, 100), // Placeholder
        'last_seen' => $last_heartbeat > 0 ? date('c', $last_heartbeat) : null
    ]);
}

function getEnhancementFeatures($host, $quality_score) {
    $features = [];
    
    if ($quality_score >= 85) $features[] = 'Premium Quality';
    if ($quality_score >= 70) $features[] = 'Enhanced Host';
    if (($host['version'] ?? '') === '1.0.0') $features[] = 'Latest Version';
    if (intval($host['cpuCount'] ?? 0) >= 8) $features[] = 'High CPU';
    if (floatval($host['memory'] ?? 0) >= 16) $features[] = 'High Memory';
    if (floatval($host['hostRating'] ?? 0) >= 300) $features[] = 'High Reputation';
    if (!empty($host['domain']) && strpos($host['domain'], '.com') !== false) $features[] = 'Premium Domain';
    
    return $features;
}

// Error handling and logging
function logError($message) {
    error_log("Enhanced Search API: " . $message);
}

// Log API usage
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$action = $_GET['action'] ?? 'search';
error_log("Enhanced Search API: $action from $ip");
?>

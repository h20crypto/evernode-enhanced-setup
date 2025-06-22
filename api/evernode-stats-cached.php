<?php
/**
 * Evernode Enhanced Discovery - Cached Stats API
 * File: api/evernode-stats-cached.php
 * 
 * Provides cached network statistics from Evernode API with enhanced metrics
 * Cache duration: 15 minutes (configurable)
 * Supports cache busting via ?bust_cache=1 parameter
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Configuration
$cache_file = '/tmp/evernode_stats_cache.json';
$cache_duration = 900; // 15 minutes (900 seconds) - Change to 600 for 10 minutes

// Check for cache busting parameter
$bust_cache = isset($_GET['bust_cache']) && $_GET['bust_cache'] === '1';

// Clear cache if requested
if ($bust_cache && file_exists($cache_file)) {
    unlink($cache_file);
    error_log("Evernode cache busted by user request");
}

// Check if cache is still valid (unless busting)
if (!$bust_cache && file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_duration) {
    $cached_data = json_decode(file_get_contents($cache_file), true);
    if ($cached_data) {
        $cached_data['cache_status'] = 'hit';
        $cached_data['cache_age'] = time() - filemtime($cache_file);
        $cached_data['cache_expires_in'] = $cache_duration - $cached_data['cache_age'];
        echo json_encode($cached_data);
        exit;
    }
}

try {
    // Fetch fresh data from Evernode APIs
    $stats_context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'user_agent' => 'Enhanced-Evernode-Discovery/1.0',
            'follow_location' => true,
            'max_redirects' => 3
        ]
    ]);
    
    // Fetch network statistics
    $stats_data = file_get_contents('https://api.evernode.network/support/stats', false, $stats_context);
    if ($stats_data === false) {
        throw new Exception('Failed to fetch stats from Evernode API');
    }
    
    // Fetch host sample for enhanced metrics
    $hosts_data = file_get_contents('https://api.evernode.network/registry/hosts?limit=1000', false, $stats_context);
    if ($hosts_data === false) {
        throw new Exception('Failed to fetch hosts from Evernode API');
    }
    
    $stats = json_decode($stats_data, true);
    $hosts = json_decode($hosts_data, true);
    
    if (!$stats || !$hosts) {
        throw new Exception('Invalid JSON response from Evernode API');
    }
    
    // Calculate enhanced metrics from host sample
    $enhanced_count = 0;
    $countries = [];
    $total_cost = 0;
    $sample_size = count($hosts['data'] ?? []);
    $online_in_sample = 0;
    $high_cpu_count = 0;
    $version_counts = [];
    
    foreach ($hosts['data'] ?? [] as $host) {
        // Enhanced detection logic
        $reputation = floatval($host['hostRating'] ?? 0);
        $cpu_count = intval($host['cpuCount'] ?? 0);
        $memory = floatval($host['memory'] ?? 0);
        $version = $host['version'] ?? '';
        
        // Quality scoring algorithm
        $quality_score = 0;
        $quality_score += min($reputation / 500 * 40, 40); // Reputation (0-40 points)
        $quality_score += ($version === '1.0.0') ? 20 : (($version) ? 10 : 0); // Version (0-20 points)
        $quality_score += min($cpu_count / 16 * 20, 20); // CPU (0-20 points)
        $quality_score += min($memory / 32 * 10, 10); // Memory (0-10 points)
        $quality_score += min(floatval($host['diskSpace'] ?? 0) / 500 * 10, 10); // Disk (0-10 points)
        
        $is_enhanced = $quality_score >= 70;
        
        // Online detection (simplified - check heartbeat if available)
        $last_heartbeat = intval($host['lastHeartbeatIndex'] ?? 0);
        $is_online = (time() - $last_heartbeat) < 3600; // Last hour
        
        // Statistics collection
        if ($is_enhanced) $enhanced_count++;
        if ($is_online || $last_heartbeat === 0) $online_in_sample++; // Assume online if no heartbeat data
        if ($cpu_count >= 8) $high_cpu_count++;
        
        // Country tracking
        if (!empty($host['countryCode'])) {
            $countries[$host['countryCode']] = true;
        }
        
        // Cost calculation
        $evr_rate = floatval($host['leaseAmount'] ?? 0.001);
        $total_cost += $evr_rate * 0.1825; // EVR to USD conversion
        
        // Version tracking
        if ($version) {
            $version_counts[$version] = ($version_counts[$version] ?? 0) + 1;
        }
    }
    
    // Estimate network-wide metrics from sample
    $sample_ratio = $sample_size > 0 ? ($sample_size / max($stats['hosts'], 1)) : 0;
    $online_ratio = $sample_size > 0 ? ($online_in_sample / $sample_size) : 0.59; // Default 59% online
    $enhanced_ratio = $sample_size > 0 ? ($enhanced_count / $sample_size) : 0.035; // Default 3.5% enhanced
    
    $estimated_enhanced = round($enhanced_ratio * $stats['active']);
    $avg_cost = $sample_size > 0 ? ($total_cost / $sample_size) : 0.0002;
    $estimated_instances = $stats['active'] * 3; // Assume 3 instances per active host
    $available_instances = round($estimated_instances * 0.65); // ~65% availability
    
    // Prepare result
    $result = [
        'success' => true,
        'timestamp' => time(),
        'cache_status' => $bust_cache ? 'busted' : 'miss',
        'cache_duration' => $cache_duration,
        'stats' => [
            'total_hosts' => intval($stats['hosts']),
            'active_hosts' => intval($stats['active']),
            'high_reputation_hosts' => intval($stats['activege200']),
            'inactive_hosts' => intval($stats['inactive']),
            'estimated_enhanced' => $estimated_enhanced,
            'estimated_available_instances' => $available_instances,
            'average_cost_usd' => round($avg_cost, 6),
            'countries_count' => count($countries),
            'sample_size' => $sample_size,
            'online_in_sample' => $online_in_sample,
            'high_cpu_hosts' => $high_cpu_count
        ],
        'metrics' => [
            'sample_ratio' => round($sample_ratio, 4),
            'online_ratio' => round($online_ratio, 4),
            'enhanced_ratio' => round($enhanced_ratio, 4),
            'evr_to_usd_rate' => 0.1825,
            'countries_sampled' => array_keys($countries)
        ],
        'meta' => [
            'last_updated' => date('c'),
            'next_update' => date('c', time() + $cache_duration),
            'cache_busted' => $bust_cache,
            'api_endpoints' => [
                'stats' => 'https://api.evernode.network/support/stats',
                'hosts' => 'https://api.evernode.network/registry/hosts'
            ],
            'version_distribution' => $version_counts,
            'cache_file' => basename($cache_file)
        ]
    ];
    
    // Cache the result
    if (file_put_contents($cache_file, json_encode($result)) === false) {
        error_log("Warning: Failed to write cache file: $cache_file");
    } else {
        // Set appropriate permissions
        chmod($cache_file, 0644);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Evernode Stats API Error: " . $e->getMessage());
    
    // Provide fallback data based on real numbers
    $fallback = [
        'success' => false,
        'error' => $e->getMessage(),
        'cache_status' => 'fallback',
        'timestamp' => time(),
        'stats' => [
            'total_hosts' => 11859,
            'active_hosts' => 7006,
            'high_reputation_hosts' => 6637,
            'inactive_hosts' => 4853,
            'estimated_enhanced' => 247,
            'estimated_available_instances' => 12847,
            'average_cost_usd' => 0.0002,
            'countries_count' => 67,
            'sample_size' => 0,
            'online_in_sample' => 0,
            'high_cpu_hosts' => 0
        ],
        'meta' => [
            'fallback_reason' => $e->getMessage(),
            'last_updated' => date('c'),
            'data_source' => 'fallback_estimates'
        ]
    ];
    
    // Set appropriate HTTP status
    http_response_code(503); // Service Unavailable
    echo json_encode($fallback);
}

// Log usage for monitoring (optional)
if (function_exists('error_log')) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 100);
    error_log("Evernode Stats API accessed from $ip - " . ($bust_cache ? 'CACHE_BUST' : 'NORMAL'));
}
?>

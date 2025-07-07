<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

set_time_limit(60);
date_default_timezone_set('UTC');

$action = $_GET['action'] ?? 'search';

function isEnhancedHost($host) {
    $domain = $host['domain'] ?? '';
    $known_enhanced = [
        'h20cryptoxah.click',
        'h20cryptonode3.dev', 
        'h20cryptonode5.dev'
    ];
    return in_array($domain, $known_enhanced);
}

function fetchRealEvernodeData($timeout = 30) {
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'user_agent' => 'Enhanced-Evernode-Discovery/5.0',
            'ignore_errors' => true
        ]
    ]);
    
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

function processHostData($rawHosts) {
    $processed = [];
    
    foreach ($rawHosts as $host) {
        if (!isset($host['active']) || $host['active'] !== true) {
            continue;
        }
        
        $domain = $host['domain'] ?? $host['address'] ?? 'unknown';
        $enhanced = isEnhancedHost($host);
        
        $processed[] = [
            'domain' => $domain,
            'address' => $host['address'] ?? '',
            'country' => $host['countryCode'] ?? 'Unknown',
            'reputation' => intval($host['hostReputation'] ?? 0),
            'quality_score' => min(100, max(0, intval(($host['hostReputation'] ?? 0) * 100 / 255))),
            'enhanced' => $enhanced,
            'cpu_cores' => intval($host['cpuCount'] ?? 4),
            'memory_gb' => round((intval($host['ramMb'] ?? 8192)) / 1024, 1),
            'disk_gb' => round((intval($host['diskMb'] ?? 50000)) / 1024, 1),
            'max_instances' => intval($host['maxInstances'] ?? 3),
            'active_instances' => intval($host['activeInstances'] ?? 0),
            'available_instances' => max(0, intval($host['maxInstances'] ?? 3) - intval($host['activeInstances'] ?? 0)),
            'lease_amount' => floatval($host['leaseAmount'] ?? 0.001),
            'cost_per_hour_usd' => floatval($host['leaseAmount'] ?? 0.001) * 0.172,
            'uri' => ($domain && $domain !== 'unknown' && strpos($domain, '.') !== false) ? "https://$domain" : null,
            'features' => $enhanced ? ['Enhanced', 'GitHub Setup'] : ['Standard'],
            'last_updated' => date('c'),
            'data_source' => 'real_evernode_api'
        ];
    }
    
    return $processed;
}

switch ($action) {
    case 'test':
        echo json_encode([
            'success' => true,
            'message' => 'Enhanced Search API v5.0 is running',
            'timestamp' => date('Y-m-d H:i:s'),
            'features' => [
                'real_evernode_data' => true,
                'enhanced_detection' => true,
                'cors_free' => true
            ]
        ]);
        break;
        
    case 'stats':
        $rawHosts = fetchRealEvernodeData();
        if ($rawHosts === false) {
            echo json_encode(['success' => false, 'error' => 'Failed to fetch from Evernode API']);
            exit;
        }
        
        $hosts = processHostData($rawHosts);
        $enhanced_hosts = count(array_filter($hosts, function($h) { return $h['enhanced']; }));
        $available_hosts = count(array_filter($hosts, function($h) { return $h['available_instances'] > 0; }));
        
        echo json_encode([
            'success' => true,
            'total_hosts' => count($hosts),
            'enhanced_hosts' => $enhanced_hosts,
            'available_hosts' => $available_hosts,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
        
    case 'search':
        $limit = min(intval($_GET['limit'] ?? 100), 10000);
        $enhanced_only = isset($_GET['enhanced_only']) && $_GET['enhanced_only'] === 'true';
        
        $rawHosts = fetchRealEvernodeData();
        if ($rawHosts === false) {
            echo json_encode(['success' => false, 'error' => 'Failed to fetch from Evernode API']);
            exit;
        }
        
        $hosts = processHostData($rawHosts);
        
        if ($enhanced_only) {
            $hosts = array_filter($hosts, function($h) { return $h['enhanced']; });
        }
        
        $hosts = array_slice($hosts, 0, $limit);
        
        echo json_encode([
            'success' => true,
            'hosts' => array_values($hosts),
            'total_found' => count($hosts),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
?>

<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// === PEER DISCOVERY FUNCTIONALITY ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['announce'])) {
        $peer_info = $input['announce'];
        echo json_encode([
            'success' => true,
            'message' => 'Announcement received',
            'peer' => $peer_info['domain'],
            'timestamp' => time(),
            'features_detected' => $peer_info['features'] ?? []
        ]);
        exit;
    }
}

// === ORIGINAL FUNCTIONALITY (Enhanced) ===
$xahau_address = trim(shell_exec('evernode config account | grep "Address:" | awk \'{print $2}\' 2>/dev/null'));

// Get system information
$domain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
$cpu_cores = intval(trim(shell_exec('nproc') ?: '4'));
$memory_mb = intval(trim(shell_exec("free -m | grep '^Mem:' | awk '{print \$2}'") ?: '8192'));
$memory_gb = round($memory_mb / 1024, 1);
$disk_info = trim(shell_exec("df -BG / | tail -1 | awk '{print \$2}'") ?: '200G');
$disk_gb = intval(str_replace('G', '', $disk_info));

// Enhanced features list
$enhanced_features = [
    'Enhanced',
    'Discovery', 
    'Cluster Manager',
    'Real-time Monitoring',
    'Auto Deploy Commands',
    'Commission System',
    'Peer Discovery',
    'Live Data APIs'
];

// Known enhanced hosts (add h20cryptonode5)
$known_enhanced_hosts = [
    'h20cryptoxah.click',
    'yayathewisemushroom2.co',
    'h20cryptonode3.dev',
    'h20cryptonode5.dev'  // Add this new host
];

$is_enhanced = in_array($domain, $known_enhanced_hosts) || 
               file_exists('/var/www/html/api/enhanced-search.php');

// Build response
$host_data = [
    // Original data
    'xahau_address' => $xahau_address ?: 'rUnknownAddress',
    'domain' => $domain,
    'enhanced' => $is_enhanced,
    
    // Enhanced data
    'uri' => "https://{$domain}",
    'features' => $is_enhanced ? $enhanced_features : ['Standard'],
    'quality_score' => $is_enhanced ? 95 : 75,
    'reputation' => 252,
    
    // System info
    'cpu_cores' => $cpu_cores,
    'memory_gb' => $memory_gb,
    'disk_gb' => $disk_gb,
    'available_instances' => 45,
    'max_instances' => 50,
    'active_instances' => 5,
    
    // Cost calculation
    'moments' => 0.00001,
    'cost_per_hour_evr' => 0.00001,
    'cost_per_hour_usd' => 0.000001825,
    'evr_rewards_eligible' => true,
    
    // API endpoints
    'api_endpoints' => [
        'discovery' => "/api/enhanced-search.php",
        'host_info' => "/api/host-info.php", 
        'cluster' => "/cluster/premium-cluster-manager.html",
        'stats' => "/api/evernode-stats-cached.php"
    ],
    
    // Metadata
    'version' => '3.0',
    'last_updated' => date('c'),
    'country' => 'United States',
    'network_type' => 'enhanced'
];

echo json_encode($host_data, JSON_PRETTY_PRINT);
?>

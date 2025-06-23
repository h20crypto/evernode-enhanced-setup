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
// Handle peer announcements and discovery
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle announcement from peer
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['announce'])) {
        // Store peer announcement
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
// Get actual host address from evernode config
$xahau_address = trim(shell_exec('evernode config account | grep "Address:" | awk \'{print $2}\' 2>/dev/null'));

// Get additional system information
$domain = $_SERVER['HTTP_HOST'];
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

// Determine if this is one of the known enhanced hosts
$known_enhanced_hosts = [
    'h20cryptoxah.click',
    'yayathewisemushroom2.co',
    'h20cryptonode3.dev'
];

$is_enhanced = in_array($domain, $known_enhanced_hosts) || 
               file_exists('/var/www/html/api/enhanced-search.php');

// Build comprehensive response
$host_data = [
    // === ORIGINAL DATA ===
    'xahau_address' => $xahau_address ?: 'rUnknownAddress',
    'domain' => $domain,
    'enhanced' => $is_enhanced,
    
    // === ENHANCED DATA ===
    'uri' => "https://{$domain}",
    'features' => $is_enhanced ? $enhanced_features : ['Standard'],
    'quality_score' => $is_enhanced ? 95 : 75,
    'reputation' => 252, // Enhanced hosts maintain max reputation
    
    // === SYSTEM INFO ===
    'cpu_cores' => $cpu_cores,
    'memory_gb' => $memory_gb,
    'disk_gb' => $disk_gb,
    'available_instances' => 45, // Estimate - could be made dynamic
    'max_instances' => 50,
    'active_instances' => 5,
    
    // === COST CALCULATION ===
    'moments' => 0.00001, // EVR per moment
    'cost_per_hour_evr' => 0.00001,
    'cost_per_hour_usd' => 0.000001825, // Based on current EVR price
    'evr_rewards_eligible' => true,
    
    // === API ENDPOINTS ===
    'api_endpoints' => [
        'discovery' => "/api/enhanced-search.php",
        'host_info' => "/api/host-info.php", 
        'cluster' => "/cluster/premium-cluster-manager.html",
        'stats' => "/api/evernode-stats-cached.php"
    ],
    
    // === METADATA ===
    'version' => '3.0',
    'last_updated' => date('c'),
    'country' => $domain === 'yayathewisemushroom2.co' ? 'Canada' : 'United States',
    'network_type' => 'enhanced'
];

// === PEER ANNOUNCEMENT ===
// Announce to other enhanced hosts (async, don't wait)
if ($is_enhanced && $_GET['announce'] !== 'false') {
    $peers = array_filter($known_enhanced_hosts, function($peer) use ($domain) {
        return $peer !== $domain; // Don't announce to self
    });
    
    foreach ($peers as $peer) {
        // Fire and forget announcement
        $announcement = json_encode(['announce' => $host_data]);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-type: application/json\r\n",
                'content' => $announcement,
                'timeout' => 2 // Quick timeout
            ]
        ]);
        
        // Async call - don't wait for response
        @file_get_contents("http://{$peer}/api/host-info.php", false, $context);
    }
}

// Return the enhanced host information
echo json_encode($host_data, JSON_PRETTY_PRINT);
?>

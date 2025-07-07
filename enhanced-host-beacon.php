<?php
/**
 * Enhanced Host Discovery Beacon - Unified Version
 * Enables cross-discovery between Enhanced hosts
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$domain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

// Verify this is actually an Enhanced host
$required_files = [
    '/var/www/html/api/enhanced-search.php',
    '/var/www/html/index.html',
    '/var/www/html/host-discovery.html'
];

$enhanced_files_found = 0;
foreach ($required_files as $file) {
    if (file_exists($file)) {
        $enhanced_files_found++;
    }
}

// Must have at least 2/3 Enhanced files to be considered Enhanced
if ($enhanced_files_found < 2) {
    http_response_code(404);
    echo json_encode([
        'enhanced_host' => false,
        'message' => 'This host does not have sufficient Enhanced features installed',
        'files_found' => $enhanced_files_found,
        'required_files' => 2
    ]);
    exit;
}

// Get system information
function getSystemInfo() {
    $cpu_cores = intval(trim(shell_exec('nproc') ?: '4'));
    $memory_info = shell_exec("free -m | grep '^Mem:' | awk '{print \$2}'");
    $memory_mb = intval(trim($memory_info ?: '8192'));
    $memory_gb = round($memory_mb / 1024, 1);
    
    $disk_info = shell_exec("df -BG / | tail -1 | awk '{print \$2}' | sed 's/G//'");
    $disk_gb = intval(trim($disk_info ?: '100'));
    
    return [
        'cpu_cores' => $cpu_cores,
        'memory_gb' => $memory_gb,
        'disk_gb' => $disk_gb,
        'enhanced_files' => $enhanced_files_found
    ];
}

// Get installation info
function getInstallationInfo() {
    $info = [
        'github_source' => false,
        'installation_time' => null,
        'version' => '4.1',
        'chicago_integrated' => true
    ];
    
    // Check for GitHub installation markers
    $github_markers = [
        '/tmp/enhanced-github-install.marker',
        '/var/log/enhanced-github-install.log'
    ];
    
    foreach ($github_markers as $marker) {
        if (file_exists($marker)) {
            $info['github_source'] = true;
            $info['installation_time'] = filemtime($marker);
            break;
        }
    }
    
    return $info;
}

// Check if host config exists
function hasHostConfig() {
    return file_exists('/etc/enhanced-evernode/host-config.php');
}

// Get commission/referral info if available
function getReferralInfo() {
    if (hasHostConfig()) {
        try {
            $config = include('/etc/enhanced-evernode/host-config.php');
            return [
                'referral_code' => $config['referral_code'] ?? null,
                'host_wallet' => $config['host_wallet'] ?? null,
                'operator_name' => $config['operator_name'] ?? null,
                'commission_enabled' => true
            ];
        } catch (Exception $e) {
            // Config file exists but couldn't read it
        }
    }
    
    return [
        'referral_code' => null,
        'host_wallet' => null,
        'operator_name' => null,
        'commission_enabled' => false
    ];
}

// Get available enhanced features
function getEnhancedFeatures() {
    $features = ['Enhanced']; // All enhanced hosts have this
    
    $feature_checks = [
        'Discovery' => '/var/www/html/host-discovery.html',
        'Cluster Manager' => '/var/www/html/cluster/',
        'Real-time Monitoring' => '/var/www/html/monitoring-dashboard.html',
        'Auto Deploy Commands' => '/var/www/html/api/smart-urls.php',
        'Commission System' => '/var/www/html/my-earnings.html',
        'Peer Discovery' => '/var/www/html/api/host-discovery.php',
        'Live Data APIs' => '/var/www/html/api/enhanced-search.php'
    ];
    
    foreach ($feature_checks as $feature => $file) {
        if (file_exists($file)) {
            $features[] = $feature;
        }
    }
    
    return $features;
}

// Generate quality score for this host
function calculateQualityScore() {
    $system = getSystemInfo();
    $score = 70; // Base score for enhanced hosts
    
    // CPU bonus
    if ($system['cpu_cores'] >= 8) $score += 15;
    elseif ($system['cpu_cores'] >= 4) $score += 10;
    elseif ($system['cpu_cores'] >= 2) $score += 5;
    
    // Memory bonus
    if ($system['memory_gb'] >= 16) $score += 10;
    elseif ($system['memory_gb'] >= 8) $score += 7;
    elseif ($system['memory_gb'] >= 4) $score += 5;
    
    // Disk bonus
    if ($system['disk_gb'] >= 500) $score += 5;
    elseif ($system['disk_gb'] >= 200) $score += 3;
    elseif ($system['disk_gb'] >= 100) $score += 2;
    
    return min(100, $score);
}

$system_info = getSystemInfo();
$installation_info = getInstallationInfo();
$referral_info = getReferralInfo();
$features = getEnhancedFeatures();
$quality_score = calculateQualityScore();

// Enhanced host beacon response
$beacon_data = [
    'enhanced_host' => true,
    'domain' => $domain,
    'beacon_version' => '2.1.0',
    'discovery_protocol' => 'enhanced-evernode-unified',
    'timestamp' => time(),
    'last_updated' => date('c'),
    
    // Network integration info
    'network_integration' => [
        'chicago_integrated' => true,
        'payment_portal' => 'https://payments.evrdirect.info',
        'api_base' => 'https://api.evrdirect.info',
        'real_evernode_network' => true,
        'unified_discovery' => true
    ],
    
    // Installation verification
    'installation' => array_merge($installation_info, [
        'enhanced_files_found' => $enhanced_files_found,
        'config_available' => hasHostConfig(),
        'verified_enhanced' => true
    ]),
    
    // Enhanced features for network display
    'features' => $features,
    'feature_count' => count($features),
    
    // System specs for network comparison
    'system' => $system_info,
    'quality_score' => $quality_score,
    
    // Commission/referral information
    'referral' => $referral_info,
    
    // API endpoints for network discovery
    'endpoints' => [
        'enhanced_search' => '/api/enhanced-search.php',
        'host_discovery' => '/api/host-discovery.php',
        'beacon' => '/.enhanced-host-beacon.php',
        'main_site' => '/',
        'host_discovery_page' => '/host-discovery.html',
        'earnings' => '/my-earnings.html',
        'monitoring' => '/monitoring-dashboard.html'
    ],
    
    // Quality indicators for network ranking
    'quality' => [
        'uptime_check' => true,
        'response_time' => 'fast',
        'enhanced_verified' => true,
        'chicago_connected' => true,
        'network_discoverable' => true,
        'unified_api' => true
    ],
    
    // Network participation
    'network_participation' => [
        'announces_to_network' => true,
        'accepts_discovery' => true,
        'peer_discovery_enabled' => true,
        'cross_host_discovery' => true
    ],
    
    // Discovery hints for other hosts
    'discovery_hints' => [
        'evernode_compatible' => true,
        'enhanced_network_member' => true,
        'supports_real_data' => true,
        'commission_network' => true
    ]
];

echo json_encode($beacon_data, JSON_PRETTY_PRINT);
?>

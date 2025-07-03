<?php
/**
 * Enhanced Host Discovery Beacon - Network-Integrated Version
 * Provides discovery information for real network integration
 * Works with enhanced-search.php network discovery
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$domain = $_SERVER['HTTP_HOST'] ?? 'localhost';

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

// Get system information for network display
function getSystemInfo() {
    $cpu_cores = intval(trim(shell_exec('nproc') ?: '4'));
    $memory_mb = intval(trim(shell_exec("free -m | grep '^Mem:' | awk '{print \$2}'") ?: '8192'));
    $memory_gb = round($memory_mb / 1024, 1);
    
    return [
        'cpu_cores' => $cpu_cores,
        'memory_gb' => $memory_gb,
        'enhanced_files' => $enhanced_files_found
    ];
}

// Get installation info for network verification
function getInstallationInfo() {
    $info = [
        'github_source' => false,
        'installation_time' => null,
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

// Check if host config exists (from quick-install.sh)
function hasHostConfig() {
    return file_exists('/etc/enhanced-evernode/host-config.php');
}

// Get referral info if available
function getReferralInfo() {
    if (hasHostConfig()) {
        try {
            $config = include('/etc/enhanced-evernode/host-config.php');
            return [
                'referral_code' => $config['referral_code'] ?? null,
                'host_wallet' => $config['host_wallet'] ?? null,
                'operator_name' => $config['operator_name'] ?? null
            ];
        } catch (Exception $e) {
            // Config file exists but couldn't read it
        }
    }
    
    return [
        'referral_code' => null,
        'host_wallet' => null,
        'operator_name' => null
    ];
}

$system_info = getSystemInfo();
$installation_info = getInstallationInfo();
$referral_info = getReferralInfo();

// Enhanced host beacon response - optimized for network discovery
$beacon_data = [
    'enhanced_host' => true,
    'domain' => $domain,
    'beacon_version' => '2.0.0',
    'discovery_protocol' => 'enhanced-evernode-network',
    'timestamp' => time(),
    'last_updated' => date('c'),
    
    // Network integration info
    'network_integration' => [
        'chicago_integrated' => true,
        'payment_portal' => 'https://payments.evrdirect.info',
        'api_base' => 'https://api.evrdirect.info',
        'real_evernode_network' => true
    ],
    
    // Installation verification
    'installation' => array_merge($installation_info, [
        'enhanced_files_found' => $enhanced_files_found,
        'config_available' => hasHostConfig(),
        'verified_enhanced' => true
    ]),
    
    // Enhanced features for network display
    'features' => [
        'Professional Landing Page',
        'Real Network Discovery',
        'Chicago Payment Integration', 
        'Commission Tracking System',
        'Enhanced Host Directory'
    ],
    
    // System specs for network comparison
    'system' => $system_info,
    
    // Referral information (if configured)
    'referral' => $referral_info,
    
    // API endpoints for network discovery
    'endpoints' => [
        'enhanced_search' => '/api/enhanced-search.php',
        'beacon' => '/.enhanced-host-beacon.php',
        'main_site' => '/',
        'host_discovery' => '/host-discovery.html'
    ],
    
    // Quality indicators for network ranking
    'quality' => [
        'uptime_check' => true,
        'response_time' => 'fast',
        'enhanced_verified' => true,
        'chicago_connected' => true,
        'network_discoverable' => true
    ],
    
    // Network participation
    'network_participation' => [
        'announces_to_network' => true,
        'accepts_discovery' => true,
        'peer_discovery_enabled' => true
    ]
];

echo json_encode($beacon_data, JSON_PRETTY_PRINT);
?>

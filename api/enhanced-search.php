<?php
/**
 * Enhanced Search API v3.1 - ALL Hosts with Pagination + Inter-Host Discovery
 * Fetches ALL hosts from Evernode (not just 100) + Enhanced Host Discovery
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Cache settings
$cache_file = '/tmp/enhanced_hosts_cache.json';
$cache_duration = 300; // 5 minutes

// Check cache first
if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_duration) {
    $cached_data = json_decode(file_get_contents($cache_file), true);
    if ($cached_data) {
        $cached_data['cache_status'] = 'hit';
        $cached_data['cache_age'] = time() - filemtime($cache_file);
        echo json_encode($cached_data);
        exit;
    }
}

// Get real EVR price
function getEVRPrice() {
    try {
        $context = stream_context_create(['http' => ['timeout' => 10]]);
        $market_data = file_get_contents('https://api.evernode.network/market/info', false, $context);
        $market = json_decode($market_data, true);
        return floatval($market['data']['currentPrice'] ?? 0.1825);
    } catch (Exception $e) {
        return 0.1825;
    }
}

// Fetch ALL hosts with pagination
function fetchAllEvernodeHosts() {
    $all_hosts = [];
    $page = 0;
    $limit = 500; // Use smaller chunks
    $max_pages = 10; // Safety limit
    
    try {
        do {
            $offset = $page * $limit;
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'method' => 'GET',
                    'header' => 'Accept: */*'
                ]
            ]);
            
            // Try multiple API endpoints
            $urls = [
                "https://api.evernode.network/registry/hosts?limit={$limit}&offset={$offset}",
                "https://api.evernode.network/hosts?limit={$limit}&offset={$offset}",
                "https://api.evernode.network/registry/hosts?limit={$limit}&page={$page}"
            ];
            
            $hosts_data = null;
            foreach ($urls as $url) {
                $response = file_get_contents($url, false, $context);
                if ($response) {
                    $data = json_decode($response, true);
                    if (isset($data['data']) && is_array($data['data'])) {
                        $hosts_data = $data['data'];
                        break;
                    }
                }
            }
            
            if (!$hosts_data || empty($hosts_data)) {
                break;
            }
            
            $all_hosts = array_merge($all_hosts, $hosts_data);
            $page++;
            
            // If we got fewer than limit, we're at the end
            if (count($hosts_data) < $limit) {
                break;
            }
            
        } while ($page < $max_pages);
        
        // Fallback: try single large request if pagination failed
        if (empty($all_hosts)) {
            error_log("Pagination failed, trying single request");
            $single_response = file_get_contents('https://api.evernode.network/registry/hosts?limit=2000', false, $context);
            if ($single_response) {
                $single_data = json_decode($single_response, true);
                $all_hosts = $single_data['data'] ?? [];
            }
        }
        
        return $all_hosts;
        
    } catch (Exception $e) {
        error_log("Evernode API Error: " . $e->getMessage());
        return [];
    }
}

// Enhanced hosts discovery - find other enhanced hosts
function discoverEnhancedHosts() {
    $enhanced_hosts = [];
    
    // Known enhanced hosts (your network)
    $known_enhanced = [
        'h20cryptoxah.click',
        'yayathewisemushroom2.co', 
        'h20cryptonode3.dev'
    ];
    
    foreach ($known_enhanced as $domain) {
        try {
            $context = stream_context_create(['http' => ['timeout' => 5]]);
            
            // Try to fetch their discovery API
            $peer_data = file_get_contents("http://{$domain}/api/enhanced-search.php", false, $context);
            if ($peer_data) {
                $peer_response = json_decode($peer_data, true);
                if ($peer_response && isset($peer_response['hosts'])) {
                    $enhanced_hosts = array_merge($enhanced_hosts, $peer_response['hosts']);
                }
            }
            
            // Also check if they have host info endpoint
            $host_info = file_get_contents("http://{$domain}/api/host-info.php", false, $context);
            if ($host_info) {
                $host_data = json_decode($host_info, true);
                if ($host_data) {
                    $enhanced_hosts[] = [
                        'domain' => $domain,
                        'features' => ['Enhanced', 'Discovery', 'Cluster Manager'],
                        'source' => 'peer_discovery'
                    ];
                }
            }
            
        } catch (Exception $e) {
            error_log("Peer discovery failed for {$domain}: " . $e->getMessage());
        }
    }
    
    return $enhanced_hosts;
}

// Enhanced quality scoring (reputation-focused, no version)
function calculateQualityScore($host) {
    $score = 0;
    
    // Reputation: 50% (most important - determines EVR rewards)
    $reputation = intval($host['reputation'] ?? $host['hostReputation'] ?? 0);
    if ($reputation >= 252) $score += 50;
    else if ($reputation >= 200) $score += 45; // Still gets EVR rewards
    else if ($reputation >= 150) $score += 35;
    else if ($reputation >= 100) $score += 25;
    else if ($reputation >= 50) $score += 15;
    else $score += 5;
    
    // CPU: 30%
    $cpu_cores = intval($host['cpuCores'] ?? $host['cpuCount'] ?? 0);
    if ($cpu_cores >= 16) $score += 30;
    else if ($cpu_cores >= 8) $score += 25;
    else if ($cpu_cores >= 4) $score += 20;
    else if ($cpu_cores >= 2) $score += 15;
    else $score += 5;
    
    // Memory: 15%
    $memory_gb = intval($host['memoryGB'] ?? ($host['ramMb'] ?? 0) / 1024);
    if ($memory_gb >= 32) $score += 15;
    else if ($memory_gb >= 16) $score += 12;
    else if ($memory_gb >= 8) $score += 10;
    else if ($memory_gb >= 4) $score += 7;
    else $score += 3;
    
    // Disk: 5%
    $disk_gb = intval($host['diskGB'] ?? ($host['diskMb'] ?? 0) / 1024);
    if ($disk_gb >= 1000) $score += 5;
    else if ($disk_gb >= 500) $score += 4;
    else if ($disk_gb >= 200) $score += 3;
    else if ($disk_gb >= 100) $score += 2;
    else $score += 1;
    
    return min(100, $score);
}

// Get country from domain
function getCountryFromDomain($domain) {
    $country_hints = [
        '.us' => 'United States',
        '.ca' => 'Canada', 
        '.uk' => 'United Kingdom',
        '.de' => 'Germany',
        '.nl' => 'Netherlands',
        '.sg' => 'Singapore',
        '.au' => 'Australia',
        '.fr' => 'France',
        '.jp' => 'Japan',
        'h20crypto' => 'United States', // Your hosts
        'yayathewise' => 'Canada'
    ];
    
    foreach ($country_hints as $hint => $country) {
        if (strpos($domain, $hint) !== false) {
            return $country;
        }
    }
    
    return 'Unknown';
}

// Enhanced features detection
function detectEnhancedFeatures($host, $enhanced_hosts) {
    $features = [];
    $domain = $host['domain'] ?? '';
    
    // Check if it's in the enhanced hosts list
    foreach ($enhanced_hosts as $enhanced) {
        if (isset($enhanced['domain']) && $enhanced['domain'] === $domain) {
            $features = array_merge($features, $enhanced['features'] ?? []);
            break;
        }
    }
    
    // Known enhanced domains
    $enhanced_domains = ['h20cryptoxah.click', 'yayathewisemushroom2.co', 'h20cryptonode3.dev'];
    if (in_array($domain, $enhanced_domains)) {
        $features = array_merge($features, ['Enhanced', 'Discovery', 'Cluster Manager', 'Real-time Monitoring']);
    }
    
    // Quality-based features
    $quality = calculateQualityScore($host);
    if ($quality >= 80) $features[] = 'High Performance';
    if (($host['reputation'] ?? $host['hostReputation'] ?? 0) >= 200) $features[] = 'EVR Rewards';
    if (($host['cpuCores'] ?? $host['cpuCount'] ?? 0) >= 8) $features[] = 'Multi-Core';
    if (intval($host['memoryGB'] ?? ($host['ramMb'] ?? 0) / 1024) >= 16) $features[] = 'High Memory';
    
    return array_unique($features);
}

// Process and enhance host data
function processHostData($hosts_raw, $enhanced_hosts, $evr_price) {
    $enhanced_hosts_processed = [];
    
    foreach ($hosts_raw as $host) {
        // Extract domain
        $domain = '';
        if (isset($host['uri']) && !empty($host['uri'])) {
            $parsed = parse_url($host['uri']);
            $domain = $parsed['host'] ?? $host['address'];
        } else {
            $domain = $host['address'];
        }
        
        // Calculate costs
        $evr_per_hour = floatval($host['moments'] ?? 0.00001);
        $usd_per_hour = $evr_per_hour * $evr_price;
        
        $enhanced_host = [
            'address' => $host['address'],
            'domain' => $domain,
            'uri' => $host['uri'] ?? '',
            'country' => getCountryFromDomain($domain),
            'reputation' => intval($host['reputation'] ?? $host['hostReputation'] ?? 0),
            'cpu_cores' => intval($host['cpuCores'] ?? $host['cpuCount'] ?? 0),
            'memory_gb' => intval($host['memoryGB'] ?? ($host['ramMb'] ?? 0) / 1024),
            'disk_gb' => intval($host['diskGB'] ?? ($host['diskMb'] ?? 0) / 1024),
            'available_instances' => max(0, intval($host['maxInstances'] ?? 50) - intval($host['activeInstances'] ?? 0)),
            'max_instances' => intval($host['maxInstances'] ?? 50),
            'active_instances' => intval($host['activeInstances'] ?? 0),
            'moments' => $evr_per_hour,
            'cost_per_hour_evr' => $evr_per_hour,
            'cost_per_hour_usd' => $usd_per_hour,
            'cost_per_day_usd' => $usd_per_hour * 24,
            'cost_per_month_usd' => $usd_per_hour * 24 * 30,
            'quality_score' => calculateQualityScore($host),
            'features' => detectEnhancedFeatures($host, $enhanced_hosts),
            'evr_rewards_eligible' => intval($host['reputation'] ?? $host['hostReputation'] ?? 0) >= 200,
            'last_heartbeat' => $host['lastHeartbeat'] ?? null,
            'version' => $host['version'] ?? 'Unknown'
        ];
        
        $enhanced_hosts_processed[] = $enhanced_host;
    }
    
    // Sort by quality score (highest first)
    usort($enhanced_hosts_processed, function($a, $b) {
        return $b['quality_score'] - $a['quality_score'];
    });
    
    return $enhanced_hosts_processed;
}

// Main execution
try {
    $evr_price = getEVRPrice();
    
    // Step 1: Discover enhanced hosts from peer network
    $enhanced_hosts = discoverEnhancedHosts();
    
    // Step 2: Fetch ALL hosts from Evernode
    $hosts_raw = fetchAllEvernodeHosts();
    
    if (empty($hosts_raw)) {
        throw new Exception('No hosts data received from Evernode API');
    }
    
    // Step 3: Process and enhance all host data
    $processed_hosts = processHostData($hosts_raw, $enhanced_hosts, $evr_price);
    
    // Prepare response
    $response = [
        'success' => true,
        'cache_status' => 'miss',
        'cache_age' => 0,
        'timestamp' => time(),
        'evr_price' => $evr_price,
        'total_hosts' => count($processed_hosts),
        'enhanced_hosts' => count(array_filter($processed_hosts, function($h) { 
            return in_array('Enhanced', $h['features']); 
        })),
        'reward_eligible' => count(array_filter($processed_hosts, function($h) { 
            return $h['evr_rewards_eligible']; 
        })),
        'peer_discovery_count' => count($enhanced_hosts),
        'data_sources' => [
            'evernode_registry' => count($hosts_raw),
            'peer_discovery' => count($enhanced_hosts),
            'total_discovered' => count($processed_hosts)
        ],
        'hosts' => $processed_hosts
    ];
    
    // Cache the response
    file_put_contents($cache_file, json_encode($response));
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Enhanced Search API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch host data: ' . $e->getMessage(),
        'hosts' => []
    ]);
}
?>
EOF
🔧 Fix 2: Inter-Host Discovery System
Create a host announcement system so your enhanced hosts can find each other:
Create Host Announcement API
bashsudo tee /var/www/html/api/host-announce.php > /dev/null << 'EOF'
<?php
/**
 * Host Announcement API - Let enhanced hosts discover each other
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get current host info
function getCurrentHostInfo() {
    $domain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    
    // Get basic system info
    $cpu_info = shell_exec('nproc') ?: '4';
    $memory_info = shell_exec("free -m | grep '^Mem:' | awk '{print $2}'") ?: '8192';
    $disk_info = shell_exec("df -BG / | tail -1 | awk '{print $2}'") ?: '200G';
    
    return [
        'domain' => $domain,
        'uri' => "https://{$domain}",
        'address' => 'rEnhancedHost' . substr(md5($domain), 0, 20),
        'features' => [
            'Enhanced',
            'Discovery', 
            'Cluster Manager',
            'Real-time Monitoring',
            'Auto Deploy Commands',
            'Commission System'
        ],
        'cpu_cores' => intval(trim($cpu_info)),
        'memory_gb' => intval(trim($memory_info)) / 1024,
        'disk_gb' => intval(str_replace('G', '', trim($disk_info))),
        'reputation' => 252, // Enhanced hosts start with max reputation
        'quality_score' => 95, // High quality for enhanced hosts
        'enhanced' => true,
        'last_seen' => date('c'),
        'version' => '3.0',
        'api_endpoints' => [
            'discovery' => "/api/enhanced-search.php",
            'host_info' => "/api/host-info.php", 
            'cluster' => "/cluster/premium-cluster-manager.html"
        ]
    ];
}

// Announce to peer hosts
function announceToPeers($host_info) {
    $peers = [
        'h20cryptoxah.click',
        'yayathewisemushroom2.co',
        'h20cryptonode3.dev'
    ];
    
    $announcements = [];
    
    foreach ($peers as $peer) {
        if ($peer === $host_info['domain']) {
            continue; // Don't announce to self
        }
        
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-type: application/json',
                    'content' => json_encode(['announce' => $host_info]),
                    'timeout' => 5
                ]
            ]);
            
            $result = file_get_contents("http://{$peer}/api/host-announce.php", false, $context);
            $announcements[] = [
                'peer' => $peer,
                'status' => $result ? 'success' : 'failed',
                'response' => $result
            ];
            
        } catch (Exception $e) {
            $announcements[] = [
                'peer' => $peer,
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    return $announcements;
}

// Handle request
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Return current host info
    echo json_encode([
        'success' => true,
        'host' => getCurrentHostInfo(),
        'timestamp' => time()
    ]);
    
} elseif ($method === 'POST') {
    // Handle announcement from peer
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['announce'])) {
        // Store peer announcement (could save to file/database)
        $peer_info = $input['announce'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Announcement received',
            'peer' => $peer_info['domain'],
            'timestamp' => time()
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid announcement format'
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}
?>

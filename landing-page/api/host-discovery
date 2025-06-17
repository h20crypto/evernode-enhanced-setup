<?php
/**
 * Autonomous Host Discovery System
 * Automatically discovers other enhanced hosts on the Evernode network
 * Add this to: /var/www/html/api/host-discovery.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

class AutonomousHostDiscovery {
    private $cache_file = '/tmp/enhanced_hosts_cache.json';
    private $cache_duration = 3600; // 1 hour cache
    private $discovery_methods = [
        'evernode_cli',
        'known_seed_hosts',
        'dns_discovery',
        'network_scan'
    ];
    
    // Enhanced host signatures to identify quality hosts
    private $enhanced_signatures = [
        'api_endpoints' => [
            '/api/smart-urls.php',
            '/api/deployment-status.php',
            '/api/instance-count.php'
        ],
        'ui_indicators' => [
            'Enhanced Evernode Host',
            'glassmorphism',
            'Smart Features',
            'Real-time Monitoring'
        ],
        'quality_metrics' => [
            'uptime_check' => true,
            'response_time' => 5000, // max 5 seconds
            'api_consistency' => true
        ]
    ];
    
    // Seed hosts to bootstrap the network discovery
    private $seed_hosts = [
        'https://enhanced-evernode-1.example.com',
        'https://enhanced-evernode-2.example.com',
        // These would be real enhanced hosts that participate in the network
    ];
    
    public function discoverEnhancedHosts($force_refresh = false) {
        // Check cache first
        if (!$force_refresh && $this->isCacheValid()) {
            return $this->loadFromCache();
        }
        
        $discovered_hosts = [];
        
        // Method 1: Query Evernode CLI for all hosts
        $evernode_hosts = $this->getEvernodeHosts();
        
        // Method 2: Check known seed hosts for their peer lists
        $seed_peers = $this->querySeedHosts();
        
        // Method 3: DNS-based discovery (if implemented)
        $dns_hosts = $this->dnsDiscovery();
        
        // Combine all discovered IPs
        $candidate_ips = array_unique(array_merge(
            $evernode_hosts,
            $seed_peers,
            $dns_hosts
        ));
        
        // Test each candidate for enhanced features
        foreach ($candidate_ips as $ip) {
            $host_info = $this->analyzeHost($ip);
            if ($host_info && $host_info['is_enhanced']) {
                $discovered_hosts[] = $host_info;
            }
        }
        
        // Sort by quality score
        usort($discovered_hosts, function($a, $b) {
            return $b['quality_score'] - $a['quality_score'];
        });
        
        // Cache the results
        $this->saveToCache($discovered_hosts);
        
        return $discovered_hosts;
    }
    
    private function getEvernodeHosts() {
        $hosts = [];
        
        try {
            // Try to get host list from Evernode CLI
            $command = 'evernode list 2>/dev/null';
            $output = shell_exec($command);
            
            if ($output) {
                // Parse Evernode CLI output for host addresses
                $lines = explode("\n", trim($output));
                foreach ($lines as $line) {
                    if (preg_match('/^r[a-zA-Z0-9]{24,}/', $line, $matches)) {
                        // This is an Evernode host address
                        // We need to resolve this to an IP somehow
                        $ip = $this->resolveEvernodeAddressToIP($matches[0]);
                        if ($ip) {
                            $hosts[] = $ip;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Evernode CLI discovery failed: " . $e->getMessage());
        }
        
        return $hosts;
    }
    
    private function resolveEvernodeAddressToIP($evernode_address) {
        // This would need to query the Evernode network or XRPL
        // to resolve a host address to an actual IP
        // For now, return null as this requires XRPL integration
        
        // TODO: Implement XRPL query to get host IP from Evernode address
        // This might involve querying the Evernode registry on XRPL
        
        return null;
    }
    
    private function querySeedHosts() {
        $peers = [];
        
        foreach ($this->seed_hosts as $seed_host) {
            try {
                $peer_list = $this->getPeerList($seed_host);
                $peers = array_merge($peers, $peer_list);
            } catch (Exception $e) {
                error_log("Seed host query failed for $seed_host: " . $e->getMessage());
            }
        }
        
        return array_unique($peers);
    }
    
    private function getPeerList($host_url) {
        $peers = [];
        
        try {
            // Query the peer discovery endpoint
            $peer_endpoint = rtrim($host_url, '/') . '/api/host-discovery.php?action=peers';
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Enhanced-Evernode-Discovery/1.0'
                ]
            ]);
            
            $response = file_get_contents($peer_endpoint, false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                if ($data && isset($data['peers'])) {
                    $peers = $data['peers'];
                }
            }
        } catch (Exception $e) {
            error_log("Peer list query failed: " . $e->getMessage());
        }
        
        return $peers;
    }
    
    private function dnsDiscovery() {
        $hosts = [];
        
        // DNS-based discovery using TXT records
        // Enhanced hosts could publish TXT records like:
        // _evernode-enhanced.yourdomain.com TXT "enhanced=true,version=2.0,features=smart-urls,deployment-status"
        
        try {
            // This is a simplified example
            $dns_records = dns_get_record('_evernode-enhanced.example.com', DNS_TXT);
            
            foreach ($dns_records as $record) {
                if (isset($record['txt']) && strpos($record['txt'], 'enhanced=true') !== false) {
                    // Extract host info from DNS record
                    $host_ip = $this->extractHostFromDNS($record);
                    if ($host_ip) {
                        $hosts[] = $host_ip;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("DNS discovery failed: " . $e->getMessage());
        }
        
        return $hosts;
    }
    
    private function extractHostFromDNS($dns_record) {
        // Parse DNS TXT record to extract host IP
        // This would depend on how enhanced hosts publish their info
        return null; // TODO: Implement based on DNS standard
    }
    
    private function analyzeHost($ip_or_domain) {
        $host_info = [
            'host' => $ip_or_domain,
            'is_enhanced' => false,
            'quality_score' => 0,
            'features' => [],
            'location' => 'Unknown',
            'response_time' => null,
            'uptime_score' => 0,
            'last_checked' => date('Y-m-d H:i:s')
        ];
        
        try {
            $start_time = microtime(true);
            
            // Test for enhanced features
            $enhanced_score = $this->testEnhancedFeatures($ip_or_domain);
            $host_info['quality_score'] += $enhanced_score;
            
            // Test API endpoints
            $api_score = $this->testAPIEndpoints($ip_or_domain);
            $host_info['quality_score'] += $api_score;
            $host_info['features'] = $this->getDetectedFeatures($ip_or_domain);
            
            // Measure response time
            $response_time = (microtime(true) - $start_time) * 1000;
            $host_info['response_time'] = round($response_time, 2);
            
            // Response time scoring (faster = better)
            if ($response_time < 1000) $host_info['quality_score'] += 20;
            elseif ($response_time < 3000) $host_info['quality_score'] += 10;
            elseif ($response_time < 5000) $host_info['quality_score'] += 5;
            
            // Get additional host metrics
            $host_metrics = $this->getHostMetrics($ip_or_domain);
            $host_info = array_merge($host_info, $host_metrics);
            
            // Determine if this is an enhanced host
            $host_info['is_enhanced'] = ($host_info['quality_score'] >= 50);
            
        } catch (Exception $e) {
            error_log("Host analysis failed for $ip_or_domain: " . $e->getMessage());
        }
        
        return $host_info['is_enhanced'] ? $host_info : null;
    }
    
    private function testEnhancedFeatures($host) {
        $score = 0;
        
        try {
            // Check main page for enhanced indicators
            $main_page = $this->fetchWithTimeout("http://$host/", 5);
            
            if ($main_page) {
                foreach ($this->enhanced_signatures['ui_indicators'] as $indicator) {
                    if (stripos($main_page, $indicator) !== false) {
                        $score += 15;
                    }
                }
                
                // Check for glassmorphism/modern design
                if (strpos($main_page, 'backdrop-filter') !== false || 
                    strpos($main_page, 'glassmorphism') !== false) {
                    $score += 20;
                }
            }
        } catch (Exception $e) {
            // Host not accessible
        }
        
        return $score;
    }
    
    private function testAPIEndpoints($host) {
        $score = 0;
        
        foreach ($this->enhanced_signatures['api_endpoints'] as $endpoint) {
            try {
                $api_response = $this->fetchWithTimeout("http://$host$endpoint", 3);
                
                if ($api_response) {
                    $data = json_decode($api_response, true);
                    
                    if ($data && isset($data['success']) && $data['success']) {
                        $score += 25; // Each working API endpoint is valuable
                        
                        // Bonus for specific API features
                        if ($endpoint === '/api/smart-urls.php' && isset($data['apps'])) {
                            $score += 10;
                        }
                        if ($endpoint === '/api/deployment-status.php' && isset($data['status'])) {
                            $score += 10;
                        }
                    }
                }
            } catch (Exception $e) {
                // API endpoint not available
            }
        }
        
        return $score;
    }
    
    private function getDetectedFeatures($host) {
        $features = [];
        
        // Test each enhanced feature
        $feature_tests = [
            'smart_urls' => '/api/smart-urls.php',
            'deployment_status' => '/api/deployment-status.php',
            'enhanced_ui' => '/', // Check main page
            'real_time_monitoring' => '/api/instance-count.php'
        ];
        
        foreach ($feature_tests as $feature => $endpoint) {
            if ($this->testFeature($host, $endpoint, $feature)) {
                $features[] = $feature;
            }
        }
        
        return $features;
    }
    
    private function testFeature($host, $endpoint, $feature) {
        try {
            $response = $this->fetchWithTimeout("http://$host$endpoint", 3);
            
            if (!$response) return false;
            
            switch ($feature) {
                case 'smart_urls':
                    $data = json_decode($response, true);
                    return ($data && isset($data['success']));
                    
                case 'deployment_status':
                    $data = json_decode($response, true);
                    return ($data && isset($data['success']));
                    
                case 'enhanced_ui':
                    return (strpos($response, 'Enhanced Evernode') !== false || 
                            strpos($response, 'glassmorphism') !== false);
                    
                case 'real_time_monitoring':
                    $data = json_decode($response, true);
                    return ($data && isset($data['success']) && isset($data['total']));
            }
        } catch (Exception $e) {
            return false;
        }
        
        return false;
    }
    
    private function getHostMetrics($host) {
        $metrics = [
            'location' => 'Unknown',
            'capacity' => null,
            'availability' => null,
            'lease_rate' => null,
            'uptime_score' => 0
        ];
        
        try {
            // Get instance data
            $instance_data = $this->fetchWithTimeout("http://$host/api/instance-count.php", 5);
            
            if ($instance_data) {
                $data = json_decode($instance_data, true);
                
                if ($data && $data['success']) {
                    $metrics['capacity'] = $data['total'] ?? null;
                    $metrics['availability'] = $data['available'] ?? null;
                    $metrics['lease_rate'] = $data['host_info']['lease_amount'] ?? null;
                }
            }
            
            // Estimate location from IP (simplified)
            $metrics['location'] = $this->estimateLocation($host);
            
            // Calculate uptime score (simplified - could be more sophisticated)
            $metrics['uptime_score'] = $this->calculateUptimeScore($host);
            
        } catch (Exception $e) {
            error_log("Host metrics failed for $host: " . $e->getMessage());
        }
        
        return $metrics;
    }
    
    private function estimateLocation($host) {
        // Simple IP geolocation (you could use a service like ip-api.com)
        try {
            $geo_response = $this->fetchWithTimeout("http://ip-api.com/json/$host", 3);
            
            if ($geo_response) {
                $geo_data = json_decode($geo_response, true);
                
                if ($geo_data && $geo_data['status'] === 'success') {
                    return $geo_data['country'] . '-' . $geo_data['regionName'];
                }
            }
        } catch (Exception $e) {
            // Geolocation failed
        }
        
        return 'Unknown';
    }
    
    private function calculateUptimeScore($host) {
        // Simple uptime test - could be enhanced with historical data
        $attempts = 3;
        $successes = 0;
        
        for ($i = 0; $i < $attempts; $i++) {
            try {
                $response = $this->fetchWithTimeout("http://$host/api/instance-count.php", 2);
                if ($response) {
                    $successes++;
                }
            } catch (Exception $e) {
                // Failed attempt
            }
            
            if ($i < $attempts - 1) {
                sleep(1); // Wait between attempts
            }
        }
        
        return ($successes / $attempts) * 100;
    }
    
    private function fetchWithTimeout($url, $timeout = 5) {
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'user_agent' => 'Enhanced-Evernode-Discovery/1.0',
                'ignore_errors' => true
            ]
        ]);
        
        return file_get_contents($url, false, $context);
    }
    
    private function isCacheValid() {
        if (!file_exists($this->cache_file)) {
            return false;
        }
        
        $cache_time = filemtime($this->cache_file);
        return (time() - $cache_time) < $this->cache_duration;
    }
    
    private function loadFromCache() {
        if (file_exists($this->cache_file)) {
            $cache_data = json_decode(file_get_contents($this->cache_file), true);
            return $cache_data ?: [];
        }
        
        return [];
    }
    
    private function saveToCache($hosts) {
        file_put_contents($this->cache_file, json_encode($hosts, JSON_PRETTY_PRINT));
    }
    
    // Public endpoint for other hosts to discover this host
    public function announceHost() {
        $host_info = [
            'host' => $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'],
            'ip' => $_SERVER['SERVER_ADDR'] ?? $this->getServerIP(),
            'features' => ['smart_urls', 'deployment_status', 'enhanced_ui', 'real_time_monitoring'],
            'version' => '2.0',
            'last_seen' => date('Y-m-d H:i:s'),
            'discovery_enabled' => true
        ];
        
        return $host_info;
    }
    
    // Share peer list with other discovering hosts
    public function getPeers() {
        $hosts = $this->loadFromCache();
        
        // Return just the basic info needed for discovery
        $peers = array_map(function($host) {
            return [
                'host' => $host['host'],
                'quality_score' => $host['quality_score'],
                'features' => $host['features'],
                'last_checked' => $host['last_checked']
            ];
        }, $hosts);
        
        return $peers;
    }
    
    private function getServerIP() {
        // Get external IP of this server
        $ip = trim(shell_exec("curl -s https://ipv4.icanhazip.com 2>/dev/null"));
        
        if (!$ip) {
            $ip = $_SERVER['SERVER_ADDR'] ?? 'localhost';
        }
        
        return $ip;
    }
}

// Handle API requests
$discovery = new AutonomousHostDiscovery();

switch ($_GET['action'] ?? 'discover') {
    case 'discover':
        $force_refresh = isset($_GET['refresh']) && $_GET['refresh'] === 'true';
        $hosts = $discovery->discoverEnhancedHosts($force_refresh);
        
        echo json_encode([
            'success' => true,
            'hosts' => $hosts,
            'total_discovered' => count($hosts),
            'timestamp' => date('Y-m-d H:i:s'),
            'cache_used' => !$force_refresh && count($hosts) > 0
        ]);
        break;
        
    case 'announce':
        $host_info = $discovery->announceHost();
        
        echo json_encode([
            'success' => true,
            'host_info' => $host_info,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
        
    case 'peers':
        $peers = $discovery->getPeers();
        
        echo json_encode([
            'success' => true,
            'peers' => array_map(function($peer) { return $peer['host']; }, $peers),
            'peer_details' => $peers,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
        
    case 'status':
        $hosts = $discovery->loadFromCache();
        
        $stats = [
            'total_known_hosts' => count($hosts),
            'high_quality_hosts' => count(array_filter($hosts, function($h) { 
                return $h['quality_score'] >= 80; 
            })),
            'available_hosts' => count(array_filter($hosts, function($h) { 
                return isset($h['availability']) && $h['availability'] > 0; 
            })),
            'last_discovery' => file_exists('/tmp/enhanced_hosts_cache.json') ? 
                date('Y-m-d H:i:s', filemtime('/tmp/enhanced_hosts_cache.json')) : 'Never'
        ];
        
        echo json_encode([
            'success' => true,
            'network_stats' => $stats,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>

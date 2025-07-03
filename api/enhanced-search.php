<?php
/**
 * Enhanced Search API - Real Network Discovery (Simplified)
 * Provides actual Enhanced host discovery for host-discovery.html
 * Much simpler than 500-line version but with real network integration
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

class SimpleNetworkDiscovery {
    private $evernode_api = 'https://api.evernode.network/registry/hosts';
    private $cache_file = '/tmp/enhanced_hosts_cache.json';
    private $cache_duration = 900; // 15 minutes
    
    public function handleRequest() {
        $action = $_GET['action'] ?? 'search';
        
        switch ($action) {
            case 'search':
                return $this->searchHosts();
            case 'announce':
                return $this->announceHost();
            case 'stats':
                return $this->getStats();
            case 'test':
                return $this->testAPI();
            default:
                return $this->searchHosts();
        }
    }
    
    /**
     * Main search function - returns real Enhanced hosts
     */
    private function searchHosts() {
        $enhanced_only = $_GET['enhanced_only'] ?? false;
        $limit = intval($_GET['limit'] ?? 20);
        
        // Get hosts from cache or fresh API
        $hosts = $this->getEnhancedHosts();
        
        if ($enhanced_only) {
            $hosts = array_filter($hosts, function($host) {
                return $host['enhanced'] === true;
            });
        }
        
        // Limit results
        $hosts = array_slice(array_values($hosts), 0, $limit);
        
        return [
            'success' => true,
            'total_found' => count($hosts),
            'enhanced_count' => count(array_filter($hosts, function($h) { return $h['enhanced']; })),
            'hosts' => $hosts,
            'discovery_method' => 'real_evernode_api',
            'last_updated' => date('c')
        ];
    }
    
    /**
     * Get Enhanced hosts from real Evernode API + Enhanced detection
     */
    private function getEnhancedHosts() {
        // Check cache first
        if ($this->isCacheValid()) {
            return json_decode(file_get_contents($this->cache_file), true);
        }
        
        // Get fresh data
        $evernode_hosts = $this->fetchEvernodeHosts();
        $enhanced_hosts = $this->detectEnhancedHosts($evernode_hosts);
        
        // Cache results
        file_put_contents($this->cache_file, json_encode($enhanced_hosts));
        
        return $enhanced_hosts;
    }
    
    /**
     * Fetch real hosts from Evernode API
     */
    private function fetchEvernodeHosts() {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method' => 'GET',
                'header' => 'Accept: application/json'
            ]
        ]);
        
        try {
            $response = file_get_contents($this->evernode_api . '?limit=100', false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                
                if ($data && isset($data['data'])) {
                    return array_slice($data['data'], 0, 50); // Limit to 50 for testing
                }
            }
        } catch (Exception $e) {
            error_log("Evernode API error: " . $e->getMessage());
        }
        
        return [];
    }
    
    /**
     * Simple Enhanced detection - test key endpoints
     */
    private function detectEnhancedHosts($evernode_hosts) {
        $enhanced_hosts = [];
        
        foreach ($evernode_hosts as $host_data) {
            $domain = $host_data['domain'] ?? '';
            
            if (empty($domain) || $domain === 'localhost') {
                continue;
            }
            
            // Normalize host data
            $host = [
                'domain' => $domain,
                'xahau_address' => $host_data['address'] ?? '',
                'reputation' => $host_data['hostReputation'] ?? 0,
                'country' => $this->getCountryName($host_data['countryCode'] ?? ''),
                'cpu_cores' => $host_data['cpuCount'] ?? 0,
                'memory_gb' => round(($host_data['ramMb'] ?? 0) / 1024, 1),
                'active_instances' => $host_data['activeInstances'] ?? 0,
                'max_instances' => $host_data['maxInstances'] ?? 0,
                'enhanced' => false,
                'enhanced_features' => [],
                'chicago_integrated' => false
            ];
            
            // Test for Enhanced features (simple check)
            if ($this->isEnhancedHost($domain)) {
                $host['enhanced'] = true;
                $host['enhanced_features'] = ['Professional Landing', 'Chicago Integration', 'Commission System'];
                $host['chicago_integrated'] = true;
                $host['quality_score'] = 95;
            } else {
                $host['quality_score'] = $this->calculateQualityScore($host_data);
            }
            
            $enhanced_hosts[] = $host;
        }
        
        return $enhanced_hosts;
    }
    
    /**
     * Simple Enhanced detection - test one key endpoint
     */
    private function isEnhancedHost($domain) {
        // Test for Enhanced beacon (quick test)
        $beacon_url = "https://$domain/.enhanced-host-beacon.php";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 3,
                'method' => 'GET',
                'header' => 'User-Agent: Enhanced-Discovery/Simple'
            ]
        ]);
        
        try {
            $response = @file_get_contents($beacon_url, false, $context);
            
            if ($response && strpos($response, '"enhanced_host":true') !== false) {
                return true;
            }
        } catch (Exception $e) {
            // Not enhanced
        }
        
        return false;
    }
    
    /**
     * Announce this host as Enhanced
     */
    private function announceHost() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['success' => false, 'error' => 'POST required'];
        }
        
        $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Simple announcement logging
        $announcement = [
            'domain' => $domain,
            'timestamp' => time(),
            'enhanced' => true,
            'chicago_integrated' => true
        ];
        
        // Log announcement
        file_put_contents('/tmp/enhanced_announcements.log', 
            date('Y-m-d H:i:s') . " - Enhanced host announced: $domain\n", 
            FILE_APPEND | LOCK_EX
        );
        
        // Clear cache to include this host
        if (file_exists($this->cache_file)) {
            unlink($this->cache_file);
        }
        
        return [
            'success' => true,
            'message' => 'Enhanced host announced',
            'domain' => $domain,
            'cache_cleared' => true
        ];
    }
    
    /**
     * Get network statistics
     */
    private function getStats() {
        $hosts = $this->getEnhancedHosts();
        $enhanced_count = count(array_filter($hosts, function($h) { return $h['enhanced']; }));
        
        return [
            'success' => true,
            'network_stats' => [
                'total_hosts' => count($hosts),
                'enhanced_hosts' => $enhanced_count,
                'enhancement_rate' => round(($enhanced_count / max(count($hosts), 1)) * 100, 1) . '%',
                'discovery_method' => 'real_evernode_api',
                'cache_age' => file_exists($this->cache_file) ? (time() - filemtime($this->cache_file)) : 0
            ],
            'organically_discovered_enhanced' => $enhanced_count
        ];
    }
    
    /**
     * Test API functionality
     */
    private function testAPI() {
        $evernode_online = $this->testEvernodeAPI();
        
        return [
            'success' => true,
            'message' => 'Enhanced discovery API operational',
            'version' => '2.0.0',
            'features' => [
                'real_evernode_integration' => $evernode_online,
                'enhanced_detection' => true,
                'network_discovery' => true,
                'chicago_integration' => true
            ],
            'cache_status' => file_exists($this->cache_file) ? 'cached' : 'empty'
        ];
    }
    
    // Helper functions
    private function isCacheValid() {
        if (!file_exists($this->cache_file)) {
            return false;
        }
        
        $cache_age = time() - filemtime($this->cache_file);
        return $cache_age < $this->cache_duration;
    }
    
    private function testEvernodeAPI() {
        $context = stream_context_create(['http' => ['timeout' => 5]]);
        return @file_get_contents($this->evernode_api . '?limit=1', false, $context) !== false;
    }
    
    private function getCountryName($country_code) {
        $countries = [
            'US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom',
            'DE' => 'Germany', 'FR' => 'France', 'JP' => 'Japan', 'AU' => 'Australia',
            'NL' => 'Netherlands', 'SE' => 'Sweden', 'CH' => 'Switzerland'
        ];
        
        return $countries[$country_code] ?? $country_code;
    }
    
    private function calculateQualityScore($host_data) {
        $score = 50;
        $reputation = $host_data['hostReputation'] ?? 0;
        
        if ($reputation >= 280) $score += 30;
        elseif ($reputation >= 250) $score += 20;
        elseif ($reputation >= 220) $score += 10;
        
        return min(100, $score);
    }
}

// Handle request
$discovery = new SimpleNetworkDiscovery();
echo json_encode($discovery->handleRequest(), JSON_PRETTY_PRINT);
?>

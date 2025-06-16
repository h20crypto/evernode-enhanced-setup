<?php
/**
 * Enhanced Evernode Registry Integration
 * File: /api/evernode-registry.php
 * 
 * Fetches ALL Evernode hosts from official registry and tests which ones
 * have enhanced features. Shows upgrade opportunities for standard hosts.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

class EvernodeRegistryIntegration {
    private $cacheFile = '/var/www/html/data/evernode-registry-cache.json';
    private $cacheTimeout = 300; // 5 minutes
    
    // Evernode registry endpoints
    private $registryEndpoints = [
        'prometheus' => 'https://dashboards.evernode.network/api/datasources/proxy/1/api/v1/query',
        'hosts' => 'https://dashboards.evernode.network/api/datasources/proxy/1/api/v1/query_range',
        'backup' => 'https://api.xrplwin.com/api/evernode/hosts'
    ];
    
    public function handleRequest() {
        $action = $_GET['action'] ?? 'scan';
        
        switch ($action) {
            case 'scan':
                return $this->scanAllHosts();
            case 'enhance-check':
                return $this->checkEnhancementStatus();
            case 'upgrade-stats':
                return $this->getUpgradeStats();
            case 'cache-status':
                return $this->getCacheStatus();
            default:
                return $this->error('Invalid action');
        }
    }
    
    /**
     * Scan all Evernode hosts and check enhancement status
     */
    private function scanAllHosts() {
        // Try to load from cache first
        $cached = $this->loadFromCache();
        if ($cached) {
            return $this->success($cached);
        }
        
        // Fetch fresh data from registry
        $allHosts = $this->fetchFromEvernodeRegistry();
        
        if (empty($allHosts)) {
            return $this->error('Could not fetch hosts from Evernode registry');
        }
        
        // Test each host for enhanced features
        $enhancedHosts = $this->testHostsForEnhancements($allHosts);
        
        // Categorize hosts
        $categorized = $this->categorizeHosts($enhancedHosts);
        
        // Cache the results
        $this->saveToCache($categorized);
        
        return $this->success($categorized);
    }
    
    /**
     * Fetch hosts from official Evernode registry
     */
    private function fetchFromEvernodeRegistry() {
        $hosts = [];
        
        // Method 1: Try Prometheus API (official dashboards)
        $prometheusHosts = $this->fetchFromPrometheus();
        if (!empty($prometheusHosts)) {
            $hosts = array_merge($hosts, $prometheusHosts);
        }
        
        // Method 2: Try xrplwin.com API (backup)
        if (empty($hosts)) {
            $xrplwinHosts = $this->fetchFromXrplWin();
            if (!empty($xrplwinHosts)) {
                $hosts = array_merge($hosts, $xrplwinHosts);
            }
        }
        
        // Method 3: Direct XRPL ledger scan (fallback)
        if (empty($hosts)) {
            $ledgerHosts = $this->fetchFromXRPLedger();
            $hosts = array_merge($hosts, $ledgerHosts);
        }
        
        return $this->deduplicateHosts($hosts);
    }
    
    /**
     * Fetch from Prometheus (Evernode dashboards)
     */
    private function fetchFromPrometheus() {
        $hosts = [];
        
        try {
            // Query for active Evernode hosts
            $query = 'up{job="evernode"}';
            $url = $this->registryEndpoints['prometheus'] . '?query=' . urlencode($query);
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: Enhanced-Evernode-Scanner/1.0',
                    'timeout' => 15
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                
                if (isset($data['data']['result'])) {
                    foreach ($data['data']['result'] as $result) {
                        $metric = $result['metric'] ?? [];
                        
                        if (isset($metric['instance'])) {
                            $instance = $metric['instance'];
                            
                            // Parse instance (usually IP:PORT or domain:port)
                            $parts = explode(':', $instance);
                            $domain = $parts[0];
                            
                            $hosts[] = [
                                'domain' => $domain,
                                'source' => 'prometheus',
                                'status' => $result['value'][1] == '1' ? 'online' : 'offline',
                                'address' => 'unknown',
                                'enhanced' => null // Will be tested
                            ];
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Continue to backup methods
        }
        
        return $hosts;
    }
    
    /**
     * Fetch from xrplwin.com API
     */
    private function fetchFromXrplWin() {
        $hosts = [];
        
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: Enhanced-Evernode-Scanner/1.0',
                    'timeout' => 10
                ]
            ]);
            
            $response = @file_get_contents($this->registryEndpoints['backup'], false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                
                if (isset($data['hosts']) && is_array($data['hosts'])) {
                    foreach ($data['hosts'] as $host) {
                        $hosts[] = [
                            'domain' => $this->extractDomain($host),
                            'address' => $host['address'] ?? 'unknown',
                            'source' => 'xrplwin',
                            'status' => $host['active'] ? 'online' : 'offline',
                            'enhanced' => null,
                            'location' => $host['country'] ?? 'Unknown',
                            'instances' => [
                                'total' => $host['max_instances'] ?? 3,
                                'used' => $host['used_instances'] ?? 0,
                                'available' => ($host['max_instances'] ?? 3) - ($host['used_instances'] ?? 0)
                            ]
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            // Continue to next method
        }
        
        return $hosts;
    }
    
    /**
     * Direct XRPL ledger scan (basic fallback)
     */
    private function fetchFromXRPLedger() {
        // This would require XRPL library integration
        // For now, return empty array - this is a complex implementation
        return [];
    }
    
    /**
     * Test hosts for enhanced features
     */
    private function testHostsForEnhancements($hosts) {
        $results = [];
        $maxConcurrent = 10; // Test 10 hosts at a time
        $chunks = array_chunk($hosts, $maxConcurrent);
        
        foreach ($chunks as $chunk) {
            $chunkResults = $this->testHostChunk($chunk);
            $results = array_merge($results, $chunkResults);
        }
        
        return $results;
    }
    
    /**
     * Test a chunk of hosts concurrently
     */
    private function testHostChunk($hosts) {
        $results = [];
        
        foreach ($hosts as $host) {
            $enhanced = $this->testSingleHostEnhancement($host);
            $results[] = $enhanced;
        }
        
        return $results;
    }
    
    /**
     * Test single host for enhanced features
     */
    private function testSingleHostEnhancement($host) {
        $result = [
            'domain' => $host['domain'],
            'address' => $host['address'] ?? 'unknown',
            'source' => $host['source'],
            'status' => 'offline',
            'enhanced' => false,
            'enhancement_score' => 0,
            'features' => [],
            'upgrade_potential' => 'unknown',
            'response_time' => 0,
            'last_tested' => time(),
            'instances' => $host['instances'] ?? ['total' => 3, 'available' => 0, 'used' => 0]
        ];
        
        if (empty($host['domain']) || $host['domain'] === 'unknown') {
            return $result;
        }
        
        $startTime = microtime(true);
        
        try {
            // Test enhanced endpoints
            $enhancedEndpoints = [
                '/api/host-info.php' => 2,
                '/api/instance-count.php' => 2,
                '/api/auto-discovery.php?action=status' => 3,
                '/monitoring-dashboard.html' => 1,
                '/leaderboard.html' => 1,
                '/cluster/create.html' => 3
            ];
            
            $score = 0;
            $foundFeatures = [];
            
            foreach ($enhancedEndpoints as $endpoint => $points) {
                if ($this->testEndpoint($host['domain'], $endpoint)) {
                    $score += $points;
                    $foundFeatures[] = $this->getFeatureName($endpoint);
                }
            }
            
            $result['response_time'] = round((microtime(true) - $startTime) * 1000);
            
            if ($score > 0) {
                $result['status'] = 'online';
                $result['enhancement_score'] = $score;
                $result['features'] = $foundFeatures;
                
                // Categorize enhancement level
                if ($score >= 8) {
                    $result['enhanced'] = true;
                    $result['upgrade_potential'] = 'fully_enhanced';
                } elseif ($score >= 4) {
                    $result['enhanced'] = true;
                    $result['upgrade_potential'] = 'partially_enhanced';
                } else {
                    $result['enhanced'] = false;
                    $result['upgrade_potential'] = 'basic_features';
                }
            } else {
                // Test if host is online at all
                if ($this->testBasicEndpoint($host['domain'])) {
                    $result['status'] = 'online';
                    $result['upgrade_potential'] = 'standard_host';
                }
            }
            
        } catch (Exception $e) {
            // Host is offline or unreachable
        }
        
        return $result;
    }
    
    /**
     * Test specific endpoint
     */
    private function testEndpoint($domain, $endpoint) {
        $url = "https://{$domain}{$endpoint}";
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 3,
                'header' => 'User-Agent: Enhancement-Scanner/1.0'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        return $response !== false;
    }
    
    /**
     * Test basic endpoint (for standard hosts)
     */
    private function testBasicEndpoint($domain) {
        $basicEndpoints = ['/', '/index.html', '/api', '/health'];
        
        foreach ($basicEndpoints as $endpoint) {
            if ($this->testEndpoint($domain, $endpoint)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get feature name from endpoint
     */
    private function getFeatureName($endpoint) {
        $featureMap = [
            '/api/host-info.php' => 'Enhanced API',
            '/api/instance-count.php' => 'Real-time Monitoring', 
            '/api/auto-discovery.php?action=status' => 'Auto Discovery',
            '/monitoring-dashboard.html' => 'Monitoring Dashboard',
            '/leaderboard.html' => 'Leaderboard System',
            '/cluster/create.html' => 'Cluster Management'
        ];
        
        return $featureMap[$endpoint] ?? 'Unknown Feature';
    }
    
    /**
     * Categorize hosts by enhancement status
     */
    private function categorizeHosts($hosts) {
        $categories = [
            'fully_enhanced' => [],
            'partially_enhanced' => [],
            'standard_hosts' => [],
            'offline_hosts' => []
        ];
        
        $stats = [
            'total_hosts' => count($hosts),
            'online_hosts' => 0,
            'enhanced_hosts' => 0,
            'upgrade_candidates' => 0,
            'total_instances' => 0,
            'available_instances' => 0
        ];
        
        foreach ($hosts as $host) {
            // Categorize
            if ($host['status'] === 'offline') {
                $categories['offline_hosts'][] = $host;
            } elseif ($host['upgrade_potential'] === 'fully_enhanced') {
                $categories['fully_enhanced'][] = $host;
                $stats['enhanced_hosts']++;
            } elseif ($host['upgrade_potential'] === 'partially_enhanced') {
                $categories['partially_enhanced'][] = $host;
                $stats['enhanced_hosts']++;
            } else {
                $categories['standard_hosts'][] = $host;
                $stats['upgrade_candidates']++;
            }
            
            // Update stats
            if ($host['status'] === 'online') {
                $stats['online_hosts']++;
                $stats['total_instances'] += $host['instances']['total'];
                $stats['available_instances'] += $host['instances']['available'];
            }
        }
        
        return [
            'categories' => $categories,
            'stats' => $stats,
            'scan_timestamp' => time(),
            'total_scanned' => count($hosts)
        ];
    }
    
    /**
     * Extract domain from host data
     */
    private function extractDomain($host) {
        // Try different fields that might contain domain
        $possibleFields = ['domain', 'hostname', 'url', 'endpoint'];
        
        foreach ($possibleFields as $field) {
            if (isset($host[$field]) && !empty($host[$field])) {
                $domain = $host[$field];
                
                // Clean up domain (remove protocol, port, etc.)
                $domain = preg_replace('/^https?:\/\//', '', $domain);
                $domain = preg_replace('/:\d+$/', '', $domain);
                $domain = preg_replace('/\/.*$/', '', $domain);
                
                if (!empty($domain) && $domain !== 'localhost') {
                    return $domain;
                }
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Remove duplicate hosts
     */
    private function deduplicateHosts($hosts) {
        $unique = [];
        $seen = [];
        
        foreach ($hosts as $host) {
            $key = $host['domain'] . '|' . $host['address'];
            
            if (!isset($seen[$key])) {
                $unique[] = $host;
                $seen[$key] = true;
            }
        }
        
        return $unique;
    }
    
    /**
     * Cache management
     */
    private function loadFromCache() {
        if (!file_exists($this->cacheFile)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($this->cacheFile), true);
        
        if (!$data || (time() - $data['cached_at']) > $this->cacheTimeout) {
            return null;
        }
        
        return $data['data'];
    }
    
    private function saveToCache($data) {
        $cacheData = [
            'data' => $data,
            'cached_at' => time()
        ];
        
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($this->cacheFile, json_encode($cacheData));
    }
    
    private function getCacheStatus() {
        if (file_exists($this->cacheFile)) {
            $data = json_decode(file_get_contents($this->cacheFile), true);
            $age = time() - ($data['cached_at'] ?? 0);
            
            return $this->success([
                'cached' => true,
                'age_seconds' => $age,
                'expires_in' => max(0, $this->cacheTimeout - $age),
                'file_size' => filesize($this->cacheFile)
            ]);
        }
        
        return $this->success(['cached' => false]);
    }
    
    /**
     * Get upgrade statistics
     */
    private function getUpgradeStats() {
        $cached = $this->loadFromCache();
        
        if (!$cached) {
            return $this->error('No scan data available. Run scan first.');
        }
        
        $stats = $cached['stats'];
        $categories = $cached['categories'];
        
        return $this->success([
            'upgrade_opportunity' => [
                'total_standard_hosts' => count($categories['standard_hosts']),
                'potential_revenue' => count($categories['standard_hosts']) * 10, // $10 commission per upgrade
                'market_penetration' => round(($stats['enhanced_hosts'] / $stats['online_hosts']) * 100, 1) . '%'
            ],
            'network_health' => [
                'online_percentage' => round(($stats['online_hosts'] / $stats['total_hosts']) * 100, 1) . '%',
                'enhancement_percentage' => round(($stats['enhanced_hosts'] / $stats['online_hosts']) * 100, 1) . '%',
                'instance_utilization' => round((($stats['total_instances'] - $stats['available_instances']) / $stats['total_instances']) * 100, 1) . '%'
            ],
            'recommendations' => $this->generateUpgradeRecommendations($categories)
        ]);
    }
    
    private function generateUpgradeRecommendations($categories) {
        $recommendations = [];
        
        $standardCount = count($categories['standard_hosts']);
        $enhancedCount = count($categories['fully_enhanced']);
        
        if ($standardCount > $enhancedCount) {
            $recommendations[] = "High upgrade potential: {$standardCount} standard hosts could be enhanced";
        }
        
        if ($enhancedCount > 10) {
            $recommendations[] = "Strong enhanced network: Consider creating host clusters";
        }
        
        if (count($categories['offline_hosts']) > 20) {
            $recommendations[] = "Network reliability concern: Many hosts offline";
        }
        
        return $recommendations;
    }
    
    private function success($data) {
        return [
            'success' => true,
            'data' => $data,
            'timestamp' => time()
        ];
    }
    
    private function error($message) {
        return [
            'success' => false,
            'error' => $message,
            'timestamp' => time()
        ];
    }
}

// Handle request
$registry = new EvernodeRegistryIntegration();
$result = $registry->handleRequest();

echo json_encode($result, JSON_PRETTY_PRINT);
?>

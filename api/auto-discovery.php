<?php
/**
 * Enhanced Auto-Discovery with Unified Registry Integration
 * File: /api/auto-discovery.php
 * 
 * Enhanced auto-discovery system that seamlessly pulls from:
 * - Original enhanced host discovery methods
 * - Official Evernode Grafana Dashboard (dashboards.evernode.network)
 * - XRPLWin Evernode API (xahau.xrplwin.com/evernode)
 * 
 * Provides complete coverage of all Evernode hosts and enhancement status
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

class AutoDiscoverySystem {
    private $registryFile = '/var/www/html/data/discovered-hosts.json';
    private $myHostFile = '/var/www/html/data/my-host-info.json';
    private $cacheFile = '/var/www/html/data/unified-registry-cache.json';
    private $cacheTimeout = 300; // 5 minutes
    
    // Original GitHub enhanced registry
    private $centralRegistry = 'https://api.github.com/repos/h20crypto/evernode-enhanced-setup/contents/data/enhanced-hosts.json';
    
    // Primary data sources
    private $dataSources = [
        'xrplwin' => [
            'name' => 'XRPLWin Evernode API',
            'url' => 'https://xahau.xrplwin.com/evernode',
            'priority' => 1,
            'provides' => 'complete_host_data'
        ],
        'grafana' => [
            'name' => 'Official Evernode Grafana',
            'url' => 'https://dashboards.evernode.network/api/datasources/proxy/1/api/v1/query',
            'priority' => 2,
            'provides' => 'monitoring_metrics'
        ],
        'github' => [
            'name' => 'Enhanced Hosts Registry',
            'url' => 'https://api.github.com/repos/h20crypto/evernode-enhanced-setup/contents/data/enhanced-hosts.json',
            'priority' => 3,
            'provides' => 'enhanced_hosts'
        ]
    ];
    
    public function __construct() {
        $this->ensureDataDirectory();
        $this->initializeMyHost();
    }
    
    public function handleRequest() {
        $action = $_GET['action'] ?? 'discover';
        
        switch ($action) {
            case 'discover':
                return $this->performDiscovery();
            case 'discover-all':
                return $this->discoverAllHosts();
            case 'register':
                return $this->registerMyHost();
            case 'ping':
                return $this->pingOtherHosts();
            case 'list':
                return $this->getDiscoveredHosts();
            case 'status':
                return $this->getDiscoveryStatus();
            case 'enhanced-only':
                return $this->getEnhancedHostsOnly();
            case 'standard-only':
                return $this->getStandardHostsOnly();
            case 'upgrade-candidates':
                return $this->getUpgradeCandidates();
            case 'network-stats':
                return $this->getNetworkStatistics();
            case 'data-sources':
                return $this->getDataSourceStatus();
            case 'refresh':
                return $this->forceRefresh();
            default:
                return $this->error('Invalid action');
        }
    }
    
    /**
     * Original enhanced host discovery (backwards compatible)
     */
    private function performDiscovery() {
        $discovered = [];
        
        // Method 1: Check central GitHub registry
        $githubHosts = $this->discoverFromGitHub();
        $discovered = array_merge($discovered, $githubHosts);
        
        // Method 2: DNS-based discovery (common domains)
        $dnsHosts = $this->discoverFromDNS();
        $discovered = array_merge($discovered, $dnsHosts);
        
        // Method 3: Known enhanced host patterns
        $patternHosts = $this->discoverFromPatterns();
        $discovered = array_merge($discovered, $patternHosts);
        
        // Method 4: Peer propagation (hosts tell us about other hosts)
        $peerHosts = $this->discoverFromPeers();
        $discovered = array_merge($discovered, $peerHosts);
        
        // Remove duplicates and test availability
        $uniqueHosts = $this->deduplicateHosts($discovered);
        $activeHosts = $this->testHostsAvailability($uniqueHosts);
        
        // Save discovered hosts
        $this->saveDiscoveredHosts($activeHosts);
        
        // Auto-register ourselves with discovered hosts
        $this->propagateToDiscoveredHosts($activeHosts);
        
        return $this->success([
            'discovered_count' => count($activeHosts),
            'methods_used' => ['github', 'dns', 'patterns', 'peers'],
            'hosts' => $activeHosts,
            'timestamp' => time()
        ]);
    }
    
    /**
     * Discover all Evernode hosts from unified sources
     */
    private function discoverAllHosts() {
        // Check cache first
        $cached = $this->loadFromCache();
        if ($cached && !$this->isCacheExpired()) {
            return $this->success($cached);
        }
        
        $allHosts = [];
        $sourceResults = [];
        
        // Fetch from all data sources
        foreach ($this->dataSources as $key => $source) {
            try {
                $hosts = $this->fetchFromSource($key, $source);
                if (!empty($hosts)) {
                    $allHosts = array_merge($allHosts, $hosts);
                    $sourceResults[$key] = [
                        'status' => 'success',
                        'host_count' => count($hosts),
                        'source' => $source['name']
                    ];
                } else {
                    $sourceResults[$key] = [
                        'status' => 'no_data',
                        'host_count' => 0,
                        'source' => $source['name']
                    ];
                }
            } catch (Exception $e) {
                $sourceResults[$key] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'source' => $source['name']
                ];
            }
        }
        
        // Remove duplicates and merge data
        $uniqueHosts = $this->mergeAndDeduplicateHosts($allHosts);
        
        // Test for enhanced features
        $enrichedHosts = $this->enrichWithEnhancementData($uniqueHosts);
        
        // Categorize results
        $results = $this->categorizeHosts($enrichedHosts);
        $results['data_sources'] = $sourceResults;
        $results['discovery_timestamp'] = time();
        
        // Cache results
        $this->saveToCache($results);
        
        return $this->success($results);
    }
    
    /**
     * Enhanced GitHub discovery (original method)
     */
    private function discoverFromGitHub() {
        $hosts = [];
        
        try {
            // Get the enhanced hosts registry from GitHub
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: Enhanced-Evernode-Discovery/1.0',
                    'timeout' => 10
                ]
            ]);
            
            $response = @file_get_contents($this->centralRegistry, false, $context);
            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['content'])) {
                    $content = base64_decode($data['content']);
                    $registry = json_decode($content, true);
                    
                    if (isset($registry['hosts'])) {
                        foreach ($registry['hosts'] as $host) {
                            $hosts[] = [
                                'domain' => $host['domain'] ?? '',
                                'address' => $host['address'] ?? '',
                                'source' => 'github_registry',
                                'enhanced' => true,
                                'features' => $host['features'] ?? ['enhanced']
                            ];
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Silent fail - try other methods
        }
        
        return $hosts;
    }
    
    /**
     * DNS pattern discovery (original method)
     */
    private function discoverFromDNS() {
        $hosts = [];
        
        // Common patterns for enhanced hosts
        $patterns = [
            'h20cryptonode*.dev',
            'h20cryptonode*.com',
            'evernode*.enhanced',
            '*node*.evernode',
            'enhanced*.evernode',
            '*cryptonode*'
        ];
        
        // Known domain suffixes
        $suffixes = ['.dev', '.com', '.net', '.io', '.xyz', '.evernode'];
        
        // Try common enhanced host naming patterns
        $commonNames = [
            'h20cryptonode3', 'h20cryptonode5', 'h20cryptonode1',
            'evernode1', 'evernode2', 'enhanced-host',
            'node1', 'node2', 'cryptonode'
        ];
        
        foreach ($commonNames as $name) {
            foreach ($suffixes as $suffix) {
                $domain = $name . $suffix;
                
                // Quick DNS check
                if ($this->isDomainResolvable($domain)) {
                    $hosts[] = [
                        'domain' => $domain,
                        'address' => 'unknown',
                        'source' => 'dns_pattern',
                        'enhanced' => null, // Will be tested
                        'features' => []
                    ];
                }
            }
        }
        
        return $hosts;
    }
    
    /**
     * Pattern discovery (original method)
     */
    private function discoverFromPatterns() {
        $hosts = [];
        
        // Get our own domain to find similar hosts
        $myDomain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        if ($myDomain !== 'localhost') {
            // Try variations of our domain
            $baseDomain = preg_replace('/[0-9]+/', '', $myDomain);
            
            for ($i = 1; $i <= 10; $i++) {
                $testDomain = str_replace($baseDomain, $baseDomain . $i, $myDomain);
                
                if ($testDomain !== $myDomain && $this->isDomainResolvable($testDomain)) {
                    $hosts[] = [
                        'domain' => $testDomain,
                        'address' => 'unknown',
                        'source' => 'pattern_matching',
                        'enhanced' => null,
                        'features' => []
                    ];
                }
            }
        }
        
        return $hosts;
    }
    
    /**
     * Peer discovery (original method)
     */
    private function discoverFromPeers() {
        $hosts = [];
        
        // Load existing discovered hosts
        $existing = $this->loadDiscoveredHosts();
        
        foreach ($existing as $peer) {
            if (isset($peer['status']) && $peer['status'] === 'online') {
                try {
                    // Ask peer for their discovered hosts
                    $peerData = $this->fetchFromHost($peer['domain'], '/api/auto-discovery.php?action=list');
                    
                    if ($peerData && isset($peerData['data']['hosts'])) {
                        foreach ($peerData['data']['hosts'] as $peerHost) {
                            $hosts[] = [
                                'domain' => $peerHost['domain'],
                                'address' => $peerHost['address'] ?? 'unknown',
                                'source' => 'peer_recommendation',
                                'enhanced' => $peerHost['enhanced'] ?? null,
                                'features' => $peerHost['features'] ?? []
                            ];
                        }
                    }
                } catch (Exception $e) {
                    // Continue with other peers
                }
            }
        }
        
        return $hosts;
    }
    
    /**
     * Fetch from XRPLWin Evernode API
     */
    private function fetchFromXRPLWin() {
        $hosts = [];
        
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: Enhanced-Evernode-Registry/2.0',
                        'Accept: application/json'
                    ],
                    'timeout' => 15
                ]
            ]);
            
            $response = @file_get_contents($this->dataSources['xrplwin']['url'], false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                
                // Parse XRPLWin response format
                if (isset($data['hosts']) && is_array($data['hosts'])) {
                    foreach ($data['hosts'] as $host) {
                        $hosts[] = $this->parseXRPLWinHost($host);
                    }
                } elseif (isset($data['data']) && is_array($data['data'])) {
                    // Alternative response format
                    foreach ($data['data'] as $host) {
                        $hosts[] = $this->parseXRPLWinHost($host);
                    }
                } elseif (is_array($data)) {
                    // Direct array format
                    foreach ($data as $host) {
                        $hosts[] = $this->parseXRPLWinHost($host);
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("XRPLWin fetch failed: " . $e->getMessage());
        }
        
        return $hosts;
    }
    
    /**
     * Parse XRPLWin host data into standard format
     */
    private function parseXRPLWinHost($host) {
        return [
            'address' => $host['address'] ?? $host['account'] ?? 'unknown',
            'domain' => $this->extractDomain($host),
            'source' => 'xrplwin',
            'status' => $this->determineStatus($host),
            'enhanced' => null, // Will be tested
            'instances' => [
                'total' => $host['max_instances'] ?? $host['capacity'] ?? 3,
                'used' => $host['used_instances'] ?? $host['utilized'] ?? 0,
                'available' => ($host['max_instances'] ?? 3) - ($host['used_instances'] ?? 0)
            ],
            'rates' => [
                'evr_per_moment' => $host['lease_rate_evr'] ?? $host['rate'] ?? 0,
                'compute_price' => $host['compute_price'] ?? 0
            ],
            'location' => [
                'country' => $host['country'] ?? $host['country_code'] ?? 'Unknown',
                'region' => $host['region'] ?? 'Unknown'
            ],
            'reputation' => [
                'score' => $host['reputation'] ?? $host['score'] ?? 0,
                'uptime' => $host['uptime'] ?? 0
            ],
            'registration' => [
                'created' => $host['created'] ?? $host['reg_date'] ?? null,
                'last_heartbeat' => $host['last_heartbeat'] ?? $host['last_seen'] ?? null
            ],
            'raw_data' => $host // Keep original for debugging
        ];
    }
    
    /**
     * Fetch from Grafana Dashboard
     */
    private function fetchFromGrafana() {
        $hosts = [];
        
        try {
            // Try multiple Prometheus queries
            $queries = [
                'evernode_host_count',
                'evernode_hosts_active', 
                'up{job="evernode"}',
                'evernode_registry_total'
            ];
            
            foreach ($queries as $query) {
                $queryHosts = $this->executeGrafanaQuery($query);
                if (!empty($queryHosts)) {
                    $hosts = array_merge($hosts, $queryHosts);
                }
            }
            
        } catch (Exception $e) {
            error_log("Grafana fetch failed: " . $e->getMessage());
        }
        
        return $hosts;
    }
    
    /**
     * Execute Grafana/Prometheus query
     */
    private function executeGrafanaQuery($query) {
        $hosts = [];
        
        try {
            $url = $this->dataSources['grafana']['url'] . '?' . http_build_query(['query' => $query]);
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: Enhanced-Evernode-Registry/2.0',
                        'Accept: application/json',
                        'Referer: https://dashboards.evernode.network/'
                    ],
                    'timeout' => 15
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                
                if (isset($data['data']['result'])) {
                    foreach ($data['data']['result'] as $result) {
                        $host = $this->parseGrafanaResult($result);
                        if ($host) {
                            $hosts[] = $host;
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("Grafana query failed: " . $e->getMessage());
        }
        
        return $hosts;
    }
    
    /**
     * Parse Grafana result into standard format
     */
    private function parseGrafanaResult($result) {
        $metric = $result['metric'] ?? [];
        $value = $result['value'] ?? [];
        
        if (empty($metric['instance'])) {
            return null;
        }
        
        $instance = $metric['instance'];
        $parts = explode(':', $instance);
        
        return [
            'domain' => $parts[0],
            'port' => $parts[1] ?? '80',
            'source' => 'grafana',
            'status' => isset($value[1]) && $value[1] > 0 ? 'online' : 'offline',
            'enhanced' => null,
            'address' => $metric['xahau_address'] ?? 'unknown',
            'location' => [
                'region' => $metric['region'] ?? 'Unknown'
            ],
            'instances' => [
                'total' => $metric['max_instances'] ?? 3,
                'used' => $metric['used_instances'] ?? 0,
                'available' => ($metric['max_instances'] ?? 3) - ($metric['used_instances'] ?? 0)
            ],
            'raw_data' => $result
        ];
    }
    
    /**
     * Fetch from specific source
     */
    private function fetchFromSource($key, $source) {
        switch ($key) {
            case 'xrplwin':
                return $this->fetchFromXRPLWin();
            case 'grafana':
                return $this->fetchFromGrafana();
            case 'github':
                return $this->discoverFromGitHub();
            default:
                return [];
        }
    }
    
    /**
     * Merge and deduplicate hosts from multiple sources
     */
    private function mergeAndDeduplicateHosts($allHosts) {
        $merged = [];
        $addressIndex = [];
        $domainIndex = [];
        
        foreach ($allHosts as $host) {
            $address = $host['address'] ?? '';
            $domain = $host['domain'] ?? '';
            
            // Primary key: Xahau address
            if (!empty($address) && $address !== 'unknown') {
                if (isset($addressIndex[$address])) {
                    // Merge data from multiple sources
                    $merged[$addressIndex[$address]] = $this->mergeHostData(
                        $merged[$addressIndex[$address]], 
                        $host
                    );
                } else {
                    $index = count($merged);
                    $merged[] = $host;
                    $addressIndex[$address] = $index;
                }
            }
            // Secondary key: Domain
            elseif (!empty($domain) && $domain !== 'unknown') {
                if (isset($domainIndex[$domain])) {
                    $merged[$domainIndex[$domain]] = $this->mergeHostData(
                        $merged[$domainIndex[$domain]], 
                        $host
                    );
                } else {
                    $index = count($merged);
                    $merged[] = $host;
                    $domainIndex[$domain] = $index;
                }
            }
            // Add unique hosts without clear identifiers
            else {
                $merged[] = $host;
            }
        }
        
        return array_values($merged); // Re-index array
    }
    
    /**
     * Merge host data from multiple sources
     */
    private function mergeHostData($existing, $new) {
        // Merge sources
        $existing['sources'] = array_unique(array_merge(
            $existing['sources'] ?? [$existing['source']],
            [$new['source']]
        ));
        
        // Keep most detailed data
        foreach (['address', 'domain', 'instances', 'rates', 'location', 'reputation'] as $field) {
            if (isset($new[$field]) && (!isset($existing[$field]) || $this->isMoreDetailed($new[$field], $existing[$field]))) {
                $existing[$field] = $new[$field];
            }
        }
        
        // Merge features
        if (isset($new['features'])) {
            $existing['features'] = array_unique(array_merge(
                $existing['features'] ?? [],
                $new['features']
            ));
        }
        
        // Use enhanced status if any source confirms it
        if (isset($new['enhanced']) && $new['enhanced'] === true) {
            $existing['enhanced'] = true;
        }
        
        return $existing;
    }
    
    /**
     * Check if new data is more detailed than existing
     */
    private function isMoreDetailed($new, $existing) {
        if (is_array($new) && is_array($existing)) {
            return count(array_filter($new)) > count(array_filter($existing));
        }
        return !empty($new) && (empty($existing) || $existing === 'unknown');
    }
    
    /**
     * Enrich hosts with enhancement testing
     */
    private function enrichWithEnhancementData($hosts) {
        $enriched = [];
        
        foreach ($hosts as $host) {
            $testResult = $this->testHostForEnhancements($host);
            $enriched[] = $testResult;
        }
        
        return $enriched;
    }
    
    /**
     * Test a single host for enhanced features
     */
    private function testHostForEnhancements($host) {
        // Skip if already confirmed enhanced
        if (isset($host['enhanced']) && $host['enhanced'] === true) {
            return array_merge($host, [
                'enhancement_test' => 'skipped_confirmed_enhanced',
                'last_tested' => time()
            ]);
        }
        
        $domain = $host['domain'] ?? '';
        if (empty($domain) || $domain === 'unknown' || $domain === 'localhost') {
            return array_merge($host, [
                'enhanced' => false,
                'enhancement_test' => 'no_domain_to_test'
            ]);
        }
        
        $startTime = microtime(true);
        $enhancementData = [
            'enhanced' => false,
            'enhancement_score' => 0,
            'features' => [],
            'response_time' => 0,
            'last_tested' => time(),
            'enhancement_test' => 'completed'
        ];
        
        try {
            // Test enhanced endpoints
            $endpoints = [
                '/api/auto-discovery.php?action=status' => 3,
                '/api/auto-discovery.php?action=network-stats' => 2,
                '/api/host-info.php' => 2,
                '/api/instance-count.php' => 1
            ];
            
            foreach ($endpoints as $endpoint => $score) {
                $data = $this->fetchFromHost($domain, $endpoint);
                
                if ($data) {
                    $enhancementData['response_time'] = round((microtime(true) - $startTime) * 1000);
                    
                    // Award points for working enhanced endpoints
                    $enhancementData['enhancement_score'] += $score;
                    
                    // Check for specific enhanced features
                    if (isset($data['enhanced']) && $data['enhanced']) {
                        $enhancementData['enhanced'] = true;
                        $enhancementData['enhancement_score'] += 5;
                    }
                    
                    if (isset($data['features']) && is_array($data['features'])) {
                        $enhancementData['features'] = array_unique(array_merge(
                            $enhancementData['features'],
                            $data['features']
                        ));
                    }
                    
                    // Auto-discovery capability is a strong indicator
                    if (strpos($endpoint, 'auto-discovery') !== false && isset($data['success'])) {
                        $enhancementData['enhanced'] = true;
                        $enhancementData['features'][] = 'auto-discovery';
                        $enhancementData['enhancement_score'] += 3;
                    }
                }
            }
            
            // Determine enhancement status based on score
            if ($enhancementData['enhancement_score'] >= 3) {
                $enhancementData['enhanced'] = true;
            }
            
        } catch (Exception $e) {
            $enhancementData['enhancement_test'] = 'failed: ' . $e->getMessage();
        }
        
        return array_merge($host, $enhancementData);
    }
    
    /**
     * Test hosts availability (original method)
     */
    private function testHostsAvailability($hosts) {
        $activeHosts = [];
        
        foreach ($hosts as $host) {
            $testResult = $this->testSingleHost($host);
            if ($testResult) {
                $activeHosts[] = $testResult;
            }
        }
        
        return $activeHosts;
    }
    
    /**
     * Test single host (original method)
     */
    private function testSingleHost($host) {
        if (empty($host['domain']) || $host['domain'] === ($_SERVER['HTTP_HOST'] ?? 'localhost')) {
            return null; // Skip our own host
        }
        
        $result = [
            'domain' => $host['domain'],
            'address' => $host['address'] ?? 'unknown',
            'source' => $host['source'] ?? 'unknown',
            'status' => 'offline',
            'enhanced' => false,
            'features' => [],
            'instances' => ['total' => 0, 'available' => 0],
            'last_seen' => time(),
            'response_time' => 0
        ];
        
        $startTime = microtime(true);
        
        try {
            // Test enhanced endpoints
            $endpoints = [
                '/api/host-info.php',
                '/api/instance-count.php',
                '/api/auto-discovery.php?action=status'
            ];
            
            foreach ($endpoints as $endpoint) {
                $data = $this->fetchFromHost($host['domain'], $endpoint);
                
                if ($data) {
                    $result['status'] = 'online';
                    $result['response_time'] = round((microtime(true) - $startTime) * 1000);
                    
                    // Extract host information
                    if (isset($data['enhanced'])) {
                        $result['enhanced'] = $data['enhanced'];
                    }
                    
                    if (isset($data['xahau_address'])) {
                        $result['address'] = $data['xahau_address'];
                    }
                    
                    if (isset($data['features'])) {
                        $result['features'] = $data['features'];
                    }
                    
                    if (isset($data['total']) && isset($data['available'])) {
                        $result['instances'] = [
                            'total' => $data['total'],
                            'available' => $data['available']
                        ];
                    }
                    
                    // If we found enhanced features, mark as enhanced
                    if (!empty($result['features']) || 
                        strpos(json_encode($data), 'enhanced') !== false) {
                        $result['enhanced'] = true;
                    }
                    
                    break; // Found working endpoint
                }
            }
        } catch (Exception $e) {
            // Host is offline or not enhanced
        }
        
        return $result['status'] === 'online' ? $result : null;
    }
    
    /**
     * Fetch data from host
     */
    private function fetchFromHost($domain, $path) {
        $url = "https://{$domain}{$path}";
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'header' => 'User-Agent: Enhanced-Evernode-Registry/2.0'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response) {
            return json_decode($response, true);
        }
        
        return null;
    }
    
    /**
     * Categorize hosts by enhancement status
     */
    private function categorizeHosts($hosts) {
        $categories = [
            'total_hosts' => count($hosts),
            'enhanced_hosts' => [],
            'standard_hosts' => [],
            'offline_hosts' => [],
            'upgrade_candidates' => [],
            'statistics' => [
                'enhanced_count' => 0,
                'standard_count' => 0,
                'offline_count' => 0,
                'total_instances' => 0,
                'available_instances' => 0,
                'enhancement_rate' => 0
            ]
        ];
        
        foreach ($hosts as $host) {
            if (isset($host['status']) && $host['status'] === 'offline') {
                $categories['offline_hosts'][] = $host;
                $categories['statistics']['offline_count']++;
            } elseif (isset($host['enhanced']) && $host['enhanced'] === true) {
                $categories['enhanced_hosts'][] = $host;
                $categories['statistics']['enhanced_count']++;
            } else {
                $categories['standard_hosts'][] = $host;
                $categories['statistics']['standard_count']++;
                
                // Check if good upgrade candidate
                if ($this->isGoodUpgradeCandidate($host)) {
                    $categories['upgrade_candidates'][] = $host;
                }
            }
            
            // Count instances
            if (isset($host['instances'])) {
                $categories['statistics']['total_instances'] += $host['instances']['total'] ?? 0;
                $categories['statistics']['available_instances'] += $host['instances']['available'] ?? 0;
            }
        }
        
        // Calculate enhancement rate
        if ($categories['total_hosts'] > 0) {
            $categories['statistics']['enhancement_rate'] = round(
                ($categories['statistics']['enhanced_count'] / $categories['total_hosts']) * 100, 2
            );
        }
        
        return $categories;
    }
    
    /**
     * Check if host is a good upgrade candidate
     */
    private function isGoodUpgradeCandidate($host) {
        // Good uptime/reputation and working domain
        return !empty($host['domain']) && 
               $host['domain'] !== 'unknown' &&
               isset($host['status']) && $host['status'] === 'online' &&
               (!isset($host['enhanced']) || $host['enhanced'] !== true);
    }
    
    /**
     * Helper functions for data extraction
     */
    private function extractDomain($host) {
        // Try different possible fields for domain/URL
        if (isset($host['domain'])) return $host['domain'];
        if (isset($host['url'])) return parse_url($host['url'], PHP_URL_HOST);
        if (isset($host['endpoint'])) return parse_url($host['endpoint'], PHP_URL_HOST);
        if (isset($host['host'])) return $host['host'];
        return 'unknown';
    }
    
    private function determineStatus($host) {
        if (isset($host['active']) && !$host['active']) return 'offline';
        if (isset($host['status'])) return $host['status'];
        if (isset($host['online'])) return $host['online'] ? 'online' : 'offline';
        if (isset($host['last_heartbeat'])) {
            $lastSeen = strtotime($host['last_heartbeat']);
            $hourAgo = time() - 3600;
            return $lastSeen > $hourAgo ? 'online' : 'offline';
        }
        return 'unknown';
    }
    
    /**
     * Get enhanced hosts only
     */
    private function getEnhancedHostsOnly() {
        $allData = $this->discoverAllHosts();
        
        if ($allData['success']) {
            $enhancedData = [
                'enhanced_hosts' => $allData['data']['enhanced_hosts'],
                'enhanced_count' => $allData['data']['statistics']['enhanced_count'],
                'enhancement_rate' => $allData['data']['statistics']['enhancement_rate']
            ];
            
            return $this->success($enhancedData);
        }
        
        return $allData;
    }
    
    /**
     * Get standard hosts only
     */
    private function getStandardHostsOnly() {
        $allData = $this->discoverAllHosts();
        
        if ($allData['success']) {
            $standardData = [
                'standard_hosts' => $allData['data']['standard_hosts'],
                'standard_count' => $allData['data']['statistics']['standard_count']
            ];
            
            return $this->success($standardData);
        }
        
        return $allData;
    }
    
    /**
     * Get upgrade candidates
     */
    private function getUpgradeCandidates() {
        $allData = $this->discoverAllHosts();
        
        if ($allData['success']) {
            $upgradeData = [
                'upgrade_candidates' => $allData['data']['upgrade_candidates'],
                'upgrade_potential' => count($allData['data']['upgrade_candidates']),
                'current_enhancement_rate' => $allData['data']['statistics']['enhancement_rate']
            ];
            
            return $this->success($upgradeData);
        }
        
        return $allData;
    }
    
    /**
     * Get network statistics
     */
    private function getNetworkStatistics() {
        $allData = $this->discoverAllHosts();
        
        if ($allData['success']) {
            return $this->success($allData['data']['statistics']);
        }
        
        return $allData;
    }
    
    /**
     * Get data source status
     */
    private function getDataSourceStatus() {
        $status = [];
        
        foreach ($this->dataSources as $key => $source) {
            $status[$key] = [
                'name' => $source['name'],
                'url' => $source['url'],
                'accessible' => $this->testSourceAccess($source['url']),
                'priority' => $source['priority'],
                'provides' => $source['provides']
            ];
        }
        
        return $this->success($status);
    }
    
    /**
     * Test source accessibility
     */
    private function testSourceAccess($url) {
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'timeout' => 5
            ]
        ]);
        
        return @file_get_contents($url, false, $context) !== false;
    }
    
    /**
     * Force refresh cache
     */
    private function forceRefresh() {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        
        return $this->discoverAllHosts();
    }
    
    /**
     * Original deduplication method
     */
    private function deduplicateHosts($hosts) {
        $unique = [];
        $seen = [];
        
        foreach ($hosts as $host) {
            $key = $host['domain'];
            if (!isset($seen[$key])) {
                $unique[] = $host;
                $seen[$key] = true;
            }
        }
        
        return $unique;
    }
    
    /**
     * Save discovered hosts (original method)
     */
    private function saveDiscoveredHosts($hosts) {
        $data = [
            'hosts' => $hosts,
            'last_discovery' => time(),
            'discovery_count' => count($hosts)
        ];
        
        file_put_contents($this->registryFile, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    /**
     * Load discovered hosts (original method)
     */
    private function loadDiscoveredHosts() {
        if (file_exists($this->registryFile)) {
            $data = json_decode(file_get_contents($this->registryFile), true);
            return $data['hosts'] ?? [];
        }
        
        return [];
    }
    
    /**
     * Initialize host (original method)
     */
    private function initializeMyHost() {
        if (!file_exists($this->myHostFile)) {
            $myHost = [
                'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
                'address' => $this->getMyXahauAddress(),
                'enhanced' => true,
                'features' => ['auto-discovery', 'enhanced-ui', 'real-time-monitoring'],
                'first_seen' => time(),
                'last_updated' => time()
            ];
            
            file_put_contents($this->myHostFile, json_encode($myHost, JSON_PRETTY_PRINT));
        }
    }
    
    /**
     * Get Xahau address (original method)
     */
    private function getMyXahauAddress() {
        $output = shell_exec('evernode status 2>/dev/null | grep "Host account address"');
        if (preg_match('/Host account address: (r[A-Za-z0-9]{25,34})/', $output, $matches)) {
            return $matches[1];
        }
        
        return 'unknown';
    }
    
    /**
     * Propagate to discovered hosts (original method)
     */
    private function propagateToDiscoveredHosts($hosts) {
        if (!file_exists($this->myHostFile)) {
            return;
        }
        
        $myHost = json_decode(file_get_contents($this->myHostFile), true);
        
        foreach ($hosts as $host) {
            if (isset($host['status']) && $host['status'] === 'online' && 
                isset($host['enhanced']) && $host['enhanced']) {
                try {
                    // Send our host info to the discovered host
                    $this->sendHostInfo($host['domain'], $myHost);
                } catch (Exception $e) {
                    // Continue with other hosts
                }
            }
        }
    }
    
    /**
     * Send host info (original method)
     */
    private function sendHostInfo($targetDomain, $hostInfo) {
        $postData = json_encode(['host' => $hostInfo]);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n" .
                           "User-Agent: Enhanced-Evernode-Discovery/1.0\r\n",
                'content' => $postData,
                'timeout' => 5
            ]
        ]);
        
        @file_get_contents("https://{$targetDomain}/api/auto-discovery.php?action=register", 
                          false, $context);
    }
    
    /**
     * Get discovery status (original method)
     */
    private function getDiscoveryStatus() {
        $hosts = $this->loadDiscoveredHosts();
        $online = array_filter($hosts, function($h) { return isset($h['status']) && $h['status'] === 'online'; });
        $enhanced = array_filter($hosts, function($h) { return isset($h['enhanced']) && $h['enhanced'] === true; });
        
        return $this->success([
            'total_discovered' => count($hosts),
            'online_hosts' => count($online),
            'enhanced_hosts' => count($enhanced),
            'last_discovery' => $this->getLastDiscoveryTime(),
            'auto_discovery_enabled' => true
        ]);
    }
    
    /**
     * Get discovered hosts (original method)
     */
    private function getDiscoveredHosts() {
        $hosts = $this->loadDiscoveredHosts();
        
        return $this->success([
            'hosts' => $hosts,
            'count' => count($hosts),
            'timestamp' => time()
        ]);
    }
    
    /**
     * Get last discovery time (original method)
     */
    private function getLastDiscoveryTime() {
        if (file_exists($this->registryFile)) {
            $data = json_decode(file_get_contents($this->registryFile), true);
            return $data['last_discovery'] ?? 0;
        }
        
        return 0;
    }
    
    /**
     * Check domain resolvable (original method)
     */
    private function isDomainResolvable($domain) {
        return @gethostbyname($domain) !== $domain;
    }
    
    /**
     * Ensure data directory (original method)
     */
    private function ensureDataDirectory() {
        $dataDir = dirname($this->registryFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
    }
    
    /**
     * Cache management
     */
    private function loadFromCache() {
        if (file_exists($this->cacheFile)) {
            $cache = json_decode(file_get_contents($this->cacheFile), true);
            return $cache['data'] ?? null;
        }
        return null;
    }
    
    private function saveToCache($data) {
        $cache = [
            'timestamp' => time(),
            'data' => $data
        ];
        file_put_contents($this->cacheFile, json_encode($cache, JSON_PRETTY_PRINT));
    }
    
    private function isCacheExpired() {
        if (file_exists($this->cacheFile)) {
            $cache = json_decode(file_get_contents($this->cacheFile), true);
            return (time() - $cache['timestamp']) > $this->cacheTimeout;
        }
        return true;
    }
    
    /**
     * Response helpers
     */
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

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Initialize and handle request
$discovery = new AutoDiscoverySystem();
$result = $discovery->handleRequest();

echo json_encode($result, JSON_PRETTY_PRINT);
?>

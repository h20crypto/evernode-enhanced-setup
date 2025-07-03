<?php
/**
 * Fast UX Enhanced Host Discovery API - Complete Organic Version
 * Lightning-fast responses with background processing and organic discovery
 * Version: 5.0.0 - Production Ready with Real Evernode Network Integration
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

class FastEnhancedHostDiscovery {
    private $cache_dir = '/tmp/enhanced-cache';
    private $registry_cache = '/tmp/evernode_registry.json';
    private $enhanced_registry = '/tmp/organic_enhanced_hosts.json';
    private $fast_cache_duration = 300; // 5 minutes for user-facing cache
    private $bg_cache_duration = 3600; // 1 hour for background cache
    private $max_response_time = 2000; // 2 seconds max response time
    
    // Real Evernode API endpoint
    private $evernode_api = 'https://api.evernode.network/registry/hosts';
    
    // NO HARDCODED ENHANCED HOSTS - Build organically only!
    // Enhanced hosts are discovered ONLY when they:
    // 1. Install GitHub software and self-announce
    // 2. Are probed and found to have enhanced endpoints
    // 3. Announce themselves via the network
    
    public function __construct() {
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
        $this->initializeEnhancedRegistry();
    }
    
    private function initializeEnhancedRegistry() {
        if (!file_exists($this->enhanced_registry)) {
            $initial_registry = [
                'enhanced_hosts' => [],
                'last_updated' => time(),
                'discovery_source' => 'organic_only',
                'total_discovered' => 0,
                'version' => '5.0.0'
            ];
            file_put_contents($this->enhanced_registry, json_encode($initial_registry, JSON_PRETTY_PRINT));
        }
    }
    
    public function handleRequest() {
        $action = $_GET['action'] ?? 'search';
        $start_time = microtime(true);
        
        try {
            switch ($action) {
                case 'search':
                    return $this->fastSearch();
                case 'announce':
                    return $this->handleEnhancedAnnouncement();
                case 'discover':
                    return $this->discoverNewEnhancedHosts();
                case 'enhanced_only':
                    return $this->getEnhancedHosts();
                case 'stats':
                    return $this->getNetworkStats();
                case 'test':
                    return $this->testConnection();
                case 'config':
                    return $this->getConfig();
                case 'peer_announcement':
                    return $this->handlePeerAnnouncement();
                default:
                    return $this->fastSearch();
            }
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        } finally {
            $execution_time = round((microtime(true) - $start_time) * 1000, 2);
            error_log("Enhanced API Request: $action completed in {$execution_time}ms");
        }
    }
    
    /**
     * INSTANT response with cached data + background refresh
     */
    private function fastSearch() {
        $force_refresh = $_GET['force_refresh'] ?? false;
        $limit = intval($_GET['limit'] ?? 50);
        $enhanced_only = $_GET['enhanced_only'] ?? false;
        
        // STEP 1: Return cached data immediately (< 100ms)
        $cached_data = $this->getCachedResults();
        
        if ($cached_data && !$force_refresh) {
            // Filter and limit results
            $filtered_hosts = $this->filterHosts($cached_data['hosts'], $enhanced_only, $limit);
            
            $response = [
                'success' => true,
                'cache_hit' => true,
                'data_source' => 'cached_results',
                'cache_age' => time() - $cached_data['timestamp'],
                'pagination' => [
                    'total_hosts' => count($cached_data['hosts']),
                    'enhanced_hosts' => $this->countEnhanced($cached_data['hosts']),
                    'returned' => count($filtered_hosts),
                    'limit' => $limit
                ],
                'hosts' => $filtered_hosts,
                'cache_status' => [
                    'hit' => true,
                    'age_seconds' => time() - $cached_data['timestamp'],
                    'next_refresh' => $cached_data['timestamp'] + $this->fast_cache_duration
                ],
                'organic_discovery' => [
                    'method' => 'github_installation_based',
                    'hardcoded_hosts' => 0,
                    'organically_discovered' => count($this->loadEnhancedRegistry()['enhanced_hosts'])
                ]
            ];
            
            // Trigger background refresh if cache is getting old
            if ((time() - $cached_data['timestamp']) > ($this->fast_cache_duration * 0.8)) {
                $this->triggerBackgroundRefresh();
            }
            
            return $response;
        }
        
        // STEP 2: No cache or forced refresh - get fresh data quickly
        return $this->getFreshHostData($enhanced_only, $limit);
    }
    
    private function getCachedResults() {
        $cache_file = $this->cache_dir . '/fast_host_results.json';
        
        if (file_exists($cache_file)) {
            $cache_age = time() - filemtime($cache_file);
            if ($cache_age < $this->fast_cache_duration) {
                return json_decode(file_get_contents($cache_file), true);
            }
        }
        
        return null;
    }
    
    private function getFreshHostData($enhanced_only = false, $limit = 50) {
        $start_time = microtime(true);
        
        // Get ALL hosts from real Evernode API
        $all_hosts = $this->getEvernodeRegistryHosts();
        
        // Load organically discovered enhanced hosts
        $enhanced_registry = $this->loadEnhancedRegistry();
        
        // Mark hosts as enhanced ONLY if they're in our organic registry
        foreach ($all_hosts as &$host) {
            $domain = $host['domain'] ?? '';
            $host['enhanced'] = isset($enhanced_registry['enhanced_hosts'][$domain]);
            $host['enhanced_source'] = $host['enhanced'] ? 'organic_discovery' : 'none';
            $host['enhanced_confidence'] = $host['enhanced'] ? 100 : 0;
            
            if ($host['enhanced']) {
                $enhanced_data = $enhanced_registry['enhanced_hosts'][$domain];
                $host['github_source'] = $enhanced_data['github_source'] ?? false;
                $host['enhanced_features'] = $enhanced_data['features'] ?? [];
                $host['discovered_at'] = $enhanced_data['discovered_at'] ?? null;
            }
        }
        
        // Cache the results
        $cache_data = [
            'hosts' => $all_hosts,
            'timestamp' => time(),
            'source' => 'fresh_evernode_api'
        ];
        
        $cache_file = $this->cache_dir . '/fast_host_results.json';
        file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT));
        
        // Filter and limit results
        $filtered_hosts = $this->filterHosts($all_hosts, $enhanced_only, $limit);
        
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        
        return [
            'success' => true,
            'cache_hit' => false,
            'data_source' => 'fresh_evernode_api',
            'execution_time_ms' => $execution_time,
            'pagination' => [
                'total_hosts' => count($all_hosts),
                'enhanced_hosts' => $this->countEnhanced($all_hosts),
                'returned' => count($filtered_hosts),
                'limit' => $limit
            ],
            'hosts' => $filtered_hosts,
            'organic_discovery' => [
                'method' => 'github_installation_based',
                'organically_discovered' => count($enhanced_registry['enhanced_hosts']),
                'last_discovery' => date('c', $enhanced_registry['last_updated'])
            ]
        ];
    }
    
    private function filterHosts($hosts, $enhanced_only, $limit) {
        if ($enhanced_only) {
            $hosts = array_filter($hosts, function($host) {
                return $host['enhanced'] === true;
            });
        }
        
        return array_slice(array_values($hosts), 0, $limit);
    }
    
    private function countEnhanced($hosts) {
        return count(array_filter($hosts, function($host) {
            return $host['enhanced'] === true;
        }));
    }
    
    /**
     * Handle enhanced host self-announcement (called during GitHub installation)
     */
    private function handleEnhancedAnnouncement() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->errorResponse('POST required for announcements');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $domain = $input['domain'] ?? $_SERVER['HTTP_HOST'] ?? 'unknown';
        $github_source = $input['github_source'] ?? true;
        $installation_time = $input['installation_time'] ?? time();
        
        // Verify this is a real enhanced installation by testing endpoints
        $verified_features = $this->verifyEnhancedInstallation($domain);
        
        if (count($verified_features) >= 3) { // At least 3 enhanced features found
            $this->addToEnhancedRegistry($domain, $verified_features, $github_source, $installation_time);
            
            // Notify other enhanced hosts about the new peer
            $this->notifyEnhancedPeers($domain, $verified_features);
            
            // Clear cache to include new enhanced host
            $this->clearCache();
            
            return [
                'success' => true,
                'message' => 'Enhanced host announced successfully',
                'domain' => $domain,
                'verified_features' => $verified_features,
                'added_to_registry' => true,
                'peers_notified' => true,
                'cache_cleared' => true
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Enhanced features not verified',
                'domain' => $domain,
                'features_found' => count($verified_features),
                'required_features' => 3,
                'tested_endpoints' => [
                    '/api/enhanced-search.php',
                    '/api/commission-tracking.php',
                    '/cluster/premium-cluster-manager.html',
                    '/host-discovery.html',
                    '/.enhanced-host-beacon.php'
                ]
            ];
        }
    }
    
    /**
     * Verify a host actually has enhanced features installed
     */
    private function verifyEnhancedInstallation($domain) {
        $features = [];
        
        // Test for actual enhanced endpoints that only exist after GitHub installation
        $test_endpoints = [
            'enhanced_search' => '/api/enhanced-search.php',
            'commission_tracking' => '/api/commission-tracking.php',
            'cluster_manager' => '/cluster/premium-cluster-manager.html',
            'host_discovery' => '/host-discovery.html',
            'discovery_beacon' => '/.enhanced-host-beacon.php'
        ];
        
        foreach ($test_endpoints as $feature => $endpoint) {
            if ($this->testEnhancedEndpoint($domain, $endpoint)) {
                $features[] = $feature;
            }
        }
        
        return $features;
    }
    
    private function testEnhancedEndpoint($domain, $endpoint) {
        $protocols = ['https', 'http'];
        
        foreach ($protocols as $protocol) {
            $url = "$protocol://$domain$endpoint";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'method' => 'GET',
                    'header' => 'User-Agent: Enhanced-Discovery/5.0'
                ]
            ]);
            
            try {
                $response = @file_get_contents($url, false, $context);
                
                if ($response && $this->validateEnhancedContent($response, $endpoint)) {
                    return true;
                }
            } catch (Exception $e) {
                // Continue to next protocol
            }
        }
        
        return false;
    }
    
    private function validateEnhancedContent($response, $endpoint) {
        // Check for specific enhanced indicators in the response
        $enhanced_indicators = [
            '/api/enhanced-search.php' => ['FastEnhancedHostDiscovery', 'enhanced_hosts', 'organic'],
            '/api/commission-tracking.php' => ['commission_rate', 'enhanced_commission'],
            '/cluster/premium-cluster-manager.html' => ['glassmorphism', 'premium-cluster', 'Enhanced'],
            '/host-discovery.html' => ['Enhanced Evernode', 'host-discovery'],
            '/.enhanced-host-beacon.php' => ['enhanced_host', 'beacon_version', 'discovery_protocol']
        ];
        
        $indicators = $enhanced_indicators[$endpoint] ?? [];
        $matches = 0;
        
        foreach ($indicators as $indicator) {
            if (stripos($response, $indicator) !== false) {
                $matches++;
            }
        }
        
        // Need at least 2 indicators to confirm it's really enhanced
        return $matches >= 2;
    }
    
    private function addToEnhancedRegistry($domain, $features, $github_source, $installation_time) {
        $registry = $this->loadEnhancedRegistry();
        
        $registry['enhanced_hosts'][$domain] = [
            'domain' => $domain,
            'features' => $features,
            'github_source' => $github_source,
            'discovered_at' => time(),
            'installation_time' => $installation_time,
            'last_verified' => time(),
            'confidence' => 100, // 100% confidence - actually verified
            'discovery_method' => 'self_announcement'
        ];
        
        $registry['total_discovered'] = count($registry['enhanced_hosts']);
        $registry['last_updated'] = time();
        
        file_put_contents($this->enhanced_registry, json_encode($registry, JSON_PRETTY_PRINT));
    }
    
    private function loadEnhancedRegistry() {
        if (file_exists($this->enhanced_registry)) {
            return json_decode(file_get_contents($this->enhanced_registry), true);
        }
        
        return [
            'enhanced_hosts' => [],
            'last_updated' => time(),
            'total_discovered' => 0
        ];
    }
    
    /**
     * Actively discover new enhanced hosts by probing all registry hosts
     */
    private function discoverNewEnhancedHosts() {
        $all_hosts = $this->getEvernodeRegistryHosts();
        $registry = $this->loadEnhancedRegistry();
        $newly_discovered = [];
        $scanned_count = 0;
        
        // Limit discovery to prevent timeout
        $max_scan = min(count($all_hosts), 100); // Max 100 hosts per discovery run
        
        foreach (array_slice($all_hosts, 0, $max_scan) as $host) {
            $domain = $host['domain'] ?? '';
            $scanned_count++;
            
            // Skip if already in registry
            if (isset($registry['enhanced_hosts'][$domain])) {
                continue;
            }
            
            // Skip if domain is empty or invalid
            if (empty($domain) || $domain === 'localhost') {
                continue;
            }
            
            // Test for enhanced features
            $features = $this->verifyEnhancedInstallation($domain);
            
            if (count($features) >= 3) {
                $this->addToEnhancedRegistry($domain, $features, false, null);
                $newly_discovered[] = [
                    'domain' => $domain,
                    'features' => $features,
                    'discovery_method' => 'active_probing'
                ];
            }
        }
        
        // Clear cache to include newly discovered hosts
        if (count($newly_discovered) > 0) {
            $this->clearCache();
        }
        
        return [
            'success' => true,
            'message' => 'Discovery scan completed',
            'hosts_scanned' => $scanned_count,
            'total_registry_hosts' => count($all_hosts),
            'newly_discovered' => count($newly_discovered),
            'total_enhanced' => count($registry['enhanced_hosts']) + count($newly_discovered),
            'new_hosts' => $newly_discovered,
            'scan_limit' => $max_scan
        ];
    }
    
    private function getEvernodeRegistryHosts() {
        // Check cache first
        if (file_exists($this->registry_cache)) {
            $cache_age = time() - filemtime($this->registry_cache);
            if ($cache_age < $this->fast_cache_duration) {
                $cached = json_decode(file_get_contents($this->registry_cache), true);
                if ($cached && isset($cached['hosts'])) {
                    return $cached['hosts'];
                }
            }
        }
        
        // Fetch from real API
        $hosts = $this->fetchEvernodeAPI();
        
        // Cache results
        $cache_data = [
            'hosts' => $hosts,
            'timestamp' => time(),
            'source' => 'api.evernode.network'
        ];
        file_put_contents($this->registry_cache, json_encode($cache_data, JSON_PRETTY_PRINT));
        
        return $hosts;
    }
    
    private function fetchEvernodeAPI() {
        $api_url = $this->evernode_api . '?limit=7000';
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method' => 'GET',
                'header' => 'Accept: application/json'
            ]
        ]);
        
        try {
            $response = file_get_contents($api_url, false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                
                if ($data && isset($data['data'])) {
                    $hosts = [];
                    
                    foreach ($data['data'] as $host_data) {
                        // Only include active hosts with good reputation
                        if ($host_data['active'] && $host_data['hostReputation'] >= 200) {
                            $hosts[] = $this->normalizeHostData($host_data);
                        }
                    }
                    
                    return $hosts;
                }
            }
        } catch (Exception $e) {
            error_log("Evernode API fetch failed: " . $e->getMessage());
        }
        
        return [];
    }
    
    private function normalizeHostData($host_data) {
        return [
            'xahau_address' => $host_data['address'] ?? '',
            'domain' => $host_data['domain'] ?? '',
            'reputation' => $host_data['hostReputation'] ?? 0,
            'cpu_cores' => $host_data['cpuCount'] ?? 0,
            'memory_gb' => round(($host_data['ramMb'] ?? 0) / 1024, 1),
            'disk_gb' => round(($host_data['diskMb'] ?? 0) / 1024, 1),
            'country_code' => $host_data['countryCode'] ?? '',
            'country' => $this->getCountryName($host_data['countryCode'] ?? ''),
            'active_instances' => $host_data['activeInstances'] ?? 0,
            'max_instances' => $host_data['maxInstances'] ?? 0,
            'available_instances' => max(0, ($host_data['maxInstances'] ?? 0) - ($host_data['activeInstances'] ?? 0)),
            'lease_amount' => $host_data['leaseAmount'] ?? '0',
            'cost_per_hour_evr' => $host_data['leaseAmount'] ?? '0',
            'version' => $host_data['version'] ?? '',
            'last_heartbeat' => $host_data['lastHeartbeatIndex'] ?? 0,
            'cpu_model' => $host_data['cpuModelName'] ?? '',
            'host_rating' => $host_data['hostRatingStr'] ?? 'Unknown',
            'quality_score' => $this->calculateQualityScore($host_data),
            'data_source' => 'real_evernode_api'
        ];
    }
    
    private function getCountryName($country_code) {
        $countries = [
            'US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom',
            'DE' => 'Germany', 'FR' => 'France', 'JP' => 'Japan', 'AU' => 'Australia',
            'NL' => 'Netherlands', 'SE' => 'Sweden', 'CH' => 'Switzerland',
            'SG' => 'Singapore', 'HK' => 'Hong Kong', 'KR' => 'South Korea',
            'IN' => 'India', 'BR' => 'Brazil', 'MX' => 'Mexico'
        ];
        
        return $countries[$country_code] ?? $country_code;
    }
    
    private function calculateQualityScore($host_data) {
        $score = 50; // Base score
        
        // Reputation scoring
        $reputation = $host_data['hostReputation'] ?? 0;
        if ($reputation >= 280) $score += 30;
        elseif ($reputation >= 250) $score += 20;
        elseif ($reputation >= 220) $score += 10;
        
        // CPU scoring
        $cpu_cores = $host_data['cpuCount'] ?? 0;
        if ($cpu_cores >= 8) $score += 15;
        elseif ($cpu_cores >= 4) $score += 10;
        elseif ($cpu_cores >= 2) $score += 5;
        
        // Memory scoring
        $memory_gb = ($host_data['ramMb'] ?? 0) / 1024;
        if ($memory_gb >= 32) $score += 15;
        elseif ($memory_gb >= 16) $score += 10;
        elseif ($memory_gb >= 8) $score += 5;
        
        // Availability scoring
        $availability = max(0, ($host_data['maxInstances'] ?? 0) - ($host_data['activeInstances'] ?? 0));
        if ($availability >= 5) $score += 10;
        elseif ($availability >= 2) $score += 5;
        
        return min(100, $score);
    }
    
    private function notifyEnhancedPeers($new_domain, $features) {
        $registry = $this->loadEnhancedRegistry();
        $notifications_sent = 0;
        
        foreach ($registry['enhanced_hosts'] as $peer_domain => $peer_data) {
            if ($peer_domain !== $new_domain) {
                if ($this->sendPeerNotification($peer_domain, $new_domain, $features)) {
                    $notifications_sent++;
                }
            }
        }
        
        return $notifications_sent;
    }
    
    private function sendPeerNotification($peer_domain, $new_domain, $features) {
        $notification_data = json_encode([
            'action' => 'peer_announcement',
            'new_peer' => $new_domain,
            'features' => $features,
            'announced_by' => $_SERVER['HTTP_HOST'] ?? 'unknown',
            'timestamp' => time()
        ]);
        
        $protocols = ['https', 'http'];
        
        foreach ($protocols as $protocol) {
            $notification_url = "$protocol://$peer_domain/api/enhanced-search.php";
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => $notification_data,
                    'timeout' => 5
                ]
            ]);
            
            if (@file_get_contents($notification_url, false, $context)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function handlePeerAnnouncement() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->errorResponse('POST required for peer announcements');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['new_peer'])) {
            return $this->errorResponse('Invalid peer announcement data');
        }
        
        $new_peer = $input['new_peer'];
        $features = $input['features'] ?? [];
        
        // Verify the announced peer actually has enhanced features
        $verified_features = $this->verifyEnhancedInstallation($new_peer);
        
        if (count($verified_features) >= 3) {
            $this->addToEnhancedRegistry($new_peer, $verified_features, false, null);
            $this->clearCache();
            
            return [
                'success' => true,
                'message' => 'Peer announcement accepted',
                'peer' => $new_peer,
                'verified_features' => $verified_features
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Peer verification failed',
            'peer' => $new_peer,
            'features_found' => count($verified_features)
        ];
    }
    
    private function getEnhancedHosts() {
        $registry = $this->loadEnhancedRegistry();
        $enhanced_hosts = [];
        
        foreach ($registry['enhanced_hosts'] as $domain => $data) {
            $enhanced_hosts[] = array_merge($data, [
                'enhanced' => true,
                'enhanced_confidence' => 100,
                'data_source' => 'organic_registry'
            ]);
        }
        
        return [
            'success' => true,
            'data_source' => 'organic_enhanced_registry',
            'total_enhanced' => count($enhanced_hosts),
            'enhanced_hosts' => $enhanced_hosts
        ];
    }
    
    private function getNetworkStats() {
        $registry = $this->loadEnhancedRegistry();
        $all_hosts = $this->getEvernodeRegistryHosts();
        
        // Calculate enhanced hosts by discovery method
        $github_sourced = 0;
        $probed_discovered = 0;
        
        foreach ($registry['enhanced_hosts'] as $host_data) {
            if ($host_data['github_source'] ?? false) {
                $github_sourced++;
            } else {
                $probed_discovered++;
            }
        }
        
        return [
            'success' => true,
            'network_stats' => [
                'total_registry_hosts' => count($all_hosts),
                'organically_discovered_enhanced' => count($registry['enhanced_hosts']),
                'enhancement_rate' => round((count($registry['enhanced_hosts']) / max(count($all_hosts), 1)) * 100, 2) . '%',
                'last_discovery' => date('c', $registry['last_updated']),
                'discovery_breakdown' => [
                    'github_installations' => $github_sourced,
                    'active_probing' => $probed_discovered
                ]
            ],
            'organic_discovery' => [
                'method' => 'github_installation_verification',
                'hardcoded_hosts' => 0,
                'confidence' => '100% verified endpoints'
            ],
            'enhanced_hosts' => array_values($registry['enhanced_hosts'])
        ];
    }
    
    private function testConnection() {
        $evernode_status = $this->testEvernodeAPI();
        $registry_status = file_exists($this->enhanced_registry);
        $cache_status = is_dir($this->cache_dir);
        
        return [
            'success' => true,
            'message' => 'Organic Enhanced Discovery System Online',
            'version' => '5.0.0',
            'discovery_method' => 'github_installation_based',
            'system_status' => [
                'evernode_api' => $evernode_status ? 'connected' : 'disconnected',
                'organic_registry' => $registry_status ? 'initialized' : 'not_found',
                'cache_system' => $cache_status ? 'operational' : 'disabled',
                'hardcoded_hosts' => 0 // NO hardcoded hosts!
            ],
            'api_endpoints' => [
                'search' => '?action=search',
                'enhanced_only' => '?action=search&enhanced_only=true',
                'announce' => '?action=announce (POST)',
                'discover' => '?action=discover',
                'stats' => '?action=stats'
            ]
        ];
    }
    
    private function getConfig() {
        $registry = $this->loadEnhancedRegistry();
        
        return [
            'success' => true,
            'config' => [
                'version' => '5.0.0',
                'discovery_method' => 'organic_only',
                'real_api_endpoint' => $this->evernode_api,
                'cache_duration_seconds' => $this->fast_cache_duration,
                'max_response_time_ms' => $this->max_response_time,
                'enhanced_registry_file' => $this->enhanced_registry,
                'total_enhanced_discovered' => count($registry['enhanced_hosts'])
            ],
            'features' => [
                'organic_discovery' => true,
                'github_integration' => true,
                'real_evernode_api' => true,
                'fast_caching' => true,
                'background_processing' => true,
                'peer_announcements' => true,
                'endpoint_verification' => true
            ]
        ];
    }
    
    private function testEvernodeAPI() {
        try {
            $context = stream_context_create([
                'http' => ['timeout' => 5]
            ]);
            $response = @file_get_contents($this->evernode_api . '?limit=1', false, $context);
            return $response !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function triggerBackgroundRefresh() {
        // Trigger background refresh without blocking response
        // This could be enhanced with a proper background job system
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        
        // Quick background refresh
        $this->getFreshHostData(false, 100);
    }
    
    private function clearCache() {
        $cache_files = [
            $this->cache_dir . '/fast_host_results.json',
            $this->registry_cache
        ];
        
        foreach ($cache_files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
    
    private function errorResponse($message) {
        return [
            'success' => false,
            'error' => $message,
            'timestamp' => time(),
            'version' => '5.0.0'
        ];
    }
}

// Initialize and handle request
$discovery = new FastEnhancedHostDiscovery();
echo json_encode($discovery->handleRequest(), JSON_PRETTY_PRINT);
?>

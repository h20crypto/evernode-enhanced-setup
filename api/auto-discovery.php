<?php
/**
 * Automatic Enhanced Host Discovery System
 * File: /api/auto-discovery.php
 * 
 * This system allows enhanced hosts to automatically find each other
 * without any manual configuration. Each host registers itself and
 * discovers others through multiple methods.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

class AutoDiscoverySystem {
    private $registryFile = '/var/www/html/data/discovered-hosts.json';
    private $myHostFile = '/var/www/html/data/my-host-info.json';
    private $centralRegistry = 'https://api.github.com/repos/h20crypto/evernode-enhanced-setup/contents/data/enhanced-hosts.json';
    
    public function __construct() {
        $this->ensureDataDirectory();
        $this->initializeMyHost();
    }
    
    /**
     * Main endpoint - handles all discovery operations
     */
    public function handleRequest() {
        $action = $_GET['action'] ?? 'discover';
        
        switch ($action) {
            case 'discover':
                return $this->performDiscovery();
            case 'register':
                return $this->registerMyHost();
            case 'ping':
                return $this->pingOtherHosts();
            case 'list':
                return $this->getDiscoveredHosts();
            case 'status':
                return $this->getDiscoveryStatus();
            default:
                return $this->error('Invalid action');
        }
    }
    
    /**
     * Automatically discover other enhanced hosts
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
     * Discover hosts from GitHub registry
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
                    
                    if (isset($registry['enhanced_hosts'])) {
                        foreach ($registry['enhanced_hosts'] as $host) {
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
     * Discover hosts using DNS patterns
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
     * Discover using known enhanced host patterns
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
     * Discover from peer host recommendations
     */
    private function discoverFromPeers() {
        $hosts = [];
        
        // Load existing discovered hosts
        $existing = $this->loadDiscoveredHosts();
        
        foreach ($existing as $peer) {
            if ($peer['status'] === 'online') {
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
     * Test if hosts are actually available and enhanced
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
     * Test a single host for enhanced capabilities
     */
    private function testSingleHost($host) {
        if (empty($host['domain']) || $host['domain'] === $_SERVER['HTTP_HOST']) {
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
     * Fetch data from another host
     */
    private function fetchFromHost($domain, $path) {
        $url = "https://{$domain}{$path}";
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'header' => 'User-Agent: Enhanced-Evernode-Discovery/1.0'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response) {
            $data = json_decode($response, true);
            return $data;
        }
        
        return null;
    }
    
    /**
     * Check if domain is resolvable
     */
    private function isDomainResolvable($domain) {
        return @gethostbyname($domain) !== $domain;
    }
    
    /**
     * Remove duplicate hosts
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
     * Save discovered hosts to local file
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
     * Load discovered hosts from local file
     */
    private function loadDiscoveredHosts() {
        if (file_exists($this->registryFile)) {
            $data = json_decode(file_get_contents($this->registryFile), true);
            return $data['hosts'] ?? [];
        }
        
        return [];
    }
    
    /**
     * Initialize our host information
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
     * Get our Xahau address
     */
    private function getMyXahauAddress() {
        $output = shell_exec('evernode status 2>/dev/null | grep "Host account address"');
        if (preg_match('/Host account address: (r[A-Za-z0-9]{25,34})/', $output, $matches)) {
            return $matches[1];
        }
        
        return 'unknown';
    }
    
    /**
     * Propagate our host info to newly discovered hosts
     */
    private function propagateToDiscoveredHosts($hosts) {
        $myHost = json_decode(file_get_contents($this->myHostFile), true);
        
        foreach ($hosts as $host) {
            if ($host['status'] === 'online' && $host['enhanced']) {
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
     * Send our host info to another host
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
     * Get discovery status
     */
    private function getDiscoveryStatus() {
        $hosts = $this->loadDiscoveredHosts();
        $online = array_filter($hosts, fn($h) => $h['status'] === 'online');
        $enhanced = array_filter($hosts, fn($h) => $h['enhanced'] === true);
        
        return $this->success([
            'total_discovered' => count($hosts),
            'online_hosts' => count($online),
            'enhanced_hosts' => count($enhanced),
            'last_discovery' => $this->getLastDiscoveryTime(),
            'auto_discovery_enabled' => true
        ]);
    }
    
    /**
     * Get list of discovered hosts
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
     * Get last discovery time
     */
    private function getLastDiscoveryTime() {
        if (file_exists($this->registryFile)) {
            $data = json_decode(file_get_contents($this->registryFile), true);
            return $data['last_discovery'] ?? 0;
        }
        
        return 0;
    }
    
    /**
     * Ensure data directory exists
     */
    private function ensureDataDirectory() {
        $dataDir = dirname($this->registryFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
    }
    
    /**
     * Success response
     */
    private function success($data) {
        return [
            'success' => true,
            'data' => $data,
            'timestamp' => time()
        ];
    }
    
    /**
     * Error response
     */
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

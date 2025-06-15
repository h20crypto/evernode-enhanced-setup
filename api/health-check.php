<?php
// api/health-check.php - Enhanced Evernode System Health Monitor
// Monitors all components of your enhanced setup

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

class EnhancedEvernodeHealthCheck {
    private $health;
    private $startTime;
    
    public function __construct() {
        $this->startTime = microtime(true);
        $this->health = [
            'status' => 'healthy',
            'timestamp' => date('c'),
            'version' => '1.0.0',
            'host_info' => $this->getHostInfo(),
            'services' => [],
            'metrics' => [],
            'api_endpoints' => [],
            'uptime' => $this->getSystemUptime()
        ];
    }
    
    public function runFullHealthCheck() {
        // Check all system components
        $this->checkInstanceCountAPI();
        $this->checkClusterSystem();
        $this->checkNFTSystem();
        $this->checkFileSystem();
        $this->checkHostDiscovery();
        $this->checkCommissionSystem();
        $this->calculateSystemMetrics();
        
        // Determine overall status
        $this->determineOverallStatus();
        
        return $this->health;
    }
    
    private function checkInstanceCountAPI() {
        try {
            $apiFile = __DIR__ . '/instance-count.php';
            if (!file_exists($apiFile)) {
                $this->health['services']['instance_count'] = 'missing';
                $this->health['api_endpoints']['instance_count'] = [
                    'status' => 'missing',
                    'file_exists' => false
                ];
                return;
            }
            
            // Test the API by including it
            ob_start();
            $result = include $apiFile;
            $output = ob_get_clean();
            
            $data = json_decode($output, true);
            if ($data && isset($data['total'])) {
                $this->health['services']['instance_count'] = 'healthy';
                $this->health['api_endpoints']['instance_count'] = [
                    'status' => 'healthy',
                    'response_time_ms' => round((microtime(true) - $this->startTime) * 1000, 2),
                    'data_source' => $data['data_source'] ?? 'unknown'
                ];
                $this->health['metrics']['total_slots'] = $data['total'];
                $this->health['metrics']['used_slots'] = $data['used'];
                $this->health['metrics']['available_slots'] = $data['available'];
                $this->health['metrics']['usage_percentage'] = $data['usage_percentage'];
            } else {
                $this->health['services']['instance_count'] = 'unhealthy';
                $this->health['api_endpoints']['instance_count'] = [
                    'status' => 'unhealthy',
                    'error' => 'Invalid JSON response'
                ];
            }
            
        } catch (Exception $e) {
            $this->health['services']['instance_count'] = 'unhealthy';
            $this->health['api_endpoints']['instance_count'] = [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function checkClusterSystem() {
        $clusterFiles = [
            'cluster-manager.php',
            'cluster-extension.php'
        ];
        
        $healthyCount = 0;
        $totalCount = count($clusterFiles);
        
        foreach ($clusterFiles as $file) {
            $filePath = __DIR__ . '/' . $file;
            if (file_exists($filePath) && is_readable($filePath)) {
                $healthyCount++;
                $this->health['api_endpoints']['cluster_' . str_replace(['-', '.php'], ['_', ''], $file)] = [
                    'status' => 'available',
                    'file_size' => filesize($filePath)
                ];
            }
        }
        
        if ($healthyCount === $totalCount) {
            $this->health['services']['cluster_management'] = 'healthy';
        } elseif ($healthyCount > 0) {
            $this->health['services']['cluster_management'] = 'partial';
        } else {
            $this->health['services']['cluster_management'] = 'unavailable';
        }
        
        $this->health['metrics']['cluster_apis_available'] = $healthyCount;
        $this->health['metrics']['cluster_apis_total'] = $totalCount;
    }
    
    private function checkNFTSystem() {
        try {
            $nftFiles = [
                'xahau-nft-licenses.php',
                'nft-image-generator.php'
            ];
            
            $available = 0;
            foreach ($nftFiles as $file) {
                if (file_exists(__DIR__ . '/' . $file)) {
                    $available++;
                }
            }
            
            if ($available === count($nftFiles)) {
                $this->health['services']['nft_system'] = 'healthy';
            } elseif ($available > 0) {
                $this->health['services']['nft_system'] = 'partial';
            } else {
                $this->health['services']['nft_system'] = 'unavailable';
            }
            
            $this->health['metrics']['nft_components'] = $available . '/' . count($nftFiles);
            
            // Test crypto rates if available
            if (file_exists(__DIR__ . '/crypto-rates.php')) {
                $this->health['api_endpoints']['crypto_rates'] = [
                    'status' => 'available'
                ];
            }
            
        } catch (Exception $e) {
            $this->health['services']['nft_system'] = 'error';
            $this->health['errors']['nft_system'] = $e->getMessage();
        }
    }
    
    private function checkFileSystem() {
        try {
            $criticalPaths = [
                '../cluster/' => 'cluster_directory',
                '../data/' => 'data_directory', 
                '../tools/' => 'tools_directory',
                '../assets/' => 'assets_directory'
            ];
            
            $pathStatus = [];
            foreach ($criticalPaths as $path => $name) {
                $fullPath = __DIR__ . '/' . $path;
                $pathStatus[$name] = [
                    'exists' => is_dir($fullPath),
                    'writable' => is_dir($fullPath) ? is_writable($fullPath) : false
                ];
            }
            
            // Check disk space
            $diskFree = disk_free_space(__DIR__);
            $diskTotal = disk_total_space(__DIR__);
            $diskUsage = $diskTotal > 0 ? (($diskTotal - $diskFree) / $diskTotal) * 100 : 0;
            
            $this->health['services']['filesystem'] = 'healthy';
            $this->health['metrics']['disk_usage_percent'] = round($diskUsage, 1);
            $this->health['metrics']['disk_free_gb'] = round($diskFree / (1024**3), 2);
            $this->health['filesystem'] = $pathStatus;
            
            if ($diskUsage > 90) {
                $this->health['services']['filesystem'] = 'warning';
                $this->health['warnings'][] = 'High disk usage: ' . round($diskUsage, 1) . '%';
            }
            
        } catch (Exception $e) {
            $this->health['services']['filesystem'] = 'error';
            $this->health['errors']['filesystem'] = $e->getMessage();
        }
    }
    
    private function checkHostDiscovery() {
        try {
            // Check if enhanced hosts data exists
            $hostsFile = __DIR__ . '/../data/enhanced-hosts.json';
            if (file_exists($hostsFile)) {
                $hostsData = json_decode(file_get_contents($hostsFile), true);
                if ($hostsData && isset($hostsData['hosts'])) {
                    $this->health['services']['host_discovery'] = 'healthy';
                    $this->health['metrics']['known_hosts'] = count($hostsData['hosts']);
                } else {
                    $this->health['services']['host_discovery'] = 'degraded';
                    $this->health['metrics']['known_hosts'] = 0;
                }
            } else {
                $this->health['services']['host_discovery'] = 'unavailable';
                $this->health['metrics']['known_hosts'] = 0;
            }
            
            // Check CLI tools
            $cliTool = __DIR__ . '/../tools/discover-cli.js';
            $this->health['tools']['discover_cli'] = file_exists($cliTool) ? 'available' : 'missing';
            
        } catch (Exception $e) {
            $this->health['services']['host_discovery'] = 'error';
            $this->health['errors']['host_discovery'] = $e->getMessage();
        }
    }
    
    private function checkCommissionSystem() {
        try {
            // Check if commission/payment monitoring exists
            $commissionFiles = [
                'monitor-payments.php'
            ];
            
            $available = 0;
            foreach ($commissionFiles as $file) {
                if (file_exists(__DIR__ . '/' . $file)) {
                    $available++;
                }
            }
            
            if ($available > 0) {
                $this->health['services']['commission_system'] = 'healthy';
            } else {
                $this->health['services']['commission_system'] = 'unavailable';
            }
            
            $this->health['metrics']['commission_components'] = $available;
            
        } catch (Exception $e) {
            $this->health['services']['commission_system'] = 'error';
            $this->health['errors']['commission_system'] = $e->getMessage();
        }
    }
    
    private function calculateSystemMetrics() {
        // PHP and system metrics
        $this->health['metrics']['php_version'] = PHP_VERSION;
        $this->health['metrics']['memory_usage_mb'] = round(memory_get_usage(true) / (1024 * 1024), 2);
        $this->health['metrics']['memory_peak_mb'] = round(memory_get_peak_usage(true) / (1024 * 1024), 2);
        
        // System load if available
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $this->health['metrics']['load_average'] = round($load[0], 2);
        }
        
        // Response time
        $this->health['metrics']['response_time_ms'] = round((microtime(true) - $this->startTime) * 1000, 2);
        
        // Count total API endpoints
        $apiFiles = glob(__DIR__ . '/*.php');
        $this->health['metrics']['total_api_endpoints'] = count($apiFiles);
    }
    
    private function determineOverallStatus() {
        $criticalServices = ['instance_count', 'filesystem'];
        $importantServices = ['cluster_management', 'nft_system'];
        
        $criticalIssues = 0;
        $importantIssues = 0;
        
        foreach ($criticalServices as $service) {
            if (isset($this->health['services'][$service]) && 
                $this->health['services'][$service] !== 'healthy') {
                $criticalIssues++;
            }
        }
        
        foreach ($importantServices as $service) {
            if (isset($this->health['services'][$service]) && 
                $this->health['services'][$service] === 'unavailable') {
                $importantIssues++;
            }
        }
        
        if ($criticalIssues > 0) {
            $this->health['status'] = 'unhealthy';
        } elseif ($importantIssues > 1) {
            $this->health['status'] = 'degraded';
        } else {
            $this->health['status'] = 'healthy';
        }
    }
    
    private function getHostInfo() {
        return [
            'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? ''
        ];
    }
    
    private function getSystemUptime() {
        if (file_exists('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            $uptime = explode(' ', $uptime)[0];
            return round($uptime / 86400, 1) . ' days';
        }
        return 'unknown';
    }
}

// Main execution
try {
    $healthChecker = new EnhancedEvernodeHealthCheck();
    $healthData = $healthChecker->runFullHealthCheck();
    
    // Set HTTP status code based on health
    $httpStatus = 200;
    if ($healthData['status'] === 'unhealthy') {
        $httpStatus = 503; // Service Unavailable
    } elseif ($healthData['status'] === 'degraded') {
        $httpStatus = 206; // Partial Content
    }
    
    http_response_code($httpStatus);
    echo json_encode($healthData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Health check system failure',
        'error' => $e->getMessage(),
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT);
}
?>

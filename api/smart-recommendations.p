<?php
/**
 * Smart Recommendations using Autonomous Discovery
 * Automatically recommends hosts discovered by the discovery system
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

class SmartRecommendations {
    private $discovery_cache = '/tmp/enhanced_hosts_cache.json';
    
    public function getRecommendations($max_hosts = 3, $exclude_full = true) {
        // Load discovered hosts
        $discovered_hosts = $this->loadDiscoveredHosts();
        
        if (empty($discovered_hosts)) {
            // Trigger discovery if no hosts found
            $this->triggerDiscovery();
            $discovered_hosts = $this->loadDiscoveredHosts();
        }
        
        // Filter and sort recommendations
        $recommendations = [];
        
        foreach ($discovered_hosts as $host) {
            // Skip if excluding full hosts and this host is full
            if ($exclude_full && isset($host['availability']) && $host['availability'] <= 0) {
                continue;
            }
            
            // Skip low quality hosts
            if ($host['quality_score'] < 50) {
                continue;
            }
            
            // Transform to recommendation format
            $recommendation = [
                'name' => $this->generateHostName($host),
                'host' => $host['host'],
                'location' => $host['location'] ?? 'Unknown',
                'features' => $host['features'] ?? [],
                'quality_score' => $host['quality_score'],
                'availability' => $host['availability'] ?? null,
                'capacity' => $host['capacity'] ?? null,
                'lease_rate' => $host['lease_rate'] ?? 'Unknown',
                'response_time' => $host['response_time'] ?? null,
                'uptime_score' => $host['uptime_score'] ?? 0,
                'last_checked' => $host['last_checked'] ?? null,
                'discovered_automatically' => true
            ];
            
            $recommendations[] = $recommendation;
            
            if (count($recommendations) >= $max_hosts) {
                break;
            }
        }
        
        return $recommendations;
    }
    
    private function loadDiscoveredHosts() {
        if (file_exists($this->discovery_cache)) {
            $data = json_decode(file_get_contents($this->discovery_cache), true);
            return $data ?: [];
        }
        
        return [];
    }
    
    private function triggerDiscovery() {
        // Trigger background discovery
        $discovery_url = "http://localhost/api/host-discovery.php?action=discover&refresh=true";
        
        // Use curl in background to avoid blocking
        $cmd = "curl -s '$discovery_url' > /dev/null 2>&1 &";
        shell_exec($cmd);
    }
    
    private function generateHostName($host_data) {
        // Generate friendly names for discovered hosts
        $location = $host_data['location'] ?? 'Unknown';
        $quality = $host_data['quality_score'] ?? 0;
        
        $prefix = '';
        if ($quality >= 90) $prefix = 'Premium';
        elseif ($quality >= 80) $prefix = 'Professional';
        elseif ($quality >= 70) $prefix = 'Enhanced';
        else $prefix = 'Standard';
        
        return "$prefix Host ($location)";
    }
    
    public function getNetworkStats() {
        $hosts = $this->loadDiscoveredHosts();
        
        $stats = [
            'total_discovered' => count($hosts),
            'high_quality' => count(array_filter($hosts, function($h) { 
                return ($h['quality_score'] ?? 0) >= 80; 
            })),
            'available_now' => count(array_filter($hosts, function($h) { 
                return ($h['availability'] ?? 0) > 0; 
            })),
            'last_discovery' => file_exists($this->discovery_cache) ? 
                date('Y-m-d H:i:s', filemtime($this->discovery_cache)) : 'Never',
            'network_locations' => $this->getUniqueLocations($hosts),
            'average_quality' => $this->calculateAverageQuality($hosts)
        ];
        
        return $stats;
    }
    
    private function getUniqueLocations($hosts) {
        $locations = array_unique(array_map(function($h) { 
            return $h['location'] ?? 'Unknown'; 
        }, $hosts));
        
        return array_values($locations);
    }
    
    private function calculateAverageQuality($hosts) {
        if (empty($hosts)) return 0;
        
        $total_quality = array_sum(array_map(function($h) { 
            return $h['quality_score'] ?? 0; 
        }, $hosts));
        
        return round($total_quality / count($hosts), 1);
    }
}

// Handle API requests
$recommendations = new SmartRecommendations();

switch ($_GET['action'] ?? 'list') {
    case 'list':
        $max_hosts = isset($_GET['max_hosts']) ? (int)$_GET['max_hosts'] : 3;
        $exclude_full = isset($_GET['exclude_full']) ? (bool)$_GET['exclude_full'] : true;
        
        $hosts = $recommendations->getRecommendations($max_hosts, $exclude_full);
        
        echo json_encode([
            'success' => true,
            'hosts' => $hosts,
            'total_recommended' => count($hosts),
            'timestamp' => date('Y-m-d H:i:s'),
            'source' => 'autonomous_discovery'
        ]);
        break;
        
    case 'stats':
        $stats = $recommendations->getNetworkStats();
        
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

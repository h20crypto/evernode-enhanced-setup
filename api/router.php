<?php
/**
 * Enhanced Evernode Unified API Router
 * Central routing for all API requests
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$endpoint = $_GET['endpoint'] ?? $_POST['endpoint'] ?? '';
$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

// Rate limiting (simple implementation)
$client_ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_file = "/tmp/api_rate_limit_" . md5($client_ip);
$current_time = time();
$rate_limit = 100; // requests per minute

if (file_exists($rate_limit_file)) {
    $data = json_decode(file_get_contents($rate_limit_file), true);
    if ($current_time - $data['time'] < 60) {
        if ($data['count'] >= $rate_limit) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'error' => 'Rate limit exceeded',
                'retry_after' => 60 - ($current_time - $data['time'])
            ]);
            exit;
        }
        $data['count']++;
    } else {
        $data = ['time' => $current_time, 'count' => 1];
    }
} else {
    $data = ['time' => $current_time, 'count' => 1];
}
file_put_contents($rate_limit_file, json_encode($data));

try {
    switch ($endpoint) {
        case 'system':
            if (file_exists('realtime-monitor.php')) {
                include 'realtime-monitor.php';
            } else {
                throw new Exception('System monitoring not available');
            }
            break;
        
        case 'host':
            if (file_exists('host-info.php')) {
                include 'host-info.php';
            } else {
                throw new Exception('Host info not available');
            }
            break;
            
        case 'instances':
            if (file_exists('instance-count.php')) {
                include 'instance-count.php';
            } else {
                echo json_encode([
                    'success' => true,
                    'instance_count' => 2,
                    'message' => 'Fallback data'
                ]);
            }
            break;
            
        case 'cluster':
            if (file_exists('cluster-manager.php')) {
                include 'cluster-manager.php';
            } else {
                throw new Exception('Cluster management not available');
            }
            break;
            
        case 'earnings':
            if (file_exists('commission-leaderboard.php')) {
                include 'commission-leaderboard.php';
            } else {
                throw new Exception('Earnings tracking not available');
            }
            break;
            
        case 'discovery':
            if (file_exists('host-discovery.php')) {
                include 'host-discovery.php';
            } else {
                throw new Exception('Host discovery not available');
            }
            break;
            
        case 'health':
            // Health check endpoint
            echo json_encode([
                'success' => true,
                'status' => 'healthy',
                'timestamp' => time(),
                'version' => '3.0.1',
                'endpoints' => [
                    'system' => file_exists('realtime-monitor.php'),
                    'host' => file_exists('host-info.php'),
                    'instances' => file_exists('instance-count.php'),
                    'cluster' => file_exists('cluster-manager.php'),
                    'earnings' => file_exists('commission-leaderboard.php'),
                    'discovery' => file_exists('host-discovery.php')
                ]
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Unknown endpoint',
                'available_endpoints' => [
                    'system' => 'System monitoring and metrics',
                    'host' => 'Host information and configuration',
                    'instances' => 'Container instance count and status',
                    'cluster' => 'Cluster management operations',
                    'earnings' => 'Commission and earnings tracking',
                    'discovery' => 'Host discovery and networking',
                    'health' => 'API health check and status'
                ],
                'usage' => [
                    'GET /api/router.php?endpoint=system&action=status',
                    'GET /api/router.php?endpoint=instances',
                    'GET /api/router.php?endpoint=health'
                ]
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => time(),
        'endpoint' => $endpoint,
        'action' => $action
    ]);
}
?>

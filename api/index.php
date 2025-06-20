<?php
/**
 * Unified API Router for Evernode Enhanced Setup
 * Dispatches requests to appropriate handlers
 * Usage: /api/?endpoint=instance-count or /api/?action=host-discovery
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once 'config.php';

// Get endpoint from multiple possible parameters
$endpoint = $_GET['endpoint'] ?? $_GET['action'] ?? $_GET['component'] ?? 'status';

// Log API requests for debugging (optional)
error_log("API Request: {$endpoint} from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

try {
    switch($endpoint) {
        case 'instance-count':
        case 'instances':
            include 'instance-count.php';
            break;
            
        case 'host-discovery':
        case 'discovery':
            include 'host-discovery.php';
            break;
            
        case 'smart-urls':
        case 'urls':
            include 'smart-urls.php';
            break;
            
        case 'crypto-rates':
        case 'rates':
        case 'pricing':
            include 'crypto-rates.php';
            break;
            
        case 'network-discovery':
        case 'network':
            include 'network-discovery.php';
            break;
            
        case 'enhancement-status':
        case 'enhanced':
            include 'enhancement-status.php';
            break;
            
        case 'commission':
        case 'earnings':
            include 'commission-tracking.php';
            break;
            
        case 'host-info':
        case 'info':
            include 'host-info.php';
            break;
            
        case 'deployment-status':
        case 'deploy':
            include 'deployment-status.php';
            break;
            
        case 'smart-recommendations':
        case 'recommendations':
            include 'smart-recommendations.php';
            break;
            
        case 'status':
        case 'health':
            // API health check
            echo json_encode([
                'success' => true,
                'status' => 'healthy',
                'version' => '3.0',
                'system' => 'evernode-enhanced',
                'timestamp' => date('c'),
                'available_endpoints' => [
                    'instance-count',
                    'host-discovery', 
                    'smart-urls',
                    'crypto-rates',
                    'network-discovery',
                    'enhancement-status',
                    'commission',
                    'host-info',
                    'deployment-status',
                    'smart-recommendations'
                ]
            ]);
            break;
            
        case 'ping':
            // Simple ping endpoint for network testing
            echo json_encode([
                'success' => true,
                'pong' => true,
                'timestamp' => time(),
                'server' => $_SERVER['HTTP_HOST'] ?? 'unknown'
            ]);
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Endpoint not found',
                'requested' => $endpoint,
                'available' => [
                    'instance-count',
                    'host-discovery', 
                    'smart-urls',
                    'crypto-rates',
                    'network-discovery',
                    'enhancement-status',
                    'commission',
                    'host-info',
                    'deployment-status',
                    'smart-recommendations',
                    'status',
                    'ping'
                ],
                'usage' => [
                    '/api/?endpoint=instance-count',
                    '/api/?endpoint=crypto-rates',
                    '/api/?endpoint=status'
                ]
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage(),
        'endpoint' => $endpoint
    ]);
    
    // Log the error
    error_log("API Error [{$endpoint}]: " . $e->getMessage());
}
?>

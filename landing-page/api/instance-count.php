<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit();
}

try {
    // Get container information using Docker commands
    $container_count = 0;
    $container_details = [];
    
    // Try to get actual container count
    $docker_output = shell_exec('docker ps --format "{{.Names}}" 2>/dev/null');
    if ($docker_output) {
        $containers = array_filter(explode("\n", trim($docker_output)));
        $container_count = count($containers);
        
        // Group by user (sashi prefixed containers)
        foreach ($containers as $container) {
            if (strpos($container, 'sashi') === 0) {
                $user = explode('_', $container)[0];
                if (!isset($container_details[$user])) {
                    $container_details[$user] = [];
                }
                $container_details[$user][] = $container;
            }
        }
    }
    
    // Fallback: Try evernode commands
    $total_instances = 3; // Default
    $evernode_total = shell_exec('evernode totalins 2>/dev/null');
    if ($evernode_total && is_numeric(trim($evernode_total))) {
        $total_instances = (int)trim($evernode_total);
    }
    
    // Calculate availability
    $available = max(0, $total_instances - $container_count);
    $usage_percentage = $total_instances > 0 ? round(($container_count / $total_instances) * 100) : 0;
    
    // Determine status
    $status = 'available';
    $status_message = '✅ Ready for new deployments!';
    
    if ($usage_percentage >= 90) {
        $status = 'full';
        $status_message = '🔴 Host at capacity';
    } elseif ($usage_percentage >= 70) {
        $status = 'limited';
        $status_message = '⚡ Limited slots available';
    }
    
    // Format container details for response
    $formatted_details = [];
    foreach ($container_details as $user => $containers) {
        $formatted_details[] = [
            'user' => $user,
            'container_count' => count($containers),
            'containers' => implode(', ', $containers)
        ];
    }
    
    // Build response
    $response = [
        'total' => $total_instances,
        'used' => $container_count,
        'available' => $available,
        'usage_percentage' => $usage_percentage,
        'status' => $status,
        'status_message' => $status_message,
        'last_updated' => date('Y-m-d H:i:s'),
        'data_source' => 'actual_containers',
        'debug_info' => [
            'containers_running' => $container_count,
            'container_details' => $formatted_details,
            'counting_method' => 'docker_api'
        ],
        'success' => true
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to get instance data',
        'message' => $e->getMessage(),
        'success' => false
    ], JSON_PRETTY_PRINT);
}
?>

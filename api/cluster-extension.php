<?php
// cluster-extension.php - Add to your existing enhanced setup

class ClusterExtensionManager extends LicensedClusterManager {
    
    public function extendAllClusters($license_key, $extension_hours) {
        // Validate license
        $license_check = $this->license_manager->validateLicense($license_key);
        if (!$license_check['valid']) {
            return ['error' => 'Valid license required', 'license_error' => $license_check['error']];
        }
        
        // Get all clusters for this license
        $clusters = $this->getUserClusters($license_key);
        $results = [];
        $total_cost = 0;
        $total_instances = 0;
        
        foreach ($clusters as $cluster) {
            $result = $this->extendSingleCluster($cluster['id'], $extension_hours);
            $results[] = [
                'cluster_id' => $cluster['id'],
                'cluster_name' => $cluster['name'],
                'instances' => count(json_decode($cluster['instances'], true)),
                'status' => $result['success'] ? 'extended' : 'failed',
                'cost' => $result['cost'] ?? 0,
                'new_expiry' => $result['new_expiry'] ?? null
            ];
            
            if ($result['success']) {
                $total_cost += $result['cost'];
                $total_instances += count(json_decode($cluster['instances'], true));
            }
        }
        
        // Calculate savings
        $manual_time = $total_instances * 0.5; // 30 minutes per instance manually
        $cluster_time = 0.05; // 3 minutes total with cluster manager
        $time_saved = $manual_time - $cluster_time;
        $efficiency_gain = ($manual_time / $cluster_time) * 100;
        
        return [
            'success' => true,
            'results' => $results,
            'summary' => [
                'total_clusters' => count($clusters),
                'total_instances' => $total_instances,
                'total_cost' => $total_cost,
                'time_saved_hours' => round($time_saved, 1),
                'efficiency_gain_percent' => round($efficiency_gain, 0),
                'manual_time_hours' => $manual_time,
                'cluster_time_minutes' => $cluster_time * 60
            ]
        ];
    }
    
    public function extendSingleCluster($cluster_id, $extension_hours) {
        $cluster = $this->getClusterById($cluster_id);
        if (!$cluster) {
            return ['success' => false, 'error' => 'Cluster not found'];
        }
        
        $instances = json_decode($cluster['instances'], true);
        if (!$instances) {
            return ['success' => false, 'error' => 'No instances found'];
        }
        
        // Create extension file
        $extend_file = $this->createExtensionFile($instances, $extension_hours);
        
        // Calculate cost
        $cost_per_instance = 0.25; // Default rate, should come from host
        $total_cost = count($instances) * $cost_per_instance * $extension_hours;
        
        // Execute evdevkit extend command
        $command = sprintf(
            '%s extend %s -m %d --output-json 2>&1',
            $this->evdevkit_path,
            escapeshellarg($extend_file),
            intval($extension_hours)
        );
        
        $output = shell_exec($command);
        $this->logExtensionAttempt($cluster_id, $command, $output);
        
        // Parse output for success/failure
        $success = !str_contains($output, 'error') && !str_contains($output, 'failed');
        
        if ($success) {
            // Update cluster database
            $new_expiry = date('Y-m-d H:i:s', strtotime('+' . $extension_hours . ' hours'));
            $this->updateClusterExpiry($cluster_id, $new_expiry);
            
            // Clean up temp file
            unlink($extend_file);
            
            return [
                'success' => true,
                'cost' => $total_cost,
                'new_expiry' => $new_expiry,
                'instances_extended' => count($instances),
                'output' => $output
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Extension failed',
            'output' => $output
        ];
    }
    
    public function getExtensionCostEstimate($license_key, $extension_hours) {
        $clusters = $this->getUserClusters($license_key);
        $total_instances = 0;
        $total_cost = 0;
        $savings_data = [];
        
        foreach ($clusters as $cluster) {
            $instances = json_decode($cluster['instances'], true);
            $instance_count = count($instances);
            $cluster_cost = $instance_count * 0.25 * $extension_hours; // $0.25/hour default rate
            
            $total_instances += $instance_count;
            $total_cost += $cluster_cost;
            
            $savings_data[] = [
                'cluster_name' => $cluster['name'],
                'instances' => $instance_count,
                'cost' => $cluster_cost,
                'manual_time_minutes' => $instance_count * 30,
                'cluster_time_seconds' => 30
            ];
        }
        
        // Calculate overall savings
        $manual_total_time = $total_instances * 0.5; // hours
        $manual_downtime_cost = $total_instances * 10; // $10 per instance downtime risk
        
        return [
            'total_clusters' => count($clusters),
            'total_instances' => $total_instances,
            'total_cost' => round($total_cost, 2),
            'manual_time_hours' => $manual_total_time,
            'cluster_time_minutes' => 3,
            'time_saved_hours' => $manual_total_time - 0.05,
            'efficiency_gain_percent' => round(($manual_total_time / 0.05) * 100, 0),
            'downtime_risk_savings' => $manual_downtime_cost,
            'clusters' => $savings_data
        ];
    }
    
    public function getClusterStatus($license_key) {
        $clusters = $this->getUserClusters($license_key);
        $status_data = [];
        
        foreach ($clusters as $cluster) {
            $instances = json_decode($cluster['instances'], true);
            $urgency = $this->calculateUrgency($cluster);
            
            $status_data[] = [
                'id' => $cluster['id'],
                'name' => $cluster['name'],
                'instances' => count($instances),
                'time_remaining' => $this->calculateTimeRemaining($cluster),
                'urgency' => $urgency,
                'hourly_rate' => 0.25, // Should be calculated from actual host rates
                'hosts' => array_unique(array_column($instances, 'host')),
                'last_extended' => $cluster['updated_at']
            ];
        }
        
        return $status_data;
    }
    
    private function createExtensionFile($instances, $hours) {
        $temp_file = tempnam(sys_get_temp_dir(), 'extend_cluster_');
        $lines = [];
        
        foreach ($instances as $instance) {
            $lines[] = sprintf(
                '%s:%s:%d',
                $instance['host'],
                $instance['name'],
                $hours
            );
        }
        
        file_put_contents($temp_file, implode("\n", $lines));
        return $temp_file;
    }
    
    private function calculateUrgency($cluster) {
        $time_remaining = $this->calculateTimeRemainingHours($cluster);
        
        if ($time_remaining < 6) return 'critical';
        if ($time_remaining < 24) return 'moderate';
        return 'low';
    }
    
    private function calculateTimeRemaining($cluster) {
        $hours = $this->calculateTimeRemainingHours($cluster);
        
        if ($hours < 1) {
            return round($hours * 60) . ' minutes';
        }
        return round($hours, 1) . ' hours';
    }
    
    private function calculateTimeRemainingHours($cluster) {
        // This would need to be calculated based on actual instance expiry times
        // For now, using a simulated calculation
        $created = strtotime($cluster['created_at']);
        $now = time();
        $elapsed_hours = ($now - $created) / 3600;
        
        // Assume 24-hour initial lease
        return max(0, 24 - $elapsed_hours);
    }
    
    private function updateClusterExpiry($cluster_id, $new_expiry) {
        $stmt = $this->db->prepare("
            UPDATE clusters 
            SET updated_at = CURRENT_TIMESTAMP, 
                config = JSON_SET(IFNULL(config, '{}'), '$.last_extended', ?)
            WHERE id = ?
        ");
        $stmt->execute([$new_expiry, $cluster_id]);
    }
    
    private function logExtensionAttempt($cluster_id, $command, $output) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'cluster_id' => $cluster_id,
            'command' => $command,
            'output' => $output
        ];
        
        $log_file = __DIR__ . '/extension_logs.json';
        $logs = file_exists($log_file) ? json_decode(file_get_contents($log_file), true) : [];
        $logs[] = $log_entry;
        
        // Keep only last 100 entries
        $logs = array_slice($logs, -100);
        file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT));
    }
    
    private function getUserClusters($license_key) {
        $stmt = $this->db->prepare("
            SELECT * FROM clusters 
            WHERE user_id = ? AND status = 'active'
            ORDER BY created_at DESC
        ");
        $stmt->execute([$license_key]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// API Routes for Extension Management
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $extension_manager = new ClusterExtensionManager();
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($_GET['action'] ?? '') {
        case 'extend-all':
            $result = $extension_manager->extendAllClusters(
                $input['license_key'],
                $input['extension_hours']
            );
            break;
            
        case 'extend-cluster':
            $result = $extension_manager->extendSingleCluster(
                $input['cluster_id'],
                $input['extension_hours']
            );
            break;
            
        case 'cost-estimate':
            $result = $extension_manager->getExtensionCostEstimate(
                $input['license_key'],
                $input['extension_hours']
            );
            break;
            
        default:
            $result = ['error' => 'Invalid action'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $extension_manager = new ClusterExtensionManager();
    
    switch ($_GET['action'] ?? '') {
        case 'cluster-status':
            $result = $extension_manager->getClusterStatus($_GET['license_key']);
            break;
            
        default:
            $result = ['error' => 'Invalid action'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}
?>

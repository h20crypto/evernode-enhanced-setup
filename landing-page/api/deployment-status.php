<?php
/**
 * Deployment Status Tracker API
 * Tracks deployment progress and provides real-time updates
 * Add this to: /var/www/html/api/deployment-status.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

class DeploymentStatusTracker {
    private $status_dir = '/tmp/deployment_status';
    
    public function __construct() {
        if (!is_dir($this->status_dir)) {
            mkdir($this->status_dir, 0755, true);
        }
    }
    
    public function startTracking($deploymentId, $appType, $containerName, $tenant) {
        $status = [
            'deployment_id' => $deploymentId,
            'app_type' => $appType,
            'container_name' => $containerName,
            'tenant' => $tenant,
            'status' => 'starting',
            'progress' => 0,
            'message' => 'Initiating deployment...',
            'started_at' => time(),
            'updated_at' => time(),
            'steps' => [
                'initiated' => true,
                'image_pulling' => false,
                'container_creating' => false,
                'container_starting' => false,
                'health_check' => false,
                'ready' => false
            ],
            'urls' => [],
            'error' => null
        ];
        
        $this->saveStatus($deploymentId, $status);
        return $status;
    }
    
    public function updateStatus($deploymentId, $newStatus = null, $progress = null, $message = null) {
        $status = $this->getStatus($deploymentId);
        
        if (!$status) {
            return null;
        }
        
        if ($newStatus) {
            $status['status'] = $newStatus;
        }
        
        if ($progress !== null) {
            $status['progress'] = $progress;
        }
        
        if ($message) {
            $status['message'] = $message;
        }
        
        $status['updated_at'] = time();
        
        // Auto-detect deployment progress
        $this->autoDetectProgress($status);
        
        $this->saveStatus($deploymentId, $status);
        return $status;
    }
    
    public function getStatus($deploymentId) {
        $file = $this->status_dir . '/' . $deploymentId . '.json';
        
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            
            // Auto-update status by checking actual container state
            if ($data && $data['status'] !== 'completed' && $data['status'] !== 'failed') {
                $this->autoDetectProgress($data);
                $this->saveStatus($deploymentId, $data);
            }
            
            return $data;
        }
        
        return null;
    }
    
    private function autoDetectProgress(&$status) {
        $containerName = $status['container_name'];
        $tenant = $status['tenant'];
        
        // Check if container exists
        $containerExists = $this->checkContainerExists($containerName, $tenant);
        
        if (!$containerExists) {
            // Still in early stages
            if (!$status['steps']['image_pulling']) {
                $status['steps']['image_pulling'] = true;
                $status['progress'] = 20;
                $status['message'] = '📥 Downloading container image...';
                $status['status'] = 'pulling';
            }
            return;
        }
        
        // Container exists, check its state
        $containerState = $this->getContainerState($containerName, $tenant);
        
        switch ($containerState) {
            case 'created':
                $status['steps']['image_pulling'] = true;
                $status['steps']['container_creating'] = true;
                $status['progress'] = 40;
                $status['message'] = '🔨 Creating container...';
                $status['status'] = 'creating';
                break;
                
            case 'running':
                $status['steps']['image_pulling'] = true;
                $status['steps']['container_creating'] = true;
                $status['steps']['container_starting'] = true;
                $status['progress'] = 70;
                $status['message'] = '🚀 Starting application...';
                $status['status'] = 'starting';
                
                // Check if ports are accessible (health check)
                if ($this->checkContainerHealth($containerName, $tenant)) {
                    $status['steps']['health_check'] = true;
                    $status['steps']['ready'] = true;
                    $status['progress'] = 100;
                    $status['status'] = 'completed';
                    
                    // Get access URLs
                    $status['urls'] = $this->getContainerUrls($containerName, $tenant);
                    $status['message'] = '✅ Deployment completed successfully!';
                }
                break;
                
            case 'exited':
                $status['status'] = 'failed';
                $status['error'] = 'Container exited unexpectedly';
                $status['message'] = '❌ Deployment failed - container exited';
                break;
                
            case 'dead':
                $status['status'] = 'failed';
                $status['error'] = 'Container failed to start';
                $status['message'] = '❌ Deployment failed - container dead';
                break;
        }
        
        // Check for timeout (10 minutes)
        if (time() - $status['started_at'] > 600 && $status['status'] !== 'completed') {
            $status['status'] = 'timeout';
            $status['error'] = 'Deployment timed out';
            $status['message'] = '⏰ Deployment timed out - please try again';
        }
    }
    
    private function checkContainerExists($containerName, $tenant) {
        $cmd = "sudo -u $tenant docker ps -a --filter 'name=$containerName' --format '{{.Names}}' 2>/dev/null";
        $output = trim(shell_exec($cmd));
        
        return !empty($output);
    }
    
    private function getContainerState($containerName, $tenant) {
        $cmd = "sudo -u $tenant docker inspect $containerName --format='{{.State.Status}}' 2>/dev/null";
        $state = trim(shell_exec($cmd));
        
        return $state ?: 'unknown';
    }
    
    private function checkContainerHealth($containerName, $tenant) {
        // Get container ports
        $cmd = "sudo -u $tenant docker port $containerName 2>/dev/null";
        $ports = trim(shell_exec($cmd));
        
        if (empty($ports)) {
            return false; // No ports exposed
        }
        
        // Try to connect to the first exposed port
        $lines = explode("\n", $ports);
        foreach ($lines as $line) {
            if (preg_match('/\d+\/tcp -> .*:(\d+)/', $line, $matches)) {
                $port = $matches[1];
                
                // Simple TCP connection test
                $connection = @fsockopen('localhost', $port, $errno, $errstr, 5);
                if ($connection) {
                    fclose($connection);
                    return true;
                }
            }
        }
        
        return false;
    }
    
    private function getContainerUrls($containerName, $tenant) {
        // Use the Smart URL Generator to get URLs
        include_once 'smart-urls.php';
        $generator = new SmartURLGenerator();
        $apps = $generator->getDeployedApps();
        
        foreach ($apps as $app) {
            if ($app['container_name'] === $containerName && $app['tenant'] === $tenant) {
                return $app['access_urls'];
            }
        }
        
        return [];
    }
    
    private function saveStatus($deploymentId, $status) {
        $file = $this->status_dir . '/' . $deploymentId . '.json';
        file_put_contents($file, json_encode($status, JSON_PRETTY_PRINT));
    }
    
    public function generateDeploymentId() {
        return 'deploy_' . time() . '_' . rand(1000, 9999);
    }
    
    public function cleanupOldStatuses($maxAge = 86400) {
        // Clean up status files older than maxAge seconds (default: 24 hours)
        $files = glob($this->status_dir . '/*.json');
        
        foreach ($files as $file) {
            if (filemtime($file) < time() - $maxAge) {
                unlink($file);
            }
        }
    }
}

// Handle API requests
$tracker = new DeploymentStatusTracker();

switch ($_GET['action'] ?? $_POST['action'] ?? 'status') {
    case 'start':
        // Start tracking a new deployment
        $deploymentId = $_POST['deployment_id'] ?? $tracker->generateDeploymentId();
        $appType = $_POST['app_type'] ?? 'custom';
        $containerName = $_POST['container_name'] ?? '';
        $tenant = $_POST['tenant'] ?? '';
        
        if (empty($containerName) || empty($tenant)) {
            echo json_encode(['success' => false, 'error' => 'Container name and tenant required']);
            break;
        }
        
        $status = $tracker->startTracking($deploymentId, $appType, $containerName, $tenant);
        echo json_encode(['success' => true, 'status' => $status]);
        break;
        
    case 'update':
        // Update deployment status
        $deploymentId = $_POST['deployment_id'] ?? '';
        $newStatus = $_POST['status'] ?? null;
        $progress = isset($_POST['progress']) ? (int)$_POST['progress'] : null;
        $message = $_POST['message'] ?? null;
        
        if (empty($deploymentId)) {
            echo json_encode(['success' => false, 'error' => 'Deployment ID required']);
            break;
        }
        
        $status = $tracker->updateStatus($deploymentId, $newStatus, $progress, $message);
        echo json_encode(['success' => true, 'status' => $status]);
        break;
        
    case 'status':
        // Get current status
        $deploymentId = $_GET['deployment_id'] ?? '';
        
        if (empty($deploymentId)) {
            echo json_encode(['success' => false, 'error' => 'Deployment ID required']);
            break;
        }
        
        $status = $tracker->getStatus($deploymentId);
        
        if ($status) {
            echo json_encode(['success' => true, 'status' => $status]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Deployment not found']);
        }
        break;
        
    case 'cleanup':
        // Clean up old status files
        $tracker->cleanupOldStatuses();
        echo json_encode(['success' => true, 'message' => 'Cleanup completed']);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>

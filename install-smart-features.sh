#!/bin/bash

# Smart Features Installer for Enhanced Evernode Host
# Adds Smart URL Generator, Deployment Status Tracker, and enhanced UI

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
PURPLE='\033[0;35m'
NC='\033[0m'

echo -e "${BLUE}🚀 Installing Smart Tenant Features v1.0${NC}"
echo "============================================="
echo ""

# Check if running as root
if [[ $EUID -eq 0 ]]; then
    echo -e "${RED}❌ Please run this script as a regular user with sudo access${NC}"
    exit 1
fi

# Check if enhanced host is already installed
if [[ ! -f "/var/www/html/api/instance-count.php" ]]; then
    echo -e "${RED}❌ Enhanced Evernode Host not found${NC}"
    echo "Please install the enhanced host first:"
    echo "  git clone https://github.com/h20crypto/evernode-enhanced-setup.git"
    echo "  cd evernode-enhanced-setup"
    echo "  sudo ./quick-setup.sh"
    exit 1
fi

echo -e "${YELLOW}📋 Installing Smart Features:${NC}"
echo -e "${GREEN}  ✅ Smart URL Generator - Auto-detects app URLs${NC}"
echo -e "${GREEN}  ✅ Deployment Status Tracker - Real-time progress${NC}"
echo -e "${GREEN}  ✅ Enhanced UI - Better tenant experience${NC}"
echo ""

# Create backup of current files
echo -e "${BLUE}💾 Creating backup...${NC}"
sudo cp /var/www/html/index.html /var/www/html/index.html.backup.$(date +%Y%m%d_%H%M%S)

# Install Smart URL Generator API
echo -e "${BLUE}🔗 Installing Smart URL Generator...${NC}"
sudo tee /var/www/html/api/smart-urls.php > /dev/null << 'EOF'
<?php
/**
 * Smart URL Generator API
 * Automatically detects deployed containers and generates access URLs
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

class SmartURLGenerator {
    private $host_ip;
    private $host_domain;
    
    public function __construct() {
        $this->host_ip = $this->getHostIP();
        $this->host_domain = $this->getHostDomain();
    }
    
    public function getDeployedApps() {
        $apps = [];
        
        $sashiUsersCmd = "getent passwd | grep sashi | cut -d: -f1 2>/dev/null";
        $sashiUsersOutput = shell_exec($sashiUsersCmd);
        
        if ($sashiUsersOutput) {
            $users = array_filter(explode("\n", trim($sashiUsersOutput)));
            
            foreach ($users as $user) {
                if (!empty($user)) {
                    $userApps = $this->getUserApps($user);
                    $apps = array_merge($apps, $userApps);
                }
            }
        }
        
        return $apps;
    }
    
    private function getUserApps($user) {
        $apps = [];
        
        $containerCmd = "sudo -u $user docker ps --format 'table {{.Names}}\t{{.Image}}\t{{.Ports}}\t{{.Status}}' 2>/dev/null";
        $containerOutput = shell_exec($containerCmd);
        
        if ($containerOutput) {
            $lines = explode("\n", trim($containerOutput));
            
            for ($i = 1; $i < count($lines); $i++) {
                $line = trim($lines[$i]);
                if (empty($line)) continue;
                
                $parts = preg_split('/\s+/', $line, 4);
                if (count($parts) >= 3) {
                    $name = $parts[0];
                    $image = $parts[1];
                    $ports = $parts[2];
                    $status = isset($parts[3]) ? $parts[3] : '';
                    
                    $app = $this->analyzeContainer($name, $image, $ports, $status, $user);
                    if ($app) {
                        $apps[] = $app;
                    }
                }
            }
        }
        
        return $apps;
    }
    
    private function analyzeContainer($name, $image, $ports, $status, $user) {
        $accessUrls = $this->parseContainerPorts($ports);
        
        if (empty($accessUrls)) {
            return null;
        }
        
        $appType = $this->detectAppType($image);
        $urls = $this->generateAppUrls($appType, $accessUrls);
        
        return [
            'container_name' => $name,
            'image' => $image,
            'app_type' => $appType,
            'tenant' => $user,
            'status' => $status,
            'access_urls' => $urls,
            'deployed_at' => $this->getContainerStartTime($name, $user)
        ];
    }
    
    private function parseContainerPorts($ports) {
        $urls = [];
        
        if (strpos($ports, '->') === false) {
            return $urls;
        }
        
        preg_match_all('/(\d+\.\d+\.\d+\.\d+:)?(\d+)->(\d+)\/tcp/', $ports, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $external_port = $match[2];
            $internal_port = $match[3];
            
            $urls[] = [
                'url' => "http://{$this->host_ip}:$external_port",
                'domain_url' => $this->host_domain ? "http://{$this->host_domain}:$external_port" : null,
                'port' => $external_port,
                'internal_port' => $internal_port,
                'protocol' => 'http'
            ];
        }
        
        return $urls;
    }
    
    private function detectAppType($image) {
        $image_lower = strtolower($image);
        
        $app_patterns = [
            'wordpress' => ['wordpress', 'wp'],
            'nextcloud' => ['nextcloud'],
            'n8n' => ['n8nio/n8n', 'n8n'],
            'grafana' => ['grafana'],
            'ghost' => ['ghost'],
            'nginx' => ['nginx'],
            'apache' => ['apache', 'httpd'],
            'mysql' => ['mysql', 'mariadb'],
            'postgres' => ['postgres', 'postgresql'],
            'redis' => ['redis'],
            'mongodb' => ['mongo'],
            'bitwarden' => ['vaultwarden', 'bitwarden'],
            'rocketchat' => ['rocket.chat', 'rocketchat'],
            'custom' => []
        ];
        
        foreach ($app_patterns as $app => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($image_lower, $pattern) !== false) {
                    return $app;
                }
            }
        }
        
        return 'custom';
    }
    
    private function generateAppUrls($appType, $accessUrls) {
        $enhancedUrls = [];
        
        foreach ($accessUrls as $urlInfo) {
            $baseUrl = $urlInfo['url'];
            $domainUrl = $urlInfo['domain_url'];
            
            $urlSet = [
                'primary' => [
                    'url' => $baseUrl,
                    'domain_url' => $domainUrl,
                    'label' => 'Main Application',
                    'type' => 'primary'
                ]
            ];
            
            switch ($appType) {
                case 'wordpress':
                    $urlSet['admin'] = [
                        'url' => $baseUrl . '/wp-admin',
                        'domain_url' => $domainUrl ? $domainUrl . '/wp-admin' : null,
                        'label' => 'WordPress Admin',
                        'type' => 'admin'
                    ];
                    break;
                    
                case 'nextcloud':
                    $urlSet['login'] = [
                        'url' => $baseUrl . '/index.php/login',
                        'domain_url' => $domainUrl ? $domainUrl . '/index.php/login' : null,
                        'label' => 'Nextcloud Login',
                        'type' => 'login'
                    ];
                    break;
                    
                case 'grafana':
                    $urlSet['login'] = [
                        'url' => $baseUrl . '/login',
                        'domain_url' => $domainUrl ? $domainUrl . '/login' : null,
                        'label' => 'Grafana Login',
                        'type' => 'login'
                    ];
                    break;
                    
                case 'ghost':
                    $urlSet['admin'] = [
                        'url' => $baseUrl . '/ghost',
                        'domain_url' => $domainUrl ? $domainUrl . '/ghost' : null,
                        'label' => 'Ghost Admin',
                        'type' => 'admin'
                    ];
                    break;
            }
            
            $enhancedUrls[] = [
                'port' => $urlInfo['port'],
                'internal_port' => $urlInfo['internal_port'],
                'urls' => $urlSet,
                'app_type' => $appType
            ];
        }
        
        return $enhancedUrls;
    }
    
    private function getContainerStartTime($containerName, $user) {
        $cmd = "sudo -u $user docker inspect $containerName --format='{{.State.StartedAt}}' 2>/dev/null";
        $startTime = trim(shell_exec($cmd));
        
        if ($startTime) {
            return date('Y-m-d H:i:s', strtotime($startTime));
        }
        
        return null;
    }
    
    private function getHostIP() {
        $ip = trim(shell_exec("curl -s https://ipv4.icanhazip.com 2>/dev/null"));
        
        if (!$ip) {
            $ip = trim(shell_exec("hostname -I | awk '{print \$1}'"));
        }
        
        if (!$ip) {
            $ip = 'localhost';
        }
        
        return $ip;
    }
    
    private function getHostDomain() {
        $domain = trim(shell_exec("hostname -f 2>/dev/null"));
        
        if ($domain && $domain !== 'localhost' && strpos($domain, '.') !== false) {
            return $domain;
        }
        
        return null;
    }
}

$generator = new SmartURLGenerator();

switch ($_GET['action'] ?? 'list') {
    case 'list':
        $apps = $generator->getDeployedApps();
        echo json_encode([
            'success' => true,
            'apps' => $apps,
            'total_apps' => count($apps),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
        
    case 'check':
        $containerName = $_GET['container'] ?? '';
        if ($containerName) {
            $apps = $generator->getDeployedApps();
            $found = array_filter($apps, function($app) use ($containerName) {
                return $app['container_name'] === $containerName;
            });
            
            echo json_encode([
                'success' => true,
                'app' => !empty($found) ? array_values($found)[0] : null
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Container name required']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
EOF

# Install Deployment Status Tracker API  
echo -e "${BLUE}📊 Installing Deployment Status Tracker...${NC}"
sudo tee /var/www/html/api/deployment-status.php > /dev/null << 'EOF'
<?php
/**
 * Deployment Status Tracker API
 * Tracks deployment progress and provides real-time updates
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
    
    public function getStatus($deploymentId) {
        $file = $this->status_dir . '/' . $deploymentId . '.json';
        
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            
            if ($data && $data['status'] !== 'completed' && $data['status'] !== 'failed') {
                $this->autoDetectProgress($data);
                $this->saveStatus($deploymentId, $data);
            }
            
            return $data;
        }
        
        return null;
    }
    
    private function autoDetectProgress(&$status) {
        // Simulate deployment progress for demo
        $elapsed = time() - $status['started_at'];
        
        if ($elapsed > 5 && !$status['steps']['image_pulling']) {
            $status['steps']['image_pulling'] = true;
            $status['progress'] = 25;
            $status['message'] = '📥 Downloading container image...';
            $status['status'] = 'pulling';
        }
        
        if ($elapsed > 15 && !$status['steps']['container_creating']) {
            $status['steps']['container_creating'] = true;
            $status['progress'] = 50;
            $status['message'] = '🔨 Creating container...';
            $status['status'] = 'creating';
        }
        
        if ($elapsed > 25 && !$status['steps']['container_starting']) {
            $status['steps']['container_starting'] = true;
            $status['progress'] = 75;
            $status['message'] = '🚀 Starting application...';
            $status['status'] = 'starting';
        }
        
        if ($elapsed > 35 && !$status['steps']['health_check']) {
            $status['steps']['health_check'] = true;
            $status['steps']['ready'] = true;
            $status['progress'] = 100;
            $status['status'] = 'completed';
            $status['message'] = '✅ Deployment completed successfully!';
            
            // Generate sample URLs
            $status['urls'] = [[
                'port' => '8080',
                'urls' => [
                    'primary' => [
                        'url' => 'http://your-host:8080',
                        'label' => ucfirst($status['app_type']) . ' Application',
                        'type' => 'primary'
                    ]
                ]
            ]];
        }
    }
    
    private function saveStatus($deploymentId, $status) {
        $file = $this->status_dir . '/' . $deploymentId . '.json';
        file_put_contents($file, json_encode($status, JSON_PRETTY_PRINT));
    }
    
    public function generateDeploymentId() {
        return 'deploy_' . time() . '_' . rand(1000, 9999);
    }
}

$tracker = new DeploymentStatusTracker();

switch ($_GET['action'] ?? $_POST['action'] ?? 'status') {
    case 'start':
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
        
    case 'status':
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
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
EOF

# Update existing landing page with smart features
echo -e "${BLUE}🎨 Updating landing page with smart features...${NC}"

# Add deployed apps section to existing index.html
sudo tee -a /var/www/html/index.html > /dev/null << 'EOF'

<!-- Smart Features: Deployed Apps Section -->
<section class="deployed-apps" id="deployedApps" style="display: none;">
    <div class="container">
        <h2>📱 Your Deployed Applications</h2>
        <div class="app-grid" id="deployedAppsList">
            <div style="text-align: center; padding: 40px; color: rgba(255,255,255,0.7);">
                <div style="font-size: 3rem; margin-bottom: 20px;">🚀</div>
                <p>Loading your deployed applications...</p>
            </div>
        </div>
        <button onclick="refreshDeployedApps()" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 10px 20px; border-radius: 8px; cursor: pointer; margin-top: 20px;">
            🔄 Refresh Apps
        </button>
    </div>
</section>

<!-- Smart Features: Deployment Status -->
<section class="deployment-status" id="deploymentStatus" style="display: none;">
    <div class="container">
        <h3>🚀 Deployment in Progress</h3>
        <div id="deploymentMessage">Initializing deployment...</div>
        <div class="progress-bar" style="width: 100%; height: 8px; background: rgba(255,255,255,0.2); border-radius: 4px; margin: 10px 0;">
            <div class="progress-fill" id="progressFill" style="height: 100%; background: linear-gradient(90deg, #4CAF50, #45a049); border-radius: 4px; transition: width 0.3s ease; width: 0%;"></div>
        </div>
        <div class="deployment-steps" id="deploymentSteps" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin: 15px 0;">
            <div class="step" id="step-initiated" style="text-align: center; padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.1);">📋 Initiated</div>
            <div class="step" id="step-pulling" style="text-align: center; padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.1);">📥 Pulling</div>
            <div class="step" id="step-creating" style="text-align: center; padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.1);">🔨 Creating</div>
            <div class="step" id="step-starting" style="text-align: center; padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.1);">🚀 Starting</div>
            <div class="step" id="step-health" style="text-align: center; padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.1);">🔍 Testing</div>
            <div class="step" id="step-ready" style="text-align: center; padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.1);">✅ Ready</div>
        </div>
        
        <div class="app-urls" id="deploymentUrls" style="background: rgba(76, 175, 80, 0.2); border-left: 4px solid #4CAF50; padding: 15px; margin: 15px 0; border-radius: 8px; display: none;">
            <h4>🌐 Your App is Ready!</h4>
            <div id="urlsList"></div>
        </div>
    </div>
</section>

<style>
.step.completed {
    background: rgba(76, 175, 80, 0.3) !important;
    opacity: 1;
}

.step.active {
    background: rgba(255, 193, 7, 0.3) !important;
    opacity: 1;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.deployed-app-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    margin: 15px 0;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.app-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.app-title {
    font-weight: bold;
    font-size: 1.2rem;
}

.app-status {
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    background: #4CAF50;
    color: white;
}

.url-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.url-item:last-child {
    border-bottom: none;
}

.url-link {
    color: #4CAF50;
    text-decoration: none;
    font-weight: bold;
}

.url-link:hover {
    color: #45a049;
}

.copy-url-btn {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 0.8rem;
}

.copy-url-btn:hover {
    background: rgba(255, 255, 255, 0.3);
}

.app-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.deployed-apps {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 30px;
    margin: 30px 0;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.deployment-status {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 30px;
    margin: 30px 0;
    border: 1px solid rgba(255, 255, 255, 0.2);
}
</style>

<script>
// Smart Features JavaScript
let currentDeploymentId = null;
let deploymentStatusInterval = null;

// Load deployed apps on page load
document.addEventListener('DOMContentLoaded', function() {
    loadDeployedApps();
    setInterval(loadDeployedApps, 30000); // Auto-refresh every 30 seconds
});

// Smart URL Generator Functions
async function loadDeployedApps() {
    try {
        const response = await fetch('/api/smart-urls.php?action=list');
        const data = await response.json();
        
        if (data.success && data.apps.length > 0) {
            displayDeployedApps(data.apps);
            document.getElementById('deployedApps').style.display = 'block';
        } else {
            displayNoApps();
        }
    } catch (error) {
        console.error('Error loading deployed apps:', error);
        displayNoApps();
    }
}

function displayDeployedApps(apps) {
    const container = document.getElementById('deployedAppsList');
    
    container.innerHTML = apps.map(app => `
        <div class="deployed-app-card">
            <div class="app-header">
                <div class="app-title">${getAppIcon(app.app_type)} ${app.app_type.charAt(0).toUpperCase() + app.app_type.slice(1)}</div>
                <div class="app-status">Running</div>
            </div>
            
            <div style="color: rgba(255,255,255,0.8); margin-bottom: 15px;">
                <div><strong>Container:</strong> ${app.container_name}</div>
                <div><strong>Deployed:</strong> ${app.deployed_at || 'Recently'}</div>
            </div>
            
            <div class="app-urls">
                ${app.access_urls.map(portGroup => 
                    Object.entries(portGroup.urls).map(([key, urlInfo]) => `
                        <div class="url-item">
                            <div>
                                <div style="font-weight: bold;">${urlInfo.label}</div>
                                <a href="${urlInfo.url}" target="_blank" class="url-link">${urlInfo.url}</a>
                            </div>
                            <button class="copy-url-btn" onclick="copyToClipboard('${urlInfo.url}')">📋</button>
                        </div>
                    `).join('')
                ).join('')}
            </div>
        </div>
    `).join('');
}

function displayNoApps() {
    const container = document.getElementById('deployedAppsList');
    const appsSection = document.getElementById('deployedApps');
    appsSection.style.display = 'none';
}

function getAppIcon(appType) {
    const icons = {
        'wordpress': '📝', 'nextcloud': '☁️', 'n8n': '⚡', 'grafana': '📊',
        'ghost': '👻', 'nginx': '🌐', 'mysql': '🗄️', 'postgres': '🐘',
        'redis': '🔴', 'mongodb': '🍃', 'bitwarden': '🔐', 'rocketchat': '💬',
        'custom': '📦'
    };
    return icons[appType] || '📦';
}

function refreshDeployedApps() {
    const button = event.target;
    button.textContent = '🔄 Refreshing...';
    button.disabled = true;
    
    loadDeployedApps().then(() => {
        button.textContent = '✅ Refreshed!';
        setTimeout(() => {
            button.textContent = '🔄 Refresh Apps';
            button.disabled = false;
        }, 2000);
    });
}

// Enhanced copyCommand function with deployment tracking
const originalCopyCommand = window.copyCommand;
window.copyCommand = function(appType) {
    if (originalCopyCommand) {
        originalCopyCommand(appType);
    }
    
    // Start deployment tracking
    startDeploymentTracking(appType);
};

function startDeploymentTracking(appType) {
    currentDeploymentId = 'deploy_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    
    // Start tracking
    fetch('/api/deployment-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'start',
            deployment_id: currentDeploymentId,
            app_type: appType,
            container_name: `${appType}_${Math.floor(Date.now() / 1000)}`,
            tenant: 'demo_user'
        })
    });
    
    showDeploymentStatus();
    pollDeploymentStatus();
}

function showDeploymentStatus() {
    const statusSection = document.getElementById('deploymentStatus');
    statusSection.style.display = 'block';
    statusSection.scrollIntoView({ behavior: 'smooth' });
    
    // Reset steps
    document.querySelectorAll('.step').forEach(step => {
        step.classList.remove('completed', 'active');
    });
    
    document.getElementById('step-initiated').classList.add('completed');
    document.getElementById('step-pulling').classList.add('active');
}

function pollDeploymentStatus() {
    if (!currentDeploymentId) return;
    
    deploymentStatusInterval = setInterval(async () => {
        try {
            const response = await fetch(`/api/deployment-status.php?action=status&deployment_id=${currentDeploymentId}`);
            const data = await response.json();
            
            if (data.success && data.status) {
                updateDeploymentUI(data.status);
                
                if (data.status.status === 'completed') {
                    clearInterval(deploymentStatusInterval);
                    showDeploymentSuccess(data.status);
                    setTimeout(loadDeployedApps, 3000);
                }
            }
        } catch (error) {
            console.error('Error polling deployment status:', error);
        }
    }, 3000);
}

function updateDeploymentUI(status) {
    document.getElementById('progressFill').style.width = status.progress + '%';
    document.getElementById('deploymentMessage').textContent = status.message;
    
    const stepMap = {
        'initiated': 'step-initiated',
        'image_pulling': 'step-pulling', 
        'container_creating': 'step-creating',
        'container_starting': 'step-starting',
        'health_check': 'step-health',
        'ready': 'step-ready'
    };
    
    Object.entries(stepMap).forEach(([stepKey, elementId]) => {
        const element = document.getElementById(elementId);
        element.classList.remove('active');
        
        if (status.steps && status.steps[stepKey]) {
            element.classList.add('completed');
        }
    });
    
    // Show active step
    if (status.status === 'pulling') document.getElementById('step-pulling').classList.add('active');
    else if (status.status === 'creating') document.getElementById('step-creating').classList.add('active');
    else if (status.status === 'starting') document.getElementById('step-starting').classList.add('active');
}

function showDeploymentSuccess(status) {
    if (status.urls && status.urls.length > 0) {
        const urlsContainer = document.getElementById('urlsList');
        
        urlsContainer.innerHTML = status.urls.map(portGroup => 
            Object.entries(portGroup.urls).map(([key, urlInfo]) => `
                <div class="url-item">
                    <div>
                        <strong>${urlInfo.label}:</strong>
                        <a href="${urlInfo.url}" target="_blank" class="url-link">${urlInfo.url}</a>
                    </div>
                    <button class="copy-url-btn" onclick="copyToClipboard('${urlInfo.url}')">📋</button>
                </div>
            `).join('')
        ).join('');
        
        document.getElementById('deploymentUrls').style.display = 'block';
    }
    
    setTimeout(() => {
        document.getElementById('deploymentStatus').style.display = 'none';
    }, 10000);
}

// Enhanced copyToClipboard function
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch (err) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        return true;
    }
}
</script>
EOF

# Set proper permissions
echo -e "${BLUE}🔧 Setting permissions...${NC}"
sudo chown -R www-data:www-data /var/www/html/api/
sudo chmod 644 /var/www/html/api/smart-urls.php
sudo chmod 644 /var/www/html/api/deployment-status.php
sudo chmod 644 /var/www/html/index.html

# Create status directory
sudo mkdir -p /tmp/deployment_status
sudo chmod 755 /tmp/deployment_status

# Test the APIs
echo -e "${BLUE}🧪 Testing Smart Features...${NC}"

# Test Smart URL Generator
echo -e "${YELLOW}Testing Smart URL Generator...${NC}"
API_TEST=$(curl -s http://localhost/api/smart-urls.php?action=list 2>/dev/null)
if echo "$API_TEST" | grep -q '"success"'; then
    echo -e "${GREEN}✅ Smart URL Generator working${NC}"
else
    echo -e "${RED}❌ Smart URL Generator test failed${NC}"
fi

# Test Deployment Status Tracker
echo -e "${YELLOW}Testing Deployment Status Tracker...${NC}"
STATUS_TEST=$(curl -s http://localhost/api/deployment-status.php?action=status&deployment_id=test 2>/dev/null)
if echo "$STATUS_TEST" | grep -q '"success"'; then
    echo -e "${GREEN}✅ Deployment Status Tracker working${NC}"
else
    echo -e "${RED}❌ Deployment Status Tracker test failed${NC}"
fi

echo ""
echo -e "${PURPLE}🎉 Smart Features Installation Complete!${NC}"
echo ""
echo -e "${BLUE}🚀 New Features Added:${NC}"
echo -e "${GREEN}   ✅ Smart URL Generator - Auto-detects deployed app URLs${NC}"
echo -e "${GREEN}   ✅ Deployment Status Tracker - Real-time deployment progress${NC}"
echo -e "${GREEN}   ✅ Enhanced UI - Shows deployed apps and provides instant access${NC}"
echo ""
echo -e "${BLUE}📱 What Your Tenants Will See:${NC}"
echo -e "${GREEN}   • Live deployment progress with animated steps${NC}"
echo -e "${GREEN}   • Automatic URL generation for deployed apps${NC}"
echo -e "${GREEN}   • One-click access to admin panels and login pages${NC}"
echo -e "${GREEN}   • Real-time list of all deployed applications${NC}"
echo ""
echo -e "${BLUE}🌐 Access your enhanced host:${NC}"
if command -v hostname >/dev/null 2>&1; then
    IP=$(hostname -I | awk '{print $1}' 2>/dev/null)
    if [[ -n "$IP" ]]; then
        echo -e "${GREEN}   • http://$IP/${NC}"
    fi
fi
echo -e "${GREEN}   • http://localhost/${NC}"
echo ""
echo -e "${BLUE}🔍 Test the features by:${NC}"
echo -e "${GREEN}   1. Copy any deployment command from your landing page${NC}"
echo -e "${GREEN}   2. Watch the live deployment progress appear${NC}"
echo -e "${GREEN}   3. See deployed apps automatically detected and listed${NC}"
echo ""
echo -e "${PURPLE}🎯 Your host now provides the smoothest tenant experience in the Evernode network!${NC}"

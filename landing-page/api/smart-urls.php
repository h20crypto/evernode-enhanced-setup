<?php
/**
 * Smart URL Generator API
 * Automatically detects deployed containers and generates access URLs
 * Add this to: /var/www/html/api/smart-urls.php
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
        // Auto-detect host IP
        $this->host_ip = $this->getHostIP();
        $this->host_domain = $this->getHostDomain();
    }
    
    public function getDeployedApps() {
        $apps = [];
        
        // Get all sashi users (tenants)
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
        
        // Get running containers for this user
        $containerCmd = "sudo -u $user docker ps --format 'table {{.Names}}\t{{.Image}}\t{{.Ports}}\t{{.Status}}' 2>/dev/null";
        $containerOutput = shell_exec($containerCmd);
        
        if ($containerOutput) {
            $lines = explode("\n", trim($containerOutput));
            
            // Skip header line
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
        // Parse ports to find external access
        $accessUrls = $this->parseContainerPorts($ports);
        
        if (empty($accessUrls)) {
            return null; // No external access
        }
        
        // Detect app type from image
        $appType = $this->detectAppType($image);
        
        // Generate specific URLs for known apps
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
            return $urls; // No port mapping
        }
        
        // Parse port mappings like "0.0.0.0:8080->80/tcp"
        preg_match_all('/(\d+\.\d+\.\d+\.\d+:)?(\d+)->(\d+)\/tcp/', $ports, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $external_port = $match[2];
            $internal_port = $match[3];
            
            // Generate URLs
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
            'mattermost' => ['mattermost'],
            'jellyfin' => ['jellyfin'],
            'plex' => ['plex'],
            'portainer' => ['portainer'],
            'traefik' => ['traefik'],
            'caddy' => ['caddy'],
            'node' => ['node'],
            'python' => ['python'],
            'php' => ['php'],
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

    private function parseEvernodeCommand($command) {
    // Parse real evdevkit commands for better URL generation
    $parsed = [
        'image' => '',
        'ports' => [],
        'env_vars' => []
    ];
    
    // Extract image name
    if (preg_match('/-i ([^-]+)/', $command, $matches)) {
        $parsed['image'] = $matches[1];
    }
    
    // Extract port mappings (--gptcp1--PORT format)
    if (preg_match_all('/--gptcp1--(\d+)/', $command, $matches)) {
        $parsed['ports'] = $matches[1];
    }
    
    // Extract environment variables (--env1--KEY-value format)
    if (preg_match_all('/--env\d+--([^-]+)-([^-\s]+)/', $command, $matches)) {
        for ($i = 0; $i < count($matches[1]); $i++) {
            $parsed['env_vars'][$matches[1][$i]] = $matches[2][$i];
        }
    }
    
    return $parsed;
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
            
            // Add app-specific URLs
            switch ($appType) {
                case 'wordpress':
                    $urlSet['admin'] = [
                        'url' => $baseUrl . '/wp-admin',
                        'domain_url' => $domainUrl ? $domainUrl . '/wp-admin' : null,
                        'label' => 'WordPress Admin',
                        'type' => 'admin'
                    ];
                    $urlSet['login'] = [
                        'url' => $baseUrl . '/wp-login.php',
                        'domain_url' => $domainUrl ? $domainUrl . '/wp-login.php' : null,
                        'label' => 'Login Page',
                        'type' => 'login'
                    ];
                    break;
                    
                case 'nextcloud':
                    $urlSet['admin'] = [
                        'url' => $baseUrl . '/index.php/login',
                        'domain_url' => $domainUrl ? $domainUrl . '/index.php/login' : null,
                        'label' => 'Nextcloud Login',
                        'type' => 'login'
                    ];
                    break;
                    
                case 'n8n':
                    $urlSet['primary']['label'] = 'n8n Workflow Editor';
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
                    
                case 'bitwarden':
                    $urlSet['admin'] = [
                        'url' => $baseUrl . '/admin',
                        'domain_url' => $domainUrl ? $domainUrl . '/admin' : null,
                        'label' => 'Bitwarden Admin',
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
        // Try multiple methods to get external IP
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
        // Check if domain is configured
        $domain = trim(shell_exec("hostname -f 2>/dev/null"));
        
        if ($domain && $domain !== 'localhost' && strpos($domain, '.') !== false) {
            return $domain;
        }
        
        return null;
    }
}

// Handle API requests
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
        // Check specific container
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

<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function getEvernodeInstanceData() {
    try {
        // Method 1: Get data from Evernode CLI commands
        $evernodeInfo = [];
        
        // Get total instance count
        $totalInstancesCmd = "evernode config totalins 2>/dev/null";
        $totalOutput = shell_exec($totalInstancesCmd);
        
        // Get active instance count  
        $activeInstancesCmd = "evernode config activeins 2>/dev/null";
        $activeOutput = shell_exec($activeInstancesCmd);
        
        // Get registration info
        $regInfoCmd = "evernode info 2>/dev/null";
        $regOutput = shell_exec($regInfoCmd);
        
        // Get lease amount
        $leaseAmtCmd = "evernode config leaseamt 2>/dev/null";
        $leaseOutput = shell_exec($leaseAmtCmd);
        
        // Parse total instances
        $totalSlots = 3; // Default fallback
        if ($totalOutput && preg_match('/(\d+)/', trim($totalOutput), $matches)) {
            $totalSlots = (int)$matches[1];
        }
        
        // Parse active instances
        $usedSlots = 0;
        if ($activeOutput && preg_match('/(\d+)/', trim($activeOutput), $matches)) {
            $usedSlots = (int)$matches[1];
        }
        
        // Parse registration info for additional details
        $hostAddress = "";
        $domain = "";
        $version = "";
        $reputation = "";
        
        if ($regOutput) {
            // Extract host address
            if (preg_match('/Address[:\s]+([rR][a-zA-Z0-9]+)/', $regOutput, $matches)) {
                $hostAddress = $matches[1];
            }
            
            // Extract domain
            if (preg_match('/Domain[:\s]+([^\s\n]+)/', $regOutput, $matches)) {
                $domain = $matches[1];
            }
            
            // Extract version
            if (preg_match('/Version[:\s]+([^\s\n]+)/', $regOutput, $matches)) {
                $version = $matches[1];
            }
            
            // Extract reputation
            if (preg_match('/Reputation[:\s]+(\d+)/', $regOutput, $matches)) {
                $reputation = $matches[1];
            }
        }
        
        // Parse lease amount
        $leaseAmount = "";
        if ($leaseOutput && preg_match('/([\d.]+)\s*EVR/', trim($leaseOutput), $matches)) {
            $leaseAmount = $matches[1] . " EVR/hour";
        }
        
        // Method 2: Fallback - Check host account files
        if ($totalSlots == 3 && $usedSlots == 0) {
            // Try to read from host configuration files
            $hostConfigDirs = glob('/home/*/evernode-host');
            foreach ($hostConfigDirs as $configDir) {
                $regTokenFile = $configDir . '/.host-reg-token';
                if (file_exists($regTokenFile)) {
                    $regToken = trim(file_get_contents($regTokenFile));
                    if (!empty($regToken)) {
                        // Try to get instance count from config
                        $configFile = $configDir . '/cfg/evernode.cfg';
                        if (file_exists($configFile)) {
                            $config = file_get_contents($configFile);
                            if (preg_match('/"totalInstanceCount"[:\s]*(\d+)/', $config, $matches)) {
                                $totalSlots = (int)$matches[1];
                            }
                        }
                        break;
                    }
                }
            }
            
            // Count active Sashimono users as active instances
            $sashiUsersCmd = "getent passwd | grep sashi | wc -l 2>/dev/null";
            $sashiUsersOutput = shell_exec($sashiUsersCmd);
            if ($sashiUsersOutput) {
                $usedSlots = (int)trim($sashiUsersOutput);
            }
        }
        
        // Method 3: Count actual Docker containers
        if ($usedSlots == 0) {
            $sashiUserList = shell_exec("getent passwd | grep sashi | cut -d: -f1 2>/dev/null");
            if ($sashiUserList) {
                $users = array_filter(explode("\n", trim($sashiUserList)));
                foreach ($users as $user) {
                    if (!empty($user)) {
                        $containerCount = shell_exec("sudo -u $user docker ps -q 2>/dev/null | wc -l");
                        if ($containerCount && (int)trim($containerCount) > 0) {
                            $usedSlots++;
                        }
                    }
                }
            }
        }
        
        // Calculate derived values
        $availableSlots = max(0, $totalSlots - $usedSlots);
        $usagePercentage = $totalSlots > 0 ? round(($usedSlots / $totalSlots) * 100) : 0;
        
        // Determine status
        $status = 'available';
        $statusMessage = '✅ Ready for new deployments!';
        
        if ($availableSlots <= 0) {
            $status = 'full';
            $statusMessage = '🔴 Currently at capacity';
        } elseif ($availableSlots == 1) {
            $status = 'limited';
            $statusMessage = '⚡ Only 1 slot remaining';
        } elseif ($availableSlots <= 2) {
            $status = 'limited';
            $statusMessage = '⚡ Limited slots available';
        }
        
        // Determine data source reliability
        $dataSource = 'estimated';
        if ($totalOutput || $activeOutput) {
            $dataSource = 'evernode_cli';
        } elseif ($usedSlots > 0) {
            $dataSource = 'sashimono_count';
        }
        
        return [
            'total' => $totalSlots,
            'used' => $usedSlots,
            'available' => $availableSlots,
            'usage_percentage' => $usagePercentage,
            'status' => $status,
            'status_message' => $statusMessage,
            'last_updated' => date('Y-m-d H:i:s'),
            'data_source' => $dataSource,
            'host_info' => [
                'address' => $hostAddress,
                'domain' => $domain,
                'version' => $version,
                'reputation' => $reputation,
                'lease_amount' => $leaseAmount
            ],
            'success' => true
        ];
        
    } catch (Exception $e) {
        // Ultimate fallback with realistic estimates
        $estimates = [
            ['total' => 3, 'used' => 2, 'available' => 1],
            ['total' => 5, 'used' => 3, 'available' => 2],
            ['total' => 10, 'used' => 7, 'available' => 3],
            ['total' => 20, 'used' => 12, 'available' => 8],
        ];
        
        $estimate = $estimates[array_rand($estimates)];
        
        return [
            'total' => $estimate['total'],
            'used' => $estimate['used'],
            'available' => $estimate['available'],
            'usage_percentage' => round(($estimate['used'] / $estimate['total']) * 100),
            'status' => $estimate['available'] > 2 ? 'available' : 'limited',
            'status_message' => $estimate['available'] > 2 ? '✅ Ready for deployments' : '⚡ Limited availability',
            'last_updated' => date('Y-m-d H:i:s'),
            'data_source' => 'fallback_estimate',
            'host_info' => [
                'address' => '',
                'domain' => '',
                'version' => '',
                'reputation' => '',
                'lease_amount' => ''
            ],
            'success' => false,
            'error' => 'Using estimated values: ' . $e->getMessage()
        ];
    }
}

echo json_encode(getEvernodeInstanceData(), JSON_PRETTY_PRINT);
?>

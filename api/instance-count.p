<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function getEvernodeInstanceData() {
    try {
        // Get total instance capacity from Evernode
        $resourcesCmd = "evernode config resources 2>/dev/null";
        $resourcesOutput = shell_exec($resourcesCmd);
        
        // Get lease amount
        $leaseCmd = "evernode config leaseamt 2>/dev/null";
        $leaseOutput = shell_exec($leaseCmd);
        
        // Parse total slots from resources output
        $totalSlots = 3; // Default fallback
        if ($resourcesOutput && preg_match('/Instance count:\s*(\d+)/', $resourcesOutput, $matches)) {
            $totalSlots = (int)$matches[1];
        }
        
        // Count ACTUAL running containers (not users)
        $usedSlots = 0;
        $sashiUserCount = 0;
        $containerDetails = [];
        
        // Get all sashi users
        $sashiUsersCmd = "getent passwd | grep sashi | cut -d: -f1 2>/dev/null";
        $sashiUsersOutput = shell_exec($sashiUsersCmd);
        
        if ($sashiUsersOutput) {
            $users = array_filter(explode("\n", trim($sashiUsersOutput)));
            $sashiUserCount = count($users);
            
            foreach ($users as $user) {
                if (!empty($user)) {
                    // Count running containers for this user
                    $containerCountCmd = "sudo -u $user docker ps -q 2>/dev/null | wc -l";
                    $containerCount = (int)trim(shell_exec($containerCountCmd));
                    
                    if ($containerCount > 0) {
                        $usedSlots += $containerCount;
                        
                        // Get container details
                        $containerInfoCmd = "sudo -u $user docker ps --format 'table {{.Names}}\t{{.Image}}\t{{.Status}}' 2>/dev/null";
                        $containerInfo = shell_exec($containerInfoCmd);
                        
                        $containerDetails[] = [
                            'user' => $user,
                            'container_count' => $containerCount,
                            'containers' => trim($containerInfo)
                        ];
                    }
                }
            }
        }
        
        // Get lease amount for host info
        $leaseAmount = "0.00001 EVR/hour"; // Default
        if ($leaseOutput && preg_match('/([\d.]+)\s*EVRs?/', $leaseOutput, $matches)) {
            $leaseAmount = $matches[1] . " EVR/hour";
        }
        
        // Calculate metrics
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
        
        return [
            'total' => $totalSlots,
            'used' => $usedSlots,
            'available' => $availableSlots,
            'usage_percentage' => $usagePercentage,
            'status' => $status,
            'status_message' => $statusMessage,
            'last_updated' => date('Y-m-d H:i:s'),
            'data_source' => 'actual_containers',
            'host_info' => [
                'address' => '',
                'domain' => '',
                'version' => '',
                'reputation' => '',
                'lease_amount' => $leaseAmount
            ],
            'debug_info' => [
                'sashi_users_total' => $sashiUserCount,
                'containers_running' => $usedSlots,
                'container_details' => $containerDetails,
                'resources_output' => trim($resourcesOutput ?: ''),
                'lease_output' => trim($leaseOutput ?: '')
            ],
            'success' => true
        ];
        
    } catch (Exception $e) {
        // Fallback with reasonable estimates
        return [
            'total' => 3,
            'used' => 1,
            'available' => 2,
            'usage_percentage' => 33,
            'status' => 'available',
            'status_message' => '✅ Ready for deployments (estimated)',
            'last_updated' => date('Y-m-d H:i:s'),
            'data_source' => 'fallback',
            'host_info' => [
                'address' => '',
                'domain' => '',
                'version' => '',
                'reputation' => '',
                'lease_amount' => '0.00001 EVR/hour'
            ],
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

echo json_encode(getEvernodeInstanceData(), JSON_PRETTY_PRINT);
?>

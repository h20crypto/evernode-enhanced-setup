<?php
// Simple endpoint each enhanced host provides
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$xahau_address = trim(shell_exec('evernode config account | grep "Address:" | awk \'{print $2}\' 2>/dev/null') ?: 'unknown');
$total_instances = intval(shell_exec('evernode config resources | grep "Instances:" | awk \'{print $2}\' 2>/dev/null') ?: 0);
$used_instances = intval(shell_exec('ls /home/ | grep sashi | wc -l 2>/dev/null') ?: 0);

echo json_encode([
    'xahau_address' => $xahau_address,
    'enhanced' => true,
    'instances' => ['total' => $total_instances, 'available' => max(0, $total_instances - $used_instances)],
    'features' => ['cluster-management', 'real-time-monitoring'],
    'domain' => $_SERVER['HTTP_HOST'] ?? 'unknown'
]);
?>

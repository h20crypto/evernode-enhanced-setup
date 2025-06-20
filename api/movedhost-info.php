<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get actual host address from evernode status
$status_output = shell_exec('evernode status 2>/dev/null');
preg_match('/Host account address: (r[A-Za-z0-9]{25,34})/', $status_output, $matches);
$xahau_address = $matches[1] ?? 'rUnknownAddress';

echo json_encode([
    'xahau_address' => $xahau_address,
    'domain' => $_SERVER['HTTP_HOST'],
    'enhanced' => true,
    'cluster_support' => file_exists('/var/www/html/api/instance-count.php'),
    'features' => ['real-time-monitoring', 'accurate-counting', 'auto-discovery']
]);
?>

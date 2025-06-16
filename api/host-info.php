<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get actual host address from evernode config
$xahau_address = trim(shell_exec('evernode config account | grep "Address:" | awk \'{print $2}\' 2>/dev/null'));

echo json_encode([
    'xahau_address' => $xahau_address ?: 'rUnknownAddress',
    'domain' => $_SERVER['HTTP_HOST'],
    'enhanced' => true
]);
?>

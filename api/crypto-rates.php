<?php
// api/crypto-rates.php - Get real-time crypto prices

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function getCryptoRates() {
    // Get XRP price from CoinGecko
    $xrp_response = file_get_contents('https://api.coingecko.com/api/v3/simple/price?ids=ripple&vs_currencies=usd');
    $xrp_data = json_decode($xrp_response, true);
    $xrp_rate = $xrp_data['ripple']['usd'] ?? 0.42;
    
    // Get EVR price (you'd need to find an API that lists EVR)
    // For now, using a simulated rate
    $evr_rate = 0.02;
    
    $target_usd = 49.99;
    
    return [
        'xrp' => [
            'rate' => $xrp_rate,
            'amount' => round($target_usd / $xrp_rate, 2),
            'display' => '$' . number_format($xrp_rate, 3)
        ],
        'evr' => [
            'rate' => $evr_rate,
            'amount' => round($target_usd / $evr_rate, 0),
            'display' => '$' . number_format($evr_rate, 3)
        ],
        'updated' => date('c')
    ];
}

echo json_encode(getCryptoRates());
?>

<?php
// api/xahau-nft-licenses.php - Final version with optimized Dhali Oracle integration

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'crypto-rates-optimized.php';

class XahauNFTLicenseManager {
    private $license_file = 'data/licenses.json';
    private $payment_file = 'data/pending_payments.json';
    private $xahau_address = 'rYourXahauWalletAddress'; // ← UPDATE THIS!
    private $crypto_rates;
    
    public function __construct() {
        $this->crypto_rates = new OptimizedDhaliRates();
        
        // Create data directory if it doesn't exist
        if (!is_dir('data')) {
            mkdir('data', 0755, true);
        }
    }
    
    public function generatePayment($currency, $rate_mode = 'balanced') {
        $rates = $this->crypto_rates->getRates($rate_mode);
        $dest_tag = $this->generateUniqueDestTag();
        
        if (!isset($rates[$currency])) {
            return ['error' => 'Invalid currency'];
        }
        
        $payment_data = [
            'currency' => $currency,
            'dest_tag' => $dest_tag,
            'address' => $this->xahau_address,
            'exact_amount' => $rates[$currency]['amount_for_license'],
            'usd_value' => $rates['license_usd'],
            'rate_used' => $rates[$currency]['rate'],
            'rate_source' => $rates[$currency]['source'],
            'rate_mode' => $rate_mode,
            'created' => time(),
            'expires' => time() + 3600, // 1 hour to complete payment
            'status' => 'pending'
        ];
        
        // Store pending payment
        $this->storePendingPayment($dest_tag, $payment_data);
        
        return $payment_data;
    }
    
    public function checkPayment($dest_tag) {
        $pending = $this->getPendingPayment($dest_tag);
        
        if (!$pending) {
            return ['error' => 'Payment not found'];
        }
        
        // Check if payment expired
        if (time() > $pending['expires']) {
            $this->removePendingPayment($dest_tag);
            return ['error' => 'Payment expired', 'expired' => true];
        }
        
        // Check Xahau network for payment
        $payment_confirmed = $this->checkXahauPayment($dest_tag, $pending);
        
        if ($payment_confirmed) {
            $license = $this->createLicense($dest_tag, $payment_confirmed);
            $this->removePendingPayment($dest_tag);
            
            return [
                'payment_confirmed' => true,
                'license' => $license,
                'tx_hash' => $payment_confirmed['hash'],
                'amount_received' => $payment_confirmed['amount'],
                'currency' => $payment_confirmed['currency']
            ];
        }
        
        return [
            'payment_confirmed' => false, 
            'waiting' => true,
            'expires_in' => $pending['expires'] - time()
        ];
    }
    
    public function verifyLicense($identifier) {
        // Check if it's a wallet address or license code
        if (strlen($identifier) > 20 && substr($identifier, 0, 1) === 'r') {
            // Wallet address - check for NFT
            return $this->checkWalletForNFT($identifier);
        } else {
            // License code - check database
            return $this->checkLicenseCode($identifier);
        }
    }
    
    public function getStats() {
        $licenses = $this->loadLicenses();
        $pending = $this->loadPendingPayments();
        
        return [
            'total_licenses' => count($licenses),
            'active_licenses' => count(array_filter($licenses, function($l) { 
                return $l['status'] === 'active'; 
            })),
            'pending_payments' => count($pending),
            'total_revenue' => array_sum(array_map(function($l) { 
                return $l['usd_value'] ?? 49.99; 
            }, $licenses)),
            'last_sale' => !empty($licenses) ? end($licenses)['created'] : null
        ];
    }
    
    private function generateUniqueDestTag() {
        // Generate unique 6-digit destination tag
        do {
            $tag = rand(100000, 999999);
        } while ($this->getPendingPayment($tag));
        
        return $tag;
    }
    
    private function storePendingPayment($dest_tag, $data) {
        $payments = $this->loadPendingPayments();
        $payments[$dest_tag] = $data;
        file_put_contents($this->payment_file, json_encode($payments, JSON_PRETTY_PRINT));
    }
    
    private function getPendingPayment($dest_tag) {
        $payments = $this->loadPendingPayments();
        return $payments[$dest_tag] ?? null;
    }
    
    private function removePendingPayment($dest_tag) {
        $payments = $this->loadPendingPayments();
        unset($payments[$dest_tag]);
        file_put_contents($this->payment_file, json_encode($payments, JSON_PRETTY_PRINT));
    }
    
    private function loadPendingPayments() {
        if (!file_exists($this->payment_file)) {
            return [];
        }
        
        $data = file_get_contents($this->payment_file);
        return json_decode($data, true) ?: [];
    }
    
    private function checkXahauPayment($dest_tag, $pending) {
        try {
            // Query Xahau network for payments to our address with this dest tag
            $url = "https://xahau.network/v1/accounts/{$this->xahau_address}/transactions?type=Payment&limit=50";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Enhanced-Evernode-Cluster-Manager/1.0',
                    'header' => "Accept: application/json\r\n"
                ]
            ]);
            
            $response = file_get_contents($url, false, $context);
            
            if (!$response) {
                error_log("Failed to fetch Xahau transactions for address: {$this->xahau_address}");
                return false;
            }
            
            $data = json_decode($response, true);
            
            if (!$data || !isset($data['transactions'])) {
                error_log("Invalid Xahau API response: " . substr($response, 0, 200));
                return false;
            }
            
            foreach ($data['transactions'] as $tx) {
                if ($tx['TransactionType'] === 'Payment' && 
                    isset($tx['DestinationTag']) && 
                    $tx['DestinationTag'] == $dest_tag &&
                    isset($tx['meta']) &&
                    $tx['meta']['TransactionResult'] === 'tesSUCCESS') {
                    
                    // Verify amount matches expected (with 5% tolerance for rate changes)
                    $amount = $this->normalizeAmount($tx['Amount'], $pending['currency']);
                    $expected = floatval($pending['exact_amount']);
                    $tolerance = $expected * 0.05; // 5% tolerance
                    
                    if ($amount >= ($expected - $tolerance)) {
                        return [
                            'hash' => $tx['hash'],
                            'amount' => $amount,
                            'currency' => $pending['currency'],
                            'confirmed' => true,
                            'ledger_index' => $tx['ledger_index']
                        ];
                    } else {
                        error_log("Payment amount mismatch. Expected: {$expected}, Got: {$amount}");
                    }
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Xahau payment check failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function normalizeAmount($amount, $currency) {
        if ($currency === 'xrp') {
            // XRP amounts in Xahau are in drops (1 XRP = 1,000,000 drops)
            if (is_string($amount) && strlen($amount) > 6) {
                return floatval($amount) / 1000000;
            }
        } else {
            // For XAH and other currencies, amount might be in different format
            if (is_array($amount)) {
                return floatval($amount['value'] ?? $amount);
            }
        }
        
        return floatval($amount);
    }
    
    private function createLicense($dest_tag, $payment_data) {
        $license_code = $this->generateLicenseCode();
        
        $license = [
            'code' => $license_code,
            'dest_tag' => $dest_tag,
            'payment_hash' => $payment_data['hash'],
            'currency' => $payment_data['currency'],
            'amount' => $payment_data['amount'],
            'usd_value' => 49.99,
            'created' => date('Y-m-d H:i:s'),
            'created_timestamp' => time(),
            'status' => 'active',
            'ledger_index' => $payment_data['ledger_index'] ?? null,
            'features' => [
                'unlimited_clusters' => true,
                'cluster_extensions' => true,
                'real_time_monitoring' => true,
                'priority_support' => true,
                'future_updates' => true,
                'dhali_oracle_access' => true
            ]
        ];
        
        // Store license
        $this->storeLicense($license);
        
        // TODO: Mint NFT on Xahau network
        $this->mintNFTLicense($license);
        
        return $license_code;
    }
    
    private function generateLicenseCode() {
        $segments = [];
        for ($i = 0; $i < 3; $i++) {
            $segments[] = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        }
        return 'EVER-' . implode('-', $segments);
    }
    
    private function storeLicense($license) {
        $licenses = $this->loadLicenses();
        $licenses[] = $license;
        file_put_contents($this->license_file, json_encode($licenses, JSON_PRETTY_PRINT));
    }
    
    private function loadLicenses() {
        if (!file_exists($this->license_file)) {
            return [];
        }
        
        $data = file_get_contents($this->license_file);
        return json_decode($data, true) ?: [];
    }
    
    private function checkWalletForNFT($wallet_address) {
        try {
            // Check Xahau network for Cluster Manager NFTs in this wallet
            $url = "https://xahau.network/v1/accounts/{$wallet_address}/nfts";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Enhanced-Evernode-Cluster-Manager/1.0'
                ]
            ]);
            
            $response = file_get_contents($url, false, $context);
            
            if (!$response) {
                return ['valid' => false, 'error' => 'Could not check wallet'];
            }
            
            $data = json_decode($response, true);
            
            if (!$data || !isset($data['nfts'])) {
                return ['valid' => false, 'error' => 'No NFTs found'];
            }
            
            foreach ($data['nfts'] as $nft) {
                // Look for our cluster manager NFTs
                $uri = $nft['URI'] ?? '';
                $decoded_uri = $uri ? hex2bin($uri) : '';
                
                if (strpos($decoded_uri, 'cluster-manager') !== false || 
                    strpos($decoded_uri, 'evernode-enhanced') !== false) {
                    return [
                        'valid' => true,
                        'license' => 'NFT-' . substr($nft['NFTokenID'], -8),
                        'nft_id' => $nft['NFTokenID']
                    ];
                }
            }
            
            return ['valid' => false, 'error' => 'No Cluster Manager NFT found'];
            
        } catch (Exception $e) {
            error_log("Wallet NFT check failed: " . $e->getMessage());
            return ['valid' => false, 'error' => 'Network error checking wallet'];
        }
    }
    
    
    private function mintNFTLicense($license) {
        // TODO: Implement NFT minting on Xahau
        // This would use xrpl.js to create and submit NFT mint transaction
        
        try {
            // Placeholder for NFT minting logic
            $nft_metadata = [
                'name' => 'Enhanced Evernode Cluster Manager License',
                'description' => 'Lifetime access to Enhanced Evernode Cluster Management Platform',
                'image' => 'https://yoursite.com/assets/nft-license.png',
                'external_url' => 'https://yoursite.com/cluster/',
                'attributes' => [
                    ['trait_type' => 'License Code', 'value' => $license['code']],
                    ['trait_type' => 'Features', 'value' => 'Unlimited Clusters'],
                    ['trait_type' => 'Type', 'value' => 'Lifetime License'],
                    ['trait_type' => 'Created', 'value' => $license['created']],
                    ['trait_type' => 'Currency', 'value' => strtoupper($license['currency'])],
                    ['trait_type' => 'Amount', 'value' => $license['amount']],
                    ['trait_type' => 'Platform', 'value' => 'Enhanced Evernode']
                ]
            ];
            
            // Create URI for NFT metadata
            $metadata_json = json_encode($nft_metadata);
            $metadata_uri = bin2hex($metadata_json);
            
            // Log NFT creation for now (implement actual minting later)
            error_log("Would mint NFT with metadata: " . $metadata_json);
            error_log("NFT URI: " . $metadata_uri);
            
            // Store NFT info in license record
            $this->updateLicenseWithNFT($license['code'], [
                'nft_metadata_uri' => $metadata_uri,
                'nft_status' => 'pending_mint'
            ]);
            
        } catch (Exception $e) {
            error_log("NFT minting preparation failed: " . $e->getMessage());
        }
    }
    
    private function updateLicenseWithNFT($license_code, $nft_data) {
        $licenses = $this->loadLicenses();
        
        for ($i = 0; $i < count($licenses); $i++) {
            if ($licenses[$i]['code'] === $license_code) {
                $licenses[$i] = array_merge($licenses[$i], $nft_data);
                break;
            }
        }
        
        file_put_contents($this->license_file, json_encode($licenses, JSON_PRETTY_PRINT));
    }
    
    // Cleanup expired pending payments (run periodically)
    public function cleanupExpiredPayments() {
        $payments = $this->loadPendingPayments();
        $current_time = time();
        $cleaned = 0;
        
        foreach ($payments as $dest_tag => $payment) {
            if ($current_time > $payment['expires']) {
                unset($payments[$dest_tag]);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            file_put_contents($this->payment_file, json_encode($payments, JSON_PRETTY_PRINT));
            error_log("Cleaned up {$cleaned} expired payments");
        }
        
        return $cleaned;
    }
}

// API Endpoints
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$manager = new XahauNFTLicenseManager();

if ($method === 'POST') {
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'generate_payment':
            $currency = $input['currency'] ?? 'xrp';
            $rate_mode = $input['rate_mode'] ?? 'balanced';
            echo json_encode($manager->generatePayment($currency, $rate_mode));
            break;
            
        case 'verify_license':
            $identifier = $input['identifier'] ?? '';
            echo json_encode($manager->verifyLicense($identifier));
            break;
            
        case 'cleanup_expired':
            $cleaned = $manager->cleanupExpiredPayments();
            echo json_encode(['cleaned' => $cleaned, 'success' => true]);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    
} elseif ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'check_payment':
            $dest_tag = $_GET['dest_tag'] ?? '';
            echo json_encode($manager->checkPayment($dest_tag));
            break;
            
        case 'stats':
            echo json_encode($manager->getStats());
            break;
            
        case 'health':
            echo json_encode([
                'status' => 'healthy',
                'timestamp' => time(),
                'dhali_integration' => 'active',
                'data_dir_writable' => is_writable('data/'),
                'last_cleanup' => filemtime('data/pending_payments.json') ?? 'never'
            ]);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    
} else {
    echo json_encode(['error' => 'Method not allowed']);
}
?>

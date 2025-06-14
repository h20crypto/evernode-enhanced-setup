<?php
// api/xahau-nft-licenses.php - NFT licenses on Xahau network

class XahauNFTLicenseSystem {
    private $xahau_node = 'wss://xahau.network';
    private $xahau_rest = 'https://xahau.network:51234';
    private $issuer_address = 'rYourIssuerAddressOnXahau';
    private $issuer_secret = 'sYourIssuerSecretOnXahau';
    private $payment_address = 'rYourPaymentAddressOnXahau';
    
    public function __construct() {
        // Initialize Xahau connection
    }
    
    // 1. CREATE PAYMENT REQUEST FOR XAHAU NETWORK
    public function createXahauPaymentRequest($options = []) {
        $pricing = $this->calculateXahauPricing();
        
        return [
            'network' => 'xahau',
            'txjson' => [
                'TransactionType' => 'Payment',
                'Account' => '', // Will be filled by user's wallet
                'Destination' => $this->payment_address,
                'Amount' => $pricing['evr_drops'], // EVR in drops
                'DestinationTag' => $this->generateUniqueTag(),
                'NetworkID' => 21337, // Xahau network ID
                'Fee' => '12' // Xahau fee (much lower than XRPL)
            ],
            'custom_meta' => [
                'instruction' => "Evernode Cluster Manager License - {$pricing['display']}",
                'blob' => [
                    'product' => 'cluster_manager_license',
                    'network' => 'xahau',
                    'evr_amount' => $pricing['evr_amount'],
                    'usd_equivalent' => 49.99,
                    'locked_until' => time() + (15 * 60) // 15 min price lock
                ]
            ],
            'pricing' => $pricing
        ];
    }
    
    // 2. CALCULATE PRICING IN EVR ON XAHAU
    private function calculateXahauPricing() {
        $targetUSD = 49.99;
        $evrRate = $this->getEVRToUSDRate();
        
        $evrAmount = $targetUSD / $evrRate;
        $evrDrops = strval(intval($evrAmount * 1000000)); // Convert to drops
        
        return [
            'evr_amount' => round($evrAmount, 2),
            'evr_drops' => $evrDrops,
            'usd_equivalent' => $targetUSD,
            'evr_rate' => $evrRate,
            'display' => round($evrAmount, 0) . ' EVR (~$' . $targetUSD . ')',
            'network' => 'xahau'
        ];
    }
    
    // 3. MONITOR XAHAU PAYMENTS
    public function monitorXahauPayments() {
        try {
            // Query Xahau for recent payments to our address
            $recentTxs = $this->getXahauTransactions($this->payment_address);
            
            foreach ($recentTxs as $tx) {
                if ($this->isValidLicensePayment($tx)) {
                    $this->processLicensePayment($tx);
                }
            }
            
        } catch (Exception $e) {
            error_log("Xahau payment monitoring failed: " . $e->getMessage());
        }
    }
    
    // 4. QUERY XAHAU TRANSACTIONS
    private function getXahauTransactions($address, $limit = 50) {
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->xahau_rest,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_POSTFIELDS => json_encode([
                'method' => 'account_tx',
                'params' => [[
                    'account' => $address,
                    'ledger_index_min' => -1,
                    'ledger_index_max' => -1,
                    'binary' => false,
                    'limit' => $limit,
                    'forward' => false
                ]]
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        if ($response) {
            $data = json_decode($response, true);
            return $data['result']['transactions'] ?? [];
        }
        
        return [];
    }
    
    // 5. MINT NFT LICENSE ON XAHAU
    public function mintXahauNFTLicense($paymentTx) {
        try {
            // 1. Create metadata for the license NFT
            $metadata = $this->createLicenseMetadata($paymentTx);
            $metadataUri = $this->uploadMetadata($metadata);
            
            // 2. Mint NFToken on Xahau network
            $mintTx = [
                'TransactionType' => 'NFTokenMint',
                'Account' => $this->issuer_address,
                'URI' => bin2hex($metadataUri), // Hex encoded URI
                'Flags' => 8, // tfTransferable
                'TransferFee' => 0, // No transfer fee
                'NFTokenTaxon' => 1337, // Cluster Manager taxon
                'NetworkID' => 21337, // Xahau network
                'Fee' => '12'
            ];
            
            // 3. Sign and submit to Xahau
            $signedTx = $this->signXahauTransaction($mintTx);
            $result = $this->submitToXahau($signedTx);
            
            if ($result['success']) {
                // 4. Transfer NFT to customer
                $transferResult = $this->transferNFTToCustomer(
                    $result['nft_token_id'],
                    $paymentTx['Account']
                );
                
                return [
                    'success' => true,
                    'nft_token_id' => $result['nft_token_id'],
                    'tx_hash' => $result['tx_hash'],
                    'network' => 'xahau',
                    'customer_address' => $paymentTx['Account'],
                    'metadata_uri' => $metadataUri
                ];
            }
            
        } catch (Exception $e) {
            error_log("Xahau NFT minting failed: " . $e->getMessage());
        }
        
        return ['success' => false, 'error' => 'Minting failed'];
    }
    
    // 6. CREATE LICENSE METADATA
    private function createLicenseMetadata($paymentTx) {
        return [
            'name' => 'Evernode Cluster Manager License',
            'description' => 'Premium license NFT for advanced cluster management on Evernode',
            'image' => 'https://yourhost.com/assets/cluster-license-nft.png',
            'external_url' => 'https://yourhost.com/cluster/',
            'attributes' => [
                ['trait_type' => 'Product', 'value' => 'Cluster Manager'],
                ['trait_type' => 'Network', 'value' => 'Xahau'],
                ['trait_type' => 'License Type', 'value' => 'Lifetime'],
                ['trait_type' => 'Features', 'value' => 'Unlimited Clusters'],
                ['trait_type' => 'Payment TX', 'value' => $paymentTx['hash']],
                ['trait_type' => 'Payment Amount', 'value' => $this->formatEVRAmount($paymentTx['Amount'])],
                ['trait_type' => 'Issued Date', 'value' => date('Y-m-d')],
                ['trait_type' => 'Issued To', 'value' => $paymentTx['Account']],
                ['trait_type' => 'Evernode Compatible', 'value' => 'Yes'],
                ['trait_type' => 'Transferable', 'value' => 'Yes']
            ],
            'license_terms' => [
                'usage' => 'Unlimited cluster creation and management',
                'duration' => 'Lifetime',
                'transferable' => true,
                'network' => 'xahau',
                'compatible_with' => ['evernode', 'xahau-apps'],
                'support' => 'Priority technical support included'
            ],
            'technical' => [
                'network' => 'xahau',
                'network_id' => 21337,
                'taxon' => 1337,
                'issuer' => $this->issuer_address,
                'version' => '1.0'
            ]
        ];
    }
    
    // 7. VERIFY NFT LICENSE OWNERSHIP ON XAHAU
    public function verifyXahauLicenseOwnership($userAddress) {
        try {
            // Query user's NFTs on Xahau
            $nfts = $this->getUserNFTs($userAddress);
            
            // Check for valid cluster manager license
            foreach ($nfts as $nft) {
                if ($this->isValidClusterLicense($nft)) {
                    return [
                        'valid' => true,
                        'nft_token_id' => $nft['NFTokenID'],
                        'issued_date' => $this->extractIssuedDate($nft),
                        'network' => 'xahau',
                        'transferable' => true,
                        'features' => ['unlimited_clusters', 'priority_support'],
                        'metadata_uri' => $this->decodeNFTURI($nft['URI'])
                    ];
                }
            }
            
            return ['valid' => false, 'reason' => 'No valid license NFT found'];
            
        } catch (Exception $e) {
            error_log("License verification failed: " . $e->getMessage());
            return ['valid' => false, 'error' => 'Verification failed'];
        }
    }
    
    // 8. GET USER'S NFTS ON XAHAU
    private function getUserNFTs($userAddress) {
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->xahau_rest,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_POSTFIELDS => json_encode([
                'method' => 'account_nfts',
                'params' => [[
                    'account' => $userAddress,
                    'ledger_index' => 'validated'
                ]]
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        if ($response) {
            $data = json_decode($response, true);
            return $data['result']['account_nfts'] ?? [];
        }
        
        return [];
    }
    
    // 9. CHECK IF NFT IS VALID CLUSTER LICENSE
    private function isValidClusterLicense($nft) {
        // Check issuer
        if ($nft['Issuer'] !== $this->issuer_address) return false;
        
        // Check taxon
        if ($nft['nft_taxon'] !== 1337) return false;
        
        // Check if it's our cluster manager license
        $uri = $this->decodeNFTURI($nft['URI'] ?? '');
        if (strpos($uri, 'cluster-license') === false) return false;
        
        return true;
    }
    
    // 10. XAMAN INTEGRATION FOR XAHAU
    public function createXamanXahauPayload($paymentRequest) {
        return [
            'txjson' => $paymentRequest['txjson'],
            'custom_meta' => [
                'instruction' => $paymentRequest['custom_meta']['instruction'],
                'blob' => array_merge($paymentRequest['custom_meta']['blob'], [
                    'network_name' => 'Xahau Network',
                    'network_info' => 'Evernode\'s native network',
                    'currency_name' => 'EVR (Evers)',
                    'why_xahau' => 'Lower fees, faster transactions, Evernode native'
                ])
            ],
            'options' => [
                'submit' => true,
                'expire' => 300, // 5 minutes
                'return_url' => [
                    'web' => 'https://yourhost.com/cluster/payment-success'
                ]
            ]
        ];
    }
    
    // Helper methods
    private function getEVRToUSDRate() {
        // Get current EVR market rate
        try {
            // In production, use real market data
            return 0.02; // $0.02 per EVR
        } catch (Exception $e) {
            return 0.02; // Fallback rate
        }
    }
    
    private function formatEVRAmount($amountDrops) {
        $evr = intval($amountDrops) / 1000000;
        return number_format($evr, 2) . ' EVR';
    }
    
    private function generateUniqueTag() {
        return time() % 100000; // Last 5 digits of timestamp
    }
    
    private function signXahauTransaction($tx) {
        // Sign transaction for Xahau network
        // Implementation depends on chosen signing library
        return $tx; // Placeholder
    }
    
    private function submitToXahau($signedTx) {
        // Submit signed transaction to Xahau network
        return ['success' => true, 'nft_token_id' => 'generated_id', 'tx_hash' => 'tx_hash'];
    }
    
    private function uploadMetadata($metadata) {
        $filename = 'license_' . uniqid() . '.json';
        $filepath = __DIR__ . '/metadata/' . $filename;
        file_put_contents($filepath, json_encode($metadata, JSON_PRETTY_PRINT));
        return 'https://yourhost.com/api/metadata/' . $filename;
    }
    
    private function decodeNFTURI($hexUri) {
        return hex2bin($hexUri);
    }
    
    private function extractIssuedDate($nft) {
        // Extract date from NFT metadata
        return date('Y-m-d');
    }
    
    private function isValidLicensePayment($tx) {
        // Validate payment meets license requirements
        if ($tx['TransactionType'] !== 'Payment') return false;
        if ($tx['Destination'] !== $this->payment_address) return false;
        
        $amount = intval($tx['Amount']) / 1000000; // Convert drops to EVR
        $requiredEVR = 49.99 / $this->getEVRToUSDRate();
        
        return $amount >= ($requiredEVR * 0.95); // 5% tolerance
    }
    
    private function processLicensePayment($tx) {
        // Process valid payment and mint NFT
        $result = $this->mintXahauNFTLicense($tx);
        
        if ($result['success']) {
            // Send confirmation email, log transaction, etc.
            $this->notifyLicenseCreated($result);
        }
        
        return $result;
    }
    
    private function notifyLicenseCreated($result) {
        // Send notifications about successful license creation
        error_log("License NFT created: " . $result['nft_token_id']);
    }
    
    private function transferNFTToCustomer($nftTokenId, $customerAddress) {
        // Create NFT offer for customer
        $offerTx = [
            'TransactionType' => 'NFTokenCreateOffer',
            'Account' => $this->issuer_address,
            'NFTokenID' => $nftTokenId,
            'Destination' => $customerAddress,
            'Amount' => '0', // Free transfer
            'Flags' => 1, // tfSellNFToken
            'NetworkID' => 21337,
            'Fee' => '12'
        ];
        
        // Sign and submit
        $signedTx = $this->signXahauTransaction($offerTx);
        return $this->submitToXahau($signedTx);
    }
}

// API endpoints for Xahau NFT system
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$nftSystem = new XahauNFTLicenseSystem();

switch ($_GET['action'] ?? 'payment-request') {
    case 'payment-request':
        echo json_encode($nftSystem->createXahauPaymentRequest());
        break;
        
    case 'verify-license':
        $address = $_POST['address'] ?? $_GET['address'] ?? '';
        echo json_encode($nftSystem->verifyXahauLicenseOwnership($address));
        break;
        
    case 'monitor-payments':
        $nftSystem->monitorXahauPayments();
        echo json_encode(['status' => 'monitoring_complete']);
        break;
        
    case 'xaman-payload':
        $paymentRequest = $nftSystem->createXahauPaymentRequest();
        $xamanPayload = $nftSystem->createXamanXahauPayload($paymentRequest);
        echo json_encode($xamanPayload);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>

<?php
// api/monitor-payments.php - Check for incoming crypto payments

class CryptoPaymentMonitor {
    private $xrpl_node = 'wss://xrplcluster.com/';
    private $payment_address = 'rYourPaymentAddressHere';
    
    public function checkForPayments() {
        // Check XRPL for incoming payments
        $recent_transactions = $this->getRecentTransactions();
        
        foreach ($recent_transactions as $tx) {
            if ($this->isValidPayment($tx)) {
                $this->processPayment($tx);
            }
        }
    }
    
    private function getRecentTransactions() {
        // Use XRPL API to get recent transactions
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.xrpscan.com/api/v1/account/{$this->payment_address}/transactions?type=payment&limit=50",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        return json_decode($response, true)['transactions'] ?? [];
    }
    
    private function isValidPayment($tx) {
        // Check if payment matches expected amount and destination tag
        $expected_amounts = $this->getExpectedPayments();
        
        $amount = $tx['Amount'];
        $dest_tag = $tx['DestinationTag'] ?? null;
        
        return isset($expected_amounts[$dest_tag]) && 
               $amount >= $expected_amounts[$dest_tag]['min_amount'];
    }
    
    private function processPayment($tx) {
        $dest_tag = $tx['DestinationTag'];
        $license = $this->generateLicense();
        
        // Store payment record
        $this->storeLicense($license, $tx['hash'], $dest_tag);
        
        // Send email notification
        $this->sendLicenseEmail($dest_tag, $license);
        
        return $license;
    }
    
    private function generateLicense() {
        $segments = [];
        for ($i = 0; $i < 3; $i++) {
            $segments[] = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        }
        return 'EVER-' . implode('-', $segments);
    }
    
    private function storeLicense($license, $tx_hash, $dest_tag) {
        $data = [
            'license' => $license,
            'tx_hash' => $tx_hash,
            'dest_tag' => $dest_tag,
            'created' => date('Y-m-d H:i:s'),
            'status' => 'active'
        ];
        
        file_put_contents('licenses.json', json_encode($data) . "\n", FILE_APPEND);
    }
    
    private function getExpectedPayments() {
        // Return expected payments by destination tag
        // This would be stored in database in production
        return [
            '12345' => ['min_amount' => '119000000', 'currency' => 'XRP'], // 119 XRP in drops
            '12346' => ['min_amount' => '2499.50', 'currency' => 'EVR']
        ];
    }
    
    private function sendLicenseEmail($dest_tag, $license) {
        // Email logic here
        // You'd get the email from the destination tag mapping
    }
}

// Run payment monitor
$monitor = new CryptoPaymentMonitor();
$monitor->checkForPayments();
?>

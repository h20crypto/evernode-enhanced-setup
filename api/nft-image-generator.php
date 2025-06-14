<?php
// api/nft-image-generator.php - Generate unique NFT images

class DynamicNFTImageGenerator {
    private $baseImagePath;
    private $generatedDir;
    private $fontPath;
    
    public function __construct() {
        $this->baseImagePath = __DIR__ . '/../assets/cluster-license-nft.png';
        $this->generatedDir = __DIR__ . '/../assets/generated/';
        $this->fontPath = __DIR__ . '/../assets/fonts/arial.ttf';
        
        // Create generated directory if it doesn't exist
        if (!is_dir($this->generatedDir)) {
            mkdir($this->generatedDir, 0755, true);
        }
    }
    
    // METHOD 1: Modify existing PNG image
    public function generatePersonalizedNFT($licenseNumber, $customerAddress = null) {
        try {
            // Load your base image
            $image = imagecreatefrompng($this->baseImagePath);
            
            if (!$image) {
                throw new Exception("Could not load base image");
            }
            
            // Prepare license number text
            $licenseText = "LICENSE #" . str_pad($licenseNumber, 3, '0', STR_PAD_LEFT);
            
            // Set up colors
            $white = imagecolorallocate($image, 255, 255, 255);
            $lightBlue = imagecolorallocate($image, 173, 216, 230);
            
            // Method A: Use built-in fonts (simpler)
            if (!file_exists($this->fontPath)) {
                $this->addTextBuiltinFont($image, $licenseText, $white);
            } else {
                // Method B: Use TTF font (better quality)
                $this->addTextTTFFont($image, $licenseText, $white);
            }
            
            // Add customer address (optional)
            if ($customerAddress) {
                $shortAddress = substr($customerAddress, 0, 12) . '...';
                $this->addOwnerInfo($image, $shortAddress, $lightBlue);
            }
            
            // Add timestamp
            $timestamp = date('Y-m-d H:i');
            $this->addTimestamp($image, $timestamp, $lightBlue);
            
            // Save unique image
            $filename = "license_nft_{$licenseNumber}.png";
            $fullPath = $this->generatedDir . $filename;
            
            if (imagepng($image, $fullPath)) {
                imagedestroy($image);
                return [
                    'success' => true,
                    'filename' => $filename,
                    'url' => $this->getPublicURL($filename),
                    'path' => $fullPath
                ];
            } else {
                throw new Exception("Failed to save image");
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // METHOD 2: Generate from SVG template (more flexible)
    public function generateFromSVGTemplate($licenseNumber, $customerAddress = null) {
        $svgTemplate = $this->createSVGTemplate($licenseNumber, $customerAddress);
        
        // Save SVG first
        $svgFilename = "license_{$licenseNumber}.svg";
        $svgPath = $this->generatedDir . $svgFilename;
        file_put_contents($svgPath, $svgTemplate);
        
        // Convert SVG to PNG using ImageMagick (if available)
        if (extension_loaded('imagick')) {
            return $this->convertSVGToPNG($svgPath, $licenseNumber);
        } else {
            // Fallback: return SVG (some wallets support SVG NFTs)
            return [
                'success' => true,
                'filename' => $svgFilename,
                'url' => $this->getPublicURL($svgFilename),
                'format' => 'svg'
            ];
        }
    }
    
    // Add text with built-in fonts
    private function addTextBuiltinFont($image, $text, $color) {
        // Position for license number (adjust based on your design)
        $x = 400; // Adjust to center on your design
        $y = 950; // Near bottom
        
        // Use built-in font (sizes 1-5)
        imagestring($image, 5, $x, $y, $text, $color);
    }
    
    // Add text with TTF font
    private function addTextTTFFont($image, $text, $color) {
        // Font size and position (adjust for your design)
        $fontSize = 16;
        $angle = 0;
        $x = 400; // Adjust to center
        $y = 950; // Adjust to bottom area
        
        // Add text with shadow effect
        $black = imagecolorallocate($image, 0, 0, 0);
        imagettftext($image, $fontSize, $angle, $x+2, $y+2, $black, $this->fontPath, $text); // Shadow
        imagettftext($image, $fontSize, $angle, $x, $y, $color, $this->fontPath, $text); // Main text
    }
    
    // Add owner info
    private function addOwnerInfo($image, $address, $color) {
        $text = "Issued to: " . $address;
        $x = 50;
        $y = 1000;
        
        if (file_exists($this->fontPath)) {
            imagettftext($image, 10, 0, $x, $y, $color, $this->fontPath, $text);
        } else {
            imagestring($image, 2, $x, $y, $text, $color);
        }
    }
    
    // Add timestamp
    private function addTimestamp($image, $timestamp, $color) {
        $text = "Issued: " . $timestamp;
        $x = 700;
        $y = 1000;
        
        if (file_exists($this->fontPath)) {
            imagettftext($image, 10, 0, $x, $y, $color, $this->fontPath, $text);
        } else {
            imagestring($image, 2, $x, $y, $text, $color);
        }
    }
    
    // Create SVG template
    private function createSVGTemplate($licenseNumber, $customerAddress = null) {
        $licenseText = "LICENSE #" . str_pad($licenseNumber, 3, '0', STR_PAD_LEFT);
        $ownerText = $customerAddress ? "Issued to: " . substr($customerAddress, 0, 12) . "..." : "";
        $dateText = "Issued: " . date('Y-m-d H:i');
        
        return <<<SVG
<svg width="1024" height="1024" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="mainGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#667eea;stop-opacity:1" />
      <stop offset="50%" style="stop-color:#764ba2;stop-opacity:1" />
      <stop offset="100%" style="stop-color:#4CAF50;stop-opacity:1" />
    </linearGradient>
    <filter id="glow">
      <feGaussianBlur stdDeviation="3" result="coloredBlur"/>
      <feMerge> 
        <feMergeNode in="coloredBlur"/>
        <feMergeNode in="SourceGraphic"/>
      </feMerge>
    </filter>
  </defs>
  
  <!-- Background -->
  <rect width="1024" height="1024" fill="url(#mainGrad)" rx="20"/>
  
  <!-- Grid pattern overlay -->
  <defs>
    <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
      <path d="M 40 0 L 0 0 0 40" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/>
    </pattern>
  </defs>
  <rect width="100%" height="100%" fill="url(#grid)" opacity="0.3"/>
  
  <!-- Main title -->
  <text x="512" y="150" text-anchor="middle" fill="white" font-size="48" font-weight="bold" font-family="Arial, sans-serif">EVERNODE</text>
  <text x="512" y="200" text-anchor="middle" fill="white" font-size="24" font-family="Arial, sans-serif" opacity="0.9">CLUSTER MANAGER LICENSE</text>
  
  <!-- Icons -->
  <text x="512" y="380" text-anchor="middle" fill="white" font-size="80" filter="url(#glow)">⚡🔗💎</text>
  
  <!-- Features box -->
  <rect x="312" y="420" width="400" height="200" fill="rgba(255,255,255,0.1)" rx="15" stroke="rgba(255,255,255,0.2)"/>
  <text x="512" y="450" text-anchor="middle" fill="white" font-size="14" font-family="Arial, sans-serif">
    <tspan x="512" dy="0">✓ Unlimited Cluster Creation</tspan>
    <tspan x="512" dy="25">✓ One-Click Instance Extensions</tspan>
    <tspan x="512" dy="25">✓ Real-Time Monitoring</tspan>
    <tspan x="512" dy="25">✓ Priority Support</tspan>
    <tspan x="512" dy="25">✓ Lifetime Access</tspan>
  </text>
  
  <!-- License number (dynamic) -->
  <rect x="362" y="750" width="300" height="60" fill="rgba(0,0,0,0.3)" rx="8"/>
  <text x="512" y="785" text-anchor="middle" fill="white" font-size="20" font-family="monospace, Courier" letter-spacing="2px">{$licenseText}</text>
  
  <!-- Footer info -->
  <text x="512" y="850" text-anchor="middle" fill="white" font-size="12" opacity="0.7" font-family="Arial, sans-serif">PREMIUM LICENSE • XAHAU NETWORK</text>
  
  <!-- Owner and date info -->
  <text x="50" y="980" fill="rgba(255,255,255,0.6)" font-size="10" font-family="Arial, sans-serif">{$ownerText}</text>
  <text x="974" y="980" text-anchor="end" fill="rgba(255,255,255,0.6)" font-size="10" font-family="Arial, sans-serif">{$dateText}</text>
  
  <!-- Corner decorations -->
  <rect x="20" y="20" width="60" height="60" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="2" rx="15"/>
  <rect x="944" y="20" width="60" height="60" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="2" rx="15"/>
  <rect x="20" y="944" width="60" height="60" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="2" rx="15"/>
  <rect x="944" y="944" width="60" height="60" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="2" rx="15"/>
</svg>
SVG;
    }
    
    // Convert SVG to PNG using ImageMagick
    private function convertSVGToPNG($svgPath, $licenseNumber) {
        try {
            $imagick = new Imagick();
            $imagick->readImage($svgPath);
            $imagick->setImageFormat('png');
            
            $pngFilename = "license_nft_{$licenseNumber}.png";
            $pngPath = $this->generatedDir . $pngFilename;
            
            $imagick->writeImage($pngPath);
            $imagick->clear();
            
            return [
                'success' => true,
                'filename' => $pngFilename,
                'url' => $this->getPublicURL($pngFilename),
                'format' => 'png'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'ImageMagick conversion failed: ' . $e->getMessage()
            ];
        }
    }
    
    // Get next license number
    public function getNextLicenseNumber() {
        $files = glob($this->generatedDir . 'license_nft_*.png');
        return count($files) + 1;
    }
    
    // Get public URL for generated image
    private function getPublicURL($filename) {
        $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
        return "{$protocol}://{$domain}/assets/generated/{$filename}";
    }
    
    // Download and install a basic font if none exists
    public function setupFont() {
        if (!file_exists(dirname($this->fontPath))) {
            mkdir(dirname($this->fontPath), 0755, true);
        }
        
        if (!file_exists($this->fontPath)) {
            // Download a free font (Google Fonts)
            $fontUrl = 'https://github.com/google/fonts/raw/main/apache/roboto/Roboto-Regular.ttf';
            $fontData = file_get_contents($fontUrl);
            
            if ($fontData) {
                file_put_contents($this->fontPath, $fontData);
                return true;
            }
        }
        
        return file_exists($this->fontPath);
    }
}

// API endpoint for generating NFT images
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $generator = new DynamicNFTImageGenerator();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $licenseNumber = $input['license_number'] ?? $generator->getNextLicenseNumber();
    $customerAddress = $input['customer_address'] ?? null;
    $method = $input['method'] ?? 'png'; // 'png' or 'svg'
    
    if ($method === 'svg') {
        $result = $generator->generateFromSVGTemplate($licenseNumber, $customerAddress);
    } else {
        $result = $generator->generatePersonalizedNFT($licenseNumber, $customerAddress);
    }
    
    echo json_encode($result);
    exit;
}

// API endpoint for getting next license number
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['action'] === 'next-number') {
    header('Content-Type: application/json');
    
    $generator = new DynamicNFTImageGenerator();
    echo json_encode([
        'next_license_number' => $generator->getNextLicenseNumber()
    ]);
    exit;
}
?>

<?php
/**
 * Request Signature Plugin
 * Validates request signatures to prevent tampering
 */

class RequestSignature {
    private static $secretKey = null;
    
    /**
     * Initialize request signature
     */
    public static function init() {
        // Get secret key from environment or generate one
        self::$secretKey = getenv('REQUEST_SIGNATURE_KEY') ?: self::generateKey();
    }
    
    /**
     * Generate secret key
     */
    private static function generateKey() {
        $keyFile = __DIR__ . '/logs/signature_key.txt';
        
        if (file_exists($keyFile)) {
            return trim(file_get_contents($keyFile));
        }
        
        $key = bin2hex(random_bytes(32));
        file_put_contents($keyFile, $key);
        chmod($keyFile, 0600); // Read/write for owner only
        
        return $key;
    }
    
    /**
     * Generate request signature
     */
    public static function generate($data) {
        if (self::$secretKey === null) {
            self::init();
        }
        
        // Sort data for consistent hashing
        ksort($data);
        
        // Create signature string
        $signatureString = http_build_query($data) . self::$secretKey;
        
        // Generate signature
        return hash_hmac('sha256', $signatureString, self::$secretKey);
    }
    
    /**
     * Verify request signature
     */
    public static function verify($data, $signature) {
        if (self::$secretKey === null) {
            self::init();
        }
        
        $expectedSignature = self::generate($data);
        
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Sign form data
     */
    public static function signFormData($data) {
        $signature = self::generate($data);
        $data['_signature'] = $signature;
        return $data;
    }
    
    /**
     * Verify form data signature
     */
    public static function verifyFormData($data) {
        if (!isset($data['_signature'])) {
            return false;
        }
        
        $signature = $data['_signature'];
        unset($data['_signature']);
        
        return self::verify($data, $signature);
    }
    
    /**
     * Generate nonce for request
     */
    public static function generateNonce($length = 16) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Validate nonce (prevent replay attacks)
     */
    public static function validateNonce($nonce, $maxAge = 300) {
        $nonceFile = __DIR__ . '/logs/nonces.json';
        
        $nonces = [];
        if (file_exists($nonceFile)) {
            $nonces = json_decode(file_get_contents($nonceFile), true) ?: [];
        }
        
        $now = time();
        
        // Clean expired nonces
        foreach ($nonces as $key => $data) {
            if ($now - $data['created'] > $maxAge) {
                unset($nonces[$key]);
            }
        }
        
        // Check if nonce exists
        if (isset($nonces[$nonce])) {
            return false; // Nonce already used
        }
        
        // Store nonce
        $nonces[$nonce] = [
            'created' => $now,
            'ip' => self::getClientIP()
        ];
        
        // Keep only last 10000 nonces
        if (count($nonces) > 10000) {
            $nonces = array_slice($nonces, -10000, null, true);
        }
        
        file_put_contents($nonceFile, json_encode($nonces));
        
        return true;
    }
    
    /**
     * Get client IP
     */
    private static function getClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}


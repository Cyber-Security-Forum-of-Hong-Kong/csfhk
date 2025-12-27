<?php
/**
 * Encryption Plugin
 * Provides secure encryption/decryption utilities
 */

class Encryption {
    private static $key = null;
    private static $cipher = 'AES-256-GCM';
    
    /**
     * Initialize encryption
     */
    public static function init() {
        // Get encryption key from environment or generate
        self::$key = getenv('ENCRYPTION_KEY') ?: self::generateKey();
    }
    
    /**
     * Generate encryption key
     */
    private static function generateKey() {
        $keyFile = __DIR__ . '/logs/encryption_key.txt';
        
        if (file_exists($keyFile)) {
            return trim(file_get_contents($keyFile));
        }
        
        $key = base64_encode(random_bytes(32));
        file_put_contents($keyFile, $key);
        chmod($keyFile, 0600);
        
        return $key;
    }
    
    /**
     * Encrypt data
     */
    public static function encrypt($data) {
        if (self::$key === null) {
            self::init();
        }
        
        $key = base64_decode(self::$key);
        $ivLength = openssl_cipher_iv_length(self::$cipher);
        $iv = random_bytes($ivLength);
        
        $encrypted = openssl_encrypt($data, self::$cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
        
        return base64_encode($iv . $tag . $encrypted);
    }
    
    /**
     * Decrypt data
     */
    public static function decrypt($encryptedData) {
        if (self::$key === null) {
            self::init();
        }
        
        $key = base64_decode(self::$key);
        $data = base64_decode($encryptedData);
        
        $ivLength = openssl_cipher_iv_length(self::$cipher);
        $tagLength = 16; // GCM tag length
        
        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, $tagLength);
        $encrypted = substr($data, $ivLength + $tagLength);
        
        return openssl_decrypt($encrypted, self::$cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
    }
    
    /**
     * Hash password (wrapper for password_hash)
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3          // 3 threads
        ]);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate secure random token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Generate secure random string
     */
    public static function generateRandomString($length = 32) {
        return base64_encode(random_bytes($length));
    }
}


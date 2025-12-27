<?php
/**
 * API Key Validation Plugin
 * Validates API keys for protected endpoints
 */

class APIKeyValidation {
    private static $keysFile = null;
    private static $enabled = false; // Enable if you want API key protection
    
    /**
     * Initialize API key validation
     */
    public static function init() {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        self::$keysFile = $logDir . '/api_keys.json';
    }
    
    /**
     * Validate API key
     */
    public static function validate($apiKey) {
        if (!self::$enabled) {
            return true; // API key validation disabled
        }
        
        if (self::$keysFile === null) {
            self::init();
        }
        
        if (empty($apiKey)) {
            return false;
        }
        
        // Load API keys
        $keys = [];
        if (file_exists(self::$keysFile)) {
            $keys = json_decode(file_get_contents(self::$keysFile), true) ?: [];
        }
        
        // Check if key exists and is valid
        foreach ($keys as $keyData) {
            if (hash_equals($keyData['key'], $apiKey)) {
                // Check expiration
                if (isset($keyData['expires']) && $keyData['expires'] < time()) {
                    return false;
                }
                
                // Update last used
                $keyData['last_used'] = time();
                self::updateKey($keyData);
                
                return true;
            }
        }
        
        // Log invalid key attempt
        if (class_exists('SecurityMonitor')) {
            SecurityMonitor::logEvent('INVALID_API_KEY', 'medium', 'Invalid API key attempt', [
                'ip' => self::getClientIP()
            ]);
        }
        
        return false;
    }
    
    /**
     * Generate API key
     */
    public static function generateKey($name, $expires = null) {
        if (self::$keysFile === null) {
            self::init();
        }
        
        $key = bin2hex(random_bytes(32));
        $keyData = [
            'name' => $name,
            'key' => $key,
            'created' => time(),
            'expires' => $expires,
            'last_used' => null
        ];
        
        $keys = [];
        if (file_exists(self::$keysFile)) {
            $keys = json_decode(file_get_contents(self::$keysFile), true) ?: [];
        }
        
        $keys[] = $keyData;
        file_put_contents(self::$keysFile, json_encode($keys));
        
        return $key;
    }
    
    /**
     * Update API key
     */
    private static function updateKey($keyData) {
        $keys = [];
        if (file_exists(self::$keysFile)) {
            $keys = json_decode(file_get_contents(self::$keysFile), true) ?: [];
        }
        
        foreach ($keys as &$key) {
            if ($key['key'] === $keyData['key']) {
                $key = $keyData;
                break;
            }
        }
        
        file_put_contents(self::$keysFile, json_encode($keys));
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


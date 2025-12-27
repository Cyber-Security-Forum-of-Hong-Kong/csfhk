<?php
/**
 * Security Token Rotation Plugin
 * Rotates security tokens periodically to prevent token reuse attacks
 */

class SecurityTokenRotation {
    private static $rotationInterval = 3600; // 1 hour
    private static $tokenFile = null;
    
    /**
     * Initialize token rotation
     */
    public static function init() {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        self::$tokenFile = $logDir . '/security_tokens.json';
    }
    
    /**
     * Generate new security token
     */
    public static function generateToken($type = 'default') {
        $token = bin2hex(random_bytes(32));
        $expires = time() + self::$rotationInterval;
        
        // Store token
        $tokens = [];
        if (file_exists(self::$tokenFile)) {
            $tokens = json_decode(file_get_contents(self::$tokenFile), true) ?: [];
        }
        
        if (!isset($tokens[$type])) {
            $tokens[$type] = [];
        }
        
        $tokens[$type][] = [
            'token' => hash('sha256', $token),
            'created' => time(),
            'expires' => $expires
        ];
        
        // Clean old tokens
        $tokens[$type] = array_filter($tokens[$type], function($t) {
            return $t['expires'] > time();
        });
        
        // Keep only last 100 tokens
        if (count($tokens[$type]) > 100) {
            $tokens[$type] = array_slice($tokens[$type], -100);
        }
        
        file_put_contents(self::$tokenFile, json_encode($tokens));
        
        return $token;
    }
    
    /**
     * Validate security token
     */
    public static function validateToken($token, $type = 'default') {
        if (empty($token)) {
            return false;
        }
        
        if (!file_exists(self::$tokenFile)) {
            return false;
        }
        
        $tokens = json_decode(file_get_contents(self::$tokenFile), true) ?: [];
        
        if (!isset($tokens[$type])) {
            return false;
        }
        
        $tokenHash = hash('sha256', $token);
        
        foreach ($tokens[$type] as $storedToken) {
            if (hash_equals($storedToken['token'], $tokenHash)) {
                // Check expiration
                if ($storedToken['expires'] < time()) {
                    return false;
                }
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Rotate all tokens
     */
    public static function rotateAll() {
        // This would be called by a cron job or scheduled task
        // For now, tokens auto-expire based on expiration time
    }
    
    /**
     * Invalidate token
     */
    public static function invalidateToken($token, $type = 'default') {
        if (!file_exists(self::$tokenFile)) {
            return;
        }
        
        $tokens = json_decode(file_get_contents(self::$tokenFile), true) ?: [];
        
        if (!isset($tokens[$type])) {
            return;
        }
        
        $tokenHash = hash('sha256', $token);
        
        $tokens[$type] = array_filter($tokens[$type], function($t) use ($tokenHash) {
            return !hash_equals($t['token'], $tokenHash);
        });
        
        file_put_contents(self::$tokenFile, json_encode($tokens));
    }
}


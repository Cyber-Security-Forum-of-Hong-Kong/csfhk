<?php
/**
 * Enhanced Security Module
 * Provides CSRF protection, input sanitization, and security utilities
 */

class Security {
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Validate and sanitize input
     */
    public static function sanitizeInput($input, $type = 'string') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return self::sanitizeInput($item, $type);
            }, $input);
        }
        
        switch ($type) {
            case 'string':
                // Remove null bytes and trim
                $input = str_replace("\0", '', $input);
                $input = trim($input);
                // HTML escape
                return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
                
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
                
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
                
            case 'raw':
                // Only remove null bytes, don't escape
                return str_replace("\0", '', $input);
                
            default:
                return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    
    /**
     * Validate email format
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate password strength
     */
    public static function validatePassword($password) {
        // Minimum 8 characters, at least one letter and one number
        if (strlen($password) < 8) {
            return ['valid' => false, 'error' => 'Password must be at least 8 characters long'];
        }
        
        if (!preg_match('/[a-zA-Z]/', $password)) {
            return ['valid' => false, 'error' => 'Password must contain at least one letter'];
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'error' => 'Password must contain at least one number'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Check for suspicious patterns in input
     */
    public static function isSuspicious($input) {
        if (!is_string($input)) {
            return false;
        }
        
        $suspiciousPatterns = [
            '/<script/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/eval\s*\(/i',
            '/expression\s*\(/i',
            '/vbscript:/i',
            '/data:text\/html/i',
            '/\.\.\//',
            '/\.\.\\\\/',
            '/union.*select/i',
            '/select.*from/i',
            '/insert.*into/i',
            '/delete.*from/i',
            '/drop.*table/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec/i',
            '/passthru/i',
            '/proc_open/i',
            '/popen/i',
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Rate limit check for specific action
     */
    public static function checkRateLimit($action, $maxAttempts = 5, $timeWindow = 300) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . $action;
        $now = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 1,
                'first_attempt' => $now,
                'last_attempt' => $now
            ];
            return true;
        }
        
        $data = $_SESSION[$key];
        
        // Reset if time window passed
        if ($now - $data['first_attempt'] > $timeWindow) {
            $_SESSION[$key] = [
                'count' => 1,
                'first_attempt' => $now,
                'last_attempt' => $now
            ];
            return true;
        }
        
        // Check if exceeded
        if ($data['count'] >= $maxAttempts) {
            return false;
        }
        
        // Increment counter
        $_SESSION[$key]['count']++;
        $_SESSION[$key]['last_attempt'] = $now;
        
        return true;
    }
    
    /**
     * Get remaining time until rate limit resets
     */
    public static function getRateLimitRemaining($action, $timeWindow = 300) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . $action;
        
        if (!isset($_SESSION[$key])) {
            return 0;
        }
        
        $data = $_SESSION[$key];
        $elapsed = time() - $data['first_attempt'];
        $remaining = $timeWindow - $elapsed;
        
        return max(0, $remaining);
    }
    
    /**
     * Check account lockout status
     */
    public static function isAccountLocked($identifier, $maxAttempts = 5, $lockoutTime = 900) {
        $lockoutFile = __DIR__ . '/logs/lockouts.json';
        
        if (!file_exists($lockoutFile)) {
            return false;
        }
        
        $lockouts = json_decode(file_get_contents($lockoutFile), true) ?: [];
        $now = time();
        
        // Clean expired lockouts
        foreach ($lockouts as $key => $lockout) {
            if ($now - $lockout['locked_at'] > $lockoutTime) {
                unset($lockouts[$key]);
            }
        }
        
        // Check if identifier is locked
        foreach ($lockouts as $lockout) {
            if ($lockout['identifier'] === $identifier) {
                if ($now - $lockout['locked_at'] < $lockoutTime) {
                    return true;
                }
            }
        }
        
        // Save cleaned lockouts
        file_put_contents($lockoutFile, json_encode(array_values($lockouts)));
        
        return false;
    }
    
    /**
     * Record failed login attempt
     */
    public static function recordFailedAttempt($identifier) {
        $attemptsFile = __DIR__ . '/logs/login_attempts.json';
        $maxAttempts = 5;
        $lockoutTime = 900; // 15 minutes
        
        $attempts = [];
        if (file_exists($attemptsFile)) {
            $attempts = json_decode(file_get_contents($attemptsFile), true) ?: [];
        }
        
        $now = time();
        $window = 300; // 5 minutes
        
        // Clean old attempts
        foreach ($attempts as $key => $attempt) {
            if ($now - $attempt['time'] > $window) {
                unset($attempts[$key]);
            }
        }
        
        // Count recent attempts for this identifier
        $recentCount = 0;
        foreach ($attempts as $attempt) {
            if ($attempt['identifier'] === $identifier && ($now - $attempt['time']) <= $window) {
                $recentCount++;
            }
        }
        
        // Record new attempt
        $attempts[] = [
            'identifier' => $identifier,
            'time' => $now,
            'ip' => self::getClientIP()
        ];
        
        file_put_contents($attemptsFile, json_encode(array_values($attempts)));
        
        // Lock account if exceeded
        if ($recentCount >= $maxAttempts - 1) {
            self::lockAccount($identifier);
        }
    }
    
    /**
     * Lock an account
     */
    private static function lockAccount($identifier) {
        $lockoutFile = __DIR__ . '/logs/lockouts.json';
        
        $lockouts = [];
        if (file_exists($lockoutFile)) {
            $lockouts = json_decode(file_get_contents($lockoutFile), true) ?: [];
        }
        
        $lockouts[] = [
            'identifier' => $identifier,
            'locked_at' => time(),
            'ip' => self::getClientIP()
        ];
        
        // Create logs directory if needed
        $logDir = dirname($lockoutFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($lockoutFile, json_encode($lockouts));
    }
    
    /**
     * Clear failed attempts for identifier (on successful login)
     */
    public static function clearFailedAttempts($identifier) {
        $attemptsFile = __DIR__ . '/logs/login_attempts.json';
        
        if (!file_exists($attemptsFile)) {
            return;
        }
        
        $attempts = json_decode(file_get_contents($attemptsFile), true) ?: [];
        
        // Remove all attempts for this identifier
        $attempts = array_filter($attempts, function($attempt) use ($identifier) {
            return $attempt['identifier'] !== $identifier;
        });
        
        file_put_contents($attemptsFile, json_encode(array_values($attempts)));
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIP() {
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
    
    /**
     * Check request size limits
     */
    public static function checkRequestSize($maxSize = 1048576) { // 1MB default
        $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
        
        if ($contentLength > $maxSize) {
            http_response_code(413);
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => false,
                'error' => 'Request too large',
                'max_size' => $maxSize
            ]);
            exit;
        }
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowedTypes = [], $maxSize = 5242880) { // 5MB default
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'File upload error'];
        }
        
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File too large'];
        }
        
        if (!empty($allowedTypes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                return ['valid' => false, 'error' => 'File type not allowed'];
            }
        }
        
        // Check for suspicious content
        $content = file_get_contents($file['tmp_name']);
        if (self::isSuspicious($content)) {
            return ['valid' => false, 'error' => 'File contains suspicious content'];
        }
        
        return ['valid' => true];
    }
}


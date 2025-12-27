<?php
/**
 * Advanced Rate Limiter Plugin
 * Multi-level rate limiting with adaptive thresholds
 */

class AdvancedRateLimiter {
    private static $rateFile = null;
    private static $limits = [
        'api' => ['requests' => 100, 'window' => 60],      // 100 per minute
        'login' => ['requests' => 5, 'window' => 300],       // 5 per 5 minutes
        'signup' => ['requests' => 3, 'window' => 3600],    // 3 per hour
        'password_reset' => ['requests' => 3, 'window' => 3600], // 3 per hour
        'general' => ['requests' => 200, 'window' => 60],   // 200 per minute
    ];
    
    /**
     * Initialize advanced rate limiter
     */
    public static function init() {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        self::$rateFile = $logDir . '/advanced_rates.json';
    }
    
    /**
     * Check rate limit
     */
    public static function checkLimit($action, $identifier = null) {
        if (self::$rateFile === null) {
            self::init();
        }
        
        $identifier = $identifier ?: self::getIdentifier();
        $limit = self::$limits[$action] ?? self::$limits['general'];
        
        $rates = [];
        if (file_exists(self::$rateFile)) {
            $rates = json_decode(file_get_contents(self::$rateFile), true) ?: [];
        }
        
        $now = time();
        $key = md5($action . $identifier);
        
        // Clean old entries
        foreach ($rates as $k => $data) {
            if ($now - $data['first_request'] > $limit['window']) {
                unset($rates[$k]);
            }
        }
        
        // Check current identifier
        if (isset($rates[$key])) {
            $data = $rates[$key];
            
            if ($now - $data['first_request'] <= $limit['window']) {
                $rates[$key]['count']++;
                $rates[$key]['last_request'] = $now;
                
                if ($rates[$key]['count'] > $limit['requests']) {
                    // Rate exceeded
                    file_put_contents(self::$rateFile, json_encode($rates));
                    
                    // Log and update reputation
                    if (class_exists('SecurityMonitor')) {
                        SecurityMonitor::logEvent('RATE_LIMIT_EXCEEDED', 'high', "Rate limit exceeded for $action", [
                            'identifier' => $identifier,
                            'count' => $rates[$key]['count'],
                            'limit' => $limit['requests']
                        ]);
                    }
                    
                    if (class_exists('IPReputation') && filter_var($identifier, FILTER_VALIDATE_IP)) {
                        IPReputation::updateReputation($identifier, -10);
                    }
                    
                    if (class_exists('ThreatResponse')) {
                        ThreatResponse::handleThreat('RATE_LIMIT_EXCEEDED', 'high', ['ip' => $identifier]);
                    }
                    
                    return false;
                }
            } else {
                // Reset window
                $rates[$key] = [
                    'count' => 1,
                    'first_request' => $now,
                    'last_request' => $now
                ];
            }
        } else {
            $rates[$key] = [
                'count' => 1,
                'first_request' => $now,
                'last_request' => $now,
                'action' => $action,
                'identifier' => $identifier
            ];
        }
        
        file_put_contents(self::$rateFile, json_encode($rates));
        return true;
    }
    
    /**
     * Get remaining requests
     */
    public static function getRemaining($action, $identifier = null) {
        if (self::$rateFile === null) {
            self::init();
        }
        
        $identifier = $identifier ?: self::getIdentifier();
        $limit = self::$limits[$action] ?? self::$limits['general'];
        
        $rates = [];
        if (file_exists(self::$rateFile)) {
            $rates = json_decode(file_get_contents(self::$rateFile), true) ?: [];
        }
        
        $key = md5($action . $identifier);
        
        if (!isset($rates[$key])) {
            return $limit['requests'];
        }
        
        $data = $rates[$key];
        $now = time();
        
        if ($now - $data['first_request'] > $limit['window']) {
            return $limit['requests'];
        }
        
        return max(0, $limit['requests'] - $data['count']);
    }
    
    /**
     * Get identifier (IP or user ID)
     */
    private static function getIdentifier() {
        // Try user ID first
        if (isset($_SESSION['user_id'])) {
            return 'user_' . $_SESSION['user_id'];
        }
        
        // Fall back to IP
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


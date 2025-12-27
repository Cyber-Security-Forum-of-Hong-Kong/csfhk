<?php
/**
 * Threat Response Plugin
 * Automated response to security threats
 */

class ThreatResponse {
    private static $responseFile = null;
    private static $autoResponseEnabled = true;
    
    /**
     * Initialize threat response
     */
    public static function init() {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        self::$responseFile = $logDir . '/threat_responses.json';
    }
    
    /**
     * Handle security threat
     */
    public static function handleThreat($threatType, $severity, $details = []) {
        if (self::$responseFile === null) {
            self::init();
        }
        
        $ip = $details['ip'] ?? self::getClientIP();
        $response = [
            'threat_type' => $threatType,
            'severity' => $severity,
            'ip' => $ip,
            'timestamp' => time(),
            'details' => $details,
            'actions_taken' => []
        ];
        
        if (!self::$autoResponseEnabled) {
            return $response;
        }
        
        // Automatic responses based on threat type and severity
        switch ($threatType) {
            case 'SQL_INJECTION':
            case 'XSS':
            case 'COMMAND_INJECTION':
                if ($severity === 'high' || $severity === 'critical') {
                    // Immediate blacklist
                    if (class_exists('IPReputation')) {
                        IPReputation::blacklistIP($ip, "$threatType attack", 86400 * 7); // 7 days
                        $response['actions_taken'][] = 'IP_BLACKLISTED';
                    }
                }
                break;
                
            case 'BRUTE_FORCE':
            case 'RATE_LIMIT_EXCEEDED':
                // Blacklist for shorter duration
                if (class_exists('IPReputation')) {
                    IPReputation::blacklistIP($ip, "$threatType detected", 3600); // 1 hour
                    $response['actions_taken'][] = 'IP_TEMPORARILY_BLACKLISTED';
                }
                break;
                
            case 'BOT_DETECTED':
                // Update reputation
                if (class_exists('IPReputation')) {
                    IPReputation::updateReputation($ip, -20);
                    $response['actions_taken'][] = 'REPUTATION_DECREASED';
                }
                break;
                
            case 'MULTIPLE_FAILED_LOGINS':
                // Account lockout already handled, but also blacklist IP
                if (class_exists('IPReputation')) {
                    IPReputation::blacklistIP($ip, 'Multiple failed logins', 3600);
                    $response['actions_taken'][] = 'IP_BLACKLISTED';
                }
                break;
        }
        
        // Log response
        $responses = [];
        if (file_exists(self::$responseFile)) {
            $responses = json_decode(file_get_contents(self::$responseFile), true) ?: [];
        }
        
        $responses[] = $response;
        
        // Keep last 1000 responses
        if (count($responses) > 1000) {
            $responses = array_slice($responses, -1000);
        }
        
        file_put_contents(self::$responseFile, json_encode($responses));
        
        // Log to security monitor
        if (class_exists('SecurityMonitor')) {
            SecurityMonitor::logEvent($threatType, $severity, "Threat detected and handled", $response);
        }
        
        return $response;
    }
    
    /**
     * Enable/disable auto response
     */
    public static function setAutoResponse($enabled) {
        self::$autoResponseEnabled = (bool)$enabled;
    }
    
    /**
     * Get threat response history
     */
    public static function getHistory($limit = 100) {
        if (self::$responseFile === null) {
            self::init();
        }
        
        if (!file_exists(self::$responseFile)) {
            return [];
        }
        
        $responses = json_decode(file_get_contents(self::$responseFile), true) ?: [];
        return array_slice(array_reverse($responses), 0, $limit);
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


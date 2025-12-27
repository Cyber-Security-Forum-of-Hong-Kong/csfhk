<?php
/**
 * Automated Response System
 * Automatically responds to detected threats
 */

class AutomatedResponse {
    private static $responseFile = null;
    private static $actions = [
        'log' => true,
        'block_ip' => true,
        'rate_limit' => true,
        'challenge' => false, // CAPTCHA challenge
        'alert' => true,
    ];
    
    /**
     * Initialize automated response
     */
    public static function init() {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        self::$responseFile = $logDir . '/automated_responses.json';
    }
    
    /**
     * Handle threat automatically
     */
    public static function handleThreat($threatType, $severity, $details = []) {
        $ip = $details['ip'] ?? self::getClientIP();
        $response = [];
        
        // Determine response based on threat type and severity
        switch ($threatType) {
            case 'SQL_INJECTION':
            case 'COMMAND_INJECTION':
            case 'XSS':
                if (self::$actions['block_ip']) {
                    if (class_exists('IPReputation')) {
                        IPReputation::blacklistIP($ip, $threatType, 3600); // 1 hour
                        IPReputation::updateReputation($ip, -50);
                    }
                    $response[] = 'ip_blocked';
                }
                break;
                
            case 'DDOS_ATTACK':
                if (self::$actions['block_ip']) {
                    if (class_exists('IPReputation')) {
                        IPReputation::blacklistIP($ip, 'DDoS attack', 7200); // 2 hours
                    }
                    $response[] = 'ip_blocked';
                }
                break;
                
            case 'BRUTE_FORCE':
                if (self::$actions['rate_limit']) {
                    // Rate limit already handled by rate limiter
                    $response[] = 'rate_limited';
                }
                break;
                
            case 'BOT_DETECTED':
                if (self::$actions['challenge']) {
                    // Could trigger CAPTCHA here
                    $response[] = 'challenge_required';
                }
                break;
        }
        
        // Always log
        if (self::$actions['log']) {
            if (class_exists('SecurityMonitor')) {
                SecurityMonitor::logEvent('AUTOMATED_RESPONSE', $severity, 
                    "Automated response triggered for $threatType", array_merge([
                        'ip' => $ip,
                        'responses' => $response
                    ], $details));
            }
        }
        
        // Send alert for critical threats
        if ($severity === 'critical' && self::$actions['alert']) {
            self::sendAlert($threatType, $severity, $details);
        }
        
        // Record response
        self::recordResponse($threatType, $severity, $ip, $response);
        
        return $response;
    }
    
    /**
     * Record automated response
     */
    private static function recordResponse($threatType, $severity, $ip, $responses) {
        $responses = [];
        if (file_exists(self::$responseFile)) {
            $responses = json_decode(file_get_contents(self::$responseFile), true) ?: [];
        }
        
        $responses[] = [
            'threat_type' => $threatType,
            'severity' => $severity,
            'ip' => $ip,
            'responses' => $responses,
            'time' => time()
        ];
        
        // Keep only last 1000 responses
        if (count($responses) > 1000) {
            $responses = array_slice($responses, -1000);
        }
        
        file_put_contents(self::$responseFile, json_encode($responses));
    }
    
    /**
     * Send alert (can be extended to email, SMS, etc.)
     */
    private static function sendAlert($threatType, $severity, $details) {
        $message = "CRITICAL THREAT DETECTED\n";
        $message .= "Type: $threatType\n";
        $message .= "Severity: $severity\n";
        $message .= "IP: " . ($details['ip'] ?? 'Unknown') . "\n";
        $message .= "Time: " . date('Y-m-d H:i:s') . "\n";
        
        error_log("SECURITY ALERT: $message");
        
        // Could send email, SMS, webhook, etc. here
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


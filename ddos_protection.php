<?php
/**
 * DDoS Protection Plugin
 * Protects against Distributed Denial of Service attacks
 */

class DDoSProtection {
    private static $protectionFile = null;
    private static $enabled = true;
    private static $thresholds = [
        'requests_per_second' => 10,
        'requests_per_minute' => 100,
        'concurrent_connections' => 50,
    ];
    
    /**
     * Initialize DDoS protection
     */
    public static function init() {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        self::$protectionFile = $logDir . '/ddos_protection.json';
    }
    
    /**
     * Check for DDoS attack
     */
    public static function check() {
        if (!self::$enabled) {
            return true;
        }
        
        if (self::$protectionFile === null) {
            self::init();
        }
        
        $ip = self::getClientIP();
        $now = time();
        
        // Load protection data
        $data = [];
        if (file_exists(self::$protectionFile)) {
            $data = json_decode(file_get_contents(self::$protectionFile), true) ?: [];
        }
        
        $ipKey = md5($ip);
        
        // Initialize IP tracking
        if (!isset($data[$ipKey])) {
            $data[$ipKey] = [
                'requests' => [],
                'first_seen' => $now,
                'blocked' => false,
                'blocked_until' => 0
            ];
        }
        
        $ipData = &$data[$ipKey];
        
        // Check if IP is currently blocked
        if ($ipData['blocked'] && $ipData['blocked_until'] > $now) {
            self::logAttack($ip, 'BLOCKED_IP_ACCESS');
            return false;
        }
        
        // Clean old requests (older than 1 minute)
        $ipData['requests'] = array_filter($ipData['requests'], function($timestamp) use ($now) {
            return ($now - $timestamp) <= 60;
        });
        
        // Add current request
        $ipData['requests'][] = $now;
        
        // Count requests in last second
        $requestsLastSecond = count(array_filter($ipData['requests'], function($timestamp) use ($now) {
            return ($now - $timestamp) <= 1;
        }));
        
        // Count requests in last minute
        $requestsLastMinute = count($ipData['requests']);
        
        // Check thresholds
        if ($requestsLastSecond > self::$thresholds['requests_per_second']) {
            self::blockIP($ip, $ipData, 300); // Block for 5 minutes
            self::logAttack($ip, 'DDoS_SECOND_THRESHOLD', [
                'requests' => $requestsLastSecond,
                'threshold' => self::$thresholds['requests_per_second']
            ]);
            return false;
        }
        
        if ($requestsLastMinute > self::$thresholds['requests_per_minute']) {
            self::blockIP($ip, $ipData, 600); // Block for 10 minutes
            self::logAttack($ip, 'DDoS_MINUTE_THRESHOLD', [
                'requests' => $requestsLastMinute,
                'threshold' => self::$thresholds['requests_per_minute']
            ]);
            return false;
        }
        
        // Save data
        file_put_contents(self::$protectionFile, json_encode($data));
        
        return true;
    }
    
    /**
     * Block IP for DDoS
     */
    private static function blockIP($ip, &$ipData, $duration) {
        $ipData['blocked'] = true;
        $ipData['blocked_until'] = time() + $duration;
        
        // Also add to IP reputation blacklist
        if (class_exists('IPReputation')) {
            IPReputation::blacklistIP($ip, 'DDoS attack', $duration);
            IPReputation::updateReputation($ip, -50);
        }
        
        // Log to security monitor
        if (class_exists('SecurityMonitor')) {
            SecurityMonitor::logEvent('DDOS_ATTACK', 'critical', "DDoS attack from $ip", [
                'duration' => $duration
            ]);
        }
        
        // Trigger threat response
        if (class_exists('ThreatResponse')) {
            ThreatResponse::handleThreat('DDOS_ATTACK', 'critical', ['ip' => $ip]);
        }
    }
    
    /**
     * Log DDoS attack
     */
    private static function logAttack($ip, $type, $details = []) {
        error_log("DDoS Protection: $type from IP: $ip");
        
        if (class_exists('SecurityMonitor')) {
            SecurityMonitor::logEvent('DDOS_PROTECTION', 'high', "DDoS protection triggered: $type", array_merge([
                'ip' => $ip
            ], $details));
        }
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


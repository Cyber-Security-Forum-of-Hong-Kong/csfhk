<?php
/**
 * Security Monitoring Plugin
 * Monitors and alerts on security events
 */

class SecurityMonitor {
    private static $alertFile = null;
    private static $statsFile = null;
    private static $thresholds = [
        'failed_logins_per_hour' => 10,
        'blocked_requests_per_hour' => 50,
        'suspicious_patterns_per_hour' => 20,
    ];
    
    /**
     * Initialize security monitor
     */
    public static function init() {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        self::$alertFile = $logDir . '/security_alerts.json';
        self::$statsFile = $logDir . '/security_stats.json';
    }
    
    /**
     * Log security event
     */
    public static function logEvent($type, $severity, $message, $context = []) {
        if (self::$alertFile === null) {
            self::init();
        }
        
        $event = [
            'type' => $type,
            'severity' => $severity, // low, medium, high, critical
            'message' => $message,
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'timestamp' => time(),
            'context' => $context
        ];
        
        $alerts = [];
        if (file_exists(self::$alertFile)) {
            $alerts = json_decode(file_get_contents(self::$alertFile), true) ?: [];
        }
        
        $alerts[] = $event;
        
        // Keep only last 1000 alerts
        if (count($alerts) > 1000) {
            $alerts = array_slice($alerts, -1000);
        }
        
        file_put_contents(self::$alertFile, json_encode($alerts));
        
        // Update statistics
        self::updateStats($type, $severity);
        
        // Check if alert threshold exceeded
        if (in_array($severity, ['high', 'critical'])) {
            self::checkThresholds();
        }
    }
    
    /**
     * Update security statistics
     */
    private static function updateStats($type, $severity) {
        $stats = [];
        if (file_exists(self::$statsFile)) {
            $stats = json_decode(file_get_contents(self::$statsFile), true) ?: [];
        }
        
        $hour = date('Y-m-d-H');
        
        if (!isset($stats[$hour])) {
            $stats[$hour] = [
                'failed_logins' => 0,
                'blocked_requests' => 0,
                'suspicious_patterns' => 0,
                'high_severity' => 0,
                'critical_severity' => 0,
            ];
        }
        
        switch ($type) {
            case 'FAILED_LOGIN':
                $stats[$hour]['failed_logins']++;
                break;
            case 'BLOCKED_REQUEST':
                $stats[$hour]['blocked_requests']++;
                break;
            case 'SUSPICIOUS_PATTERN':
                $stats[$hour]['suspicious_patterns']++;
                break;
        }
        
        if ($severity === 'high') {
            $stats[$hour]['high_severity']++;
        } elseif ($severity === 'critical') {
            $stats[$hour]['critical_severity']++;
        }
        
        // Clean old stats (keep last 24 hours)
        $now = time();
        foreach ($stats as $key => $value) {
            $statTime = strtotime(str_replace('-', ' ', $key));
            if ($now - $statTime > 86400) {
                unset($stats[$key]);
            }
        }
        
        file_put_contents(self::$statsFile, json_encode($stats));
    }
    
    /**
     * Check if thresholds exceeded
     */
    private static function checkThresholds() {
        $stats = [];
        if (file_exists(self::$statsFile)) {
            $stats = json_decode(file_get_contents(self::$statsFile), true) ?: [];
        }
        
        $hour = date('Y-m-d-H');
        $currentStats = $stats[$hour] ?? [];
        
        foreach (self::$thresholds as $metric => $threshold) {
            $key = str_replace('_per_hour', '', $metric);
            $value = $currentStats[$key] ?? 0;
            
            if ($value >= $threshold) {
                self::triggerAlert("Threshold exceeded: $metric ($value >= $threshold)");
            }
        }
    }
    
    /**
     * Trigger security alert
     */
    private static function triggerAlert($message) {
        error_log("SECURITY ALERT: $message");
        // In production, you could send email, SMS, or webhook notification here
    }
    
    /**
     * Get security statistics
     */
    public static function getStats($hours = 24) {
        $stats = [];
        if (file_exists(self::$statsFile)) {
            $stats = json_decode(file_get_contents(self::$statsFile), true) ?: [];
        }
        
        $now = time();
        $result = [];
        
        foreach ($stats as $key => $value) {
            $statTime = strtotime(str_replace('-', ' ', $key));
            if ($now - $statTime <= ($hours * 3600)) {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Get recent alerts
     */
    public static function getRecentAlerts($limit = 50) {
        if (self::$alertFile === null) {
            self::init();
        }
        
        if (!file_exists(self::$alertFile)) {
            return [];
        }
        
        $alerts = json_decode(file_get_contents(self::$alertFile), true) ?: [];
        return array_slice(array_reverse($alerts), 0, $limit);
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


<?php
/**
 * Database Monitor Plugin
 * Monitors database queries for suspicious activity
 */

class DatabaseMonitor {
    private static $logFile = null;
    private static $queryLog = [];
    private static $suspiciousPatterns = [
        '/\bDROP\b/i',
        '/\bTRUNCATE\b/i',
        '/\bALTER\b.*\bTABLE\b/i',
        '/\bCREATE\b.*\bTABLE\b/i',
        '/\bGRANT\b/i',
        '/\bREVOKE\b/i',
        '/\bFLUSH\b/i',
        '/\bDELETE\b.*\bFROM\b.*WHERE\s+1\s*=\s*1/i',
        '/\bUPDATE\b.*\bSET\b.*WHERE\s+1\s*=\s*1/i',
    ];
    
    /**
     * Initialize database monitor
     */
    public static function init() {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        self::$logFile = $logDir . '/database_queries.json';
    }
    
    /**
     * Log database query
     */
    public static function logQuery($sql, $params = [], $executionTime = null) {
        if (self::$logFile === null) {
            self::init();
        }
        
        // Check for suspicious patterns
        $isSuspicious = false;
        foreach (self::$suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                $isSuspicious = true;
                break;
            }
        }
        
        $query = [
            'sql' => substr($sql, 0, 500), // Limit length
            'params' => self::sanitizeParams($params),
            'execution_time' => $executionTime,
            'is_suspicious' => $isSuspicious,
            'ip' => self::getClientIP(),
            'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
            'timestamp' => time(),
        ];
        
        if ($isSuspicious) {
            error_log("SUSPICIOUS DATABASE QUERY: " . substr($sql, 0, 200));
            if (class_exists('SecurityMonitor')) {
                SecurityMonitor::logEvent('SUSPICIOUS_QUERY', 'high', "Suspicious database query detected", $query);
            }
        }
        
        self::$queryLog[] = $query;
        
        // Keep only last 1000 queries
        if (count(self::$queryLog) > 1000) {
            self::$queryLog = array_slice(self::$queryLog, -1000);
        }
        
        // Save to file periodically (every 10 queries)
        if (count(self::$queryLog) % 10 === 0) {
            self::saveLog();
        }
    }
    
    /**
     * Save query log to file
     */
    private static function saveLog() {
        if (self::$logFile === null) {
            return;
        }
        
        $existing = [];
        if (file_exists(self::$logFile)) {
            $existing = json_decode(file_get_contents(self::$logFile), true) ?: [];
        }
        
        $combined = array_merge($existing, self::$queryLog);
        
        // Keep only last 5000 queries total
        if (count($combined) > 5000) {
            $combined = array_slice($combined, -5000);
        }
        
        file_put_contents(self::$logFile, json_encode($combined));
        self::$queryLog = [];
    }
    
    /**
     * Sanitize parameters for logging
     */
    private static function sanitizeParams($params) {
        $sanitized = [];
        foreach ($params as $param) {
            if (is_string($param)) {
                // Truncate long strings
                $sanitized[] = substr($param, 0, 100);
            } else {
                $sanitized[] = $param;
            }
        }
        return $sanitized;
    }
    
    /**
     * Get query statistics
     */
    public static function getStats($hours = 24) {
        if (self::$logFile === null) {
            self::init();
        }
        
        if (!file_exists(self::$logFile)) {
            return [];
        }
        
        $queries = json_decode(file_get_contents(self::$logFile), true) ?: [];
        $now = time();
        $cutoff = $now - ($hours * 3600);
        
        $stats = [
            'total' => 0,
            'suspicious' => 0,
            'avg_execution_time' => 0,
            'slow_queries' => 0,
        ];
        
        $executionTimes = [];
        
        foreach ($queries as $query) {
            if ($query['timestamp'] < $cutoff) {
                continue;
            }
            
            $stats['total']++;
            
            if ($query['is_suspicious']) {
                $stats['suspicious']++;
            }
            
            if ($query['execution_time'] !== null) {
                $executionTimes[] = $query['execution_time'];
                if ($query['execution_time'] > 1.0) { // Slow query threshold
                    $stats['slow_queries']++;
                }
            }
        }
        
        if (count($executionTimes) > 0) {
            $stats['avg_execution_time'] = array_sum($executionTimes) / count($executionTimes);
        }
        
        return $stats;
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


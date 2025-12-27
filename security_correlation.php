<?php
/**
 * Security Event Correlation Plugin
 * Correlates security events to detect advanced attack patterns
 */

class SecurityCorrelation {
    private static $correlationFile = null;
    private static $timeWindow = 300; // 5 minutes
    
    /**
     * Initialize correlation engine
     */
    public static function init() {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        self::$correlationFile = $logDir . '/security_correlation.json';
    }
    
    /**
     * Correlate security events
     */
    public static function correlate($eventType, $details = []) {
        if (self::$correlationFile === null) {
            self::init();
        }
        
        $ip = $details['ip'] ?? self::getClientIP();
        $now = time();
        
        // Load correlation data
        $correlations = [];
        if (file_exists(self::$correlationFile)) {
            $correlations = json_decode(file_get_contents(self::$correlationFile), true) ?: [];
        }
        
        $ipKey = md5($ip);
        
        // Initialize IP tracking
        if (!isset($correlations[$ipKey])) {
            $correlations[$ipKey] = [
                'events' => [],
                'patterns' => [],
                'first_seen' => $now,
                'last_seen' => $now
            ];
        }
        
        $ipData = &$correlations[$ipKey];
        
        // Add current event
        $ipData['events'][] = [
            'type' => $eventType,
            'time' => $now,
            'details' => $details
        ];
        
        // Clean old events
        $ipData['events'] = array_filter($ipData['events'], function($event) use ($now) {
            return ($now - $event['time']) <= self::$timeWindow;
        });
        
        $ipData['last_seen'] = $now;
        
        // Detect attack patterns
        $patterns = self::detectPatterns($ipData['events']);
        
        foreach ($patterns as $pattern) {
            if (!in_array($pattern, $ipData['patterns'])) {
                $ipData['patterns'][] = $pattern;
                
                // Log correlated attack
                if (class_exists('SecurityMonitor')) {
                    SecurityMonitor::logEvent('CORRELATED_ATTACK', 'high', "Attack pattern detected: $pattern", [
                        'ip' => $ip,
                        'events_count' => count($ipData['events'])
                    ]);
                }
                
                // Trigger response
                if (class_exists('ThreatResponse')) {
                    ThreatResponse::handleThreat('CORRELATED_ATTACK', 'high', [
                        'ip' => $ip,
                        'pattern' => $pattern
                    ]);
                }
                
                // Update reputation
                if (class_exists('IPReputation')) {
                    IPReputation::updateReputation($ip, -30);
                }
            }
        }
        
        // Save correlation data
        file_put_contents(self::$correlationFile, json_encode($correlations));
        
        return $patterns;
    }
    
    /**
     * Detect attack patterns from events
     */
    private static function detectPatterns($events) {
        $patterns = [];
        $eventTypes = array_column($events, 'type');
        $eventCount = count($events);
        
        // Pattern: Multiple failed logins followed by SQL injection
        $hasFailedLogin = in_array('FAILED_LOGIN', $eventTypes);
        $hasSQLInjection = in_array('SQL_INJECTION', $eventTypes);
        if ($hasFailedLogin && $hasSQLInjection) {
            $patterns[] = 'BRUTE_FORCE_THEN_SQL_INJECTION';
        }
        
        // Pattern: Multiple different attack types (multi-vector attack)
        $uniqueAttackTypes = array_unique(array_filter($eventTypes, function($type) {
            return in_array($type, ['SQL_INJECTION', 'XSS', 'COMMAND_INJECTION', 'PATH_TRAVERSAL']);
        }));
        
        if (count($uniqueAttackTypes) >= 3) {
            $patterns[] = 'MULTI_VECTOR_ATTACK';
        }
        
        // Pattern: Rapid security events (automated attack)
        if ($eventCount > 10) {
            $timeSpan = end($events)['time'] - reset($events)['time'];
            if ($timeSpan > 0 && ($eventCount / $timeSpan) > 0.1) {
                $patterns[] = 'RAPID_AUTOMATED_ATTACK';
            }
        }
        
        // Pattern: Bot detection + attack attempts
        $hasBot = in_array('BOT_DETECTED', $eventTypes);
        $hasAttack = in_array('SQL_INJECTION', $eventTypes) || 
                     in_array('XSS', $eventTypes) ||
                     in_array('COMMAND_INJECTION', $eventTypes);
        if ($hasBot && $hasAttack) {
            $patterns[] = 'BOT_ATTACK';
        }
        
        // Pattern: WAF blocks + rate limit (sophisticated attacker)
        $hasWAFBlock = in_array('WAF_BLOCKED', $eventTypes);
        $hasRateLimit = in_array('RATE_LIMIT_EXCEEDED', $eventTypes);
        if ($hasWAFBlock && $hasRateLimit) {
            $patterns[] = 'PERSISTENT_ATTACKER';
        }
        
        return $patterns;
    }
    
    /**
     * Get correlation data for IP
     */
    public static function getCorrelationData($ip) {
        if (self::$correlationFile === null) {
            self::init();
        }
        
        if (!file_exists(self::$correlationFile)) {
            return null;
        }
        
        $correlations = json_decode(file_get_contents(self::$correlationFile), true) ?: [];
        $ipKey = md5($ip);
        
        return $correlations[$ipKey] ?? null;
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


<?php
/**
 * Behavioral Analysis Plugin
 * Analyzes user behavior patterns to detect anomalies
 */

class BehavioralAnalysis {
    private static $behaviorFile = null;
    private static $suspiciousPatterns = [];
    
    /**
     * Initialize behavioral analysis
     */
    public static function init() {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        self::$behaviorFile = $logDir . '/user_behavior.json';
    }
    
    /**
     * Analyze request behavior
     */
    public static function analyze($userId = null) {
        if (self::$behaviorFile === null) {
            self::init();
        }
        
        $identifier = $userId ? "user_$userId" : self::getClientIP();
        $now = time();
        
        // Load behavior data
        $behaviors = [];
        if (file_exists(self::$behaviorFile)) {
            $behaviors = json_decode(file_get_contents(self::$behaviorFile), true) ?: [];
        }
        
        // Initialize behavior tracking
        if (!isset($behaviors[$identifier])) {
            $behaviors[$identifier] = [
                'requests' => [],
                'actions' => [],
                'patterns' => [],
                'first_seen' => $now,
                'last_seen' => $now,
                'risk_score' => 0
            ];
        }
        
        $behavior = &$behaviors[$identifier];
        
        // Record current request
        $request = [
            'time' => $now,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
        ];
        
        $behavior['requests'][] = $request;
        $behavior['last_seen'] = $now;
        
        // Keep only last 100 requests
        if (count($behavior['requests']) > 100) {
            $behavior['requests'] = array_slice($behavior['requests'], -100);
        }
        
        // Analyze patterns
        $anomalies = self::detectAnomalies($behavior);
        
        if (count($anomalies) > 0) {
            $behavior['risk_score'] += count($anomalies) * 10;
            
            // Log anomalies
            foreach ($anomalies as $anomaly) {
                if (class_exists('SecurityMonitor')) {
                    SecurityMonitor::logEvent('BEHAVIORAL_ANOMALY', 'medium', $anomaly, [
                        'identifier' => $identifier,
                        'risk_score' => $behavior['risk_score']
                    ]);
                }
            }
            
            // High risk score triggers action
            if ($behavior['risk_score'] > 50) {
                if (class_exists('IPReputation') && !$userId) {
                    IPReputation::updateReputation(self::getClientIP(), -20);
                }
                
                if (class_exists('ThreatResponse')) {
                    ThreatResponse::handleThreat('SUSPICIOUS_BEHAVIOR', 'high', [
                        'identifier' => $identifier,
                        'risk_score' => $behavior['risk_score']
                    ]);
                }
            }
        }
        
        // Decay risk score over time
        $timeSinceFirstSeen = $now - $behavior['first_seen'];
        if ($timeSinceFirstSeen > 3600) {
            $behavior['risk_score'] = max(0, $behavior['risk_score'] - 1);
        }
        
        // Save behavior data
        file_put_contents(self::$behaviorFile, json_encode($behaviors));
        
        return [
            'risk_score' => $behavior['risk_score'],
            'anomalies' => $anomalies
        ];
    }
    
    /**
     * Detect behavioral anomalies
     */
    private static function detectAnomalies($behavior) {
        $anomalies = [];
        $requests = $behavior['requests'];
        
        if (count($requests) < 3) {
            return $anomalies; // Not enough data
        }
        
        // Check for rapid requests (bot-like behavior)
        $recentRequests = array_slice($requests, -10);
        $timeSpan = end($recentRequests)['time'] - reset($recentRequests)['time'];
        if ($timeSpan > 0 && count($recentRequests) / $timeSpan > 2) {
            $anomalies[] = 'Rapid request pattern detected';
        }
        
        // Check for unusual URI patterns
        $uris = array_column($recentRequests, 'uri');
        $uniqueUris = count(array_unique($uris));
        if ($uniqueUris / count($uris) > 0.9 && count($uris) > 5) {
            $anomalies[] = 'Unusual URI access pattern';
        }
        
        // Check for missing referrer on POST requests
        $postRequests = array_filter($recentRequests, function($r) {
            return $r['method'] === 'POST';
        });
        if (count($postRequests) > 3) {
            $anomalies[] = 'Multiple POST requests without referrer';
        }
        
        // Check for user agent changes
        $userAgents = array_unique(array_column($recentRequests, 'user_agent'));
        if (count($userAgents) > 3) {
            $anomalies[] = 'Frequent user agent changes';
        }
        
        return $anomalies;
    }
    
    /**
     * Get behavior risk score
     */
    public static function getRiskScore($userId = null) {
        if (self::$behaviorFile === null) {
            self::init();
        }
        
        $identifier = $userId ? "user_$userId" : self::getClientIP();
        
        $behaviors = [];
        if (file_exists(self::$behaviorFile)) {
            $behaviors = json_decode(file_get_contents(self::$behaviorFile), true) ?: [];
        }
        
        return $behaviors[$identifier]['risk_score'] ?? 0;
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


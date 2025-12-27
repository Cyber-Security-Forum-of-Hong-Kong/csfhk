<?php
/**
 * Intrusion Detection System (IDS)
 * Detects intrusion patterns and suspicious activities
 */

class IntrusionDetection {
    private static $idsFile = null;
    private static $patterns = [
        'sql_injection' => [
            '/(\bUNION\b.*\bSELECT\b)/i',
            '/(\bOR\b.*=.*)/i',
            '/(\bAND\b.*=.*)/i',
            '/(\bEXEC\b|\bEXECUTE\b)/i',
            '/(\bDROP\b.*\bTABLE\b)/i',
            '/(\bINSERT\b.*\bINTO\b)/i',
            '/(\bDELETE\b.*\bFROM\b)/i',
            '/(\bUPDATE\b.*\bSET\b)/i',
        ],
        'xss' => [
            '/<script/i',
            '/javascript:/i',
            '/onerror=/i',
            '/onload=/i',
            '/onclick=/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
        ],
        'command_injection' => [
            '/[;&|`$(){}]/',
            '/\b(cat|ls|pwd|whoami|id|uname)\b/i',
            '/\b(nc|netcat|wget|curl|ping)\b/i',
            '/\b(rm|del|delete|mv|cp)\b/i',
        ],
        'path_traversal' => [
            '/\.\.\//',
            '/\.\.\\\\/',
            '/\/etc\/passwd/i',
            '/\/proc\/self/i',
            '/\/windows\/system32/i',
        ],
        'file_inclusion' => [
            '/include.*\$_(GET|POST|COOKIE)/i',
            '/require.*\$_(GET|POST|COOKIE)/i',
            '/include_once.*\$_(GET|POST|COOKIE)/i',
            '/require_once.*\$_(GET|POST|COOKIE)/i',
        ],
    ];
    
    /**
     * Initialize IDS
     */
    public static function init() {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        self::$idsFile = $logDir . '/ids_detections.json';
    }
    
    /**
     * Scan request for intrusion patterns
     */
    public static function scanRequest() {
        $detections = [];
        $severity = 'low';
        
        // Scan all input
        $inputs = array_merge(
            $_GET ?? [],
            $_POST ?? [],
            $_COOKIE ?? [],
            $_SERVER ?? []
        );
        
        foreach ($inputs as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            
            $valueStr = (string)$value;
            
            // Check each pattern type
            foreach (self::$patterns as $type => $patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $valueStr)) {
                        $detections[] = [
                            'type' => $type,
                            'field' => $key,
                            'pattern' => $pattern,
                            'value' => substr($valueStr, 0, 100), // Truncate for logging
                            'time' => time()
                        ];
                        
                        if ($type === 'sql_injection' || $type === 'command_injection') {
                            $severity = 'critical';
                        } elseif ($type === 'file_inclusion') {
                            $severity = 'high';
                        } else {
                            $severity = 'medium';
                        }
                    }
                }
            }
        }
        
        if (!empty($detections)) {
            self::logDetection($detections, $severity);
            return false;
        }
        
        return true;
    }
    
    /**
     * Log intrusion detection
     */
    private static function logDetection($detections, $severity) {
        $ip = self::getClientIP();
        
        // Load detection history
        $history = [];
        if (file_exists(self::$idsFile)) {
            $history = json_decode(file_get_contents(self::$idsFile), true) ?: [];
        }
        
        $ipKey = md5($ip);
        
        // Initialize IP tracking
        if (!isset($history[$ipKey])) {
            $history[$ipKey] = [
                'detections' => [],
                'first_seen' => time(),
                'last_seen' => time(),
                'count' => 0
            ];
        }
        
        $ipData = &$history[$ipKey];
        
        // Add detections
        foreach ($detections as $detection) {
            $ipData['detections'][] = $detection;
            $ipData['count']++;
        }
        
        // Keep only last 50 detections
        if (count($ipData['detections']) > 50) {
            $ipData['detections'] = array_slice($ipData['detections'], -50);
        }
        
        $ipData['last_seen'] = time();
        
        // Save history
        file_put_contents(self::$idsFile, json_encode($history));
        
        // Log to security monitor
        if (class_exists('SecurityMonitor')) {
            SecurityMonitor::logEvent('IDS_DETECTION', $severity, 
                "Intrusion detected: " . count($detections) . " patterns", [
                    'ip' => $ip,
                    'detections' => $detections
                ]);
        }
        
        // Update IP reputation
        if (class_exists('IPReputation')) {
            $reputationChange = $severity === 'critical' ? -30 : ($severity === 'high' ? -20 : -10);
            IPReputation::updateReputation($ip, $reputationChange);
        }
        
        // Trigger threat response
        if (class_exists('ThreatResponse')) {
            ThreatResponse::handleThreat('INTRUSION_DETECTED', $severity, [
                'ip' => $ip,
                'detections' => $detections
            ]);
        }
        
        // Correlate with other events
        if (class_exists('SecurityCorrelation')) {
            foreach ($detections as $detection) {
                SecurityCorrelation::correlate(strtoupper($detection['type']), [
                    'ip' => $ip,
                    'field' => $detection['field']
                ]);
            }
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


<?php
/**
 * Device Fingerprinting Plugin
 * Creates unique fingerprints for devices to detect suspicious activity
 */

class DeviceFingerprinting {
    private static $fingerprintFile = null;
    
    /**
     * Initialize device fingerprinting
     */
    public static function init() {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        self::$fingerprintFile = $logDir . '/device_fingerprints.json';
    }
    
    /**
     * Generate device fingerprint
     */
    public static function generateFingerprint() {
        $components = [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            'accept_encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            'accept' => $_SERVER['HTTP_ACCEPT'] ?? '',
            'connection' => $_SERVER['HTTP_CONNECTION'] ?? '',
            'screen_resolution' => $_POST['screen_res'] ?? '',
            'timezone' => $_POST['timezone'] ?? '',
        ];
        
        $fingerprint = hash('sha256', json_encode($components));
        return $fingerprint;
    }
    
    /**
     * Check device fingerprint for anomalies
     */
    public static function checkFingerprint($userId = null) {
        if (self::$fingerprintFile === null) {
            self::init();
        }
        
        $fingerprint = self::generateFingerprint();
        $ip = self::getClientIP();
        $identifier = $userId ? "user_$userId" : "ip_$ip";
        
        // Load fingerprints
        $fingerprints = [];
        if (file_exists(self::$fingerprintFile)) {
            $fingerprints = json_decode(file_get_contents(self::$fingerprintFile), true) ?: [];
        }
        
        // Initialize identifier tracking
        if (!isset($fingerprints[$identifier])) {
            $fingerprints[$identifier] = [
                'fingerprints' => [],
                'first_seen' => time(),
                'last_seen' => time(),
                'suspicious_changes' => 0
            ];
        }
        
        $data = &$fingerprints[$identifier];
        
        // Check if fingerprint changed
        if (!empty($data['fingerprints'])) {
            $lastFingerprint = end($data['fingerprints']);
            if ($lastFingerprint['fingerprint'] !== $fingerprint) {
                $data['suspicious_changes']++;
                
                // Multiple fingerprint changes indicate suspicious activity
                if ($data['suspicious_changes'] > 3) {
                    if (class_exists('SecurityMonitor')) {
                        SecurityMonitor::logEvent('DEVICE_FINGERPRINT_ANOMALY', 'high', 
                            "Multiple device fingerprint changes detected for $identifier");
                    }
                    
                    if (class_exists('IPReputation')) {
                        IPReputation::updateReputation($ip, -15);
                    }
                    
                    return false;
                }
            }
        }
        
        // Record fingerprint
        $data['fingerprints'][] = [
            'fingerprint' => $fingerprint,
            'time' => time(),
            'ip' => $ip
        ];
        
        // Keep only last 10 fingerprints
        if (count($data['fingerprints']) > 10) {
            $data['fingerprints'] = array_slice($data['fingerprints'], -10);
        }
        
        $data['last_seen'] = time();
        
        // Save fingerprints
        file_put_contents(self::$fingerprintFile, json_encode($fingerprints));
        
        return true;
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


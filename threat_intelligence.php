<?php
/**
 * Threat Intelligence Plugin
 * Integrates threat intelligence feeds and known bad actors
 */

class ThreatIntelligence {
    private static $threatFile = null;
    private static $knownBadIPs = [];
    private static $knownBadUserAgents = [
        'sqlmap', 'nikto', 'nmap', 'masscan', 'zap', 'burp',
        'w3af', 'acunetix', 'nessus', 'openvas', 'metasploit'
    ];
    private static $knownBadCountries = []; // Can be populated from threat feeds
    
    /**
     * Initialize threat intelligence
     */
    public static function init() {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        self::$threatFile = $logDir . '/threat_intelligence.json';
        
        // Load known bad IPs
        if (file_exists(self::$threatFile)) {
            $data = json_decode(file_get_contents(self::$threatFile), true) ?: [];
            self::$knownBadIPs = $data['bad_ips'] ?? [];
        }
    }
    
    /**
     * Check IP against threat intelligence
     */
    public static function checkIP($ip) {
        // Check against known bad IPs
        if (in_array($ip, self::$knownBadIPs)) {
            return [
                'threat' => true,
                'reason' => 'Known malicious IP',
                'severity' => 'high'
            ];
        }
        
        // Check IP range (basic implementation)
        foreach (self::$knownBadIPs as $badIP) {
            if (self::ipInRange($ip, $badIP)) {
                return [
                    'threat' => true,
                    'reason' => 'IP in known malicious range',
                    'severity' => 'medium'
                ];
            }
        }
        
        return ['threat' => false];
    }
    
    /**
     * Check user agent against threat intelligence
     */
    public static function checkUserAgent($userAgent) {
        $uaLower = strtolower($userAgent);
        
        foreach (self::$knownBadUserAgents as $badUA) {
            if (strpos($uaLower, $badUA) !== false) {
                return [
                    'threat' => true,
                    'reason' => 'Known malicious user agent',
                    'severity' => 'high'
                ];
            }
        }
        
        return ['threat' => false];
    }
    
    /**
     * Add IP to threat list
     */
    public static function addThreatIP($ip, $reason = '') {
        if (!in_array($ip, self::$knownBadIPs)) {
            self::$knownBadIPs[] = $ip;
            
            // Save to file
            $data = [
                'bad_ips' => self::$knownBadIPs,
                'updated' => time()
            ];
            file_put_contents(self::$threatFile, json_encode($data));
            
            // Log
            if (class_exists('SecurityMonitor')) {
                SecurityMonitor::logEvent('THREAT_IP_ADDED', 'medium', 
                    "IP added to threat list: $ip", ['reason' => $reason]);
            }
        }
    }
    
    /**
     * Check if IP is in range (basic CIDR check)
     */
    private static function ipInRange($ip, $range) {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        list($subnet, $mask) = explode('/', $range);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - (int)$mask);
        
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
    
    /**
     * Get threat score for request
     */
    public static function getThreatScore($ip, $userAgent = '') {
        $score = 0;
        
        // Check IP
        $ipCheck = self::checkIP($ip);
        if ($ipCheck['threat']) {
            $score += $ipCheck['severity'] === 'high' ? 50 : 25;
        }
        
        // Check user agent
        if (!empty($userAgent)) {
            $uaCheck = self::checkUserAgent($userAgent);
            if ($uaCheck['threat']) {
                $score += $uaCheck['severity'] === 'high' ? 30 : 15;
            }
        }
        
        return min(100, $score);
    }
}


<?php
/**
 * IP Reputation Plugin
 * Checks IP reputation and maintains blacklist/whitelist
 */

class IPReputation {
    private static $blacklistFile = null;
    private static $whitelistFile = null;
    private static $reputationFile = null;
    
    // Known malicious IP ranges (example - in production, use threat intelligence feeds)
    private static $knownMaliciousRanges = [
        // Add known malicious IP ranges here
    ];
    
    /**
     * Initialize IP reputation system
     */
    public static function init() {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        self::$blacklistFile = $logDir . '/ip_blacklist.json';
        self::$whitelistFile = $logDir . '/ip_whitelist.json';
        self::$reputationFile = $logDir . '/ip_reputation.json';
    }
    
    /**
     * Check if IP is blacklisted
     */
    public static function isBlacklisted($ip) {
        if (self::$blacklistFile === null) {
            self::init();
        }
        
        // Check static blacklist
        $blacklist = [];
        if (file_exists(self::$blacklistFile)) {
            $blacklist = json_decode(file_get_contents(self::$blacklistFile), true) ?: [];
        }
        
        foreach ($blacklist as $entry) {
            if (self::ipMatches($ip, $entry['ip'])) {
                // Check if blacklist entry is still valid
                if (!isset($entry['expires']) || $entry['expires'] > time()) {
                    return true;
                }
            }
        }
        
        // Check known malicious ranges
        foreach (self::$knownMaliciousRanges as $range) {
            if (self::ipInRange($ip, $range)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if IP is whitelisted
     */
    public static function isWhitelisted($ip) {
        if (self::$whitelistFile === null) {
            self::init();
        }
        
        $whitelist = [];
        if (file_exists(self::$whitelistFile)) {
            $whitelist = json_decode(file_get_contents(self::$whitelistFile), true) ?: [];
        }
        
        foreach ($whitelist as $entry) {
            if (self::ipMatches($ip, $entry['ip'])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Add IP to blacklist
     */
    public static function blacklistIP($ip, $reason = '', $duration = 86400) {
        if (self::$blacklistFile === null) {
            self::init();
        }
        
        $blacklist = [];
        if (file_exists(self::$blacklistFile)) {
            $blacklist = json_decode(file_get_contents(self::$blacklistFile), true) ?: [];
        }
        
        // Check if already blacklisted
        foreach ($blacklist as $key => $entry) {
            if (self::ipMatches($ip, $entry['ip'])) {
                // Update existing entry
                $blacklist[$key] = [
                    'ip' => $ip,
                    'reason' => $reason,
                    'added' => time(),
                    'expires' => time() + $duration
                ];
                file_put_contents(self::$blacklistFile, json_encode($blacklist));
                return;
            }
        }
        
        // Add new entry
        $blacklist[] = [
            'ip' => $ip,
            'reason' => $reason,
            'added' => time(),
            'expires' => time() + $duration
        ];
        
        file_put_contents(self::$blacklistFile, json_encode($blacklist));
    }
    
    /**
     * Update IP reputation score
     */
    public static function updateReputation($ip, $scoreChange) {
        if (self::$reputationFile === null) {
            self::init();
        }
        
        $reputations = [];
        if (file_exists(self::$reputationFile)) {
            $reputations = json_decode(file_get_contents(self::$reputationFile), true) ?: [];
        }
        
        $ipKey = md5($ip);
        
        if (!isset($reputations[$ipKey])) {
            $reputations[$ipKey] = [
                'ip' => $ip,
                'score' => 0,
                'last_seen' => time()
            ];
        }
        
        $reputations[$ipKey]['score'] += $scoreChange;
        $reputations[$ipKey]['last_seen'] = time();
        
        // Auto-blacklist if reputation too low
        if ($reputations[$ipKey]['score'] < -100) {
            self::blacklistIP($ip, 'Low reputation score', 86400 * 7); // 7 days
        }
        
        file_put_contents(self::$reputationFile, json_encode($reputations));
    }
    
    /**
     * Get IP reputation score
     */
    public static function getReputation($ip) {
        if (self::$reputationFile === null) {
            self::init();
        }
        
        $reputations = [];
        if (file_exists(self::$reputationFile)) {
            $reputations = json_decode(file_get_contents(self::$reputationFile), true) ?: [];
        }
        
        $ipKey = md5($ip);
        return $reputations[$ipKey]['score'] ?? 0;
    }
    
    /**
     * Check if IP matches pattern (supports CIDR)
     */
    private static function ipMatches($ip, $pattern) {
        if ($ip === $pattern) {
            return true;
        }
        
        // Check CIDR notation
        if (strpos($pattern, '/') !== false) {
            return self::ipInRange($ip, $pattern);
        }
        
        return false;
    }
    
    /**
     * Check if IP is in range (CIDR notation)
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
     * Check if IP is from known proxy/VPN
     */
    public static function isProxy($ip) {
        // In production, use a service like MaxMind, IP2Location, or similar
        // This is a placeholder
        return false;
    }
    
    /**
     * Get IP geolocation info (basic)
     */
    public static function getGeoInfo($ip) {
        // In production, use MaxMind GeoIP2 or similar service
        // This is a placeholder
        return [
            'country' => 'Unknown',
            'city' => 'Unknown',
            'is_proxy' => false
        ];
    }
}


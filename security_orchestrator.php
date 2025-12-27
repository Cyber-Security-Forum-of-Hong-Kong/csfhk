<?php
/**
 * Security Orchestrator Plugin
 * Coordinates all security plugins and provides unified interface
 */

class SecurityOrchestrator {
    /**
     * Initialize all security systems
     */
    public static function init() {
        // Load all security plugins
        $plugins = [
            'security_headers.php',
            'security.php',
            'advanced_security.php',
            'security_monitor.php',
            'bot_detection.php',
            'ip_reputation.php',
            'request_signature.php',
            'file_integrity.php',
            'encryption.php',
            'database_monitor.php',
            'threat_response.php',
            'security_audit.php',
            'rate_limiter_advanced.php',
            'session_security.php',
            'input_validator.php',
            'waf.php',
        ];
        
        foreach ($plugins as $plugin) {
            $path = __DIR__ . '/' . $plugin;
            if (file_exists($path)) {
                require_once $path;
            }
        }
        
        // Initialize all systems
        if (class_exists('SecurityHeaders')) {
            SecurityHeaders::setAll();
        }
        
        if (class_exists('SessionSecurity')) {
            SessionSecurity::init();
        }
        
        if (class_exists('SecurityMonitor')) {
            SecurityMonitor::init();
        }
        
        if (class_exists('BotDetection')) {
            BotDetection::init();
        }
        
        if (class_exists('IPReputation')) {
            IPReputation::init();
        }
        
        if (class_exists('RequestSignature')) {
            RequestSignature::init();
        }
        
        if (class_exists('FileIntegrity')) {
            FileIntegrity::init();
        }
        
        if (class_exists('DatabaseMonitor')) {
            DatabaseMonitor::init();
        }
        
        if (class_exists('ThreatResponse')) {
            ThreatResponse::init();
        }
        
        if (class_exists('SecurityAudit')) {
            SecurityAudit::init();
        }
        
        if (class_exists('AdvancedRateLimiter')) {
            AdvancedRateLimiter::init();
        }
        
        if (class_exists('AdvancedSecurity')) {
            AdvancedSecurity::init();
        }
        
        if (class_exists('WAF')) {
            WAF::init();
        }
    }
    
    /**
     * Perform comprehensive security check
     */
    public static function performSecurityCheck() {
        $results = [];
        
        // Check file integrity
        if (class_exists('FileIntegrity')) {
            $violations = FileIntegrity::checkIntegrity();
            $results['file_integrity'] = [
                'status' => count($violations) === 0 ? 'ok' : 'warning',
                'violations' => count($violations)
            ];
        }
        
        // Check IP reputation
        $clientIP = self::getClientIP();
        if (class_exists('IPReputation')) {
            $reputation = IPReputation::getReputation($clientIP);
            $results['ip_reputation'] = [
                'status' => $reputation >= 0 ? 'ok' : 'warning',
                'score' => $reputation
            ];
        }
        
        // Get security statistics
        if (class_exists('SecurityMonitor')) {
            $stats = SecurityMonitor::getStats(24);
            $results['security_stats'] = $stats;
        }
        
        // Get database query stats
        if (class_exists('DatabaseMonitor')) {
            $dbStats = DatabaseMonitor::getStats(24);
            $results['database_stats'] = $dbStats;
        }
        
        // Perform security audit
        if (class_exists('SecurityAudit')) {
            $audit = SecurityAudit::performAudit();
            $results['security_audit'] = [
                'score' => $audit['score'],
                'status' => $audit['status']
            ];
        }
        
        return $results;
    }
    
    /**
     * Get security dashboard data
     */
    public static function getDashboardData() {
        $data = [
            'timestamp' => time(),
            'security_score' => 0,
            'threats_blocked_24h' => 0,
            'failed_logins_24h' => 0,
            'blocked_ips' => 0,
            'recent_alerts' => [],
            'system_status' => []
        ];
        
        // Get statistics
        if (class_exists('SecurityMonitor')) {
            $stats = SecurityMonitor::getStats(24);
            $hour = date('Y-m-d-H');
            $currentStats = $stats[$hour] ?? [];
            
            $data['threats_blocked_24h'] = array_sum(array_column($stats, 'blocked_requests'));
            $data['failed_logins_24h'] = array_sum(array_column($stats, 'failed_logins'));
            $data['recent_alerts'] = SecurityMonitor::getRecentAlerts(10);
        }
        
        // Get blocked IPs count
        if (class_exists('IPReputation')) {
            $blacklistFile = __DIR__ . '/logs/ip_blacklist.json';
            if (file_exists($blacklistFile)) {
                $blacklist = json_decode(file_get_contents($blacklistFile), true) ?: [];
                $data['blocked_ips'] = count($blacklist);
            }
        }
        
        // Get security audit score
        if (class_exists('SecurityAudit')) {
            $audit = SecurityAudit::getLatestAudit();
            if ($audit) {
                $data['security_score'] = $audit['score'];
            }
        }
        
        // System status
        $data['system_status'] = [
            'waf' => class_exists('WAF') ? 'active' : 'inactive',
            'bot_detection' => class_exists('BotDetection') ? 'active' : 'inactive',
            'ip_reputation' => class_exists('IPReputation') ? 'active' : 'inactive',
            'security_monitor' => class_exists('SecurityMonitor') ? 'active' : 'inactive',
            'file_integrity' => class_exists('FileIntegrity') ? 'active' : 'inactive',
            'database_monitor' => class_exists('DatabaseMonitor') ? 'active' : 'inactive',
        ];
        
        return $data;
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


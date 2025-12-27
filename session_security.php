<?php
/**
 * Enhanced Session Security Plugin
 * Advanced session protection and management
 */

class SessionSecurity {
    /**
     * Initialize secure session
     */
    public static function init() {
        // Configure secure session settings
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_lifetime', 0);
        ini_set('session.gc_maxlifetime', 3600);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_path', '/');
        
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Validate session
        self::validateSession();
    }
    
    /**
     * Validate session security
     */
    private static function validateSession() {
        // Check if session is new or needs regeneration
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
            $_SESSION['ip_address'] = self::getClientIP();
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
        
        // Regenerate session ID periodically (every 30 minutes)
        if (time() - $_SESSION['created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
        
        // Check for session hijacking
        if (isset($_SESSION['ip_address'])) {
            $currentIP = self::getClientIP();
            if ($_SESSION['ip_address'] !== $currentIP) {
                // IP changed - could be legitimate or attack
                // Log but don't block immediately (mobile networks change IPs)
                if (class_exists('SecurityMonitor')) {
                    SecurityMonitor::logEvent('SESSION_IP_CHANGE', 'medium', "Session IP changed", [
                        'old_ip' => $_SESSION['ip_address'],
                        'new_ip' => $currentIP
                    ]);
                }
            }
        }
        
        // Check user agent
        if (isset($_SESSION['user_agent'])) {
            $currentUA = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if ($_SESSION['user_agent'] !== $currentUA) {
                // User agent changed - likely session hijacking
                session_destroy();
                if (class_exists('SecurityMonitor')) {
                    SecurityMonitor::logEvent('SESSION_HIJACK_ATTEMPT', 'critical', "Session hijacking attempt detected");
                }
                return false;
            }
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 3600) {
            session_destroy();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Secure session destroy
     */
    public static function destroy() {
        $_SESSION = [];
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/', '', true, true);
        }
        
        session_destroy();
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


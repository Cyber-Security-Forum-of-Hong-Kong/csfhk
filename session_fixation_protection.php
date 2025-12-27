<?php
/**
 * Session Fixation Protection Plugin
 * Protects against session fixation attacks
 */

class SessionFixationProtection {
    /**
     * Initialize session with fixation protection
     */
    public static function startSecureSession() {
        // Regenerate session ID on login
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerate ID periodically
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else {
            // Regenerate every 15 minutes
            if (time() - $_SESSION['created'] > 900) {
                self::regenerateSession();
            }
        }
        
        // Check for session hijacking indicators
        self::checkSessionHijacking();
    }
    
    /**
     * Regenerate session ID
     */
    public static function regenerateSession() {
        // Save session data
        $oldData = $_SESSION;
        
        // Regenerate ID
        session_regenerate_id(true);
        
        // Restore data
        $_SESSION = $oldData;
        $_SESSION['created'] = time();
        $_SESSION['regenerated'] = time();
    }
    
    /**
     * Check for session hijacking
     */
    private static function checkSessionHijacking() {
        $currentFingerprint = self::getSessionFingerprint();
        
        if (isset($_SESSION['fingerprint'])) {
            if ($_SESSION['fingerprint'] !== $currentFingerprint) {
                // Fingerprint changed - possible hijacking
                self::handleHijackingAttempt();
            }
        } else {
            // First time - set fingerprint
            $_SESSION['fingerprint'] = $currentFingerprint;
        }
    }
    
    /**
     * Get session fingerprint
     */
    private static function getSessionFingerprint() {
        $components = [
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];
        
        return hash('sha256', json_encode($components));
    }
    
    /**
     * Handle hijacking attempt
     */
    private static function handleHijackingAttempt() {
        $ip = self::getClientIP();
        
        // Log the attempt
        if (class_exists('SecurityMonitor')) {
            SecurityMonitor::logEvent('SESSION_HIJACKING_ATTEMPT', 'critical', 
                "Session hijacking attempt detected from IP: $ip");
        }
        
        // Destroy session
        session_destroy();
        
        // Start new session
        session_start();
        session_regenerate_id(true);
        
        // Update IP reputation
        if (class_exists('IPReputation')) {
            IPReputation::updateReputation($ip, -25);
            IPReputation::blacklistIP($ip, 'Session hijacking attempt', 3600);
        }
        
        // Trigger threat response
        if (class_exists('ThreatResponse')) {
            ThreatResponse::handleThreat('SESSION_HIJACKING', 'critical', ['ip' => $ip]);
        }
    }
    
    /**
     * Force session regeneration (e.g., on login)
     */
    public static function forceRegeneration() {
        self::regenerateSession();
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


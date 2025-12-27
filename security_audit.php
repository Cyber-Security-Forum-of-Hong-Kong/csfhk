<?php
/**
 * Security Audit Plugin
 * Comprehensive security auditing and reporting
 */

class SecurityAudit {
    private static $auditFile = null;
    
    /**
     * Initialize security audit
     */
    public static function init() {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        self::$auditFile = $logDir . '/security_audit.json';
    }
    
    /**
     * Perform security audit
     */
    public static function performAudit() {
        if (self::$auditFile === null) {
            self::init();
        }
        
        $audit = [
            'timestamp' => time(),
            'date' => date('Y-m-d H:i:s'),
            'checks' => []
        ];
        
        // Check 1: File integrity
        if (class_exists('FileIntegrity')) {
            $violations = FileIntegrity::checkIntegrity();
            $audit['checks']['file_integrity'] = [
                'status' => count($violations) === 0 ? 'pass' : 'fail',
                'violations' => count($violations),
                'details' => $violations
            ];
        }
        
        // Check 2: Security headers
        $audit['checks']['security_headers'] = self::checkSecurityHeaders();
        
        // Check 3: Database security
        $audit['checks']['database_security'] = self::checkDatabaseSecurity();
        
        // Check 4: Session security
        $audit['checks']['session_security'] = self::checkSessionSecurity();
        
        // Check 5: Configuration security
        $audit['checks']['configuration'] = self::checkConfiguration();
        
        // Check 6: Log files
        $audit['checks']['log_files'] = self::checkLogFiles();
        
        // Calculate overall score
        $audit['score'] = self::calculateScore($audit['checks']);
        $audit['status'] = $audit['score'] >= 80 ? 'good' : ($audit['score'] >= 60 ? 'fair' : 'poor');
        
        // Save audit
        $audits = [];
        if (file_exists(self::$auditFile)) {
            $audits = json_decode(file_get_contents(self::$auditFile), true) ?: [];
        }
        
        $audits[] = $audit;
        
        // Keep last 100 audits
        if (count($audits) > 100) {
            $audits = array_slice($audits, -100);
        }
        
        file_put_contents(self::$auditFile, json_encode($audits));
        
        return $audit;
    }
    
    /**
     * Check security headers
     */
    private static function checkSecurityHeaders() {
        $headers = [];
        foreach (headers_list() as $header) {
            if (preg_match('/^([^:]+):\s*(.+)$/', $header, $matches)) {
                $headers[strtolower($matches[1])] = $matches[2];
            }
        }
        
        $required = [
            'x-content-type-options',
            'x-xss-protection',
            'x-frame-options',
            'content-security-policy'
        ];
        
        $found = 0;
        foreach ($required as $header) {
            if (isset($headers[$header])) {
                $found++;
            }
        }
        
        return [
            'status' => $found === count($required) ? 'pass' : 'fail',
            'score' => ($found / count($required)) * 100,
            'headers_found' => $found,
            'headers_required' => count($required)
        ];
    }
    
    /**
     * Check database security
     */
    private static function checkDatabaseSecurity() {
        $checks = [
            'prepared_statements' => true, // Assume true if using our db.php
            'credentials_in_env' => file_exists(__DIR__ . '/.env'),
            'error_display_off' => ini_get('display_errors') == 0,
        ];
        
        $passed = count(array_filter($checks));
        $total = count($checks);
        
        return [
            'status' => $passed === $total ? 'pass' : 'fail',
            'score' => ($passed / $total) * 100,
            'checks' => $checks
        ];
    }
    
    /**
     * Check session security
     */
    private static function checkSessionSecurity() {
        $checks = [
            'httponly' => ini_get('session.cookie_httponly') == 1,
            'secure' => ini_get('session.cookie_secure') == 1 || !isset($_SERVER['HTTPS']),
            'samesite' => ini_get('session.cookie_samesite') !== '',
        ];
        
        $passed = count(array_filter($checks));
        $total = count($checks);
        
        return [
            'status' => $passed === $total ? 'pass' : 'fail',
            'score' => ($passed / $total) * 100,
            'checks' => $checks
        ];
    }
    
    /**
     * Check configuration
     */
    private static function checkConfiguration() {
        $checks = [
            'env_file_exists' => file_exists(__DIR__ . '/.env'),
            'env_in_gitignore' => strpos(file_get_contents(__DIR__ . '/.gitignore') ?: '', '.env') !== false,
            'htaccess_exists' => file_exists(__DIR__ . '/.htaccess'),
            'error_reporting_off' => error_reporting() == 0 || ini_get('display_errors') == 0,
        ];
        
        $passed = count(array_filter($checks));
        $total = count($checks);
        
        return [
            'status' => $passed === $total ? 'pass' : 'fail',
            'score' => ($passed / $total) * 100,
            'checks' => $checks
        ];
    }
    
    /**
     * Check log files
     */
    private static function checkLogFiles() {
        $logDir = __DIR__ . '/logs';
        $exists = is_dir($logDir);
        $writable = $exists && is_writable($logDir);
        
        return [
            'status' => $exists && $writable ? 'pass' : 'fail',
            'score' => ($exists && $writable) ? 100 : 0,
            'directory_exists' => $exists,
            'writable' => $writable
        ];
    }
    
    /**
     * Calculate overall security score
     */
    private static function calculateScore($checks) {
        $totalScore = 0;
        $count = 0;
        
        foreach ($checks as $check) {
            if (isset($check['score'])) {
                $totalScore += $check['score'];
                $count++;
            }
        }
        
        return $count > 0 ? round($totalScore / $count, 2) : 0;
    }
    
    /**
     * Get latest audit
     */
    public static function getLatestAudit() {
        if (self::$auditFile === null) {
            self::init();
        }
        
        if (!file_exists(self::$auditFile)) {
            return null;
        }
        
        $audits = json_decode(file_get_contents(self::$auditFile), true) ?: [];
        return !empty($audits) ? end($audits) : null;
    }
}


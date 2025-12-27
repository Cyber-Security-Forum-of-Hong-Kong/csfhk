<?php
/**
 * Web Application Firewall (WAF)
 * Protects against common web attacks: SQL Injection, XSS, Path Traversal, Command Injection, etc.
 */

class WAF {
    private static $logFile = null;
    private static $rateLimitFile = null;
    private static $blockedIPs = [];
    private static $enabled = true;
    
    // Attack patterns
    private static $sqlInjectionPatterns = [
        '/(\bUNION\b.*\bSELECT\b)/i',
        '/(\bSELECT\b.*\bFROM\b)/i',
        '/(\bINSERT\b.*\bINTO\b)/i',
        '/(\bUPDATE\b.*\bSET\b)/i',
        '/(\bDELETE\b.*\bFROM\b)/i',
        '/(\bDROP\b.*\bTABLE\b)/i',
        '/(\bEXEC\b|\bEXECUTE\b)/i',
        '/(\bSCRIPT\b)/i',
        '/(\bOR\b\s+\d+\s*=\s*\d+)/i',
        '/(\bAND\b\s+\d+\s*=\s*\d+)/i',
        '/(\'\s*OR\s*\'\d+\'\s*=\s*\'\d+)/i',
        '/(\'\s*AND\s*\'\d+\'\s*=\s*\'\d+)/i',
        '/(\bOR\b\s+1\s*=\s*1)/i',
        '/(\bAND\b\s+1\s*=\s*1)/i',
        '/(\bOR\b\s+\'\d+\'\s*=\s*\'\d+)/i',
        '/(\bAND\b\s+\'\d+\'\s*=\s*\'\d+)/i',
        '/(\bUNION\b.*\bALL\b.*\bSELECT\b)/i',
        '/(\bCONCAT\b.*\()/i',
        '/(\bCHAR\b\()/i',
        '/(\bASCII\b\()/i',
        '/(\bSUBSTRING\b\()/i',
        '/(\bBENCHMARK\b\()/i',
        '/(\bSLEEP\b\()/i',
        '/(\bWAITFOR\b.*\bDELAY\b)/i',
        '/(\bPG_SLEEP\b\()/i',
        '/(\bLOAD_FILE\b\()/i',
        '/(\bINTO\b.*\bOUTFILE\b)/i',
        '/(\bINTO\b.*\bDUMPFILE\b)/i',
        '/(\bINFORMATION_SCHEMA\b)/i',
        '/(\bSYSTEM_USER\b)/i',
        '/(\bUSER\b\()/i',
        '/(\bDATABASE\b\()/i',
        '/(\bVERSION\b\()/i',
        '/(\b@@VERSION\b)/i',
        '/(\b@@DATABASE\b)/i',
        '/(\b@@USER\b)/i',
    ];
    
    private static $xssPatterns = [
        '/<script[^>]*>.*?<\/script>/is',
        '/<iframe[^>]*>.*?<\/iframe>/is',
        '/javascript:/i',
        '/onerror\s*=/i',
        '/onload\s*=/i',
        '/onclick\s*=/i',
        '/onmouseover\s*=/i',
        '/onfocus\s*=/i',
        '/onblur\s*=/i',
        '/onchange\s*=/i',
        '/onsubmit\s*=/i',
        '/<img[^>]*src[^>]*=.*javascript:/i',
        '/<svg[^>]*onload/i',
        '/<body[^>]*onload/i',
        '/<input[^>]*onfocus/i',
        '/<form[^>]*on submit/i',
        '/<link[^>]*href[^>]*=.*javascript:/i',
        '/<meta[^>]*http-equiv[^>]*=.*refresh/i',
        '/<object[^>]*data/i',
        '/<embed[^>]*src/i',
        '/expression\s*\(/i',
        '/vbscript:/i',
        '/data:text\/html/i',
    ];
    
    private static $pathTraversalPatterns = [
        '/\.\.\//',
        '/\.\.\\\\/',
        '/\.\.%2f/i',
        '/\.\.%5c/i',
        '/\.\.%252f/i',
        '/\.\.%255c/i',
        '/\.\.%c0%af/i',
        '/\.\.%c1%9c/i',
        '/\.\.%e0%80%af/i',
        '/\.\.%2e%2e%2f/i',
        '/\.\.%2e%2e%5c/i',
        '/\.\.%252e%252e%252f/i',
        '/\.\.%252e%252e%255c/i',
        '/etc\/passwd/i',
        '/etc\/shadow/i',
        '/boot\.ini/i',
        '/windows\/system32/i',
        '/proc\/self/i',
        '/\.\.\/\.\.\/\.\.\/\.\.\/\.\.\/etc\/passwd/i',
    ];
    
    private static $commandInjectionPatterns = [
        '/;\s*(rm|del|cat|ls|dir|pwd|whoami|id|uname|ps|kill|chmod|chown|wget|curl|nc|netcat|python|perl|ruby|php|bash|sh)/i',
        '/\|\s*(rm|del|cat|ls|dir|pwd|whoami|id|uname|ps|kill|chmod|chown|wget|curl|nc|netcat|python|perl|ruby|php|bash|sh)/i',
        '/&&\s*(rm|del|cat|ls|dir|pwd|whoami|id|uname|ps|kill|chmod|chown|wget|curl|nc|netcat|python|perl|ruby|php|bash|sh)/i',
        '/`[^`]*`/',
        '/\$\([^)]*\)/',
        '/\$\{[^}]*\}/',
        '/<[^>]*>/',
        '/\bexec\s*\(/i',
        '/\bsystem\s*\(/i',
        '/\bshell_exec\s*\(/i',
        '/\bpassthru\s*\(/i',
        '/\bproc_open\s*\(/i',
        '/\bpopen\s*\(/i',
        '/\bpcntl_exec\s*\(/i',
        '/\beval\s*\(/i',
        '/\bassert\s*\(/i',
        '/\bcreate_function\s*\(/i',
        '/\bcall_user_func\s*\(/i',
        '/\bcall_user_func_array\s*\(/i',
        '/\binclude\s*\(/i',
        '/\brequire\s*\(/i',
        '/\binclude_once\s*\(/i',
        '/\brequire_once\s*\(/i',
    ];
    
    // Additional attack patterns
    private static $additionalPatterns = [
        // LDAP injection
        '/\(&|\(|\)|&|!|\||=/',
        // XML injection
        '/<!\[CDATA\[/i',
        '/<\!ENTITY/i',
        '/%00/',
        // Null byte injection
        '/\x00/',
        // CRLF injection
        '/%0d%0a/i',
        '/\r\n/',
        // SSI injection
        '/<!--#(exec|include|echo|config)/i',
        // PHP code injection
        '/<\?php/i',
        '/<\?=/i',
        '/<%[=]?/i',
        // File inclusion
        '/\.\.\/\.\.\/\.\.\/\.\.\//',
        '/php:\/\/input/i',
        '/php:\/\/filter/i',
        '/data:\/\/text\/plain/i',
        // Remote file inclusion
        '/http:\/\//i',
        '/https:\/\//i',
        '/ftp:\/\//i',
        // SQL comment injection
        '/--\s/',
        '/\/\*/',
        '/#/',
        // Boolean-based blind SQL
        '/\b(TRUE|FALSE)\b/i',
        // Time-based SQL
        '/WAITFOR\s+DELAY/i',
        '/SLEEP\s*\(/i',
        '/BENCHMARK\s*\(/i',
    ];
    
    /**
     * Initialize WAF
     */
    public static function init($logDir = null) {
        if ($logDir === null) {
            $logDir = __DIR__ . '/logs';
        }
        
        // Create logs directory if it doesn't exist
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        self::$logFile = $logDir . '/waf.log';
        self::$rateLimitFile = $logDir . '/ratelimit.log';
        
        // Load blocked IPs
        $blockedFile = $logDir . '/blocked_ips.txt';
        if (file_exists($blockedFile)) {
            $content = file_get_contents($blockedFile);
            self::$blockedIPs = array_filter(explode("\n", $content));
        }
    }
    
    /**
     * Check if request should be blocked
     */
    public static function checkRequest() {
        if (!self::$enabled) {
            return true;
        }
        
        // Initialize if not done
        if (self::$logFile === null) {
            self::init();
        }
        
        $ip = self::getClientIP();
        
        // Check if IP is blocked
        if (in_array($ip, self::$blockedIPs)) {
            self::log('BLOCKED_IP', $ip, 'Blocked IP attempted access');
            self::sendBlockResponse('IP address is blocked');
            return false;
        }
        
        // Rate limiting
        if (!self::checkRateLimit($ip)) {
            self::log('RATE_LIMIT', $ip, 'Rate limit exceeded');
            self::sendBlockResponse('Rate limit exceeded. Please try again later.');
            return false;
        }
        
        // Check all input data
        $allInput = array_merge($_GET, $_POST, $_COOKIE, $_SERVER);
        
        foreach ($allInput as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subValue) {
                    if (!self::checkValue($subValue, $key)) {
                        return false;
                    }
                }
            } else {
                if (!self::checkValue($value, $key)) {
                    return false;
                }
            }
        }
        
        // Check request URI
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (!self::checkValue($uri, 'REQUEST_URI')) {
            return false;
        }
        
        // Check user agent
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (!self::checkValue($userAgent, 'USER_AGENT')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check a single value against attack patterns
     */
    private static function checkValue($value, $key = '') {
        if (!is_string($value)) {
            return true;
        }
        
        $value = urldecode($value);
        $ip = self::getClientIP();
        
        // Check SQL Injection
        foreach (self::$sqlInjectionPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                self::log('SQL_INJECTION', $ip, "SQL injection attempt in $key: " . substr($value, 0, 100));
                self::sendBlockResponse('Invalid request detected');
                return false;
            }
        }
        
        // Check XSS
        foreach (self::$xssPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                self::log('XSS', $ip, "XSS attempt in $key: " . substr($value, 0, 100));
                self::sendBlockResponse('Invalid request detected');
                return false;
            }
        }
        
        // Check Path Traversal
        foreach (self::$pathTraversalPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                self::log('PATH_TRAVERSAL', $ip, "Path traversal attempt in $key: " . substr($value, 0, 100));
                self::sendBlockResponse('Invalid request detected');
                return false;
            }
        }
        
        // Check Command Injection
        foreach (self::$commandInjectionPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                self::log('COMMAND_INJECTION', $ip, "Command injection attempt in $key: " . substr($value, 0, 100));
                self::sendBlockResponse('Invalid request detected');
                return false;
            }
        }
        
        // Check Additional Attack Patterns
        foreach (self::$additionalPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                self::log('SUSPICIOUS_PATTERN', $ip, "Suspicious pattern detected in $key: " . substr($value, 0, 100));
                self::sendBlockResponse('Invalid request detected');
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Rate limiting: max requests per minute
     */
    private static function checkRateLimit($ip, $maxRequests = 60, $timeWindow = 60) {
        $rateLimitData = [];
        
        if (file_exists(self::$rateLimitFile)) {
            $content = file_get_contents(self::$rateLimitFile);
            $rateLimitData = json_decode($content, true) ?: [];
        }
        
        $now = time();
        $ipKey = md5($ip);
        
        // Clean old entries
        foreach ($rateLimitData as $key => $data) {
            if ($now - $data['first_request'] > $timeWindow) {
                unset($rateLimitData[$key]);
            }
        }
        
        // Check current IP
        if (isset($rateLimitData[$ipKey])) {
            $data = $rateLimitData[$ipKey];
            
            // Reset if time window passed
            if ($now - $data['first_request'] > $timeWindow) {
                $rateLimitData[$ipKey] = [
                    'count' => 1,
                    'first_request' => $now,
                    'last_request' => $now
                ];
            } else {
                $rateLimitData[$ipKey]['count']++;
                $rateLimitData[$ipKey]['last_request'] = $now;
                
                if ($rateLimitData[$ipKey]['count'] > $maxRequests) {
                    // Block IP temporarily if rate limit exceeded multiple times
                    if ($rateLimitData[$ipKey]['count'] > $maxRequests * 2) {
                        self::blockIP($ip, 300); // Block for 5 minutes
                    }
                    @file_put_contents(self::$rateLimitFile, json_encode($rateLimitData));
                    return false;
                }
            }
        } else {
            $rateLimitData[$ipKey] = [
                'count' => 1,
                'first_request' => $now,
                'last_request' => $now
            ];
        }
        
        @file_put_contents(self::$rateLimitFile, json_encode($rateLimitData));
        return true;
    }
    
    /**
     * Block an IP address temporarily
     */
    private static function blockIP($ip, $seconds = 300) {
        $blockedFile = dirname(self::$logFile) . '/blocked_ips.txt';
        $blockedIPs = [];
        
        if (file_exists($blockedFile)) {
            $content = file_get_contents($blockedFile);
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $parts = explode('|', trim($line));
                if (count($parts) >= 2) {
                    $blockedIP = $parts[0];
                    $expiry = (int)$parts[1];
                    if ($expiry > time()) {
                        $blockedIPs[] = $blockedIP;
                    }
                }
            }
        }
        
        if (!in_array($ip, $blockedIPs)) {
            $blockedIPs[] = $ip;
            $expiry = time() + $seconds;
            $line = "$ip|$expiry\n";
            @file_put_contents($blockedFile, $line, FILE_APPEND);
            self::$blockedIPs[] = $ip;
        }
    }
    
    /**
     * Log security event
     */
    private static function log($type, $ip, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$type] IP: $ip | $message\n";
        @file_put_contents(self::$logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Send block response
     */
    private static function sendBlockResponse($message = 'Access denied') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => false,
            'error' => $message,
            'code' => 'WAF_BLOCKED'
        ]);
        exit;
    }
    
    /**
     * Get client IP address
     */
    private static function getClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (from proxies)
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
    
    /**
     * Enable/disable WAF
     */
    public static function setEnabled($enabled) {
        self::$enabled = (bool)$enabled;
    }
    
    /**
     * Get WAF status
     */
    public static function isEnabled() {
        return self::$enabled;
    }
}


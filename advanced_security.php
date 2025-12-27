<?php
/**
 * Advanced Security Module
 * Protects against sophisticated attacks: SSRF, XXE, Request Smuggling, 
 * Anomaly Detection, Advanced Rate Limiting, etc.
 */

class AdvancedSecurity {
    private static $fingerprintFile = null;
    private static $anomalyFile = null;
    private static $requestHistory = [];
    
    /**
     * Initialize advanced security
     */
    public static function init() {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        self::$fingerprintFile = $logDir . '/fingerprints.json';
        self::$anomalyFile = $logDir . '/anomalies.json';
    }
    
    /**
     * Validate and normalize request headers
     */
    public static function validateHeaders() {
        $suspiciousHeaders = [];
        
        // Check for header injection attempts
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace('_', '-', substr($key, 5));
                
                // Check for CRLF injection in headers
                if (preg_match('/[\r\n]/', $value)) {
                    self::logAnomaly('HEADER_INJECTION', "CRLF injection in header: $headerName");
                    return false;
                }
                
                // Check for suspicious header values
                if (self::isSuspiciousHeaderValue($value)) {
                    self::logAnomaly('SUSPICIOUS_HEADER', "Suspicious header value: $headerName");
                    $suspiciousHeaders[] = $headerName;
                }
            }
        }
        
        // Validate required headers
        if (!isset($_SERVER['HTTP_USER_AGENT']) || empty($_SERVER['HTTP_USER_AGENT'])) {
            self::logAnomaly('MISSING_UA', 'Missing User-Agent header');
            return false;
        }
        
        // Check User-Agent for suspicious patterns
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (strlen($ua) > 512) {
            self::logAnomaly('LONG_UA', 'User-Agent too long');
            return false;
        }
        
        return true;
    }
    
    /**
     * Check for suspicious header values
     */
    private static function isSuspiciousHeaderValue($value) {
        $suspiciousPatterns = [
            '/javascript:/i',
            '/data:text\/html/i',
            '/vbscript:/i',
            '/on\w+\s*=/i',
            '/<script/i',
            '/\.\.\//',
            '/\x00/',
            '/%00/',
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detect SSRF (Server-Side Request Forgery) attempts
     */
    public static function detectSSRF($url) {
        if (empty($url)) {
            return false;
        }
        
        // Parse URL
        $parsed = parse_url($url);
        
        if (!$parsed) {
            return true; // Invalid URL
        }
        
        $host = $parsed['host'] ?? '';
        $scheme = $parsed['scheme'] ?? '';
        
        // Block internal/private IPs
        $privateIPs = [
            '/^127\./',
            '/^10\./',
            '/^172\.(1[6-9]|2[0-9]|3[0-1])\./',
            '/^192\.168\./',
            '/^169\.254\./',
            '/^::1$/',
            '/^fc00:/',
            '/^fe80:/',
            '/^localhost$/i',
        ];
        
        foreach ($privateIPs as $pattern) {
            if (preg_match($pattern, $host)) {
                self::logAnomaly('SSRF_ATTEMPT', "SSRF attempt to internal IP: $host");
                return true;
            }
        }
        
        // Block file:// and other dangerous schemes
        $dangerousSchemes = ['file', 'gopher', 'jar', 'php', 'data'];
        if (in_array(strtolower($scheme), $dangerousSchemes)) {
            self::logAnomaly('SSRF_ATTEMPT', "SSRF attempt with dangerous scheme: $scheme");
            return true;
        }
        
        return false;
    }
    
    /**
     * Detect XXE (XML External Entity) attempts
     */
    public static function detectXXE($content) {
        if (empty($content)) {
            return false;
        }
        
        $xxePatterns = [
            '/<!ENTITY/i',
            '/SYSTEM\s+["\']/i',
            '/PUBLIC\s+["\']/i',
            '/%[a-zA-Z0-9_]+;/',
            '/&[a-zA-Z0-9_]+;/',
            '/<!\[CDATA\[/i',
            '/<\!DOCTYPE/i',
        ];
        
        foreach ($xxePatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                self::logAnomaly('XXE_ATTEMPT', 'XXE injection attempt detected');
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validate input length limits
     */
    public static function validateInputLengths($maxLengths = []) {
        $defaultMaxLengths = [
            'title' => 200,
            'content' => 10000,
            'name' => 50,
            'email' => 255,
            'category' => 50,
            'author' => 50,
        ];
        
        $maxLengths = array_merge($defaultMaxLengths, $maxLengths);
        
        foreach ($_POST as $key => $value) {
            if (isset($maxLengths[$key])) {
                $maxLen = $maxLengths[$key];
                if (is_string($value) && strlen($value) > $maxLen) {
                    self::logAnomaly('INPUT_TOO_LONG', "Input too long: $key (max: $maxLen)");
                    return false;
                }
            }
        }
        
        foreach ($_GET as $key => $value) {
            if (is_string($value) && strlen($value) > 1000) {
                self::logAnomaly('INPUT_TOO_LONG', "GET parameter too long: $key");
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Request fingerprinting for anomaly detection
     */
    public static function fingerprintRequest() {
        $ip = self::getClientIP();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $acceptEnc = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        
        $fingerprint = md5($ip . $ua . $method . $uri . $accept . $acceptLang . $acceptEnc);
        
        // Load existing fingerprints
        $fingerprints = [];
        if (file_exists(self::$fingerprintFile)) {
            $fingerprints = json_decode(file_get_contents(self::$fingerprintFile), true) ?: [];
        }
        
        // Check if this fingerprint is known
        if (isset($fingerprints[$fingerprint])) {
            $fingerprints[$fingerprint]['count']++;
            $fingerprints[$fingerprint]['last_seen'] = time();
        } else {
            $fingerprints[$fingerprint] = [
                'ip' => $ip,
                'ua' => substr($ua, 0, 200),
                'count' => 1,
                'first_seen' => time(),
                'last_seen' => time(),
            ];
        }
        
        // Clean old fingerprints (older than 24 hours)
        $now = time();
        foreach ($fingerprints as $fp => $data) {
            if ($now - $data['last_seen'] > 86400) {
                unset($fingerprints[$fp]);
            }
        }
        
        file_put_contents(self::$fingerprintFile, json_encode($fingerprints));
        
        return $fingerprint;
    }
    
    /**
     * Detect anomalies in request patterns
     */
    public static function detectAnomalies() {
        $ip = self::getClientIP();
        $now = time();
        
        // Check for rapid requests from same IP
        $recentRequests = self::getRecentRequests($ip, 60); // Last minute
        if (count($recentRequests) > 30) {
            self::logAnomaly('RAPID_REQUESTS', "Too many requests from $ip: " . count($recentRequests));
            return false;
        }
        
        // Check for unusual request patterns
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Detect potential directory traversal in URI
        if (preg_match('/\.\./', $uri)) {
            self::logAnomaly('PATH_TRAVERSAL', "Path traversal in URI: $uri");
            return false;
        }
        
        // Detect potential SQL injection in URI
        if (preg_match('/(union|select|insert|delete|drop|exec)/i', $uri)) {
            self::logAnomaly('SQL_INJECTION_URI', "SQL injection pattern in URI: $uri");
            return false;
        }
        
        // Check for unusual HTTP methods
        $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];
        if (!in_array($method, $allowedMethods)) {
            self::logAnomaly('UNUSUAL_METHOD', "Unusual HTTP method: $method");
            return false;
        }
        
        return true;
    }
    
    /**
     * Get recent requests from IP
     */
    private static function getRecentRequests($ip, $seconds) {
        $historyFile = __DIR__ . '/logs/request_history.json';
        
        if (!file_exists($historyFile)) {
            return [];
        }
        
        $history = json_decode(file_get_contents($historyFile), true) ?: [];
        $now = time();
        $recent = [];
        
        foreach ($history as $request) {
            if ($request['ip'] === $ip && ($now - $request['time']) <= $seconds) {
                $recent[] = $request;
            }
        }
        
        return $recent;
    }
    
    /**
     * Record request for analysis
     */
    public static function recordRequest() {
        $historyFile = __DIR__ . '/logs/request_history.json';
        
        $request = [
            'ip' => self::getClientIP(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
            'time' => time(),
        ];
        
        $history = [];
        if (file_exists($historyFile)) {
            $history = json_decode(file_get_contents($historyFile), true) ?: [];
        }
        
        $history[] = $request;
        
        // Keep only last 1000 requests
        if (count($history) > 1000) {
            $history = array_slice($history, -1000);
        }
        
        file_put_contents($historyFile, json_encode($history));
    }
    
    /**
     * Log anomaly
     */
    private static function logAnomaly($type, $message) {
        $ip = self::getClientIP();
        $logEntry = [
            'type' => $type,
            'message' => $message,
            'ip' => $ip,
            'time' => time(),
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        ];
        
        $anomalies = [];
        if (file_exists(self::$anomalyFile)) {
            $anomalies = json_decode(file_get_contents(self::$anomalyFile), true) ?: [];
        }
        
        $anomalies[] = $logEntry;
        
        // Keep only last 500 anomalies
        if (count($anomalies) > 500) {
            $anomalies = array_slice($anomalies, -500);
        }
        
        file_put_contents(self::$anomalyFile, json_encode($anomalies));
        
        // Also log to error log
        error_log("SECURITY ANOMALY [$type]: $message from IP: $ip");
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
    
    /**
     * Validate all inputs have reasonable length
     */
    public static function validateAllInputs() {
        // Check POST data
        foreach ($_POST as $key => $value) {
            if (is_string($value) && strlen($value) > 50000) {
                self::logAnomaly('HUGE_INPUT', "Huge input in POST[$key]: " . strlen($value) . " bytes");
                return false;
            }
        }
        
        // Check GET data
        foreach ($_GET as $key => $value) {
            if (is_string($value) && strlen($value) > 2000) {
                self::logAnomaly('HUGE_INPUT', "Huge input in GET[$key]: " . strlen($value) . " bytes");
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check for request smuggling attempts
     */
    public static function detectRequestSmuggling() {
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
        $transferEncoding = $_SERVER['HTTP_TRANSFER_ENCODING'] ?? '';
        
        // Check for conflicting headers
        if ($contentLength > 0 && !empty($transferEncoding)) {
            self::logAnomaly('REQUEST_SMUGGLING', 'Conflicting Content-Length and Transfer-Encoding headers');
            return true;
        }
        
        // Check for duplicate headers
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace('_', '-', substr($key, 5));
                if (isset($headers[$headerName])) {
                    self::logAnomaly('DUPLICATE_HEADER', "Duplicate header: $headerName");
                    return true;
                }
                $headers[$headerName] = $value;
            }
        }
        
        return false;
    }
}


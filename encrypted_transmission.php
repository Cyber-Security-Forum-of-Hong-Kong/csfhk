<?php
/**
 * Encrypted Transmission Plugin
 * Enforces and manages encrypted data transmission (HTTPS/TLS)
 */

class EncryptedTransmission {
    private static $enforceHTTPS = true;
    private static $hstsMaxAge = 31536000; // 1 year
    private static $hstsIncludeSubdomains = true;
    private static $hstsPreload = true;
    
    /**
     * Initialize encrypted transmission
     */
    public static function init() {
        // Enforce HTTPS if enabled
        if (self::$enforceHTTPS) {
            self::enforceHTTPS();
        }
        
        // Set HSTS header
        self::setHSTS();
        
        // Set secure cookie flags
        self::setSecureCookies();
    }
    
    /**
     * Enforce HTTPS connection
     */
    private static function enforceHTTPS() {
        // Check if request is not HTTPS
        $isHTTPS = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
            (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        );
        
        if (!$isHTTPS && php_sapi_name() !== 'cli') {
            // Redirect to HTTPS
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            $httpsUrl = 'https://' . $host . $uri;
            
            // For API endpoints, return error instead of redirect
            if (self::isAPIRequest()) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'ok' => false,
                    'error' => 'HTTPS required. Please use HTTPS to access this API.'
                ]);
                exit;
            }
            
            // Redirect to HTTPS
            header("Location: $httpsUrl", true, 301);
            exit;
        }
    }
    
    /**
     * Set HTTP Strict Transport Security (HSTS) header
     */
    private static function setHSTS() {
        if (headers_sent()) {
            return;
        }
        
        $hstsValue = 'max-age=' . self::$hstsMaxAge;
        
        if (self::$hstsIncludeSubdomains) {
            $hstsValue .= '; includeSubDomains';
        }
        
        if (self::$hstsPreload) {
            $hstsValue .= '; preload';
        }
        
        header('Strict-Transport-Security: ' . $hstsValue);
    }
    
    /**
     * Set secure cookie flags
     */
    private static function setSecureCookies() {
        // Override PHP ini settings for secure cookies
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');
    }
    
    /**
     * Check if current request is API request
     */
    private static function isAPIRequest() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $apiEndpoints = ['discussions_api.php', 'ctf_check.php', 'login.php', 'signup.php'];
        
        foreach ($apiEndpoints as $endpoint) {
            if (strpos($uri, $endpoint) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Encrypt sensitive data for transmission
     */
    public static function encryptForTransmission($data, $key = null) {
        if ($key === null) {
            $key = self::getEncryptionKey();
        }
        
        if (class_exists('Encryption')) {
            return Encryption::encrypt($data, $key);
        }
        
        // Fallback: simple base64 encoding (not secure, but better than plain text)
        // In production, always use proper encryption
        return base64_encode($data);
    }
    
    /**
     * Decrypt data received from transmission
     */
    public static function decryptFromTransmission($encryptedData, $key = null) {
        if ($key === null) {
            $key = self::getEncryptionKey();
        }
        
        if (class_exists('Encryption')) {
            return Encryption::decrypt($encryptedData, $key);
        }
        
        // Fallback: base64 decode
        return base64_decode($encryptedData);
    }
    
    /**
     * Get encryption key from environment
     */
    private static function getEncryptionKey() {
        $key = getenv('ENCRYPTION_KEY');
        
        if (empty($key)) {
            // Generate a key if not set (store in session for this request)
            if (!isset($_SESSION['temp_encryption_key'])) {
                $_SESSION['temp_encryption_key'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['temp_encryption_key'];
        }
        
        return $key;
    }
    
    /**
     * Verify SSL/TLS certificate
     */
    public static function verifySSL($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        
        $result = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'valid' => empty($error) && $httpCode > 0,
            'error' => $error,
            'http_code' => $httpCode
        ];
    }
    
    /**
     * Enable HTTPS enforcement
     */
    public static function enableHTTPS() {
        self::$enforceHTTPS = true;
    }
    
    /**
     * Disable HTTPS enforcement (for development only)
     */
    public static function disableHTTPS() {
        self::$enforceHTTPS = false;
    }
    
    /**
     * Check if HTTPS is enabled
     */
    public static function isHTTPSEnabled() {
        return self::$enforceHTTPS;
    }
    
    /**
     * Get current connection security status
     */
    public static function getConnectionStatus() {
        $isHTTPS = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
            (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        );
        
        return [
            'https' => $isHTTPS,
            'protocol' => $isHTTPS ? 'HTTPS' : 'HTTP',
            'secure' => $isHTTPS,
            'hsts_enabled' => self::$enforceHTTPS
        ];
    }
    
    /**
     * Set up encrypted database connection
     */
    public static function getEncryptedDBConnection($host, $user, $pass, $db) {
        // MySQL SSL connection options
        $sslOptions = [
            'ssl' => [
                'verify_server_cert' => true,
                'verify_peer_name' => true,
                'cafile' => getenv('DB_SSL_CA') ?: null,
                'capath' => getenv('DB_SSL_CAPATH') ?: null,
                'cipher' => getenv('DB_SSL_CIPHER') ?: 'AES256-SHA',
            ]
        ];
        
        $mysqli = new mysqli($host, $user, $pass, $db, null, null, $sslOptions);
        
        if ($mysqli->connect_errno) {
            return null;
        }
        
        // Verify SSL connection
        $result = $mysqli->query("SHOW STATUS LIKE 'Ssl_cipher'");
        if ($result && $row = $result->fetch_assoc()) {
            if (empty($row['Value'])) {
                error_log('Warning: Database connection is not using SSL');
            }
        }
        
        return $mysqli;
    }
}


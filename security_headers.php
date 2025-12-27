<?php
/**
 * Security Headers Plugin
 * Manages and enforces security headers
 */

class SecurityHeaders {
    private static $headersSet = false;
    
    /**
     * Set all security headers
     */
    public static function setAll() {
        if (self::$headersSet) {
            return; // Headers already set
        }
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // XSS Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Frame options
        header('X-Frame-Options: SAMEORIGIN');
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy
        self::setCSP();
        
        // Permissions Policy
        self::setPermissionsPolicy();
        
        // HSTS (if HTTPS)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Remove server information
        header_remove('Server');
        header_remove('X-Powered-By');
        
        self::$headersSet = true;
    }
    
    /**
     * Set Content Security Policy
     */
    private static function setCSP() {
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.gstatic.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: https:",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
            "media-src 'self'",
            "worker-src 'self'",
            "manifest-src 'self'",
            "upgrade-insecure-requests",
        ];
        
        header('Content-Security-Policy: ' . implode('; ', $csp));
    }
    
    /**
     * Set Permissions Policy
     */
    private static function setPermissionsPolicy() {
        $permissions = [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'magnetometer=()',
            'gyroscope=()',
            'speaker=()',
            'vibrate=()',
            'fullscreen=(self)',
            'payment=()',
            'usb=()',
        ];
        
        header('Permissions-Policy: ' . implode(', ', $permissions));
    }
    
    /**
     * Set custom CSP
     */
    public static function setCustomCSP($directives) {
        header('Content-Security-Policy: ' . $directives);
    }
    
    /**
     * Add report-only CSP
     */
    public static function setCSPReportOnly($directives) {
        header('Content-Security-Policy-Report-Only: ' . $directives);
    }
}


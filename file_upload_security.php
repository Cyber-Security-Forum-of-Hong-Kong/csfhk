<?php
/**
 * File Upload Security Plugin
 * Enhanced security for file uploads
 */

class FileUploadSecurity {
    private static $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'];
    private static $maxFileSize = 5242880; // 5MB
    private static $blockedMimeTypes = [
        'application/x-php',
        'application/x-executable',
        'application/x-sh',
        'application/x-bat',
        'text/x-php',
        'text/x-shellscript',
    ];
    
    /**
     * Validate uploaded file
     */
    public static function validateUpload($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'Invalid file upload'];
        }
        
        $errors = [];
        
        // Check file size
        if ($file['size'] > self::$maxFileSize) {
            $errors[] = "File size exceeds maximum allowed size (" . (self::$maxFileSize / 1024 / 1024) . "MB)";
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::$allowedExtensions)) {
            $errors[] = "File type not allowed. Allowed types: " . implode(', ', self::$allowedExtensions);
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (in_array($mimeType, self::$blockedMimeTypes)) {
            $errors[] = "File type blocked for security reasons";
        }
        
        // Validate MIME type matches extension
        $expectedMimes = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'txt' => ['text/plain'],
        ];
        
        if (isset($expectedMimes[$extension])) {
            if (!in_array($mimeType, $expectedMimes[$extension])) {
                $errors[] = "File MIME type does not match file extension";
            }
        }
        
        // Check for malicious content
        $content = file_get_contents($file['tmp_name']);
        
        // Check for PHP tags
        if (preg_match('/<\?php/i', $content) || preg_match('/<\?=/i', $content)) {
            $errors[] = "File contains PHP code";
        }
        
        // Check for script tags
        if (preg_match('/<script/i', $content)) {
            $errors[] = "File contains script tags";
        }
        
        // Check for executable markers
        if (preg_match('/\x7fELF|\x4d\x5a/', $content)) {
            $errors[] = "File appears to be an executable";
        }
        
        // Check file name for path traversal
        if (strpos($file['name'], '..') !== false || strpos($file['name'], '/') !== false) {
            $errors[] = "Invalid file name";
        }
        
        // Check for null bytes
        if (strpos($file['name'], "\0") !== false) {
            $errors[] = "File name contains null bytes";
        }
        
        if (!empty($errors)) {
            // Log suspicious upload attempt
            $ip = self::getClientIP();
            if (class_exists('SecurityMonitor')) {
                SecurityMonitor::logEvent('SUSPICIOUS_FILE_UPLOAD', 'high', 
                    "Suspicious file upload attempt", [
                        'ip' => $ip,
                        'filename' => $file['name'],
                        'errors' => $errors
                    ]);
            }
            
            if (class_exists('IPReputation')) {
                IPReputation::updateReputation($ip, -10);
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Sanitize file name
     */
    public static function sanitizeFileName($filename) {
        // Remove path components
        $filename = basename($filename);
        
        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        // Limit length
        $filename = substr($filename, 0, 255);
        
        return $filename;
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


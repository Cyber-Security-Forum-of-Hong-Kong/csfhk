<?php
/**
 * Advanced Input Validator Plugin
 * Comprehensive input validation and sanitization
 */

class InputValidator {
    /**
     * Validate and sanitize email
     */
    public static function validateEmail($email) {
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => 'Invalid email format'];
        }
        
        // Check for suspicious patterns
        if (preg_match('/[<>"\']/', $email)) {
            return ['valid' => false, 'error' => 'Email contains invalid characters'];
        }
        
        // Check length
        if (strlen($email) > 255) {
            return ['valid' => false, 'error' => 'Email too long'];
        }
        
        return ['valid' => true, 'value' => $email];
    }
    
    /**
     * Validate and sanitize string
     */
    public static function validateString($input, $minLength = 0, $maxLength = null, $allowHtml = false) {
        if (!is_string($input)) {
            return ['valid' => false, 'error' => 'Input must be a string'];
        }
        
        $input = trim($input);
        
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Check length
        if (strlen($input) < $minLength) {
            return ['valid' => false, 'error' => "Input too short (minimum $minLength characters)"];
        }
        
        if ($maxLength !== null && strlen($input) > $maxLength) {
            return ['valid' => false, 'error' => "Input too long (maximum $maxLength characters)"];
        }
        
        // Sanitize HTML if not allowed
        if (!$allowHtml) {
            $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        return ['valid' => true, 'value' => $input];
    }
    
    /**
     * Validate integer
     */
    public static function validateInteger($input, $min = null, $max = null) {
        if (!is_numeric($input)) {
            return ['valid' => false, 'error' => 'Input must be a number'];
        }
        
        $value = (int)$input;
        
        if ($min !== null && $value < $min) {
            return ['valid' => false, 'error' => "Value must be at least $min"];
        }
        
        if ($max !== null && $value > $max) {
            return ['valid' => false, 'error' => "Value must be at most $max"];
        }
        
        return ['valid' => true, 'value' => $value];
    }
    
    /**
     * Validate URL
     */
    public static function validateURL($url) {
        $url = filter_var(trim($url), FILTER_SANITIZE_URL);
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'error' => 'Invalid URL format'];
        }
        
        // Check for dangerous schemes
        $parsed = parse_url($url);
        $dangerousSchemes = ['javascript', 'data', 'vbscript', 'file'];
        
        if (isset($parsed['scheme']) && in_array(strtolower($parsed['scheme']), $dangerousSchemes)) {
            return ['valid' => false, 'error' => 'Dangerous URL scheme not allowed'];
        }
        
        return ['valid' => true, 'value' => $url];
    }
    
    /**
     * Validate file upload
     */
    public static function validateFile($file, $allowedTypes = [], $maxSize = 5242880) {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'File upload error'];
        }
        
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File too large'];
        }
        
        // Check MIME type
        if (!empty($allowedTypes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                return ['valid' => false, 'error' => 'File type not allowed'];
            }
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'exe', 'bat', 'sh', 'js'];
        
        if (in_array($extension, $dangerousExtensions)) {
            return ['valid' => false, 'error' => 'Dangerous file type'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validate array input
     */
    public static function validateArray($input, $maxItems = 100) {
        if (!is_array($input)) {
            return ['valid' => false, 'error' => 'Input must be an array'];
        }
        
        if (count($input) > $maxItems) {
            return ['valid' => false, 'error' => "Array too large (maximum $maxItems items)"];
        }
        
        return ['valid' => true, 'value' => $input];
    }
}


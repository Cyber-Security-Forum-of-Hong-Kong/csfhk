<?php
/**
 * Advanced Request Validator Plugin
 * Comprehensive request validation and normalization
 */

class RequestValidator {
    /**
     * Validate and normalize entire request
     */
    public static function validateRequest() {
        $errors = [];
        
        // Validate HTTP method
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];
        if (!in_array($method, $allowedMethods)) {
            $errors[] = 'Invalid HTTP method';
        }
        
        // Validate Content-Type for POST requests
        if ($method === 'POST') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (empty($contentType)) {
                $errors[] = 'Missing Content-Type header';
            }
        }
        
        // Validate Content-Length
        $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
        if ($contentLength > 10485760) { // 10MB
            $errors[] = 'Request too large';
        }
        
        // Validate GET parameters
        foreach ($_GET as $key => $value) {
            $validation = self::validateParameter($key, $value, 'GET');
            if (!$validation['valid']) {
                $errors[] = "GET parameter '$key': " . $validation['error'];
            }
        }
        
        // Validate POST parameters
        foreach ($_POST as $key => $value) {
            $validation = self::validateParameter($key, $value, 'POST');
            if (!$validation['valid']) {
                $errors[] = "POST parameter '$key': " . $validation['error'];
            }
        }
        
        // Validate headers
        $headerErrors = self::validateHeaders();
        $errors = array_merge($errors, $headerErrors);
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate individual parameter
     */
    private static function validateParameter($key, $value, $source) {
        // Key validation
        if (strlen($key) > 100) {
            return ['valid' => false, 'error' => 'Parameter name too long'];
        }
        
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $key)) {
            return ['valid' => false, 'error' => 'Invalid parameter name format'];
        }
        
        // Value validation
        if (is_array($value)) {
            if (count($value) > 100) {
                return ['valid' => false, 'error' => 'Array too large'];
            }
            
            foreach ($value as $item) {
                $itemValidation = self::validateParameterValue($item, $source);
                if (!$itemValidation['valid']) {
                    return $itemValidation;
                }
            }
        } else {
            return self::validateParameterValue($value, $source);
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validate parameter value
     */
    private static function validateParameterValue($value, $source) {
        if (!is_string($value)) {
            return ['valid' => true]; // Non-strings handled separately
        }
        
        // Length validation
        $maxLength = $source === 'GET' ? 2000 : 50000;
        if (strlen($value) > $maxLength) {
            return ['valid' => false, 'error' => "Value too long (max $maxLength)"];
        }
        
        // Null byte check
        if (strpos($value, "\0") !== false) {
            return ['valid' => false, 'error' => 'Null byte detected'];
        }
        
        // Encoding validation
        if (!mb_check_encoding($value, 'UTF-8')) {
            return ['valid' => false, 'error' => 'Invalid encoding'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validate HTTP headers
     */
    private static function validateHeaders() {
        $errors = [];
        
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace('_', '-', substr($key, 5));
                
                // Check for header injection
                if (preg_match('/[\r\n]/', $value)) {
                    $errors[] = "Header injection detected in $headerName";
                }
                
                // Check header length
                if (strlen($value) > 8192) {
                    $errors[] = "Header $headerName too long";
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Normalize request data
     */
    public static function normalizeRequest() {
        // Normalize GET
        foreach ($_GET as $key => $value) {
            if (is_string($value)) {
                $_GET[$key] = trim($value);
                $_GET[$key] = str_replace("\0", '', $_GET[$key]);
            }
        }
        
        // Normalize POST
        foreach ($_POST as $key => $value) {
            if (is_string($value)) {
                $_POST[$key] = trim($value);
                $_POST[$key] = str_replace("\0", '', $_POST[$key]);
            }
        }
    }
    
    /**
     * Validate API request structure
     */
    public static function validateAPIRequest($requiredFields = [], $optionalFields = []) {
        $errors = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field]) || (is_string($_POST[$field]) && trim($_POST[$field]) === '')) {
                $errors[] = "Required field missing: $field";
            }
        }
        
        // Check for unexpected fields (mass assignment protection)
        $allowedFields = array_merge($requiredFields, $optionalFields);
        foreach ($_POST as $key => $value) {
            if (!in_array($key, $allowedFields) && !in_array($key, ['csrf_token', '_signature'])) {
                $errors[] = "Unexpected field: $key";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}


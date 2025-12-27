<?php
/**
 * Configuration file that loads environment variables from .env
 * This file should not be accessed directly
 */

// Prevent direct access
if (!defined('IN_APP')) {
    // Check if this is a direct request (not included)
    $directAccess = (
        basename($_SERVER['PHP_SELF']) === basename(__FILE__) ||
        (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], basename(__FILE__)) !== false)
    );
    
    if ($directAccess) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
}

// Simple .env file parser
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        return;
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            
            // Set environment variable if not already set
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

// Load .env file
loadEnv(__DIR__ . '/.env');

// Get API endpoint from environment, with fallback
$API_ENDPOINT = getenv('API_ENDPOINT') ?: 'discussions_api.php';


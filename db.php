<?php

$DB_HOST = getenv('DB_HOST') ?: 'sql309.infinityfree.com';
$DB_USER = getenv('DB_USER') ?: 'if0_40753568';
$DB_PASS = getenv('DB_PASSWORD') ?: 'Ad281029'; 
$DB_NAME = getenv('DB_NAME') ?: 'if0_40753568_discussions';

if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// 
date_default_timezone_set('Asia/Hong_Kong');

/**
 * 
 * @return mysqli 
 */
// Global variable to store connection error
$GLOBALS['db_connection_error'] = null;

function getDBConnection() {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    global $db_connection_error;
    
    static $connection = null;
    
    if ($connection === null) {
        $connection = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
        
        if ($connection->connect_errno) {
            // Store the error for later retrieval
            $GLOBALS['db_connection_error'] = $connection->connect_error;
            // Log the error
            error_log("Database connection failed: " . $connection->connect_error);
            
            // Don't exit here - let the calling script handle the error
            // Return null to indicate failure
            $connection = null;
            return null;
        }
        
        // Clear any previous error
        $GLOBALS['db_connection_error'] = null;
        
        $connection->set_charset('utf8mb4');
        $connection->query("SET time_zone = '+08:00'");
    }
    
    return $connection;
}

function getDBConnectionError() {
    return $GLOBALS['db_connection_error'] ?? null;
}

/**
 * Close database connection
 */
function closeDBConnection() {
    $connection = getDBConnection();
    if ($connection && method_exists($connection, 'ping') && $connection->ping()) {
        $connection->close();
    }
}

/**
 *
 * @param string
 * @param array 
 * @return mixed
 */
function executeQuery($sql, $params = []) {
    $db = getDBConnection();
    $stmt = $db->prepare($sql);
    
    if (!$stmt) {
        error_log("Query preparation failed: " . $db->error);
        return false;
    }
    
    if (!empty($params)) {
        $types = '';
        $values = [];
        
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_string($param)) {
                $types .= 's';
            } else {
                $types .= 'b';
            }
            $values[] = $param;
        }
        
        $stmt->bind_param($types, ...$values);
    }
    
    if (!$stmt->execute()) {
        error_log("Query execution failed: " . $stmt->error);
        return false;
    }
    
    return $stmt;
}

/**
 * 
 * @return int 
 */
function getLastInsertId() {
    $db = getDBConnection();
    return $db->insert_id;
}

/**
 *
 * @param string 
 * @return string 
 */
function escapeString($str) {
    $db = getDBConnection();
    return $db->real_escape_string($str);
}

// Create global $mysqli variable for backward compatibility
$mysqli = getDBConnection();

// If connection failed, $mysqli will be null
// The calling script should check for this

register_shutdown_function('closeDBConnection');

?>
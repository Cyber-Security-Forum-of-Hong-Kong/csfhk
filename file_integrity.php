<?php
/**
 * File Integrity Checker Plugin
 * Monitors file changes and detects unauthorized modifications
 */

class FileIntegrity {
    private static $integrityFile = null;
    private static $monitoredFiles = [];
    
    /**
     * Initialize file integrity checker
     */
    public static function init() {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        self::$integrityFile = $logDir . '/file_integrity.json';
        
        // Define critical files to monitor
        self::$monitoredFiles = [
            'config.php',
            'db.php',
            'auth.php',
            'waf.php',
            'security.php',
            'advanced_security.php',
            '.htaccess',
            'web.config',
        ];
    }
    
    /**
     * Calculate file hash
     */
    public static function calculateHash($filePath) {
        if (!file_exists($filePath)) {
            return null;
        }
        
        return hash_file('sha256', $filePath);
    }
    
    /**
     * Check file integrity
     */
    public static function checkIntegrity($filePath = null) {
        if (self::$integrityFile === null) {
            self::init();
        }
        
        $integrity = [];
        if (file_exists(self::$integrityFile)) {
            $integrity = json_decode(file_get_contents(self::$integrityFile), true) ?: [];
        }
        
        $filesToCheck = $filePath ? [$filePath] : self::$monitoredFiles;
        $violations = [];
        
        foreach ($filesToCheck as $file) {
            $fullPath = __DIR__ . '/' . $file;
            
            if (!file_exists($fullPath)) {
                continue;
            }
            
            $currentHash = self::calculateHash($fullPath);
            $storedHash = $integrity[$file] ?? null;
            
            if ($storedHash === null) {
                // First time checking, store hash
                $integrity[$file] = [
                    'hash' => $currentHash,
                    'last_checked' => time(),
                    'created' => time()
                ];
            } else {
                if ($storedHash['hash'] !== $currentHash) {
                    // File modified!
                    $violations[] = [
                        'file' => $file,
                        'old_hash' => $storedHash['hash'],
                        'new_hash' => $currentHash,
                        'timestamp' => time()
                    ];
                    
                    // Log violation
                    error_log("FILE INTEGRITY VIOLATION: $file has been modified!");
                    
                    // Update stored hash (file was legitimately changed)
                    $integrity[$file] = [
                        'hash' => $currentHash,
                        'last_checked' => time(),
                        'created' => $storedHash['created'],
                        'last_modified' => time()
                    ];
                } else {
                    // File unchanged, update last checked
                    $integrity[$file]['last_checked'] = time();
                }
            }
        }
        
        file_put_contents(self::$integrityFile, json_encode($integrity));
        
        return $violations;
    }
    
    /**
     * Get integrity status
     */
    public static function getStatus() {
        if (self::$integrityFile === null) {
            self::init();
        }
        
        $integrity = [];
        if (file_exists(self::$integrityFile)) {
            $integrity = json_decode(file_get_contents(self::$integrityFile), true) ?: [];
        }
        
        $status = [];
        foreach (self::$monitoredFiles as $file) {
            $fullPath = __DIR__ . '/' . $file;
            $currentHash = file_exists($fullPath) ? self::calculateHash($fullPath) : null;
            $storedHash = $integrity[$file]['hash'] ?? null;
            
            $status[$file] = [
                'exists' => file_exists($fullPath),
                'current_hash' => $currentHash,
                'stored_hash' => $storedHash,
                'integrity_ok' => $currentHash === $storedHash,
                'last_checked' => $integrity[$file]['last_checked'] ?? null,
                'last_modified' => $integrity[$file]['last_modified'] ?? null,
            ];
        }
        
        return $status;
    }
}


<?php
/**
 * Password Policy Enforcement Plugin
 * Enforces strong password requirements
 */

class PasswordPolicy {
    private static $minLength = 8;
    private static $requireUppercase = true;
    private static $requireLowercase = true;
    private static $requireNumbers = true;
    private static $requireSpecial = true;
    private static $maxLength = 128;
    private static $commonPasswords = [
        'password', '123456', '12345678', '1234', 'qwerty',
        'abc123', 'monkey', '1234567', 'letmein', 'trustno1',
        'dragon', 'baseball', 'iloveyou', 'master', 'sunshine',
        'ashley', 'bailey', 'passw0rd', 'shadow', '123123',
        '654321', 'superman', 'qazwsx', 'michael', 'football'
    ];
    
    /**
     * Validate password against policy
     */
    public static function validate($password) {
        $errors = [];
        
        // Length check
        if (strlen($password) < self::$minLength) {
            $errors[] = "Password must be at least " . self::$minLength . " characters";
        }
        
        if (strlen($password) > self::$maxLength) {
            $errors[] = "Password must be no more than " . self::$maxLength . " characters";
        }
        
        // Character requirements
        if (self::$requireUppercase && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        if (self::$requireLowercase && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        if (self::$requireNumbers && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        if (self::$requireSpecial && !preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        // Common password check
        $passwordLower = strtolower($password);
        foreach (self::$commonPasswords as $common) {
            if ($passwordLower === $common || strpos($passwordLower, $common) !== false) {
                $errors[] = "Password is too common. Please choose a more unique password";
                break;
            }
        }
        
        // Sequential characters check
        if (self::hasSequentialChars($password)) {
            $errors[] = "Password contains sequential characters (e.g., abc, 123)";
        }
        
        // Repeated characters check
        if (self::hasRepeatedChars($password)) {
            $errors[] = "Password contains too many repeated characters";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Check for sequential characters
     */
    private static function hasSequentialChars($password) {
        $sequences = ['abc', 'bcd', 'cde', 'def', 'efg', 'fgh', 'ghi', 'hij', 'ijk', 'jkl', 'klm', 'lmn', 'mno', 'nop', 'opq', 'pqr', 'qrs', 'rst', 'stu', 'tuv', 'uvw', 'vwx', 'wxy', 'xyz',
                      '012', '123', '234', '345', '456', '567', '678', '789'];
        
        $passwordLower = strtolower($password);
        foreach ($sequences as $seq) {
            if (strpos($passwordLower, $seq) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check for repeated characters
     */
    private static function hasRepeatedChars($password) {
        $chars = str_split($password);
        $counts = array_count_values($chars);
        
        foreach ($counts as $char => $count) {
            if ($count > (strlen($password) / 3)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Calculate password strength (0-100)
     */
    public static function calculateStrength($password) {
        $strength = 0;
        $length = strlen($password);
        
        // Length contribution (max 40 points)
        $strength += min(40, ($length - 8) * 2);
        
        // Character variety (max 30 points)
        $hasUpper = preg_match('/[A-Z]/', $password);
        $hasLower = preg_match('/[a-z]/', $password);
        $hasNumber = preg_match('/[0-9]/', $password);
        $hasSpecial = preg_match('/[^a-zA-Z0-9]/', $password);
        
        $variety = ($hasUpper ? 1 : 0) + ($hasLower ? 1 : 0) + ($hasNumber ? 1 : 0) + ($hasSpecial ? 1 : 0);
        $strength += $variety * 7.5;
        
        // Entropy estimation (max 30 points)
        $entropy = self::calculateEntropy($password);
        $strength += min(30, $entropy * 2);
        
        return min(100, max(0, $strength));
    }
    
    /**
     * Calculate password entropy
     */
    private static function calculateEntropy($password) {
        $charset = 0;
        
        if (preg_match('/[a-z]/', $password)) $charset += 26;
        if (preg_match('/[A-Z]/', $password)) $charset += 26;
        if (preg_match('/[0-9]/', $password)) $charset += 10;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $charset += 33;
        
        if ($charset === 0) return 0;
        
        $length = strlen($password);
        return $length * log($charset, 2);
    }
}


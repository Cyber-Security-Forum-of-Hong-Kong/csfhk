<?php
/**
 * Bot Detection Plugin
 * Detects and blocks automated bots, scrapers, and malicious crawlers
 */

class BotDetection {
    private static $botPatterns = [
        // Known bot user agents
        '/bot|crawler|spider|crawling|scraper|fetcher|indexer/i',
        '/googlebot|bingbot|slurp|duckduckbot|baiduspider|yandexbot|sogou|exabot|facebot|ia_archiver/i',
        '/curl|wget|python|java|perl|ruby|php|scrapy|mechanize|httpclient/i',
        '/headless|phantom|selenium|webdriver|puppeteer|playwright/i',
        '/semrush|ahrefs|majestic|moz\.com|dotbot|blexbot/i',
    ];
    
    private static $suspiciousPatterns = [
        // Suspicious behavior patterns
        '/no user-agent/i',
        '/^$/',
        '/^Mozilla$/', // Too simple
        '/^[A-Z]+$/', // All caps (suspicious)
    ];
    
    private static $honeypotFile = null;
    
    /**
     * Initialize bot detection
     */
    public static function init() {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        self::$honeypotFile = $logDir . '/honeypot_catches.json';
    }
    
    /**
     * Check if request is from a bot
     */
    public static function isBot($strict = false) {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Empty user agent is suspicious
        if (empty($userAgent)) {
            return true;
        }
        
        // Check against known bot patterns
        foreach (self::$botPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                // Allow legitimate search engine bots in non-strict mode
                if (!$strict && preg_match('/googlebot|bingbot|slurp/i', $userAgent)) {
                    // Verify it's actually from that search engine (basic check)
                    if (self::verifySearchEngineBot($userAgent)) {
                        continue;
                    }
                }
                return true;
            }
        }
        
        // Check suspicious patterns
        foreach (self::$suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }
        
        // Check for missing or suspicious headers
        if (!isset($_SERVER['HTTP_ACCEPT']) || empty($_SERVER['HTTP_ACCEPT'])) {
            return true;
        }
        
        // Check for suspicious header combinations
        if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && !isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Verify search engine bot (basic verification)
     */
    private static function verifySearchEngineBot($userAgent) {
        // In production, you should verify by reverse DNS lookup
        // This is a simplified check
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // For now, just check if IP is not obviously malicious
        // Real implementation should do reverse DNS lookup
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
    
    /**
     * Check honeypot field (for forms)
     */
    public static function checkHoneypot($fieldName = 'website') {
        // Honeypot fields should be empty (bots fill them, humans don't)
        if (isset($_POST[$fieldName]) && !empty($_POST[$fieldName])) {
            self::logHoneypotCatch($fieldName, $_POST[$fieldName]);
            return false; // Bot detected
        }
        
        return true; // Not a bot
    }
    
    /**
     * Generate honeypot field HTML
     */
    public static function generateHoneypotField($fieldName = 'website', $label = 'Website') {
        // Style to hide from humans but visible to bots
        return sprintf(
            '<div style="position:absolute;left:-9999px;visibility:hidden;" aria-hidden="true">
                <label for="%s">%s (leave blank)</label>
                <input type="text" name="%s" id="%s" autocomplete="off" tabindex="-1" />
            </div>',
            htmlspecialchars($fieldName),
            htmlspecialchars($label),
            htmlspecialchars($fieldName),
            htmlspecialchars($fieldName)
        );
    }
    
    /**
     * Log honeypot catch
     */
    private static function logHoneypotCatch($field, $value) {
        if (self::$honeypotFile === null) {
            self::init();
        }
        
        $catch = [
            'field' => $field,
            'value' => substr($value, 0, 100),
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => time(),
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
        ];
        
        $catches = [];
        if (file_exists(self::$honeypotFile)) {
            $catches = json_decode(file_get_contents(self::$honeypotFile), true) ?: [];
        }
        
        $catches[] = $catch;
        
        // Keep last 500 catches
        if (count($catches) > 500) {
            $catches = array_slice($catches, -500);
        }
        
        file_put_contents(self::$honeypotFile, json_encode($catches));
        
        error_log("Honeypot catch: Bot filled field '$field' with value: " . substr($value, 0, 50));
    }
    
    /**
     * Check request rate (bot behavior)
     */
    public static function checkRequestRate($ip, $maxRequests = 30, $timeWindow = 60) {
        $rateFile = __DIR__ . '/logs/bot_rates.json';
        
        $rates = [];
        if (file_exists($rateFile)) {
            $rates = json_decode(file_get_contents($rateFile), true) ?: [];
        }
        
        $now = time();
        $ipKey = md5($ip);
        
        // Clean old entries
        foreach ($rates as $key => $data) {
            if ($now - $data['first_request'] > $timeWindow) {
                unset($rates[$key]);
            }
        }
        
        // Check current IP
        if (isset($rates[$ipKey])) {
            $data = $rates[$ipKey];
            
            if ($now - $data['first_request'] <= $timeWindow) {
                $rates[$ipKey]['count']++;
                $rates[$ipKey]['last_request'] = $now;
                
                if ($rates[$ipKey]['count'] > $maxRequests) {
                    file_put_contents($rateFile, json_encode($rates));
                    return false; // Rate exceeded
                }
            } else {
                $rates[$ipKey] = [
                    'count' => 1,
                    'first_request' => $now,
                    'last_request' => $now
                ];
            }
        } else {
            $rates[$ipKey] = [
                'count' => 1,
                'first_request' => $now,
                'last_request' => $now
            ];
        }
        
        file_put_contents($rateFile, json_encode($rates));
        return true; // Rate OK
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


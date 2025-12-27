<?php
/**
 * Timing Analysis Plugin
 * Detects timing-based attacks and anomalies
 */

class TimingAnalysis {
    private static $timingFile = null;
    private static $baseline = [];
    
    /**
     * Initialize timing analysis
     */
    public static function init() {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        self::$timingFile = $logDir . '/timing_analysis.json';
    }
    
    /**
     * Start timing measurement
     */
    public static function start($operation) {
        $_SESSION['timing_' . $operation] = microtime(true);
    }
    
    /**
     * End timing measurement and analyze
     */
    public static function end($operation) {
        if (!isset($_SESSION['timing_' . $operation])) {
            return;
        }
        
        $startTime = $_SESSION['timing_' . $operation];
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        unset($_SESSION['timing_' . $operation]);
        
        // Analyze timing
        $analysis = self::analyzeTiming($operation, $duration);
        
        // Log if suspicious
        if ($analysis['suspicious']) {
            if (class_exists('SecurityMonitor')) {
                SecurityMonitor::logEvent('TIMING_ANOMALY', 'medium', "Suspicious timing for $operation", [
                    'duration' => $duration,
                    'baseline' => $analysis['baseline'],
                    'deviation' => $analysis['deviation']
                ]);
            }
        }
        
        return $analysis;
    }
    
    /**
     * Analyze timing pattern
     */
    private static function analyzeTiming($operation, $duration) {
        if (self::$timingFile === null) {
            self::init();
        }
        
        // Load timing data
        $timings = [];
        if (file_exists(self::$timingFile)) {
            $timings = json_decode(file_get_contents(self::$timingFile), true) ?: [];
        }
        
        // Initialize operation tracking
        if (!isset($timings[$operation])) {
            $timings[$operation] = [
                'measurements' => [],
                'baseline' => $duration,
                'count' => 0
            ];
        }
        
        $opData = &$timings[$operation];
        
        // Add measurement
        $opData['measurements'][] = $duration;
        $opData['count']++;
        
        // Keep only last 100 measurements
        if (count($opData['measurements']) > 100) {
            $opData['measurements'] = array_slice($opData['measurements'], -100);
        }
        
        // Calculate baseline (average)
        $opData['baseline'] = array_sum($opData['measurements']) / count($opData['measurements']);
        
        // Calculate standard deviation
        $variance = 0;
        foreach ($opData['measurements'] as $measurement) {
            $variance += pow($measurement - $opData['baseline'], 2);
        }
        $stdDev = sqrt($variance / count($opData['measurements']));
        
        // Check for anomalies
        $deviation = abs($duration - $opData['baseline']);
        $suspicious = $deviation > ($stdDev * 3); // 3 standard deviations
        
        // Save timing data
        file_put_contents(self::$timingFile, json_encode($timings));
        
        return [
            'duration' => $duration,
            'baseline' => $opData['baseline'],
            'deviation' => $deviation,
            'suspicious' => $suspicious
        ];
    }
    
    /**
     * Detect timing attacks (e.g., password timing attacks)
     */
    public static function detectTimingAttack($operation, $duration) {
        $analysis = self::analyzeTiming($operation, $duration);
        
        // Timing attacks often show consistent patterns
        // Very fast responses might indicate bypass
        // Very slow responses might indicate brute force
        
        if ($analysis['suspicious']) {
            if (class_exists('SecurityMonitor')) {
                SecurityMonitor::logEvent('TIMING_ATTACK_SUSPECTED', 'high', "Possible timing attack on $operation");
            }
            
            return true;
        }
        
        return false;
    }
}


<?php
/**
 * Security Maintenance Script
 * Run periodically (via cron) to perform security maintenance tasks
 */

// This script should be run via cron: php security_maintenance.php

define('IN_APP', true);

// Load all security plugins
require __DIR__ . '/security_orchestrator.php';
SecurityOrchestrator::init();

echo "Starting security maintenance...\n";

// 1. Check file integrity
if (class_exists('FileIntegrity')) {
    echo "Checking file integrity...\n";
    $violations = FileIntegrity::checkIntegrity();
    if (count($violations) > 0) {
        echo "WARNING: " . count($violations) . " file integrity violations detected!\n";
        foreach ($violations as $violation) {
            echo "  - {$violation['file']} has been modified\n";
        }
    } else {
        echo "File integrity check passed.\n";
    }
}

// 2. Perform security audit
if (class_exists('SecurityAudit')) {
    echo "Performing security audit...\n";
    $audit = SecurityAudit::performAudit();
    echo "Security score: {$audit['score']}/100 ({$audit['status']})\n";
}

// 3. Clean old log files
echo "Cleaning old log entries...\n";
$logDir = __DIR__ . '/logs';
$files = [
    'security_alerts.json',
    'anomalies.json',
    'request_history.json',
    'database_queries.json',
    'threat_responses.json',
];

foreach ($files as $file) {
    $path = $logDir . '/' . $file;
    if (file_exists($path)) {
        $data = json_decode(file_get_contents($path), true) ?: [];
        $now = time();
        $cleaned = array_filter($data, function($entry) use ($now) {
            return isset($entry['timestamp']) && ($now - $entry['timestamp']) < 2592000; // 30 days
        });
        
        if (count($cleaned) < count($data)) {
            file_put_contents($path, json_encode(array_values($cleaned)));
            echo "  Cleaned " . (count($data) - count($cleaned)) . " old entries from $file\n";
        }
    }
}

// 4. Clean expired blacklist entries
if (class_exists('IPReputation')) {
    echo "Cleaning expired blacklist entries...\n";
    $blacklistFile = $logDir . '/ip_blacklist.json';
    if (file_exists($blacklistFile)) {
        $blacklist = json_decode(file_get_contents($blacklistFile), true) ?: [];
        $now = time();
        $cleaned = array_filter($blacklist, function($entry) use ($now) {
            return !isset($entry['expires']) || $entry['expires'] > $now;
        });
        
        if (count($cleaned) < count($blacklist)) {
            file_put_contents($blacklistFile, json_encode(array_values($cleaned)));
            echo "  Removed " . (count($blacklist) - count($cleaned)) . " expired blacklist entries\n";
        }
    }
}

// 5. Generate security report
if (class_exists('SecurityOrchestrator')) {
    echo "Generating security report...\n";
    $dashboard = SecurityOrchestrator::getDashboardData();
    echo "Security Score: {$dashboard['security_score']}/100\n";
    echo "Threats Blocked (24h): {$dashboard['threats_blocked_24h']}\n";
    echo "Failed Logins (24h): {$dashboard['failed_logins_24h']}\n";
    echo "Blocked IPs: {$dashboard['blocked_ips']}\n";
}

echo "Security maintenance completed.\n";


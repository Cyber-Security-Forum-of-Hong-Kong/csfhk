<?php
/**
 * Login Handler
 */

// Define constant to allow included files
define('IN_APP', true);

// Load WAF first to protect against attacks
require __DIR__ . '/security/waf.php';
require __DIR__ . '/security/security.php';
require __DIR__ . '/security/advanced_security.php';
require __DIR__ . '/security/security_monitor.php';
require __DIR__ . '/security/bot_detection.php';
require __DIR__ . '/security/ip_reputation.php';

// Initialize security plugins
require __DIR__ . '/security/security_headers.php';
require __DIR__ . '/security/session_security.php';
require __DIR__ . '/security/threat_response.php';
require __DIR__ . '/security/rate_limiter_advanced.php';
require __DIR__ . '/security/ddos_protection.php';
require __DIR__ . '/security/behavioral_analysis.php';
require __DIR__ . '/security/request_validator.php';
require __DIR__ . '/security/security_correlation.php';
require __DIR__ . '/security/timing_analysis.php';
require __DIR__ . '/security/device_fingerprinting.php';
require __DIR__ . '/security/intrusion_detection.php';
require __DIR__ . '/security/threat_intelligence.php';
require __DIR__ . '/security/automated_response.php';

SecurityHeaders::setAll();
SessionSecurity::init();
SecurityMonitor::init();
BotDetection::init();
IPReputation::init();
ThreatResponse::init();
AdvancedRateLimiter::init();
AdvancedSecurity::init();
DDoSProtection::init();
BehavioralAnalysis::init();
SecurityCorrelation::init();
TimingAnalysis::init();
DeviceFingerprinting::init();
IntrusionDetection::init();
ThreatIntelligence::init();
AutomatedResponse::init();

// DDoS Protection
if (!DDoSProtection::check()) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests']);
    exit;
}

// Request validation
RequestValidator::normalizeRequest();
$requestValidation = RequestValidator::validateRequest();
if (!$requestValidation['valid']) {
    SecurityCorrelation::correlate('INVALID_REQUEST', ['ip' => $clientIP, 'errors' => $requestValidation['errors']]);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Validate headers
if (!AdvancedSecurity::validateHeaders()) {
    SecurityCorrelation::correlate('INVALID_HEADERS', ['ip' => $clientIP]);
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Detect anomalies
if (!AdvancedSecurity::detectAnomalies()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Anomalous request detected']);
    exit;
}

// Record request
AdvancedSecurity::recordRequest();

// Check IP reputation
$clientIP = Security::getClientIP();

// Threat intelligence check
$threatCheck = ThreatIntelligence::checkIP($clientIP);
if ($threatCheck['threat']) {
    SecurityMonitor::logEvent('THREAT_INTELLIGENCE_MATCH', 'high', 
        "Threat intelligence match on login: " . $threatCheck['reason']);
    AutomatedResponse::handleThreat('THREAT_INTELLIGENCE', $threatCheck['severity'], ['ip' => $clientIP]);
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (IPReputation::isBlacklisted($clientIP)) {
    SecurityMonitor::logEvent('BLACKLISTED_IP', 'high', "Blacklisted IP attempted login: $clientIP");
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Bot detection
if (BotDetection::isBot(true)) {
    SecurityMonitor::logEvent('BOT_DETECTED', 'medium', "Bot detected on login: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'));
    IPReputation::updateReputation($clientIP, -10);
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Check honeypot (if form has it)
if (!BotDetection::checkHoneypot('website')) {
    SecurityMonitor::logEvent('HONEYPOT_CATCH', 'high', "Honeypot triggered on login");
    IPReputation::updateReputation($clientIP, -30);
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Intrusion Detection System scan
if (!IntrusionDetection::scanRequest()) {
    SecurityMonitor::logEvent('IDS_BLOCKED', 'critical', "IDS blocked login from: $clientIP");
    SecurityCorrelation::correlate('IDS_DETECTION', ['ip' => $clientIP, 'action' => 'login']);
    AutomatedResponse::handleThreat('INTRUSION_DETECTED', 'critical', ['ip' => $clientIP]);
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

WAF::init();
if (!WAF::checkRequest()) {
    // Request was blocked by WAF
    SecurityMonitor::logEvent('WAF_BLOCKED', 'high', "WAF blocked login from: $clientIP");
    SecurityCorrelation::correlate('WAF_BLOCKED', ['ip' => $clientIP, 'action' => 'login']);
    AutomatedResponse::handleThreat('WAF_BLOCKED', 'high', ['ip' => $clientIP]);
    IPReputation::updateReputation($clientIP, -15);
    exit;
}

require __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Advanced rate limiting
if (!AdvancedRateLimiter::checkLimit('login')) {
    SecurityCorrelation::correlate('RATE_LIMIT_EXCEEDED', ['ip' => $clientIP, 'action' => 'login']);
    $remaining = AdvancedRateLimiter::getRemaining('login');
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => "Too many requests. Please try again later."]);
    exit;
}

// Behavioral analysis
$behaviorAnalysis = BehavioralAnalysis::analyze();
if ($behaviorAnalysis['risk_score'] > 60) {
    SecurityCorrelation::correlate('HIGH_RISK_BEHAVIOR', ['ip' => $clientIP, 'risk_score' => $behaviorAnalysis['risk_score']]);
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Suspicious activity detected']);
    exit;
}

// Start timing analysis
TimingAnalysis::start('login_attempt');

// Check request size
Security::checkRequestSize(10240); // 10KB max for login

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!Security::verifyCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$email = Security::sanitizeInput($_POST['email'] ?? '', 'email');
$password = $_POST['password'] ?? '';

$result = loginUser($email, $password);

// End timing analysis
TimingAnalysis::end('login_attempt');

// Correlate login result
if ($result['success']) {
    SecurityCorrelation::correlate('SUCCESSFUL_LOGIN', ['ip' => $clientIP, 'email' => $email]);
} else {
    SecurityCorrelation::correlate('FAILED_LOGIN', ['ip' => $clientIP, 'email' => $email]);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);

?>


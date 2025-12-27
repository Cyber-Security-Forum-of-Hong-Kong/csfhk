<?php
// Define constant to allow included files
define('IN_APP', true);

header('Content-Type: application/json; charset=utf-8');

// Load WAF first to protect against attacks
require __DIR__ . '/security/waf.php';
require __DIR__ . '/security/security.php';
require __DIR__ . '/security/advanced_security.php';
require __DIR__ . '/security/security_monitor.php';
require __DIR__ . '/security/bot_detection.php';
require __DIR__ . '/security/ip_reputation.php';
require __DIR__ . '/security/request_signature.php';
require __DIR__ . '/security/security_headers.php';
require __DIR__ . '/security/file_integrity.php';
require __DIR__ . '/security/encryption.php';
require __DIR__ . '/security/encrypted_transmission.php';
require __DIR__ . '/security/database_monitor.php';
require __DIR__ . '/security/threat_response.php';
require __DIR__ . '/security/security_audit.php';
require __DIR__ . '/security/rate_limiter_advanced.php';
require __DIR__ . '/security/ddos_protection.php';
require __DIR__ . '/security/behavioral_analysis.php';
require __DIR__ . '/security/request_validator.php';
require __DIR__ . '/security/security_correlation.php';
require __DIR__ . '/security/api_key_validation.php';
require __DIR__ . '/security/timing_analysis.php';
require __DIR__ . '/security/device_fingerprinting.php';
require __DIR__ . '/security/intrusion_detection.php';
require __DIR__ . '/security/password_policy.php';
require __DIR__ . '/security/session_fixation_protection.php';
require __DIR__ . '/security/file_upload_security.php';
require __DIR__ . '/security/security_token_rotation.php';
require __DIR__ . '/security/threat_intelligence.php';
require __DIR__ . '/security/automated_response.php';

// Initialize all security plugins
EncryptedTransmission::init(); // Initialize encrypted transmission first
SecurityHeaders::setAll(); // Set security headers immediately
SecurityMonitor::init();
BotDetection::init();
IPReputation::init();
RequestSignature::init();
AdvancedSecurity::init();
FileIntegrity::init();
DatabaseMonitor::init();
ThreatResponse::init();
SecurityAudit::init();
AdvancedRateLimiter::init();
DDoSProtection::init();
BehavioralAnalysis::init();
SecurityCorrelation::init();
APIKeyValidation::init();
TimingAnalysis::init();
DeviceFingerprinting::init();
IntrusionDetection::init();
SecurityTokenRotation::init();
ThreatIntelligence::init();
AutomatedResponse::init();

// DDoS Protection - Check first
if (!DDoSProtection::check()) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many requests']);
    exit;
}

// Request validation and normalization
RequestValidator::normalizeRequest();
$requestValidation = RequestValidator::validateRequest();
if (!$requestValidation['valid']) {
    SecurityMonitor::logEvent('INVALID_REQUEST', 'medium', 'Request validation failed', $requestValidation['errors']);
    SecurityCorrelation::correlate('INVALID_REQUEST', ['ip' => $clientIP, 'errors' => $requestValidation['errors']]);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

// Validate headers first
if (!AdvancedSecurity::validateHeaders()) {
    SecurityCorrelation::correlate('INVALID_HEADERS', ['ip' => $clientIP]);
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid request headers']);
    exit;
}

// Detect request smuggling
if (AdvancedSecurity::detectRequestSmuggling()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

// Validate input lengths
if (!AdvancedSecurity::validateAllInputs()) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => 'Input too large']);
    exit;
}

// Detect anomalies
if (!AdvancedSecurity::detectAnomalies()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Anomalous request detected']);
    exit;
}

// Record request for analysis
AdvancedSecurity::recordRequest();

// Fingerprint request
AdvancedSecurity::fingerprintRequest();

// Check IP reputation
$clientIP = Security::getClientIP();

// Threat intelligence check
$threatCheck = ThreatIntelligence::checkIP($clientIP);
if ($threatCheck['threat']) {
    SecurityMonitor::logEvent('THREAT_INTELLIGENCE_MATCH', 'high', 
        "Threat intelligence match: " . $threatCheck['reason']);
    AutomatedResponse::handleThreat('THREAT_INTELLIGENCE', $threatCheck['severity'], ['ip' => $clientIP]);
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

// Check user agent against threat intelligence
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$uaThreatCheck = ThreatIntelligence::checkUserAgent($userAgent);
if ($uaThreatCheck['threat']) {
    SecurityMonitor::logEvent('MALICIOUS_USER_AGENT', 'high', 
        "Malicious user agent detected: $userAgent");
    IPReputation::updateReputation($clientIP, -20);
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

if (IPReputation::isBlacklisted($clientIP)) {
    SecurityMonitor::logEvent('BLACKLISTED_IP', 'high', "Blacklisted IP attempted access: $clientIP");
    SecurityCorrelation::correlate('BLACKLISTED_IP_ACCESS', ['ip' => $clientIP]);
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

// Check if IP is whitelisted (bypass some checks)
$isWhitelisted = IPReputation::isWhitelisted($clientIP);

// Bot detection (strict mode for non-whitelisted IPs)
if (!$isWhitelisted && BotDetection::isBot(true)) {
    SecurityMonitor::logEvent('BOT_DETECTED', 'medium', "Bot detected: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'));
    IPReputation::updateReputation($clientIP, -10);
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

// Check bot request rate
if (!$isWhitelisted && !BotDetection::checkRequestRate($clientIP, 30, 60)) {
    SecurityMonitor::logEvent('BOT_RATE_EXCEEDED', 'high', "Bot rate limit exceeded: $clientIP");
    IPReputation::updateReputation($clientIP, -20);
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many requests']);
    exit;
}

// Intrusion Detection System scan
if (!IntrusionDetection::scanRequest()) {
    // Intrusion detected
    SecurityMonitor::logEvent('IDS_BLOCKED', 'critical', "IDS blocked request from: $clientIP");
    SecurityCorrelation::correlate('IDS_DETECTION', ['ip' => $clientIP]);
    AutomatedResponse::handleThreat('INTRUSION_DETECTED', 'critical', ['ip' => $clientIP]);
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

WAF::init();
if (!WAF::checkRequest()) {
    // Request was blocked by WAF
    SecurityMonitor::logEvent('WAF_BLOCKED', 'high', "WAF blocked request from: $clientIP");
    SecurityCorrelation::correlate('WAF_BLOCKED', ['ip' => $clientIP]);
    AutomatedResponse::handleThreat('WAF_BLOCKED', 'high', ['ip' => $clientIP]);
    IPReputation::updateReputation($clientIP, -15);
    exit;
}

// Check request size (1MB max for API)
Security::checkRequestSize(1048576);

// Advanced rate limiting for API
if (!AdvancedRateLimiter::checkLimit('api')) {
    SecurityCorrelation::correlate('RATE_LIMIT_EXCEEDED', ['ip' => $clientIP]);
    $remaining = AdvancedRateLimiter::getRemaining('api');
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Rate limit exceeded. Please try again later.']);
    exit;
}

// Device fingerprinting
$userId = isLoggedIn() ? getUserId() : null;
if (!DeviceFingerprinting::checkFingerprint($userId)) {
    SecurityCorrelation::correlate('DEVICE_FINGERPRINT_ANOMALY', ['ip' => $clientIP]);
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Suspicious activity detected']);
    exit;
}

// Behavioral analysis
$behaviorAnalysis = BehavioralAnalysis::analyze($userId);
if ($behaviorAnalysis['risk_score'] > 70) {
    SecurityCorrelation::correlate('HIGH_RISK_BEHAVIOR', ['ip' => $clientIP, 'risk_score' => $behaviorAnalysis['risk_score']]);
    AutomatedResponse::handleThreat('SUSPICIOUS_BEHAVIOR', 'high', ['ip' => $clientIP, 'risk_score' => $behaviorAnalysis['risk_score']]);
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Suspicious activity detected']);
    exit;
}

// Set error handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'PHP Fatal Error: ' . $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ]);
        exit;
    }
});

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';

// Check if $mysqli is available
if (!isset($mysqli) || !$mysqli) {
    http_response_code(500);
    // Don't expose database connection details (security)
    // Log the actual error for debugging
    if (function_exists('getDBConnectionError')) {
        $db_error = getDBConnectionError();
        if ($db_error) {
            error_log("Database connection error: " . $db_error);
        }
    }
    // Return generic error to user
    echo json_encode([
        'ok' => false, 
        'error' => 'Service temporarily unavailable',
        'message' => 'Unable to process request. Please try again later.'
    ]);
    exit;
}

function json_error($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_db_error($mysqli, $prefix = 'DB error') {
    // Don't expose database error details to users (security)
    // Log the actual error for debugging
    $actualError = '';
    if ($mysqli && method_exists($mysqli, 'error')) {
        $actualError = $mysqli->error;
        error_log("Database error: $prefix - $actualError");
    }
    
    // Return generic error message to user
    json_error('Database operation failed', 500);
}

$method = $_SERVER['REQUEST_METHOD'];

// Start timing analysis for request processing
TimingAnalysis::start('api_request');

// Whitelist of allowed actions to prevent action injection
$allowedActions = ['list', 'view', 'create', 'reply', 'delete'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;

// Validate action against whitelist
if (!$action || !in_array($action, $allowedActions, true)) {
    json_error('Invalid or missing action', 400);
}

// Sanitize action (defense in depth)
$action = Security::sanitizeInput($action, 'string');

if ($action === 'list' && $method === 'GET') {
    // Use prepared statement for security (even though no user input, best practice)
    $sql = "SELECT id, topic, category, content, users, date, time, views FROM discuss ORDER BY date DESC, time DESC LIMIT 1000";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        json_db_error($mysqli, 'DB prepare error');
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res) {
        $stmt->close();
        json_db_error($mysqli, 'DB query error (list): ' . $mysqli->error);
    }
    
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        // Get reply count using prepared statement
        $discussionId = (int)$row['id'];
        $replyCount = 0;
        
        // 嘗試獲取回覆數量，如果表不存在則返回0
        $countStmt = $mysqli->prepare("SELECT COUNT(*) as cnt FROM user_discuss WHERE discussion_id = ?");
        if ($countStmt) {
            $countStmt->bind_param('i', $discussionId);
            if ($countStmt->execute()) {
                $countResult = $countStmt->get_result();
                if ($countRow = $countResult->fetch_assoc()) {
                    $replyCount = (int)$countRow['cnt'];
                }
            }
            $countStmt->close();
        } else {
            // 如果表不存在，回覆數量為0
            $replyCount = 0;
        }
        
        // Build response with proper field mapping and output encoding
        $responseRow = [
            'id' => $discussionId,
            'title' => htmlspecialchars($row['topic'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'category' => htmlspecialchars($row['category'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'content' => htmlspecialchars($row['content'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'author' => htmlspecialchars($row['users'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'date' => htmlspecialchars($row['date'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'time' => htmlspecialchars($row['time'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'views' => (int)($row['views'] ?? 0),
            'reply_count' => $replyCount
        ];
        $rows[] = $responseRow;
    }
    
    $stmt->close();
    
    // Always return valid JSON
    TimingAnalysis::end('api_request');
    SecurityCorrelation::correlate('API_LIST', ['ip' => $clientIP]);
    echo json_encode(['ok' => true, 'discussions' => $rows], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

if ($action === 'view' && $method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) json_error('Invalid id');

    $stmt = $mysqli->prepare("SELECT id,
                                     topic    AS title,
                                     category,
                                     content,
                                     users    AS author,
                                     date,
                                     time,
                                     views
                              FROM discuss
                              WHERE id = ?");
    if (!$stmt) {
        json_db_error($mysqli, 'DB prepare error');
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $thread = $res->fetch_assoc();
    if (!$thread) json_error('Not found', 404);

    $update = $mysqli->prepare("UPDATE discuss SET views = views + 1 WHERE id = ?");
    if (!$update) {
        json_db_error($mysqli, 'DB prepare error');
    }
    $update->bind_param('i', $id);
    $update->execute();
    $thread['views'] = (int)$thread['views'] + 1;

    // 查詢該討論的所有回覆（從 user_discuss 表）
    $stmt = $mysqli->prepare("SELECT id, author, content, date, time FROM user_discuss WHERE discussion_id = ? ORDER BY id ASC");
    if (!$stmt) {
        $error = $mysqli->error;
        if (strpos($error, "doesn't exist") !== false || strpos($error, "Table") !== false) {
            // 如果表不存在，返回空回覆列表而不是錯誤
            echo json_encode(['ok' => true, 'thread' => $thread, 'replies' => []]);
            exit;
        }
        json_db_error($mysqli, 'DB prepare error');
    }
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        if (strpos($error, "doesn't exist") !== false || strpos($error, "Table") !== false) {
            // 如果表不存在，返回空回覆列表
            echo json_encode(['ok' => true, 'thread' => $thread, 'replies' => []]);
            exit;
        }
        json_db_error($mysqli, 'DB execute error');
    }
    $res = $stmt->get_result();
    $replies = [];
    while ($row = $res->fetch_assoc()) {
        // 確保所有字段都存在並正確格式化
        $reply = [
            'id' => (int)$row['id'],
            'author' => isset($row['author']) ? $row['author'] : '',
            'content' => isset($row['content']) ? $row['content'] : '',
            'date' => isset($row['date']) ? $row['date'] : '',
            'time' => isset($row['time']) ? $row['time'] : ''
        ];
        $replies[] = $reply;
    }
    $stmt->close();

    // 返回討論主題和所有回覆 (with output encoding)
    $safeThread = [
        'id' => (int)$thread['id'],
        'title' => htmlspecialchars($thread['title'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'category' => htmlspecialchars($thread['category'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'content' => htmlspecialchars($thread['content'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'author' => htmlspecialchars($thread['author'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'date' => htmlspecialchars($thread['date'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'time' => htmlspecialchars($thread['time'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'views' => (int)($thread['views'] ?? 0),
    ];
    
    $safeReplies = [];
    foreach ($replies as $reply) {
        $safeReplies[] = [
            'id' => (int)$reply['id'],
            'author' => htmlspecialchars($reply['author'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'content' => htmlspecialchars($reply['content'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'date' => htmlspecialchars($reply['date'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'time' => htmlspecialchars($reply['time'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ];
    }
    
    TimingAnalysis::end('api_request');
    SecurityCorrelation::correlate('API_VIEW', ['ip' => $clientIP, 'thread_id' => $id]);
    echo json_encode([
        'ok' => true, 
        'thread' => $safeThread, 
        'replies' => $safeReplies,
        'reply_count' => count($safeReplies)
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

if ($action === 'create' && $method === 'POST') {
    // Require login for creating posts
    if (!isLoggedIn()) {
        json_error('請先登入', 401);
    }
    
    // Verify CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Security::verifyCSRFToken($csrfToken)) {
        json_error('Invalid security token', 403);
    }
    
    // Sanitize and validate inputs
    $title = Security::sanitizeInput(trim($_POST['title'] ?? ''), 'string');
    $category = Security::sanitizeInput(trim($_POST['category'] ?? ''), 'string');
    $content = Security::sanitizeInput(trim($_POST['content'] ?? ''), 'string');
    
    // Validate lengths
    if (strlen($title) > 200) {
        json_error('Title too long (max 200 characters)');
    }
    if (strlen($content) > 10000) {
        json_error('Content too long (max 10000 characters)');
    }
    if (strlen($category) > 50) {
        json_error('Category too long (max 50 characters)');
    }
    
    // Check for suspicious content
    if (Security::isSuspicious($title) || Security::isSuspicious($content)) {
        SecurityCorrelation::correlate('SUSPICIOUS_CONTENT', ['ip' => $clientIP, 'action' => 'create']);
        json_error('Invalid content detected');
    }
    
    // Always use logged-in user's name as author
    $author = getUserName();

    if ($title === '' || $content === '' || !$author) {
        json_error('Missing fields');
    }

    $now = new DateTime('now', new DateTimeZone('Asia/Hong_Kong'));
    $date = $now->format('Y-m-d');
    $time = $now->format('H:i');

    $stmt = $mysqli->prepare("INSERT INTO discuss (topic, category, content, users, date, time, views) VALUES (?,?,?,?,?,?,0)");
    if (!$stmt) {
        json_db_error($mysqli, 'DB prepare error');
    }
    $stmt->bind_param('ssssss', $title, $category, $content, $author, $date, $time);
    if (!$stmt->execute()) {
        json_db_error($mysqli, 'DB insert error');
    }
    $id = $stmt->insert_id;

    TimingAnalysis::end('api_request');
    SecurityCorrelation::correlate('API_CREATE', ['ip' => $clientIP, 'user' => $author]);
    echo json_encode([
        'ok' => true,
        'thread' => [
            'id' => (int)$id,
            'title' => $title,
            'category' => $category,
            'content' => $content,
            'author' => $author,
            'date' => $date,
            'time' => $time,
            'views' => 0,
            'reply_count' => 0,
        ]
    ]);
    exit;
}

// 新增回覆
if ($action === 'reply' && $method === 'POST') {
    // Require login for replying
    if (!isLoggedIn()) {
        json_error('請先登入', 401);
    }
    
    // Verify CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Security::verifyCSRFToken($csrfToken)) {
        json_error('Invalid security token', 403);
    }
    
    $threadId = isset($_POST['thread_id']) ? (int)$_POST['thread_id'] : 0;
    // Use logged-in user's name instead of POST data
    $author = getUserName() ?? Security::sanitizeInput(trim($_POST['author'] ?? ''), 'string');
    $content = Security::sanitizeInput(trim($_POST['content'] ?? ''), 'string');
    
    // Validate lengths
    if (strlen($content) > 5000) {
        json_error('Content too long (max 5000 characters)');
    }
    
    // Check for suspicious content
    if (Security::isSuspicious($content)) {
        SecurityCorrelation::correlate('SUSPICIOUS_CONTENT', ['ip' => $clientIP, 'action' => 'reply']);
        json_error('Invalid content detected');
    }
    
    if ($threadId <= 0 || $author === '' || $content === '') {
        json_error('Missing fields');
    }

    // 驗證討論主題是否存在
    $checkStmt = $mysqli->prepare("SELECT id FROM discuss WHERE id = ?");
    if ($checkStmt) {
        $checkStmt->bind_param('i', $threadId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows === 0) {
            $checkStmt->close();
            json_error('討論主題不存在', 404);
        }
        $checkStmt->close();
    }

    $now = new DateTime('now', new DateTimeZone('Asia/Hong_Kong'));
    $date = $now->format('Y-m-d');
    $time = $now->format('H:i');

    $stmt = $mysqli->prepare("INSERT INTO user_discuss (discussion_id, author, content, date, time) VALUES (?,?,?,?,?)");
    if (!$stmt) {
        $error = $mysqli->error;
        if (strpos($error, "doesn't exist") !== false || strpos($error, "Table") !== false) {
            json_error('user_discuss 表不存在，請先創建該表', 500);
        }
        json_db_error($mysqli, 'DB prepare error');
    }
    $stmt->bind_param('issss', $threadId, $author, $content, $date, $time);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        if (strpos($error, "doesn't exist") !== false || strpos($error, "Table") !== false) {
            json_error('user_discuss 表不存在，請先創建該表', 500);
        }
        json_db_error($mysqli, 'DB insert error');
    }
    
    $replyId = $stmt->insert_id;
    $stmt->close();

    // 返回成功響應，包含新創建的回覆信息
    TimingAnalysis::end('api_request');
    SecurityCorrelation::correlate('API_REPLY', ['ip' => $clientIP, 'user' => $author, 'thread_id' => $threadId]);
    echo json_encode([
        'ok' => true, 
        'message' => '回覆發布成功',
        'reply' => [
            'id' => (int)$replyId,
            'discussion_id' => $threadId,
            'author' => $author,
            'content' => $content,
            'date' => $date,
            'time' => $time
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'delete' && $method === 'POST') {
    // Require login for deleting
    if (!isLoggedIn()) {
        json_error('請先登入', 401);
    }
    
    // Verify CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Security::verifyCSRFToken($csrfToken)) {
        json_error('Invalid security token', 403);
    }
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) json_error('Invalid id');
    
    // Validate ID is reasonable
    if ($id > 2147483647) {
        json_error('Invalid id');
    }
    
    // CRITICAL: Verify user owns the thread (IDOR fix)
    $stmt = $mysqli->prepare("SELECT users FROM discuss WHERE id = ?");
    if (!$stmt) {
        json_db_error($mysqli, 'DB prepare error');
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $thread = $result->fetch_assoc();
    $stmt->close();
    
    if (!$thread) {
        json_error('Thread not found', 404);
    }
    
    // Authorization check: Only thread owner can delete
    if ($thread['users'] !== getUserName()) {
        // Log unauthorized delete attempt
        error_log("Unauthorized delete attempt: User " . getUserName() . " tried to delete thread $id owned by " . $thread['users']);
        SecurityCorrelation::correlate('UNAUTHORIZED_DELETE', ['ip' => $clientIP, 'user' => getUserName(), 'thread_id' => $id]);
        json_error('Unauthorized: You can only delete your own threads', 403);
    }

    $stmt = $mysqli->prepare("DELETE FROM user_discuss WHERE discussion_id = ?");
    if (!$stmt) {
        json_db_error($mysqli, 'DB prepare error');
    }
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        json_db_error($mysqli, 'DB delete error (replies)');
    }

    $stmt = $mysqli->prepare("DELETE FROM discuss WHERE id = ?");
    if (!$stmt) {
        json_db_error($mysqli, 'DB prepare error');
    }
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        json_db_error($mysqli, 'DB delete error (discuss)');
    }

    TimingAnalysis::end('api_request');
    SecurityCorrelation::correlate('API_DELETE', ['ip' => $clientIP, 'user' => getUserName(), 'thread_id' => $id]);
    echo json_encode(['ok' => true]);
    exit;
}

json_error('Unsupported action or method', 405);




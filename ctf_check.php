<?php
// 後端 CTF 檢查：所有真正 flag 只存在這裡

// Define constant to allow included files
define('IN_APP', true);

// Load WAF first to protect against attacks
require __DIR__ . '/security/waf.php';
require __DIR__ . '/security/security.php';
require __DIR__ . '/security/advanced_security.php';
require __DIR__ . '/security/security_monitor.php';
require __DIR__ . '/security/bot_detection.php';
require __DIR__ . '/security/ip_reputation.php';
require __DIR__ . '/security/ddos_protection.php';
require __DIR__ . '/security/behavioral_analysis.php';
require __DIR__ . '/security/request_validator.php';
require __DIR__ . '/security/security_correlation.php';
require __DIR__ . '/security/timing_analysis.php';

// Initialize security plugins
SecurityMonitor::init();
BotDetection::init();
IPReputation::init();
DDoSProtection::init();
BehavioralAnalysis::init();
SecurityCorrelation::init();
TimingAnalysis::init();
AdvancedSecurity::init();

// DDoS Protection
if (!DDoSProtection::check()) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many requests']);
    exit;
}

// Request validation
RequestValidator::normalizeRequest();
$requestValidation = RequestValidator::validateRequest();
if (!$requestValidation['valid']) {
    $clientIP = Security::getClientIP();
    SecurityCorrelation::correlate('INVALID_REQUEST', ['ip' => $clientIP, 'errors' => $requestValidation['errors']]);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

// Validate headers
if (!AdvancedSecurity::validateHeaders()) {
    $clientIP = Security::getClientIP();
    SecurityCorrelation::correlate('INVALID_HEADERS', ['ip' => $clientIP]);
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

// Check request size
Security::checkRequestSize(10240); // 10KB max

// Detect anomalies
if (!AdvancedSecurity::detectAnomalies()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Anomalous request detected']);
    exit;
}

WAF::init();
if (!WAF::checkRequest()) {
    // Request was blocked by WAF
    $clientIP = Security::getClientIP();
    SecurityMonitor::logEvent('WAF_BLOCKED', 'high', "WAF blocked CTF check from: $clientIP");
    SecurityCorrelation::correlate('WAF_BLOCKED', ['ip' => $clientIP, 'action' => 'ctf_check']);
    exit;
}

require __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');

// Require login
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => '請先登入']);
    exit;
}

// 只接受 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// 支援 JSON body with size and depth limits to prevent JSON DoS
$raw = file_get_contents('php://input');
if ($raw) {
    // Limit JSON input size (10KB max)
    if (strlen($raw) > 10240) {
        http_response_code(413);
        echo json_encode(['ok' => false, 'error' => 'Request too large']);
        exit;
    }
    
    // Decode with depth limit to prevent JSON bombing
    $data = json_decode($raw, true, 3); // Max depth 3
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        exit;
    }
} else {
    $data = $_POST;
}

$id = isset($data['id']) ? intval($data['id']) : 0;
$flag = isset($data['flag']) ? (string)$data['flag'] : '';

if ($id <= 0 || $flag === '') {
    echo json_encode(['ok' => false, 'error' => 'Invalid input']);
    exit;
}

function normalize_flag(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    // 去除 - 空白 大括號，與前端一致
    return preg_replace('/[-\s{}]/u', '', $s);
}

// 所有題目的正確 flag（只在 PHP 內部存在）
$flags = [
    1  => 'CSFHK{hidden_in_source}',
    2  => 'CSFHK-{hong_kong_forum}',
    3  => 'CSFHK{love_net_work}',
    4  => 'CSFHK{hello_cybersecurity}',
    5  => 'CSFHK{console_master}',
    6  => 'CSFHK{hex_decode}',
    7  => 'CSFHK-cookie_master',          // 其實題目主要靠 Cookie，但這裡仍保留正確值
    8  => 'CSFHK{binary}',
    9  => 'CSFHK-{exif_data}',
    10 => 'CSFHK-{script_obfuscation}',
    11 => 'CSFHK{xor_reverse_master}',
    12 => 'CSFHK{format_string}',
    13 => 'CSFHK{B1nary_b4se64_f0n0t_h1d1n_3N1]',
    14 => 'CSFHK{vigenere_cipher}',
    15 => 'CSFHK{secure_log}',
    16 => 'CSFHK{playfair}',
    17 => 'CSFHK{multibase}',
    18 => 'CSFHK{rsa}',
    19 => 'CSFHK{the_quick_brown}',
    20 => 'CSFHK{xor_cipher}',
    21 => 'CSFHK{Learn_to_decrypt}',
    22 => 'CSFHK{multi_layer_encoding}',
    23 => 'CSFHK{multi_xor_challenge}',
    24 => 'CSFHK{columnar_transposition}',
    25 => 'CSFHK{rsa_hard}',
    26 => 'CSFHK{combined_cipher}',
    27 => 'CSFHK{reverse_base64}',
    28 => 'CSFHK{advanced_vigenere}',
    29 => 'CSFHK{assembly}',
    30 => 'CSFHK{js_obfuscate}',
    31 => 'CSFHK{steganography}',
];

if (!isset($flags[$id])) {
    echo json_encode(['ok' => false, 'error' => 'Unknown challenge']);
    exit;
}

$userNorm = normalize_flag($flag);
$correctNorm = normalize_flag($flags[$id]);

$ok = hash_equals($correctNorm, $userNorm);

// If flag is correct, update user progress
if ($ok) {
    // Get challenge points (you may need to fetch this from your challenge data)
    $challenge_points = [
        1 => 10, 2 => 10, 3 => 15, 4 => 15, 5 => 10, 6 => 15,
        7 => 20, 8 => 20, 9 => 25, 10 => 25,
        11 => 40, 12 => 45, 13 => 35, 14 => 40, 15 => 35,
        16 => 45, 17 => 40, 18 => 50, 19 => 38, 20 => 42,
        21 => 43, 22 => 55, 23 => 52, 24 => 48, 25 => 60,
        26 => 58, 27 => 45, 28 => 50, 29 => 55, 30 => 48, 31 => 40
    ];
    
    $points = $challenge_points[$id] ?? 0;
    updateCTFProgress(getUserId(), $id, $points);
}

echo json_encode([
    'ok' => $ok,
    // 不回傳真正 flag，避免洩露
    'error' => $ok ? null : 'Flag 不正確',
]);



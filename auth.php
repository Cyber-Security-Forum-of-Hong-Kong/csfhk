<?php
/**
 * Authentication and Session Management
 * Handles user login, signup, logout, and session validation
 */

// Define constant if not already defined (for direct includes)
if (!defined('IN_APP')) {
    define('IN_APP', true);
}

require __DIR__ . '/db.php';
require __DIR__ . '/security/security.php';
require __DIR__ . '/security/session_fixation_protection.php';
require __DIR__ . '/security/password_policy.php';
require __DIR__ . '/security/device_fingerprinting.php';
require __DIR__ . '/security/threat_intelligence.php';
require __DIR__ . '/security/automated_response.php';

// Configure secure session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_lifetime', 0); // Session cookie (expires when browser closes)
ini_set('session.gc_maxlifetime', 3600); // 1 hour

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    
    // Regenerate session ID periodically to prevent session fixation
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) { // 30 minutes
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_name'])) {
        return false;
    }
    
    // Verify session hasn't been hijacked
    $currentIP = Security::getClientIP();
    $currentUA = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Check IP address (allow for proxy changes, but log suspicious activity)
    if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $currentIP) {
        // IP changed - could be legitimate (mobile network) or attack
        // Log but don't block immediately
        error_log("Session IP changed for user {$_SESSION['user_id']}: {$_SESSION['ip_address']} -> $currentIP");
    }
    
    // Check user agent
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $currentUA) {
        // User agent changed - likely session hijacking
        error_log("Session user agent changed for user {$_SESSION['user_id']}");
        return false;
    }
    
    // Check session timeout (1 hour)
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 3600) {
        logoutUser();
        return false;
    }
    
    return true;
}

/**
 * Get current user ID
 */
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user name
 */
function getUserName() {
    return $_SESSION['user_name'] ?? null;
}

/**
 * Get current user email
 */
function getUserEmail() {
    return $_SESSION['user_email'] ?? null;
}

/**
 * Get current user level
 */
function getUserLevel() {
    return $_SESSION['user_level'] ?? 'guest';
}

/**
 * Require login - redirect to index if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php?login_required=1');
        exit;
    }
}

/**
 * Login user
 */
function loginUser($email, $password) {
    // Check account lockout
    if (Security::isAccountLocked($email)) {
        return ['success' => false, 'message' => '帳戶已被暫時鎖定，請稍後再試'];
    }
    
    // Check rate limit
    if (!Security::checkRateLimit('login', 5, 300)) {
        $remaining = Security::getRateLimitRemaining('login', 300);
        return ['success' => false, 'message' => '嘗試次數過多，請在 ' . ceil($remaining / 60) . ' 分鐘後再試'];
    }
    
    // Sanitize input
    $email = Security::sanitizeInput($email, 'email');
    if (!Security::validateEmail($email)) {
        return ['success' => false, 'message' => '無效的電子郵件地址'];
    }
    
    $mysqli = getDBConnection();
    if (!$mysqli) {
        return ['success' => false, 'message' => '數據庫連接失敗'];
    }

    $stmt = $mysqli->prepare("SELECT id, account_name, account_email, password_hash, user_level FROM users WHERE account_email = ?");
    if (!$stmt) {
        return ['success' => false, 'message' => '數據庫查詢失敗'];
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        Security::recordFailedAttempt($email);
        $clientIP = Security::getClientIP();
        if (class_exists('SecurityMonitor')) {
            SecurityMonitor::logEvent('FAILED_LOGIN', 'medium', "Failed login attempt for: $email (user not found)", ['ip' => $clientIP]);
        }
        if (class_exists('IPReputation')) {
            IPReputation::updateReputation($clientIP, -5);
        }
        return ['success' => false, 'message' => '電子郵件或密碼錯誤'];
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        Security::recordFailedAttempt($email);
        return ['success' => false, 'message' => '電子郵件或密碼錯誤'];
    }

    // Clear failed attempts on successful login
    Security::clearFailedAttempts($email);
    
    // Update IP reputation positively
    $clientIP = Security::getClientIP();
    if (class_exists('IPReputation')) {
        IPReputation::updateReputation($clientIP, 5);
    }
    if (class_exists('SecurityMonitor')) {
        SecurityMonitor::logEvent('SUCCESSFUL_LOGIN', 'low', "Successful login for: $email");
    }
    
    // Regenerate session ID on login to prevent session fixation
    if (class_exists('SessionFixationProtection')) {
        SessionFixationProtection::forceRegeneration();
    } else {
        session_regenerate_id(true);
    }
    
    // Device fingerprinting
    if (class_exists('DeviceFingerprinting')) {
        DeviceFingerprinting::checkFingerprint($user['id']);
    }

    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['account_name'];
    $_SESSION['user_email'] = $user['account_email'];
    $_SESSION['user_level'] = $user['user_level'];
    $_SESSION['login_time'] = time();
    $_SESSION['ip_address'] = Security::getClientIP();
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';

    return ['success' => true, 'message' => '登入成功', 'user' => $user];
}

/**
 * Signup new user
 */
function signupUser($name, $email, $password, $confirmPassword = null) {
    // Check rate limit for signup
    if (!Security::checkRateLimit('signup', 3, 3600)) {
        $remaining = Security::getRateLimitRemaining('signup', 3600);
        return ['success' => false, 'message' => '註冊嘗試過多，請在 ' . ceil($remaining / 60) . ' 分鐘後再試'];
    }
    
    // Sanitize and validate input
    $name = Security::sanitizeInput($name, 'string');
    $email = Security::sanitizeInput($email, 'email');
    
    // Check for suspicious content
    if (Security::isSuspicious($name) || Security::isSuspicious($email)) {
        return ['success' => false, 'message' => '輸入包含無效字符'];
    }
    
    $mysqli = getDBConnection();
    if (!$mysqli) {
        return ['success' => false, 'message' => '數據庫連接失敗'];
    }

    // Validate input
    if (empty($name) || empty($email) || empty($password)) {
        return ['success' => false, 'message' => '請填寫所有欄位'];
    }

    if (strlen($name) < 3 || strlen($name) > 50) {
        return ['success' => false, 'message' => '用戶名長度必須在 3-50 個字符之間'];
    }

    if (!Security::validateEmail($email)) {
        return ['success' => false, 'message' => '無效的電子郵件地址'];
    }

    // Enhanced password validation with PasswordPolicy
    if (class_exists('PasswordPolicy')) {
        $passwordValidation = PasswordPolicy::validate($password);
        if (!$passwordValidation['valid']) {
            return ['success' => false, 'message' => implode('. ', $passwordValidation['errors'])];
        }
        
        // Check password strength
        $strength = PasswordPolicy::calculateStrength($password);
        if ($strength < 40) {
            return ['success' => false, 'message' => '密碼強度不足，請使用更複雜的密碼'];
        }
    } else {
        // Fallback to Security::validatePassword
        $passwordValidation = Security::validatePassword($password);
        if (!$passwordValidation['valid']) {
            return ['success' => false, 'message' => $passwordValidation['error']];
        }
    }

    // Confirm password check (if provided)
    if ($confirmPassword !== null && $password !== $confirmPassword) {
        return ['success' => false, 'message' => '兩次輸入的密碼不一致'];
    }

    // Check if email already exists
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE account_email = ?");
    if (!$stmt) {
        return ['success' => false, 'message' => '數據庫查詢失敗'];
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmt->close();
        return ['success' => false, 'message' => '此電子郵件已被註冊'];
    }
    $stmt->close();

    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user
    $stmt = $mysqli->prepare("INSERT INTO users (account_name, account_email, password_hash, user_level) VALUES (?, ?, ?, 'guest')");
    if (!$stmt) {
        return ['success' => false, 'message' => '數據庫插入失敗: ' . $mysqli->error];
    }

    $stmt->bind_param('sss', $name, $email, $password_hash);
    
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => '註冊失敗: ' . $error];
    }

    $user_id = $mysqli->insert_id;
    $stmt->close();

    // Initialize CTF progress
    $stmt = $mysqli->prepare("INSERT INTO ctf_progress (user_id, ctf_total_points, challenges_done) VALUES (?, 0, 0)");
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
    }

    // 不自動登入，讓用戶回到登入頁自己登入
    return ['success' => true, 'message' => '註冊成功！', 'user_id' => $user_id];
}

/**
 * Logout user
 */
function logoutUser() {
    $_SESSION = array();
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}

/**
 * Update CTF progress when user completes a challenge
 */
function updateCTFProgress($user_id, $challenge_id, $points) {
    $mysqli = getDBConnection();
    if (!$mysqli) {
        return false;
    }

    // Check if already completed
    $stmt = $mysqli->prepare("SELECT id FROM ctf_user_challenges WHERE user_id = ? AND challenge_id = ?");
    $stmt->bind_param('ii', $user_id, $challenge_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        return false; // Already completed
    }
    $stmt->close();

    // Insert challenge completion
    $stmt = $mysqli->prepare("INSERT INTO ctf_user_challenges (user_id, challenge_id, points) VALUES (?, ?, ?)");
    $stmt->bind_param('iii', $user_id, $challenge_id, $points);
    $stmt->execute();
    $stmt->close();

    // Update progress summary
    $stmt = $mysqli->prepare("
        INSERT INTO ctf_progress (user_id, ctf_total_points, challenges_done)
        VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE
            ctf_total_points = ctf_total_points + ?,
            challenges_done = challenges_done + 1
    ");
    $stmt->bind_param('iii', $user_id, $points, $points);
    $stmt->execute();
    $stmt->close();

    return true;
}

/**
 * Get user CTF progress
 */
function getUserCTFProgress($user_id) {
    $mysqli = getDBConnection();
    if (!$mysqli) {
        return ['total_points' => 0, 'challenges_done' => 0];
    }

    $stmt = $mysqli->prepare("SELECT ctf_total_points, challenges_done FROM ctf_progress WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $progress = $result->fetch_assoc();
        $stmt->close();
        return [
            'total_points' => (int)$progress['ctf_total_points'],
            'challenges_done' => (int)$progress['challenges_done']
        ];
    }
    
    $stmt->close();
    return ['total_points' => 0, 'challenges_done' => 0];
}

?>


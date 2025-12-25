<?php

header('Content-Type: application/json; charset=utf-8');

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

// Check if $mysqli is available
if (!isset($mysqli) || !$mysqli) {
    http_response_code(500);
    $error_msg = 'Database connection failed';
    // Try to get more details about the connection error
    if (function_exists('getDBConnectionError')) {
        $db_error = getDBConnectionError();
        if ($db_error) {
            $error_msg .= ': ' . htmlspecialchars($db_error);
        }
    }
    echo json_encode([
        'ok' => false, 
        'error' => $error_msg,
        'message' => 'Unable to establish database connection. Please check your database configuration.'
    ]);
    exit;
}

function json_error($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_db_error($mysqli, $prefix = 'DB error') {
    $errorMsg = $prefix;
    if ($mysqli && method_exists($mysqli, 'error')) {
        $errorMsg .= ': ' . $mysqli->error;
    }
    json_error($errorMsg, 500);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    json_error('Missing action', 400);
}

if ($action === 'list' && $method === 'GET') {
    // Use the simplest possible query - no GROUP BY, no JOIN
    $sql = "SELECT id, topic, category, content, users, date, time, views FROM discuss ORDER BY date DESC, time DESC";
    
    $res = $mysqli->query($sql);
    if (!$res) {
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
        
        // Build response with proper field mapping
        $responseRow = [
            'id' => $discussionId,
            'title' => isset($row['topic']) ? $row['topic'] : '',
            'category' => isset($row['category']) ? $row['category'] : '',
            'content' => isset($row['content']) ? $row['content'] : '',
            'author' => isset($row['users']) ? $row['users'] : '',
            'date' => isset($row['date']) ? $row['date'] : '',
            'time' => isset($row['time']) ? $row['time'] : '',
            'views' => isset($row['views']) ? (int)$row['views'] : 0,
            'reply_count' => $replyCount
        ];
        $rows[] = $responseRow;
    }
    
    // Always return valid JSON
    echo json_encode(['ok' => true, 'discussions' => $rows], JSON_UNESCAPED_UNICODE);
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

    // 返回討論主題和所有回覆
    echo json_encode([
        'ok' => true, 
        'thread' => $thread, 
        'replies' => $replies,
        'reply_count' => count($replies)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'create' && $method === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $author = trim($_POST['author'] ?? '');

    if ($title === '' || $content === '' || $author === '') {
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
    $threadId = isset($_POST['thread_id']) ? (int)$_POST['thread_id'] : 0;
    $author = trim($_POST['author'] ?? '');
    $content = trim($_POST['content'] ?? '');
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
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) json_error('Invalid id');

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

    echo json_encode(['ok' => true]);
    exit;
}

json_error('Unsupported action or method', 405);



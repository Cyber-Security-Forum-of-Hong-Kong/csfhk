<?php

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db.php';

function json_error(string $msg, int $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function json_db_error(mysqli $mysqli, string $prefix = 'DB error') {
    json_error($prefix . ': ' . $mysqli->error, 500);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    json_error('Missing action', 400);
}

if ($action === 'list' && $method === 'GET') {
    $sql = "SELECT d.id,
                   d.topic    AS title,
                   d.category AS category,
                   d.content  AS content,
                   d.users    AS author,
                   d.date,
                   d.time,
                   d.views,
                   COUNT(r.id) AS reply_count
            FROM discuss d
            LEFT JOIN replies r ON r.discussion_id = d.id
            GROUP BY d.id
            ORDER BY d.date DESC, d.time DESC";
    $res = $mysqli->query($sql);
    if (!$res) {
        json_db_error($mysqli);
    }
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['views'] = (int)$row['views'];
        $row['reply_count'] = (int)$row['reply_count'];
        $rows[] = $row;
    }
    echo json_encode(['ok' => true, 'discussions' => $rows]);
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
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $thread = $res->fetch_assoc();
    if (!$thread) json_error('Not found', 404);

    $update = $mysqli->prepare("UPDATE discuss SET views = views + 1 WHERE id = ?");
    $update->bind_param('i', $id);
    $update->execute();
    $thread['views'] = (int)$thread['views'] + 1;

    $stmt = $mysqli->prepare("SELECT id, author, content, date, time FROM replies WHERE discussion_id = ? ORDER BY id ASC");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $replies = [];
    while ($row = $res->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $replies[] = $row;
    }

    echo json_encode(['ok' => true, 'thread' => $thread, 'replies' => $replies]);
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

    $now = new DateTime('now', new DateTimeZone('Asia/Hong_Kong'));
    $date = $now->format('Y-m-d');
    $time = $now->format('H:i');

    $stmt = $mysqli->prepare("INSERT INTO replies (discussion_id, author, content, date, time) VALUES (?,?,?,?,?)");
    $stmt->bind_param('issss', $threadId, $author, $content, $date, $time);
    if (!$stmt->execute()) {
        json_db_error($mysqli, 'DB insert error');
    }

    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'delete' && $method === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) json_error('Invalid id');

    $stmt = $mysqli->prepare("DELETE FROM replies WHERE discussion_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    $stmt = $mysqli->prepare("DELETE FROM discuss WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    echo json_encode(['ok' => true]);
    exit;
}

json_error('Unsupported action or method', 405);



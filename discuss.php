<?php
// 討論區頁面：顯示及管理討論主題與回覆
define('IN_APP', true);

// Set security headers
require_once __DIR__ . '/security/security_headers.php';
SecurityHeaders::setAll();

require __DIR__ . '/config/config.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
requireLogin(); // Require user to be logged in

// 數據庫測試模式：通過 ?test_db=1 參數觸發
$testDbMode = isset($_GET['test_db']) && $_GET['test_db'] == '1';

if ($testDbMode) {
    // 測試數據庫連接狀態（使用 view_database 的方式）
    $dbConnected = isset($mysqli) && $mysqli && !$mysqli->connect_errno;
}
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>討論區 | CSFHK - 香港網安論壇</title>
    <link rel="stylesheet" href="assets/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    <?php if ($testDbMode): ?>
    <style>
        .db-viewer-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
            font-family: 'Courier New', monospace;
        }
        .db-viewer-container h1 {
            color: #00ffff;
            margin-bottom: 20px;
            border-bottom: 2px solid #00ff88;
            padding-bottom: 10px;
        }
        .db-viewer-container h2 {
            color: #00ff88;
            margin: 30px 0 15px 0;
            border-left: 4px solid #00ff88;
            padding-left: 10px;
        }
        .db-section {
            background: #1a1f3a;
            border: 1px solid #00ff88;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .db-viewer-container table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .db-viewer-container th, .db-viewer-container td {
            border: 1px solid #00ff88;
            padding: 10px;
            text-align: left;
        }
        .db-viewer-container th {
            background: #00ff88;
            color: #0a0e27;
            font-weight: bold;
        }
        .db-viewer-container td {
            background: #0f1529;
        }
        .db-viewer-container tr:hover td {
            background: #1a2340;
        }
        .db-count {
            color: #00ffff;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .db-error {
            color: #ff4444;
            background: #2a0f0f;
            padding: 15px;
            border: 1px solid #ff4444;
            border-radius: 5px;
            margin: 20px 0;
        }
        .db-success {
            color: #00ff88;
            background: #0f2a1f;
            padding: 15px;
            border: 1px solid #00ff88;
            border-radius: 5px;
            margin: 20px 0;
        }
        .db-content-cell {
            max-width: 300px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .db-back-link {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: #00ff88;
            color: #0a0e27;
            border-radius: 5px;
            font-weight: bold;
            text-decoration: none;
        }
        .db-back-link:hover {
            background: #00ffff;
            text-decoration: none;
        }
    </style>
    <?php endif; ?>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo-section">
                <div class="logo">
                    <a href="index.php" style="text-decoration: none; color: inherit;">
                        <span class="logo-text">&gt; CSFHK</span>
                        <span class="cursor-blink">_</span>
                    </a>
                </div>
                <div class="logo-subtitle">香港網安論壇 | Hong Kong Cybersecurity Forum</div>
            </div>
            <nav class="nav">
                <a href="index.php" class="nav-link">首頁</a>
                <a href="ctfquestion.php" class="nav-link">CTF 挑戰</a>
                <a href="discuss.php" class="nav-link active">討論區</a>
                <a href="resource.php" class="nav-link">資源</a>
                <?php if (isLoggedIn()): ?>
                    <span class="nav-link" style="color: var(--accent-green);">
                        👤 <?php echo htmlspecialchars(getUserName()); ?>
                    </span>
                    <a href="logout.php" class="nav-link">登出</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <?php if ($testDbMode): ?>
        <!-- 數據庫測試模式 - 使用表格顯示 -->
        <div class="db-viewer-container">
            <a href="discuss.php" class="db-back-link">← 返回討論區</a>
            <h1>📊 Database Content Viewer</h1>

            <?php
            if (!$dbConnected) {
                echo '<div class="db-error">❌ Database connection failed!</div>';
                if (function_exists('getDBConnectionError')) {
                    $error = getDBConnectionError();
                    if ($error) {
                        echo '<div class="db-error">Error: ' . htmlspecialchars($error) . '</div>';
                    }
                }
            } else {
                echo '<div class="db-success">✅ Database connection successful!</div>';
            }
            ?>

            <?php if ($dbConnected): ?>
            <!-- Discussions Table -->
            <div class="db-section">
                <h2>💬 Discussions Table</h2>
                <?php
                // Use prepared statement for security (even in test mode)
                $sql = "SELECT * FROM discuss ORDER BY date DESC, time DESC LIMIT 1000";
                $stmt = $mysqli->prepare($sql);
                if ($stmt) {
                    $stmt->execute();
                    $result = $stmt->get_result();
                } else {
                    $result = false;
                }
                
                if ($result) {
                    $count = $result->num_rows;
                    echo '<div class="db-count">Total Discussions: ' . htmlspecialchars($count) . '</div>';
                    
                    if ($count > 0) {
                        echo '<table>';
                        echo '<tr>';
                        echo '<th>ID</th>';
                        echo '<th>Topic (Title)</th>';
                        echo '<th>Category</th>';
                        echo '<th>Content</th>';
                        echo '<th>Users (Author)</th>';
                        echo '<th>Date</th>';
                        echo '<th>Time</th>';
                        echo '<th>Views</th>';
                        echo '</tr>';
                        
                        while ($row = $result->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($row['id'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($row['topic'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($row['category'] ?? '') . '</td>';
                            $content = $row['content'] ?? '';
                            $contentPreview = htmlspecialchars(substr($content, 0, 100));
                            echo '<td class="db-content-cell">' . $contentPreview . (strlen($content) > 100 ? '...' : '') . '</td>';
                            echo '<td>' . htmlspecialchars($row['users'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($row['date'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($row['time'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($row['views'] ?? '0') . '</td>';
                            echo '</tr>';
                        }
                        
                        echo '</table>';
                        if (isset($stmt)) {
                            $stmt->close();
                        }
                    } else {
                        echo '<div class="db-error">No discussions found in database.</div>';
                    }
                } else {
                    echo '<div class="db-error">Error querying discussions: ' . htmlspecialchars($mysqli->error ?? 'Unknown error') . '</div>';
                }
                ?>
            </div>

            <!-- Replies Table -->
            <div class="db-section">
                <h2>💭 Replies Table</h2>
                <?php
                // Use prepared statement for security
                $sql = "SELECT * FROM user_discuss ORDER BY id ASC LIMIT 1000";
                $stmt = $mysqli->prepare($sql);
                if ($stmt) {
                    $stmt->execute();
                    $result = $stmt->get_result();
                } else {
                    $result = false;
                }
                
                if ($result) {
                    $count = $result->num_rows;
                    echo '<div class="db-count">Total Replies: ' . htmlspecialchars($count) . '</div>';
                    
                    if ($count > 0) {
                        echo '<table>';
                        echo '<tr>';
                        echo '<th>ID</th>';
                        echo '<th>Discussion ID</th>';
                        echo '<th>Author</th>';
                        echo '<th>Content</th>';
                        echo '<th>Date</th>';
                        echo '<th>Time</th>';
                        echo '</tr>';
                        
                        while ($row = $result->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($row['id'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($row['discussion_id'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($row['author'] ?? '') . '</td>';
                            $content = $row['content'] ?? '';
                            $contentPreview = htmlspecialchars(substr($content, 0, 100));
                            echo '<td class="db-content-cell">' . $contentPreview . (strlen($content) > 100 ? '...' : '') . '</td>';
                            echo '<td>' . htmlspecialchars($row['date'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($row['time'] ?? '') . '</td>';
                            echo '</tr>';
                        }
                        
                        echo '</table>';
                        if (isset($stmt)) {
                            $stmt->close();
                        }
                    } else {
                        echo '<div class="db-error">No replies found in database.</div>';
                    }
                } else {
                    echo '<div class="db-error">Error querying replies: ' . htmlspecialchars($mysqli->error ?? 'Unknown error') . '</div>';
                }
                ?>
            </div>

            <!-- Database Statistics -->
            <div class="db-section">
                <h2>📈 Database Statistics</h2>
                <?php
                // Get statistics using prepared statements
                $stats = [];
                
                // Total discussions
                $stmt = $mysqli->prepare("SELECT COUNT(*) as cnt FROM discuss");
                if ($stmt) {
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result) {
                        $row = $result->fetch_assoc();
                        $stats['total_discussions'] = $row['cnt'] ?? 0;
                    }
                    $stmt->close();
                }
                
                // Total replies
                $stmt = $mysqli->prepare("SELECT COUNT(*) as cnt FROM user_discuss");
                if ($stmt) {
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result) {
                        $row = $result->fetch_assoc();
                        $stats['total_replies'] = $row['cnt'] ?? 0;
                    }
                    $stmt->close();
                }
                
                // Discussions by category
                $stmt = $mysqli->prepare("SELECT category, COUNT(*) as cnt FROM discuss GROUP BY category");
                if ($stmt) {
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result) {
                        $stats['by_category'] = [];
                        while ($row = $result->fetch_assoc()) {
                            $stats['by_category'][$row['category'] ?? ''] = $row['cnt'] ?? 0;
                        }
                    }
                    $stmt->close();
                }
                
                // Total views
                $stmt = $mysqli->prepare("SELECT SUM(views) as total FROM discuss");
                if ($stmt) {
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result) {
                        $row = $result->fetch_assoc();
                        $stats['total_views'] = $row['total'] ?? 0;
                    }
                    $stmt->close();
                }
                
                echo '<table>';
                echo '<tr><th>Statistic</th><th>Value</th></tr>';
                echo '<tr><td>Total Discussions</td><td>' . ($stats['total_discussions'] ?? 0) . '</td></tr>';
                echo '<tr><td>Total Replies</td><td>' . ($stats['total_replies'] ?? 0) . '</td></tr>';
                echo '<tr><td>Total Views</td><td>' . ($stats['total_views'] ?? 0) . '</td></tr>';
                
                if (isset($stats['by_category']) && count($stats['by_category']) > 0) {
                    foreach ($stats['by_category'] as $category => $count) {
                        echo '<tr><td>Discussions in "' . htmlspecialchars($category) . '"</td><td>' . $count . '</td></tr>';
                    }
                }
                
                echo '</table>';
                ?>
            </div>

            <div style="margin-top: 30px; padding: 20px; background: #1a1f3a; border: 1px solid #00ff88; border-radius: 5px;">
                <p><strong>Database:</strong> <?php echo htmlspecialchars(isset($DB_NAME) ? $DB_NAME : 'Unknown'); ?></p>
                <p><strong>Host:</strong> <?php echo htmlspecialchars(isset($DB_HOST) ? $DB_HOST : 'Unknown'); ?></p>
                <p><strong>Last Updated:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <!-- 正常討論區模式 -->
        <div class="container">
            <section id="discussions" class="section">
                <h2 class="section-title">
                    <span class="title-icon">[&gt;]</span>
                    討論區 | Discussions
                </h2>
                <div class="discussion-forum">
                    <div class="forum-header">
                        <div class="forum-controls">
                            <input type="text" id="searchInput" class="search-input" placeholder="搜尋討論主題...">
                            <select id="categoryFilter" class="category-filter">
                                <option value="all">所有分類</option>
                                <option value="ctf">CTF 題目</option>
                                <option value="security">網絡安全</option>
                                <option value="general">一般討論</option>
                                <option value="food">飲食</option>
                                <option value="others">其他</option>
                            </select>
                        </div>
                        <div style="display: flex; gap: 1rem;">
                            <a href="discuss.php?test_db=1" class="btn-new-post" style="text-decoration: none; display: inline-flex; align-items: center;">
                                <span>🔍</span> 測試數據庫
                            </a>
                            <button class="btn-new-post" id="newPostBtn">
                                <span>+</span> 發表新主題
                            </button>
                        </div>
                    </div>
                    
                    <div class="threads-container" id="threadsContainer">
                    </div>
                </div>
            </section>
        </div>
        <?php endif; ?>
    </main>

    <!-- New Post Modal -->
    <div class="modal" id="newPostModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>發表新主題</h3>
                <button class="modal-close" id="closeModal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="newPostForm">
                    <div class="form-group">
                        <label for="postTitle">標題</label>
                        <input type="text" id="postTitle" required>
                    </div>
                    <div class="form-group">
                        <label for="postCategory">分類</label>
                        <select id="postCategory" required>
                            <option value="ctf">CTF 題目討論</option>
                            <option value="security">網絡安全議題</option>
                            <option value="general">一般討論</option>
                            <option value="food">飲食</option>
                            <option value="others">其他</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="postContent">內容</label>
                        <textarea id="postContent" rows="8" required></textarea>
                    </div>
                    <!-- 作者會自動使用登入帳號名稱，使用者無需輸入 -->
                    <button type="submit" class="btn-submit">發布</button>
                </form>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <p>&gt; CSFHK - 香港網安論壇</p>
                <p>Building a Secure Cyber Community</p>
                <p class="footer-meta">© 2024 CSFHK | Stay Secure, Stay Informed</p>
            </div>
        </div>
    </footer>

    <script>
        // API endpoint from environment configuration
        const API_ENDPOINT = '<?php echo htmlspecialchars($API_ENDPOINT, ENT_QUOTES, 'UTF-8'); ?>';
        // CSRF token
        const CSRF_TOKEN = '<?php 
            require_once __DIR__ . "/security/security.php";
            echo htmlspecialchars(Security::generateCSRFToken(), ENT_QUOTES, 'UTF-8');
        ?>';
    </script>
    <script src="assets/script.js"></script>
    <?php if (!$testDbMode): ?>
    <script>
        // 調試：確保討論區正常初始化
        console.log('討論區頁面已加載');
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('threadsContainer');
            console.log('threadsContainer 元素:', container ? '找到' : '未找到');
            if (container) {
                console.log('討論區容器已準備就緒');
                // 測試 API 連接
                fetch(API_ENDPOINT + '?action=list')
                    .then(res => {
                        console.log('API 響應狀態:', res.status);
                        return res.json();
                    })
                    .then(data => {
                        console.log('API 返回數據:', data);
                        if (!data.ok) {
                            console.error('API 錯誤:', data.error);
                            const container = document.getElementById('threadsContainer');
                            if (container) {
                                container.innerHTML = '<div style="padding: 2rem; text-align: center; color: var(--danger);">載入討論失敗：' + (data.error || '未知錯誤') + '</div>';
                            }
                        }
                    })
                    .catch(err => {
                        console.error('API 請求失敗:', err);
                        const container = document.getElementById('threadsContainer');
                        if (container) {
                            container.innerHTML = '<div style="padding: 2rem; text-align: center; color: var(--danger);">無法連接到伺服器，請檢查網絡連接</div>';
                        }
                    });
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>



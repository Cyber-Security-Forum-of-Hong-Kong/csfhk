<?php
// index.php - PHP 版本首頁，內容與原本 index.html 相同
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSFHK - 香港網安論壇 | Hong Kong Cybersecurity Forum</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="logo-section">
                <div class="logo">
                    <span class="logo-text">&gt; CSFHK</span>
                    <span class="cursor-blink">_</span>
                </div>
                <div class="logo-subtitle">香港網安論壇 | Hong Kong Cybersecurity Forum</div>
            </div>
            <nav class="nav">
                <a href="#home" class="nav-link active">首頁</a>
                <a href="ctfquestion.php" class="nav-link">CTF 挑戰</a>
                <a href="discuss.php" class="nav-link">討論區</a>
                <a href="resource.php" class="nav-link">資源</a>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="terminal-window">
            <div class="terminal-header">
                <div class="terminal-buttons">
                    <span class="btn btn-red"></span>
                    <span class="btn btn-yellow"></span>
                    <span class="btn btn-green"></span>
                </div>
                <span class="terminal-title">csfhk@terminal:~$</span>
            </div>
            <div class="terminal-body">
                <div class="terminal-line">
                    <span class="prompt">csfhk@terminal:~$</span>
                    <span class="command">welcome</span>
                </div>
                <div class="terminal-output">
                    <p>╔════════════════════════════════════════╗</p>
                    <p>║   CSFHK - 香港網安論壇                ║</p>
                    <p>║   Connecting Security Professionals   ║</p>
                    <p>╚════════════════════════════════════════╝</p>
                    <p></p>
                    <p>System Status: <span class="status-online">ONLINE</span></p>
                    <p>Active Users: <span class="user-count" id="userCount">0</span></p>
                    <p>Forum Topics: <span id="topicCount">0</span></p>
                    <!-- CSFHK{hidden_in_source} -->
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <section id="ctf" class="section">
                <h2 class="section-title">
                    <span class="title-icon">[&gt;]</span>
                    CTF 挑戰 | CTF Challenges
                </h2>
                <p class="section-description">點擊下方按鈕前往 CTF 挑戰頁面</p>
                <div style="text-align: center; margin: 3rem 0;">
                    <a href="ctfquestion.php" class="btn-new-post" style="display: inline-flex; text-decoration: none;">
                        <span>🚀</span> 進入 CTF 挑戰頁面
                    </a>
                </div>
            </section>
        </div>
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
                            <option value="news">新聞分享</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="postContent">內容</label>
                        <textarea id="postContent" rows="8" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="postAuthor">作者</label>
                        <input type="text" id="postAuthor" placeholder="您的名稱" required>
                    </div>
                    <button type="submit" class="btn-submit">發布</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <p>&gt; CSFHK - 香港網安論壇</p>
                <p>Building a Secure Cyber Community</p>
                <p class="footer-meta">© 2024 CSFHK | Stay Secure, Stay Informed</p>
            </div>
        </div>
    </footer>

    <script src="script.js"></script>
</body>
</html>



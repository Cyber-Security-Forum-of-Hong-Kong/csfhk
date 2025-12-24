<?php
// 討論區頁面：顯示及管理討論主題與回覆
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>討論區 | CSFHK - 香港網安論壇</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo-section">
                <div class="logo">
                    <a href="index.html" style="text-decoration: none; color: inherit;">
                        <span class="logo-text">&gt; CSFHK</span>
                        <span class="cursor-blink">_</span>
                    </a>
                </div>
                <div class="logo-subtitle">香港網安論壇 | Hong Kong Cybersecurity Forum</div>
            </div>
            <nav class="nav">
                <a href="index.php#home" class="nav-link">首頁</a>
                <a href="ctfquestion.html" class="nav-link">CTF 挑戰</a>
                <a href="discuss.php" class="nav-link active">討論區</a>
                <a href="resource.php" class="nav-link">資源</a>
            </nav>
        </div>
    </header>

    <main class="main-content">
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
                        <button class="btn-new-post" id="newPostBtn">
                            <span>+</span> 發表新主題
                        </button>
                    </div>
                    
                    <div class="threads-container" id="threadsContainer">
                    </div>
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
                            <option value="food">飲食</option>
                            <option value="others">其他</option>
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



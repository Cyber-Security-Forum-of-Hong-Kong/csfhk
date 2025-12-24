<?php
// ctfquestion.php - PHP 版本 CTF 挑戰頁面，內容與原本 ctfquestion.html 相同
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CTF 挑戰 | CSFHK - 香港網安論壇</title>
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
                    <a href="index.php" style="text-decoration: none; color: inherit;">
                        <span class="logo-text">&gt; CSFHK</span>
                        <span class="cursor-blink">_</span>
                    </a>
                </div>
                <div class="logo-subtitle">香港網安論壇 | Hong Kong Cybersecurity Forum</div>
            </div>
            <nav class="nav">
                <a href="index.php" class="nav-link">返回首頁</a>
                <a href="index.php#discussions" class="nav-link">討論區</a>
                <a href="index.php#resources" class="nav-link">資源</a>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <section id="ctf" class="section">
                <h2 class="section-title">
                    <span class="title-icon">[&gt;]</span>
                    CTF 挑戰 | CTF Challenges
                </h2>
                <p class="section-description">選擇分類開始挑戰 CTF 題目</p>
                <div class="forum-categories">
                    <div class="category-card" data-category="web">
                        <div class="category-icon">🌐</div>
                        <h3>Web Security</h3>
                        <p>Web 安全挑戰</p>
                        <div class="category-stats">
                            <span class="category-discussion-count">0 題目</span>
                            <span class="difficulty easy">入門</span>
                        </div>
                    </div>
                    <div class="category-card" data-category="crypto">
                        <div class="category-icon">🔐</div>
                        <h3>Cryptography</h3>
                        <p>密碼學挑戰</p>
                        <div class="category-stats">
                            <span class="category-discussion-count">0 題目</span>
                            <span class="difficulty medium">中級</span>
                        </div>
                    </div>
                    <div class="category-card" data-category="forensics">
                        <div class="category-icon">🔍</div>
                        <h3>Digital Forensics</h3>
                        <p>數位鑑識挑戰</p>
                        <div class="category-stats">
                            <span class="category-discussion-count">0 題目</span>
                            <span class="difficulty medium">中級</span>
                        </div>
                    </div>
                    <div class="category-card" data-category="reverse">
                        <div class="category-icon">⚙️</div>
                        <h3>Reverse Engineering</h3>
                        <p>逆向工程挑戰</p>
                        <div class="category-stats">
                            <span class="category-discussion-count">0 題目</span>
                            <span class="difficulty hard">高級</span>
                        </div>
                    </div>
                    <div class="category-card" data-category="pwn">
                        <div class="category-icon">💥</div>
                        <h3>Pwn / Exploitation</h3>
                        <p>漏洞利用挑戰</p>
                        <div class="category-stats">
                            <span class="category-discussion-count">0 題目</span>
                            <span class="difficulty hard">高級</span>
                        </div>
                    </div>
                    <div class="category-card" data-category="misc">
                        <div class="category-icon">🧩</div>
                        <h3>Miscellaneous</h3>
                        <p>雜項挑戰</p>
                        <div class="category-stats">
                            <span class="category-discussion-count">0 題目</span>
                            <span class="difficulty easy">入門</span>
                        </div>
                    </div>
                </div>
            </section>

            <section id="challenges" class="section">
                <div id="challengesContainer">
                </div>
            </section>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <p>&gt; CSFHK - 香港網安論壇</p>
                <p>Building a Secure Cyber Community</p>
                <p class="footer-meta">© 2024 CSFHK | Stay Secure, Stay Informed</p>
            </div>
        </div>
    </footer>

    <script src="ctf.js"></script>
</body>
</html>



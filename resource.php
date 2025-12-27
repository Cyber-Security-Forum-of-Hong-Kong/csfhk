<?php
// 資源頁面：顯示各種網安學習資源與工具連結
// 只有登入用戶可以瀏覽；未登入會被導向首頁登入頁
define('IN_APP', true);

// Set security headers
require_once __DIR__ . '/security_headers.php';
SecurityHeaders::setAll();

require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>資源 | CSFHK - 香港網安論壇</title>
    <link rel="stylesheet" href="assets/styles.css">
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
                <a href="index.php#home" class="nav-link">首頁</a>
                <a href="ctfquestion.php" class="nav-link">CTF 挑戰</a>
                <a href="discuss.php" class="nav-link">討論區</a>
                <a href="resource.php" class="nav-link active">資源</a>
                <?php if (isLoggedIn()): ?>
                    <span class="nav-link" style="color: var(--accent-green);">
                        👤 <?php echo htmlspecialchars(getUserName()); ?>
                    </span>
                    <a href="logout.php" class="nav-link">登出</a>
                <?php else: ?>
                    <a href="index.php?login_required=1" class="nav-link">登入 / 註冊</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <section id="resources" class="section">
                <h2 class="section-title">
                    <span class="title-icon">[&gt;]</span>
                    實用資源 | Resources
                </h2>
                <p class="section-description">精選網絡安全學習資源、工具及平台，幫助你持續成長</p>
                <div class="resources-grid">
                    <div class="resource-card">
                        <h3>學習資源</h3>
                        <ul>
                            <li><a href="https://owasp.org/www-project-top-ten/" target="_blank" rel="noopener noreferrer">OWASP Top 10</a></li>
                            <li><a href="https://ctf-wiki.org/" target="_blank" rel="noopener noreferrer">CTF Wiki</a></li>
                            <li><a href="https://cve.mitre.org/" target="_blank" rel="noopener noreferrer">CVE 漏洞資料庫</a></li>
                            <li><aside><a href="https://www.exploit-db.com/" target="_blank" rel="noopener noreferrer">Exploit Database</a></aside></li>
                            <li><a href="https://portswigger.net/web-security" target="_blank" rel="noopener noreferrer">PortSwigger Web Security Academy</a></li>
                            <li><a href="https://www.hacksplaining.com/" target="_blank" rel="noopener noreferrer">Hacksplaining</a></li>
                        </ul>
                    </div>
                    <div class="resource-card">
                        <h3>工具推薦</h3>
                        <ul>
                            <li><a href="https://portswigger.net/burp" target="_blank" rel="noopener noreferrer">Burp Suite</a></li>
                            <li><a href="https://www.wireshark.org/" target="_blank" rel="noopener noreferrer">Wireshark</a></li>
                            <li><a href="https://www.metasploit.com/" target="_blank" rel="noopener noreferrer">Metasploit</a></li>
                            <li><a href="https://nmap.org/" target="_blank" rel="noopener noreferrer">Nmap</a></li>
                            <li><a href="https://github.com/Gallopsled/pwntools" target="_blank" rel="noopener noreferrer">Pwntools</a></li>
                            <li><a href="https://www.ghidra-sre.org/" target="_blank" rel="noopener noreferrer">Ghidra</a></li>
                        </ul>
                    </div>
                    <div class="resource-card">
                        <h3>CTF 平台</h3>
                        <ul>
                            <li><a href="https://ctftime.org/" target="_blank" rel="noopener noreferrer">CTFtime</a></li>
                            <li><a href="https://picoctf.org/" target="_blank" rel="noopener noreferrer">picoCTF</a></li>
                            <li><a href="https://ctflearn.com/" target="_blank" rel="noopener noreferrer">CTFlearn</a></li>
                            <li><a href="https://www.hackthebox.com/" target="_blank" rel="noopener noreferrer">Hack The Box</a></li>
                            <li><a href="https://tryhackme.com/" target="_blank" rel="noopener noreferrer">TryHackMe</a></li>
                            <li><a href="https://overthewire.org/" target="_blank" rel="noopener noreferrer">OverTheWire</a></li>
                        </ul>
                    </div>
                    <div class="resource-card">
                        <h3>香港相關</h3>
                        <ul>
                            <li><a href="https://www.hkcert.org/" target="_blank" rel="noopener noreferrer">HKCERT</a></li>
                            <li><a href="https://www.hkpc.org/zh-HK/services/cyber-security" target="_blank" rel="noopener noreferrer">HKPC Security</a></li>
                            <li><a href="https://www.pcc.hkpc.org/" target="_blank" rel="noopener noreferrer">香港生產力促進局</a></li>
                            <li><a href="https://www.pcpd.org.hk/" target="_blank" rel="noopener noreferrer">個人資料私隱專員公署</a></li>
                            <li><a href="https://www.gov.hk/" target="_blank" rel="noopener noreferrer">GovHK</a></li>
                        </ul>
                    </div>
                    <div class="resource-card">
                        <h3>在線練習</h3>
                        <ul>
                            <li><a href="https://www.root-me.org/" target="_blank" rel="noopener noreferrer">Root-Me</a></li>
                            <li><a href="https://www.wechall.net/" target="_blank" rel="noopener noreferrer">WeChall</a></li>
                            <li><a href="https://www.pentesterlab.com/" target="_blank" rel="noopener noreferrer">PentesterLab</a></li>
                            <li><a href="https://pentesteracademy.com/" target="_blank" rel="noopener noreferrer">Pentester Academy</a></li>
                            <li><a href="https://www.vulnhub.com/" target="_blank" rel="noopener noreferrer">VulnHub</a></li>
                        </ul>
                    </div>
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

    <script>
        // API endpoint from environment configuration
        const API_ENDPOINT = '<?php echo htmlspecialchars($API_ENDPOINT, ENT_QUOTES, 'UTF-8'); ?>';
    </script>
    <script src="assets/script.js"></script>
</body>
</html>



<?php
// index.php - PHP 版本首頁，內容與原本 index.html 相同
define('IN_APP', true);

// Set security headers and encrypted transmission
require_once __DIR__ . '/security/security_headers.php';
require_once __DIR__ . '/security/encrypted_transmission.php';
EncryptedTransmission::init(); // Initialize encrypted transmission first
SecurityHeaders::setAll();

require __DIR__ . '/config/config.php';
require __DIR__ . '/auth.php';

$login_required = isset($_GET['login_required']) && $_GET['login_required'] == '1';
$logged_out = isset($_GET['logged_out']) && $_GET['logged_out'] == '1';
$is_logged_in = isLoggedIn();
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSFHK - 香港網安論壇 | Hong Kong Cybersecurity Forum</title>
    <link rel="stylesheet" href="assets/styles.css">
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
                <?php if ($is_logged_in): ?>
                    <a href="ctfquestion.php" class="nav-link">CTF 挑戰</a>
                    <a href="discuss.php" class="nav-link">討論區</a>
                <?php endif; ?>
                <a href="resource.php" class="nav-link">資源</a>
                <?php if ($is_logged_in): ?>
                    <span class="nav-link" style="color: var(--accent-green);">
                        👤 <?php echo htmlspecialchars(getUserName()); ?>
                    </span>
                    <a href="logout.php" class="nav-link">登出</a>
                <?php endif; ?>
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
            <?php if (!$is_logged_in): ?>
                <!-- Login/Signup Section -->
                <section id="auth" class="section">
                    <h2 class="section-title">
                        <span class="title-icon">[&gt;]</span>
                        登入 / 註冊 | Login / Sign Up
                    </h2>
                    <?php if ($login_required): ?>
                        <div style="background: rgba(255, 51, 102, 0.1); border: 2px solid var(--danger); border-radius: 8px; padding: 1rem; margin-bottom: 2rem; color: var(--danger);">
                            ⚠️ 請先登入以使用 CTF 挑戰和討論區功能
                        </div>
                    <?php endif; ?>
                    <?php if ($logged_out): ?>
                        <div style="background: rgba(0, 255, 136, 0.1); border: 2px solid var(--accent-green); border-radius: 8px; padding: 1rem; margin-bottom: 2rem; color: var(--accent-green);">
                            ✓ 已成功登出
                        </div>
                    <?php endif; ?>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-top: 2rem;">
                        <!-- Login Form -->
                        <div id="loginCard" class="auth-card" style="background: var(--bg-secondary); border: 2px solid var(--border-color); border-radius: 8px; padding: 2rem;">
                            <h3 style="color: var(--accent-cyan); margin-bottom: 1.5rem;">登入帳號</h3>
                            <form id="loginForm">
                                <div class="form-group">
                                    <label for="loginEmail">電子郵件</label>
                                    <input type="email" id="loginEmail" required>
                                </div>
                                <div class="form-group">
                                    <label for="loginPassword">密碼</label>
                                    <input type="password" id="loginPassword" required>
                                </div>
                                <button type="submit" class="btn-submit" style="width: 100%;">登入</button>
                                <div id="loginMessage" style="margin-top: 1rem;"></div>
                            </form>
                            <div style="margin-top: 1.5rem; text-align: center; color: var(--text-secondary);">
                                還沒有帳號？
                                <button type="button" id="showSignupBtn" class="btn-submit" style="margin-top: 0.5rem; padding: 0.5rem 1rem; font-size: 0.9rem;">
                                    建立新帳號
                                </button>
                            </div>
                        </div>

                        <!-- Signup Form -->
                        <div id="signupCard" class="auth-card" style="background: var(--bg-secondary); border: 2px solid var(--border-color); border-radius: 8px; padding: 2rem; display: none;">
                            <h3 style="color: var(--accent-green); margin-bottom: 1.5rem;">註冊新帳號</h3>
                            <form id="signupForm">
                                <div class="form-group">
                                    <label for="signupName">用戶名稱</label>
                                    <input type="text" id="signupName" required minlength="3" maxlength="50">
                                </div>
                                <div class="form-group">
                                    <label for="signupEmail">電子郵件</label>
                                    <input type="email" id="signupEmail" required>
                                </div>
                                <div class="form-group">
                                    <label for="signupPassword">密碼（至少 6 個字符）</label>
                                    <input type="password" id="signupPassword" required minlength="6">
                                </div>
                                <div class="form-group">
                                    <label for="signupPasswordConfirm">確認密碼</label>
                                    <input type="password" id="signupPasswordConfirm" required minlength="6">
                                </div>
                                <button type="submit" class="btn-submit" style="width: 100%;">註冊</button>
                                <div id="signupMessage" style="margin-top: 1rem;"></div>
                            </form>
                            <div style="margin-top: 1.5rem; text-align: center; color: var(--text-secondary);">
                                已經有帳號？
                                <button type="button" id="showLoginBtn" class="btn-submit" style="margin-top: 0.5rem; padding: 0.5rem 1rem; font-size: 0.9rem;">
                                    返回登入
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            <?php else: ?>
                <!-- Welcome Section for Logged In Users -->
                <section id="welcome" class="section">
                    <h2 class="section-title">
                        <span class="title-icon">[&gt;]</span>
                        歡迎回來，<?php echo htmlspecialchars(getUserName()); ?>！
                    </h2>
                    <?php
                    $progress = getUserCTFProgress(getUserId());
                    ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
                        <div style="background: var(--bg-secondary); border: 2px solid var(--accent-green); border-radius: 8px; padding: 1.5rem; text-align: center;">
                            <div style="font-size: 2rem; color: var(--accent-green); font-weight: bold;"><?php echo $progress['challenges_done']; ?></div>
                            <div style="color: var(--text-secondary); margin-top: 0.5rem;">已完成挑戰</div>
                        </div>
                        <div style="background: var(--bg-secondary); border: 2px solid var(--accent-cyan); border-radius: 8px; padding: 1.5rem; text-align: center;">
                            <div style="font-size: 2rem; color: var(--accent-cyan); font-weight: bold;"><?php echo $progress['total_points']; ?></div>
                            <div style="color: var(--text-secondary); margin-top: 0.5rem;">總分數</div>
                        </div>
                    </div>
                </section>

                <!-- CTF Section -->
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
            <?php endif; ?>
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

    <script>
        // API endpoint from environment configuration
        const API_ENDPOINT = '<?php echo htmlspecialchars($API_ENDPOINT, ENT_QUOTES, 'UTF-8'); ?>';
        // CSRF token
        const CSRF_TOKEN = '<?php require_once __DIR__ . "/security/security.php"; echo Security::generateCSRFToken(); ?>';
    </script>
    <script src="assets/script.js"></script>
    <script>
        function showLoginCard() {
            const loginCard = document.getElementById('loginCard');
            const signupCard = document.getElementById('signupCard');
            if (loginCard && signupCard) {
                loginCard.style.display = 'block';
                signupCard.style.display = 'none';
            }
        }

        function showSignupCard() {
            const loginCard = document.getElementById('loginCard');
            const signupCard = document.getElementById('signupCard');
            if (loginCard && signupCard) {
                loginCard.style.display = 'none';
                signupCard.style.display = 'block';
            }
        }

        // Toggle buttons between login and signup
        document.getElementById('showSignupBtn')?.addEventListener('click', function() {
            showSignupCard();
        });

        document.getElementById('showLoginBtn')?.addEventListener('click', function() {
            showLoginCard();
        });

        // Login form handler
        document.getElementById('loginForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const email = document.getElementById('loginEmail').value;
            const password = document.getElementById('loginPassword').value;
            const messageDiv = document.getElementById('loginMessage');
            
            messageDiv.innerHTML = '<span style="color: var(--accent-cyan);">登入中...</span>';
            
            try {
                const formData = new FormData();
                formData.append('email', email);
                formData.append('password', password);
                formData.append('csrf_token', CSRF_TOKEN);
                
                const response = await fetch('login.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    messageDiv.innerHTML = '<span style="color: var(--accent-green);">✓ ' + result.message + '</span>';
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    messageDiv.innerHTML = '<span style="color: var(--danger);">✗ ' + result.message + '</span>';
                }
            } catch (error) {
                messageDiv.innerHTML = '<span style="color: var(--danger);">✗ 登入失敗，請稍後再試</span>';
            }
        });

        // Signup form handler
        document.getElementById('signupForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const name = document.getElementById('signupName').value;
            const email = document.getElementById('signupEmail').value;
            const password = document.getElementById('signupPassword').value;
            const confirmPassword = document.getElementById('signupPasswordConfirm').value;
            const messageDiv = document.getElementById('signupMessage');
            
            if (password !== confirmPassword) {
                messageDiv.innerHTML = '<span style="color: var(--danger);">✗ 兩次輸入的密碼不一致</span>';
                return;
            }

            messageDiv.innerHTML = '<span style="color: var(--accent-cyan);">註冊中...</span>';
            
            try {
                const formData = new FormData();
                formData.append('name', name);
                formData.append('email', email);
                formData.append('password', password);
                formData.append('confirm_password', confirmPassword);
                formData.append('csrf_token', CSRF_TOKEN);
                
                const response = await fetch('signup.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    messageDiv.innerHTML = '<span style="color: var(--accent-green);">✓ ' + result.message + '，請使用新帳號登入</span>';
                    // 預填登入表單的 email，並切換回登入畫面
                    const loginEmail = document.getElementById('loginEmail');
                    if (loginEmail) {
                        loginEmail.value = email;
                    }
                    setTimeout(() => {
                        showLoginCard();
                    }, 1000);
                } else {
                    messageDiv.innerHTML = '<span style="color: var(--danger);">✗ ' + result.message + '</span>';
                }
            } catch (error) {
                messageDiv.innerHTML = '<span style="color: var(--danger);">✗ 註冊失敗，請稍後再試</span>';
            }
        });
    </script>
</body>
</html>



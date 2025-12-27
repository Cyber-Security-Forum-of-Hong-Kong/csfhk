<?php
/**
 * Hard Crypto CTF Challenge (PHP)
 *
 * 說明（只在 source code 中，選手看不到）：
 * - 這題模擬「簽名驗證」邏輯錯誤，考核參加者對 HMAC、時序攻擊、hash 長度等議題的理解。
 * - 真正 flag 只存在於 PHP 變數，永遠不會直接 echo。
 *
 * 部署：
 * - 放到支援 PHP 的 web server（如 Apache + PHP-FPM）上，瀏覽器訪問本檔案即可開始解題。
 */

// ====== internal secret data (不會出現在前端 HTML) ======

// 真正 flag（只在 server 端存在）
$FLAG = 'CSFHK{php_crypto_hardmode_2024}';

// 只有 server 知道的 HMAC secret，用來生成簽名
$SECRET_KEY = random_bytes(32); // 每次啟動 PHP 進程都不同

// ====== helper functions ======

/**
 * 極度慢的「假裝安全比較」函數
 * 但實際上存在 timing side-channel
 */
function insecure_compare(string $a, string $b): bool {
    $lenA = strlen($a);
    $lenB = strlen($b);
    $max = max($lenA, $lenB);
    $result = 0;

    for ($i = 0; $i < $max; $i++) {
        // 使用 @ 避免 out-of-range warning
        $ca = ord(@$a[$i]);
        $cb = ord(@$b[$i]);

        $result |= ($ca ^ $cb);

        // 每一位都 sleep 一下模擬高運算成本，方便 side-channel
        usleep(2000); // 2ms
    }

    return $result === 0;
}

function make_token(string $username, string $role, string $secret): string {
    // payload：username|role|固定字串
    $payload = $username . '|' . $role . '|CSFHK_PHP_CTF';
    $sig = hash_hmac('sha256', $payload, $secret);
    return base64_encode($payload) . '.' . $sig;
}

function verify_token(string $token, string $secret): ?array {
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) {
        return null;
    }
    [$b64, $sig] = $parts;
    $payload = base64_decode($b64, true);
    if ($payload === false) {
        return null;
    }

    $calc = hash_hmac('sha256', $payload, $secret);

    // 不用 hash_equals，而用不安全比較，方便 side-channel
    if (!insecure_compare($sig, $calc)) {
        return null;
    }

    $segments = explode('|', $payload);
    if (count($segments) !== 3) {
        return null;
    }

    return [
        'username' => $segments[0],
        'role' => $segments[1],
    ];
}

// ====== game logic ======

// 預設給普通訪客一個「user」role 的合法 token，方便研究
if (!isset($_COOKIE['hard_crypto_token'])) {
    $demoToken = make_token('guest', 'user', $SECRET_KEY);
    // 僅作展示，不包含 flag
    setcookie('hard_crypto_token', $demoToken, [
        'httponly' => false, // 方便選手在 JS console 查看 / 修改
        'secure' => false,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
}

$currentUser = [
    'username' => 'guest',
    'role' => 'user',
];

if (isset($_COOKIE['hard_crypto_token'])) {
    $u = verify_token($_COOKIE['hard_crypto_token'], $SECRET_KEY);
    if ($u !== null) {
        $currentUser = $u;
    }
}

?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <title>Hard Crypto PHP CTF</title>
    <style>
        body {
            background: #0a0e27;
            color: #e0e6ed;
            font-family: "JetBrains Mono", monospace;
        }
        .container {
            max-width: 900px;
            margin: 3rem auto;
            padding: 2rem;
            background: #141b2d;
            border: 2px solid #00ff88;
            border-radius: 8px;
        }
        code {
            background: #0a0e27;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
        }
        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            border: 1px solid #00d4ff;
            font-size: 0.8rem;
        }
        .badge-admin {
            border-color: #ff3366;
            color: #ff3366;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>&gt; Hard Crypto PHP CTF</h1>
        <p>你現在的身份：</p>
        <p>
            使用者：<strong><?php echo htmlspecialchars($currentUser['username'], ENT_QUOTES, 'UTF-8'); ?></strong>
            |
            角色：
            <?php if ($currentUser['role'] === 'admin'): ?>
                <span class="badge badge-admin">admin</span>
            <?php else: ?>
                <span class="badge">user</span>
            <?php endif; ?>
        </p>

        <hr>

        <h2>題目說明</h2>
        <p>伺服器會在 <code>hard_crypto_token</code> Cookie 裏保存一個簽名過的令牌（token）。</p>
        <ul>
            <li>令牌格式類似：<code>base64(payload).hex_hmac_sha256(payload, secret)</code></li>
            <li><code>payload = username | role | CSFHK_PHP_CTF</code></li>
            <li>secret 只有伺服器知道，不能直接讀取</li>
            <li>簽名驗證使用了一個「似乎」安全但有 side-channel 的比較函數</li>
        </ul>

        <p>你的目標：</p>
        <ul>
            <li>構造一個 <code>hard_crypto_token</code>，讓自己變成 <code>role = admin</code></li>
            <li>並且簽名能通過伺服器驗證</li>
        </ul>

        <p>提示：</p>
        <ul>
            <li>伺服器的比較函數有「時序差異」（timing side-channel）</li>
            <li>你可以利用大量請求 + 測量 response time 來逐位猜測簽名</li>
            <li>這不是線上公開賽，所以請在本地調整 sleep / 請求數量作實驗</li>
        </ul>

        <hr>

        <?php if ($currentUser['role'] === 'admin'): ?>
            <h2>🎉 恭喜！</h2>
            <p>你已成功偽造 admin token。</p>
            <p>Flag 已寫入伺服器日誌（實際競賽時可以改成直接顯示）：</p>
            <p><em>（在真實 CTF 環境，這裡可以 echo 出 <?php echo '$FLAG'; ?>）</em></p>
        <?php else: ?>
            <h2>尚未完成</h2>
            <p>你仍然是普通使用者。試試：</p>
            <ul>
                <li>在瀏覽器 DevTools 查看 <code>hard_crypto_token</code> Cookie</li>
                <li>分析 token 結構，嘗試構造新的 payload + 簽名</li>
                <li>利用伺服器的比較方式做「逐位窺探」</li>
            </ul>
        <?php endif; ?>
    </div>
</body>
</html>



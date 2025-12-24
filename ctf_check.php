<?php
// 後端 CTF 檢查：所有真正 flag 只存在這裡
header('Content-Type: application/json; charset=utf-8');

// 只接受 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// 支援 JSON body
$raw = file_get_contents('php://input');
if ($raw) {
    $data = json_decode($raw, true);
} else {
    $data = $_POST;
}

$id = isset($data['id']) ? intval($data['id']) : 0;
$flag = isset($data['flag']) ? (string)$data['flag'] : '';

if ($id <= 0 || $flag === '') {
    echo json_encode(['ok' => false, 'error' => 'Invalid input']);
    exit;
}

function normalize_flag(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    // 去除 - 空白 大括號，與前端一致
    return preg_replace('/[-\s{}]/u', '', $s);
}

// 所有題目的正確 flag（只在 PHP 內部存在）
$flags = [
    1  => 'CSFHK{hidden_in_source}',
    2  => 'CSFHK-{hong_kong_forum}',
    3  => 'CSFHK{love_net_work}',
    4  => 'CSFHK{hello_cybersecurity}',
    5  => 'CSFHK{console_master}',
    6  => 'CSFHK{hex_decode}',
    7  => 'CSFHK-cookie_master',          // 其實題目主要靠 Cookie，但這裡仍保留正確值
    8  => 'CSFHK{binary}',
    9  => 'CSFHK-{exif_data}',
    10 => 'CSFHK-{script_obfuscation}',
    11 => 'CSFHK{xor_reverse_master}',
    12 => 'CSFHK{format_string}',
    13 => 'CSFHK{B1nary_b4se64_f0n0t_h1d1n_3N1]',
    14 => 'CSFHK{vigenere_cipher}',
    15 => 'CSFHK{secure_log}',
    16 => 'CSFHK{playfair}',
    17 => 'CSFHK{multibase}',
    18 => 'CSFHK{rsa}',
    19 => 'CSFHK{the_quick_brown}',
    20 => 'CSFHK{xor_cipher}',
    21 => 'CSFHK{Learn_to_decrypt}',
    22 => 'CSFHK{multi_layer_encoding}',
    23 => 'CSFHK{multi_xor_challenge}',
    24 => 'CSFHK{columnar_transposition}',
    25 => 'CSFHK{rsa_hard}',
    26 => 'CSFHK{combined_cipher}',
    27 => 'CSFHK{reverse_base64}',
    28 => 'CSFHK{advanced_vigenere}',
    29 => 'CSFHK{assembly}',
    30 => 'CSFHK{js_obfuscate}',
    31 => 'CSFHK{steganography}',
];

if (!isset($flags[$id])) {
    echo json_encode(['ok' => false, 'error' => 'Unknown challenge']);
    exit;
}

$userNorm = normalize_flag($flag);
$correctNorm = normalize_flag($flags[$id]);

$ok = hash_equals($correctNorm, $userNorm);

echo json_encode([
    'ok' => $ok,
    // 不回傳真正 flag，避免洩露
    'error' => $ok ? null : 'Flag 不正確',
]);



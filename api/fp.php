<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/multi.php';
boot_session();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'POST only.']);
    exit;
}

function fp_origin_ok(): bool {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $host = (string)preg_replace('/:\d+$/', '', $host);
    if ($host !== 'vexaris.net' && $host !== 'www.vexaris.net') return false;
    $ref = (string)($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
    if ($ref === '') return true;
    $rh = strtolower((string)parse_url($ref, PHP_URL_HOST));
    return $rh === '' || $rh === $host || $rh === 'www.vexaris.net' || $rh === 'vexaris.net';
}

if (!fp_origin_ok()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Bad origin.']);
    exit;
}

$raw = (string)file_get_contents('php://input');
if (strlen($raw) > 4096) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'msg' => 'Payload too large.']);
    exit;
}
$in = json_decode($raw, true);
if (!is_array($in)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Bad payload.']);
    exit;
}

$vid = strtolower(trim((string)($in['vid'] ?? '')));
if (!preg_match('/^[0-9a-f]{32}$/', $vid)) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid id.']);
    exit;
}

$bitsIn = is_array($in['bits'] ?? null) ? $in['bits'] : [];
$bits = [];
foreach (['canvas', 'webgl', 'fonts', 'hw', 'loc'] as $k) {
    $v = strtolower(trim((string)($bitsIn[$k] ?? '')));
    if (preg_match('/^[0-9a-f]{8,32}$/', $v)) $bits[$k] = fp_hash('bit:' . $k, $v);
}
ksort($bits);

$pdo = db();
if (!$pdo) {
    echo json_encode(['ok' => false, 'msg' => 'DB unavailable.']);
    exit;
}
if (!api_rate_limit($pdo, 'fp', client_ip(), 60, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'msg' => 'Too many requests.']);
    exit;
}

$vidHash = fp_hash('vid', $vid);
if ($vidHash === '') {
    echo json_encode(['ok' => false, 'msg' => 'Fingerprint disabled.']);
    exit;
}

$cc = strtoupper(substr(trim((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')), 0, 2));
if (!preg_match('/^[A-Z]{2}$/', $cc)) $cc = '';
$bitsJson = $bits ? json_encode($bits, JSON_UNESCAPED_SLASHES) : '';

$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
@setcookie('vex_vid', $vid, ['expires' => time() + 31536000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $secure]);
$_COOKIE['vex_vid'] = $vid;

$uid = (int)($_SESSION['user']['id'] ?? 0);
try {
    multi_ensure_tables($pdo);
    if ($uid > 0) {
        $did = multi_device_id();
        $st = $pdo->prepare("SELECT id FROM user_devices WHERE user_id = ? AND did = ? ORDER BY id DESC LIMIT 1");
        $st->execute([$uid, $did]);
        $rowId = (int)$st->fetchColumn();
        $byp = multi_is_exempt($pdo, $uid) ? 1 : 0;
        if ($rowId > 0) {
            $pdo->prepare("UPDATE user_devices SET vid = ?, bits = ?, fp_salt_ver = 1, fp_ver = 5, country = ?, bypassed = ?, last_seen = NOW() WHERE id = ?")
                ->execute([$vidHash, $bitsJson, $cc, $byp, $rowId]);
        } else {
            $pdo->prepare("INSERT INTO user_devices (user_id, did, fp, vid, bits, fp_salt_ver, fp_ver, country, bypassed, ip, user_agent, event) VALUES (?,?, '', ?,?,1,5,?,?,?,?,'login')")
                ->execute([$uid, $did, $vidHash, $bitsJson, $cc, $byp, client_ip(), substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
        }
        try { multi_group_sync($pdo, $uid); } catch (Throwable $eGs) {}
    }
} catch (Throwable $e) {
    error_log('api/fp: ' . $e->getMessage());
}

echo json_encode(['ok' => true]);

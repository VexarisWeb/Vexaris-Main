<?php
require_once __DIR__ . '/../core/db.php';

function dlupd_hmac_key(): string {
    return 'v' . '4x' . '7e' . strrev('REDACTED_KEY') . '!' . 'c' . '3' . 'b';
}

$pdo = db();
try { if (setting_get('vx_downloads_off', '0') === '1') { http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); echo 'Downloads are disabled.'; exit; } } catch (Throwable $e) {}
if (!$pdo) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'msg' => 'unavailable']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'POST gerekli.']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
try {
    $pdo->prepare("DELETE FROM api_rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)")->execute();
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM api_rate_limits WHERE ip=? AND scope='downlupd' AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
    $cnt->execute([$ip]);
    if ((int)$cnt->fetchColumn() >= 10) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'msg' => 'too many requests']);
        exit;
    }
    $pdo->prepare("INSERT INTO api_rate_limits (ip, scope) VALUES (?,'downlupd')")->execute([$ip]);
} catch (Exception $e) {}

$domain    = strtolower(trim((string)($_POST['domain'] ?? '')));
$timestamp = (int)($_POST['timestamp'] ?? 0);
$nonce     = strtolower(trim((string)($_POST['nonce'] ?? '')));
$signature = (string)($_POST['signature'] ?? '');
$version   = trim((string)($_POST['version'] ?? ''));

$domain = preg_replace('/[^a-z0-9.\-:]/', '', $domain);

if ($domain === '' || strlen($domain) > 253 || $timestamp <= 0 || !preg_match('/^[a-f0-9]{16,64}$/', $nonce) || $signature === '' || $version === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'invalid request']);
    exit;
}

if (abs(time() - $timestamp) > 300) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'stale timestamp']);
    exit;
}

try {
    $n = $pdo->prepare("SELECT id FROM license_nonces WHERE nonce=? LIMIT 1");
    $n->execute([$nonce]);
    if ($n->fetch()) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'nonce used']);
        exit;
    }
    $pdo->prepare("INSERT INTO license_nonces (nonce) VALUES (?)")->execute([$nonce]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'server error']);
    exit;
}

$exp = hash_hmac('sha256', $domain . '|' . $timestamp . '|' . $nonce, dlupd_hmac_key());
if (!hash_equals($exp, $signature)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'bad signature']);
    exit;
}

$licensed = false;
try {
    $rows = $pdo->query("SELECT allowed_domains, features FROM licenses WHERE status='active' AND (suspended_until IS NULL OR suspended_until <= NOW())")->fetchAll();
    foreach ($rows as $row) {
        $list = json_decode((string)$row['allowed_domains'], true);
        if (!is_array($list)) continue;
        foreach ($list as $pattern) {
            if (!domain_match($domain, (string)$pattern)) continue;
            $f = json_decode((string)$row['features'], true);
            $licensed = is_array($f) && ($f['core'] ?? false) === true && ($f['updates'] ?? false) === true;
            break 2;
        }
    }
} catch (Exception $e) {}

if (!$licensed) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'not licensed']);
    exit;
}

try {
    $st = $pdo->prepare("SELECT * FROM versions WHERE is_active=1 AND version=? LIMIT 1");
    $st->execute([$version]);
    $v = $st->fetch();
} catch (Exception $e) {
    $v = false;
}

if (!$v) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'msg' => 'version not found']);
    exit;
}

$abs = __DIR__ . '/../' . $v['file_path'];
// Guvenlik: dosya yolu uploads altina hapsedilir (stored path traversal savunmasi)
$uploadsBase = realpath(__DIR__ . '/../uploads');
$realAbs = ($uploadsBase !== false) ? realpath($abs) : false;
if ($realAbs === false || strpos($realAbs, $uploadsBase) !== 0 || !is_file($realAbs)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'msg' => 'file missing']);
    exit;
}
$abs = $realAbs;
if (!is_file($abs)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'msg' => 'file missing']);
    exit;
}

try {
    $pdo->prepare("INSERT INTO update_events (version_id, domain, event_type, delay_days) VALUES (?,?,?,0)")
        ->execute([(int)$v['id'], $domain, 'installed']);
} catch (Exception $e) {}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($v['file_name']) . '"');
header('Content-Length: ' . filesize($abs));
header('X-Content-Type-Options: nosniff');
while (ob_get_level()) ob_end_clean();
readfile($abs);
exit;
<?php
require_once __DIR__ . '/../core/db.php';

header('Content-Type: application/json; charset=utf-8');

function checkupd_hmac_key(): string {
    return 'v' . '4x' . '7e' . strrev('REDACTED_KEY') . '!' . 'c' . '3' . 'b';
}

$pdo = db();
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

try {
    if (!api_rate_limit($pdo, 'checkupd', client_ip(), 10, 60)) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'msg' => 'too many requests']);
        exit;
    }
} catch (Throwable $e) {}

$domain    = strtolower(trim((string)($_POST['domain'] ?? '')));
$timestamp = (int)($_POST['timestamp'] ?? 0);
$nonce     = strtolower(trim((string)($_POST['nonce'] ?? '')));
$signature = (string)($_POST['signature'] ?? '');

$domain = preg_replace('/[^a-z0-9.\-:]/', '', $domain);

if ($domain === '' || strlen($domain) > 253 || $timestamp <= 0 || !preg_match('/^[a-f0-9]{16,64}$/', $nonce) || $signature === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'invalid request']);
    exit;
}

if (abs(time() - $timestamp) > 300) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'stale timestamp']);
    exit;
}

$exp = hash_hmac('sha256', $domain . '|' . $timestamp . '|' . $nonce, checkupd_hmac_key());
if (!hash_equals($exp, $signature)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'bad signature']);
    exit;
}

try {
    if (random_int(1, 50) === 1) {
        $pdo->exec("DELETE FROM license_nonces WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    }
    $n = $pdo->prepare("INSERT IGNORE INTO license_nonces (nonce) VALUES (?)");
    $n->execute([$nonce]);
    if ($n->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'nonce used']);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'server error']);
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
} catch (Throwable $e) {}

if (!$licensed) {
    echo json_encode(['ok' => false, 'msg' => 'not licensed']);
    exit;
}

try {
    $st = $pdo->query("SELECT * FROM versions WHERE is_active=1 ORDER BY created_at DESC LIMIT 1");
    $v  = $st->fetch();
} catch (Throwable $e) {
    $v = false;
}

$ts = time();
if (!$v) {
    echo json_encode([
        'ok'        => false,
        'msg'       => 'no update',
        'timestamp' => $ts,
        'signature' => hash_hmac('sha256', '{}|' . $ts, checkupd_hmac_key()),
    ]);
    exit;
}

$payload = json_encode([
    'version'     => $v['version'],
    'description' => $v['description'],
    'file_size'   => $v['file_size'],
    'file_name'   => $v['file_name'],
    'published'   => $v['created_at'],
]);
echo json_encode([
    'ok'        => true,
    'version'   => json_decode($payload, true),
    'timestamp' => $ts,
    'signature' => hash_hmac('sha256', $payload . '|' . $ts, checkupd_hmac_key()),
]);
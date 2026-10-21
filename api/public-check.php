<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../core/turnstile.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = db();
if (!$pdo) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'msg' => 'unavailable']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
try {
    $pdo->prepare("DELETE FROM api_rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)")->execute();
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM api_rate_limits WHERE ip=? AND scope='pubcheck' AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
    $cnt->execute([$ip]);
    if ((int)$cnt->fetchColumn() >= 10) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'msg' => 'too many requests']);
        exit;
    }
    $pdo->prepare("INSERT INTO api_rate_limits (ip, scope) VALUES (?,'pubcheck')")->execute([$ip]);
} catch (Throwable $e) {}

$token = $_GET['cf_token'] ?? $_POST['cf-turnstile-response'] ?? $_POST['cf_token'] ?? '';
if (turnstile_enabled() && !turnstile_verify($token, $_SERVER['REMOTE_ADDR'] ?? null)) {
    echo json_encode(['ok'=>false,'msg'=>'Güvenlik doğrulaması başarısız. Lütfen Turnstile\'ı tamamlayın.']);
    exit;
}
$domain = strtolower(trim((string)($_GET['domain'] ?? $_POST['domain'] ?? '')));
$domain = preg_replace('/[^a-z0-9.\-]/', '', $domain);

if ($domain === '' || strlen($domain) > 253) {
    echo json_encode(['ok' => false, 'msg' => 'Geçerli bir domain girin.']);
    exit;
}

$found = false;
try {
    $rows = $pdo->query("SELECT allowed_domains FROM licenses WHERE status='active' AND (suspended_until IS NULL OR suspended_until <= NOW())")->fetchAll();
    foreach ($rows as $row) {
        $list = json_decode((string)$row['allowed_domains'], true);
        if (!is_array($list)) continue;
        foreach ($list as $pattern) {
            if (!_vx_d($domain, (string)$pattern)) continue;
            $found = true;
            break 2;
        }
    }
} catch (Throwable $e) {}

echo json_encode([
    'ok'      => $found,
    'domain'  => $domain,
    'message' => $found
        ? 'Bu site orijinal Vexaris altyapısını kullanıyor.'
        : 'Bu site orijinal Vexaris altyapısını kullanmıyor! Kredi kartı bilgileriniz veya kişisel bilgileriniz çalınabilir.',
]);

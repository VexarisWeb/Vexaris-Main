<?php
require_once __DIR__ . '/../core/db.php';

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/autoload.php';

$pdo = db();
if (!$pdo) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'msg' => 'unavailable']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'POST only.']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
try {
    $pdo->prepare("DELETE FROM api_rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)")->execute();
    $pdo->prepare("DELETE FROM license_nonces WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)")->execute();
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM api_rate_limits WHERE ip=? AND scope='verify' AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
    $cnt->execute([$ip]);
    if ((int)$cnt->fetchColumn() >= 20) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'msg' => 'too many requests']);
        exit;
    }
    $pdo->prepare("INSERT INTO api_rate_limits (ip, scope) VALUES (?,'verify')")->execute([$ip]);
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

$exp = hash_hmac('sha256', $domain . '|' . $timestamp . '|' . $nonce, _vx_k());
$macOk = hash_equals($exp, $signature);
/* Gecis donemi: eski master key ile imzalanmis mağaza istekleri de kabul
   edilir (tum magazalar yeni surume gecince bu dal kaldirilabilir). */
if (!$macOk && function_exists('_vx_k_legacy')) {
    $legacyExp = hash_hmac('sha256', $domain . '|' . $timestamp . '|' . $nonce, _vx_k_legacy());
    if (hash_equals($legacyExp, $signature)) {
        $macOk = true;
        error_log('Vexaris verify: legacy master key ile imzali istek (gecis donemi) domain=' . $domain);
    }
}
if (!$macOk) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'bad signature']);
    exit;
}

/* Nonce imzadan SONRA yazilir: imzasiz istekler tabloyu doldurup gecerli
   nonce'lari yakmasin. Atomik insert ile replay engellenir. */
try {
    $pdo->prepare("INSERT IGNORE INTO license_nonces (nonce) VALUES (?)")->execute([$nonce]);
    if ($pdo->query("SELECT ROW_COUNT()")->fetchColumn() == 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'nonce used']);
        exit;
    }
    if (mt_rand(1, 50) === 1) {
        $pdo->prepare("DELETE FROM license_nonces WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)")->execute();
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'server error']);
    exit;
}

$domain = preg_replace('/^www\./', '', $domain);
$domainWww = 'www.' . $domain;

$features = _vx_f();
$planSlug = 'free';

try {
    /* Lisans eslesmesi: en SPESIFIK eslesme kazanir (tam alan adi > wildcard).
       Eski davranis id-ASC ilk eslesmeyi secuyordu; wildcard'li bir lisans
       tum yeni magazalarin onune gecip plan gölgelemesi yapabiliyordu. */
    $rows = $pdo->query("SELECT id, user_id, allowed_domains, features FROM licenses WHERE status='active' AND (suspended_until IS NULL OR suspended_until <= NOW()) ORDER BY id ASC")->fetchAll();
    $best = null;
    $bestScore = -1;
    foreach ($rows as $row) {
        $list = json_decode((string)$row['allowed_domains'], true);
        if (!is_array($list)) continue;
        foreach ($list as $pattern) {
            $pattern = (string)$pattern;
            $exact = (strtolower($pattern) === $domain || strtolower($pattern) === $domainWww);
            $wild  = (strncmp($pattern, '*.', 2) === 0);
            if (!$exact && !$wild) continue;
            if (!$exact && !(_vx_d($domain, $pattern) || _vx_d($domainWww, $pattern))) continue;
            $score = $exact ? 100 : 10;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row;
            }
        }
    }
    if ($best) {
        $row = $best;
        $f = json_decode((string)$row['features'], true);
        if (is_array($f)) {
            foreach ($features as $k => $v) {
                $features[$k] = array_key_exists($k, $f) ? (bool)$f[$k] : $v;
            }
            if (isset($f['plan'])) {
                $ps = strtolower(trim((string)$f['plan']));
                if (in_array($ps, ['free', 'starter', 'professional', 'enterprise'], true)) $planSlug = $ps;
            }
            if (!empty($row['user_id']) && !empty($f['plan_auto']) && $planSlug !== 'free') {
                try {
                    $sq = $pdo->prepare("SELECT plan, status, current_period_end FROM user_subscriptions WHERE user_id=? LIMIT 1");
                    $sq->execute([(int)$row['user_id']]);
                    $sub = $sq->fetch();
                    $subOk = $sub && ($sub['status'] ?? '') === 'active' && (int)($sub['current_period_end'] ?? 0) > time();
                    if (!$subOk && $planSlug !== 'free') {
                        $planSlug = 'free';
                        $f['plan'] = 'free';
                        $pdo->prepare("UPDATE licenses SET features=? WHERE id=?")->execute([json_encode($f), (int)$row['id']]);
                    }
                } catch (Throwable $e2) {}
            }
        }
    }
} catch (Throwable $e) {}

$planTable = [
    'free' => ['slug' => 'free', 'product_limit' => 25, 'revenue_limit_usd' => 500.0, 'revenue_reset' => 'never', 'seats' => 0, 'modules' => 'none', 'ai' => null],
    'starter' => ['slug' => 'starter', 'product_limit' => 35, 'revenue_limit_usd' => 1000.0, 'revenue_reset' => 'monthly', 'seats' => 3, 'modules' => 'basic', 'ai' => ['per_window' => 12, 'window_h' => 8]],
    'professional' => ['slug' => 'professional', 'product_limit' => 75, 'revenue_limit_usd' => 5000.0, 'revenue_reset' => 'monthly', 'seats' => 10, 'modules' => 'advanced', 'ai' => ['per_window' => 30, 'window_h' => 6]],
    'enterprise' => ['slug' => 'enterprise', 'product_limit' => -1, 'revenue_limit_usd' => -1.0, 'revenue_reset' => 'monthly', 'seats' => 30, 'modules' => 'advanced', 'ai' => ['per_window' => 70, 'window_h' => 4]],
];
$plan = $planTable[$planSlug];

$ts  = time();
$out = json_encode($features);
$planRaw = json_encode($plan);
/* Yanit imzasi: gecis doneminde ESKI anahtarla imzalanir (mevcut magazalar
   eski anahtarla dogruluyor). Tum magazalar yeni surume gecince
   settings.vx_sign_with_new_key='1' ile yeni anahtara gecilir.
   signature2 her zaman yeni anahtarla uretilir (yeni surum magazalar kullanir). */
$sigKey = (function_exists('setting_get') && setting_get('vx_sign_with_new_key', '0') === '1')
    ? _vx_k()
    : (function_exists('_vx_k_legacy') ? _vx_k_legacy() : _vx_k());
echo json_encode([
    'features'  => $features,
    'plan'      => $plan,
    'plan_sig'  => hash_hmac('sha256', $planRaw . '|' . $ts, $sigKey),
    'timestamp' => $ts,
    'signature' => hash_hmac('sha256', $out . '|' . $ts, $sigKey),
    'signature2' => hash_hmac('sha256', $out . '|' . $ts, _vx_k()),
]);

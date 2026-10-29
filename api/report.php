<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../core/reports.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');
header('Access-Control-Expose-Headers: Retry-After');
header('Access-Control-Max-Age: 86400');
header('X-Content-Type-Options: nosniff');

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function rpt_json(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function rpt_host(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') return '';
    $host = parse_url($raw, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        $host = preg_replace('~^[a-z]+://~i', '', $raw);
        $host = explode('/', $host)[0];
        $host = explode(':', $host)[0];
    }
    $host = strtolower(trim($host));
    return (string)preg_replace('/[^a-z0-9.\-]/', '', $host);
}

function rpt_text(string $raw, int $max): string {
    $raw = (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $raw);
    $raw = trim(strip_tags($raw));
    return mb_substr($raw, 0, $max);
}

$pdo = db();
if (!$pdo) rpt_json(503, ['ok' => false, 'msg' => 'unavailable']);

$ip = function_exists('client_ip') ? client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
$act = strtolower(trim((string)($_GET['action'] ?? '')));

if ($act === 'config') {
    header('Cache-Control: no-store, max-age=0');
    if (!api_rate_limit($pdo, 'report_cfg', $ip, 120, 60)) {
        header('Retry-After: 60');
        rpt_json(429, ['ok' => false, 'msg' => 'rate_limited', 'retry_after' => 60]);
    }
    $enabled = report_setting($pdo, 'report_enabled', '1') === '1';
    $hours = max(1, min(168, (int)report_setting($pdo, 'report_rate_hours', '12')));
    $reasons = [];
    foreach (report_reasons() as $id => $labels) {
        $reasons[] = ['id' => $id, 'tr' => (string)$labels['tr'], 'en' => (string)$labels['en']];
    }
    rpt_json(200, [
        'ok' => true,
        'enabled' => $enabled,
        'pow' => $enabled ? report_pow_issue($pdo) : null,
        'endpoint' => 'https://vexaris.net/api/report.php',
        'window' => $hours * 3600,
        'hours' => $hours,
        'categories' => $reasons,
    ]);
}

if ($method !== 'POST') rpt_json(405, ['ok' => false, 'msg' => 'method']);

if (report_setting($pdo, 'report_enabled', '1') !== '1') {
    rpt_json(403, ['ok' => false, 'msg' => 'reports_disabled']);
}

if (!api_rate_limit($pdo, 'report_post', $ip, 20, 3600)) {
    header('Retry-After: 600');
    rpt_json(429, ['ok' => false, 'msg' => 'rate_limited', 'retry_after' => 600]);
}

$originRaw = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
$originHost = rpt_host($originRaw);

if ($originHost === 'demo.vexaris.net' || $originHost === 'demo.vexaris.com.tr') {
    rpt_json(403, ['ok' => false, 'msg' => 'demo_readonly']);
}

if ($originHost === '' || strpos($originHost, '.') === false) {
    rpt_json(403, ['ok' => false, 'msg' => 'origin']);
}

$body = (string)file_get_contents('php://input');
if (strlen($body) > 8192) rpt_json(413, ['ok' => false, 'msg' => 'too_large']);
$in = json_decode($body, true);
if (!is_array($in)) $in = $_POST;

if (!empty($in['website']) || !empty($in['vx_company_url']) || !empty($in['company'])) {
    rpt_json(200, ['ok' => true, 'id' => 0]);
}

$uaRaw = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
if ($uaRaw === '' || preg_match('~(curl|wget|python-requests|python-urllib|aiohttp|go-http-client|okhttp|java/|httpclient|libwww|scrapy|node-fetch|axios/|headlesschrome|phantomjs|puppeteer|playwright|selenium|masscan|nikto|sqlmap|nuclei)~i', $uaRaw)) {
    rpt_json(403, ['ok' => false, 'msg' => 'agent']);
}

/* ── Yalnizca lisansli Vexaris magazalari: imza + lisans ───────────────
   Imza semasi verify.php ile aynidir: hmac_sha256("domain|ts|nonce", master).
   Master key magazada, dogrulama anahtari yalnizca burada (env dosyasi). */
$ts    = (int)($in['ts'] ?? 0);
$nonce = strtolower(trim((string)($in['nonce'] ?? '')));
$sig   = trim((string)($in['sig'] ?? ''));

if ($ts <= 0 || !preg_match('/^[a-f0-9]{16,64}$/', $nonce) || $sig === '') {
    rpt_json(403, ['ok' => false, 'msg' => 'unauthorized']);
}
if (abs(time() - $ts) > 7200) {
    rpt_json(403, ['ok' => false, 'msg' => 'stale']);
}
if (!function_exists('_vx_verify_hmac')) {
    $auto = __DIR__ . '/../config/autoload.php';
    if (is_file($auto)) require_once $auto;
}

$domain = strtolower(trim((string)($in['domain'] ?? '')));
if ($domain === '') $domain = $originHost;
$domain = (string)preg_replace('/[^a-z0-9.\-]/', '', $domain);
if ($domain === '' || strpos($domain, '.') === false) {
    rpt_json(422, ['ok' => false, 'msg' => 'domain']);
}

$domainBare = (string)preg_replace('/^www\./', '', $domain);
$domainWww  = 'www.' . $domainBare;
$originBare = (string)preg_replace('/^www\./', '', $originHost);
if ($originBare !== $domainBare) {
    rpt_json(403, ['ok' => false, 'msg' => 'origin']);
}

if (!function_exists('_vx_verify_hmac') || !_vx_verify_hmac($domain . '|' . $ts . '|' . $nonce, $sig)) {
    rpt_json(403, ['ok' => false, 'msg' => 'bad_signature']);
}

try {
    $pdo->prepare("INSERT IGNORE INTO license_nonces (nonce) VALUES (?)")->execute([$nonce]);
    if ((int)$pdo->query("SELECT ROW_COUNT()")->fetchColumn() === 0) {
        rpt_json(403, ['ok' => false, 'msg' => 'nonce_used']);
    }
} catch (\Throwable $e) {
    rpt_json(500, ['ok' => false, 'msg' => 'server']);
}

$licensed = false;
try {
    $st = $pdo->prepare("SELECT allowed_domains FROM licenses WHERE status='active' AND (suspended_until IS NULL OR suspended_until <= NOW()) AND allowed_domains IS NOT NULL");
    $st->execute();
    foreach ((array)$st->fetchAll(\PDO::FETCH_COLUMN) as $raw) {
        $list = json_decode((string)$raw, true);
        if (!is_array($list)) continue;
        foreach ($list as $pattern) {
            if (_vx_d($domainBare, (string)$pattern) || _vx_d($domainWww, (string)$pattern)) {
                $licensed = true;
                break 2;
            }
        }
    }
} catch (\Throwable $e) {}
if (!$licensed) {
    rpt_json(403, ['ok' => false, 'msg' => 'not_licensed']);
}

if (!report_pow_verify($in)) {
    rpt_json(403, ['ok' => false, 'msg' => 'pow']);
}

try {
    $pTok = hash('sha256', 'rptpow|' . strtolower((string)($in['pow_salt'] ?? '')) . '|' . (int)($in['pow_ts'] ?? 0) . '|' . (string)($in['pow_nonce'] ?? ''));
    $pdo->prepare("INSERT IGNORE INTO report_pow_used (token) VALUES (?)")->execute([$pTok]);
    if ((int)$pdo->query("SELECT ROW_COUNT()")->fetchColumn() === 0) {
        rpt_json(403, ['ok' => false, 'msg' => 'pow']);
    }
    if (random_int(1, 40) === 1) {
        $pdo->exec("DELETE FROM report_pow_used WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 DAY)");
    }
} catch (\Throwable $e) {
}

$elapsed = (int)($in['elapsed'] ?? 0);
$acts    = (int)($in['acts'] ?? 0);
$minSec  = max(1, min(30, (int)report_setting($pdo, 'report_min_seconds', '2')));
if ($elapsed > 86400000) {
    rpt_json(403, ['ok' => false, 'msg' => 'timing']);
}
if ($elapsed < ($minSec * 1000) && $acts < 2) {
    rpt_json(403, ['ok' => false, 'msg' => 'timing']);
}

$domDaily = max(1, min(100, (int)report_setting($pdo, 'report_domain_daily', '8')));
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM store_reports WHERE domain=? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $st->execute([$domain]);
    if ((int)$st->fetchColumn() >= $domDaily) {
        header('Retry-After: 3600');
        rpt_json(429, ['ok' => false, 'msg' => 'flood', 'retry_after' => 3600]);
    }
} catch (\Throwable $e) {
}

$reason = strtolower(trim((string)($in['reason'] ?? '')));
if (!report_reason_valid($reason)) rpt_json(422, ['ok' => false, 'msg' => 'reason']);

$message = rpt_text((string)($in['message'] ?? ''), 1500);
if ($reason === 'other' && mb_strlen($message) < 5) {
    rpt_json(422, ['ok' => false, 'msg' => 'message_required']);
}

$lang = strtolower(trim((string)($in['lang'] ?? 'en'))) === 'tr' ? 'tr' : 'en';
$storeName = rpt_text((string)($in['site_name'] ?? ''), 120);
$storeCat = strtolower(rpt_text((string)($in['store_category'] ?? ''), 30));
$storeCat = (string)preg_replace('/[^a-z]/', '', $storeCat);

$hours = max(1, min(168, (int)report_setting($pdo, 'report_rate_hours', '12')));
try {
    $st = $pdo->prepare("SELECT created_at FROM store_reports WHERE ip=? AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR) ORDER BY id DESC LIMIT 1");
    $st->execute([$ip, $hours]);
    $last = $st->fetchColumn();
} catch (\Throwable $e) {
    $last = false;
}
if ($last !== false && $last !== null) {
    $nextAt = strtotime((string)$last) + ($hours * 3600);
    $retry = max(1, $nextAt - time());
    header('Retry-After: ' . $retry);
    rpt_json(429, ['ok' => false, 'msg' => 'rate_limited', 'retry_after' => $retry, 'hours' => $hours]);
}

$ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
try {
    $ins = $pdo->prepare("INSERT INTO store_reports (store_name, store_category, domain, origin, reason, message, lang, ip, user_agent) VALUES (?,?,?,?,?,?,?,?,?)");
    $ins->execute([$storeName, $storeCat, $domain, mb_substr($originHost, 0, 190), $reason, $message, $lang, $ip, $ua]);
    $id = (int)$pdo->lastInsertId();
} catch (\Throwable $e) {
    rpt_json(500, ['ok' => false, 'msg' => 'db']);
}

echo json_encode(['ok' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
if (function_exists('fastcgi_finish_request')) {
    @fastcgi_finish_request();
}
try {
    if ($id > 0) report_spam_scan($pdo, $id);
} catch (\Throwable $e) {
    error_log('[VX] report scan: ' . $e->getMessage());
}
exit;

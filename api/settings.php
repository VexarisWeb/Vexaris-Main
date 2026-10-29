<?php
require_once __DIR__ . '/../core/db.php';
boot_session();
require_admin();
if (!has_permission('settings')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Bu işlem için yetkin yok.']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
$pdo = db();
if (!$pdo) { echo json_encode(['ok'=>false,'msg'=>'DB hatası']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'msg'=>'POST gerekli']); exit; }
csrf_check();
$act = $_POST['action'] ?? '';
if ($act === 'save_whop') {
    setting_set('whop_payments_enabled', ($_POST['payments_enabled'] ?? '0') === '1' ? '1':'0');
    $cur = strtolower(trim((string)($_POST['currency'] ?? 'usd')));
    setting_set('whop_currency', preg_match('/^[a-z]{3}$/', $cur) ? $cur : 'usd');
    // Gizli anahtarlar: boş gönderilirse mevcut değer korunur.
    foreach (['api_key' => 'whop_api_key', 'webhook_secret' => 'whop_webhook_secret'] as $in => $key) {
        $v = trim((string)($_POST[$in] ?? ''));
        if ($v !== '') setting_set($key, $v);
    }
    setting_set('whop_company_id', trim((string)($_POST['company_id'] ?? '')));
    setting_set('whop_sandbox', ($_POST['sandbox'] ?? '0') === '1' ? '1':'0');
    echo json_encode(['ok'=>true,'msg'=>'Whop kaydedildi']);
    exit;
}
if ($act === 'test_whop') {
    require_once __DIR__ . '/../core/whop.php';
    $apiKey = setting_get('whop_api_key', '');
    if ($apiKey === '') { echo json_encode(['ok'=>false,'msg'=>'Önce API anahtarını kaydedin.']); exit; }
    $opts = ['http' => [
        'method' => 'GET',
        'header' => "Authorization: Bearer " . $apiKey . "\r\nAccept: application/json\r\nApi-Version-Date: 2026-07-01\r\nUser-Agent: Vexaris-Whop/1.0\r\n",
        'timeout' => 10,
        'ignore_errors' => true,
    ]];
    $raw = @file_get_contents(whop_api_base() . '/api/v1/accounts/me', false, stream_context_create($opts));
    if ($raw === false) { echo json_encode(['ok'=>false,'msg'=>'Whop API erişilemedi. Anahtarı ve sandbox ayarını kontrol edin.']); exit; }
    $j = json_decode($raw, true);
    if (!is_array($j) || isset($j['error'])) { echo json_encode(['ok'=>false,'msg'=>'API hatası: ' . substr((string)($j['error'] ?? $j['message'] ?? 'geçersiz yanıt'), 0, 120)]); exit; }
    $who = $j['name'] ?? $j['id'] ?? 'OK';
    echo json_encode(['ok'=>true,'msg'=>'Bağlantı OK (' . $who . ')']);
    exit;
}
if ($act === 'save_multi') {
    setting_set('multi_enabled', ($_POST['enabled'] ?? '0') === '1' ? '1':'0');
    if (isset($_POST['autoban'])) setting_set('multi_autoban', ($_POST['autoban'] ?? '0') === '1' ? '1':'0');
    $mode = strtolower(trim((string)($_POST['mode'] ?? 'notify')));
    if (!in_array($mode, ['off', 'shadow', 'notify', 'graduated'], true)) $mode = 'notify';
    setting_set('multi_mode', $mode);
    $thr = (int)($_POST['threshold'] ?? 3);
    if ($thr < 2) $thr = 2;
    if ($thr > 20) $thr = 20;
    setting_set('multi_ban_threshold', (string)$thr);
    $ipm = (int)($_POST['ip_max'] ?? 3);
    if ($ipm < 1) $ipm = 1;
    if ($ipm > 50) $ipm = 50;
    setting_set('multi_ip_max', (string)$ipm);
    $byp = [];
    foreach (preg_split('/[\s,;]+/', strtolower((string)($_POST['bypass_emails'] ?? ''))) as $bE) {
        $bE = trim($bE);
        if ($bE !== '' && strlen($bE) < 160 && filter_var($bE, FILTER_VALIDATE_EMAIL)) $byp[] = $bE;
    }
    $byp = array_values(array_unique(array_slice($byp, 0, 200)));
    setting_set('multi_bypass_emails', implode("\n", $byp));
    $extra = strtolower((string)($_POST['extra_domains'] ?? ''));
    $lines = [];
    foreach (preg_split('/[\s,;]+/', $extra) as $d) {
        $d = trim($d, " \t\n\r\0\x0B.");
        if ($d !== '' && strpos($d, '.') !== false && strlen($d) < 80) $lines[] = $d;
    }
    setting_set('multi_extra_domains', implode("\n", array_values(array_unique($lines))));
    if (function_exists('audit_log')) audit_log($pdo, (int)(current_user()['id'] ?? 0), 'multi_settings', 'settings', 'multi', 'mode=' . $mode . ' thr=' . $thr . ' ip=' . $ipm . ' bypass=' . count($byp));
    echo json_encode(['ok'=>true,'msg'=>'Multi-hesap koruması kaydedildi (' . count($byp) . ' bypass)']);
    exit;
}
if ($act === 'save_turnstile') {
    setting_set('turnstile_enabled', ($_POST['enabled'] ?? '0') === '1' ? '1':'0');
    setting_set('turnstile_site_key', trim((string)($_POST['site_key'] ?? '')));
    $tsSecretVal = trim((string)($_POST['secret'] ?? ''));
    if ($tsSecretVal !== '') setting_set('turnstile_secret', $tsSecretVal);
    echo json_encode(['ok'=>true,'msg'=>'Turnstile kaydedildi']);
    exit;
}
if ($act === 'save_oauth') {
    $p = $_POST['provider'] ?? '';
    $labels = ['google' => 'Google', 'discord' => 'Discord', 'github' => 'GitHub', 'microsoft' => 'Microsoft'];
    if (!isset($labels[$p])) { echo json_encode(['ok'=>false,'msg'=>'Geçersiz sağlayıcı']); exit; }
    $en = ($_POST['enabled'] ?? '0') === '1' ? '1':'0';
    $cid = trim((string)($_POST['client_id'] ?? ''));
    $cid = preg_replace('#^https?://#i', '', $cid);
    $cid = rtrim((string)$cid, '/');
    $sec = trim((string)($_POST['client_secret'] ?? ''));
    setting_set($p.'_oauth_enabled', $en);
    if ($cid !== '') setting_set($p.'_client_id', $cid);
    if ($sec !== '') setting_set($p.'_client_secret', $sec);
    echo json_encode(['ok'=>true,'msg'=> $labels[$p].' OAuth kaydedildi']);
    exit;
}
if ($act === 'save_indexing') {
    $en = ($_POST['enabled'] ?? '0') === '1' ? '1':'0';
    $raw = trim((string)($_POST['service_json'] ?? ''));
    setting_set('google_indexing_enabled', $en);
    if ($raw !== '') {
        $j = json_decode($raw, true);
        if (!is_array($j) || empty($j['client_email']) || empty($j['private_key'])) {
            echo json_encode(['ok'=>false,'msg'=>'Geçersiz servis JSON. client_email ve private_key gerekli.']);
            exit;
        }
        setting_set('google_service_json', $raw);
    }
    echo json_encode(['ok'=>true,'msg'=>'Indexing ayarları kaydedildi']);
    exit;
}
if ($act === 'test_indexing') {
    require_once __DIR__ . '/../core/indexing.php';
    [$en, $sa] = indexing_settings();
    if (!$en || $sa === null) { echo json_encode(['ok'=>false,'msg'=>'Önce anahtarı yapıştırıp Aktif edin.']); exit; }
    $tok = indexing_access_token($sa);
    if (!$tok) { echo json_encode(['ok'=>false,'msg'=>'Jeton alınamadı. Anahtar veya Search Console yetkisini kontrol edin.']); exit; }
    echo json_encode(['ok'=>true,'msg'=>'Bağlantı OK (' . $sa['client_email'] . ')']);
    exit;
}
echo json_encode(['ok'=>false,'msg'=>'Bilinmeyen işlem']);

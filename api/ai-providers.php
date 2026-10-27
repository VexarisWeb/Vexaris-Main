<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/ai.php';

header('Content-Type: application/json; charset=utf-8');
boot_session();
if (!is_logged_in()) { http_response_code(401); echo json_encode(['ok' => false, 'msg' => 'giris']); exit; }
if (!has_permission('settings')) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'yetki']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false, 'msg' => 'POST']); exit; }
csrf_check();
ai_tables();

$pdo = db();
if (!$pdo) { http_response_code(503); echo json_encode(['ok' => false, 'msg' => 'db']); exit; }

$op = trim((string)($_POST['op'] ?? ''));

if ($op === 'list') {
    $rows = $pdo->query("SELECT id, provider, model_id, is_active, priority, credit_rate, created_at, LENGTH(api_key) AS klen FROM ai_providers ORDER BY priority DESC, id ASC")->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[] = ['id' => (int)$r['id'], 'provider' => $r['provider'], 'model_id' => $r['model_id'], 'is_active' => (int)$r['is_active'], 'priority' => (int)$r['priority'], 'credit_rate' => (float)($r['credit_rate'] ?? 0), 'has_key' => ((int)$r['klen'] > 0), 'created_at' => $r['created_at']];
    }
    echo json_encode(['ok' => true, 'rows' => $out]);
    exit;
}

if ($op === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $provider = trim((string)($_POST['provider'] ?? ''));
    $model = trim((string)($_POST['model_id'] ?? ''));
    $key = trim((string)($_POST['api_key'] ?? ''));
    $active = (($_POST['is_active'] ?? '1') === '1') ? 1 : 0;
    $prio = max(0, min(100, (int)($_POST['priority'] ?? 0)));
    $rate = max(0, min(1000, (float)($_POST['credit_rate'] ?? 0)));
    if (!in_array($provider, ['google', 'openrouter', 'dahl'], true)) { echo json_encode(['ok' => false, 'msg' => 'provider']); exit; }
    if ($model === '' || mb_strlen($model) > 120) { echo json_encode(['ok' => false, 'msg' => 'model']); exit; }
    if ($id > 0) {
        if ($key !== '') {
            if (strlen($key) < 8 || strlen($key) > 500) { echo json_encode(['ok' => false, 'msg' => 'key']); exit; }
            $pdo->prepare("UPDATE ai_providers SET provider=?, model_id=?, api_key=?, is_active=?, priority=?, credit_rate=? WHERE id=?")->execute([$provider, $model, ai_encrypt($key), $active, $prio, $rate, $id]);
        } else {
            $pdo->prepare("UPDATE ai_providers SET provider=?, model_id=?, is_active=?, priority=?, credit_rate=? WHERE id=?")->execute([$provider, $model, $active, $prio, $rate, $id]);
        }
    } else {
        if (strlen($key) < 8 || strlen($key) > 500) { echo json_encode(['ok' => false, 'msg' => 'key']); exit; }
        $pdo->prepare("INSERT INTO ai_providers (provider, model_id, api_key, is_active, priority, credit_rate) VALUES (?,?,?,?,?,?)")->execute([$provider, $model, ai_encrypt($key), $active, $prio, $rate]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($op === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => 'id']); exit; }
    $pdo->prepare("DELETE FROM ai_providers WHERE id=?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($op === 'test') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => 'id']); exit; }
    $st = $pdo->prepare("SELECT * FROM ai_providers WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) { echo json_encode(['ok' => false, 'msg' => 'yok']); exit; }
    $row['api_key_plain'] = ai_decrypt((string)($row['api_key'] ?? ''));
    if ($row['api_key_plain'] === '') { echo json_encode(['ok' => false, 'msg' => 'key']); exit; }
    $r = ai_call_provider($row, [['role' => 'user', 'content' => 'Sadece sunucu testi. Tek kelimeyle yanıtla: OK']], 16);
    if (empty($r['ok'])) { echo json_encode(['ok' => false, 'msg' => $r['msg'] ?? 'hata']); exit; }
    echo json_encode(['ok' => true, 'reply' => mb_substr((string)($r['text'] ?? ''), 0, 200), 'in' => (int)($r['in'] ?? 0), 'out' => (int)($r['out'] ?? 0)]);
    exit;
}

if ($op === 'quotas') {
    $month = date('Y-m');
    $rows = $pdo->query("SELECT domain, credits AS used FROM ai_credits WHERE month='" . $month . "' ORDER BY used DESC LIMIT 100")->fetchAll();
    echo json_encode(['ok' => true, 'month' => $month, 'limit' => AI_CREDITS_MONTHLY, 'rows' => $rows]);
    exit;
}

if ($op === 'logs') {
    $limit = max(1, min(300, (int)($_POST['limit'] ?? 100)));
    $domain = strtolower(trim((string)($_POST['domain'] ?? '')));
    $onlyErr = (string)($_POST['errors'] ?? '') === '1';
    $where = [];
    $args = [];
    if ($domain !== '') { $where[] = "domain LIKE ?"; $args[] = '%' . $domain . '%'; }
    if ($onlyErr) { $where[] = "ok=0"; }
    $sqlWhere = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
    $st = $pdo->prepare("SELECT id, domain, ip, plan, lang, mode, provider, model_id, in_t, out_t, credits, ms, ok, status, err, LEFT(COALESCE(prompt,''),400) AS prompt_short, created_at FROM ai_request_log" . $sqlWhere . " ORDER BY id DESC LIMIT " . (int)$limit);
    $st->execute($args);
    $rows = $st->fetchAll();
    $domains = $pdo->query("SELECT domain, COUNT(*) AS c, MAX(created_at) AS last_at FROM ai_request_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY domain ORDER BY c DESC LIMIT 50")->fetchAll();
    echo json_encode(['ok' => true, 'rows' => $rows, 'domains' => $domains, 'limit' => (int)$limit]);
    exit;
}

if ($op === 'log_detail') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => 'id']); exit; }
    $st = $pdo->prepare("SELECT * FROM ai_request_log WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) { echo json_encode(['ok' => false, 'msg' => 'yok']); exit; }
    echo json_encode(['ok' => true, 'row' => $row]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'msg' => 'op']);

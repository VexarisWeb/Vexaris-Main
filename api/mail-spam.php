<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/ai.php';
require_once __DIR__ . '/../core/mail.php';

header('Content-Type: application/json; charset=utf-8');

boot_session();
require_admin();
if (!has_permission('tickets')) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'yetki']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false]); exit; }

$raw = file_get_contents('php://input');
if (strlen($raw) > 20000) { http_response_code(413); echo json_encode(['ok' => false]); exit; }
$d = json_decode($raw, true);
if (!is_array($d)) { http_response_code(400); echo json_encode(['ok' => false]); exit; }

$token = (string)($d['_csrf'] ?? '');
$sess = (string)($_SESSION['csrf'] ?? '');
if ($sess === '' || $token === '' || !hash_equals($sess, $token)) {
    http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'csrf']); exit;
}

$pdo = db();
if (!$pdo) { http_response_code(503); echo json_encode(['ok' => false]); exit; }

$sysPrompt = mail_spam_setting($pdo, 'mail_spam_prompt', mail_spam_default_prompt());
if (trim($sysPrompt) === '') { $sysPrompt = mail_spam_default_prompt(); }

$prov = ai_active_provider($pdo);
if (!$prov) { echo json_encode(['ok' => false, 'msg' => 'AI saglayici yok (API & AI sayfasindan ekle)']); exit; }
$model = (string)($prov['model_id'] ?? '');

$mode = (string)($d['mode'] ?? 'single');

if ($mode === 'single' || isset($d['id'])) {
    $id = (int)($d['id'] ?? 0);
    if ($id <= 0) { http_response_code(400); echo json_encode(['ok' => false]); exit; }
    echo json_encode(mail_spam_scan_email($pdo, $id));
    exit;
}

if ($mode === 'scan') {
    $limit = (int)($d['limit'] ?? 3);
    if ($limit < 1) { $limit = 1; }
    if ($limit > 15) { $limit = 15; }
    $rows = $pdo->query("SELECT id, subject, from_addr, body_text, body_html FROM inbound_emails WHERE spam_checked=0 AND deleted=0 ORDER BY id ASC LIMIT $limit")->fetchAll();
    $scanned = 0; $spam = 0; $by = '';
    foreach ($rows as $row) {
        $res = mail_spam_scan_one($pdo, $prov, $sysPrompt, $model, $row);
        if (empty($res['ok'])) { continue; }
        $score = isset($res['prob']) ? (int)round((float)$res['prob'] * 100) : ($res['spam'] ? 100 : 0);
        $pdo->prepare("UPDATE inbound_emails SET spam=?, spam_checked=1, spam_score=?, spam_reason=? WHERE id=?")
            ->execute([$res['spam'], $score, $res['reason'], (int)$row['id']]);
        $scanned++; if ($res['spam']) { $spam++; }
        if ($by === '') $by = (string)($res['by'] ?? '');
    }
    $pending = (int)$pdo->query("SELECT COUNT(*) FROM inbound_emails WHERE spam_checked=0 AND deleted=0")->fetchColumn();
    $spamTotal = (int)$pdo->query("SELECT COUNT(*) FROM inbound_emails WHERE spam=1 AND deleted=0")->fetchColumn();
    echo json_encode(['ok' => true, 'scanned' => $scanned, 'spam' => $spam, 'pending' => $pending, 'spam_total' => $spamTotal, 'model' => $model, 'engine' => $by]);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'gecersiz mod']);

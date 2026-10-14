<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/ai.php';

header('Content-Type: application/json; charset=utf-8');

boot_session();
require_admin();
if (!has_permission('tickets')) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'yetki']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false]); exit; }

$raw = file_get_contents('php://input');
if (strlen($raw) > 60000) { http_response_code(413); echo json_encode(['ok' => false]); exit; }
$d = json_decode($raw, true);
if (!is_array($d)) { http_response_code(400); echo json_encode(['ok' => false]); exit; }

$token = (string)($d['_csrf'] ?? '');
$sess = (string)($_SESSION['csrf'] ?? '');
if ($sess === '' || $token === '' || !hash_equals($sess, $token)) {
    http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'csrf']); exit;
}

$now = time();
$last = (int)($_SESSION['mailai_last'] ?? 0);
if ($now - $last < 3) { http_response_code(429); echo json_encode(['ok' => false, 'msg' => 'hizli']); exit; }
$_SESSION['mailai_last'] = $now;

$prompt = mb_substr(trim((string)($d['prompt'] ?? '')), 0, 4000);
$context = mb_substr(trim((string)($d['context'] ?? '')), 0, 6000);
$history = [];
if (is_array($d['history'] ?? null)) {
    foreach (array_slice($d['history'], -6) as $h) {
        if (!is_array($h)) continue;
        $r = ($h['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
        $c = mb_substr(trim((string)($h['content'] ?? '')), 0, 4000);
        if ($c !== '') $history[] = ['role' => $r, 'content' => $c];
    }
}
if ($prompt === '' && !$history) { http_response_code(400); echo json_encode(['ok' => false]); exit; }

$prov = ai_active_provider($pdo ?? db());
if (!$prov) { echo json_encode(['ok' => false, 'msg' => 'AI saglayici yok (API & AI sayfasindan ekle)']); exit; }

$sys = 'Sen Vexaris e-posta asistanısın. Türkçe yaz. Görevin: e-posta metni yazmak, düzeltmek, çevirmek, kısaltmak, üslup değiştirmek. '
    . 'Yanıtını SADECE markdown olarak ver, ```markdown ... ``` kod bloğu içine al, bloğun dışında hiçbir açıklama yazma. '
    . 'E-posta gövdesi için konu satırı yazma, selamlama ve imza yerine [Ad] koy.';

$msgs = [['role' => 'user', 'content' => $sys . "\n\nMEVCUT TASLAK:\n" . ($context !== '' ? $context : '(boş)')]];
foreach ($history as $h) $msgs[] = $h;
if ($prompt !== '') $msgs[] = ['role' => 'user', 'content' => $prompt];

$r = ai_call_provider($prov, $msgs, 1500);
if (empty($r['ok'])) {
    try { ai_usage_log($pdo ?? db(), 'admin-mail', (string)($prov['provider'] ?? ''), (string)($prov['model_id'] ?? ''), 0, 0, false, mb_substr((string)($r['msg'] ?? 'hata'), 0, 200)); } catch (Exception $e) {}
    echo json_encode(['ok' => false, 'msg' => 'AI hatasi: ' . mb_substr((string)($r['msg'] ?? ''), 0, 120)]);
    exit;
}
$text = trim((string)($r['text'] ?? ''));
if (preg_match('/```(?:markdown)?\s*([\s\S]*?)```/', $text, $m)) $text = trim($m[1]);
try { ai_usage_log($pdo ?? db(), 'admin-mail', (string)($prov['provider'] ?? ''), (string)($prov['model_id'] ?? ''), (int)($r['in'] ?? 0), (int)($r['out'] ?? 0), true); } catch (Exception $e) {}
echo json_encode(['ok' => true, 'text' => $text, 'model' => (string)($prov['model_id'] ?? '')]);

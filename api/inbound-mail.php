<?php
require_once __DIR__ . '/../core/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = db();
if (!$pdo) { http_response_code(503); echo json_encode(['ok' => false]); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false]); exit; }

$raw = file_get_contents('php://input');
if (strlen($raw) > 300000) { http_response_code(413); echo json_encode(['ok' => false]); exit; }
$d = json_decode($raw, true);
if (!is_array($d)) { http_response_code(400); echo json_encode(['ok' => false]); exit; }

$secret = '';
try {
    $st = $pdo->prepare("SELECT `value` FROM settings WHERE `key`='mail_inbound_secret' LIMIT 1");
    $st->execute();
    $secret = (string)$st->fetchColumn();
} catch (Exception $e) {}
if ($secret === '') { http_response_code(503); echo json_encode(['ok' => false, 'msg' => 'not-configured']); exit; }

$to      = mb_substr(trim((string)($d['to'] ?? '')), 0, 190);
$from    = mb_substr(trim((string)($d['from'] ?? '')), 0, 190);
$fromNm  = mb_substr(trim((string)($d['from_name'] ?? '')), 0, 190);
$subject = mb_substr(trim((string)($d['subject'] ?? '')), 0, 255);
$text    = mb_substr((string)($d['text'] ?? ''), 0, 200000);
$mid     = mb_substr(trim((string)($d['message_id'] ?? '')), 0, 255);
$ts      = (string)($d['ts'] ?? '');
$sig     = (string)($d['sig'] ?? '');

if ($to === '' || $from === '' || $ts === '' || $sig === '') {
    http_response_code(400); echo json_encode(['ok' => false]); exit;
}
if (stripos($to, '@vexaris.net') === false) {
    http_response_code(400); echo json_encode(['ok' => false]); exit;
}
if (abs(time() - (int)$ts) > 600) {
    http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'stale']); exit;
}
$calc = hash_hmac('sha256', $ts . "\n" . $to . "\n" . $from . "\n" . $subject . "\n" . $text, $secret);
if (!hash_equals($calc, $sig)) {
    http_response_code(403); echo json_encode(['ok' => false]); exit;
}

try {
    if ($mid !== '') {
        $st = $pdo->prepare("SELECT id FROM inbound_emails WHERE message_id=? LIMIT 1");
        $st->execute([$mid]);
        if ($st->fetch()) { echo json_encode(['ok' => true, 'dup' => true]); exit; }
    }
    $st = $pdo->prepare("INSERT INTO inbound_emails (to_addr, from_addr, from_name, subject, body_text, message_id) VALUES (?,?,?,?,?,?)");
    $st->execute([$to, $from, ($fromNm !== '' ? $fromNm : null), ($subject !== '' ? $subject : null), ($text !== '' ? $text : null), ($mid !== '' ? $mid : null)]);
    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['ok' => false]);
}

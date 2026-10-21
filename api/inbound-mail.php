<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/mail.php';

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
} catch (Throwable $e) {}
if ($secret === '') { http_response_code(503); echo json_encode(['ok' => false, 'msg' => 'not-configured']); exit; }

$to      = mb_substr(trim((string)($d['to'] ?? '')), 0, 190);
$from    = mb_substr(trim((string)($d['from'] ?? '')), 0, 190);
$fromNm  = mb_substr(trim((string)($d['from_name'] ?? '')), 0, 190);
$subject = mb_substr(trim((string)($d['subject'] ?? '')), 0, 255);
$text    = mb_substr((string)($d['text'] ?? ''), 0, 200000);
$html    = mb_substr((string)($d['html'] ?? ''), 0, 200000);
$htmlsum = (string)($d['htmlsum'] ?? '');
$mid     = mb_substr(trim((string)($d['message_id'] ?? '')), 0, 255);
$nonce   = strtolower(trim((string)($d['nonce'] ?? '')));
$ts      = (string)($d['ts'] ?? '');
$sig     = (string)($d['sig'] ?? '');

if ($to === '' || $from === '' || $ts === '' || $sig === '') {
    http_response_code(400); echo json_encode(['ok' => false]); exit;
}
if ($nonce !== '' && !preg_match('/^[a-f0-9]{16,64}$/', $nonce)) {
    http_response_code(400); echo json_encode(['ok' => false]); exit;
}
if (stripos($to, '@vexaris.net') === false) {
    http_response_code(400); echo json_encode(['ok' => false]); exit;
}
if (abs(time() - (int)$ts) > 600) {
    http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'stale']); exit;
}
$sigExtra = $nonce !== '' ? "\n" . $nonce : '';
$calc = hash_hmac('sha256', $ts . "\n" . $to . "\n" . $from . "\n" . $subject . "\n" . $text . "\n" . ($html !== '' ? hash('sha256', $html) : '') . $sigExtra, $secret);
if (!hash_equals($calc, $sig)) {
    // Geriye uyumluluk: html gondermeyen eski Worker surumu
    $calc0 = hash_hmac('sha256', $ts . "\n" . $to . "\n" . $from . "\n" . $subject . "\n" . $text . $sigExtra, $secret);
    if ($html !== '' || !hash_equals($calc0, $sig)) {
        http_response_code(403); echo json_encode(['ok' => false]); exit;
    }
    if ($htmlsum !== '') { http_response_code(403); echo json_encode(['ok' => false]); exit; }
} elseif ($html !== '' && $htmlsum !== '' && !hash_equals(hash('sha256', $html), strtolower($htmlsum))) {
    http_response_code(403); echo json_encode(['ok' => false]); exit;
}

if ($nonce !== '') {
    try {
        if (random_int(1, 50) === 1) {
            $pdo->exec("DELETE FROM license_nonces WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        }
        $n = $pdo->prepare("INSERT IGNORE INTO license_nonces (nonce) VALUES (?)");
        $n->execute([$nonce]);
        if ($n->rowCount() === 0) {
            http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'nonce used']); exit;
        }
    } catch (Throwable $e) {
        http_response_code(500); echo json_encode(['ok' => false]); exit;
    }
}

try {
    if ($mid !== '') {
        $st = $pdo->prepare("SELECT id, spam_checked FROM inbound_emails WHERE message_id=? LIMIT 1");
        $st->execute([$mid]);
    } else {
        $st = $pdo->prepare("SELECT id, spam_checked FROM inbound_emails WHERE message_id IS NULL AND from_addr=? AND subject<=>? AND body_text<=>? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE) LIMIT 1");
        $st->execute([$from, ($subject !== '' ? $subject : null), ($text !== '' ? $text : null)]);
    }
    $ex = $st->fetch();
    if ($ex) {
        try {
            if (!(int)$ex['spam_checked'] && function_exists('mail_spam_scan_email')) { mail_spam_scan_email($pdo, (int)$ex['id']); }
            if (function_exists('mail_promote_stale_pending')) { mail_promote_stale_pending($pdo, 30); }
        } catch (Throwable $e) {}
        echo json_encode(['ok' => true, 'dup' => true]); exit;
    }
    $st = $pdo->prepare("INSERT INTO inbound_emails (to_addr, from_addr, from_name, subject, body_text, body_html, message_id) VALUES (?,?,?,?,?,?,?)");
    $st->execute([$to, $from, ($fromNm !== '' ? $fromNm : null), ($subject !== '' ? $subject : null), ($text !== '' ? $text : null), ($html !== '' ? $html : null), ($mid !== '' ? $mid : null)]);
    $newId = (int)$pdo->lastInsertId();
    try {
        if (function_exists('mail_spam_scan_email')) { mail_spam_scan_email($pdo, $newId); }
        if (function_exists('mail_promote_stale_pending')) { mail_promote_stale_pending($pdo, 30); }
    } catch (Throwable $e) {}
    echo json_encode(['ok' => true, 'id' => $newId]);
} catch (Throwable $e) {
    http_response_code(500); echo json_encode(['ok' => false]);
}

<?php
require_once __DIR__ . '/../core/db.php';

header('Content-Type: application/json; charset=utf-8');

boot_session();
require_admin();
if (!has_permission('tickets')) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'yetki']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false]); exit; }

$raw = file_get_contents('php://input');
if (strlen($raw) > 30000) { http_response_code(413); echo json_encode(['ok' => false]); exit; }
$d = json_decode($raw, true);
if (!is_array($d)) { http_response_code(400); echo json_encode(['ok' => false]); exit; }

$token = (string)($d['_csrf'] ?? '');
$sess = (string)($_SESSION['csrf'] ?? '');
if ($sess === '' || $token === '' || !hash_equals($sess, $token)) {
    http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'csrf']); exit;
}

$LANGS = ['tr', 'en', 'de', 'fr', 'es', 'ru', 'ar', 'az'];
$tl = strtolower((string)($d['lang'] ?? 'tr'));
if (!in_array($tl, $LANGS, true)) { $tl = 'tr'; }

function vxt_text(string $html): string {
    $t = str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</tr>', '</h1>', '</h2>', '</h3>', '</li>'], "\n", $html);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8');
    $t = (string)preg_replace('/[ \t\x0B\f]+/', ' ', $t);
    $t = (string)preg_replace('/\n\s*\n+/', "\n\n", $t);
    return trim($t);
}

$text = '';
if (isset($d['id'])) {
    $pdo = db();
    if (!$pdo) { http_response_code(503); echo json_encode(['ok' => false]); exit; }
    $id = (int)$d['id'];
    $box = ($d['box'] ?? '') === 'sent' ? 'sent' : 'in';
    if ($id <= 0) { http_response_code(400); echo json_encode(['ok' => false]); exit; }
    if ($box === 'sent') {
        $st = $pdo->prepare("SELECT body_html FROM sent_emails WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $text = vxt_text((string)($st->fetchColumn() ?: ''));
    } else {
        $st = $pdo->prepare("SELECT body_text, body_html FROM inbound_emails WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row) { $text = (string)$row['body_text']; if (trim($text) === '') { $text = vxt_text((string)$row['body_html']); } }
    }
} else {
    $text = (string)($d['text'] ?? '');
}
$text = trim(mb_substr($text, 0, 20000));
if ($text === '') { echo json_encode(['ok' => true, 'text' => '', 'lang' => $tl, 'detected' => '']); exit; }

function vxt_chunks(string $text, int $max = 1700): array {
    $out = []; $len = mb_strlen($text);
    $i = 0;
    while ($i < $len) {
        $chunk = mb_substr($text, $i, $max);
        if ($i + $max < $len) {
            $cut = mb_strrpos($chunk, "\n");
            if ($cut === false || $cut < 400) { $c2 = mb_strrpos($chunk, '. '); if ($c2 !== false && $c2 > 400) { $cut = $c2 + 1; } }
            if ($cut !== false && $cut > 400) { $chunk = mb_substr($chunk, 0, $cut); }
        }
        $out[] = $chunk;
        $i += mb_strlen($chunk);
    }
    return $out;
}

function vxt_google(string $q, string $tl, string &$detected = ''): ?string {
    $url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=auto&tl=' . urlencode($tl) . '&dt=t&q=' . urlencode($q);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ['User-Agent: Mozilla/5.0 (Vexaris Admin)', 'Accept: application/json'],
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($resp === false || $code < 200 || $code >= 300) { return null; }
    $j = json_decode($resp, true);
    if (!is_array($j) || !isset($j[0]) || !is_array($j[0])) { return null; }
    $out = '';
    foreach ($j[0] as $seg) { if (is_array($seg) && isset($seg[0])) { $out .= (string)$seg[0]; } }
    if ($detected === '' && isset($j[2]) && is_string($j[2])) { $detected = $j[2]; }
    return $out;
}

$detected = '';
$result = '';
foreach (vxt_chunks($text) as $chunk) {
    $tr = vxt_google($chunk, $tl, $detected);
    if ($tr === null) { echo json_encode(['ok' => false, 'msg' => 'Çeviri servisine ulaşılamadı.']); exit; }
    $result .= $tr;
}
echo json_encode(['ok' => true, 'text' => $result, 'lang' => $tl, 'detected' => $detected]);

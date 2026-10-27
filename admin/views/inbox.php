<?php
require_once __DIR__ . '/../../core/mail.php';
require_once __DIR__ . '/../../core/editor.php';
require_once __DIR__ . '/../../core/html-sanitize.php';
require_once __DIR__ . '/../../core/ai.php';

function e2($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$IB_DEF_ALIASES = ['info@vexaris.net', 'abuse@vexaris.net', 'founder@vexaris.net', 'noreply@vexaris.net'];

function ib_cfg(PDO $pdo, string $key, string $def = ''): string {
    try {
        $st = $pdo->prepare("SELECT `value` FROM settings WHERE `key`=? LIMIT 1");
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return ($v === false || $v === null) ? $def : (string)$v;
    } catch (Throwable $e) { return $def; }
}
function ib_cfg_set(PDO $pdo, string $key, string $value): void {
    $st = $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
    $st->execute([$key, $value]);
}
function ib_aliases(PDO $pdo, array $defaults): array {
    $raw = ib_cfg($pdo, 'mail_aliases', '');
    $list = [];
    if ($raw !== '') {
        $d = json_decode($raw, true);
        if (is_array($d)) {
            foreach ($d as $a) {
                $a = strtolower(trim((string)$a));
                if (filter_var($a, FILTER_VALIDATE_EMAIL) && stripos($a, '@vexaris.net') !== false && !in_array($a, $list, true)) { $list[] = $a; }
            }
        }
    }
    if (!$list) { $list = $defaults; }
    return $list;
}
function ib_dt(?string $s, bool $full = false): string {
    if ($s === null || $s === '') { return ''; }
    $t = strtotime($s);
    if (!$t) { return (string)$s; }
    if (!$full && date('Y-m-d') === date('Y-m-d', $t)) { return date('H:i', $t); }
    if (date('Y') === date('Y', $t)) { return date('d.m H:i', $t); }
    return date('d.m.Y H:i', $t);
}
function ib_text(string $html): string {
    $t = str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</tr>', '</h1>', '</h2>', '</h3>', '</li>'], "\n", $html);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8');
    $t = (string)preg_replace('/[ \t\x0B\f]+/', ' ', $t);
    $t = (string)preg_replace('/\n\s*\n+/', "\n\n", $t);
    return trim($t);
}
function ib_quote(array $row, string $box): string {
    $who = $box === 'sent' ? (string)($row['to_addr'] ?? '') : (string)($row['from_addr'] ?? '');
    $when = (string)($row['created_at'] ?? '');
    $text = $box === 'sent' ? ib_text((string)($row['body_html'] ?? '')) : (string)($row['body_text'] ?? '');
    if ($text === '') { $text = ib_text((string)($row['body_html'] ?? '')); }
    return '<br><br><div style="border-left:3px solid #ccc;padding-left:10px;color:#666">'
        . '<div style="font-size:12px;margin-bottom:6px">--- ' . e2($who) . ' yazdı (' . e2($when) . ') ---</div>'
        . nl2br(e2($text)) . '</div>';
}
function ib_go(string $code): void {
    $back = (string)($_POST['back'] ?? '');
    $back = (string)preg_replace('/[^A-Za-z0-9%&=_.+~\-]/', '', $back);
    if ($back !== '') {
        header('Location: /admin?' . $back . '&done=' . urlencode($code));
    } else {
        header('Location: /admin?tab=inbox&done=' . urlencode($code));
    }
    exit;
}

$OKMSG = [
    'sent' => 'Mail gönderildi ve Gönderilenler kutusuna kaydedildi.',
    'star' => 'Yıldız durumu güncellendi.',
    'read' => 'Okundu olarak işaretlendi.',
    'unread' => 'Okunmadı olarak işaretlendi.',
    'spam' => 'Mail spam olarak işaretlendi.',
    'notspam' => 'Mail spam değil olarak işaretlendi.',
    'trash' => 'Mail çöp kutusuna taşındı.',
    'restore' => 'Mail geri yüklendi.',
    'del' => 'Mail kalıcı olarak silindi.',
    'emptytrash' => 'Çöp kutusu boşaltıldı.',
    'saved' => 'Ayarlar kaydedildi.',
    'alias_add' => 'Alias eklendi.',
    'alias_del' => 'Alias kaldırıldı.',
    'regen' => 'Webhook anahtarı yenilendi. Worker içindeki INBOUND_SECRET değerini güncelle.',
    'bulk' => 'Toplu işlem uygulandı.',
];
$ERRMSG = [
    'invalid_to' => 'Alıcı e-posta adresi geçersiz.',
    'invalid_from' => 'Gönderen alias geçersiz.',
    'empty_fields' => 'Konu ve içerik zorunlu.',
    'send_fail' => 'Mail gönderilemedi. Aktarım ayarlarını (Ayarlar sekmesi) kontrol et.',
    'alias_invalid' => 'Alias geçersiz. Sadece harf, rakam, nokta, tire kullan (ör: destek).',
    'alias_exists' => 'Bu alias zaten listede.',
    'alias_limit' => 'En fazla 12 alias ekleyebilirsin.',
    'alias_last' => 'En az bir alias kalmalı.',
    'settings_invalid' => 'Ayar değerleri geçersiz.',
];

$mailCfg = mail_get_settings($pdo);
$transfer = strtolower(trim((string)($mailCfg['mail_transfer'] ?? 'sendmail')));
$transferName = ['brevo' => 'Brevo API', 'smtp' => 'SMTP', 'sendmail' => 'Sunucu (sendmail)'][$transfer] ?? $transfer;
$aliases = ib_aliases($pdo, $IB_DEF_ALIASES);

$folder = in_array($_GET['folder'] ?? 'inbox', ['inbox', 'sent', 'starred', 'trash', 'spam'], true) ? (string)($_GET['folder'] ?? 'inbox') : 'inbox';
$filter = in_array($_GET['filter'] ?? 'all', ['all', 'unread', 'read', 'replied'], true) ? (string)($_GET['filter'] ?? 'all') : 'all';
if (!in_array($folder, ['inbox', 'spam'], true)) { $filter = 'all'; }
$q      = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = (int)ib_cfg($pdo, 'mail_page_size', '25');
if (!in_array($per, [25, 50, 100], true)) { $per = 25; }
$markRead = ib_cfg($pdo, 'mail_mark_read', '1') === '1';

$inSecret = ib_cfg($pdo, 'mail_inbound_secret', '');
$inMsg = ''; $inErr = false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $pdo) {
    csrf_check();
    $act = (string)($_POST['ib_act'] ?? '');

    if ($act === 'send') {
        $to      = mb_substr(trim((string)($_POST['ib_to'] ?? '')), 0, 190);
        $from    = trim((string)($_POST['ib_from'] ?? ''));
        $subj    = mb_substr(trim((string)($_POST['ib_subj'] ?? '')), 0, 255);
        $html    = vx_clean_html(mb_substr((string)($_POST['ib_html'] ?? ''), 0, 500000), 'rich');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { $inMsg = $ERRMSG['invalid_to']; $inErr = true; }
        elseif (!in_array($from, $aliases, true)) { $inMsg = $ERRMSG['invalid_from']; $inErr = true; }
        elseif ($subj === '' || trim(strip_tags($html)) === '') { $inMsg = $ERRMSG['empty_fields']; $inErr = true; }
        else {
            $ok = mail_send($pdo, $to, $subj, $html, [], ['email' => $from, 'name' => 'Vexaris', 'raw' => true]);
            if ($ok) {
                $st = $pdo->prepare("INSERT INTO sent_emails (to_addr, from_addr, subject, body_html) VALUES (?,?,?,?)");
                $st->execute([$to, $from, $subj, $html]);
                if (!empty($_POST['ib_inreply'])) {
                    $st = $pdo->prepare("UPDATE inbound_emails SET is_read=1, replied_at=NOW() WHERE id=?");
                    $st->execute([(int)$_POST['ib_inreply']]);
                }
                header('Location: /admin?tab=inbox&folder=sent&done=sent');
                exit;
            }
            $inMsg = $ERRMSG['send_fail'] . ' (' . $transferName . ')';
            $inErr = true;
        }
    } elseif ($act === 'star' && isset($_POST['id'])) {
        $tbl = ($_POST['box'] ?? '') === 'sent' ? 'sent_emails' : 'inbound_emails';
        $st = $pdo->prepare("UPDATE $tbl SET starred = 1 - starred WHERE id=?");
        $st->execute([(int)$_POST['id']]);
        ib_go('star');
    } elseif (($act === 'read' || $act === 'unread') && isset($_POST['id'])) {
        $val = $act === 'read' ? 1 : 0;
        $st = $pdo->prepare("UPDATE inbound_emails SET is_read=? WHERE id=?");
        $st->execute([$val, (int)$_POST['id']]);
        ib_go($act);
    } elseif ($act === 'spam' && isset($_POST['id'])) {
        $st = $pdo->prepare("UPDATE inbound_emails SET spam=1, spam_checked=1 WHERE id=?");
        $st->execute([(int)$_POST['id']]);
        ib_go('spam');
    } elseif ($act === 'notspam' && isset($_POST['id'])) {
        $st = $pdo->prepare("UPDATE inbound_emails SET spam=0, spam_checked=1 WHERE id=?");
        $st->execute([(int)$_POST['id']]);
        ib_go('notspam');
    } elseif ($act === 'trash' && isset($_POST['id'])) {
        $tbl = ($_POST['box'] ?? '') === 'sent' ? 'sent_emails' : 'inbound_emails';
        $st = $pdo->prepare("UPDATE $tbl SET deleted=1 WHERE id=?");
        $st->execute([(int)$_POST['id']]);
        ib_go('trash');
    } elseif ($act === 'restore' && isset($_POST['id'])) {
        $tbl = ($_POST['box'] ?? '') === 'sent' ? 'sent_emails' : 'inbound_emails';
        $st = $pdo->prepare("UPDATE $tbl SET deleted=0 WHERE id=?");
        $st->execute([(int)$_POST['id']]);
        ib_go('restore');
    } elseif ($act === 'del' && isset($_POST['id'])) {
        $tbl = ($_POST['box'] ?? '') === 'sent' ? 'sent_emails' : 'inbound_emails';
        $st = $pdo->prepare("DELETE FROM $tbl WHERE id=?");
        $st->execute([(int)$_POST['id']]);
        ib_go('del');
    } elseif ($act === 'emptytrash') {
        $pdo->exec("DELETE FROM inbound_emails WHERE deleted=1");
        $pdo->exec("DELETE FROM sent_emails WHERE deleted=1");
        ib_go('emptytrash');
    } elseif ($act === 'bulk') {
        $op = (string)($_POST['ib_bulk'] ?? '');
        if ($op === 'emptytrash') {
            $pdo->exec("DELETE FROM inbound_emails WHERE deleted=1");
            $pdo->exec("DELETE FROM sent_emails WHERE deleted=1");
            ib_go('emptytrash');
        }
        if (!empty($_POST['ids_in_all']) && $op === 'del') {
            $pdo->exec("DELETE FROM inbound_emails WHERE spam=1");
            ib_go('bulk');
        }
        $idsIn = array_values(array_filter(array_map('intval', (array)($_POST['ids_in'] ?? []))));
        $idsSent = array_values(array_filter(array_map('intval', (array)($_POST['ids_sent'] ?? []))));
        if ($idsIn || $idsSent) {
            $apply = function (string $tbl, array $ids, string $sql) use ($pdo) {
                if (!$ids) { return; }
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $st = $pdo->prepare("UPDATE $tbl SET $sql WHERE id IN ($ph)");
                $st->execute($ids);
            };
            $remove = function (string $tbl, array $ids) use ($pdo) {
                if (!$ids) { return; }
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $st = $pdo->prepare("DELETE FROM $tbl WHERE id IN ($ph)");
                $st->execute($ids);
            };
            if ($op === 'read') { $apply('inbound_emails', $idsIn, 'is_read=1'); }
            elseif ($op === 'unread') { $apply('inbound_emails', $idsIn, 'is_read=0'); }
            elseif ($op === 'star') { $apply('inbound_emails', $idsIn, 'starred=1'); $apply('sent_emails', $idsSent, 'starred=1'); }
            elseif ($op === 'unstar') { $apply('inbound_emails', $idsIn, 'starred=0'); $apply('sent_emails', $idsSent, 'starred=0'); }
            elseif ($op === 'trash') { $apply('inbound_emails', $idsIn, 'deleted=1'); $apply('sent_emails', $idsSent, 'deleted=1'); }
            elseif ($op === 'restore') { $apply('inbound_emails', $idsIn, 'deleted=0'); $apply('sent_emails', $idsSent, 'deleted=0'); }
            elseif ($op === 'spam') { $apply('inbound_emails', $idsIn, 'spam=1, spam_checked=1'); }
            elseif ($op === 'notspam') { $apply('inbound_emails', $idsIn, 'spam=0, spam_checked=1'); }
            elseif ($op === 'del') { $remove('inbound_emails', $idsIn); $remove('sent_emails', $idsSent); }
            ib_go('bulk');
        }
        ib_go('bulk');
    } elseif ($act === 'alias_add') {
        $inp = strtolower(trim((string)($_POST['ib_alias'] ?? '')));
        if ($inp !== '' && strpos($inp, '@') !== false) { $local = explode('@', $inp)[0]; } else { $local = $inp; }
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,40}$/', $local)) { ib_go('e_alias_invalid'); }
        $alias = $local . '@vexaris.net';
        if (in_array($alias, $aliases, true)) { ib_go('e_alias_exists'); }
        if (count($aliases) >= 12) { ib_go('e_alias_limit'); }
        $aliases[] = $alias;
        ib_cfg_set($pdo, 'mail_aliases', json_encode(array_values($aliases)));
        ib_go('alias_add');
    } elseif ($act === 'alias_del') {
        $alias = strtolower(trim((string)($_POST['ib_alias'] ?? '')));
        if (in_array($alias, $aliases, true)) {
            if (count($aliases) <= 1) { ib_go('e_alias_last'); }
            $aliases = array_values(array_filter($aliases, function ($a) use ($alias) { return $a !== $alias; }));
            ib_cfg_set($pdo, 'mail_aliases', json_encode($aliases));
            if (ib_cfg($pdo, 'mail_default_from', 'info@vexaris.net') === $alias) {
                ib_cfg_set($pdo, 'mail_default_from', $aliases[0]);
            }
            ib_go('alias_del');
        }
        ib_go('e_alias_invalid');
    } elseif ($act === 'settings_save') {
        $defFrom = strtolower(trim((string)($_POST['ib_default_from'] ?? '')));
        if (!in_array($defFrom, $aliases, true)) { $defFrom = $aliases[0]; }
        $sig = vx_clean_html(mb_substr((string)($_POST['ib_signature'] ?? ''), 0, 8000), 'basic');
        $ps = (int)($_POST['ib_page_size'] ?? 25);
        if (!in_array($ps, [25, 50, 100], true)) { $ps = 25; }
        ib_cfg_set($pdo, 'mail_default_from', $defFrom);
        ib_cfg_set($pdo, 'mail_signature', $sig);
        ib_cfg_set($pdo, 'mail_page_size', (string)$ps);
        ib_cfg_set($pdo, 'mail_mark_read', !empty($_POST['ib_mark_read']) ? '1' : '0');
        ib_cfg_set($pdo, 'mail_spam_enabled', !empty($_POST['ib_spam_enabled']) ? '1' : '0');
        $sp = trim(mb_substr((string)($_POST['ib_spam_prompt'] ?? ''), 0, 2000));
        if ($sp !== '') { ib_cfg_set($pdo, 'mail_spam_prompt', $sp); }
        $words = mb_substr(trim((string)($_POST['ib_spam_words'] ?? '')), 0, 20000);
        ib_cfg_set($pdo, 'mail_spam_words', $words);
        $mx = (int)($_POST['ib_spam_maxlen'] ?? 10000);
        if ($mx < 0) { $mx = 0; }
        ib_cfg_set($pdo, 'mail_spam_maxlen', (string)$mx);
        /* EVREN (SSB) LLM cekisi: clef karar modeli ile olasilik tabanli spam tespiti */
        ib_cfg_set($pdo, 'mail_spam_evren', !empty($_POST['ib_spam_evren']) ? '1' : '0');
        $evModel = trim(mb_substr((string)($_POST['ib_spam_evren_model'] ?? 'clef'), 0, 40));
        if ($evModel === '') { $evModel = 'clef'; }
        ib_cfg_set($pdo, 'mail_spam_evren_model', $evModel);
        $thr = (float)str_replace(',', '.', (string)($_POST['ib_spam_threshold'] ?? '0.7'));
        if ($thr <= 0 || $thr > 1) { $thr = 0.7; }
        ib_cfg_set($pdo, 'mail_spam_threshold', (string)$thr);
        $evKey = trim((string)($_POST['ib_evren_key'] ?? ''));
        if ($evKey !== '') {
            $enc = function_exists('ai_encrypt') ? ai_encrypt($evKey) : '';
            if ($enc !== '') { ib_cfg_set($pdo, 'evren_llm_api_key', $enc); }
        }
        $evBase = trim(mb_substr((string)($_POST['ib_evren_base'] ?? ''), 0, 200));
        if ($evBase !== '') { ib_cfg_set($pdo, 'evren_llm_base_url', $evBase); }
        ib_go('saved');
    } elseif ($act === 'regen') {
        $inSecret = bin2hex(random_bytes(32));
        ib_cfg_set($pdo, 'mail_inbound_secret', $inSecret);
        ib_go('regen');
    }
}
if ($inSecret === '' && $pdo) {
    $inSecret = bin2hex(random_bytes(32));
    try { ib_cfg_set($pdo, 'mail_inbound_secret', $inSecret); } catch (Throwable $e) {}
}

function ib_like(string $q): string {
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q) . '%';
}
function ib_list(PDO $pdo, string $folder, string $filter, string $q, int $page, int $per): array {
    $inCols = "id,'in' AS box,to_addr,from_addr,from_name,subject,is_read,replied_at,starred,created_at,LEFT(COALESCE(NULLIF(body_text,''),body_html),600) AS preview";
    $seCols = "id,'sent' AS box,to_addr,from_addr,NULL AS from_name,subject,0 AS is_read,NULL AS replied_at,starred,created_at,LEFT(body_html,600) AS preview";
    $inW = ['deleted=0', 'spam=0', 'spam_checked=1']; $inA = [];
    $seW = ['deleted=0']; $seA = [];
    if ($folder === 'starred') { $inW[] = 'starred=1'; $seW[] = 'starred=1'; }
    if ($folder === 'trash') { $inW = ['deleted=1']; $seW = ['deleted=1']; }
    if ($folder === 'spam') { $inW = ['deleted=0', 'spam=1']; }
    if (($folder === 'inbox' || $folder === 'spam') && $filter === 'unread') { $inW[] = 'is_read=0'; }
    if (($folder === 'inbox' || $folder === 'spam') && $filter === 'read') { $inW[] = 'is_read=1'; }
    if ($folder === 'inbox' && $filter === 'replied') { $inW[] = 'replied_at IS NOT NULL'; }
    if ($q !== '') {
        $like = ib_like($q);
        $inW[] = '(subject LIKE ? OR from_addr LIKE ? OR to_addr LIKE ? OR from_name LIKE ? OR body_text LIKE ?)';
        array_push($inA, $like, $like, $like, $like, $like);
        $seW[] = '(subject LIKE ? OR to_addr LIKE ? OR from_addr LIKE ? OR body_html LIKE ?)';
        array_push($seA, $like, $like, $like, $like);
    }
    $inWhere = implode(' AND ', $inW);
    $seWhere = implode(' AND ', $seW);
    if ($folder === 'sent') {
        $inner = "SELECT $seCols FROM sent_emails WHERE $seWhere";
        $args = $seA;
    } elseif ($folder === 'inbox' || $folder === 'spam') {
        $inner = "SELECT $inCols FROM inbound_emails WHERE $inWhere";
        $args = $inA;
    } else {
        $inner = "(SELECT $inCols FROM inbound_emails WHERE $inWhere) UNION ALL (SELECT $seCols FROM sent_emails WHERE $seWhere)";
        $args = array_merge($inA, $seA);
    }
    $total = 0;
    try {
        $cst = $pdo->prepare("SELECT COUNT(*) FROM ($inner) t");
        $cst->execute($args);
        $total = (int)$cst->fetchColumn();
    } catch (Throwable $e) { $total = 0; }
    $pages = max(1, (int)ceil($total / $per));
    if ($page > $pages) { $page = $pages; }
    $off = ($page - 1) * $per;
    $items = [];
    try {
        $st = $pdo->prepare("SELECT * FROM ($inner) t ORDER BY created_at DESC, id DESC LIMIT $per OFFSET $off");
        $st->execute($args);
        $items = $st->fetchAll();
    } catch (Throwable $e) { $items = []; }
    return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page, 'per' => $per];
}

if ($pdo && function_exists('mail_promote_stale_pending')) { try { mail_promote_stale_pending($pdo, 30); } catch (Throwable $e) {} }

$counts = ['inbox' => 0, 'unread' => 0, 'sent' => 0, 'starred' => 0, 'trash' => 0, 'spam' => 0];
$spamPending = 0;
if ($pdo) {
    try {
        $r = $pdo->query("SELECT SUM(deleted=0 AND spam=0 AND spam_checked=1), SUM(deleted=0 AND spam=0 AND spam_checked=1 AND is_read=0), SUM(deleted=0 AND starred=1 AND spam=0 AND spam_checked=1), SUM(deleted=1), SUM(deleted=0 AND spam=1), SUM(deleted=0 AND spam_checked=0) FROM inbound_emails")->fetch(PDO::FETCH_NUM);
        $counts['inbox'] = (int)($r[0] ?? 0);
        $counts['unread'] = (int)($r[1] ?? 0);
        $counts['starred'] += (int)($r[2] ?? 0);
        $counts['trash'] += (int)($r[3] ?? 0);
        $counts['spam'] = (int)($r[4] ?? 0);
        $spamPending = (int)($r[5] ?? 0);
        $r = $pdo->query("SELECT SUM(deleted=0), SUM(deleted=0 AND starred=1), SUM(deleted=1) FROM sent_emails")->fetch(PDO::FETCH_NUM);
        $counts['sent'] = (int)($r[0] ?? 0);
        $counts['starred'] += (int)($r[1] ?? 0);
        $counts['trash'] += (int)($r[2] ?? 0);
    } catch (Throwable $e) {}
}

$listing = ['items' => [], 'total' => 0, 'pages' => 1, 'page' => 1, 'per' => $per];
if ($pdo) { $listing = ib_list($pdo, $folder, $filter, $q, $page, $per); }
$mails = $listing['items'];
$page = $listing['page'];

$sel = null; $selBox = 'in';
$selId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$reqBox = ($_GET['box'] ?? '') === 'sent' ? 'sent' : 'in';
if ($selId > 0 && $pdo) {
    try {
        if ($folder === 'sent') { $reqBox = 'sent'; }
        if ($reqBox === 'sent') {
            $st = $pdo->prepare("SELECT * FROM sent_emails WHERE id=? LIMIT 1");
        } else {
            $st = $pdo->prepare("SELECT * FROM inbound_emails WHERE id=? LIMIT 1");
        }
        $st->execute([$selId]);
        $sel = $st->fetch() ?: null;
        if ($sel) {
            $selBox = $reqBox;
            if ($selBox === 'in' && !(int)$sel['is_read'] && $markRead) {
                $pdo->prepare("UPDATE inbound_emails SET is_read=1 WHERE id=?")->execute([$selId]);
                $sel['is_read'] = 1;
            }
        }
    } catch (Throwable $e) { $sel = null; }
}

$composing = isset($_GET['compose']) || isset($_GET['reply']) || isset($_GET['fwd']) || $inErr;
$replyRow = null; $replyBox = 'in';
if ((isset($_GET['reply']) || isset($_GET['fwd'])) && $pdo) {
    $rid = (int)($_GET['reply'] ?? $_GET['fwd'] ?? 0);
    $rb = ($_GET['box'] ?? '') === 'sent' ? 'sent' : 'in';
    try {
        if ($rb === 'sent') {
            $st = $pdo->prepare("SELECT * FROM sent_emails WHERE id=? LIMIT 1");
        } else {
            $st = $pdo->prepare("SELECT * FROM inbound_emails WHERE id=? LIMIT 1");
        }
        $st->execute([$rid]);
        $replyRow = $st->fetch() ?: null;
        $replyBox = $rb;
    } catch (Throwable $e) {}
}
$isFwd = isset($_GET['fwd']);
$sig = (string)ib_cfg($pdo, 'mail_signature', '');
$defFrom = (string)ib_cfg($pdo, 'mail_default_from', $aliases[0]);
if (!in_array($defFrom, $aliases, true)) { $defFrom = $aliases[0]; }

$cTo = ''; $cFrom = $defFrom; $cSubj = ''; $cBody = $sig;
if ($replyRow) {
    if ($isFwd) {
        $cSubj = 'Fwd: ' . ($replyRow['subject'] !== '' ? $replyRow['subject'] : '(konu yok)');
        $cBody = ($sig !== '' ? $sig . '<br>' : '') . ib_quote($replyRow, $replyBox);
    } else {
        if ($replyBox === 'sent') {
            $cTo = (string)$replyRow['to_addr'];
            $cFrom = in_array($replyRow['from_addr'], $aliases, true) ? $replyRow['from_addr'] : $defFrom;
        } else {
            $cTo = (string)$replyRow['from_addr'];
            $cFrom = in_array($replyRow['to_addr'], $aliases, true) ? $replyRow['to_addr'] : $defFrom;
        }
        $cSubj = 'Re: ' . ($replyRow['subject'] !== '' ? $replyRow['subject'] : '(konu yok)');
        $cBody = ($sig !== '' ? $sig . '<br>' : '') . ib_quote($replyRow, $replyBox);
    }
}
if ($inErr) {
    $cTo = (string)($_POST['ib_to'] ?? $cTo);
    $cFrom = in_array((string)($_POST['ib_from'] ?? ''), $aliases, true) ? (string)$_POST['ib_from'] : $cFrom;
    $cSubj = (string)($_POST['ib_subj'] ?? $cSubj);
    $cBody = vx_clean_html((string)($_POST['ib_html'] ?? $cBody), 'rich');
}
$sigJson = json_encode($sig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$backQ = ['tab' => 'inbox', 'folder' => $folder];
if ($filter !== 'all') { $backQ['filter'] = $filter; }
if ($q !== '') { $backQ['q'] = $q; }
if ($page > 1) { $backQ['page'] = $page; }
if ($sel) { $backQ['id'] = $selId; $backQ['box'] = $selBox; }
$ibBack = http_build_query($backQ);

$U = function (array $m = [], array $rm = []) use ($folder, $filter, $q, $page) {
    $p = ['tab' => 'inbox', 'folder' => $folder];
    if ($filter !== 'all') { $p['filter'] = $filter; }
    if ($q !== '') { $p['q'] = $q; }
    if ($page > 1) { $p['page'] = $page; }
    foreach ($rm as $k) { unset($p[$k]); }
    foreach ($m as $k => $v) { if ($v === null) { unset($p[$k]); } else { $p[$k] = $v; } }
    return '/admin?' . http_build_query($p);
};
$baseRm = ['id', 'box', 'page', 'reply', 'fwd', 'compose', 'set', 'done'];

$done = (string)($_GET['done'] ?? '');
$flashOk = ''; $flashErr = '';
if ($done !== '') {
    if (strpos($done, 'e_') === 0) { $flashErr = $ERRMSG[substr($done, 2)] ?? 'İşlem başarısız.'; }
    else { $flashOk = $OKMSG[$done] ?? 'Tamam.'; }
}
if (isset($_GET['sent'])) { $flashOk = $OKMSG['sent']; }
?>
<link rel="stylesheet" href="/assets/css/admin-inbox.css?v=3">
<script src="/assets/js/admin-inbox.js?v=3" defer></script>

<div class="ib-folders">
    <a class="ib-fold <?= $folder === 'inbox' && !$composing ? 'on' : '' ?>" href="<?= e2($U(['folder' => 'inbox', 'filter' => 'all'], ['id', 'box', 'page'])) ?>">
        <i class="fas fa-inbox"></i> Gelen Kutusu
        <?php if ($counts['unread'] > 0): ?><span class="ib-fold__n hot"><?= $counts['unread'] ?></span><?php else: ?><span class="ib-fold__n"><?= $counts['inbox'] ?></span><?php endif; ?>
    </a>
    <a class="ib-fold <?= $folder === 'starred' && !$composing ? 'on' : '' ?>" href="<?= e2($U(['folder' => 'starred', 'filter' => 'all'], ['id', 'box', 'page'])) ?>">
        <i class="fas fa-star"></i> Yıldızlı <?php if ($counts['starred'] > 0): ?><span class="ib-fold__n"><?= $counts['starred'] ?></span><?php endif; ?>
    </a>
    <a class="ib-fold <?= $folder === 'sent' && !$composing ? 'on' : '' ?>" href="<?= e2($U(['folder' => 'sent', 'filter' => 'all'], ['id', 'box', 'page'])) ?>">
        <i class="fas fa-paper-plane"></i> Gönderilenler <?php if ($counts['sent'] > 0): ?><span class="ib-fold__n"><?= $counts['sent'] ?></span><?php endif; ?>
    </a>
    <a class="ib-fold <?= $folder === 'spam' && !$composing ? 'on' : '' ?>" href="<?= e2($U(['folder' => 'spam', 'filter' => 'all'], ['id', 'box', 'page'])) ?>">
        <i class="fas fa-ban"></i> Spam <?php if ($counts['spam'] > 0): ?><span class="ib-fold__n hot"><?= $counts['spam'] ?></span><?php endif; ?>
    </a>
    <a class="ib-fold <?= $folder === 'trash' && !$composing ? 'on' : '' ?>" href="<?= e2($U(['folder' => 'trash', 'filter' => 'all'], ['id', 'box', 'page'])) ?>">
        <i class="fas fa-trash-can"></i> Çöp <?php if ($counts['trash'] > 0): ?><span class="ib-fold__n"><?= $counts['trash'] ?></span><?php endif; ?>
    </a>
    <button type="button" class="ib-fold ib-iconbtn" id="ibSetBtn" title="Posta ayarları"><i class="fas fa-gear"></i></button>
    <a class="ib-fold ib-iconbtn" href="<?= e2($U([], ['id', 'box'])) ?>" title="Yenile"><i class="fas fa-rotate-right"></i></a>
    <a class="ib-fold ib-new <?= $composing && !$replyRow ? 'on' : '' ?>" href="<?= e2($U(['compose' => 1], $baseRm)) ?>"><i class="fas fa-pen"></i> Yeni</a>
</div>

<?php if (!$composing): ?>
<form method="get" action="/admin" class="ib-search" style="margin-bottom:1rem">
    <input type="hidden" name="tab" value="inbox">
    <input type="hidden" name="folder" value="<?= e2($folder) ?>">
    <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?= e2($filter) ?>"><?php endif; ?>
    <div class="ib-search__wrap">
        <i class="fas fa-magnifying-glass"></i>
        <input type="text" name="q" value="<?= e2($q) ?>" placeholder="Mail ara (konu, adres, içerik)...">
    </div>
    <button type="submit">Ara</button>
    <?php if ($q !== ''): ?><a class="ib-fold" href="<?= e2($U([], ['q', 'id', 'box', 'page'])) ?>"><i class="fas fa-xmark"></i></a><?php endif; ?>
</form>
<?php if ($folder === 'inbox' || $folder === 'spam'): ?>
<div class="ib-filters">
    <a class="ib-fchip <?= $filter === 'all' ? 'on' : '' ?>" href="<?= e2($U(['filter' => 'all'], ['id', 'box', 'page'])) ?>">Tümü</a>
    <a class="ib-fchip <?= $filter === 'unread' ? 'on' : '' ?>" href="<?= e2($U(['filter' => 'unread'], ['id', 'box', 'page'])) ?>">Okunmayan<?= ($folder === 'inbox' && $counts['unread'] > 0) ? ' (' . $counts['unread'] . ')' : '' ?></a>
    <a class="ib-fchip <?= $filter === 'read' ? 'on' : '' ?>" href="<?= e2($U(['filter' => 'read'], ['id', 'box', 'page'])) ?>">Okunan</a>
    <?php if ($folder === 'inbox'): ?><a class="ib-fchip <?= $filter === 'replied' ? 'on' : '' ?>" href="<?= e2($U(['filter' => 'replied'], ['id', 'box', 'page'])) ?>">Yanıtlanan</a><?php endif; ?>
    <?php if ($folder === 'spam' && $counts['spam'] > 0): ?>
    <form method="post" style="margin-left:auto" onsubmit="return confirm('Spam kutusundaki TÜM mailler kalıcı silinsin mi?')">
        <?= csrf_field() ?>
        <input type="hidden" name="ib_bulk" value="del">
        <input type="hidden" name="ib_act" value="bulk">
        <input type="hidden" name="back" value="<?= e2($ibBack) ?>">
        <input type="hidden" name="ids_in_all" value="1">
        <button type="submit" class="ib-fchip" style="border-color:rgba(224,138,138,.3);color:#d79595"><i class="fas fa-broom"></i> Spam'ı temizle</button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php if ($folder === 'trash'): ?>
<div class="ib-filters">
    <span class="ib-fchip" style="cursor:default"><i class="fas fa-trash-can"></i> Çöp kutusu — <?= $counts['trash'] ?> mail</span>
    <form method="post" style="margin-left:auto" onsubmit="return confirm('Çöp kutusundaki TÜM mailler kalıcı silinsin mi?')">
        <?= csrf_field() ?>
        <input type="hidden" name="ib_act" value="emptytrash">
        <input type="hidden" name="back" value="<?= e2($ibBack) ?>">
        <button type="submit" class="ib-fchip" style="border-color:rgba(224,138,138,.3);color:#d79595"><i class="fas fa-broom"></i> Çöpü boşalt</button>
    </form>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if ($flashOk !== ''): ?><div class="alert-msg alert-ok" style="display:block"><?= e2($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr !== ''): ?><div class="alert-msg alert-err" style="display:block"><?= e2($flashErr) ?></div><?php endif; ?>
<?php if ($inMsg !== '' && !$inErr): ?><div class="alert-msg alert-ok" style="display:block"><?= e2($inMsg) ?></div><?php endif; ?>
<?php if ($inErr): ?><div class="alert-msg alert-err" style="display:block"><?= e2($inMsg) ?></div><?php endif; ?>

<?php if ($composing): ?>
<?php
$mailReady = ($mailCfg['mail_enabled'] ?? '0') === '1';
if ($transfer === 'brevo') { $mailReady = $mailReady && trim((string)($mailCfg['mail_brevo_api_key'] ?? '')) !== ''; }
elseif ($transfer === 'smtp') { $mailReady = $mailReady && trim((string)($mailCfg['mail_smtp_host'] ?? '')) !== ''; }
$cmpTitle = $isFwd ? 'İlet' : ($replyRow ? 'Yanıtla' : 'Yeni Mail');
?>
<?php if (!$mailReady): ?>
<div class="alert-msg alert-err" style="display:block"><i class="fas fa-triangle-exclamation"></i> Mail servisi hazır değil. <a href="/admin?tab=settings" style="color:inherit;text-decoration:underline">Ayarlar</a> sekmesinden Brevo/SMTP yapılandır.</div>
<?php endif; ?>
<div class="ib-main">
<div class="ib-cmp">
    <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.4rem">
        <span style="font-weight:800;font-size:1rem"><i class="fas <?= $isFwd ? 'fa-share' : ($replyRow ? 'fa-reply' : 'fa-pen') ?>" style="color:var(--cyan)"></i> <?= e2($cmpTitle) ?></span>
        <?php if ($replyRow): ?><span class="ib-pill"><?= e2(mb_strimwidth((string)$replyRow['subject'], 0, 60, '…')) ?></span><?php endif; ?>
    </div>
    <form method="post" id="ibSendForm">
        <?= csrf_field() ?>
        <input type="hidden" name="ib_act" value="send">
        <input type="hidden" name="ib_html" id="ibHtml" value="">
        <?php if ($replyRow && !$isFwd): ?><input type="hidden" name="ib_inreply" value="<?= (int)$replyRow['id'] ?>"><?php endif; ?>
        <label>Alıcı</label>
        <input type="email" name="ib_to" required placeholder="ornek@mail.com" value="<?= e2($cTo) ?>">
        <div class="ib-setgrid">
            <div>
                <label>Gönderen (alias)</label>
                <select name="ib_from">
                    <?php foreach ($aliases as $a): ?><option value="<?= e2($a) ?>" <?= $cFrom === $a ? 'selected' : '' ?>><?= e2($a) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Konu</label>
                <input type="text" name="ib_subj" required placeholder="Konu" value="<?= e2($cSubj) ?>">
            </div>
        </div>
        <div class="ib-cmp__lblrow">
            <label>İçerik</label>
            <button type="button" class="ib-minibtn" id="ibModeBtn" title="Görsel / HTML kod görünümü"><i class="fa-solid fa-code"></i> KOD</button>
            <button type="button" class="ib-minibtn" id="ibSigBtn" title="İmzayı ekle"><i class="fas fa-signature"></i> İmza</button>
            <button type="button" class="ib-minibtn" id="ibAiBtn" title="Vexaris AI ile yaz" style="color:#c9b8e8"><svg width="13" height="13" viewBox="0 0 200 200" style="vertical-align:-2px"><path d="M70 44q11 45 56 56-45 11-56 56-11-45-56-56 45-11 56-56m90-30q7 19 26 26-19 7-26 26-7-19-26-26 19-7 26-26m-10 120q7 19 26 26-19 7-26 26-7-19-26-26 19-7 26-26" fill="currentColor"/></svg> AI</button>
        </div>
        <?php render_editor('mailEd', $cBody, '280px', 'mailEdName'); ?>
        <textarea class="ib-code" id="ibCode" placeholder="<p>HTML kodunu buraya yapıştır...</p>"></textarea>
        <div style="display:flex;gap:.6rem;margin-top:1rem;flex-wrap:wrap">
            <button class="btn-save" type="submit" style="flex:2;min-width:160px"><i class="fas fa-paper-plane"></i> Gönder (<?= e2($transferName) ?>)</button>
            <a class="ib-act" href="<?= e2($U([], $baseRm)) ?>"><i class="fas fa-xmark"></i> Vazgeç</a>
        </div>
    </form>
</div>
</div>
<script>window.ibSignatureHtml = <?= $sigJson ?>;</script>

<div class="modal-overlay" id="ibAiModal" style="z-index:9500">
    <div class="modal-box" style="max-width:620px;width:94%">
        <button class="modal-close" id="ibAiClose" type="button"><i class="fas fa-xmark"></i></button>
        <div class="modal-title"><svg width="16" height="16" viewBox="0 0 200 200" style="vertical-align:-2px;color:#c9b8e8"><path d="M70 44q11 45 56 56-45 11-56 56-11-45-56-56 45-11 56-56m90-30q7 19 26 26-19 7-26 26-7-19-26-26 19-7 26-26m-10 120q7 19 26 26-19 7-26 26-7-19-26-26 19-7 26-26" fill="currentColor"/></svg> Vexaris AI <span id="ibAiModel" style="font-size:.7rem;color:var(--muted);font-weight:400"></span></div>
        <div class="form-field">
            <label>Ne yapsın? (ör: "iade talebine kibar ret yaz", "yazım hatalarını düzelt", "İngilizceye çevir")</label>
            <textarea id="ibAiPrompt" rows="3" placeholder="İsteğini yaz..." style="min-height:70px;resize:vertical"></textarea>
        </div>
        <label class="feat-label" style="margin-bottom:1rem"><input type="checkbox" id="ibAiCtx" checked> Editördeki mevcut metni bağlam olarak gönder</label>
        <div id="ibAiOut" style="display:none;background:#101318;border:1px solid var(--border);border-radius:10px;padding:.8rem 1rem;margin-bottom:1rem;max-height:38vh;overflow:auto">
            <div style="font-size:.72rem;font-weight:700;color:var(--muted);margin-bottom:.4rem">AI ÖNERİSİ (markdown)</div>
            <pre id="ibAiText" style="white-space:pre-wrap;font-family:monospace;font-size:.8rem;line-height:1.6;margin:0"></pre>
        </div>
        <div class="form-field" id="ibAiRevWrap" style="display:none">
            <label>Revize ettir</label>
            <div style="display:flex;gap:.5rem">
                <input type="text" id="ibAiRev" placeholder="ör: daha kısa yaz" style="flex:1">
                <button type="button" class="btn-primary" id="ibAiRevGo" style="white-space:nowrap">Revize</button>
            </div>
        </div>
        <div class="alert-msg" id="ibAiAlert"></div>
        <div class="modal-actions" style="flex-wrap:wrap">
            <button type="button" class="btn-save" id="ibAiGo"><i class="fas fa-wand-magic-sparkles"></i> Oluştur</button>
            <button type="button" class="btn-ghost" id="ibAiCopy" style="display:none"><i class="fas fa-copy"></i> Kopyala</button>
            <button type="button" class="btn-ghost" id="ibAiApply" style="display:none;border-color:rgba(148,178,150,.4);color:#a9c4ad"><i class="fas fa-check"></i> Editöre Uygula</button>
        </div>
    </div>
</div>
<?php else: ?>
<div class="ib-wrap <?= $sel ? 'has-sel' : '' ?>" id="ibWrap" data-spam-pending="<?= (int)$spamPending ?>" data-spam-enabled="<?= ib_cfg($pdo, 'mail_spam_enabled', '1') === '1' ? '1' : '0' ?>">
    <div class="ib-side">
        <div class="ib-side__head">
            <div class="ib-side__title"><i class="fas <?= ['inbox' => 'fa-inbox', 'sent' => 'fa-paper-plane', 'starred' => 'fa-star', 'trash' => 'fa-trash-can', 'spam' => 'fa-ban'][$folder] ?>"></i>
                <?= ['inbox' => 'Gelen Kutusu', 'sent' => 'Gönderilenler', 'starred' => 'Yıldızlı', 'trash' => 'Çöp Kutusu', 'spam' => 'Spam'][$folder] ?>
            </div>
            <span class="ib-side__count"><?= $listing['total'] ?> mail<?= $q !== '' ? ' • arama' : '' ?></span>
        </div>
        <form id="ibBulkForm" method="post" style="display:none">
            <?= csrf_field() ?>
            <input type="hidden" name="ib_act" value="bulk">
            <input type="hidden" name="back" value="<?= e2($ibBack) ?>">
        </form>
        <div class="ib-bulkbar">
            <label><input type="checkbox" id="ibSelAll"> Tümünü seç</label>
            <span class="ib-selinfo" id="ibSelInfo"></span>
            <button class="ib-bbtn" form="ibBulkForm" name="ib_bulk" value="read" type="submit"><i class="fas fa-envelope-open"></i> Okundu</button>
            <button class="ib-bbtn" form="ibBulkForm" name="ib_bulk" value="unread" type="submit"><i class="fas fa-envelope"></i> Okunmadı</button>
            <button class="ib-bbtn" form="ibBulkForm" name="ib_bulk" value="star" type="submit"><i class="fas fa-star"></i></button>
            <?php if ($folder === 'trash'): ?>
                <button class="ib-bbtn" form="ibBulkForm" name="ib_bulk" value="restore" type="submit"><i class="fas fa-rotate-left"></i> Geri</button>
                <button class="ib-bbtn danger" form="ibBulkForm" name="ib_bulk" value="del" type="submit"><i class="fas fa-trash"></i> Sil</button>
            <?php elseif ($folder === 'spam'): ?>
                <button class="ib-bbtn" form="ibBulkForm" name="ib_bulk" value="notspam" type="submit"><i class="fas fa-circle-check"></i> Spam değil</button>
                <button class="ib-bbtn danger" form="ibBulkForm" name="ib_bulk" value="del" type="submit"><i class="fas fa-trash"></i> Sil</button>
            <?php else: ?>
                <button class="ib-bbtn" form="ibBulkForm" name="ib_bulk" value="spam" type="submit"><i class="fas fa-ban"></i> Spam</button>
                <button class="ib-bbtn danger" form="ibBulkForm" name="ib_bulk" value="trash" type="submit"><i class="fas fa-trash-can"></i> Çöp</button>
            <?php endif; ?>
        </div>
        <div class="ib-list">
            <?php if (!$mails): ?>
                <div class="ib-empty"><i class="fas fa-inbox"></i>
                    <?php if ($q !== ''): ?>"<?= e2($q) ?>" için sonuç yok.
                    <?php elseif ($folder === 'starred'): ?>Yıldızlı mail yok.
                    <?php elseif ($folder === 'trash'): ?>Çöp kutusu boş.
                    <?php elseif ($folder === 'spam'): ?>Spam yok. Temiz! 🎉
                    <?php elseif ($folder === 'sent'): ?>Henüz gönderilmiş mail yok.
                    <?php else: ?>Henüz mail yok.<br><span style="font-size:.75rem">Worker aktif olunca mailler buraya düşer.</span><?php endif; ?>
                </div>
            <?php else: foreach ($mails as $m): $mb = $m['box']; ?>
                <div class="ib-item <?= $sel && $selBox === $mb && $selId === (int)$m['id'] ? 'on' : '' ?> <?= ($mb === 'in' && !(int)$m['is_read']) ? 'unread' : '' ?>">
                    <label class="ib-chk"><input type="checkbox" form="ibBulkForm" name="ids_<?= $mb ?>[]" value="<?= (int)$m['id'] ?>"></label>
                    <form method="post" class="ib-starf">
                        <?= csrf_field() ?>
                        <input type="hidden" name="ib_act" value="star">
                        <input type="hidden" name="box" value="<?= e2($mb) ?>">
                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                        <input type="hidden" name="back" value="<?= e2($ibBack) ?>">
                        <button type="submit" class="ib-starbtn <?= (int)$m['starred'] ? 'on' : '' ?>" title="Yıldız"><?= (int)$m['starred'] ? '★' : '☆' ?></button>
                    </form>
                    <a class="ib-item__link" href="<?= e2($U(['id' => (int)$m['id'], 'box' => $mb], ['reply', 'fwd', 'compose', 'set', 'done'])) ?>">
                        <div class="ib-top">
                            <span class="ib-from"><?= $mb === 'sent' ? ('Kime: ' . e2($m['to_addr'])) : e2(!empty($m['from_name']) ? $m['from_name'] : $m['from_addr']) ?></span>
                            <span class="ib-time" title="<?= e2($m['created_at']) ?>"><?= e2(ib_dt($m['created_at'])) ?></span>
                        </div>
                        <div class="ib-subj"><?= e2($m['subject'] !== '' ? $m['subject'] : '(konu yok)') ?></div>
                        <div class="ib-preview"><?= e2(mb_strimwidth(ib_text((string)($m['preview'] ?? '')), 0, 120, '…')) ?></div>
                    </a>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <?php if ($listing['pages'] > 1): $pg = $listing['page']; ?>
        <div class="ib-pagination">
            <?php if ($pg > 1): ?><a href="<?= e2($U(['page' => $pg - 1], ['id', 'box'])) ?>">‹</a><?php else: ?><span class="dis">‹</span><?php endif; ?>
            <?php for ($i = max(1, $pg - 2); $i <= min($listing['pages'], $pg + 2); $i++): ?>
                <?php if ($i === $pg): ?><span class="on"><?= $i ?></span><?php else: ?><a href="<?= e2($U(['page' => $i], ['id', 'box'])) ?>"><?= $i ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($pg < $listing['pages']): ?><a href="<?= e2($U(['page' => $pg + 1], ['id', 'box'])) ?>">›</a><?php else: ?><span class="dis">›</span><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="ib-main">
        <?php if (!$sel): ?>
            <div class="ib-empty"><i class="fas fa-envelope-open"></i><p>Listeden bir mail seç veya yeni mail yaz.</p>
                <div class="ib-box">
                    <b style="color:#fff"><i class="fas fa-plug"></i> Inbound worker kurulumu</b><br>
                    Webhook URL: <code>https://vexaris.net/api/inbound-mail.php</code><br>
                    Webhook anahtarını ve tüm posta ayarlarını sağ üstteki <i class="fas fa-gear"></i> düğmesinden yönetebilirsin.
                </div>
            </div>
        <?php else:
            $isTrash = (int)($sel['deleted'] ?? 0) === 1;
            $isSpam = (int)($sel['spam'] ?? 0) === 1;
            $hasHtml = !empty($sel['body_html']);
            $bodyClean = $hasHtml ? vx_clean_html((string)$sel['body_html'], 'rich') : '';
            if ($hasHtml) {
                $probe = (string)preg_replace('/<(style|script|title)[^>]*>.*?<\/\1>/is', ' ', $bodyClean);
                $probe = trim((string)preg_replace('/\s+/u', ' ', strip_tags($probe)));
                if ($probe === '' && stripos($bodyClean, '<img') === false) { $hasHtml = false; $bodyClean = ''; }
            }
            if ($selBox === 'sent') { $copyText = ib_text((string)($sel['body_html'] ?? '')); }
            else { $copyText = (string)($sel['body_text'] ?? ''); if ($copyText === '') { $copyText = ib_text((string)($sel['body_html'] ?? '')); } }
            $addrCopy = $selBox === 'sent' ? (string)$sel['to_addr'] : (string)$sel['from_addr'];
        ?>
            <div class="ib-readhead">
                <a class="ib-back" href="<?= e2($U([], ['id', 'box'])) ?>"><i class="fas fa-arrow-left"></i> Geri</a>
                <div class="ib-readhead__top">
                    <div class="ib-readhead__subj"><?= e2($sel['subject'] !== '' ? $sel['subject'] : '(konu yok)') ?></div>
                    <form method="post" class="ib-starf">
                        <?= csrf_field() ?>
                        <input type="hidden" name="ib_act" value="star">
                        <input type="hidden" name="box" value="<?= e2($selBox) ?>">
                        <input type="hidden" name="id" value="<?= (int)$sel['id'] ?>">
                        <input type="hidden" name="back" value="<?= e2($ibBack) ?>">
                        <button type="submit" class="ib-starbtn <?= (int)$sel['starred'] ? 'on' : '' ?>" style="height:34px;font-size:1.05rem" title="Yıldız"><?= (int)$sel['starred'] ? '★' : '☆' ?></button>
                    </form>
                </div>
                <div class="ib-readhead__meta">
                    <?php if ($selBox === 'sent'): ?>
                        Kime: <b><?= e2($sel['to_addr']) ?></b>
                    <?php else: ?>
                        Kimden: <b><?= e2(!empty($sel['from_name']) ? $sel['from_name'] . ' <' . $sel['from_addr'] . '>' : $sel['from_addr']) ?></b>
                        &nbsp;•&nbsp; Kime: <?= e2($sel['to_addr']) ?>
                    <?php endif; ?>
                    &nbsp;•&nbsp; <?= e2(ib_dt($sel['created_at'], true)) ?>
                    <?php if ($isTrash): ?>&nbsp;•&nbsp; <span style="color:#d79595">çöpte</span><?php endif; ?>
                    <?php if ($isSpam): ?>&nbsp;•&nbsp; <span style="color:#e08a8a"><i class="fas fa-ban"></i> spam</span><?php endif; ?>
                    <?php if (!empty($sel['replied_at'])): ?>&nbsp;•&nbsp; <span style="color:#a9c4ad"><i class="fas fa-reply"></i> yanıtlandı</span><?php endif; ?>
                </div>
                <div class="ib-viewtabs">
                    <?php if ($hasHtml): ?>
                    <button type="button" class="ib-viewbtn ib-viewtab on" data-v="html"><i class="fas fa-palette"></i> HTML</button>
                    <button type="button" class="ib-viewbtn ib-viewtab" data-v="text"><i class="fas fa-align-left"></i> Metin</button>
                    <?php endif; ?>
                    <button type="button" class="ib-viewbtn" data-ibcopy-from="#ibMailText"><i class="fas fa-copy"></i> Metni kopyala</button>
                    <button type="button" class="ib-viewbtn" data-ibcopy="<?= e2($addrCopy) ?>"><i class="fas fa-at"></i> Adresi kopyala</button>
                    <?php if ($hasHtml): ?><button type="button" class="ib-viewbtn" data-ibcopy-from="#ibMailHtml"><i class="fas fa-code"></i> HTML kopyala</button><?php endif; ?>
                    <?php if ($selBox === 'in'): ?><button type="button" class="ib-viewbtn" id="ibSpamScanBtn" data-id="<?= (int)$sel['id'] ?>"><i class="fas fa-shield-halved"></i> AI ile tara</button><?php endif; ?>
                    <button type="button" class="ib-viewbtn" id="ibTrBtn" data-id="<?= (int)$sel['id'] ?>" data-box="<?= e2($selBox) ?>"><i class="fas fa-language"></i> Çevir</button>
                </div>
            </div>
            <textarea id="ibMailText" class="ib-hidden"><?= e2($copyText) ?></textarea>
            <?php if ($hasHtml): ?><textarea id="ibMailHtml" class="ib-hidden"><?= e2((string)$sel['body_html']) ?></textarea><?php endif; ?>
            <?php if ($hasHtml): ?>
            <div class="ib-pane-html">
                <iframe class="ib-iframe" sandbox="" referrerpolicy="no-referrer" title="E-posta içeriği" srcdoc="<?= e2($bodyClean) ?>"></iframe>
                <div style="font-size:.7rem;color:var(--muted);padding:.4rem 1.3rem;border-top:1px solid var(--border)"><i class="fas fa-shield-halved"></i> HTML görünüm yalıtımlı kutuda açılır (betikler çalışmaz).</div>
            </div>
            <div class="ib-pane-text" style="display:none"><div class="ib-textpane"><?= e2($copyText !== '' ? $copyText : '(düz metin yok)') ?></div></div>
            <?php else: ?>
            <div class="ib-textpane"><?= e2($copyText !== '' ? $copyText : '(içerik yok)') ?></div>
            <?php endif; ?>
            <div class="ib-actions">
                <?php if (!$isTrash): ?>
                    <a class="ib-act pri" href="/admin?tab=inbox&reply=<?= (int)$sel['id'] ?>&box=<?= e2($selBox) ?>"><i class="fas fa-reply"></i> Yanıtla</a>
                    <a class="ib-act" href="/admin?tab=inbox&fwd=<?= (int)$sel['id'] ?>&box=<?= e2($selBox) ?>"><i class="fas fa-share"></i> İlet</a>
                    <?php if ($selBox === 'in'): ?>
                    <form method="post" style="display:inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="ib_act" value="<?= (int)$sel['is_read'] ? 'unread' : 'read' ?>">
                        <input type="hidden" name="id" value="<?= (int)$sel['id'] ?>">
                        <input type="hidden" name="back" value="<?= e2($ibBack) ?>">
                        <button type="submit" class="ib-act"><i class="fas <?= (int)$sel['is_read'] ? 'fa-envelope' : 'fa-envelope-open' ?>"></i> <?= (int)$sel['is_read'] ? 'Okunmadı' : 'Okundu' ?></button>
                    </form>
                    <form method="post" style="display:inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="ib_act" value="<?= $isSpam ? 'notspam' : 'spam' ?>">
                        <input type="hidden" name="box" value="in">
                        <input type="hidden" name="id" value="<?= (int)$sel['id'] ?>">
                        <input type="hidden" name="back" value="<?= e2($ibBack) ?>">
                        <button type="submit" class="ib-act <?= $isSpam ? '' : 'danger' ?>"><i class="fas fa-ban"></i> <?= $isSpam ? 'Spam değil' : 'Spam' ?></button>
                    </form>
                    <?php endif; ?>
                    <form method="post" style="display:inline;margin-left:auto">
                        <?= csrf_field() ?>
                        <input type="hidden" name="ib_act" value="trash">
                        <input type="hidden" name="box" value="<?= e2($selBox) ?>">
                        <input type="hidden" name="id" value="<?= (int)$sel['id'] ?>">
                        <input type="hidden" name="back" value="<?= e2($ibBack) ?>">
                        <button type="submit" class="ib-act danger"><i class="fas fa-trash-can"></i> Çöpe taşı</button>
                    </form>
                <?php else: ?>
                    <form method="post" style="display:inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="ib_act" value="restore">
                        <input type="hidden" name="box" value="<?= e2($selBox) ?>">
                        <input type="hidden" name="id" value="<?= (int)$sel['id'] ?>">
                        <input type="hidden" name="back" value="<?= e2($ibBack) ?>">
                        <button type="submit" class="ib-act pri"><i class="fas fa-rotate-left"></i> Geri yükle</button>
                    </form>
                    <form method="post" style="display:inline;margin-left:auto" onsubmit="return confirm('Bu mail KALICI olarak silinsin mi?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="ib_act" value="del">
                        <input type="hidden" name="box" value="<?= e2($selBox) ?>">
                        <input type="hidden" name="id" value="<?= (int)$sel['id'] ?>">
                        <input type="hidden" name="back" value="<?= e2($ibBack) ?>">
                        <button type="submit" class="ib-act danger"><i class="fas fa-trash"></i> Kalıcı sil</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php
$mailReadyS = ($mailCfg['mail_enabled'] ?? '0') === '1';
if ($transfer === 'brevo') { $mailReadyS = $mailReadyS && trim((string)($mailCfg['mail_brevo_api_key'] ?? '')) !== ''; }
elseif ($transfer === 'smtp') { $mailReadyS = $mailReadyS && trim((string)($mailCfg['mail_smtp_host'] ?? '')) !== ''; }
$sigNow = (string)ib_cfg($pdo, 'mail_signature', '');
$defFromNow = (string)ib_cfg($pdo, 'mail_default_from', $aliases[0]);
$spamEnabled = ib_cfg($pdo, 'mail_spam_enabled', '1') === '1';
$spamPromptDefault = 'Sen bir e-posta spam filtresisin. Sana verilen e-postayi incele ve SADECE "Yes" ya da "No" yaz. Reklam, phishing, dolandiricilik, istenmeyen toplu gonderim veya supheli ise "Yes"; gercek, kisisel veya mesru bir yazisma ise "No". Baska hicbir sey yazma.';
$spamPrompt = trim((string)ib_cfg($pdo, 'mail_spam_prompt', ''));
if ($spamPrompt === '') { $spamPrompt = $spamPromptDefault; }
$spamWordsRaw = trim((string)ib_cfg($pdo, 'mail_spam_words', ''));
$spamWordsText = $spamWordsRaw !== '' ? $spamWordsRaw : implode(', ', mail_spam_words_default());
$spamMaxLen = (int)ib_cfg($pdo, 'mail_spam_maxlen', '10000');
if ($spamMaxLen < 0) { $spamMaxLen = 10000; }
$spamEvrenOn = ib_cfg($pdo, 'mail_spam_evren', '0') === '1';
$spamThreshold = ib_cfg($pdo, 'mail_spam_threshold', '0.7');
if ($spamThreshold === '' || (float)$spamThreshold <= 0) { $spamThreshold = '0.7'; }
$evrenModel = ib_cfg($pdo, 'mail_spam_evren_model', 'clef');
if ($evrenModel === '') { $evrenModel = 'clef'; }
$evrenBase = ib_cfg($pdo, 'evren_llm_base_url', 'https://evren-llmapi.ssyz.org.tr');
$evrenKeySet = trim((string)ib_cfg($pdo, 'evren_llm_api_key', '')) !== '';
$evrenQuota = null;
if ($pdo) {
    $evFile = __DIR__ . '/../../core/evren.php';
    if (is_file($evFile)) require_once $evFile;
    if (function_exists('evren_quota')) { $evrenQuota = evren_quota($pdo); }
}
$aiProv = $pdo ? ai_active_provider($pdo) : null;
$aiModelName = $aiProv ? (string)($aiProv['model_id'] ?? '') : 'yapılandırılmamış';
?>
<div class="modal-overlay" id="ibSetModal" style="z-index:9400">
    <div class="modal-box ib-settingsbox" style="max-width:680px;width:94%">
        <button class="modal-close" id="ibSetClose" type="button"><i class="fas fa-xmark"></i></button>
        <div class="modal-title"><i class="fas fa-gear" style="color:var(--cyan)"></i> Posta Ayarları</div>
        <div class="ib-badges">
            <span class="ib-pill <?= $mailReadyS ? 'ok' : 'bad' ?>"><?= $mailReadyS ? 'Servis hazır' : 'Servis pasif/eksik' ?></span>
            <span class="ib-pill">Aktarım: <?= e2($transferName) ?></span>
            <span class="ib-pill"><?= count($aliases) ?> alias</span>
        </div>

        <div class="ib-sec">
            <h4><i class="fas fa-at"></i> Aliases (gönderen adresleri)</h4>
            <div class="ib-aliaslist">
                <?php foreach ($aliases as $a): ?>
                <span class="ib-aliaschip"><?= e2($a) ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="ib_act" value="alias_del">
                        <input type="hidden" name="ib_alias" value="<?= e2($a) ?>">
                        <input type="hidden" name="back" value="<?= e2($ibBack . '&set=1') ?>">
                        <button type="submit" title="Kaldır"><i class="fas fa-xmark"></i></button>
                    </form>
                </span>
                <?php endforeach; ?>
            </div>
            <form method="post" class="ib-addrow">
                <?= csrf_field() ?>
                <input type="hidden" name="ib_act" value="alias_add">
                <input type="hidden" name="back" value="<?= e2($ibBack . '&set=1') ?>">
                <input type="text" name="ib_alias" placeholder="destek  (veya destek@vexaris.net)" maxlength="60" required>
                <button type="submit" class="btn-primary" style="white-space:nowrap"><i class="fas fa-plus"></i> Alias ekle</button>
            </form>
            <div style="font-size:.72rem;color:var(--muted);margin-top:.5rem">Not: Buradaki adresler <b>gönderen</b> listesinde çıkar. Gelen postayı alabilmesi için sunucuda (Brevo/sendmail) aynı adrese yönlendirme tanımlı olmalı.</div>
        </div>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="ib_act" value="settings_save">
            <input type="hidden" name="back" value="<?= e2($ibBack . '&set=1') ?>">
            <div class="ib-sec">
                <h4><i class="fas fa-sliders"></i> Tercihler</h4>
                <div class="ib-setgrid">
                    <div class="form-field">
                        <label>Varsayılan gönderen</label>
                        <select name="ib_default_from">
                            <?php foreach ($aliases as $a): ?><option value="<?= e2($a) ?>" <?= $defFromNow === $a ? 'selected' : '' ?>><?= e2($a) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Sayfa başına mail</label>
                        <select name="ib_page_size">
                            <?php foreach ([25, 50, 100] as $n): ?><option value="<?= $n ?>" <?= (int)ib_cfg($pdo, 'mail_page_size', '25') === $n ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-field">
                    <label>İmza (HTML)</label>
                    <textarea name="ib_signature" rows="3" style="min-height:80px;font-family:monospace;font-size:.82rem" placeholder="&lt;p&gt;Ad Soyad&lt;br&gt;Vexaris&lt;/p&gt;"><?= e2($sigNow) ?></textarea>
                </div>
                <label class="feat-label" style="margin-bottom:.4rem"><input type="checkbox" name="ib_mark_read" value="1" <?= $markRead ? 'checked' : '' ?>> Mail açıldığında otomatik "okundu" işaretle</label>
                <div class="modal-actions"><button type="submit" class="btn-save"><i class="fas fa-floppy-disk"></i> Ayarları kaydet</button></div>
            </div>
        </form>

        <div class="ib-sec">
            <h4><i class="fas fa-shield-halved"></i> Spam filtresi (AI)</h4>
            <div style="font-size:.76rem;color:var(--muted);margin-bottom:.7rem">Gelen mailler aktif AI (<b style="color:var(--cyan)"><?= e2($aiModelName) ?></b>) tarafından okunur ve <b>Yes/No</b> ile spam kararı verilir. Ekstra model/anahtar gerekmez.</div>
            <div class="ib-badges" style="margin-bottom:.8rem">
                <span class="ib-pill <?= $spamEnabled ? 'ok' : 'bad' ?>"><?= $spamEnabled ? 'Filtre açık' : 'Filtre kapalı' ?></span>
                <span class="ib-pill"><?= (int)$spamPending ?> mail taranmayı bekliyor</span>
                <span class="ib-pill">Spam: <?= (int)$counts['spam'] ?></span>
            </div>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="ib_act" value="settings_save">
                <input type="hidden" name="back" value="<?= e2($ibBack . '&set=1') ?>">
                <input type="hidden" name="ib_default_from" value="<?= e2($defFromNow) ?>">
                <input type="hidden" name="ib_page_size" value="<?= (int)ib_cfg($pdo, 'mail_page_size', '25') ?>">
                <input type="hidden" name="ib_mark_read" value="<?= $markRead ? '1' : '' ?>">
                <input type="hidden" name="ib_signature" value="<?= e2($sigNow) ?>">
                <label class="feat-label" style="margin-bottom:.6rem"><input type="checkbox" name="ib_spam_enabled" value="1" <?= $spamEnabled ? 'checked' : '' ?>> AI spam filtresini etkinleştir</label>
                <div style="border:1px solid rgba(127,179,217,.22);border-radius:10px;padding:.7rem;margin-bottom:.7rem;background:rgba(127,179,217,.05)">
                    <label class="feat-label" style="margin-bottom:.5rem"><input type="checkbox" name="ib_spam_evren" value="1" <?= $spamEvrenOn ? 'checked' : '' ?>> EVREN (SSB) <b>clef</b> karar modeli ile tespit</label>
                    <div class="form-field">
                        <label>EVREN LLM API anahtarı</label>
                        <input type="password" name="ib_evren_key" value="" placeholder="<?= $evrenKeySet ? 'Kayıtlı — değiştirmek için yazın, boş bırakılırsa korunur' : 'evren_llm_… anahtarı' ?>" autocomplete="new-password">
                        <div style="font-size:.7rem;color:var(--muted);margin-top:.3rem">Boş bırakılırsa kayıtlı anahtar korunur. Anahtar şifreli saklanır; ortam değişkeni <code>EVREN_LLM_API_KEY</code> veya <code>/etc/vexaris/evren_llm.key</code> önceliklidir.</div>
                    </div>
                    <div class="form-field">
                        <label>Servis adresi</label>
                        <input type="text" name="ib_evren_base" value="<?= e2($evrenBase) ?>" placeholder="https://evren-llmapi.ssyz.org.tr">
                    </div>
                    <div class="form-field">
                        <label>Karar modeli</label>
                        <input type="text" name="ib_spam_evren_model" value="<?= e2($evrenModel) ?>" placeholder="clef">
                        <div style="font-size:.7rem;color:var(--muted);margin-top:.3rem"><code>clef</code> = metin üretmez, sorulara <b>olasılık</b> döner (noul/score/choice). <code>laya</code> toplu karar için kullanılır.</div>
                    </div>
                    <div class="form-field">
                        <label>Spam eşiği (0-1)</label>
                        <input type="number" name="ib_spam_threshold" min="0.05" max="0.99" step="0.05" value="<?= e2($spamThreshold) ?>">
                        <div style="font-size:.7rem;color:var(--muted);margin-top:.3rem">Bu eşiğin üstündeki olasılık spam sayılır. 0.70 önerilir (meşru banka/fatura bildirimleri için güvenli).</div>
                    </div>
                    <?php if (is_array($evrenQuota) && !empty($evrenQuota['ok'])): ?>
                    <div style="font-size:.7rem;color:var(--muted)">EVREN kotası: günlük <b><?= number_format((float)$evrenQuota['daily_remaining']) ?></b> token kaldı<?= (float)$evrenQuota['credits'] > 0 ? ' · kredi bakiyesi ' . number_format((float)$evrenQuota['credits'], 2) : '' ?>.</div>
                    <?php elseif ($evrenKeySet): ?>
                    <div style="font-size:.7rem;color:#f0a04b">EVREN kotası alınamadı (servis erişilemiyor olabilir) — tespit mevcut AI sağlayıcısına düşer.</div>
                    <?php endif; ?>
                </div>
                <div class="form-field">
                    <label>Sistem promptu</label>
                    <textarea name="ib_spam_prompt" rows="3" style="min-height:80px;font-size:.82rem"><?= e2($spamPrompt) ?></textarea>
                </div>
                <div class="ib-setgrid">
                    <div class="form-field">
                        <label>Uzun mail eşiği (karakter)</label>
                        <input type="number" name="ib_spam_maxlen" min="0" step="500" value="<?= (int)$spamMaxLen ?>">
                    </div>
                    <div class="form-field" style="justify-content:flex-end">
                        <div style="font-size:.72rem;color:var(--muted);padding-bottom:.5rem">Bu uzunluktan büyük mailler <b>modele gönderilmez</b> (0 = sınırsız).</div>
                    </div>
                </div>
                <div class="form-field">
                    <label>Yasaklı kelimeler (virgül veya satır ile ayır)</label>
                    <textarea name="ib_spam_words" rows="4" style="min-height:90px;font-size:.82rem"><?= e2($spamWordsText) ?></textarea>
                </div>
                <div style="font-size:.72rem;color:var(--muted);margin-bottom:.6rem"><i class="fas fa-shield-halved"></i> Bu kelimelerden biri geçerse mail <b>doğrudan spam</b> olur ve <b>modele hiç gönderilmez</b>. Türkçe karakterler/ayraçlar/leet (b.a.h.i.s, b4h1s) otomatik normalize edilir.</div>
                <div class="modal-actions" style="flex-wrap:wrap">
                    <button type="submit" class="btn-save"><i class="fas fa-floppy-disk"></i> Kaydet</button>
                    <button type="button" class="btn-ghost" id="ibSpamScanAllBtn"><i class="fas fa-wand-magic-sparkles"></i> Şimdi tara</button>
                </div>
            </form>
        </div>

        <div class="ib-sec">
            <h4><i class="fas fa-plug"></i> Inbound webhook</h4>
            <div style="font-size:.78rem;color:var(--muted);margin-bottom:.5rem">Worker URL: <code style="color:#a9b6cf">https://vexaris.net/api/inbound-mail.php</code></div>
            <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                <code style="color:#a9b6cf;word-break:break-all;flex:1;min-width:200px" id="ibSec"><?= e2($inSecret) ?></code>
                <button type="button" class="ib-minibtn" data-ibcopy-from="#ibSec"><i class="fas fa-copy"></i> Kopyala</button>
                <form method="post" onsubmit="return confirm('Webhook anahtarı yenilensin mi? Worker güncellenmeli.')" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="ib_act" value="regen">
                    <input type="hidden" name="back" value="<?= e2($ibBack . '&set=1') ?>">
                    <button type="submit" class="ib-minibtn"><i class="fas fa-rotate"></i> Yenile</button>
                </form>
            </div>
        </div>
    </div>
</div>
<textarea id="ibCopyBuf" class="ib-hidden" readonly></textarea>
<div class="ib-toast" id="ibToast"></div>
<div class="modal-overlay" id="ibTrModal" style="z-index:9450">
    <div class="modal-box" style="max-width:660px;width:94%">
        <button class="modal-close" id="ibTrClose" type="button"><i class="fas fa-xmark"></i></button>
        <div class="modal-title"><i class="fas fa-language" style="color:var(--cyan)"></i> Çeviri <span id="ibTrMeta" style="font-size:.72rem;color:var(--muted);font-weight:400"></span></div>
        <div class="ib-setrow">
            <select id="ibTrLang" class="ib-trl">
                <option value="tr" selected>Türkçe</option>
                <option value="en">İngilizce</option>
                <option value="de">Almanca</option>
                <option value="fr">Fransızca</option>
                <option value="es">İspanyolca</option>
                <option value="ru">Rusça</option>
                <option value="ar">Arapça</option>
                <option value="az">Azerice</option>
            </select>
            <button type="button" class="btn-primary" id="ibTrGo"><i class="fas fa-language"></i> Çevir</button>
            <button type="button" class="ib-minibtn" id="ibTrCopy" style="display:none"><i class="fas fa-copy"></i> Kopyala</button>
        </div>
        <div id="ibTrMsg" class="alert-msg"></div>
        <div id="ibTrOut" style="display:none;white-space:pre-wrap;background:#101318;border:1px solid var(--border);border-radius:10px;padding:.9rem 1rem;font-size:.88rem;line-height:1.65;max-height:52vh;overflow:auto;overflow-wrap:anywhere"></div>
        <div style="font-size:.7rem;color:var(--muted);margin-top:.6rem"><i class="fas fa-info-circle"></i> Google Translate üzerinden (resmi olmayan uç nokta) çevrilir; kısa sürede yanıt vermezse tekrar deneyin.</div>
    </div>
</div>

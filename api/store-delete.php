<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/mail.php';
boot_session();
header('Content-Type: application/json; charset=utf-8');
if (!is_logged_in()) { echo json_encode(['ok' => false, 'msg' => 'Login required']); exit; }
$pdo = db();
if (!$pdo) { echo json_encode(['ok' => false, 'msg' => 'Database error']); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok' => false, 'msg' => 'POST required']); exit; }
csrf_check();

$u = current_user();
$uid = (int)($u['id'] ?? 0);
$uemail = strtolower(trim((string)($u['email'] ?? '')));
$uname = (string)($u['username'] ?? '');
$act = (string)($_POST['action'] ?? '');
$ip = function_exists('client_ip') ? (string)client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
$noreply = ['email' => setting_get('mail_noreply_email', 'noreply@vexaris.net'), 'name' => 'Vexaris'];

function sdc_owned(PDO $pdo, int $sid, int $uid, string $uemail): ?array {
    $st = $pdo->prepare("SELECT id, slug, domain, status, logo_url FROM hosted_stores WHERE id=? AND (user_id=? OR owner=?) AND status='active' LIMIT 1");
    $st->execute([$sid, $uid, $uemail]);
    $r = $st->fetch();
    return $r ?: null;
}

if ($act === 'send') {
    if (!api_rate_limit($pdo, 'sdc_send', $ip !== '' ? $ip : '0.0.0.0', 6, 900)) {
        echo json_encode(['ok' => false, 'msg' => 'Too many requests. Please try again later.']); exit;
    }
    $sid = (int)($_POST['store_id'] ?? 0);
    $store = sdc_owned($pdo, $sid, $uid, $uemail);
    if (!$store) { echo json_encode(['ok' => false, 'msg' => 'Store not found.']); exit; }
    if ($uemail === '' || !filter_var($uemail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'msg' => 'Your account has no valid email address.']); exit;
    }
    try {
        $pdo->prepare("DELETE FROM store_delete_codes WHERE user_id=? AND (expires_at < NOW() OR created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR))")->execute([$uid]);
        $st = $pdo->prepare("SELECT * FROM store_delete_codes WHERE user_id=? AND store_id=? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $st->execute([$uid, $sid]);
        $row = $st->fetch();
        $sends = $row ? (int)$row['sends'] : 0;
        $lastSent = $row ? strtotime((string)$row['last_sent_at']) : 0;
        if ($sends >= 2) {
            echo json_encode(['ok' => false, 'msg' => 'No more codes for this request. Close and start again later.', 'locked' => true]); exit;
        }
        if ($sends === 1) {
            $wait = 300 - (time() - $lastSent);
            if ($wait > 0) {
                echo json_encode(['ok' => false, 'msg' => 'Please wait ' . (int)ceil($wait / 60) . ' more minute(s) before requesting another code.', 'wait' => $wait]); exit;
            }
        }
        $code = (string)random_int(100000, 999999);
        if ($row) {
            $pdo->prepare("UPDATE store_delete_codes SET code_hash=?, attempts=0, sends=sends+1, last_sent_at=NOW(), expires_at=DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id=?")
                ->execute([password_hash($code, PASSWORD_BCRYPT), (int)$row['id']]);
        } else {
            $pdo->prepare("INSERT INTO store_delete_codes (user_id, store_id, slug, code_hash, attempts, sends, last_sent_at, expires_at) VALUES (?,?,?,?,0,1,NOW(),DATE_ADD(NOW(), INTERVAL 10 MINUTE))")
                ->execute([$uid, $sid, (string)$store['slug'], password_hash($code, PASSWORD_BCRYPT)]);
        }
        $cells = '';
        foreach (str_split($code) as $d) {
            $cells .= '<td align="center" width="46" style="background-color:#0b0d10;border:1px solid #e05d5d;border-radius:10px;">'
                . '<div style="color:#ffffff;font-size:24px;font-weight:800;padding:12px 0;">' . $d . '</div></td>'
                . '<td width="8" style="font-size:0;line-height:0;">&nbsp;</td>';
        }
        $body = '<p style="margin:0 0 14px 0;color:#e9e7e2;font-size:15px;">Hello {user_name},</p>'
            . '<p style="margin:0 0 18px 0;color:#9aa3ac;font-size:14px;line-height:1.7;">You asked to permanently delete your store <strong style="color:#e9e7e2;">' . htmlspecialchars((string)$store['slug'], ENT_QUOTES) . '.vexaris.app</strong>. Enter the <strong style="color:#e9e7e2;">6-digit code</strong> below to confirm. The code expires in <strong style="color:#e9e7e2;">10 minutes</strong>.</p>'
            . '<table border="0" cellpadding="0" cellspacing="0" align="center" style="margin:0 auto 18px auto;"><tr>' . $cells . '</tr></table>'
            . '<p style="margin:0;color:#9aa3ac;font-size:13px;line-height:1.7;">Deletion is permanent: products, orders and uploaded files are removed. If you did not request this, ignore this email and change your password.</p>';
        $ok = mail_send($pdo, $uemail, 'Your store deletion code | Vexaris', $body, ['user_name' => $uname], $noreply);
        if (!$ok) {
            echo json_encode(['ok' => false, 'msg' => 'Could not send the code. Please try again later.']); exit;
        }
        echo json_encode(['ok' => true, 'msg' => 'Code sent to ' . $uemail, 'masked' => preg_replace('/^(.).*(@.*)$/', '$1***$2', $uemail), 'expires_in' => 600, 'sends' => $sends + 1]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => 'Server error.']);
    }
    exit;
}

if ($act === 'verify') {
    if (!api_rate_limit($pdo, 'sdc_verify', $ip !== '' ? $ip : '0.0.0.0', 20, 900)) {
        echo json_encode(['ok' => false, 'msg' => 'Too many attempts. Please try again later.']); exit;
    }
    $sid = (int)($_POST['store_id'] ?? 0);
    $code = preg_replace('/[^0-9]/', '', (string)($_POST['code'] ?? ''));
    if (strlen($code) !== 6) { echo json_encode(['ok' => false, 'msg' => 'Enter the 6-digit code.']); exit; }
    $store = sdc_owned($pdo, $sid, $uid, $uemail);
    if (!$store) { echo json_encode(['ok' => false, 'msg' => 'Store not found.']); exit; }
    try {
        $st = $pdo->prepare("SELECT * FROM store_delete_codes WHERE user_id=? AND store_id=? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $st->execute([$uid, $sid]);
        $row = $st->fetch();
        if (!$row) { echo json_encode(['ok' => false, 'msg' => 'The code expired. Request a new one.']); exit; }
        if ((int)$row['attempts'] >= 5) { echo json_encode(['ok' => false, 'msg' => 'Too many wrong codes. Request a new one.']); exit; }
        if (!password_verify($code, (string)$row['code_hash'])) {
            $pdo->prepare("UPDATE store_delete_codes SET attempts=attempts+1 WHERE id=?")->execute([(int)$row['id']]);
            $left = max(0, 5 - ((int)$row['attempts'] + 1));
            echo json_encode(['ok' => false, 'msg' => 'Incorrect code. ' . $left . ' attempt(s) left.']); exit;
        }
        $pdo->prepare("DELETE FROM store_delete_codes WHERE user_id=? AND store_id=?")->execute([$uid, $sid]);
        $stD = $pdo->prepare("SELECT id FROM hosting_jobs WHERE slug=? AND kind='delete' AND status IN ('pending','claimed') LIMIT 1");
        $stD->execute([(string)$store['slug']]);
        if ($stD->fetch()) { echo json_encode(['ok' => true, 'job_id' => 0, 'queued' => true, 'slug' => (string)$store['slug']]); exit; }
        $pdo->prepare("INSERT INTO hosting_jobs (user_id, slug, plan, owner, site_name, lang, kind, status) VALUES (?,?,'free',?,'','en','delete','pending')")
            ->execute([$uid, (string)$store['slug'], $uemail]);
        $jid = (int)$pdo->lastInsertId();
        if (function_exists('audit_log')) {
            try { audit_log($pdo, $uid, 'hosting_delete_request', 'hosting', (string)$store['slug'], 'code verified'); } catch (Throwable $e3) {}
        }
        echo json_encode(['ok' => true, 'job_id' => $jid, 'slug' => (string)$store['slug']]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => 'Server error.']);
    }
    exit;
}

if ($act === 'status') {
    $jid = (int)($_POST['job_id'] ?? 0);
    if ($jid <= 0) { echo json_encode(['ok' => false]); exit; }
    try {
        $st = $pdo->prepare("SELECT status, last_msg FROM hosting_jobs WHERE id=? AND user_id=? LIMIT 1");
        $st->execute([$jid, $uid]);
        $row = $st->fetch();
        if (!$row) { echo json_encode(['ok' => false]); exit; }
        echo json_encode(['ok' => true, 'status' => (string)$row['status'], 'message' => (string)($row['last_msg'] ?? '')]);
    } catch (Throwable $e) { echo json_encode(['ok' => false]); }
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Unknown action']);

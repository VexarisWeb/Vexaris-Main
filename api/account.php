<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/mail.php';
boot_session();
header('Content-Type: application/json; charset=utf-8');
if (!is_logged_in()) { echo json_encode(['ok'=>false,'msg'=>'Login required']); exit; }
$pdo = db();
if (!$pdo) { echo json_encode(['ok'=>false,'msg'=>'Database error']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'msg'=>'POST required']); exit; }
csrf_check();
$act = $_POST['action'] ?? '';
$uid = (int)current_user()['id'];

if ($act === 'request_email_change') {
    if (!api_rate_limit($pdo, 'emailchg', client_ip(), 5, 3600)) { echo json_encode(['ok'=>false,'msg'=>'Too many requests. Please try again later.']); exit; }
    $new = strtolower(trim($_POST['new_email'] ?? ''));
    if (!filter_var($new, FILTER_VALIDATE_EMAIL)) { echo json_encode(['ok'=>false,'msg'=>'Invalid email address']); exit; }
    $st = $pdo->prepare("SELECT id FROM users WHERE email=? AND id<>? LIMIT 1");
    $st->execute([$new,$uid]);
    if ($st->fetch()) { echo json_encode(['ok'=>false,'msg'=>'This email is already in use']); exit; }
    // Kademeli tekrar gönder: 1. tekrar 60 sn sonra, 2. tekrar 5 dk sonra, sonra dur
    try {
        $pdo->prepare("DELETE FROM email_change_tokens WHERE user_id=? AND created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)")->execute([$uid]);
        $stC = $pdo->prepare("SELECT created_at FROM email_change_tokens WHERE user_id=? ORDER BY id DESC");
        $stC->execute([$uid]);
        $times = $stC->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $n = count($times);
        if ($n >= 3) {
            echo json_encode(['ok'=>false,'msg'=>'Too many code requests. Please try again later.']);
            exit;
        }
        if ($n > 0) {
            $gap = ($n === 1) ? 60 : 300;
            $wait = $gap - (time() - strtotime((string)$times[0]));
            if ($wait > 0) {
                $wmsg = $wait >= 60 ? ('about ' . (int)ceil($wait / 60) . ' minutes') : ($wait . ' seconds');
                echo json_encode(['ok'=>false,'msg'=>'Please wait ' . $wmsg . ' for a new code.']);
                exit;
            }
        }
    } catch (Throwable $e) {}
    if (!mail_is_configured($pdo)) {
        $curPw = (string)($_POST['current_password'] ?? $_POST['current'] ?? '');
        if ($curPw === '') {
            echo json_encode(['ok'=>false,'msg'=>'Current password is required to change email while email verification is disabled.']);
            exit;
        }
        $stPw = $pdo->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
        $stPw->execute([$uid]);
        if (!password_verify($curPw, (string)($stPw->fetchColumn()))) {
            echo json_encode(['ok'=>false,'msg'=>'Current password is incorrect']);
            exit;
        }
        $pdo->prepare("UPDATE users SET email=? WHERE id=?")->execute([$new,$uid]);
        $_SESSION['user']['email']=$new;
        echo json_encode(['ok'=>true,'msg'=>'Email updated directly (email verification is disabled).','popup'=>true]);
        exit;
    }
    $token = bin2hex(random_bytes(16));
    // Not: geçmiş satırlar kademeli sayaç için korunur (verify başarılı olunca temizlenir)
    $pdo->prepare("INSERT INTO email_change_tokens (user_id,new_email,token,expires_at) VALUES (?,?,?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))")->execute([$uid,$new,$token]);
    $ok = mail_send($pdo, $new, 'Email Verification — Vexaris', '<p>Your verification code: <strong style="font-size:18px;letter-spacing:2px">'.$token.'</strong></p><p>Please confirm it in your account settings within 30 minutes.</p>');
    if (!$ok) { echo json_encode(['ok'=>false,'msg'=>'Could not send the email. Please try again later.']); exit; }
    echo json_encode(['ok'=>true,'msg'=>'Verification code sent. Please check your inbox.','popup'=>true]);
    exit;
}
if ($act === 'verify_email_change') {
    $token = trim($_POST['token'] ?? '');
    if ($token==='') { echo json_encode(['ok'=>false,'msg'=>'Code is required']); exit; }
    $st = $pdo->prepare("SELECT new_email FROM email_change_tokens WHERE user_id=? AND token=? AND expires_at > NOW() LIMIT 1");
    $st->execute([$uid,$token]);
    $row = $st->fetch();
    if (!$row) { echo json_encode(['ok'=>false,'msg'=>'Code is invalid or has expired']); exit; }
    $new = $row['new_email'];
    $stD = $pdo->prepare("SELECT id FROM users WHERE email=? AND id<>? LIMIT 1");
    $stD->execute([$new,$uid]);
    if ($stD->fetch()) { echo json_encode(['ok'=>false,'msg'=>'This email is already in use']); exit; }
    try {
        $pdo->prepare("UPDATE users SET email=? WHERE id=?")->execute([$new,$uid]);
    } catch (PDOException $eDup) {
        if ((string)$eDup->getCode() === '23000') {
            echo json_encode(['ok'=>false,'msg'=>'This email is already in use']);
            exit;
        }
        echo json_encode(['ok'=>false,'msg'=>'Could not update email. Please try again later.']);
        exit;
    }
    $pdo->prepare("DELETE FROM email_change_tokens WHERE user_id=?")->execute([$uid]);
    $_SESSION['user']['email'] = $new;
    echo json_encode(['ok'=>true,'msg'=>'Email updated: '.$new]);
    exit;
}
if ($act === 'unlink_oauth') {
    $p = (string)($_POST['provider'] ?? '');
    if (!in_array($p, ['google', 'discord', 'github'], true)) { echo json_encode(['ok'=>false,'msg'=>'Invalid provider']); exit; }
    $pdo->prepare("DELETE FROM oauth_accounts WHERE user_id=? AND provider=?")->execute([$uid,$p]);
    echo json_encode(['ok'=>true,'msg'=>'Social account connection removed']);
    exit;
}
if ($act === 'change_password') {
    if (!api_rate_limit($pdo, 'chpass', client_ip(), 5, 300)) { echo json_encode(['ok'=>false,'msg'=>'Too many attempts. Please try again later.']); exit; }
    $cur = $_POST['current'] ?? '';
    $nw = $_POST['new'] ?? '';
    if (strlen($nw) < 8) { echo json_encode(['ok'=>false,'msg'=>'New password must be at least 8 characters']); exit; }
    if (!preg_match('/[A-Z]/', $nw) || !preg_match('/[a-z]/', $nw) || !preg_match('/[0-9]/', $nw)) { echo json_encode(['ok'=>false,'msg'=>'New password must include uppercase, lowercase and a number']); exit; }
    $st = $pdo->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
    $st->execute([$uid]);
    $hash = (string)$st->fetchColumn();
    if (!password_verify($cur, $hash)) { echo json_encode(['ok'=>false,'msg'=>'Current password is incorrect']); exit; }
    $nh = password_hash($nw, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$nh,$uid]);
    // Güvenlik: şifre değişince tüm oturumlar her yerde kapatılır (bu oturum hariç yaşatılır)
    $newVer = sessions_kill_all($pdo, $uid, true);
    $_SESSION['user']['sess_ver'] = $newVer;
    try {
        $pdo->prepare("DELETE FROM password_resets WHERE user_id=?")->execute([$uid]);
    } catch (Throwable $e2) {}
    echo json_encode(['ok'=>true,'msg'=>'Password updated. Sessions on other devices have been signed out.']);
    exit;
}
if ($act === 'sessions_list') {
    try {
        $pdo->prepare("DELETE FROM user_sessions WHERE user_id=? AND last_seen < DATE_SUB(NOW(), INTERVAL 30 DAY)")->execute([$uid]);
    } catch (Throwable $e) {}
    $st = $pdo->prepare("SELECT session_key, ip, user_agent, device_label, device_hash, created_at, last_seen FROM user_sessions WHERE user_id=? AND revoked=0 ORDER BY last_seen DESC");
    $st->execute([$uid]);
    $rows = $st->fetchAll();
    $cur = (string)session_id();
    $groups = [];
    foreach ($rows as $r) {
        $lbl = trim((string)($r['device_label'] ?? ''));
        if ($lbl === '') $lbl = device_parse((string)($r['user_agent'] ?? ''))['label'];
        $dh = trim((string)($r['device_hash'] ?? ''));
        $dkey = $dh !== '' ? $dh : ('l:' . md5($lbl));
        $created = (string)($r['created_at'] ?? '');
        $seen = (string)($r['last_seen'] ?? '');
        if (!isset($groups[$dkey])) {
            $groups[$dkey] = ['key' => $dkey, 'device' => $lbl, 'mobile' => (stripos($lbl, 'Android') !== false || stripos($lbl, 'iOS') !== false), 'ip' => ($r['ip'] ?: '—'), 'started' => $created, 'last_seen' => $seen, 'current' => false];
        }
        if ($r['session_key'] === $cur) $groups[$dkey]['current'] = true;
        if (strtotime($created) < strtotime((string)$groups[$dkey]['started'])) $groups[$dkey]['started'] = $created;
        if (strtotime($seen) >= strtotime((string)$groups[$dkey]['last_seen'])) { $groups[$dkey]['last_seen'] = $seen; $groups[$dkey]['ip'] = $r['ip'] ?: $groups[$dkey]['ip']; }
    }
    $out = [];
    foreach ($groups as $g) {
        $out[] = [
            'current'   => $g['current'],
            'device'    => $g['device'],
            'mobile'    => $g['mobile'],
            'ip'        => $g['ip'],
            'started'   => date('d.m.Y H:i', strtotime((string)$g['started'])),
            'last_seen' => date('d.m.Y H:i', strtotime((string)$g['last_seen'])),
            'key'       => $g['key'],
        ];
    }
    usort($out, function ($a, $b) { return ($a['current'] ? 0 : 1) <=> ($b['current'] ? 0 : 1); });
    echo json_encode(['ok' => true, 'sessions' => $out]);
    exit;
}
if ($act === 'sessions_terminate') {
    $key = trim((string)($_POST['key'] ?? ''));
    $cur = (string)session_id();
    if ($key === '') { echo json_encode(['ok' => false, 'msg' => 'Invalid request.']); exit; }
    if (preg_match('/^[a-f0-9]{64}$/', $key)) {
        $pdo->prepare("UPDATE user_sessions SET revoked=1 WHERE user_id=? AND device_hash=? AND session_key<>?")->execute([$uid, $key, $cur]);
    } else {
        if ($key === $cur) { echo json_encode(['ok' => false, 'msg' => 'Invalid request.']); exit; }
        $pdo->prepare("UPDATE user_sessions SET revoked=1 WHERE user_id=? AND session_key=?")->execute([$uid, $key]);
    }
    echo json_encode(['ok' => true, 'msg' => 'Session signed out.']);
    exit;
}
if ($act === 'sessions_terminate_all') {
    $newVer = sessions_kill_all($pdo, $uid, true);
    $_SESSION['user']['sess_ver'] = $newVer;
    echo json_encode(['ok' => true, 'msg' => 'Other sessions signed out.']);
    exit;
}
echo json_encode(['ok'=>false,'msg'=>'Unknown action']);

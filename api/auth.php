<?php
// Herkese açık kimlik işlemleri: şifre sıfırlama (6 haneli kod).
// NOT: Test amaçlı mail gönderilmez; akış yalnızca gerçek kullanıcı isteğiyle çalışır.
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/mail.php';
require_once __DIR__ . '/../core/mail-templates.php';
require_once __DIR__ . '/../core/turnstile.php';
boot_session();
header('Content-Type: application/json; charset=utf-8');

$pdo = db();
if (!$pdo) {
    echo json_encode(['ok' => false, 'msg' => 'Could not connect to the database.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'POST only.']);
    exit;
}
csrf_check();

if (setting_get('password_reset_enabled', '1') !== '1') {
    echo json_encode(['ok' => false, 'msg' => 'Password reset is currently disabled.']);
    exit;
}

// IP bazlı hız sınırı (15 dk pencerede temizlenir, dakikada 10 istek)
$ip = client_ip();
try {
    $pdo->prepare("DELETE FROM api_rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)")->execute();
    $c = $pdo->prepare("SELECT COUNT(*) FROM api_rate_limits WHERE ip = ? AND scope = 'pwreset' AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
    $c->execute([$ip !== '' ? $ip : '0.0.0.0']);
    if ((int)$c->fetchColumn() >= 10) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'msg' => 'Too many requests. Please wait a minute.']);
        exit;
    }
    $pdo->prepare("INSERT INTO api_rate_limits (ip, scope) VALUES (?,'pwreset')")->execute([$ip !== '' ? $ip : '0.0.0.0']);
} catch (Throwable $e) {}

$action = (string)($_POST['action'] ?? '');
$noreply = ['email' => setting_get('mail_noreply_email', 'noreply@vexaris.net'), 'name' => 'Vexaris'];

function pw_active_row(PDO $pdo, int $uid): ?array {
    $st = $pdo->prepare("SELECT * FROM password_resets WHERE user_id = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $st->execute([$uid]);
    $r = $st->fetch();
    return $r ?: null;
}

try {
    // ── 1) Kod iste ──────────────────────────────────────────
    if ($action === 'request_reset') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['ok' => false, 'msg' => 'Please enter a valid email address.']);
            exit;
        }
        if (turnstile_enabled() && !turnstile_verify($_POST['cf-turnstile-response'] ?? $_POST['cf_token'] ?? '', client_ip() ?: null)) {
            echo json_encode(['ok' => false, 'msg' => 'Security verification failed.']);
            exit;
        }
        $st = $pdo->prepare("SELECT id, username FROM users WHERE email = ? LIMIT 1");
        $st->execute([$email]);
        $user = $st->fetch();
        // Hesap yoksa da aynı mesaj (kullanıcı sayımı engelleme)
        if (!$user) {
            echo json_encode(['ok' => true, 'msg' => 'If an account exists, a verification code has been sent.']);
            exit;
        }
        $uid = (int)$user['id'];
        $row = pw_active_row($pdo, $uid);
        $sends = $row ? (int)$row['sends'] : 0;
        $lastSent = $row ? strtotime((string)$row['last_sent_at']) : 0;
        $wait = 0;
        if ($sends >= 3) {
            echo json_encode(['ok' => false, 'msg' => 'Too many code requests. Please try again later.']);
            exit;
        } elseif ($sends === 1) {
            $wait = 60 - (time() - $lastSent);          // 2. gönderim: 60 sn bekle
        } elseif ($sends === 2) {
            $wait = 5 * 60 - (time() - $lastSent);      // 3. gönderim: 5 dk bekle
        }
        if ($wait > 0) {
            $m = (int)ceil($wait / 60);
            echo json_encode(['ok' => false, 'msg' => 'Please wait about ' . $m . ' minutes for a new code.']);
            exit;
        }
        $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$uid]);
        $code = (string)random_int(100000, 999999);
        $pdo->prepare("INSERT INTO password_resets (user_id, email, code_hash, attempts, sends, last_sent_at, expires_at) VALUES (?,?,?,?,?,NOW(),DATE_ADD(NOW(), INTERVAL 15 MINUTE))")
            ->execute([$uid, $email, password_hash($code, PASSWORD_BCRYPT), 0, $sends + 1]);
        [$subj, $body] = mail_tpl_reset($code);
        $ok = mail_send($pdo, $email, $subj, $body, ['user_name' => $user['username']], $noreply);
        if (!$ok) {
            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$uid]);
            echo json_encode(['ok' => false, 'msg' => 'Could not send the code. Please try again later.']);
            exit;
        }
        echo json_encode(['ok' => true, 'msg' => 'Verification code sent.']);
        exit;
    }

    // ── 2) Kodu doğrula ──────────────────────────────────────
    if ($action === 'verify_reset') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $code = preg_replace('/[^0-9]/', '', (string)($_POST['code'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($code) !== 6) {
            echo json_encode(['ok' => false, 'msg' => 'Email and 6-digit code are required.']);
            exit;
        }
        $st = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $st->execute([$email]);
        $uid = (int)$st->fetchColumn();
        if ($uid <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Code is incorrect or has expired.']);
            exit;
        }
        $row = pw_active_row($pdo, $uid);
        if (!$row) {
            echo json_encode(['ok' => false, 'msg' => 'Code is incorrect or has expired.']);
            exit;
        }
        if ((int)$row['attempts'] >= 5) {
            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$uid]);
            echo json_encode(['ok' => false, 'msg' => 'Too many incorrect attempts. Please request a new code.']);
            exit;
        }
        if (!password_verify($code, (string)$row['code_hash'])) {
            $pdo->prepare("UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?")->execute([(int)$row['id']]);
            echo json_encode(['ok' => false, 'msg' => 'Incorrect code.']);
            exit;
        }
        $token = bin2hex(random_bytes(32));
        $pdo->prepare("UPDATE password_resets SET reset_token_hash = ?, reset_expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?")
            ->execute([hash('sha256', $token), (int)$row['id']]);
        echo json_encode(['ok' => true, 'msg' => 'Code verified.', 'token' => $token]);
        exit;
    }

    // ── 3) Yeni şifreyi kaydet ───────────────────────────────
    if ($action === 'reset_password') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $token = trim((string)($_POST['token'] ?? ''));
        $nw = (string)($_POST['new'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $token === '' || strlen($nw) < 6) {
            echo json_encode(['ok' => false, 'msg' => 'Invalid request.']);
            exit;
        }
        $st = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $st->execute([$email]);
        $uid = (int)$st->fetchColumn();
        $row = $uid > 0 ? pw_active_row($pdo, $uid) : null;
        if (!$row || empty($row['reset_token_hash']) || empty($row['reset_expires_at'])
            || strtotime((string)$row['reset_expires_at']) < time()
            || !hash_equals((string)$row['reset_token_hash'], hash('sha256', $token))) {
            echo json_encode(['ok' => false, 'msg' => 'Session is invalid or has expired. Please start over.']);
            exit;
        }
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($nw, PASSWORD_BCRYPT), $uid]);
        sessions_kill_all($pdo, $uid, false); // tüm oturumlar + hatırlama kapatılır
        $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$uid]);
        echo json_encode(['ok' => true, 'msg' => 'Your password has been updated. You can now sign in.']);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Unknown action.']);
} catch (Throwable $e) {
    error_log('auth API: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Server error.']);
}

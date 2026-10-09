<?php
// Hesap aktivasyon bağlantısı: /activate?token=...
require_once __DIR__ . '/../core/db.php';
boot_session();

$token = trim((string)($_GET['token'] ?? ''));
$ok = false;
if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
    try {
        $pdo = db();
        if ($pdo) {
            $st = $pdo->prepare("SELECT user_id FROM activation_tokens WHERE token_hash = ? AND expires_at > NOW() LIMIT 1");
            $st->execute([hash('sha256', $token)]);
            $uid = (int)$st->fetchColumn();
            if ($uid > 0) {
                $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?")->execute([$uid]);
                $pdo->prepare("DELETE FROM activation_tokens WHERE user_id = ?")->execute([$uid]);
                $ok = true;
            }
        }
    } catch (Throwable $e) { $ok = false; }
}
header('Location: /login' . ($ok ? '?activated=1' : '?err=activation'));
exit;

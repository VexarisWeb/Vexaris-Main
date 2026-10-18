<?php
require_once __DIR__ . '/../core/db.php';
boot_session();
if (!empty($_COOKIE['remember_token'])) remember_forget((string)$_COOKIE['remember_token']);
try {
    $pdo = db();
    $sk = (string)session_id();
    if ($pdo && $sk !== '') {
        $st = $pdo->prepare("SELECT user_id, device_hash FROM user_sessions WHERE session_key = ? LIMIT 1");
        $st->execute([$sk]);
        $srow = $st->fetch();
        if ($srow && !empty($srow['device_hash'])) {
            $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ? AND device_hash = ?")->execute([(int)$srow['user_id'], (string)$srow['device_hash']]);
        } else {
            $pdo->prepare("DELETE FROM user_sessions WHERE session_key = ?")->execute([$sk]);
        }
    }
} catch (Throwable $e) {}
$_SESSION = [];
session_destroy();
header('Location: /');
exit;

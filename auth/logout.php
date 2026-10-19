<?php
require_once __DIR__ . '/../core/db.php';
boot_session();
if (!empty($_COOKIE['remember_token'])) remember_forget((string)$_COOKIE['remember_token']);
try {
    $pdo = db();
    $sk = (string)session_id();
    $uid = (int)($_SESSION['user']['id'] ?? 0);
    if ($pdo && $sk !== '') {
        if ($uid <= 0) {
            $st = $pdo->prepare("SELECT user_id FROM user_sessions WHERE session_key = ? LIMIT 1");
            $st->execute([$sk]);
            $uid = (int)$st->fetchColumn();
        }
        $pdo->prepare("DELETE FROM user_sessions WHERE session_key = ?")->execute([$sk]);
    }
} catch (Throwable $e) {}
$_SESSION = [];
session_destroy();
header('Location: /');
exit;

<?php
require_once __DIR__ . '/../core/db.php';
boot_session();
if (!empty($_COOKIE['remember_token'])) remember_forget((string)$_COOKIE['remember_token']);
try {
    $pdo = db();
    $sk = (string)session_id();
    if ($pdo && $sk !== '') {
        $pdo->prepare("DELETE FROM user_sessions WHERE session_key = ?")->execute([$sk]);
    }
} catch (Throwable $e) {}
$_SESSION = [];
session_destroy();
header('Location: /');
exit;

<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/oauth.php';
boot_session();
$p = (string)($_GET['provider'] ?? '');
$act = (string)($_GET['action'] ?? '');
$all = oauth_providers();
if (!isset($all[$p])) {
    header('Location: /login?err=social');
    exit;
}
if ($act === 'login') {
    if (!oauth_is_configured($p)) {
        header('Location: ' . (is_logged_in() ? '/dashboard/account?err=disabled' : '/login?err=disabled'));
        exit;
    }
    if (is_logged_in()) {
        header('Location: ' . oauth_login_url($p, '/dashboard/account'));
        exit;
    }
    $next = (string)($_GET['next'] ?? '');
    if (!preg_match('#^/(?![/\\\\%5c])#i', $next)) $next = '';
    header('Location: ' . oauth_login_url($p, $next));
    exit;
}
if ($act === 'callback') {
    if (!oauth_is_configured($p)) {
        header('Location: ' . (is_logged_in() ? '/dashboard/account?err=disabled' : '/login?err=disabled'));
        exit;
    }
    $r = oauth_finish($p);
    if (isset($r['target'])) {
        header('Location: ' . $r['target']);
        exit;
    }
    $code = (string)($r['error'] ?? 'social');
    if ($code === 'banned') {
        header('Location: /banned');
        exit;
    }
    if ($code !== 'taken' && $code !== 'domain' && $code !== 'iplimit') $code = 'social';
    if ($code === 'domain') {
        header('Location: ' . (is_logged_in() ? '/dashboard/account?err=domain' : '/login?err=domain'));
        exit;
    }
    if ($code === 'iplimit') {
        header('Location: /login?err=iplimit');
        exit;
    }
    header('Location: ' . (is_logged_in() ? '/dashboard/account?err=' . $code : '/login?err=social'));
    exit;
}
header('Location: /login?err=social');

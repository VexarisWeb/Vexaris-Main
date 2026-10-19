<?php
// POST /api/license-domains — kendi lisansina domain ekle/cikar (login gerekli)
require_once __DIR__ . '/../core/db.php';
boot_session();

if (!is_logged_in()) {
    header('Location: /login?next=/dashboard/license');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard/license');
    exit;
}
csrf_check();

$uid = (int)current_user()['id'];
$pdo = db();
if (!$pdo) {
    header('Location: /dashboard/license?err=fail');
    exit;
}

function lic_clean_domain(string $d): string {
    $d = strtolower(trim($d));
    $d = preg_replace('#^https?://#i', '', $d);
    $d = rtrim($d, '/');
    if (str_starts_with($d, '*.')) $d = substr($d, 2);
    if ($d === '' || strlen($d) > 253 || strpos($d, '.') === false) return '';
    if (preg_match('/[^a-z0-9.\-]/', $d)) return '';
    foreach (explode('.', $d) as $label) {
        if ($label === '' || strlen($label) > 63) return '';
        if ($label[0] === '-' || substr($label, -1) === '-') return '';
    }
    return $d;
}

function lic_platform_domain(string $d): bool {
    foreach (['vexaris.app', 'vexaris.net', 'vexaris.com.tr'] as $p) {
        if ($d === $p || substr($d, -(strlen($p) + 1)) === '.' . $p) return true;
    }
    return false;
}

try {
    $st = $pdo->prepare("SELECT id, allowed_domains FROM licenses WHERE user_id=? ORDER BY id ASC LIMIT 1");
    $st->execute([$uid]);
    $lic = $st->fetch();
    if (!$lic) {
        header('Location: /dashboard/license?err=fail');
        exit;
    }
    $domains = json_decode((string)$lic['allowed_domains'], true);
    if (!is_array($domains)) $domains = [];
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'claim') {
        $key = trim((string)($_POST['license_key'] ?? ''));
        if ($key === '' || strlen($key) > 64) {
            header('Location: /dashboard/license?err=invalid');
            exit;
        }
        $cq = $pdo->prepare("SELECT id FROM licenses WHERE license_key=? AND user_id IS NULL LIMIT 1");
        $cq->execute([$key]);
        $cid = (int)$cq->fetchColumn();
        if ($cid <= 0) {
            header('Location: /dashboard/license?err=notfound');
            exit;
        }
        $pdo->prepare("UPDATE licenses SET user_id=? WHERE id=? AND user_id IS NULL")->execute([$uid, $cid]);
        header('Location: /dashboard/license?ok=claimed');
        exit;
    }
    $dom = lic_clean_domain((string)($_POST['domain'] ?? ''));
    if ($act === 'add') {
        if ($dom === '' || strpos(strtolower(trim((string)($_POST['domain'] ?? ''))), '*') !== false) {
            header('Location: /dashboard/license?err=invalid');
            exit;
        }
        if (lic_platform_domain($dom)) {
            header('Location: /dashboard/license?err=invalid');
            exit;
        }
        if (in_array($dom, $domains, true)) {
            header('Location: /dashboard/license?err=exists');
            exit;
        }
        if (count($domains) >= 10) {
            header('Location: /dashboard/license?err=limit');
            exit;
        }
        $ck = $pdo->prepare("SELECT id FROM licenses WHERE allowed_domains LIKE ? AND id<>? LIMIT 1");
        $ck->execute(['%"' . $dom . '"%', (int)$lic['id']]);
        if ($ck->fetch()) {
            header('Location: /dashboard/license?err=exists');
            exit;
        }
        $domains[] = $dom;
        $pdo->prepare("UPDATE licenses SET allowed_domains=? WHERE id=?")->execute([json_encode(array_values($domains)), (int)$lic['id']]);
        header('Location: /dashboard/license?ok=added');
        exit;
    }
    if ($act === 'remove') {
        if ($dom === '') {
            header('Location: /dashboard/license?err=fail');
            exit;
        }
        $domWild = '*.' . $dom;
        if (!in_array($dom, $domains, true) && !in_array($domWild, $domains, true)) {
            header('Location: /dashboard/license?err=fail');
            exit;
        }
        $domains = array_values(array_filter($domains, fn($x) => $x !== $dom && $x !== $domWild));
        $pdo->prepare("UPDATE licenses SET allowed_domains=? WHERE id=?")->execute([json_encode($domains), (int)$lic['id']]);
        header('Location: /dashboard/license?ok=removed');
        exit;
    }
} catch (Throwable $e) {
    error_log('license-domains: ' . $e->getMessage());
}
header('Location: /dashboard/license?err=fail');
exit;

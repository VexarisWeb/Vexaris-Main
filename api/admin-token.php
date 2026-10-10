<?php
require_once __DIR__ . '/../core/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = db();
if (!$pdo) { http_response_code(503); echo json_encode(['ok' => false]); exit; }

$action = strtolower(trim((string)($_POST['action'] ?? '')));

if ($action === 'issue') {
    boot_session();
    require_login();
    $me = current_user();
    if ((int)($me['is_admin'] ?? 0) !== 1) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'msg' => 'admin only']);
        exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false]);
        exit;
    }
    csrf_check();
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim((string)($_POST['slug'] ?? ''))));
    if ($slug === '') {
        echo json_encode(['ok' => false, 'msg' => 'bad slug']);
        exit;
    }
    try {
        $st = $pdo->prepare("SELECT id FROM hosted_stores WHERE slug=? LIMIT 1");
        $st->execute([$slug]);
        if (!$st->fetch()) {
            echo json_encode(['ok' => false, 'msg' => 'not found']);
            exit;
        }
        $token = bin2hex(random_bytes(32));
        $pdo->prepare("INSERT INTO store_tokens (token, slug, admin_id, used) VALUES (?,?,?,0)")->execute([$token, $slug, (int)$me['id']]);
        echo json_encode(['ok' => true, 'url' => 'https://' . $slug . '.vexaris.net/shop/admin-login.php?token=' . $token]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['ok' => false]);
    }
    exit;
}

if ($action === 'consume') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false]);
        exit;
    }
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (function_exists('hosting_ratelimit') && !hosting_ratelimit($pdo, $ip, 'admintoken', 20)) {
        http_response_code(429);
        echo json_encode(['ok' => false]);
        exit;
    }
    $token = strtolower(trim((string)($_POST['token'] ?? '')));
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        echo json_encode(['ok' => false]);
        exit;
    }
    try {
        $st = $pdo->prepare("SELECT slug, admin_id, used, created_at FROM store_tokens WHERE token=? LIMIT 1");
        $st->execute([$token]);
        $row = $st->fetch();
        if (!$row || (int)$row['used'] !== 0) {
            echo json_encode(['ok' => false]);
            exit;
        }
        if (strtotime((string)$row['created_at']) < time() - 600) {
            $pdo->prepare("UPDATE store_tokens SET used=1 WHERE token=?")->execute([$token]);
            echo json_encode(['ok' => false]);
            exit;
        }
        $up = $pdo->prepare("UPDATE store_tokens SET used=1 WHERE token=? AND used=0");
        $up->execute([$token]);
        if ($up->rowCount() === 0) {
            echo json_encode(['ok' => false]);
            exit;
        }
        echo json_encode(['ok' => true, 'slug' => (string)$row['slug']]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['ok' => false]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false]);

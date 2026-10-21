<?php
require_once __DIR__ . '/../core/db.php';
boot_session();
header('Content-Type: application/json; charset=utf-8');

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Yetkisiz erişim.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Yalnızca POST.']);
    exit;
}

csrf_check();

$pdo    = demo_db();
$action = $_POST['action'] ?? '';

if (!$pdo) {
    echo json_encode(['ok' => false, 'msg' => 'Demo veritabanına bağlanılamadı.']);
    exit;
}

try {

    if ($action === 'get_state') {
        $st = $pdo->query("SELECT `value` FROM settings WHERE `key`='demo_post_enabled' LIMIT 1");
        $v  = $st->fetchColumn();
        echo json_encode(['ok' => true, 'demo_post_enabled' => $v === false ? '0' : (string)$v]);
        exit;
    }

    if ($action === 'set_demo_post') {
        $val = ($_POST['demo_post_enabled'] ?? '') === '1' ? '1' : '0';
        $exists = $pdo->prepare("SELECT `key` FROM settings WHERE `key`='demo_post_enabled' LIMIT 1");
        $exists->execute();
        if ($exists->fetch()) {
            $pdo->prepare("UPDATE settings SET `value`=? WHERE `key`='demo_post_enabled'")->execute([$val]);
        } else {
            $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES ('demo_post_enabled',?)")->execute([$val]);
        }
        echo json_encode(['ok' => true, 'msg' => $val === '1' ? 'Demo POST işlemleri açıldı.' : 'Demo POST işlemleri kapatıldı.', 'demo_post_enabled' => $val]);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Bilinmeyen işlem.']);

} catch (Throwable $e) {
    error_log($e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Sunucu hatası oluştu.']);
}

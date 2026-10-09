<?php
require_once __DIR__ . '/../core/legal.php';
boot_session();
header('Content-Type: application/json; charset=utf-8');

if (!has_permission('legal')) {
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

$action = $_POST['action'] ?? '';

if ($action === 'legal_save') {
    $slug    = trim($_POST['slug'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if (!legal_slug_valid($slug)) {
        echo json_encode(['ok' => false, 'msg' => 'Geçersiz sayfa.']);
        exit;
    }
    legal_save($slug, $content);
    echo json_encode(['ok' => true, 'msg' => 'Sayfa kaydedildi.']);
    exit;
}

if ($action === 'legal_reset') {
    $slug = trim($_POST['slug'] ?? '');
    if (!legal_slug_valid($slug)) {
        echo json_encode(['ok' => false, 'msg' => 'Geçersiz sayfa.']);
        exit;
    }
    legal_save($slug, '');
    echo json_encode(['ok' => true, 'msg' => 'Varsayılan içeriğe dönüldü.']);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Bilinmeyen işlem.']);

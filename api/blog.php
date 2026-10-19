<?php
require_once __DIR__ . '/../core/blog.php';
require_once __DIR__ . '/../core/seo.php';
boot_session();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Yalnızca POST.']);
    exit;
}

if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    echo json_encode(['ok' => false, 'msg' => 'Veri sunucuya ulaşmadı. İçerik çok büyük olabilir: resimleri dosya olarak gömmek yerine URL ile ekleyin veya kapak görseli yüklemeyi kullanın.']);
    exit;
}

csrf_check();

$action = $_POST['action'] ?? '';

if ($action === 'blog_view_leave') {
    $pdo = db();
    if (!$pdo) {
        echo json_encode(['ok' => false, 'msg' => 'Veritabanı bağlantısı kurulamadı.']);
        exit;
    }
    $viewId = (int)($_POST['view_id'] ?? 0);
    $duration = (int)($_POST['duration'] ?? 0);
    if ($duration < 0) $duration = 0;
    if ($duration > 7200) $duration = 7200;
    blog_view_end($pdo, $viewId, $duration);
    echo json_encode(['ok' => true]);
    exit;
}

if (!has_permission('blog')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Yetkisiz erişim.']);
    exit;
}

$pdo = db();
if (!$pdo) {
    echo json_encode(['ok' => false, 'msg' => 'Veritabanı bağlantısı kurulamadı.']);
    exit;
}

if ($action === 'blog_save') {
    $res = blog_save($pdo, $_POST);
    echo json_encode($res);
    exit;
}

if ($action === 'blog_delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0 || !blog_delete($pdo, $id)) {
        echo json_encode(['ok' => false, 'msg' => 'Yazı silinemedi.']);
        exit;
    }
    echo json_encode(['ok' => true, 'msg' => 'Yazı silindi.']);
    exit;
}

if ($action === 'blog_cover_upload') {
    if (empty($_FILES['cover']['name'])) {
        echo json_encode(['ok' => false, 'msg' => 'Lütfen bir görsel seçin.']);
        exit;
    }
    $res = blog_cover_upload($_FILES['cover']);
    echo json_encode($res);
    exit;
}

if ($action === 'blog_seo_save') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0 || !blog_get_by_id($pdo, $id)) {
        echo json_encode(['ok' => false, 'msg' => 'Yazı bulunamadı.']);
        exit;
    }
    $res = blog_seo_save($pdo, $id, $_POST);
    echo json_encode($res);
    exit;
}

if ($action === 'blog_seo_reset') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0 || !blog_get_by_id($pdo, $id)) {
        echo json_encode(['ok' => false, 'msg' => 'Yazı bulunamadı.']);
        exit;
    }
    $res = blog_seo_reset($pdo, $id);
    echo json_encode($res);
    exit;
}

if ($action === 'blog_stats') {
    $id = (int)($_POST['id'] ?? 0);
    $post = blog_get_by_id($pdo, $id);
    if (!$post) {
        echo json_encode(['ok' => false, 'msg' => 'Yazı bulunamadı.']);
        exit;
    }
    $stats = blog_stats($pdo, $id);
    echo json_encode(['ok' => true, 'stats' => $stats, 'post' => $post]);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Bilinmeyen işlem.']);
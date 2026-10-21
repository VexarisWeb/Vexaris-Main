<?php
require_once __DIR__ . '/../core/db.php';
boot_session();
require_admin();

header('Content-Type: application/json; charset=utf-8');

$pdo = db();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Veritabanı bağlantısı kurulamadı.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$allResources = [
    'dashboard'   => 'Dashboard',
    'users'       => 'Kullanıcılar',
    'tickets'     => 'Destek Talepleri',
    'products'    => 'Ürünler',
    'blog'        => 'Blog',
    'seo'         => 'SEO Yönetimi',
    'licenses'    => 'Lisanslar',
    'versions'    => 'Versiyonlar',
    'demo'        => 'Demo Yönetimi',
    'settings'    => 'Ayarlar',
    'legal'       => 'Yasal Sayfalar',
    'permissions' => 'Rol & Yetkiler',
];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    try {
        $roles = [];
        foreach (ROLES as $r) {
            $st = $pdo->prepare("SELECT resource FROM permissions WHERE role = ?");
            $st->execute([$r]);
            $roles[$r] = $st->fetchAll(PDO::FETCH_COLUMN);
        }
        echo json_encode(['ok' => true, 'roles' => $roles, 'resources' => $allResources]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => 'Sorgu hatası.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Geçersiz istek.']);
    exit;
}

csrf_check();

if (user_role() !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'İzinleri yalnızca admin düzenleyebilir.']);
    exit;
}

$role = trim($_POST['role'] ?? '');
if (!in_array($role, ROLES, true) || $role === 'admin') {
    echo json_encode(['ok' => false, 'msg' => 'Geçersiz rol.']);
    exit;
}

$resources = array_map('trim', (array)($_POST['resources'] ?? []));
$resources = array_intersect($resources, array_keys($allResources));

try {
    $pdo->prepare("DELETE FROM permissions WHERE role = ?")->execute([$role]);
    $ins = $pdo->prepare("INSERT IGNORE INTO permissions (role, resource) VALUES (?,?)");
    foreach ($resources as $res) {
        $ins->execute([$role, $res]);
    }
    echo json_encode(['ok' => true, 'msg' => role_label($role) . ' rolü için izinler güncellendi.']);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'msg' => 'İzinler güncellenemedi.']);
}

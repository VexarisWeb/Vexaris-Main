<?php
require_once __DIR__ . '/../core/db.php';
boot_session();
require_admin();
log_pageview('admin');

$pdo = db();

$tab = $_GET['tab'] ?? 'dashboard';
$tabPermissions = [
    'dashboard'   => 'dashboard',
    'users'       => 'users',
    'products'    => 'products',
    'licenses'    => 'licenses',
    'versions'    => 'versions',
    'demo'        => 'demo',
    'settings'    => 'settings',
    'legal'       => 'legal',
    'blog'        => 'blog',
    'seo'         => 'seo',
    'tickets'     => 'tickets',
    'permissions' => 'permissions',
    'api'         => 'settings',
];
$allowedTabs = array_keys($tabPermissions);
if (!in_array($tab, $allowedTabs, true)) $tab = 'dashboard';
if (!has_permission($tabPermissions[$tab] ?? '')) $tab = 'dashboard';

$pageTitles = [
    'dashboard'   => 'Dashboard',
    'users'       => 'Kullanıcılar',
    'products'    => 'Ürünler',
    'licenses'    => 'Lisanslar',
    'versions'    => 'Versiyonlar',
    'demo'        => 'Demo Yönetimi',
    'settings'    => 'Ayarlar',
    'legal'       => 'Yasal Sayfalar',
    'blog'        => 'Blog',
    'seo'         => 'SEO Yönetimi',
    'tickets'     => 'Destek Talepleri',
    'permissions' => 'Rol & Yetkiler',
    'api'         => 'API & AI',
];
$pageTitle = $pageTitles[$tab] ?? 'Admin Panel';

require_once __DIR__ . '/_inc/header.php';

$viewFile = __DIR__ . '/views/' . $tab . '.php';
if (is_file($viewFile)) {
    require $viewFile;
} else {
    echo '<div class="text-muted" style="text-align:center;padding:3rem">Sayfa bulunamadı.</div>';
}

require_once __DIR__ . '/_inc/footer.php';
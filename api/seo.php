<?php
require_once __DIR__ . '/../core/seo.php';
require_once __DIR__ . '/../core/blog.php';
boot_session();
header('Content-Type: application/json; charset=utf-8');

if (!has_permission('seo')) {
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

$pdo = db();
if (!$pdo) {
    echo json_encode(['ok' => false, 'msg' => 'Veritabanı bağlantısı kurulamadı.']);
    exit;
}

$action = $_POST['action'] ?? '';
$target = trim((string)($_POST['target'] ?? ''));

$systemPages = [
    'home'    => ['label' => 'Ana Sayfa',            'title' => 'Vexaris - E-Ticaret Platformu',       'description' => 'Vexaris - E-ticaret platformu. 35+ modül, güçlü yönetim paneli ve SEO dostu mimari ile işinizi büyütün.'],
    'features' => ['label' => 'Özellikler',           'title' => 'Özellikler - Vexaris',                'description' => 'Vexaris özellikleri: ürün ve stok yönetimi, kuponlar, otomatik teslimat, lisans anahtarları ve 7 ödeme entegrasyonu.'],
    'pricing'  => ['label' => 'Fiyatlandırma',        'title' => 'Pricing - Vexaris',                 'description' => 'Vexaris pricing: Free, Starter ($6.99), Professional ($14.99) and Enterprise ($39.99) plans compared in detail.'],
    'blog'    => ['label' => 'Blog Listesi',         'title' => 'Blog - Vexaris',                      'description' => 'Vexaris blog - e-ticaret, yazılım ve dijital ticaret üzerine güncel yazılar, rehberler ve ipuçları.'],
    'license_check' => ['label' => 'Lisans Sorgula', 'title' => 'Lisans Sorgula - Vexaris',            'description' => 'Vexaris lisans durumunuzu sorgulayın ve doğrulayın.'],
    'legal_terms'   => ['label' => 'Kullanım Şartları', 'title' => 'Kullanım Şartları - Vexaris',      'description' => 'Vexaris kullanım şartları ve sözleşme hükümleri.'],
    'legal_privacy' => ['label' => 'Gizlilik Politikası', 'title' => 'Gizlilik Politikası - Vexaris',  'description' => 'Vexaris gizlilik politikası - verilerinizin nasıl işlendiği hakkında bilgi.'],
    'legal_cookie'  => ['label' => 'Çerez Politikası', 'title' => 'Çerez Politikası - Vexaris',        'description' => 'Vexaris çerez politikası - çerezlerin kullanımı hakkında bilgi.'],
];

if ($action === 'seo_targets') {
    $targets = ['system' => [], 'blog' => []];
    foreach ($systemPages as $k => $v) {
        $targets['system'][] = ['id' => $k, 'label' => $v['label']];
    }
    $st = $pdo->query("SELECT id, title, slug, status FROM blog_posts ORDER BY created_at DESC LIMIT 500");
    foreach ($st->fetchAll() as $r) {
        $targets['blog'][] = ['id' => 'blog:' . $r['id'], 'label' => $r['title'] . ($r['status'] !== 'published' ? ' (taslak)' : '')];
    }
    echo json_encode(['ok' => true, 'targets' => $targets]);
    exit;
}

if ($action === 'seo_load') {
    if ($target === '' || strpos($target, 'blog:') === 0) {
        $id = (int)substr($target, 5);
        $post = blog_get_by_id($pdo, $id);
        if (!$post) {
            echo json_encode(['ok' => false, 'msg' => 'Yazı bulunamadı.']);
            exit;
        }
        $fallback = ['title' => $post['title'], 'description' => $post['excerpt'] ?? ''];
        $seo = seo_page_build($pdo, 'blog:' . $id, $fallback, $post);
        echo json_encode(['ok' => true, 'seo' => $seo, 'fallback' => $fallback]);
        exit;
    }
    if (!isset($systemPages[$target])) {
        echo json_encode(['ok' => false, 'msg' => 'Geçersiz sayfa.']);
        exit;
    }
    $fallback = ['title' => $systemPages[$target]['title'], 'description' => $systemPages[$target]['description']];
    $seo = seo_page_build($pdo, $target, $fallback);
    echo json_encode(['ok' => true, 'seo' => $seo, 'fallback' => $fallback]);
    exit;
}

if ($action === 'seo_save') {
    if ($target === '' || strpos($target, 'blog:') === 0) {
        $id = (int)substr($target, 5);
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Geçersiz hedef.']);
            exit;
        }
        $res = blog_seo_save($pdo, $id, $_POST);
        echo json_encode($res);
        exit;
    }
    if (!isset($systemPages[$target])) {
        echo json_encode(['ok' => false, 'msg' => 'Geçersiz sayfa.']);
        exit;
    }
    $res = seo_page_save($pdo, $target, $_POST);
    echo json_encode($res);
    exit;
}

if ($action === 'seo_reset') {
    if ($target === '' || strpos($target, 'blog:') === 0) {
        $id = (int)substr($target, 5);
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Geçersiz hedef.']);
            exit;
        }
        $res = blog_seo_reset($pdo, $id);
        echo json_encode($res);
        exit;
    }
    if (!isset($systemPages[$target])) {
        echo json_encode(['ok' => false, 'msg' => 'Geçersiz sayfa.']);
        exit;
    }
    $res = seo_page_reset($pdo, $target);
    echo json_encode($res);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Bilinmeyen işlem.']);
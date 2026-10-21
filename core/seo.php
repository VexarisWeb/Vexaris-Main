<?php
require_once __DIR__ . '/db.php';

function seo_page_build(PDO $pdo, string $key, array $fallback, ?array $post = null): array {
    $def = [
        'key'         => $key,
        'title'       => $fallback['title'] ?? 'Vexaris - E-Ticaret Platformu',
        'description' => $fallback['description'] ?? '',
        'keywords'    => '',
        'canonical'   => '',
        'noindex'     => 0,
        'og_title'    => '',
        'og_description' => '',
        'og_image'    => '',
        'published'   => '',
        'author'      => '',
        'slug'        => '',
    ];
    if ($post !== null) {
        $def['title']       = trim((string)($post['meta_title'] ?? '')) !== '' ? $post['meta_title'] : ($post['title'] ?? $def['title']);
        $def['description'] = trim((string)($post['meta_description'] ?? '')) !== '' ? $post['meta_description'] : ($post['excerpt'] ?? $def['description']);
        $def['keywords']    = trim((string)($post['keywords'] ?? ''));
        $def['canonical']   = trim((string)($post['canonical'] ?? ''));
        $def['noindex']     = (int)($post['noindex'] ?? 0);
        $def['og_title']    = trim((string)($post['og_title'] ?? '')) !== '' ? $post['og_title'] : $def['title'];
        $def['og_description'] = trim((string)($post['og_description'] ?? '')) !== '' ? $post['og_description'] : $def['description'];
        $def['og_image']    = trim((string)($post['og_image'] ?? '')) !== '' ? $post['og_image'] : ($post['cover_image'] ?? '');
        $def['published']   = (string)($post['created_at'] ?? '');
        $def['author']      = (string)($post['author_name'] ?? '');
        $def['slug']        = (string)($post['slug'] ?? '');
        return $def;
    }
    $prefix = 'seo_' . $key . '_';
    $st = $pdo->prepare("SELECT `key`,`value` FROM settings WHERE `key` LIKE ?");
    $st->execute([$prefix . '%']);
    foreach ($st->fetchAll() as $row) {
        $k = substr($row['key'], strlen($prefix));
        if (isset($def[$k])) {
            $def[$k] = $k === 'noindex' ? (int)$row['value'] : (string)$row['value'];
        }
    }
    $def['og_title']       = $def['og_title'] !== '' ? $def['og_title'] : $def['title'];
    $def['og_description'] = $def['og_description'] !== '' ? $def['og_description'] : $def['description'];
    return $def;
}

function seo_page_save(PDO $pdo, string $key, array $d): array {
    $key = preg_replace('/[^a-z0-9_\-]/i', '', $key);
    if ($key === '') return ['ok' => false, 'msg' => 'Geçersiz SEO anahtarı.'];
    $fields = ['title','description','keywords','canonical','noindex','og_title','og_description','og_image'];
    $vals = [];
    foreach ($fields as $f) {
        if ($f === 'noindex') {
            $vals[$f] = isset($d[$f]) && (int)$d[$f] === 1 ? '1' : '0';
        } else {
            $vals[$f] = mb_substr(trim((string)($d[$f] ?? '')), 0, 300, 'UTF-8');
        }
    }
    try {
        $st = $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        foreach ($vals as $f => $v) {
            $st->execute(['seo_' . $key . '_' . $f, $v]);
        }
        return ['ok' => true, 'msg' => 'SEO bilgileri kaydedildi.'];
    } catch (Exception $e) {
        error_log('seo_page_save hatası: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Kaydetme sırasında bir hata oluştu.'];
    }
}

function seo_page_reset(PDO $pdo, string $key): array {
    $key = preg_replace('/[^a-z0-9_\-]/i', '', $key);
    if ($key === '') return ['ok' => false, 'msg' => 'Geçersiz SEO anahtarı.'];
    try {
        $pdo->prepare("DELETE FROM settings WHERE `key` LIKE ?")->execute(['seo_' . $key . '_%']);
        return ['ok' => true, 'msg' => 'Varsayılan SEO bilgileri geri yüklendi.'];
    } catch (Exception $e) {
        error_log('seo_page_reset hatası: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Sıfırlama sırasında bir hata oluştu.'];
    }
}

function blog_seo_save(PDO $pdo, int $id, array $d): array {
    $vals = [
        'meta_title'       => mb_substr(trim((string)($d['title'] ?? '')), 0, 190, 'UTF-8'),
        'meta_description' => mb_substr(trim((string)($d['description'] ?? '')), 0, 300, 'UTF-8'),
        'keywords'         => mb_substr(trim((string)($d['keywords'] ?? '')), 0, 255, 'UTF-8'),
        'canonical'        => mb_substr(trim((string)($d['canonical'] ?? '')), 0, 255, 'UTF-8'),
        'noindex'          => isset($d['noindex']) && (int)$d['noindex'] === 1 ? 1 : 0,
        'og_title'         => mb_substr(trim((string)($d['og_title'] ?? '')), 0, 190, 'UTF-8'),
        'og_description'   => mb_substr(trim((string)($d['og_description'] ?? '')), 0, 300, 'UTF-8'),
        'og_image'         => mb_substr(trim((string)($d['og_image'] ?? '')), 0, 255, 'UTF-8'),
    ];
    try {
        $st = $pdo->prepare("UPDATE blog_posts SET meta_title=?, meta_description=?, keywords=?, canonical=?, noindex=?, og_title=?, og_description=?, og_image=? WHERE id=?");
        $st->execute([$vals['meta_title'] ?: null, $vals['meta_description'] ?: null, $vals['keywords'] ?: null, $vals['canonical'] ?: null, $vals['noindex'], $vals['og_title'] ?: null, $vals['og_description'] ?: null, $vals['og_image'] ?: null, $id]);
        return ['ok' => true, 'msg' => 'SEO bilgileri kaydedildi.'];
    } catch (Exception $e) {
        error_log('blog_seo_save hatası: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Kaydetme sırasında bir hata oluştu.'];
    }
}

function blog_seo_reset(PDO $pdo, int $id): array {
    try {
        $st = $pdo->prepare("UPDATE blog_posts SET meta_title=NULL, meta_description=NULL, keywords=NULL, canonical=NULL, noindex=0, og_title=NULL, og_description=NULL, og_image=NULL WHERE id=?");
        $st->execute([$id]);
        return ['ok' => true, 'msg' => 'Yazı SEO bilgileri varsayılana döndürüldü.'];
    } catch (Exception $e) {
        error_log('blog_seo_reset hatası: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Sıfırlama sırasında bir hata oluştu.'];
    }
}

function seo_render_head(array $seo): void {
    $site = 'Vexaris';
    $title = trim($seo['title'] ?? '');
    if ($title === '') $title = $site;
    $desc = trim($seo['description'] ?? '');
    $kw   = trim($seo['keywords'] ?? '');
    $can  = trim($seo['canonical'] ?? '');
    $noindex = (int)($seo['noindex'] ?? 0) === 1;
    $ogT  = trim($seo['og_title'] ?? '') !== '' ? $seo['og_title'] : $title;
    $ogD  = trim($seo['og_description'] ?? '') !== '' ? $seo['og_description'] : $desc;
    $ogI  = trim($seo['og_image'] ?? '');
    $key  = (string)($seo['key'] ?? '');

    /* Kanonik URL: sabit SITE_URL + yol. Host başlığına ve sorgu
       string'ine güvenilmez (canonical/og:url zehirleme + duplicate içerik). */
    $base = defined('SITE_URL') ? rtrim((string)SITE_URL, '/') : 'https://vexaris.net';
    $path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    $path = '/' . trim($path, '/');
    if ($path === '/') $path = '/';
    $selfUrl = $base . $path;
    if ($can !== '') {
        if (preg_match('#^https?://#i', $can)) {
            $canonUrl = $can;
        } else {
            $canonUrl = $base . '/' . ltrim($can, '/');
        }
    } else {
        $canonUrl = $selfUrl;
    }

    $ogType = ($key === 'blog-post') ? 'article' : 'website';
    $defaultOg = $base . '/assets/images/og-cover.png';
    $ogImg = $ogI !== '' ? $ogI : $defaultOg;

    echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>' . "\n";
    if ($desc !== '') echo '    <meta name="description" content="' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    if ($kw !== '') echo '    <meta name="keywords" content="' . htmlspecialchars($kw, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    if ($noindex) {
        echo '    <meta name="robots" content="noindex, nofollow">' . "\n";
    }
    echo '    <link rel="canonical" href="' . htmlspecialchars($canonUrl, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '    <meta property="og:title" content="' . htmlspecialchars($ogT, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    if ($ogD !== '') echo '    <meta property="og:description" content="' . htmlspecialchars($ogD, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '    <meta property="og:image" content="' . htmlspecialchars($ogImg, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '    <meta property="og:url" content="' . htmlspecialchars($canonUrl, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '    <meta property="og:site_name" content="' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '    <meta property="og:type" content="' . $ogType . '">' . "\n";
    echo '    <meta property="og:locale" content="en_US">' . "\n";
    if ($ogType === 'article' && trim((string)($seo['published'] ?? '')) !== '') {
        echo '    <meta property="article:published_time" content="' . htmlspecialchars((string)$seo['published'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }
    echo '    <meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '    <meta name="twitter:title" content="' . htmlspecialchars($ogT, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    if ($ogD !== '') echo '    <meta name="twitter:description" content="' . htmlspecialchars($ogD, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '    <meta name="twitter:image" content="' . htmlspecialchars($ogImg, ENT_QUOTES, 'UTF-8') . '">' . "\n";

    /* JSON-LD: sayfa turune gore. Tekil sayfalarda eklenmez. */
    $ld = null;
    if ($key === 'home') {
        $ld = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => $base . '/#organization',
                    'name' => 'Vexaris',
                    'url' => $base . '/',
                    'logo' => $base . '/assets/images/vexaris-logo.png',
                    'description' => $desc,
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $base . '/#website',
                    'url' => $base . '/',
                    'name' => 'Vexaris',
                    'publisher' => ['@id' => $base . '/#organization'],
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $base . '/blog?q={search_term_string}'],
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
            ],
        ];
    } elseif ($key === 'blog-post') {
        $slug = trim((string)($seo['slug'] ?? ''));
        $ld = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $title,
            'description' => $desc,
            'url' => $canonUrl,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonUrl],
            'image' => $ogImg,
            'datePublished' => trim((string)($seo['published'] ?? '')),
            'publisher' => ['@type' => 'Organization', 'name' => 'Vexaris', 'logo' => ['@type' => 'ImageObject', 'url' => $base . '/assets/images/vexaris-logo.png']],
        ];
        if (trim((string)($seo['author'] ?? '')) !== '') {
            $ld['author'] = ['@type' => 'Person', 'name' => (string)$seo['author']];
        }
        if ($slug !== '') {
            $ld['mainEntityOfPage'] = ['@type' => 'WebPage', '@id' => $base . '/' . ltrim($slug, '/')];
        }
    }
    if ($ld !== null) {
        echo '    <script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }
}
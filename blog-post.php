<?php
require_once __DIR__ . '/core/blog.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/data.php';
boot_session();
$loggedIn = is_logged_in();
$BASE = '/';
$VEX_LOGO = '/assets/images/vexaris-logo.png';
$pdo = db();
$slug = trim((string)($_GET['slug'] ?? ''));
$uriPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$viaBlogPrefix = is_string($uriPath) && (bool)preg_match('#^/blog/#i', (string)$uriPath);
if ($slug === '' && is_string($uriPath)) {
    // Rewrite query string bazi sunucularda dusebiliyor (nginx proxy / AllowOverride farklari).
    // Bu yuzden slug dogrudan REQUEST_URI'den de coz: once /blog/slug, sonra kok-seviye /slug.
    if (preg_match('#^/blog/([a-z0-9-]+/[a-z0-9-]+)/?$#i', $uriPath, $m)) {
        $slug = trim(urldecode($m[1]));
        $viaBlogPrefix = true;
    } elseif (preg_match('#^/blog/([^/]+)/?$#i', $uriPath, $m)) {
        $slug = trim(urldecode($m[1]));
        $viaBlogPrefix = true;
    } elseif (preg_match('#^/([A-Za-z0-9_-]+)/?$#', $uriPath, $m)) {
        $slug = trim(urldecode($m[1]));
    } elseif (preg_match('#^/([a-z0-9-]+/[a-z0-9-]+)/?$#', $uriPath, $m)) {
        $slug = trim(urldecode($m[1]));
    }
}
// Tasanmis slug'lar kalici olarak yeni adrese tasinir (tek hopta 301).
$legacySlugs = [
  'gumroad-alternatives-2026' => 'gumroad/alternative-2026',
  'dijital-urun-nasil-satilir' => 'requirements-for-selling-digital-products-automated-store-guide-2026',
  'vexaris-nedir-dijital-urun-satisi-icin-gelistirilen-e-ticaret-altyapisi' => 'what-is-vexaris-saas-infrastructure-for-digital-product-sales',
];
if (isset($legacySlugs[$slug])) {
    header('Location: /' . $legacySlugs[$slug], true, 301);
    exit;
}
// Slug'lar blog_slugify() ile küçük harf üretilir; büyük harfli linkleri de tolere et.
$slug = strtolower(trim($slug, " \t\n\r\0\x0B/"));
if ($slug === '' || !$pdo) {
    header('Location: /blog'); exit;
}
$post = blog_get($pdo, $slug);
if ((!$post || $post['status'] !== 'published') && !$viaBlogPrefix) {
    // Kok-seviye bilinmeyen slug: blog 404'u degil, global 404 sayfasi gosterilir.
    require __DIR__ . '/404.php';
    exit;
}
if (!$post || $post['status'] !== 'published') {
    http_response_code(404);
    log_pageview('blog-post-404');
    $GLOBALS['seoKey'] = 'blog-post';
    $GLOBALS['seoPost'] = ['title' => 'Post Not Found', 'excerpt' => 'This post was not found or is not published yet.', 'noindex' => 1];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php include __DIR__ . '/includes/seo-head.php'; ?>
<link rel="icon" type="image/png" href="/assets/images/vexaris-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
<link rel="stylesheet" href="/assets/css/tailwind.css?v=<?= filemtime(__DIR__ . '/assets/css/tailwind.css') ?>" />
<link rel="stylesheet" href="/assets/css/legal.css?v=<?= filemtime(__DIR__ . '/assets/css/legal.css') ?>">
</head>
<body>
<div class="relative min-h-screen bg-[#0b0d10] text-[#e9e7e2]">
<div class="noise-overlay" aria-hidden="true"></div>
<?php
    include __DIR__ . '/header.php';
    echo '<section class="blog-post-section"><div class="container blog-post-container"><div class="blog-empty" style="padding:6rem 0"><i class="fas fa-circle-question"></i><p>This post was not found or is not published yet.</p><a href="/blog" class="btn btn-primary" style="margin-top:1rem"><i class="fas fa-arrow-left"></i> Back to Blog</a></div></div></section>';
    include __DIR__ . '/footer.php';
    include __DIR__ . '/includes/modals.php';
?>
</div>
<script>
window.VEX_LEGAL = <?= json_encode($LEGAL, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/assets/js/index.js?v=<?= filemtime(__DIR__ . '/assets/js/index.js') ?>"></script>
</body>
</html>
<?php
    exit;
}
if ($viaBlogPrefix) {
    // Canonical kok-seviye URL'dir (/slug); /blog/slug kalici olarak tasinir.
    header('Location: /' . $post['slug'], true, 301);
    exit;
}
log_pageview('blog-post-' . $slug);
$viewId = blog_view_start($pdo, (int)$post['id']);
$viewCount = 0;
try {
    $stV = $pdo->prepare("SELECT COUNT(*) FROM blog_post_views WHERE post_id = ?");
    $stV->execute([(int)$post['id']]);
    $viewCount = (int)$stV->fetchColumn();
} catch (Throwable $e) { $viewCount = 0; }
$postExcerpt = blog_excerpt_text($post, 200);
$other = $pdo->query("SELECT slug, title FROM blog_posts WHERE status='published' AND slug <> " . $pdo->quote($slug) . " ORDER BY created_at DESC LIMIT 4")->fetchAll();
$GLOBALS['seoKey'] = 'blog-post';
$GLOBALS['seoPost'] = $post;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php include __DIR__ . '/includes/seo-head.php'; ?>
<link rel="icon" type="image/png" href="/assets/images/vexaris-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
<link rel="stylesheet" href="/assets/css/tailwind.css?v=<?= filemtime(__DIR__ . '/assets/css/tailwind.css') ?>" />
<link rel="stylesheet" href="/assets/css/legal.css?v=<?= filemtime(__DIR__ . '/assets/css/legal.css') ?>">
</head>
<body>
<div class="relative min-h-screen bg-[#0b0d10] text-[#e9e7e2]">
<div class="noise-overlay" aria-hidden="true"></div>
<?php include __DIR__ . '/header.php'; ?>

<section class="blog-post-section" aria-label="Blog post">
    <div class="container blog-post-container">
        <nav class="blog-post__crumbs">
            <a href="/">Home</a>
            <i class="fas fa-chevron-right"></i>
            <a href="/blog">Blog</a>
        </nav>

        <div class="blog-post__head">
            <h1 class="blog-post__title"><?= htmlspecialchars($post['title']) ?></h1>
            <div class="blog-post__meta">
                <span><i class="fas fa-calendar"></i> <?= date('d.m.Y', strtotime($post['created_at'])) ?></span>
                <?php if ($post['author_name']): ?>
                <span><i class="fas fa-user"></i> <?= htmlspecialchars($post['author_name']) ?></span>
                <?php endif; ?>
                <span><i class="fas fa-eye"></i> <?= number_format($viewCount, 0, ',', '.') ?> views</span>
            </div>
        </div>

        <?php if ($post['cover_image']): ?>
        <div class="blog-post__cover">
            <img src="<?= htmlspecialchars($post['cover_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>"<?= function_exists('vx_img_attrs') ? vx_img_attrs($post['cover_image']) : '' ?>>
        </div>
        <?php endif; ?>

        <?php if ($postExcerpt !== ''): ?>
        <div class="blog-post__excerpt"><?= htmlspecialchars($postExcerpt) ?></div>
        <?php endif; ?>

        <div class="blog-post__body">
            <?= blog_content_render($post['content']) ?>
        </div>

        <div class="blog-post__back">
            <a href="/blog" class="btn btn-outline"><i class="fas fa-arrow-left"></i> All Posts</a>
        </div>

        <?php if ($other): ?>
        <div class="blog-post__other">
            <h3 class="blog-post__other-title">Other Posts</h3>
            <div class="blog-grid">
                <?php foreach ($other as $o): ?>
                <a href="/<?= htmlspecialchars($o['slug']) ?>" class="blog-card">
                    <div class="blog-card__body">
                        <h2 class="blog-card__title"><?= htmlspecialchars($o['title']) ?></h2>
                        <span class="blog-card__more">Read More <i class="fas fa-arrow-right"></i></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<style>
    .blog-post-section { padding: 170px 0 80px; overflow-x: clip; }
    .blog-post-container { max-width: 1020px; width: 100%; margin: 0 auto; padding-left: 1.25rem; padding-right: 1.25rem; }
    .blog-post__body > * { max-width: 100%; min-width: 0; }
    .blog-post__body [align="center"], .blog-post__body center { display: block; text-align: center; margin-left: auto; margin-right: auto; max-width: 100%; }
    .blog-post__body div[style*="text-align:center"] > img:only-child, .blog-post__body div[style*="text-align: center"] > img:only-child, .blog-post__body p[style*="text-align:center"] > img:only-child, .blog-post__body p[style*="text-align: center"] > img:only-child { display: block; margin-left: auto; margin-right: auto; }
    .blog-post__crumbs { display: flex; align-items: center; gap: 8px; font-size: .82rem; color: var(--text-muted); margin-bottom: 2rem; }
    .blog-post__crumbs a { color: var(--text-muted); text-decoration: none; transition: color .2s; }
    .blog-post__crumbs a:hover { color: var(--cyan); }
    .blog-post__crumbs i { font-size: .65rem; }
    .blog-post__head { text-align: center; margin-bottom: 2rem; }
    .blog-post__title { font-size: clamp(1.7rem,4vw,2.6rem); font-weight: 900; line-height: 1.25; margin-bottom: 1rem; }
    .blog-post__meta { display: flex; gap: 1.2rem; justify-content: center; flex-wrap: wrap; font-size: .82rem; color: var(--text-muted); }
    .blog-post__meta i { color: var(--cyan); margin-right: 4px; }
    .blog-post__cover { border-radius: 18px; overflow: hidden; margin-bottom: 2rem; border: 1px solid var(--cyan-border); }
    .blog-post__cover img { width: 100%; display: block; }
    .blog-post__excerpt { font-size: 1.05rem; color: var(--text-muted); font-style: italic; line-height: 1.7; margin-bottom: 2rem; border-left: 3px solid var(--cyan); padding-left: 1.1rem; }
    .blog-post__body { font-size: 1rem; line-height: 1.85; color: rgba(255,255,255,.88); overflow-wrap: break-word; word-break: break-word; }
    .blog-post__body h1 { font-size: 1.8rem; font-weight: 900; line-height: 1.3; margin: 1.8rem 0 .8rem; color: var(--white); }
    .blog-post__body h2 { font-size: 1.5rem; font-weight: 800; line-height: 1.35; margin: 1.7rem 0 .75rem; color: var(--white); }
    .blog-post__body h3 { font-size: 1.25rem; font-weight: 800; line-height: 1.4; margin: 1.6rem 0 .7rem; color: var(--white); }
    .blog-post__body h4 { font-size: 1.1rem; font-weight: 700; margin: 1.4rem 0 .6rem; color: var(--white); }
    .blog-post__body p, .blog-post__body div { margin-bottom: 1.1rem; }
    .blog-post__body span[style], .blog-post__body font { font-size: inherit; }
    .blog-post__body b, .blog-post__body strong { font-weight: 800; color: #fff; }
    .blog-post__body i, .blog-post__body em { font-style: italic; }
    .blog-post__body u { text-decoration: underline; text-underline-offset: 3px; }
    .blog-post__body s, .blog-post__body strike, .blog-post__body del { text-decoration: line-through; opacity: .75; }
    .blog-post__body a { color: var(--cyan); text-decoration: underline; text-underline-offset: 3px; }
    .blog-post__body a:hover { color: #fff; }
    .blog-post__body ul { list-style: disc; margin: 0 0 1.1rem 1.4rem; padding: 0; }
    .blog-post__body ol { list-style: decimal; margin: 0 0 1.1rem 1.4rem; padding: 0; }
    .blog-post__body table { width: 100%; border-collapse: collapse; margin: 1.4rem 0; font-size: .92rem; display: block; overflow-x: auto; }
    .blog-post__body th, .blog-post__body td { border: 1px solid rgba(255,255,255,.12); padding: .65rem .8rem; text-align: left; }
    .blog-post__body thead th { background: rgba(0,212,255,.08); font-weight: 800; color: #fff; white-space: nowrap; }
    .blog-post__body tbody tr:nth-child(even) { background: rgba(255,255,255,.03); }
    .blog-post__body li { margin-bottom: .4rem; }
    .blog-post__body li::marker { color: var(--cyan); }
    .blog-post__body img { max-width: 100%; height: auto; border-radius: 12px; margin: 1.2rem 0; display: inline-block; }
    .blog-post__body blockquote { border-left: 4px solid var(--cyan); background: rgba(0,212,255,.06); margin: 1.4rem 0; padding: 1rem 1.2rem; color: var(--text-muted); font-style: italic; border-radius: 0 12px 12px 0; }
    .blog-post__body blockquote p, .blog-post__body blockquote div { margin-bottom: .4rem; }
    .blog-post__body blockquote p:last-child, .blog-post__body blockquote div:last-child { margin-bottom: 0; }
    .blog-post__body code { background: rgba(0,0,0,.4); padding: 2px 6px; border-radius: 5px; font-family: monospace; font-size: .9em; color: #e06c75; }
    .blog-post__body pre { background: rgba(0,0,0,.45); border: 1px solid rgba(255,255,255,.08); padding: 1rem 1.2rem; border-radius: 12px; overflow-x: auto; margin: 1.4rem 0; }
    .blog-post__body pre code { background: none; padding: 0; color: #e06c75; }
    .blog-post__body hr { border: none; border-top: 1px solid rgba(255,255,255,.12); margin: 1.6rem 0; }
    .blog-post__back { text-align: center; margin: 3rem 0; }
    .blog-post__other { margin-top: 3rem; padding-top: 2.5rem; border-top: 1px solid rgba(255,255,255,.06); }
    .blog-post__other-title { font-size: 1.2rem; font-weight: 800; margin-bottom: 1.4rem; text-align: center; }
    .blog-post__other .blog-card { background: rgba(255,255,255,.04); }
</style>

<?php if ($viewId > 0): ?>
<script>
(function() {
    var start = Date.now();
    var sent = false;
    function leave() {
        if (sent) return;
        sent = true;
        var secs = Math.max(1, Math.round((Date.now() - start) / 1000));
        var fd = new FormData();
        fd.append('action', 'blog_view_leave');
        fd.append('view_id', '<?= (int)$viewId ?>');
        fd.append('duration', String(secs));
        fd.append('_csrf', '<?= htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8") ?>');
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/api/blog', fd);
        } else {
            try {
                fetch('/api/blog', { method: 'POST', body: fd, keepalive: true });
            } catch (e) {}
        }
    }
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') leave();
    });
    window.addEventListener('pagehide', leave);
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
<?php include __DIR__ . '/includes/modals.php'; ?>
</div>
<script>
window.VEX_LEGAL = <?= json_encode($LEGAL, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/assets/js/index.js?v=<?= filemtime(__DIR__ . '/assets/js/index.js') ?>"></script>
</body>
</html>
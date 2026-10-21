<?php
require_once __DIR__ . '/core/blog.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/data.php';
boot_session();
$loggedIn = is_logged_in();
log_pageview('blog');
$pdo = db();
$posts = $pdo ? blog_list($pdo, 24) : [];
$posts = array_values(array_filter($posts, fn($p) => $p['status'] === 'published'));
$GLOBALS['seoKey'] = 'blog';
$BASE = '/';
$VEX_LOGO = '/assets/images/vexaris-logo.png';
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

<section class="blog-section" aria-label="Blog section">
    <div class="container blog-container">
        <div class="blog-header">
            <div class="hero__badge" style="margin:0 auto 1.5rem">
                <i class="fas fa-pen-nib"></i>
                <span>Blog</span>
            </div>
            <h1 class="section-title">Vexaris <span class="accent">Blog</span></h1>
            <p class="section-subtitle">
                E-commerce trends, Vexaris updates, and tips to grow your business.
            </p>
        </div>

        <?php if ($posts): ?>
        <div class="blog-grid">
            <?php foreach ($posts as $p): ?>
            <a href="/<?= htmlspecialchars($p['slug']) ?>" class="blog-card" aria-label="<?= htmlspecialchars($p['title']) ?>">
                <?php if ($p['cover_image']): ?>
                <div class="blog-card__img-wrap">
                    <img src="<?= htmlspecialchars($p['cover_image']) ?>" alt="<?= htmlspecialchars($p['title']) ?>" loading="lazy">
                </div>
                <?php else: ?>
                <div class="blog-card__img-wrap blog-card__img-wrap--empty">
                    <i class="fas fa-pen-nib"></i>
                </div>
                <?php endif; ?>
                <div class="blog-card__body">
                    <div class="blog-card__meta">
                        <span><i class="fas fa-calendar"></i> <?= date('d.m.Y', strtotime($p['created_at'])) ?></span>
                        <?php if ($p['author_name']): ?>
                        <span><i class="fas fa-user"></i> <?= htmlspecialchars($p['author_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <h2 class="blog-card__title"><?= htmlspecialchars($p['title']) ?></h2>
                    <?php $cardExcerpt = blog_excerpt_text($p, 140); ?>
                    <?php if ($cardExcerpt !== ''): ?>
                    <p class="blog-card__excerpt"><?= htmlspecialchars($cardExcerpt) ?></p>
                    <?php endif; ?>
                    <span class="blog-card__more">Read More <i class="fas fa-arrow-right"></i></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="blog-empty">
            <i class="fas fa-pen-nib"></i>
            <p>No posts published yet. Coming soon.</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<style>
    .blog-section { padding: 180px 0 90px; }
    .blog-container { max-width: 1100px; }
    .blog-header { text-align: center; margin-bottom: 3rem; }
    .blog-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.6rem; }
    .blog-card {
        display: flex; flex-direction: column;
        background: rgba(255,255,255,.05);
        border: 1px solid var(--cyan-border);
        border-radius: 18px;
        overflow: hidden;
        text-decoration: none; color: var(--white);
        transition: all .3s ease;
    }
    .blog-card:hover { transform: translateY(-5px); border-color: var(--cyan); box-shadow: 0 12px 40px rgba(0,212,255,.18); }
    .blog-card__img-wrap { height: 190px; overflow: hidden; background: linear-gradient(135deg,#0d1b3e,#0a1628); }
    .blog-card__img-wrap img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
    .blog-card:hover .blog-card__img-wrap img { transform: scale(1.05); }
    .blog-card__img-wrap--empty { display: flex; align-items: center; justify-content: center; color: rgba(0,212,255,.4); font-size: 2.6rem; }
    .blog-card__body { padding: 1.4rem 1.5rem 1.6rem; display: flex; flex-direction: column; flex: 1; }
    .blog-card__meta { display: flex; gap: 1rem; font-size: .76rem; color: var(--text-muted); margin-bottom: .7rem; }
    .blog-card__meta i { color: var(--cyan); margin-right: 4px; }
    .blog-card__title { font-size: 1.15rem; font-weight: 800; line-height: 1.35; margin-bottom: .6rem; }
    .blog-card__excerpt { color: var(--text-muted); font-size: .9rem; line-height: 1.6; margin-bottom: 1rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .blog-card__more { margin-top: auto; color: var(--cyan); font-size: .86rem; font-weight: 700; }
    .blog-card__more i { margin-left: 5px; transition: transform .25s ease; }
    .blog-card:hover .blog-card__more i { transform: translateX(5px); }
    .blog-empty { text-align: center; color: var(--text-muted); padding: 3rem 0; }
    .blog-empty i { font-size: 2.6rem; color: rgba(0,212,255,.35); display: block; margin-bottom: .8rem; }
</style>

<?php include __DIR__ . '/footer.php'; ?>
<?php include __DIR__ . '/includes/modals.php'; ?>
</div>
<script>
window.VEX_LEGAL = <?= json_encode($LEGAL, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/assets/js/index.js?v=<?= filemtime(__DIR__ . '/assets/js/index.js') ?>"></script>
</body>
</html>
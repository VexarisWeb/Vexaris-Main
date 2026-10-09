<?php
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/blog.php';
header('Content-Type: application/xml; charset=utf-8');
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$base = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'vexaris.net');
$urls = [
    ['loc' => $base . '/', 'freq' => 'daily', 'pr' => '1.0'],
    ['loc' => $base . '/features', 'freq' => 'weekly', 'pr' => '0.9'],
    ['loc' => $base . '/pricing', 'freq' => 'weekly', 'pr' => '0.9'],
    ['loc' => $base . '/blog', 'freq' => 'daily', 'pr' => '0.8'],
    ['loc' => $base . '/license-check', 'freq' => 'monthly', 'pr' => '0.7'],
    ['loc' => $base . '/login', 'freq' => 'monthly', 'pr' => '0.3'],
    ['loc' => $base . '/register', 'freq' => 'monthly', 'pr' => '0.3'],
    ['loc' => $base . '/privacy-policy', 'freq' => 'yearly', 'pr' => '0.3'],
    ['loc' => $base . '/terms-of-use', 'freq' => 'yearly', 'pr' => '0.3'],
    ['loc' => $base . '/cookie-policy', 'freq' => 'yearly', 'pr' => '0.3'],
];
try {
    $pdo = db();
    if ($pdo) {
        $st = $pdo->query("SELECT slug, created_at, updated_at FROM blog_posts WHERE status='published' ORDER BY created_at DESC LIMIT 500");
        foreach ($st->fetchAll() as $r) {
            $lm = !empty($r['updated_at']) ? $r['updated_at'] : $r['created_at'];
            $urls[] = [
                'loc' => $base . '/blog/' . $r['slug'],
                'freq' => 'monthly',
                'pr' => '0.6',
                'lastmod' => date('Y-m-d', strtotime($lm)),
            ];
        }
    }
} catch (Throwable $e) {}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($u['loc'], ENT_QUOTES, 'UTF-8') . '</loc>' . "\n";
    if (!empty($u['lastmod'])) echo '    <lastmod>' . $u['lastmod'] . '</lastmod>' . "\n";
    echo '    <changefreq>' . $u['freq'] . '</changefreq>' . "\n";
    echo '    <priority>' . $u['pr'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}
echo '</urlset>';

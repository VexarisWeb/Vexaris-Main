<?php
require_once dirname(__DIR__) . '/core/seo.php';
$seoKey = $GLOBALS['seoKey'] ?? '';
$seoPost = $GLOBALS['seoPost'] ?? null;
if ($seoKey !== '') {
    $pdoSeo = db();
    if ($pdoSeo) {
        $fallback = $GLOBALS['seoFallback'] ?? [
            'title' => 'Vexaris: Digital Product Sales Platform',
            'description' => 'Vexaris is a digital product sales platform that lets you sell e-pins, e-books, license keys and software packages from a single panel.',
        ];
        if ($seoKey === 'blog-post' && $seoPost) {
            $fallback['title'] = $seoPost['title'] ?? $fallback['title'];
            $fallback['description'] = $seoPost['excerpt'] ?? '';
        }
        $seoHead = seo_page_build($pdoSeo, $seoKey, $fallback, $seoKey === 'blog-post' ? $seoPost : null);
        seo_render_head($seoHead);
    }
}

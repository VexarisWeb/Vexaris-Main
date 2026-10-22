<?php
declare(strict_types=1);

$slug = isset($_GET['slug']) ? strtolower(preg_replace('#[^a-zA-Z0-9/_-]#', '', (string)$_GET['slug'])) : '';

$title = '';
$seed = $slug !== '' ? $slug : 'vexaris';

require_once __DIR__ . '/core/db.php';
$pdo = db();
if ($pdo && $slug !== '') {
    try {
        $st = $pdo->prepare("SELECT title, keywords FROM blog_posts WHERE slug = ? AND status = 'published' LIMIT 1");
        $st->execute([$slug]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $title = (string)$row['title'];
            if (!empty($row['keywords'])) {
                $seed = (string)$row['keywords'] . '|' . $slug;
            }
        }
    } catch (Throwable $e) {
    }
}
if ($title === '') {
    $title = ucwords(str_replace('-', ' ', $slug));
}

$hue = (int)(hexdec(substr(md5($seed), 0, 8)) % 360);
if ($hue >= 52 && $hue <= 112) {
    $hue = ($hue + 128) % 360;
}
$c1 = sprintf('hsl(%d, 58%%, 52%%)', $hue);
$c2 = sprintf('hsl(%d, 62%%, 27%%)', ($hue + 340) % 360);

$words = preg_split('/\s+/', trim($title)) ?: [];
$stop = ['the', 'and', 'for', 'with', 'your', 'from', 'that', 'this', 'what', 'why', 'how', 'a', 'an', 'in', 'on', 'of', 'to', 'is', 'are', 'vs'];
$letters = [];
foreach ($words as $w) {
    $w = preg_replace('/[^A-Za-z0-9]/', '', $w);
    if ($w === '' || in_array(strtolower($w), $stop, true)) {
        continue;
    }
    $letters[] = mb_strtoupper(mb_substr($w, 0, 1));
    if (count($letters) === 2) {
        break;
    }
}
if (!$letters) {
    $letters = ['V'];
}
$ini = htmlspecialchars(implode('', $letters), ENT_QUOTES, 'UTF-8');

$rawLabel = mb_strtoupper(trim($title), 'UTF-8');
$ell = '';
if (mb_strlen($rawLabel) > 34) {
    $cut = mb_substr($rawLabel, 0, 34);
    $sp = mb_strrpos($cut, ' ');
    $rawLabel = $sp !== false && $sp > 14 ? mb_substr($cut, 0, $sp) : $cut;
    $ell = '…';
}
$label = htmlspecialchars($rawLabel, ENT_QUOTES, 'UTF-8');

$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630" role="img" aria-label="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">'
    . '<defs>'
    . '<linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $c1 . '"/><stop offset="1" stop-color="' . $c2 . '"/></linearGradient>'
    . '<pattern id="dots" width="26" height="26" patternUnits="userSpaceOnUse"><circle cx="1.6" cy="1.6" r="1.6" fill="rgba(255,255,255,.16)"/></pattern>'
    . '</defs>'
    . '<rect width="1200" height="630" fill="url(#g)"/>'
    . '<rect width="1200" height="630" fill="url(#dots)"/>'
    . '<circle cx="1010" cy="120" r="250" fill="rgba(255,255,255,.10)"/>'
    . '<circle cx="150" cy="600" r="230" fill="rgba(0,0,0,.20)"/>'
    . '<circle cx="1060" cy="120" r="118" fill="none" stroke="rgba(255,255,255,.22)" stroke-width="2"/>'
    . '<text x="80" y="410" font-family="Manrope,Segoe UI,Arial,sans-serif" font-size="250" font-weight="800" fill="rgba(255,255,255,.94)" letter-spacing="-8">' . $ini . '</text>'
    . '<text x="86" y="520" font-family="Manrope,Segoe UI,Arial,sans-serif" font-size="26" font-weight="600" fill="rgba(255,255,255,.72)" letter-spacing="1">' . $label . $ell . '</text>'
    . '<text x="1114" y="566" text-anchor="end" font-family="JetBrains Mono,Consolas,monospace" font-size="18" font-weight="600" fill="rgba(255,255,255,.55)" letter-spacing="6">VEXARIS</text>'
    . '</svg>';

$etag = '"' . md5($svg) . '"';
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim((string)$_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    header('ETag: ' . $etag);
    header('Cache-Control: public, max-age=86400');
    exit;
}

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=86400');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
echo $svg;

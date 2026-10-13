<?php
require_once __DIR__ . '/db.php';

function blog_markdown_to_html(string $md): string {
    $md = str_replace("\r\n", "\n", $md);
    $html = htmlspecialchars($md, ENT_QUOTES, 'UTF-8');
    $html = preg_replace('/```([\s\S]*?)```/', '<pre style="background:#1a1a1a;border:1px solid #333;padding:12px;border-radius:8px;overflow-x:auto;margin:12px 0"><code>$1</code></pre>', $html);
    $html = preg_replace('/`([^`]+)`/', '<code style="background:rgba(0,0,0,.35);padding:2px 6px;border-radius:5px;font-family:monospace;color:#e06c75">$1</code>', $html);
    $html = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^#\s+(.+)$/m', '<h1>$1</h1>', $html);
    $html = preg_replace('/^>\s+(.+)$/m', '<blockquote>$1</blockquote>', $html);
    $html = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" style="max-width:100%;border-radius:8px">', $html);
    $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank">$1</a>', $html);
    $html = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $html);
    $html = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $html);
    $html = preg_replace('/_([^_]+)_/', '<em>$1</em>', $html);
    $html = preg_replace('/^---$/m', '<hr>', $html);
    $lines = explode("\n", $html);
    $out = []; $inUl = false;
    foreach ($lines as $line) {
        if (preg_match('/^\s*[-*+]\s+(.+)$/', $line, $m)) {
            if (!$inUl) { $out[] = '<ul>'; $inUl = true; }
            $out[] = '<li>' . $m[1] . '</li>';
        } else {
            if ($inUl) { $out[] = '</ul>'; $inUl = false; }
            $out[] = $line;
        }
    }
    if ($inUl) $out[] = '</ul>';
    $html = implode("\n", $out);
    $html = preg_replace('/^\d+\.\s+(.+)$/m', '<li>$1</li>', $html);
    $parts = preg_split('/\n{2,}/', $html);
    $res = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        if (preg_match('/^<(h[1-3]|ul|ol|pre|blockquote|hr|img|p|li)/', $p)) $res[] = $p;
        else $res[] = '<p>' . nl2br($p) . '</p>';
    }
    return implode("\n", $res);
}

function blog_content_render(string $content): string {
    if (strpos($content, '<') !== false && preg_match('/<[a-z][a-z0-9]*(\s[^<>]*)?\/?>/i', $content)) {
        return $content;
    }
    if (preg_match('/(^|\n)\s*(#{1,3}\s|[-*+]\s|>\s|```|.*\*\*.+\*\*|.*\[[^\]]+\]\([^)]+\))/m', $content)) {
        return blog_markdown_to_html($content);
    }
    return '<p>' . nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8')) . '</p>';
}

function blog_excerpt_text(array $post, int $len = 160): string {
    $ex = trim((string)($post['excerpt'] ?? ''));
    if ($ex !== '') return $ex;
    $plain = trim((string)preg_replace('/\s+/', ' ', strip_tags(blog_content_render((string)($post['content'] ?? '')))));
    if (mb_strlen($plain, 'UTF-8') > $len) return mb_substr($plain, 0, $len, 'UTF-8') . '…';
    return $plain;
}

function blog_slugify(string $text): string {
    $map = [
        'ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'I' => 'i',
        'İ' => 'i', 'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u',
    ];
    $out = [];
    foreach (preg_split('#/#', $text) as $part) {
        $part = strtr($part, $map);
        $part = mb_strtolower($part, 'UTF-8');
        $part = preg_replace('/[^a-z0-9]+/', '-', $part);
        $part = trim($part, '-');
        if ($part !== '') $out[] = $part;
    }
    if (!$out) return 'yazi';
    return implode('/', $out);
}

function blog_slug_unique(PDO $pdo, string $slug, ?int $excludeId): string {
    $base = $slug;
    $i = 1;
    while (true) {
        $st = $pdo->prepare('SELECT id FROM blog_posts WHERE slug = ?' . ($excludeId ? ' AND id <> ?' : '') . ' LIMIT 1');
        $args = $excludeId ? [$slug, $excludeId] : [$slug];
        $st->execute($args);
        if (!$st->fetchColumn()) return $slug;
        $i++;
        $slug = $base . '-' . $i;
    }
}

function blog_list(PDO $pdo, int $limit = 50): array {
    $st = $pdo->prepare("SELECT b.*, u.username AS author_name
        FROM blog_posts b
        LEFT JOIN users u ON u.id = b.author_id
        ORDER BY b.created_at DESC
        LIMIT " . (int)$limit);
    $st->execute();
    return $st->fetchAll();
}

function blog_get(PDO $pdo, string $slug): ?array {
    $st = $pdo->prepare("SELECT b.*, u.username AS author_name
        FROM blog_posts b
        LEFT JOIN users u ON u.id = b.author_id
        WHERE b.slug = ? LIMIT 1");
    $st->execute([$slug]);
    $row = $st->fetch();
    return $row ?: null;
}

function blog_get_by_id(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare("SELECT b.*, u.username AS author_name
        FROM blog_posts b
        LEFT JOIN users u ON u.id = b.author_id
        WHERE b.id = ? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function blog_save(PDO $pdo, array $d): array {
    $title = trim($d['title'] ?? '');
    if ($title === '') return ['ok' => false, 'msg' => 'Başlık zorunludur.'];
    $slug = blog_slug_unique($pdo, blog_slugify(trim($d['slug'] ?? '') !== '' ? $d['slug'] : $title), $d['id'] ?? null);
    $excerpt = trim($d['excerpt'] ?? '');
    if ($excerpt === '') {
        $plain = trim(strip_tags($d['content'] ?? ''));
        $excerpt = mb_substr($plain, 0, 180, 'UTF-8');
    }
    $content = trim($d['content'] ?? '');
    if ($content !== '' && strpos($content, '<') === false && preg_match('/(^|\n)\s*(#{1,3}\s|[-*+]\s|>\s|```)/m', $content)) {
        $content = blog_markdown_to_html($content);
    }
    $cover = trim($d['cover_image'] ?? '');
    if ($cover !== '' && !preg_match('#^/uploads/blog/[a-z0-9-]+\.(jpg|jpeg|png|webp|gif)$#i', $cover)) {
        return ['ok' => false, 'msg' => 'Geçersiz kapak görseli.'];
    }
    $status = ($d['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $author = (int)($d['author_id'] ?? 0);
    if ($author <= 0) {
        $u = current_user();
        $author = $u ? (int)$u['id'] : 0;
    }

    if (!empty($d['id'])) {
        $st = $pdo->prepare("UPDATE blog_posts SET title=?, slug=?, excerpt=?, content=?, cover_image=?, status=?, author_id=?, meta_title=?, meta_description=?, keywords=?, canonical=?, noindex=?, og_title=?, og_description=?, og_image=? WHERE id=?");
        $st->execute([$title, $slug, $excerpt, $content, $cover ?: null, $status, $author,
            trim($d['meta_title'] ?? '') !== '' ? $d['meta_title'] : null,
            trim($d['meta_description'] ?? '') !== '' ? $d['meta_description'] : null,
            trim($d['keywords'] ?? '') !== '' ? $d['keywords'] : null,
            trim($d['canonical'] ?? '') !== '' ? $d['canonical'] : null,
            (isset($d['noindex']) && (int)$d['noindex'] === 1) ? 1 : 0,
            trim($d['og_title'] ?? '') !== '' ? $d['og_title'] : null,
            trim($d['og_description'] ?? '') !== '' ? $d['og_description'] : null,
            trim($d['og_image'] ?? '') !== '' ? $d['og_image'] : null,
            (int)$d['id']]);
        try {
            require_once __DIR__ . '/indexing.php';
            indexing_notify_slug($slug, $status === 'published' ? 'URL_UPDATED' : 'URL_DELETED');
        } catch (Throwable $e) {}
        return ['ok' => true, 'msg' => 'Yazı güncellendi.', 'id' => (int)$d['id'], 'slug' => $slug];
    }

    $st = $pdo->prepare("INSERT INTO blog_posts (title, slug, excerpt, content, cover_image, status, author_id, meta_title, meta_description, keywords, canonical, noindex, og_title, og_description, og_image) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $st->execute([$title, $slug, $excerpt, $content, $cover ?: null, $status, $author,
        trim($d['meta_title'] ?? '') !== '' ? $d['meta_title'] : null,
        trim($d['meta_description'] ?? '') !== '' ? $d['meta_description'] : null,
        trim($d['keywords'] ?? '') !== '' ? $d['keywords'] : null,
        trim($d['canonical'] ?? '') !== '' ? $d['canonical'] : null,
        (isset($d['noindex']) && (int)$d['noindex'] === 1) ? 1 : 0,
        trim($d['og_title'] ?? '') !== '' ? $d['og_title'] : null,
        trim($d['og_description'] ?? '') !== '' ? $d['og_description'] : null,
        trim($d['og_image'] ?? '') !== '' ? $d['og_image'] : null]);
    $newId = (int)$pdo->lastInsertId();
    if ($status === 'published') {
        try {
            require_once __DIR__ . '/indexing.php';
            indexing_notify_slug($slug, 'URL_UPDATED');
        } catch (Throwable $e) {}
    }
    return ['ok' => true, 'msg' => 'Yazı yayınlandı.', 'id' => $newId, 'slug' => $slug];
}

function blog_delete(PDO $pdo, int $id): bool {
    $slug = '';
    try {
        $st = $pdo->prepare('SELECT slug FROM blog_posts WHERE id = ?');
        $st->execute([$id]);
        $slug = (string)($st->fetchColumn() ?: '');
    } catch (Throwable $e) {}
    $st = $pdo->prepare('DELETE FROM blog_posts WHERE id = ?');
    $st->execute([$id]);
    $ok = $st->rowCount() > 0;
    if ($ok && $slug !== '') {
        try {
            require_once __DIR__ . '/indexing.php';
            indexing_notify_slug($slug, 'URL_DELETED');
        } catch (Throwable $e2) {}
    }
    return $ok;
}

function blog_view_start(PDO $pdo, int $postId): int {
    try { if (is_admin()) return 0; } catch (Throwable $e) {}
    $sessionKey = null;
    $uid = null;
    $u = current_user();
    if ($u) $uid = (int)$u['id'];
    if (!empty($_COOKIE['vx_session'])) $sessionKey = substr(preg_replace('/[^a-zA-Z0-9]/', '', $_COOKIE['vx_session']), 0, 64);
    $ip = client_ip();
    try {
        $st = $pdo->prepare("INSERT INTO blog_post_views (post_id, session_key, user_id, ip, entered_at) VALUES (?,?,?,?, NOW())");
        $st->execute([$postId, $sessionKey ?: null, $uid ?: null, $ip !== '' ? $ip : null]);
        return (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        error_log('blog_view_start hatası: ' . $e->getMessage());
        return 0;
    }
}

function blog_view_end(PDO $pdo, int $viewId, int $duration): void {
    if ($viewId <= 0 || $duration < 0) return;
    try {
        $pdo->prepare("UPDATE blog_post_views SET duration_sec = ?, left_at = NOW() WHERE id = ? AND duration_sec = 0")
            ->execute([(int)$duration, $viewId]);
    } catch (Exception $e) {
        error_log('blog_view_end hatası: ' . $e->getMessage());
    }
}

function blog_stats(PDO $pdo, int $postId): array {
    $out = ['total' => 0, 'unique_ip' => 0, 'avg_duration' => 0, 'chart' => [], 'recent' => []];
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM blog_post_views WHERE post_id = ?");
        $st->execute([$postId]);
        $out['total'] = (int)$st->fetchColumn();

        $st = $pdo->prepare("SELECT COUNT(DISTINCT ip) FROM blog_post_views WHERE post_id = ? AND ip IS NOT NULL");
        $st->execute([$postId]);
        $out['unique_ip'] = (int)$st->fetchColumn();

        $st = $pdo->prepare("SELECT AVG(duration_sec) FROM blog_post_views WHERE post_id = ? AND duration_sec > 0");
        $st->execute([$postId]);
        $out['avg_duration'] = (int)round((float)$st->fetchColumn());

        $st = $pdo->prepare("SELECT DATE(entered_at) AS d, COUNT(*) AS c FROM blog_post_views
            WHERE post_id = ? AND entered_at >= (CURDATE() - INTERVAL 13 DAY)
            GROUP BY DATE(entered_at) ORDER BY d ASC");
        $st->execute([$postId]);
        $rows = $st->fetchAll();
        $byDay = [];
        foreach ($rows as $r) $byDay[$r['d']] = (int)$r['c'];
        for ($i = 13; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} day"));
            $out['chart'][] = ['date' => date('d.m', strtotime($d)), 'count' => $byDay[$d] ?? 0];
        }

        $st = $pdo->prepare("SELECT v.ip, v.duration_sec, v.entered_at, v.left_at, u.username
            FROM blog_post_views v LEFT JOIN users u ON u.id = v.user_id
            WHERE v.post_id = ? ORDER BY v.id DESC LIMIT 10");
        $st->execute([$postId]);
        $out['recent'] = $st->fetchAll();
    } catch (Exception $e) {
        error_log('blog_stats hatası: ' . $e->getMessage());
    }
    return $out;
}

function blog_cover_upload(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => 'Dosya yüklenemedi. (kod: ' . ($file['error'] ?? '?') . ')'];
    }
    $maxSize = 10 * 1024 * 1024;
    if ((int)$file['size'] > $maxSize) {
        return ['ok' => false, 'msg' => 'Görsel 10 MB\'dan büyük olamaz.'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed, true)) {
        return ['ok' => false, 'msg' => 'Yalnızca JPG, PNG, WEBP veya GIF yüklenebilir.'];
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return ['ok' => false, 'msg' => 'Geçersiz görsel dosyası.'];
    }
    $dir = dirname(__DIR__) . '/uploads/blog';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fname = 'blog-' . substr(bin2hex(random_bytes(8)), 0, 16) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . DIRECTORY_SEPARATOR . $fname)) {
        return ['ok' => false, 'msg' => 'Dosya kaydedilemedi. Dizin yazılabilir olmayabilir.'];
    }
    return ['ok' => true, 'url' => '/uploads/blog/' . $fname];
}
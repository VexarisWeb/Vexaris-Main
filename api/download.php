<?php
require_once __DIR__ . '/../core/db.php';
boot_session();

$pdo = db();

try { if (setting_get('vx_downloads_off', '0') === '1') { http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); echo 'Downloads are disabled.'; exit; } } catch (Throwable $e) {}

if ($pdo && isset($_GET['token'])) {
    $token = trim((string)$_GET['token']);
    if ($token !== '') {
        $st = $pdo->prepare("SELECT * FROM download_tokens WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1");
        $st->execute([$token]);
        $tok = $st->fetch();
        if ($tok) {
            try {
                if (setting_get('activation_enabled', '1') === '1' && !user_is_active($pdo, (int)$tok['user_id'])) {
                    http_response_code(403);
                    echo 'Your account has not been activated yet.';
                    exit;
                }
            } catch (Throwable $e) {}
            $stP = $pdo->prepare("SELECT p.*, up.user_id AS owner_id FROM products p JOIN user_products up ON up.product_id = p.id WHERE p.id = ? AND up.user_id = ? LIMIT 1");
            $stP->execute([(int)$tok['product_id'], (int)$tok['user_id']]);
            $prod = $stP->fetch();
            if ($prod && $prod['is_active'] == 1) {
                $ver = $pdo->prepare("SELECT * FROM versions WHERE product_id=? AND is_active=1 ORDER BY created_at DESC LIMIT 1");
                $ver->execute([(int)$prod['id']]);
                $v = $ver->fetch();
                if ($v && !empty($v['file_path']) && is_file(dirname(__DIR__) . $v['file_path'])) {
                    $prod['file_name'] = $v['file_name'];
                    $prod['file_path'] = ltrim($v['file_path'], '/');
                    $filePath = dirname(__DIR__) . $v['file_path'];
                } else {
                    $filePath = dirname(__DIR__) . '/uploads/products/' . $prod['file_path'];
                }
                // Guvenlik: dosya yolu uploads altina hapsedilir (stored path traversal savunmasi)
                $uploadsBase = realpath(dirname(__DIR__) . '/uploads');
                $realPath = ($filePath !== '' && $uploadsBase !== false) ? realpath($filePath) : false;
                if ($realPath === false || strpos($realPath, $uploadsBase) !== 0 || !is_file($realPath)) {
                    $pdo->prepare("UPDATE download_tokens SET used = 1 WHERE id = ?")->execute([(int)$tok['id']]);
                    http_response_code(404);
                    echo 'Download not found or the link has expired.';
                    exit;
                }
                $filePath = $realPath;
                if (is_file($filePath)) {
                    $pdo->prepare("UPDATE download_tokens SET used = 1 WHERE id = ?")->execute([(int)$tok['id']]);
                    $ext = strtolower(pathinfo($prod['file_name'], PATHINFO_EXTENSION));
                    $map = [
                        'zip' => 'application/zip', 'rar' => 'application/vnd.rar',
                        '7z' => 'application/x-7z-compressed', 'tar' => 'application/x-tar',
                        'gz' => 'application/gzip', 'pdf' => 'application/pdf',
                        'txt' => 'text/plain', 'sql' => 'application/octet-stream',
                        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                        'webp' => 'image/webp', 'exe' => 'application/octet-stream',
                    ];
                    $mime = $map[$ext] ?? 'application/octet-stream';
                    $size = (int)filesize($filePath);
                    header('Content-Description: File Transfer');
                    header('Content-Type: ' . $mime);
                    header('Content-Length: ' . $size);
                    header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $prod['file_name']) . '"');
                    header('X-Content-Type-Options: nosniff');
                    while (ob_get_level() > 0) ob_end_clean();
                    readfile($filePath);
                    exit;
                }
                $pdo->prepare("UPDATE download_tokens SET used = 1 WHERE id = ?")->execute([(int)$tok['id']]);
            }
        }
    }
    http_response_code(404);
    echo 'Download not found or the link has expired.';
    exit;
}

require_login();
$uid = (int)(current_user()['id'] ?? 0);
try {
    if ($pdo && setting_get('activation_enabled', '1') === '1' && !user_is_active($pdo, $uid)) {
        http_response_code(403);
        echo 'Your account has not been activated yet.';
        exit;
    }
} catch (Throwable $e) {}
$productId = (int)($_GET['product_id'] ?? 0);

if ($productId <= 0 || !$pdo) {
    http_response_code(404);
    echo 'Not found.';
    exit;
}

try {
    $st = $pdo->prepare("SELECT p.* FROM products p JOIN user_products up ON up.product_id = p.id WHERE p.id = ? AND up.user_id = ? AND p.is_active = 1 LIMIT 1");
    $st->execute([$productId, $uid]);
    $prod = $st->fetch();
    if (!$prod) {
        http_response_code(403);
        echo 'You do not have access to this product.';
        exit;
    }

    $pdo->prepare("DELETE FROM download_tokens WHERE user_id = ? AND product_id = ? AND (used = 1 OR expires_at <= NOW())")
        ->execute([$uid, $productId]);

    $token   = bin2hex(random_bytes(32));
    $pdo->prepare("INSERT INTO download_tokens (user_id, product_id, token, expires_at) VALUES (?,?,?,DATE_ADD(NOW(), INTERVAL 5 MINUTE))")
        ->execute([$uid, $productId, $token]);

    header('Location: /api/download?token=' . $token);
    exit;
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo 'Server error.';
}

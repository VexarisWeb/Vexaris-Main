<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/html-sanitize.php';
boot_session();
header('Content-Type: application/json; charset=utf-8');

if (!is_admin() && !has_permission('products')) {
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

$pdo    = db();
$action = $_POST['action'] ?? '';

if (!$pdo) {
    echo json_encode(['ok' => false, 'msg' => 'Veritabanı bağlantısı kurulamadı.']);
    exit;
}

$uploadsDir = dirname(__DIR__) . '/uploads/products';
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0755, true);
}

function product_mime_allowed(string $ext, string $mime): bool {
    if ($mime === '' || $mime === 'application/octet-stream') return true;
    $map = [
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
        'rar'  => ['application/x-rar-compressed', 'application/x-rar', 'application/vnd.rar'],
        '7z'   => ['application/x-7z-compressed'],
        'pdf'  => ['application/pdf'],
        'txt'  => ['text/plain'],
        'epub' => ['application/epub+zip', 'application/zip'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'gif'  => ['image/gif'],
        'mp4'  => ['video/mp4'],
        'mp3'  => ['audio/mpeg'],
        'key'  => ['text/plain'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt'  => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'apk'  => ['application/vnd.android.package-archive', 'application/zip'],
        'exe'  => ['application/x-dos-exe', 'application/x-executable', 'application/x-msdownload'],
    ];
    foreach ($map[$ext] ?? [] as $m) {
        if ($mime === $m) return true;
    }
    return false;
}

function product_save_file(array $file, string $uploadsDir): array {
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => 'Dosya yüklenemedi. (kod: ' . $error . ')'];
    }
    $maxSize = 256 * 1024 * 1024;
    if ((int)$file['size'] > $maxSize) {
        return ['ok' => false, 'msg' => 'Dosya 256 MB\'dan büyük olamaz.'];
    }
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $baseName = strtolower((string)($file['name'] ?? ''));
    $allowedExt = ['zip','rar','7z','pdf','txt','epub','jpg','jpeg','png','webp','gif','mp4','mp3','key','doc','docx','xls','xlsx','ppt','pptx','apk','exe'];
    if (!in_array($ext, $allowedExt, true) || in_array($baseName, ['.htaccess', '.user.ini'], true)) {
        return ['ok' => false, 'msg' => 'Bu dosya türü güvenlik nedeniyle yüklenemez.'];
    }
    $finfo = function_exists('finfo_open') ? @finfo_open(FILEINFO_MIME_TYPE) : false;
    if ($finfo) {
        $mime = (string)@finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!product_mime_allowed($ext, $mime)) {
            return ['ok' => false, 'msg' => 'Dosya içeriği uzantısıyla uyuşmuyor.'];
        }
    }
    $safe = preg_replace('/[^a-z0-9_-]/i', '-', pathinfo($file['name'], PATHINFO_FILENAME));
    if ($safe === '') $safe = 'urun';
    $fname = $safe . '-' . substr(bin2hex(random_bytes(6)), 0, 10) . '.' . ($ext !== '' ? $ext : 'bin');
    $dest  = $uploadsDir . DIRECTORY_SEPARATOR . $fname;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'msg' => 'Dosya kaydedilemedi. Dizin yazılabilir olmayabilir.'];
    }
    $bytes = (int)$file['size'];
    $size  = $bytes >= 1048576 ? round($bytes / 1048576, 2) . ' MB' : round($bytes / 1024, 1) . ' KB';
    return ['ok' => true, 'file_name' => $file['name'], 'file_path' => $fname, 'file_size' => $size];
}

try {

    // ── Liste ────────────────────────────────────────────────
    if ($action === 'list') {
        $rows = $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll();
        echo json_encode(['ok' => true, 'products' => $rows]);
        exit;
    }

    // ── Ekle ─────────────────────────────────────────────────
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            echo json_encode(['ok' => false, 'msg' => 'Ürün adı zorunludur.']);
            exit;
        }
        if (empty($_FILES['file']['name'])) {
            echo json_encode(['ok' => false, 'msg' => 'Lütfen bir dosya yükleyin.']);
            exit;
        }
        $saved = product_save_file($_FILES['file'], $uploadsDir);
        if (!$saved['ok']) {
            echo json_encode($saved);
            exit;
        }
        $desc = trim(vx_clean_html(trim($_POST['description'] ?? ''), 'basic'));
        $active = (isset($_POST['is_active']) && $_POST['is_active'] === '0') ? 0 : 1;
        $price = max(0, (float)($_POST['price'] ?? 0));
        $pdo->prepare("INSERT INTO products (name, description, file_name, file_path, file_size, is_active, price) VALUES (?,?,?,?,?,?,?)")
            ->execute([$name, $desc, $saved['file_name'], $saved['file_path'], $saved['file_size'], $active, $price]);
        echo json_encode(['ok' => true, 'msg' => 'Ürün eklendi.', 'id' => (int)$pdo->lastInsertId()]);
        exit;
    }

    // ── Düzenle ──────────────────────────────────────────────
    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $prod = $st->fetch();
        if (!$prod) {
            echo json_encode(['ok' => false, 'msg' => 'Ürün bulunamadı.']);
            exit;
        }
        $name  = trim($_POST['name'] ?? '');
        if ($name === '') {
            echo json_encode(['ok' => false, 'msg' => 'Ürün adı zorunludur.']);
            exit;
        }
        $desc   = trim(vx_clean_html(trim($_POST['description'] ?? ''), 'basic'));
        $active = (isset($_POST['is_active']) && $_POST['is_active'] === '0') ? 0 : 1;
        $price  = max(0, (float)($_POST['price'] ?? 0));

        if (!empty($_FILES['file']['name'])) {
            $saved = product_save_file($_FILES['file'], $uploadsDir);
            if (!$saved['ok']) {
                echo json_encode($saved);
                exit;
            }
            $oldPath = $uploadsDir . DIRECTORY_SEPARATOR . $prod['file_path'];
            if (is_file($oldPath)) @unlink($oldPath);
            $pdo->prepare("UPDATE products SET name=?, description=?, file_name=?, file_path=?, file_size=?, is_active=?, price=? WHERE id=?")
                ->execute([$name, $desc, $saved['file_name'], $saved['file_path'], $saved['file_size'], $active, $price, $id]);
        } else {
            $pdo->prepare("UPDATE products SET name=?, description=?, is_active=?, price=? WHERE id=?")
                ->execute([$name, $desc, $active, $price, $id]);
        }
        echo json_encode(['ok' => true, 'msg' => 'Ürün güncellendi.']);
        exit;
    }

    // ── Sil ──────────────────────────────────────────────────
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $prod = $st->fetch();
        if (!$prod) {
            echo json_encode(['ok' => false, 'msg' => 'Ürün bulunamadı.']);
            exit;
        }
        $oldPath = $uploadsDir . DIRECTORY_SEPARATOR . $prod['file_path'];
        if (is_file($oldPath)) @unlink($oldPath);
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM user_products WHERE product_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM download_tokens WHERE product_id = ?")->execute([$id]);
        echo json_encode(['ok' => true, 'msg' => 'Ürün silindi.']);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Bilinmeyen işlem.']);

} catch (Throwable $e) {
    error_log($e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Sunucu hatası oluştu.']);
}

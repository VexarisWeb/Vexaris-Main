<?php
require_once __DIR__ . '/../core/db.php';
boot_session();
require_admin();
if (!has_permission('versions')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Bu işlem için yetkin yok.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$pdo    = db();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (!$pdo) {
    echo json_encode(['ok' => false, 'msg' => 'Veritabanı bağlantısı kurulamadı.']);
    exit;
}

function ver_sanitize(string $v): string {
    $v = trim($v);
    if (!preg_match('/^\d+\.\d+(\.\d+)?$/', $v)) return '';
    return $v;
}

function ver_human_size($bytes): string {
    $bytes = (int)$bytes;
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)       return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

/* Sürüm paketi her lisanslı mağazaya dağıtılıp mağazanın webroot'unun
   üzerine açılıyor. Bu yüzden yükleme/değiştirme/silme işlemleri SADECE
   admin rolüne açıktır; 'versions' izni olan yönetici rolleri yeterli değildir. */
function ver_require_admin(string $msg = 'Bu işlem için admin yetkisi gerekli.'): void {
    if (user_role() === 'admin' && is_admin()) return;
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    try {
        $rows = $pdo->query("SELECT v.*,
            (SELECT COUNT(*) FROM update_events e WHERE e.version_id=v.id AND e.event_type='installed') AS installed_count,
            (SELECT COUNT(*) FROM update_events e WHERE e.version_id=v.id AND e.event_type='delayed')   AS delayed_count
            FROM versions v ORDER BY v.created_at DESC")->fetchAll();
        echo json_encode(['ok' => true, 'versions' => $rows]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => 'Sorgu hatası.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'stats') {
    $id = (int)($_GET['id'] ?? 0);
    try {
        $v = $pdo->prepare("SELECT * FROM versions WHERE id=?");
        $v->execute([$id]);
        $row = $v->fetch();
        if (!$row) {
            echo json_encode(['ok' => false, 'msg' => 'Sürüm bulunamadı.']);
            exit;
        }
        $ev = $pdo->prepare("SELECT event_type, domain, delay_days, created_at FROM update_events WHERE version_id=? ORDER BY created_at DESC LIMIT 200");
        $ev->execute([$id]);
        $events = $ev->fetchAll();
        $domains = [];
        foreach ($events as $e) {
            if ($e['event_type'] === 'installed' && !in_array($e['domain'], $domains, true)) $domains[] = $e['domain'];
        }
        $last14 = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-$i days"));
            $cntI = $pdo->prepare("SELECT COUNT(*) FROM update_events WHERE version_id=? AND event_type='installed' AND DATE(created_at)=?");
            $cntI->execute([$id, $day]);
            $cntD = $pdo->prepare("SELECT COUNT(*) FROM update_events WHERE version_id=? AND event_type='delayed' AND DATE(created_at)=?");
            $cntD->execute([$id, $day]);
            $last14[] = ['date' => $day, 'installed' => (int)$cntI->fetchColumn(), 'delayed' => (int)$cntD->fetchColumn()];
        }
        $delayed = $pdo->prepare("SELECT COUNT(*) FROM update_events WHERE version_id=? AND event_type='delayed'");
        $delayed->execute([$id]);
        echo json_encode([
            'ok'        => true,
            'version'   => $row,
            'events'    => $events,
            'domains'   => $domains,
            'installed' => count($domains),
            'delayed'   => (int)$delayed->fetchColumn(),
            'last14'    => $last14,
        ]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => 'Sorgu hatası.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Geçersiz istek.']);
    exit;
}

csrf_check();

switch ($action) {

    case 'create':
        ver_require_admin();
        $ver = ver_sanitize((string)($_POST['version'] ?? ''));
        if ($ver === '') {
            echo json_encode(['ok' => false, 'msg' => 'Geçersiz sürüm numarası. Örnek: 1.0.0']);
            exit;
        }
        $desc = trim((string)($_POST['description'] ?? ''));
        if (mb_strlen($desc, 'UTF-8') > 1000) {
            echo json_encode(['ok' => false, 'msg' => 'Açıklama en fazla 1000 karakter olabilir.']);
            exit;
        }
        $pid = (int)($_POST['product_id'] ?? 0);
        if ($pid > 0) {
            $chk = $pdo->prepare("SELECT id FROM products WHERE id=? LIMIT 1");
            $chk->execute([$pid]);
            if (!$chk->fetch()) { echo json_encode(['ok' => false, 'msg' => 'Seçilen ürün bulunamadı.']); exit; }
        } else { $pid = null; }
        if (empty($_FILES['update_file']['tmp_name'])) {
            echo json_encode(['ok' => false, 'msg' => 'Güncelleme dosyası gerekli.']);
            exit;
        }
        $f = $_FILES['update_file'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'msg' => 'Dosya yüklenemedi (hata: ' . $f['error'] . ').']);
            exit;
        }
        if ($f['size'] > 1073741824) {
            echo json_encode(['ok' => false, 'msg' => 'Dosya 1 GB üzeri olamaz.']);
            exit;
        }
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['zip'], true)) {
            echo json_encode(['ok' => false, 'msg' => 'Sadece zip dosyası kabul edilir.']);
            exit;
        }
        $dir = __DIR__ . '/../uploads/versions/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $sn = bin2hex(random_bytes(16)) . '.zip';
        $dp = $dir . $sn;
        if (!move_uploaded_file($f['tmp_name'], $dp)) {
            echo json_encode(['ok' => false, 'msg' => 'Dosya kaydedilemedi.']);
            exit;
        }
        try {
            // Artifact bütünlüğü: mağaza tarafı (ve ileride imza) sha256'ya
            // göre doğrulayabilsin.
            $sha = hash_file('sha256', $dp);
            $pdo->prepare("INSERT INTO versions (version, description, file_name, file_path, file_size, product_id, is_active, sha256) VALUES (?,?,?,?,?,?,1,?)")
                ->execute([$ver, $desc ?: null, $f['name'], '/uploads/versions/' . $sn, ver_human_size($f['size']), $pid, $sha ?: null]);
            $vid = (int)$pdo->lastInsertId();
            if ($pid !== null) {
                $us = $pdo->prepare("SELECT user_id FROM user_products WHERE product_id=?");
                $us->execute([$pid]);
                $prodName = $pdo->prepare("SELECT name FROM products WHERE id=?");
                $prodName->execute([$pid]);
                $pname = (string)$prodName->fetchColumn();
                foreach ($us->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                    try { $pdo->prepare("INSERT INTO notifications (user_id,type,title,message) VALUES (?,?,?,?)")->execute([$uid,'update',"Yeni güncelleme: $ver", ($pname ? "$pname icin " : "") . "$ver surumu yayinlandi. " . ($desc ?: "Indirme sayfanizdan guncelleyebilirsiniz.")]); } catch (Throwable $e) {}
                }
            }
            echo json_encode(['ok' => true, 'msg' => 'Sürüm eklendi.', 'id' => $vid]);
        } catch (Throwable $e) {
            @unlink($dp);
            echo json_encode(['ok' => false, 'msg' => 'Sürüm eklenemedi (aynı sürüm zaten mevcut olabilir).']);
        }
        break;

    case 'update':
        ver_require_admin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => 'Geçersiz ID.']); exit; }
        $desc = trim((string)($_POST['description'] ?? ''));
        if (mb_strlen($desc, 'UTF-8') > 1000) {
            echo json_encode(['ok' => false, 'msg' => 'Açıklama en fazla 1000 karakter olabilir.']);
            exit;
        }
        $active = (int)($_POST['is_active'] ?? -1);
        try {
            if ($active === 0 || $active === 1) {
                $pdo->prepare("UPDATE versions SET description=?, is_active=? WHERE id=?")->execute([$desc ?: null, $active, $id]);
            } else {
                $pdo->prepare("UPDATE versions SET description=? WHERE id=?")->execute([$desc ?: null, $id]);
            }
            echo json_encode(['ok' => true, 'msg' => 'Sürüm güncellendi.']);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'msg' => 'Güncelleme başarısız.']);
        }
        break;

    case 'toggle':
        ver_require_admin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => 'Geçersiz ID.']); exit; }
        try {
            $pdo->prepare("UPDATE versions SET is_active = 1 - is_active WHERE id=?")->execute([$id]);
            echo json_encode(['ok' => true, 'msg' => 'Durum güncellendi.']);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'msg' => 'İşlem başarısız.']);
        }
        break;

    case 'delete':
        ver_require_admin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => 'Geçersiz ID.']); exit; }
        try {
            $st = $pdo->prepare("SELECT version, file_path FROM versions WHERE id=?");
            $st->execute([$id]);
            $vrow = $st->fetch() ?: [];
            $fp = (string)($vrow['file_path'] ?? '');
            $pdo->prepare("DELETE FROM update_events WHERE version_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM versions WHERE id=?")->execute([$id]);
            audit_log($pdo, (int)(current_user()['id'] ?? 0), 'version_delete', 'version', (string)$id, (string)($vrow['version'] ?? ''));
            if ($fp) {
                $abs = __DIR__ . '/..' . $fp;
                if (is_file($abs)) @unlink($abs);
            }
            echo json_encode(['ok' => true, 'msg' => 'Sürüm silindi.']);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'msg' => 'Silme başarısız.']);
        }
        break;

    default:
        echo json_encode(['ok' => false, 'msg' => 'Bilinmeyen işlem.']);
}
<?php
require_once __DIR__ . '/../core/db.php';
boot_session();
require_admin();
if (!has_permission('licenses')) {
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

$featureKeys = ['core', 'theme_azure', 'theme_ember', 'tickets', 'mail_system', 'admin_panel', 'seo', 'announcements', 'coupons', 'custom_pages', 'references', 'reviews', 'discord_integration', 'roles_permissions', 'updates'];

function lic_sanitize_domains(?array $domains): array {
    $out = [];
    foreach ((array)$domains as $d) {
        $d = strtolower(trim((string)$d));
        $d = preg_replace('#^https?://#i', '', $d);
        $d = rtrim($d, '/');
        $d = preg_replace('/[^a-z0-9.\-*]/', '', $d);
        if ($d === '' || strlen($d) > 253) continue;
        if (!in_array($d, $out, true)) $out[] = $d;
    }
    return $out;
}

function lic_plan_slug($v): string {
    $v = strtolower(trim((string)$v));
    return in_array($v, ['free', 'starter', 'professional', 'enterprise'], true) ? $v : 'free';
}
function lic_feature_map(?array $checked, array $keys): array {
    $map = [];
    foreach ($keys as $k) $map[$k] = false;
    if ($checked === null) {
        foreach ($keys as $k) $map[$k] = true;
        return $map;
    }
    $checked = array_map('strval', (array)$checked);
    foreach ($keys as $k) {
        if (in_array($k, $checked, true)) $map[$k] = true;
    }
    return $map;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $search  = trim($_GET['q'] ?? '');
    $page    = max(1, (int)($_GET['p'] ?? 1));
    $perPage = 15;
    $offset  = ($page - 1) * $perPage;

    try {
        if ($search) {
            $like = '%' . $search . '%';
            $cnt  = $pdo->prepare("SELECT COUNT(*) FROM licenses WHERE customer_name LIKE ? OR license_key LIKE ?");
            $cnt->execute([$like, $like]);
            $total = (int)$cnt->fetchColumn();
            $stmt  = $pdo->prepare("SELECT * FROM licenses WHERE customer_name LIKE ? OR license_key LIKE ? ORDER BY id DESC LIMIT $perPage OFFSET $offset");
            $stmt->execute([$like, $like]);
        } else {
            $total = (int)$pdo->query("SELECT COUNT(*) FROM licenses")->fetchColumn();
            $stmt  = $pdo->prepare("SELECT * FROM licenses ORDER BY id DESC LIMIT $perPage OFFSET $offset");
            $stmt->execute();
        }
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $list = json_decode((string)$r['allowed_domains'], true);
            $r['domain_count'] = is_array($list) ? count($list) : 0;
            $feat = json_decode((string)$r['features'], true);
            $r['features'] = is_array($feat) ? $feat : [];
        }
        echo json_encode([
            'ok'         => true,
            'licenses'   => $rows,
            'total'      => $total,
            'totalPages' => max(1, (int)ceil($total / $perPage)),
            'page'       => $page,
            'featureKeys'=> $featureKeys,
        ]);
    } catch (Exception $e) {
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
        $customer = trim((string)($_POST['customer_name'] ?? ''));
        if ($customer === '' || strlen($customer) > 255) {
            echo json_encode(['ok' => false, 'msg' => 'Müşteri adı gerekli (en fazla 255 karakter).']);
            exit;
        }
        $domains = lic_sanitize_domains($_POST['domains'] ?? null);
        if (!$domains) {
            echo json_encode(['ok' => false, 'msg' => 'En az bir geçerli domain ekleyin.']);
            exit;
        }
        $features = lic_feature_map($_POST['features'] ?? null, $featureKeys);
        $features['plan'] = lic_plan_slug($_POST['plan'] ?? 'free');
        try {
            $key = bin2hex(random_bytes(24));
            $pdo->prepare("INSERT INTO licenses (customer_name, license_key, allowed_domains, status, features, suspended_until) VALUES (?,?,?,'active',?,NULL)")
                ->execute([$customer, $key, json_encode($domains), json_encode($features)]);
            echo json_encode(['ok' => true, 'msg' => 'Lisans oluşturuldu.', 'license_key' => $key]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Lisans oluşturulamadı.']);
        }
        break;

    case 'update':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => 'Geçersiz ID.']); exit; }
        $customer = trim((string)($_POST['customer_name'] ?? ''));
        if ($customer === '' || strlen($customer) > 255) {
            echo json_encode(['ok' => false, 'msg' => 'Müşteri adı gerekli (en fazla 255 karakter).']);
            exit;
        }
        $domains = lic_sanitize_domains($_POST['domains'] ?? null);
        if (!$domains) {
            echo json_encode(['ok' => false, 'msg' => 'En az bir geçerli domain ekleyin.']);
            exit;
        }
        $features = lic_feature_map($_POST['features'] ?? null, $featureKeys);
        $newPlan = lic_plan_slug($_POST['plan'] ?? 'free');
        $features['plan'] = $newPlan;
        $features['plan_auto'] = 0;
        try {
            $curF = $pdo->prepare("SELECT features FROM licenses WHERE id=?");
            $curF->execute([$id]);
            $oldFeat = json_decode((string)$curF->fetchColumn(), true);
            if (is_array($oldFeat) && ($oldFeat['plan'] ?? 'free') === $newPlan && !empty($oldFeat['plan_auto'])) $features['plan_auto'] = 1;
            $pdo->prepare("UPDATE licenses SET customer_name=?, allowed_domains=?, features=? WHERE id=?")
                ->execute([$customer, json_encode($domains), json_encode($features), $id]);
            echo json_encode(['ok' => true, 'msg' => 'Lisans güncellendi.']);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Güncelleme başarısız.']);
        }
        break;

    case 'features':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => 'Geçersiz ID.']); exit; }
        $features = lic_feature_map($_POST['features'] ?? null, $featureKeys);
        try {
            $cur = $pdo->prepare("SELECT features FROM licenses WHERE id=?");
            $cur->execute([$id]);
            $oldF = json_decode((string)$cur->fetchColumn(), true);
            if (is_array($oldF) && isset($oldF['plan'])) $features['plan'] = lic_plan_slug($oldF['plan']);
            $pdo->prepare("UPDATE licenses SET features=? WHERE id=?")->execute([json_encode($features), $id]);
            echo json_encode(['ok' => true, 'msg' => 'Özellikler güncellendi.']);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Güncelleme başarısız.']);
        }
        break;

    case 'suspend':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => 'Geçersiz ID.']); exit; }
        $until = '';
        $days  = (int)($_POST['days'] ?? 0);
        if ($days > 0) {
            $until = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        } else {
            $raw = trim((string)($_POST['until'] ?? ''));
            if ($raw !== '') {
                $ts = strtotime($raw);
                if ($ts) $until = date('Y-m-d H:i:s', $ts);
            }
        }
        if ($until === '') {
            echo json_encode(['ok' => false, 'msg' => 'Bir tarih veya gün seçin.']);
            exit;
        }
        try {
            $pdo->prepare("UPDATE licenses SET status='suspended', suspended_until=? WHERE id=?")->execute([$until, $id]);
            echo json_encode(['ok' => true, 'msg' => 'Lisans askıya alındı.', 'until' => $until]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'İşlem başarısız.']);
        }
        break;

    case 'resume':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => 'Geçersiz ID.']); exit; }
        try {
            $pdo->prepare("UPDATE licenses SET status='active', suspended_until=NULL WHERE id=?")->execute([$id]);
            echo json_encode(['ok' => true, 'msg' => 'Lisans hemen açıldı.']);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'İşlem başarısız.']);
        }
        break;

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => 'Geçersiz ID.']); exit; }
        try {
            $pdo->prepare("DELETE FROM licenses WHERE id=?")->execute([$id]);
            echo json_encode(['ok' => true, 'msg' => 'Lisans silindi.']);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Silme başarısız.']);
        }
        break;

    default:
        echo json_encode(['ok' => false, 'msg' => 'Bilinmeyen işlem.']);
}

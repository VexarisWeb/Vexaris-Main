<?php
require_once __DIR__ . '/../core/db.php';
boot_session();
require_admin();
if (!has_permission('users')) {
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

// ── Kullanıcıları Listele (GET) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $search  = trim($_GET['q'] ?? '');
    $page    = max(1, (int)($_GET['p'] ?? 1));
    $perPage = 15;
    $offset  = ($page - 1) * $perPage;

    try {
        if ($search) {
            $like  = '%' . $search . '%';

            $stmtC = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username LIKE ? OR email LIKE ?");
            $stmtC->execute([$like, $like]);
            $total = (int)$stmtC->fetchColumn();

            $stmt = $pdo->prepare("SELECT id, username, email, is_admin, role, balance, is_active, ban_reason, created_at FROM users WHERE username LIKE ? OR email LIKE ? ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
            $stmt->execute([$like, $like]);
        } else {
            $stmtC = $pdo->query("SELECT COUNT(*) FROM users");
            $total = (int)$stmtC->fetchColumn();

            $stmt = $pdo->prepare("SELECT id, username, email, is_admin, role, balance, is_active, ban_reason, created_at FROM users ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
            $stmt->execute();
        }

        $users      = $stmt->fetchAll();
        $totalPages = max(1, (int)ceil($total / $perPage));

        // Multi-hesap riski: bağlantılı hesap sayısı (tek sorgu).
        try {
            require_once __DIR__ . '/../core/multi.php';
            multi_ensure_tables($pdo);
            $risk = multi_risk_map($pdo, array_column($users, 'id'));
            foreach ($users as &$uu) $uu['risk'] = $risk[(int)$uu['id']] ?? 0;
            unset($uu);
        } catch (Throwable $e2) {}

        echo json_encode([
            'ok'          => true,
            'users'       => $users,
            'total'       => $total,
            'totalPages'  => $totalPages,
            'page'        => $page,
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => 'Sorgu hatası.']);
    }
    exit;
}

// ── POST işlemleri ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Geçersiz istek.']);
    exit;
}

csrf_check();

$id = (int)($_POST['id'] ?? 0);

// ── Bağlantılı hesaplar (GET/POST) ───────────────────────────
if ($action === 'links') {
    if ($id <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'Geçersiz kullanıcı ID.']);
        exit;
    }
    try {
        require_once __DIR__ . '/../core/multi.php';
        multi_ensure_tables($pdo);
        $linked = multi_linked_ids($pdo, $id);
        $detail = [];
        if ($linked) {
            $in = implode(',', array_map('intval', array_keys($linked)));
            $rows = $pdo->query("SELECT id, username, email, created_at FROM users WHERE id IN ($in)")->fetchAll();
            foreach ($rows as $r) {
                $r['signal'] = $linked[(int)$r['id']] ?? '';
                $detail[] = $r;
            }
        }
        $flags = $pdo->prepare("SELECT linked_user_id, `sig` AS `signal`, detail, created_at FROM multi_flags WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
        $flags->execute([$id]);
        echo json_encode(['ok' => true, 'linked' => $detail, 'flags' => $flags->fetchAll()]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => 'Sorgu hatası.']);
    }
    exit;
}

if ($id <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Geçersiz kullanıcı ID.']);
    exit;
}

// Kendini silemesin / yetkisini alamasın
$selfId = (int)(current_user()['id'] ?? 0);
$myRole = user_role();
$sysOwnerId = 0;
try { $sysOwnerId = (int)$pdo->query("SELECT id FROM users WHERE is_admin=1 ORDER BY id ASC LIMIT 1")->fetchColumn(); } catch (Exception $e) {}
$isSysOwner = function (int $uid) use ($sysOwnerId): bool { return $sysOwnerId > 0 && $uid === $sysOwnerId; };
$denyOwnerMsg = 'The system owner account is protected.';

switch ($action) {

    // ── Manuel Ban (itiraz/info@ üzerinden) ────────────────
    case 'ban':
        if ($myRole !== 'admin') {
            echo json_encode(['ok' => false, 'msg' => 'Banlamak için admin yetkisi gerekli.']);
            exit;
        }
        if ($id === $selfId) {
            echo json_encode(['ok' => false, 'msg' => 'Kendini banlayamazsın.']);
            exit;
        }
        if ($isSysOwner($id)) {
            echo json_encode(['ok' => false, 'msg' => $denyOwnerMsg]);
            exit;
        }
        try {
            $pdo->prepare("UPDATE users SET is_active = 0, ban_reason = 'manual' WHERE id = ?")->execute([$id]);
            echo json_encode(['ok' => true, 'msg' => 'Kullanıcı banlandı.']);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'İşlem başarısız.']);
        }
        break;

    // ── Ban kaldır (itiraz sonrası) ──────────────────────────
    case 'unban':
        if ($myRole !== 'admin') {
            echo json_encode(['ok' => false, 'msg' => 'Ban kaldırmak için admin yetkisi gerekli.']);
            exit;
        }
        try {
            $pdo->prepare("UPDATE users SET is_active = 1, ban_reason = '' WHERE id = ?")->execute([$id]);
            echo json_encode(['ok' => true, 'msg' => 'Ban kaldırıldı, hesap aktif.']);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'İşlem başarısız.']);
        }
        break;

    // ── Kullanıcı Adı Değiştir ──────────────────────────────
    case 'rename':
        $newName = trim($_POST['username'] ?? '');
        if (strlen($newName) < 3 || strlen($newName) > 60) {
            echo json_encode(['ok' => false, 'msg' => 'Kullanıcı adı 3–60 karakter olmalı.']);
            exit;
        }
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $newName)) {
            echo json_encode(['ok' => false, 'msg' => 'Kullanıcı adında geçersiz karakter var.']);
            exit;
        }
        try {
            // Benzersizlik kontrolü
            $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $chk->execute([$newName, $id]);
            if ($chk->fetch()) {
                echo json_encode(['ok' => false, 'msg' => 'Bu kullanıcı adı zaten kullanılıyor.']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->execute([$newName, $id]);
            echo json_encode(['ok' => true, 'msg' => 'Kullanıcı adı güncellendi.', 'username' => $newName]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Güncelleme başarısız.']);
        }
        break;

    // ── Rol Değiştir ────────────────────────────────────────
    case 'set_role':
        if ($myRole !== 'admin') {
            echo json_encode(['ok' => false, 'msg' => 'Rol atamak için admin yetkisi gerekli.']);
            exit;
        }
        if ($id === $selfId) {
            echo json_encode(['ok' => false, 'msg' => 'Kendi rolünü değiştiremezsin.']);
            exit;
        }
        if ($isSysOwner($id)) {
            echo json_encode(['ok' => false, 'msg' => $denyOwnerMsg]);
            exit;
        }
        $newRole = trim($_POST['role'] ?? '');
        if (!in_array($newRole, ROLES, true)) {
            echo json_encode(['ok' => false, 'msg' => 'Geçersiz rol.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("UPDATE users SET role = ?, is_admin = ? WHERE id = ?");
            $stmt->execute([$newRole, $newRole === 'admin' ? 1 : 0, $id]);
            echo json_encode([
                'ok'      => true,
                'role'    => $newRole,
                'is_admin'=> $newRole === 'admin' ? 1 : 0,
                'msg'     => 'Rol güncellendi: ' . role_label($newRole) . '.',
            ]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Rol güncellenemedi.']);
        }
        break;

    // ── Kredi Gönder ────────────────────────────────────────
    case 'send_credit':
        if (!is_admin()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'msg' => 'Bu işlem için yetkin yok.']);
            exit;
        }
        if ($id === $selfId) {
            echo json_encode(['ok' => false, 'msg' => 'Kendine kredi gönderemezsin.']);
            exit;
        }
        $amount = (float)($_POST['amount'] ?? 0);
        $note   = trim($_POST['note'] ?? '');
        if ($amount <= 0 || $amount > 1000000) {
            echo json_encode(['ok' => false, 'msg' => 'Geçersiz tutar.']);
            exit;
        }
        try {
            $row = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
            $row->execute([$id]);
            $balance = (float)$row->fetchColumn();
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $id]);
            $desc = $note !== '' ? $note : 'Admin kredi gönderimi';
            $pdo->prepare("INSERT INTO balance_log (user_id, amount, type, description) VALUES (?,?,?,?)")
                ->execute([$id, $amount, 'admin', $desc]);
            echo json_encode([
                'ok'      => true,
                'balance' => $balance + $amount,
                'msg'     => sprintf('Kredi gönderildi: +₺%.2f', $amount),
            ]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Kredi gönderilirken hata oluştu.']);
        }
        break;

    // ── Kullanıcı Sil ──────────────────────────────────────
    case 'delete':
        if ($id === $selfId) {
            echo json_encode(['ok' => false, 'msg' => 'Kendi hesabını silemezsin.']);
            exit;
        }
        if ($isSysOwner($id)) {
            echo json_encode(['ok' => false, 'msg' => $denyOwnerMsg]);
            exit;
        }
        try {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            echo json_encode(['ok' => true, 'msg' => 'Kullanıcı silindi.']);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Silme işlemi başarısız.']);
        }
        break;

    default:
        echo json_encode(['ok' => false, 'msg' => 'Bilinmeyen işlem.']);
}

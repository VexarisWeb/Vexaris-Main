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
    } catch (Throwable $e) {
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

// ── Multi-hesap grubu işlemleri ──────────────────────────────
if ($action === 'multi_group') {
    require_once __DIR__ . '/../core/multi.php';
    require_once __DIR__ . '/../core/risk.php';
    multi_ensure_tables($pdo);
    $gkey = (string)($_POST['gkey'] ?? '');
    $op = (string)($_POST['op'] ?? '');
    if ($gkey === '' || !in_array($op, ['ban', 'dismiss', 'bypass'], true)) {
        echo json_encode(['ok' => false, 'msg' => 'Geçersiz istek.']);
        exit;
    }
    $st = $pdo->prepare("SELECT gkey, member_ids, member_count FROM multi_groups WHERE gkey = ? LIMIT 1");
    $st->execute([$gkey]);
    $grp = $st->fetch();
    if (!$grp) { echo json_encode(['ok' => false, 'msg' => 'Grup bulunamadı.']); exit; }
    $memberIds = array_values(array_filter(array_map('intval', explode(',', (string)$grp['member_ids']))));
    if ($op === 'dismiss') {
        $pdo->prepare("UPDATE multi_groups SET status = 'dismissed' WHERE gkey = ?")->execute([$gkey]);
        $pdo->prepare("DELETE FROM multi_alerts WHERE gkey = ?")->execute([$gkey]);
        if (function_exists('audit_log')) audit_log($pdo, (int)(current_user()["id"] ?? 0), 'multi_dismiss', 'multi_group', $gkey, 'n=' . count($memberIds));
        echo json_encode(['ok' => true, 'msg' => 'Grup yoksayıldı.']);
        exit;
    }
    if ($op === 'bypass') {
        $list = multi_bypass_emails(true);
        $added = 0;
        foreach ($memberIds as $mid) {
            $q = $pdo->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
            $q->execute([$mid]);
            $em = strtolower(trim((string)$q->fetchColumn()));
            if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL) && !in_array($em, $list, true)) { $list[] = $em; $added++; }
        }
        if ($added > 0) {
            setting_set('multi_bypass_emails', implode("\n", $list));
            multi_bypass_emails(true);
        }
        $pdo->prepare("UPDATE multi_groups SET status = 'dismissed' WHERE gkey = ?")->execute([$gkey]);
        $pdo->prepare("DELETE FROM multi_alerts WHERE gkey = ?")->execute([$gkey]);
        if (function_exists('audit_log')) audit_log($pdo, (int)(current_user()["id"] ?? 0), 'multi_bypass', 'multi_group', $gkey, 'added=' . $added);
        echo json_encode(['ok' => true, 'msg' => $added . ' e-posta bypass listesine eklendi.']);
        exit;
    }
    $banned = 0; $skipped = 0;
    foreach ($memberIds as $mid) {
        if (multi_is_exempt($pdo, $mid)) { $skipped++; continue; }
        try {
            $q = $pdo->prepare("UPDATE users SET is_active = 0, ban_reason = 'multi' WHERE id = ? AND is_active = 1");
            $q->execute([$mid]);
            if ($q->rowCount() < 1) continue;
            if (function_exists('sessions_kill_all')) { sessions_kill_all($pdo, $mid, false); }
            else {
                $pdo->prepare("UPDATE users SET session_version = session_version + 1 WHERE id = ?")->execute([$mid]);
                $pdo->prepare("UPDATE user_sessions SET revoked = 1 WHERE user_id = ?")->execute([$mid]);
                $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$mid]);
            }
            risk_event_log($pdo, $mid, $gkey, 'manual_ban', 'banned', 'admin_manual', '', '', client_ip());
            try {
                notify_user($mid, 'rejected', 'Account suspended — multiple accounts',
                    'Your account was suspended for violating the one-account-per-person policy. If you believe this is a mistake, contact info@vexaris.net to appeal.');
            } catch (Throwable $e2) {}
            $banned++;
        } catch (Throwable $e) {}
    }
    $pdo->prepare("UPDATE multi_groups SET status = 'banned' WHERE gkey = ?")->execute([$gkey]);
    $pdo->prepare("DELETE FROM multi_alerts WHERE gkey = ?")->execute([$gkey]);
    if (function_exists('audit_log')) audit_log($pdo, (int)(current_user()["id"] ?? 0), 'multi_ban', 'multi_group', $gkey, 'banned=' . $banned . ' skipped=' . $skipped);
    echo json_encode(['ok' => true, 'msg' => $banned . ' hesap banlandı' . ($skipped > 0 ? ', ' . $skipped . ' muaf atlandı' : '') . '.']);
    exit;
}

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
        $groups = [];
        $gs = $pdo->prepare("SELECT gkey, gtype, member_count, active_count, ip_set, reason, status, last_seen FROM multi_groups WHERE FIND_IN_SET(?, member_ids) ORDER BY last_seen DESC LIMIT 10");
        $gs->execute([$id]);
        foreach ($gs->fetchAll() as $g) {
            $g['ip_set'] = (string)$g['ip_set'];
            $groups[] = $g;
        }
        $vidRow = $pdo->prepare("SELECT vid, bits, country, last_seen FROM user_devices WHERE user_id = ? AND vid <> '' ORDER BY last_seen DESC LIMIT 1");
        $vidRow->execute([$id]);
        echo json_encode(['ok' => true, 'linked' => $detail, 'flags' => $flags->fetchAll(), 'groups' => $groups, 'device' => ($vidRow->fetch() ?: null)]);
    } catch (Throwable $e) {
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
try { $sysOwnerId = (int)$pdo->query("SELECT id FROM users WHERE is_admin=1 ORDER BY id ASC LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}
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
            // Ban: mevcut oturumlar ve "remember me" tokenları düşsün.
            // Aksi halde banlanan kullanıcı açık oturumuyla panele girmeye devam eder.
            if (function_exists('sessions_kill_all')) {
                sessions_kill_all($pdo, $id, false);
            } else {
                $pdo->prepare("UPDATE users SET session_version = session_version + 1 WHERE id = ?")->execute([$id]);
                $pdo->prepare("UPDATE user_sessions SET revoked = 1 WHERE user_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$id]);
            }
            if (function_exists('audit_log')) audit_log($pdo, (int)$selfId, 'ban', 'users', (string)$id, 'manual');
            echo json_encode(['ok' => true, 'msg' => 'Kullanıcı banlandı.']);
        } catch (Throwable $e) {
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
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'msg' => 'İşlem başarısız.']);
        }
        break;

    // ── Kullanıcı Adı Değiştir ──────────────────────────────
    case 'rename':
        if ($myRole !== 'admin') {
            echo json_encode(['ok' => false, 'msg' => 'Kullanıcı adı değiştirmek için admin yetkisi gerekli.']);
            exit;
        }
        if ($isSysOwner($id)) {
            echo json_encode(['ok' => false, 'msg' => $denyOwnerMsg]);
            exit;
        }
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
        } catch (Throwable $e) {
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
            $stmt = $pdo->prepare("UPDATE users SET role = ?, is_admin = ?, session_version = session_version + 1 WHERE id = ?");
            $stmt->execute([$newRole, $newRole === 'admin' ? 1 : 0, $id]);
            if (function_exists('audit_log')) audit_log($pdo, (int)($selfId ?? 0), 'set_role', 'users', (string)$id, $newRole);
            echo json_encode([
                'ok'      => true,
                'role'    => $newRole,
                'is_admin'=> $newRole === 'admin' ? 1 : 0,
                'msg'     => 'Rol güncellendi: ' . role_label($newRole) . '.',
            ]);
        } catch (Throwable $e) {
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
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'msg' => 'Kredi gönderilirken hata oluştu.']);
        }
        break;

    // ── Kullanıcı Sil ──────────────────────────────────────
    case 'delete':
        // Silme yetkisi yalnızca admin'de: moderator/yonetici rolleri
        // 'users' izni taşıdığı için silme yetkisini de devralmamalı.
        if ($myRole !== 'admin') {
            echo json_encode(['ok' => false, 'msg' => 'Kullanıcı silmek için admin yetkisi gerekli.']);
            exit;
        }
        if ($id === $selfId) {
            echo json_encode(['ok' => false, 'msg' => 'Kendi hesabını silemezsin.']);
            exit;
        }
        if ($isSysOwner($id)) {
            echo json_encode(['ok' => false, 'msg' => $denyOwnerMsg]);
            exit;
        }
        try {
            /* Kullanici silinince bagli kayitlar da temizlenir: aksi halde
               magazası aktif kalir ve ayni e-posta ile yeniden kayit olan
               kisi magazayi devralir (owner=email eslesmesi). */
            $pdo->beginTransaction();
            $delTables = [
                'DELETE FROM remember_tokens WHERE user_id=?',
                'DELETE FROM user_sessions WHERE user_id=?',
                'DELETE FROM oauth_accounts WHERE user_id=?',
                'DELETE FROM email_change_tokens WHERE user_id=?',
                'DELETE FROM password_resets WHERE user_id=?',
                'DELETE FROM activation_tokens WHERE user_id=?',
                'DELETE FROM user_products WHERE user_id=?',
                'DELETE FROM user_subscriptions WHERE user_id=?',
                'DELETE FROM store_tokens WHERE admin_id=?',
                'DELETE FROM api_rate_limits WHERE ip IN (SELECT email FROM users WHERE id=?)',
            ];
            foreach ($delTables as $sql) {
                try { $pdo->prepare($sql)->execute([$id]); } catch (Throwable $e) {}
            }
            try {
                $licDel = $pdo->prepare("SELECT id FROM licenses WHERE user_id=?");
                $licDel->execute([$id]);
                foreach ($licDel->fetchAll(PDO::FETCH_COLUMN) as $licId) {
                    $pdo->prepare("DELETE FROM licenses WHERE id=?")->execute([(int)$licId]);
                }
            } catch (Throwable $e) {}
            /* Kullanicinin magazalari aktif kalmasin: silme islemi kuyruga alinir
               (worker konteyner/DB/volume temizligi yapar). */
            try {
                $uEmail = strtolower(trim((string)($pdo->query("SELECT email FROM users WHERE id=" . (int)$id)->fetchColumn() ?: '')));
                $storeQ = $pdo->prepare("SELECT id, slug, owner, site_name, lang, category FROM hosted_stores WHERE user_id=? OR owner=?");
                $storeQ->execute([$id, $uEmail]);
                foreach ($storeQ->fetchAll() as $srow) {
                    $pdo->prepare("INSERT INTO hosting_jobs (user_id, slug, plan, owner, site_name, lang, kind, status, owner_hash, category) VALUES (?,?, 'free', ?, ?, ?, 'delete','pending','',?)")
                        ->execute([$id, (string)$srow['slug'], (string)$srow['owner'], (string)($srow['site_name'] ?: $srow['slug']), (string)($srow['lang'] ?: 'en'), (string)($srow['category'] ?: 'other')]);
                    $pdo->prepare("UPDATE hosted_stores SET status='deleting' WHERE id=?")->execute([(int)$srow['id']]);
                }
            } catch (Throwable $e) {}
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            if (function_exists('audit_log')) audit_log($pdo, (int)($selfId ?? 0), 'delete', 'users', (string)$id, '');
            $pdo->commit();
            echo json_encode(['ok' => true, 'msg' => 'Kullanıcı silindi.']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['ok' => false, 'msg' => 'Silme işlemi başarısız.']);
        }
        break;

    default:
        echo json_encode(['ok' => false, 'msg' => 'Bilinmeyen işlem.']);
}

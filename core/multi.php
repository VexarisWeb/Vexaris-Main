<?php
// ============================================================
//  Vexaris — Multi-Hesap Koruması
//  Katmanlar: normalize e-posta çakışması, disposable mail engeli,
//  IP başına hesap limiti, cihaz çerezi (vex_did) + tarayıcı
//  parmak izi eşleşmesi, bağlantılı hesap grafiği + admin bayrağı.
// ============================================================

function multi_ensure_tables(\PDO $pdo): void {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_devices (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id    INT UNSIGNED NOT NULL,
            did        CHAR(32) NOT NULL DEFAULT '',
            fp         VARCHAR(64) NOT NULL DEFAULT '',
            ip         VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            event      VARCHAR(12) NOT NULL DEFAULT 'login',
            first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ud_user (user_id),
            KEY idx_ud_did (did),
            KEY idx_ud_fp (fp),
            KEY idx_ud_ip (ip)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS multi_flags (
            id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id        INT UNSIGNED NOT NULL,
            linked_user_id INT UNSIGNED NOT NULL DEFAULT 0,
            sig            VARCHAR(30) NOT NULL DEFAULT '',
            detail         VARCHAR(255) NULL,
            is_read        TINYINT(1) NOT NULL DEFAULT 0,
            created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_mf_user (user_id),
            KEY idx_mf_read (is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        ensure_column($pdo, 'users', 'email_norm', "`email_norm` VARCHAR(160) NULL");
        ensure_column($pdo, 'users', 'reg_ip', "`reg_ip` VARCHAR(45) NULL");
        ensure_column($pdo, 'users', 'ban_reason', "`ban_reason` VARCHAR(20) NOT NULL DEFAULT ''");
        // Eski hesapların normalize e-postasını doldur (parti parti).
        try {
            $st = $pdo->query("SELECT id, email FROM users WHERE email_norm IS NULL OR email_norm = '' LIMIT 500");
            $upd = $pdo->prepare("UPDATE users SET email_norm = ? WHERE id = ?");
            foreach ($st->fetchAll() as $r) {
                $upd->execute([multi_normalize_email((string)$r['email']), (int)$r['id']]);
            }
        } catch (Throwable $e2) {}
    } catch (Throwable $e) {
        error_log('multi_ensure: ' . $e->getMessage());
    }
}

// Gmail/Googlemail: nokta + artı eki yoksay; diğerlerinde artı ekini yoksay.
function multi_normalize_email(string $email): string {
    $e = strtolower(trim($email));
    $parts = explode('@', $e);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') return $e;
    [$local, $domain] = $parts;
    if ($domain === 'googlemail.com') $domain = 'gmail.com';
    if ($domain === 'gmail.com') {
        $local = str_replace('.', '', $local);
    }
    $plus = strpos($local, '+');
    if ($plus !== false) $local = substr($local, 0, $plus);
    return $local . '@' . $domain;
}

// Güvenilir sağlayıcı WHITELIST'i: sadece bunlar kayıt olabilir.
// Temp/tek kullanımlık uzantılarla tek tek uğraşmak yerine ters mantık.
function multi_allowed_domains(): array {
    $base = ['gmail.com','googlemail.com',
        'yahoo.com','yahoo.co.uk','yahoo.fr','yahoo.de','yahoo.es','yahoo.it',
        'outlook.com','outlook.fr','outlook.de','hotmail.com','hotmail.fr','hotmail.de','hotmail.co.uk',
        'live.com','live.fr','live.co.uk','live.de','msn.com',
        'icloud.com','me.com','mac.com',
        'proton.me','protonmail.com','protonmail.ch','pm.me',
        'tuta.com','tuta.io','tutanota.com','tutanota.de',
        'gmx.com','gmx.net','gmx.de','gmx.fr',
        'aol.com','fastmail.com','fastmail.fm',
        'yandex.com','yandex.com.tr','yandex.ru','ya.ru',
        'zoho.com','mail.ru','bk.ru','inbox.ru'];
    try {
        $extra = strtolower((string)setting_get('multi_extra_domains', ''));
        foreach (preg_split('/[\s,;]+/', $extra) as $d) {
            $d = trim($d, " \t\n\r\0\x0B.");
            if ($d !== '' && strpos($d, '.') !== false) $base[] = $d;
        }
    } catch (Throwable $e) {}
    return array_values(array_unique($base));
}

function multi_email_allowed(string $email): bool {
    $e = strtolower(trim($email));
    $at = strrpos($e, '@');
    if ($at === false) return false;
    $domain = trim(substr($e, $at + 1));
    $domain = (string)preg_replace('/:\d+$/', '', $domain);
    return in_array($domain, multi_allowed_domains(), true);
}

function multi_setting(string $key, string $default = ''): string {
    try { return setting_get($key, $default); } catch (Throwable $e) { return $default; }
}

function multi_enabled(): bool {
    return multi_setting('multi_enabled', '1') === '1';
}

// Kalıcı cihaz çerezi: varsa okur, yoksa üretip yazar.
function multi_device_id(): string {
    $did = (string)($_COOKIE['vex_did'] ?? '');
    if (preg_match('/^[0-9a-f]{32}$/', $did)) return $did;
    $did = bin2hex(random_bytes(16));
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    @setcookie('vex_did', $did, ['expires' => time() + 63072000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $secure]);
    $_COOKIE['vex_did'] = $did;
    return $did;
}

function multi_client_fp(): string {
    $fp = strtolower(trim((string)($_POST['fp'] ?? '')));
    return (bool)preg_match('/^[0-9a-f]{8,64}$/', $fp) ? $fp : '';
}

// Kayıt öncesi kontrol. Dönüş: ['ok'=>bool,'msg'=>string,'soft'=>bool,'linked'=>[ids],'signals'=>[...]]
function multi_check_registration(\PDO $pdo, string $email): array {
    $out = ['ok' => true, 'msg' => '', 'soft' => false, 'linked' => [], 'signals' => []];
    if (!multi_enabled()) return $out;
    try {
        multi_ensure_tables($pdo);
        $norm = multi_normalize_email($email);
        // Sert engel 1: whitelist dışı e-posta asla kayıt olamaz.
        if (!multi_email_allowed($email)) {
            return ['ok' => false, 'msg' => 'Only trusted email providers are accepted (Gmail, Yahoo, Outlook/Hotmail, iCloud, Proton, Tuta, GMX, AOL, FastMail, Yandex and similar).', 'soft' => false, 'linked' => [], 'signals' => ['email_domain']];
        }
        // Sert engel 2: normalize çakışma (nokta/plus hilesi).
        $st = $pdo->prepare("SELECT id FROM users WHERE email_norm = ? LIMIT 1");
        $st->execute([$norm]);
        if ($st->fetch()) {
            return ['ok' => false, 'msg' => 'This email is already registered (a similar address exists).', 'soft' => false, 'linked' => [], 'signals' => ['email_norm']];
        }
        // IP / cihaz / parmak izi eşleşmeleri kaydı ENGELLEMEZ —
        // hesap açılır, ardından multi_enforce() multileri anında banlar.
        $ip = client_ip();
        $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE reg_ip = ? AND reg_ip <> '' AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $st->execute([$ip !== '' ? $ip : '0.0.0.0']);
        $ipCount = (int)$st->fetchColumn();
        $did = multi_device_id();
        $linked = [];
        $st = $pdo->prepare("SELECT DISTINCT user_id FROM user_devices WHERE did = ? AND did <> ''");
        $st->execute([$did]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $x) $linked[(int)$x] = 'device_cookie';
        $fp = multi_client_fp();
        if ($fp !== '') {
            $st = $pdo->prepare("SELECT DISTINCT user_id FROM user_devices WHERE fp = ? AND fp <> ''");
            $st->execute([$fp]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $x) $linked[(int)$x] = $linked[(int)$x] ?? 'fingerprint';
        }
        if ($linked || $ipCount > 0) {
            $out['soft'] = true;
            $out['linked'] = array_keys($linked);
            $out['signals'] = array_values(array_unique(array_merge(
                array_values($linked),
                $ipCount > 0 ? ['shared_ip'] : []
            )));
        }
        return $out;
    } catch (Throwable $e) {
        return $out; // fail-open: koruma çalışmazsa kayıt sürer
    }
}

function multi_after_register(\PDO $pdo, int $uid, array $chk): void {
    if ($uid <= 0) return;
    try {
        multi_ensure_tables($pdo);
        $ip = client_ip();
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $pdo->prepare("INSERT INTO user_devices (user_id, did, fp, ip, user_agent, event) VALUES (?,?,?,?,?,'register')")
            ->execute([$uid, multi_device_id(), multi_client_fp(), $ip !== '' ? $ip : null, $ua !== '' ? $ua : null]);
        foreach ((array)($chk['linked'] ?? []) as $lid) {
            multi_flag($pdo, $uid, (int)$lid, 'register_link', 'Kayıtta bağlantılı cihaz: ' . implode(',', (array)($chk['signals'] ?? [])));
        }
        if (!empty($chk['soft']) && empty($chk['linked'])) {
            multi_flag($pdo, $uid, 0, 'shared_ip', 'Kayıt IP daha önce kullanılmış');
        }
    } catch (Throwable $e) {}
}

function multi_after_login(\PDO $pdo, int $uid): void {
    if ($uid <= 0 || !multi_enabled()) return;
    try {
        multi_ensure_tables($pdo);
        $did = multi_device_id();
        $fp = multi_client_fp();
        $ip = client_ip();
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $st = $pdo->prepare("SELECT id FROM user_devices WHERE user_id = ? AND did = ? AND fp = ? LIMIT 1");
        $st->execute([$uid, $did, $fp]);
        $rowId = $st->fetchColumn();
        if ($rowId) {
            $pdo->prepare("UPDATE user_devices SET last_seen = NOW(), ip = ?, user_agent = ? WHERE id = ?")
                ->execute([$ip !== '' ? $ip : null, $ua !== '' ? $ua : null, (int)$rowId]);
        } else {
            $pdo->prepare("INSERT INTO user_devices (user_id, did, fp, ip, user_agent, event) VALUES (?,?,?,?,?,'login')")
                ->execute([$uid, $did, $fp, $ip !== '' ? $ip : null, $ua !== '' ? $ua : null]);
        }
        foreach (multi_linked_ids($pdo, $uid) as $lid => $sig) {
            multi_flag($pdo, $uid, (int)$lid, 'login_link', 'Girişte bağlantı: ' . $sig);
        }
    } catch (Throwable $e) {}
}

// Kendisi hariç bağlantılı kullanıcılar: [id => sinyal]
function multi_linked_ids(\PDO $pdo, int $uid): array {
    $out = [];
    try {
        $me = $pdo->prepare("SELECT email_norm, reg_ip FROM users WHERE id = ? LIMIT 1");
        $me->execute([$uid]);
        $m = $me->fetch() ?: [];
        if (!empty($m['email_norm'])) {
            $st = $pdo->prepare("SELECT id FROM users WHERE email_norm = ? AND id <> ?");
            $st->execute([$m['email_norm'], $uid]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $x) $out[(int)$x] = 'email_norm';
        }
        if (!empty($m['reg_ip'])) {
            $st = $pdo->prepare("SELECT id FROM users WHERE reg_ip = ? AND id <> ? LIMIT 50");
            $st->execute([$m['reg_ip'], $uid]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $x) $out[(int)$x] = $out[(int)$x] ?? 'shared_ip';
        }
        $st = $pdo->prepare("SELECT did, fp FROM user_devices WHERE user_id = ?");
        $st->execute([$uid]);
        foreach ($st->fetchAll() as $d) {
            if (!empty($d['did'])) {
                $q = $pdo->prepare("SELECT DISTINCT user_id FROM user_devices WHERE did = ? AND user_id <> ? LIMIT 50");
                $q->execute([$d['did'], $uid]);
                foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $x) $out[(int)$x] = $out[(int)$x] ?? 'device_cookie';
            }
            if (!empty($d['fp'])) {
                $q = $pdo->prepare("SELECT DISTINCT user_id FROM user_devices WHERE fp = ? AND user_id <> ? LIMIT 50");
                $q->execute([$d['fp'], $uid]);
                foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $x) $out[(int)$x] = $out[(int)$x] ?? 'fingerprint';
            }
        }
    } catch (Throwable $e) {}
    return $out;
}

function multi_flag(\PDO $pdo, int $uid, int $linkedId, string $signal, string $detail = ''): void {
    if ($uid <= 0) return;
    try {
        // Aynı eşleşmeyi 30 günde bir kez bayrakla (spam önleme).
        $st = $pdo->prepare("SELECT COUNT(*) FROM multi_flags WHERE user_id = ? AND linked_user_id = ? AND sig = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $st->execute([$uid, $linkedId, $signal]);
        if ((int)$st->fetchColumn() > 0) return;
        $pdo->prepare("INSERT INTO multi_flags (user_id, linked_user_id, sig, detail) VALUES (?,?,?,?)")
            ->execute([$uid, $linkedId, $signal, mb_substr($detail, 0, 255)]);
    } catch (Throwable $e) {}
}

function multi_risk_map(\PDO $pdo, array $uids): array {
    $out = [];
    $uids = array_values(array_unique(array_map('intval', $uids)));
    if (!$uids) return $out;
    try {
        $in = implode(',', $uids);
        $rows = $pdo->query("SELECT u.id,
            ((SELECT COUNT(DISTINCT u2.id) FROM users u2 WHERE u2.id <> u.id AND ((u.reg_ip IS NOT NULL AND u.reg_ip <> '' AND u2.reg_ip = u.reg_ip) OR (u.email_norm IS NOT NULL AND u.email_norm <> '' AND u2.email_norm = u.email_norm)))
            + (SELECT COUNT(DISTINCT d2.user_id) FROM user_devices d JOIN user_devices d2 ON d2.user_id <> u.id AND (d2.did = d.did OR (d.fp <> '' AND d2.fp = d.fp)) WHERE d.user_id = u.id)) AS risk
            FROM users u WHERE u.id IN ($in)")->fetchAll();
        foreach ($rows as $r) $out[(int)$r['id']] = (int)$r['risk'];
    } catch (Throwable $e) {}
    return $out;
}

function multi_is_banned(?array $user): bool {
    if (!$user) return false;
    if ((int)($user['is_active'] ?? 1) === 1) return false;
    return (string)($user['ban_reason'] ?? '') !== '';
}

function multi_ban_reason(\PDO $pdo, int $uid): string {
    try {
        $st = $pdo->prepare("SELECT ban_reason FROM users WHERE id = ? LIMIT 1");
        $st->execute([$uid]);
        return (string)$st->fetchColumn();
    } catch (Throwable $e) { return ''; }
}

// Acımasız yaptırım: bağlantı grubunda EN ESKİ aktif hesap ana hesaptır
// ve asla dokunulmaz; grubun geri kalanı anında banlanır.
// Güçlü sinyal (aynı cihaz çerezi / parmak izi / normalize e-posta) = otoban.
// Zayıf sinyal (yalnızca ortak IP) = bayrak, ban yok (yurt/ofis/CGNAT koruması).
// Dönüş: 'clean' | 'main' | 'banned'
function multi_enforce(\PDO $pdo, int $uid): string {
    if ($uid <= 0 || !multi_enabled()) return 'clean';
    if (multi_setting('multi_autoban', '1') !== '1') return 'clean';
    try {
        multi_ensure_tables($pdo);
        $st = $pdo->prepare("SELECT id, is_admin, role, is_active, created_at FROM users WHERE id = ? LIMIT 1");
        $st->execute([$uid]);
        $me = $st->fetch();
        if (!$me) return 'clean';
        // Adminler ve personel rolleri otomasyona kapalı.
        if ((int)($me['is_admin'] ?? 0) === 1 || (($me['role'] ?? 'user') !== 'user')) return 'clean';

        $strong = []; // id => sinyal
        $weak = [];
        $myDev = $pdo->prepare("SELECT did, fp FROM user_devices WHERE user_id = ?");
        $myDev->execute([$uid]);
        foreach ($myDev->fetchAll() as $d) {
            if (!empty($d['did'])) {
                $q = $pdo->prepare("SELECT DISTINCT d2.user_id FROM user_devices d2 JOIN users u2 ON u2.id = d2.user_id WHERE d2.did = ? AND d2.user_id <> ? AND u2.is_admin = 0 AND u2.role = 'user'");
                $q->execute([$d['did'], $uid]);
                foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $x) $strong[(int)$x] = 'device_cookie';
            }
            if (!empty($d['fp'])) {
                $q = $pdo->prepare("SELECT DISTINCT d2.user_id FROM user_devices d2 JOIN users u2 ON u2.id = d2.user_id WHERE d2.fp = ? AND d2.user_id <> ? AND u2.is_admin = 0 AND u2.role = 'user'");
                $q->execute([$d['fp'], $uid]);
                foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $x) $strong[(int)$x] = $strong[(int)$x] ?? 'fingerprint';
            }
        }
        $meFull = $pdo->prepare("SELECT email_norm, reg_ip FROM users WHERE id = ? LIMIT 1");
        $meFull->execute([$uid]);
        $mf = $meFull->fetch() ?: [];
        if (!empty($mf['email_norm'])) {
            $q = $pdo->prepare("SELECT id FROM users WHERE email_norm = ? AND id <> ? AND is_admin = 0 AND role = 'user'");
            $q->execute([$mf['email_norm'], $uid]);
            foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $x) $strong[(int)$x] = 'email_norm';
        }
        if (!empty($mf['reg_ip'])) {
            $q = $pdo->prepare("SELECT id FROM users WHERE reg_ip = ? AND id <> ? AND is_admin = 0 AND role = 'user' LIMIT 50");
            $q->execute([$mf['reg_ip'], $uid]);
            foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $x) {
                if (!isset($strong[(int)$x])) $weak[(int)$x] = 'shared_ip';
            }
        }
        foreach ($weak as $lid => $sig) {
            multi_flag($pdo, $uid, (int)$lid, 'shared_ip', 'Ortak IP (inceleme)');
        }
        if (!$strong) return 'clean';

        // Grup: ben + güçlü bağlantılar (aktif olanlar). En eski = ana hesap.
        $ids = array_values(array_unique(array_merge([$uid], array_keys($strong))));
        $in = implode(',', array_map('intval', $ids));
        $rows = $pdo->query("SELECT id, created_at FROM users WHERE id IN ($in) AND is_active = 1 ORDER BY created_at ASC, id ASC")->fetchAll();
        if (count($rows) < 2) return 'clean'; // banlanacak aktif multi yok
        $mainId = (int)$rows[0]['id'];
        $bannedSelf = false;
        foreach ($rows as $r) {
            $bid = (int)$r['id'];
            if ($bid === $mainId) continue; // ana hesap: dokunulmaz
            $pdo->prepare("UPDATE users SET is_active = 0, ban_reason = 'multi' WHERE id = ? AND is_active = 1")->execute([$bid]);
            multi_flag($pdo, $bid, $mainId, 'multi_ban', 'Otomatik multi-hesap bani (ana hesap #' . $mainId . ')');
            try {
                notify_user($bid, 'rejected', 'Account suspended — multiple accounts',
                    'Your account was suspended for violating the one-account-per-person policy. If you believe this is a mistake, contact info@vexaris.net to appeal.');
            } catch (Throwable $e2) {}
            if ($bid === $uid) $bannedSelf = true;
        }
        return $bannedSelf ? 'banned' : 'main';
    } catch (Throwable $e) {
        return 'clean'; // fail-open
    }
}

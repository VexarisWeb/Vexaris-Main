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
        $pdo->exec("CREATE TABLE IF NOT EXISTS multi_groups (
            id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            gkey         VARCHAR(64) NOT NULL,
            gtype        VARCHAR(12) NOT NULL DEFAULT 'device',
            member_ids   VARCHAR(600) NOT NULL DEFAULT '',
            member_count INT UNSIGNED NOT NULL DEFAULT 0,
            active_count INT UNSIGNED NOT NULL DEFAULT 0,
            ip_set       VARCHAR(600) NOT NULL DEFAULT '',
            reason       VARCHAR(32) NOT NULL DEFAULT '',
            status       VARCHAR(12) NOT NULL DEFAULT 'watch',
            first_seen   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_mg_gkey (gkey),
            KEY idx_mg_status (status),
            KEY idx_mg_type (gtype)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        ensure_column($pdo, 'multi_groups', 'active_count', '`active_count` INT UNSIGNED NOT NULL DEFAULT 0');
        $pdo->exec("CREATE TABLE IF NOT EXISTS multi_alerts (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            gkey       VARCHAR(64) NOT NULL DEFAULT '',
            user_id    INT UNSIGNED NOT NULL DEFAULT 0,
            is_read    TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ma_user (user_id),
            KEY idx_ma_read (is_read),
            UNIQUE KEY uq_ma_gkey_user (gkey, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS risk_events (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id    INT UNSIGNED NOT NULL DEFAULT 0,
            gkey       VARCHAR(64) NOT NULL DEFAULT '',
            action     VARCHAR(24) NOT NULL DEFAULT '',
            decision   VARCHAR(12) NOT NULL DEFAULT '',
            reasons    VARCHAR(255) NOT NULL DEFAULT '',
            vid        CHAR(32) NOT NULL DEFAULT '',
            did        CHAR(32) NOT NULL DEFAULT '',
            ip         VARCHAR(45) NOT NULL DEFAULT '',
            bypassed   TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_re_user (user_id),
            KEY idx_re_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS fp_nonces (
            nonce      CHAR(32) NOT NULL PRIMARY KEY,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_fn_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        ensure_column($pdo, 'user_devices', 'vid', "`vid` CHAR(32) NOT NULL DEFAULT ''");
        ensure_column($pdo, 'user_devices', 'fp_ver', '`fp_ver` TINYINT NOT NULL DEFAULT 0');
        ensure_column($pdo, 'user_devices', 'bits', "`bits` VARCHAR(600) NOT NULL DEFAULT ''");
        ensure_column($pdo, 'user_devices', 'fp_salt_ver', '`fp_salt_ver` TINYINT NOT NULL DEFAULT 1');
        ensure_column($pdo, 'user_devices', 'country', "`country` CHAR(2) NOT NULL DEFAULT ''");
        ensure_column($pdo, 'user_devices', 'bypassed', '`bypassed` TINYINT(1) NOT NULL DEFAULT 0');
        try {
            $hasVid = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_devices' AND INDEX_NAME = 'idx_ud_vid'")->fetchColumn();
            if (!(int)$hasVid) $pdo->exec("ALTER TABLE `user_devices` ADD KEY `idx_ud_vid` (`vid`)");
        } catch (Throwable $eIdx) {}
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
        $pdo->prepare("INSERT INTO user_devices (user_id, did, fp, vid, ip, user_agent, event, bypassed) VALUES (?,?,?,?,?,?,'register',?)")
            ->execute([$uid, multi_device_id(), multi_client_fp(), multi_client_vid(), $ip !== '' ? $ip : null, $ua !== '' ? $ua : null, multi_is_exempt($pdo, $uid) ? 1 : 0]);
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
        $vid = multi_client_vid();
        $ip = client_ip();
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $st = $pdo->prepare("SELECT id FROM user_devices WHERE user_id = ? AND did = ? AND fp = ? LIMIT 1");
        $st->execute([$uid, $did, $fp]);
        $rowId = $st->fetchColumn();
        if ($rowId) {
            $pdo->prepare("UPDATE user_devices SET last_seen = NOW(), ip = ?, user_agent = ?, vid = IF(? <> '', ?, vid), bypassed = ? WHERE id = ?")
                ->execute([$ip !== '' ? $ip : null, $ua !== '' ? $ua : null, $vid, $vid, multi_is_exempt($pdo, $uid) ? 1 : 0, (int)$rowId]);
        } else {
            $pdo->prepare("INSERT INTO user_devices (user_id, did, fp, vid, ip, user_agent, event, bypassed) VALUES (?,?,?,?,?,?,'login',?)")
                ->execute([$uid, $did, $fp, $vid, $ip !== '' ? $ip : null, $ua !== '' ? $ua : null, multi_is_exempt($pdo, $uid) ? 1 : 0]);
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
        $st = $pdo->prepare("SELECT COUNT(*) FROM multi_flags WHERE user_id = ? AND linked_user_id = ? AND `sig` = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $st->execute([$uid, $linkedId, $signal]);
        if ((int)$st->fetchColumn() > 0) return;
        $pdo->prepare("INSERT INTO multi_flags (user_id, linked_user_id, `sig`, detail) VALUES (?,?,?,?)")
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
    if (multi_mode() !== 'harsh') return 'clean';
    if (multi_setting('multi_autoban', '1') !== '1') return 'clean';
    if (multi_is_exempt($pdo, $uid)) return 'clean';
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
            // Oturumlar ve remember-me tokenlari dusurulur (ban gecerli olsun).
            try {
                if (function_exists('sessions_kill_all')) {
                    sessions_kill_all($pdo, $bid, false);
                } else {
                    $pdo->prepare("UPDATE users SET session_version = session_version + 1 WHERE id = ?")->execute([$bid]);
                    $pdo->prepare("UPDATE user_sessions SET revoked = 1 WHERE user_id = ?")->execute([$bid]);
                    $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$bid]);
                }
            } catch (Throwable $e3) {}
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

function multi_secret(): string {
    static $s = null;
    if ($s !== null) return $s;
    $v = '';
    foreach (['VX_FP_SECRET', 'VX_MASTER_KEY'] as $k) {
        $env = getenv($k);
        if ($env !== false && strlen(trim($env)) >= 32) { $v = trim($env); break; }
        try {
            if (function_exists('vx_secret')) {
                $f = vx_secret($k, '');
                if (strlen($f) >= 32) { $v = $f; break; }
            }
        } catch (Throwable $e) {}
        $fp = '/var/www/html/install/.vx_master_key';
        if ($v === '' && is_readable($fp)) {
            $raw = trim((string)@file_get_contents($fp));
            if (strlen($raw) >= 32) { $v = $raw; break; }
        }
    }
    $s = $v;
    return $s;
}

function fp_hash(string $kind, string $value): string {
    if ($value === '') return '';
    $s = multi_secret();
    if ($s === '') return '';
    $k = hash_hmac('sha256', $kind . ':v1', $s, true);
    return substr(hash_hmac('sha256', $value, $k), 0, 32);
}

function multi_bypass_emails(bool $fresh = false): array {
    static $list = null;
    if ($list !== null && !$fresh) return $list;
    $list = [];
    try {
        $raw = multi_setting('multi_bypass_emails', '');
        foreach (preg_split('/[\s,;]+/', $raw) as $e) {
            $e = strtolower(trim($e));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) $list[] = $e;
        }
        $list = array_values(array_unique($list));
    } catch (Throwable $e) {}
    return $list;
}

function multi_sys_owner_id(\PDO $pdo): int {
    static $id = null;
    if ($id !== null) return $id;
    $id = 0;
    try { $id = (int)$pdo->query("SELECT id FROM users WHERE is_admin = 1 ORDER BY id ASC LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}
    return $id;
}

function multi_is_exempt(\PDO $pdo, int $uid): bool {
    static $cache = [];
    if ($uid <= 0) return false;
    if (array_key_exists($uid, $cache)) return $cache[$uid];
    $out = false;
    try {
        $st = $pdo->prepare("SELECT id, email, is_admin, role FROM users WHERE id = ? LIMIT 1");
        $st->execute([$uid]);
        $u = $st->fetch();
        if ($u) {
            if ((int)$u['is_admin'] === 1) {
                $out = true;
            } elseif ((string)($u['role'] ?? 'user') !== 'user') {
                $out = true;
            } elseif (multi_sys_owner_id($pdo) === $uid) {
                $out = true;
            } else {
                $e = strtolower(trim((string)$u['email']));
                $list = multi_bypass_emails();
                if ($list && (in_array($e, $list, true) || in_array(multi_normalize_email($e), $list, true))) $out = true;
            }
        }
    } catch (Throwable $e) {}
    $cache[$uid] = $out;
    return $out;
}

function multi_mode(): string {
    $m = strtolower(trim(multi_setting('multi_mode', 'notify')));
    return in_array($m, ['off', 'shadow', 'notify', 'graduated', 'harsh'], true) ? $m : 'notify';
}

function multi_ip_max(): int {
    $n = (int)multi_setting('multi_ip_max', '3');
    if ($n < 1) $n = 1;
    if ($n > 50) $n = 50;
    return $n;
}

function multi_ban_threshold(): int {
    $n = (int)multi_setting('multi_ban_threshold', '3');
    if ($n < 2) $n = 2;
    if ($n > 20) $n = 20;
    return $n;
}

function multi_exempt_sql(): array {
    $sql = " AND is_admin = 0 AND role = 'user'";
    $args = [];
    $list = multi_bypass_emails();
    if ($list) {
        $sql .= " AND email NOT IN (" . implode(',', array_fill(0, count($list), '?')) . ")";
        foreach ($list as $e) $args[] = $e;
    }
    return [$sql, $args];
}

function multi_ip_active_count(\PDO $pdo, string $ip, int $excludeUid = 0): int {
    if ($ip === '') return 0;
    try {
        [$exSql, $exArgs] = multi_exempt_sql();
        $sql = "SELECT COUNT(*) FROM users WHERE reg_ip = ? AND is_active = 1 AND reg_ip <> ''" . $exSql;
        $args = array_merge([$ip], $exArgs);
        if ($excludeUid > 0) { $sql .= " AND id <> ?"; $args[] = $excludeUid; }
        $st = $pdo->prepare($sql);
        $st->execute($args);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

function multi_ip_over_cap(\PDO $pdo, string $ip, int $uid): bool {
    if ($ip === '' || $uid <= 0) return false;
    try {
        [$exSql, $exArgs] = multi_exempt_sql();
        $sql = "SELECT COUNT(*) FROM users WHERE reg_ip = ? AND reg_ip <> '' AND is_active = 1 AND id < ?" . $exSql;
        $st = $pdo->prepare($sql);
        $st->execute(array_merge([$ip, $uid], $exArgs));
        return (int)$st->fetchColumn() >= multi_ip_max();
    } catch (Throwable $e) { return false; }
}

function multi_ip_gate(\PDO $pdo, string $ip): array {
    if (!multi_enabled() || multi_mode() === 'off') return ['ok' => true, 'msg' => ''];
    $max = multi_ip_max();
    $n = multi_ip_active_count($pdo, $ip);
    if ($n >= $max) {
        return ['ok' => false, 'msg' => 'Rate limit exceeded — too many accounts from this network. Contact info@vexaris.net if you believe this is a mistake.'];
    }
    return ['ok' => true, 'msg' => ''];
}

function multi_client_vid(): string {
    $v = strtolower(trim((string)($_POST['vid'] ?? ($_COOKIE['vex_vid'] ?? ''))));
    if (!preg_match('/^[0-9a-f]{32}$/', $v)) return '';
    return fp_hash('vid', $v);
}

function multi_linked_device_users(\PDO $pdo, int $uid, string $emailNorm = ''): array {
    $out = [];
    if ($uid <= 0) return $out;
    try {
        $st = $pdo->prepare("SELECT DISTINCT vid, did FROM user_devices WHERE user_id = ?");
        $st->execute([$uid]);
        foreach ($st->fetchAll() as $d) {
            foreach (['vid', 'did'] as $col) {
                if (empty($d[$col])) continue;
                $q = $pdo->prepare("SELECT DISTINCT d.user_id FROM user_devices d JOIN users u ON u.id = d.user_id AND u.is_active = 1 WHERE d.`$col` = ? AND d.user_id <> ? LIMIT 20");
                $q->execute([$d[$col], $uid]);
                foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $x) $out[(int)$x] = $col;
            }
        }
        $st = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        $st->execute([$uid]);
        $row = $st->fetch();
        if (!$row) return $out;
        if ($emailNorm === '') {
            $e = $pdo->prepare("SELECT email_norm FROM users WHERE id = ? LIMIT 1");
            $e->execute([$uid]);
            $emailNorm = (string)$e->fetchColumn();
        }
        if ($emailNorm !== '') {
            $q = $pdo->prepare("SELECT id FROM users WHERE email_norm = ? AND id <> ? LIMIT 20");
            $q->execute([$emailNorm, $uid]);
            foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $x) $out[(int)$x] = $out[(int)$x] ?? 'email_norm';
        }
    } catch (Throwable $e) {}
    return $out;
}

function multi_store_gate(\PDO $pdo, int $uid, string $emailNorm = ''): array {
    if (!multi_enabled() || multi_mode() === 'off') return ['ok' => true, 'msg' => '', 'linked' => []];
    if ($uid <= 0) return ['ok' => true, 'msg' => '', 'linked' => []];
    try {
        multi_ensure_tables($pdo);
        if (multi_is_exempt($pdo, $uid)) return ['ok' => true, 'msg' => '', 'linked' => []];
        $linked = multi_linked_device_users($pdo, $uid, $emailNorm);
        if (!$linked) return ['ok' => true, 'msg' => '', 'linked' => []];
        $hit = [];
        foreach (array_keys($linked) as $lid) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM hosted_stores WHERE user_id = ? AND status IN ('active','provisioning')");
            $st->execute([$lid]);
            $n = (int)$st->fetchColumn();
            if ($n === 0) {
                $st = $pdo->prepare("SELECT COUNT(*) FROM hosting_jobs WHERE user_id = ? AND status IN ('pending','claimed')");
                $st->execute([$lid]);
                $n = (int)$st->fetchColumn();
            }
            if ($n > 0) $hit[] = $lid;
        }
        if ($hit) {
            return ['ok' => false, 'linked' => $hit,
                'msg' => 'A store already exists on this device. One free store per person — delete the existing store or upgrade. Contact info@vexaris.net if you believe this is a mistake.'];
        }
    } catch (Throwable $e) {}
    return ['ok' => true, 'msg' => '', 'linked' => []];
}

function risk_event_log(\PDO $pdo, int $uid, string $gkey, string $action, string $decision, string $reasons = '', string $vid = '', string $did = '', string $ip = '', bool $bypassed = false): void {
    if ($uid <= 0) return;
    try {
        $pdo->prepare("INSERT INTO risk_events (user_id, gkey, action, decision, reasons, vid, did, ip, bypassed) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$uid, $gkey, $action, $decision, mb_substr($reasons, 0, 255), $vid, $did, $ip, $bypassed ? 1 : 0]);
    } catch (Throwable $e) {}
}

function multi_group_upsert(\PDO $pdo, string $gkey, string $gtype, array $members, array $ips, string $reason, int $activeCount = 0): void {
    if ($gkey === '' || !$members) return;
    try {
        $ids = implode(',', array_map('intval', $members));
        $ipStr = implode(',', array_slice(array_values(array_unique(array_filter($ips))), 0, 40));
        $st = $pdo->prepare("SELECT id, status FROM multi_groups WHERE gkey = ? LIMIT 1");
        $st->execute([$gkey]);
        $row = $st->fetch();
        if ($row) {
            $pdo->prepare("UPDATE multi_groups SET member_ids = ?, member_count = ?, active_count = ?, ip_set = ?, last_seen = NOW(), reason = ? WHERE id = ?")
                ->execute([$ids, count($members), $activeCount, $ipStr, $reason, (int)$row['id']]);
        } else {
            $pdo->prepare("INSERT INTO multi_groups (gkey, gtype, member_ids, member_count, active_count, ip_set, reason, status) VALUES (?,?,?,?,?,?,?, 'watch')")
                ->execute([$gkey, $gtype, $ids, count($members), $activeCount, $ipStr, $reason]);
        }
        $alertMin = $gtype === 'ip' ? max(3, multi_ban_threshold()) : 2;
        if ($activeCount >= $alertMin) {
            $admins = $pdo->query("SELECT id FROM users WHERE is_admin = 1 AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
            $ins = $pdo->prepare("INSERT IGNORE INTO multi_alerts (gkey, user_id) VALUES (?,?)");
            foreach ($admins as $aid) $ins->execute([$gkey, (int)$aid]);
        }
    } catch (Throwable $e) {}
}

function multi_group_sync(\PDO $pdo, int $uid): array {
    $out = ['groups' => 0, 'alerts' => 0];
    if ($uid <= 0 || !multi_enabled() || multi_mode() === 'off') return $out;
    try {
        multi_ensure_tables($pdo);
        $vids = []; $dids = []; $ips = [];
        $st = $pdo->prepare("SELECT vid, did, ip FROM user_devices WHERE user_id = ?");
        $st->execute([$uid]);
        foreach ($st->fetchAll() as $m) {
            if (!empty($m['vid'])) $vids[(string)$m['vid']] = true;
            if (!empty($m['did'])) $dids[(string)$m['did']] = true;
            if (!empty($m['ip'])) $ips[(string)$m['ip']] = true;
        }
        $st = $pdo->prepare("SELECT reg_ip FROM users WHERE id = ? LIMIT 1");
        $st->execute([$uid]);
        $regIp = (string)$st->fetchColumn();
        if ($regIp !== '') $ips[$regIp] = true;
        $exempt = multi_is_exempt($pdo, $uid);

        foreach (['vid' => $vids, 'did' => $dids] as $col => $set) {
            foreach (array_keys($set) as $val) {
                $q = $pdo->prepare("SELECT d.user_id AS uid, MAX(u.is_active) AS act, GROUP_CONCAT(DISTINCT d.ip) AS ips
                    FROM user_devices d JOIN users u ON u.id = d.user_id
                    WHERE d.`$col` = ? GROUP BY d.user_id");
                $q->execute([$val]);
                $ms = []; $active = 0; $ipx = [];
                foreach ($q->fetchAll() as $r) {
                    $ms[(int)$r['uid']] = true;
                    if ((int)$r['act'] === 1) $active++;
                    foreach (explode(',', (string)$r['ips']) as $oneIp) {
                        $oneIp = trim($oneIp);
                        if ($oneIp !== '') $ipx[] = $oneIp;
                    }
                }
                if (count($ms) < 2) continue;
                $gkey = ($col === 'vid' ? 'd_' : 'c_') . $val;
                multi_group_upsert($pdo, $gkey, 'device', array_keys($ms), $ipx, $col === 'vid' ? 'same_fingerprint' : 'same_cookie', $active);
                $out['groups']++;
                if (!$exempt && $active >= 2) {
                    risk_event_log($pdo, $uid, $gkey, 'group_sync', 'watch', $col === 'vid' ? 'same_fingerprint' : 'same_cookie', $col === 'vid' ? $val : '', $col === 'did' ? $val : '', $regIp);
                }
            }
        }
        foreach (array_keys($ips) as $ipx) {
            $q = $pdo->prepare("SELECT id FROM users WHERE reg_ip = ? AND is_active = 1");
            $q->execute([$ipx]);
            $ms = [];
            foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $x) $ms[(int)$x] = true;
            if (count($ms) < 2) continue;
            multi_group_upsert($pdo, 'i_' . md5($ipx), 'ip', array_keys($ms), [$ipx], 'shared_ip', count($ms));
            $out['groups']++;
        }
    } catch (Throwable $e) {}
    return $out;
}

function multi_groups_rebuild_all(\PDO $pdo): array {
    $out = ['users' => 0, 'groups' => 0];
    try {
        multi_ensure_tables($pdo);
        $ids = $pdo->query("SELECT DISTINCT user_id FROM user_devices WHERE user_id > 0")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $uid) {
            multi_group_sync($pdo, (int)$uid);
            $out['users']++;
        }
        $out['groups'] = (int)$pdo->query("SELECT COUNT(*) FROM multi_groups")->fetchColumn();
    } catch (Throwable $e) {}
    return $out;
}

function multi_open_alert_count(\PDO $pdo, int $adminId = 0): int {
    try {
        $st = $pdo->prepare("SELECT COUNT(DISTINCT a.gkey) FROM multi_alerts a JOIN multi_groups g ON g.gkey = a.gkey WHERE a.user_id = ? AND a.is_read = 0 AND g.status = 'watch'");
        $st->execute([$adminId]);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

function multi_group_rows(\PDO $pdo, string $status = 'watch', int $limit = 100): array {
    try {
        $st = $pdo->prepare("SELECT * FROM multi_groups WHERE status = ? ORDER BY member_count DESC, last_seen DESC LIMIT " . (int)$limit);
        $st->execute([$status]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

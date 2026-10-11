<?php
// ============================================================
//  Vexaris — Veritabanı Bağlantısı & Migration
// ============================================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'vexaris_net');
define('DB_USER',    'vexaris_net_usr');
define('DB_PASS',    'REDACTED_DB_PASSWORD');
define('DB_CHARSET', 'utf8mb4');

define('SESSION_NAME', 'vexaris_sess');
define('SITE_URL',     'https://vexaris.net');

// ── PDO Bağlantısı ──────────────────────────────────────────
function db(): ?PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5,
            ]);
        } catch (PDOException $e) {
            error_log('Vexaris DB bağlantı hatası: ' . $e->getMessage());
            return null;
        }
    }
    return $pdo;
}

// ── Session Başlat ──────────────────────────────────────────
function boot_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $life = 60*60*24*180;
        @ini_set('session.gc_maxlifetime', (string)$life);
        @ini_set('session.cookie_lifetime', (string)$life);
        @ini_set('session.gc_probability', '1');
        @ini_set('session.gc_divisor', '100');
        session_set_cookie_params(['lifetime'=>$life,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
        session_name(SESSION_NAME);
        session_start();
    }
    auth_via_remember();
    session_guard();
}

// ── Oturum koruma + takibi ───────────────────────────────────
// Şifre değişince session_version artar; eski oturumlar bir sonraki
// istekte düşer. user_sessions tablosu "Oturumlarım" listesini besler.
function session_guard(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) return;
    $uid = (int)$_SESSION['user']['id'];
    try {
        $pdo = db();
        if (!$pdo) return;
        $st = $pdo->prepare("SELECT session_version FROM users WHERE id = ? LIMIT 1");
        $st->execute([$uid]);
        $ver = $st->fetchColumn();
        if ($ver === false) { // kullanıcı silinmiş
            unset($_SESSION['user']);
            return;
        }
        $sessVer = (int)($_SESSION['user']['sess_ver'] ?? 0);
        if ($sessVer !== (int)$ver) { // başka yerde şifre değişti / tüm oturumlar kapatıldı
            unset($_SESSION['user']);
            @session_regenerate_id(true);
            return;
        }
        $_SESSION['user']['sess_ver'] = (int)$ver;
        session_track($pdo, $uid);
    } catch (Throwable $e) { /* sessiz: koruma devrede değilse oturum sürer */ }
}

// User-Agent'tan cihaz etiketi çıkarır: "Chrome • Windows" gibi.
// Oturum takibi IP'ye değil cihaza göre yapılır (mobil ağ değişiminde IP değişir).
function device_parse(string $ua): array {
    $u = strtolower($ua);
    if (strpos($u, 'iphone') !== false || strpos($u, 'ipad') !== false) $os = 'iOS';
    elseif (strpos($u, 'android') !== false) $os = 'Android';
    elseif (strpos($u, 'windows') !== false) $os = 'Windows';
    elseif (strpos($u, 'mac os') !== false || strpos($u, 'macintosh') !== false) $os = 'macOS';
    elseif (strpos($u, 'linux') !== false) $os = 'Linux';
    else $os = 'Unknown';
    if (strpos($u, 'edg/') !== false || strpos($u, 'edge/') !== false) $br = 'Edge';
    elseif (strpos($u, 'opr/') !== false || strpos($u, 'opera') !== false) $br = 'Opera';
    elseif (strpos($u, 'samsungbrowser') !== false) $br = 'Samsung Internet';
    elseif (strpos($u, 'firefox') !== false || strpos($u, 'fxios') !== false) $br = 'Firefox';
    elseif (strpos($u, 'crios') !== false || strpos($u, 'chrome') !== false) $br = 'Chrome';
    elseif (strpos($u, 'safari') !== false) $br = 'Safari';
    else $br = 'Browser';
    $mobile = (strpos($u, 'mobile') !== false || strpos($u, 'android') !== false || strpos($u, 'iphone') !== false || strpos($u, 'ipad') !== false);
    $label = $br . ' • ' . $os;
    // Parmak izi: sürüm numaralarından arındırılmış normalize UA hash'i
    $norm = preg_replace('/\/[\d.]+/', '', $u);
    $norm = preg_replace('/\s+/', ' ', (string)$norm);
    return ['label' => $label, 'mobile' => $mobile, 'hash' => hash('sha256', trim((string)$norm))];
}

function session_track(PDO $pdo, int $uid): void {
    try {
        $key = (string)session_id();
        if ($key === '' || $uid <= 0) return;
        $now = time();
        if (isset($_SESSION['sess_ping']) && ($now - (int)$_SESSION['sess_ping']) < 300) return;
        $_SESSION['sess_ping'] = $now;
        $ip = client_ip();
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $dev = device_parse((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $st = $pdo->prepare("SELECT id, revoked FROM user_sessions WHERE session_key = ? LIMIT 1");
        $st->execute([$key]);
        $row = $st->fetch();
        if ($row) {
            if ((int)$row['revoked'] === 1) { // bu oturum uzaktan kapatılmış
                unset($_SESSION['user']);
                @session_regenerate_id(true);
                return;
            }
            $pdo->prepare("UPDATE user_sessions SET last_seen = NOW(), user_id = ?, ip = ?, user_agent = ?, device_label = ?, device_hash = ? WHERE id = ?")
                ->execute([$uid, $ip !== '' ? $ip : null, $ua !== '' ? $ua : null, $dev['label'], $dev['hash'], (int)$row['id']]);
        } else {
            try {
                $pdo->prepare("INSERT INTO user_sessions (user_id, session_key, ip, user_agent, device_label, device_hash) VALUES (?,?,?,?,?,?)")
                    ->execute([$uid, $key, $ip !== '' ? $ip : null, $ua !== '' ? $ua : null, $dev['label'], $dev['hash']]);
            } catch (Throwable $e2) { /* çift kayıt yarışı: görmezden gel */ }
        }
    } catch (Throwable $e) {}
}

// Tüm oturumları kapat (şifre değişiminde). Mevcut oturum korunur,
// yeni sürüm numarası döner. remember-me tokenları da silinir.
function sessions_kill_all(PDO $pdo, int $uid, bool $keepCurrent = true): int {
    $cur = (string)session_id();
    try {
        $pdo->prepare("UPDATE users SET session_version = session_version + 1 WHERE id = ?")->execute([$uid]);
        if ($keepCurrent && $cur !== '') {
            $pdo->prepare("UPDATE user_sessions SET revoked = 1 WHERE user_id = ? AND session_key <> ?")->execute([$uid, $cur]);
        } else {
            $pdo->prepare("UPDATE user_sessions SET revoked = 1 WHERE user_id = ?")->execute([$uid]);
        }
    } catch (Throwable $e) {}
    try {
        $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$uid]);
    } catch (Throwable $e2) {}
    try {
        $st = $pdo->prepare("SELECT session_version FROM users WHERE id = ? LIMIT 1");
        $st->execute([$uid]);
        return (int)$st->fetchColumn();
    } catch (Throwable $e3) { return 0; }
}

function user_is_active(PDO $pdo, int $uid): bool {
    try {
        $st = $pdo->prepare("SELECT is_active FROM users WHERE id = ? LIMIT 1");
        $st->execute([$uid]);
        $v = $st->fetchColumn();
        if ($v === false) return false;
        return (int)$v === 1;
    } catch (Throwable $e) { return true; }
}

// Destek talebi resimlerini diskten siler ve kayıtları NULL'lar.
// Kapanan/silinen taleplerde çağrılır (depolama temizliği).
function support_purge_attachments(PDO $pdo, int $ticketId): void {
    if ($ticketId <= 0) return;
    try {
        $base = realpath(dirname(__DIR__) . '/uploads/tickets');
        $st = $pdo->prepare("SELECT attachment FROM support_messages WHERE ticket_id = ? AND attachment IS NOT NULL AND attachment <> ''");
        $st->execute([$ticketId]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $a) {
            $p = ($base !== false) ? realpath($base . '/' . basename((string)$a)) : false;
            if ($p !== false && strpos($p, $base) === 0 && is_file($p)) @unlink($p);
        }
        $pdo->prepare("UPDATE support_messages SET attachment = NULL WHERE ticket_id = ?")->execute([$ticketId]);
    } catch (Throwable $e) {}
}

function auth_via_remember(): void {
    if (!empty($_SESSION['user'])) return;
    $raw = (string)($_COOKIE['remember_token'] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $raw)) return;
    $pdo = db();
    if (!$pdo) return;
    try {
        $st = $pdo->prepare("SELECT rt.user_id, rt.expires_at, u.username, u.email, u.is_admin, u.session_version FROM remember_tokens rt JOIN users u ON u.id = rt.user_id WHERE rt.token_hash = ? LIMIT 1");
        $st->execute([hash('sha256', $raw)]);
        $row = $st->fetch();
        if (!$row || strtotime((string)$row['expires_at']) < time()) {
            remember_forget($raw);
            try { $pdo->exec("DELETE FROM remember_tokens WHERE expires_at < NOW()"); } catch (Throwable $e2) {}
            return;
        }
        $_SESSION['user'] = [
            'id' => (int)$row['user_id'],
            'username' => $row['username'],
            'email' => $row['email'],
            'is_admin' => $row['is_admin'],
            'sess_ver' => (int)($row['session_version'] ?? 0),
        ];
        session_regenerate_id(true);
        remember_rotate($raw, (int)$row['user_id']);
    } catch (Throwable $e) {}
}

function remember_forget(string $raw): void {
    try {
        $pdo = db();
        if ($pdo && preg_match('/^[a-f0-9]{64}$/', $raw)) {
            $pdo->prepare("DELETE FROM remember_tokens WHERE token_hash = ?")->execute([hash('sha256', $raw)]);
        }
    } catch (Throwable $e) {}
    setcookie('remember_token', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
}

function remember_rotate(string $oldRaw, int $uid): void {
    try {
        $pdo = db();
        if (!$pdo) return;
        $pdo->prepare("DELETE FROM remember_tokens WHERE token_hash = ?")->execute([hash('sha256', $oldRaw)]);
        $new = bin2hex(random_bytes(32));
        $exp = date('Y-m-d H:i:s', time() + 60*60*24*180);
        $pdo->prepare("INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?,?,?)")->execute([$uid, hash('sha256', $new), $exp]);
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('remember_token', $new, ['expires' => time()+60*60*24*180, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $secure]);
    } catch (Throwable $e) {}
}

// ── Migration: Tablolar yoksa oluştur ───────────────────────
function run_migrations(): void {
    $pdo = db();
    if (!$pdo) return; // DB yoksa geç

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username    VARCHAR(60)  NOT NULL UNIQUE,
            email       VARCHAR(120) NOT NULL UNIQUE,
            password    VARCHAR(255) NOT NULL,
            is_admin    TINYINT(1)   NOT NULL DEFAULT 0,
            role        VARCHAR(30)  NOT NULL DEFAULT 'user',
            balance     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS page_views (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            page       VARCHAR(120) NOT NULL,
            ip         VARCHAR(45)  NOT NULL,
            user_agent VARCHAR(255),
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS licenses (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            customer_name  VARCHAR(255) NOT NULL,
            license_key    VARCHAR(64)  UNIQUE NOT NULL,
            allowed_domains TEXT        NOT NULL,
            status         ENUM('active','suspended','revoked') DEFAULT 'active',
            features       JSON         NOT NULL,
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            suspended_until DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        ensure_column($pdo, 'licenses', 'user_id', '`user_id` INT UNSIGNED NULL');
        ensure_column($pdo, 'hosting_jobs', 'kind', "`kind` VARCHAR(20) NOT NULL DEFAULT 'provision'");
        ensure_column($pdo, 'hosting_jobs', 'owner_hash', "`owner_hash` VARCHAR(255) NOT NULL DEFAULT ''");
        ensure_column($pdo, 'hosted_stores', 'site_name', "`site_name` VARCHAR(80) NOT NULL DEFAULT ''");
        ensure_column($pdo, 'hosted_stores', 'app_version', "`app_version` VARCHAR(30) NOT NULL DEFAULT ''");
        ensure_column($pdo, 'hosted_stores', 'traffic_bytes', "`traffic_bytes` BIGINT NOT NULL DEFAULT 0");
        ensure_column($pdo, 'hosted_stores', 'traffic_updated', "`traffic_updated` DATETIME NULL");
        $pdo->exec("CREATE TABLE IF NOT EXISTS store_traffic_daily (
            slug       VARCHAR(40) NOT NULL,
            day        DATE NOT NULL,
            bytes      BIGINT NOT NULL DEFAULT 0,
            PRIMARY KEY (slug, day)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS store_tokens (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            token      VARCHAR(64) UNIQUE NOT NULL,
            slug       VARCHAR(40) NOT NULL,
            admin_id   INT UNSIGNED NOT NULL DEFAULT 0,
            used       TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_st_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        ensure_column($pdo, 'hosted_stores', 'products', '`products` INT NOT NULL DEFAULT 0');
        ensure_column($pdo, 'hosted_stores', 'currency', "`currency` VARCHAR(5) NOT NULL DEFAULT 'TRY'");
        ensure_column($pdo, 'hosted_stores', 'abuse_score', '`abuse_score` INT NOT NULL DEFAULT 0');
        ensure_column($pdo, 'hosted_stores', 'abuse_detail', '`abuse_detail` VARCHAR(255) NOT NULL DEFAULT ' . "''");
        ensure_column($pdo, 'hosted_stores', 'custom_domain', "`custom_domain` VARCHAR(190) NOT NULL DEFAULT ''");
        ensure_column($pdo, 'hosted_stores', 'custom_status', "`custom_status` VARCHAR(20) NOT NULL DEFAULT ''");
        try {
            $sjLen = $pdo->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hosting_jobs' AND COLUMN_NAME = 'site_name'")->fetchColumn();
            if ($sjLen !== false && (int)$sjLen < 190) {
                $pdo->exec("ALTER TABLE `hosting_jobs` MODIFY COLUMN `site_name` VARCHAR(190) NOT NULL DEFAULT 'en'");
            }
        } catch (Exception $e) {}
        try {
            $hasIdx = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'licenses' AND INDEX_NAME = 'idx_lic_user'")->fetchColumn();
            if (!(int)$hasIdx) $pdo->exec("ALTER TABLE `licenses` ADD KEY `idx_lic_user` (`user_id`)");
        } catch (Exception $e) {}

        $pdo->exec("CREATE TABLE IF NOT EXISTS license_nonces (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            nonce      VARCHAR(64) UNIQUE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS api_rate_limits (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            ip         VARCHAR(45) NOT NULL,
            scope      VARCHAR(30) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_arl_ip_scope (ip, scope),
            KEY idx_arl_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS hosted_stores (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id    INT UNSIGNED NOT NULL DEFAULT 0,
            slug       VARCHAR(40) NOT NULL,
            domain     VARCHAR(190) NOT NULL,
            plan       VARCHAR(20) NOT NULL DEFAULT 'free',
            owner      VARCHAR(190) NOT NULL DEFAULT '',
            users      INT NOT NULL DEFAULT 0,
            revenue    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            status     VARCHAR(20) NOT NULL DEFAULT 'active',
            reported_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_hs_slug (slug),
            KEY idx_hs_user (user_id),
            KEY idx_hs_domain (domain)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS hosting_jobs (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id    INT UNSIGNED NOT NULL DEFAULT 0,
            slug       VARCHAR(40) NOT NULL,
            plan       VARCHAR(20) NOT NULL DEFAULT 'free',
            owner      VARCHAR(190) NOT NULL DEFAULT '',
            site_name  VARCHAR(80) NOT NULL DEFAULT '',
            lang       VARCHAR(5) NOT NULL DEFAULT 'en',
            status     VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts   INT NOT NULL DEFAULT 0,
            last_msg   VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_hj_status (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id    INT UNSIGNED NOT NULL,
            type       VARCHAR(40) NOT NULL DEFAULT 'info',
            title      VARCHAR(190) NOT NULL,
            message    TEXT NULL,
            is_read    TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_notif_user (user_id, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
            id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id      INT UNSIGNED NOT NULL,
            order_no     VARCHAR(32) NOT NULL,
            product_name VARCHAR(190) NOT NULL,
            amount       DECIMAL(10,2) NOT NULL DEFAULT 0,
            status       VARCHAR(30) NOT NULL DEFAULT 'completed',
            provider     VARCHAR(20) NOT NULL DEFAULT '',
            provider_ref VARCHAR(64) NOT NULL DEFAULT '',
            product_id   INT UNSIGNED NULL,
            currency     VARCHAR(10) NOT NULL DEFAULT '',
            created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_orders_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        try {
            $hasUq = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME = 'uq_orders_no'")->fetchColumn();
            if (!(int)$hasUq) $pdo->exec("ALTER TABLE `orders` ADD UNIQUE KEY `uq_orders_no` (`order_no`)");
        } catch (Exception $e) {}

        $pdo->exec("CREATE TABLE IF NOT EXISTS downloads (
            id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id         INT UNSIGNED NOT NULL,
            user_id          INT UNSIGNED NOT NULL,
            file_name        VARCHAR(190) NOT NULL,
            file_url         VARCHAR(500) NOT NULL,
            file_size        VARCHAR(40) NOT NULL DEFAULT '',
            download_count   INT UNSIGNED NOT NULL DEFAULT 0,
            last_download_at DATETIME NULL,
            created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_dl_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            `key`       VARCHAR(60) NOT NULL PRIMARY KEY,
            `value`     TEXT NULL,
            updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS products (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(190) NOT NULL,
            description TEXT NULL,
            file_name   VARCHAR(190) NOT NULL,
            file_path   VARCHAR(500) NOT NULL,
            file_size   VARCHAR(40) NOT NULL DEFAULT '',
            is_active   TINYINT(1) NOT NULL DEFAULT 1,
            price       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            currency    VARCHAR(10) NOT NULL DEFAULT '',
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS user_products (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id     INT UNSIGNED NOT NULL,
            product_id  INT UNSIGNED NOT NULL,
            granted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_product (user_id, product_id),
            KEY idx_up_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS user_subscriptions (
            id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id            INT UNSIGNED NOT NULL UNIQUE,
            plan               VARCHAR(16) NOT NULL DEFAULT 'free',
            cycle              VARCHAR(8) NOT NULL DEFAULT 'monthly',
            status             VARCHAR(16) NOT NULL DEFAULT 'active',
            current_period_end INT UNSIGNED NOT NULL DEFAULT 0,
            whop_membership_id VARCHAR(64) NOT NULL DEFAULT '',
            created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_usub_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS download_tokens (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id     INT UNSIGNED NOT NULL,
            product_id  INT UNSIGNED NOT NULL,
            token       VARCHAR(64) NOT NULL UNIQUE,
            used        TINYINT(1) NOT NULL DEFAULT 0,
            expires_at  DATETIME NOT NULL,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_dt_token (token),
            KEY idx_dt_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS versions (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            version     VARCHAR(30)  NOT NULL,
            description TEXT         NULL,
            file_name   VARCHAR(190) NOT NULL,
            file_path   VARCHAR(500) NOT NULL,
            file_size   VARCHAR(40)  NOT NULL DEFAULT '',
            is_active   TINYINT(1)   NOT NULL DEFAULT 1,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_versions_version (version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS update_events (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            version_id  INT NOT NULL,
            domain      VARCHAR(253) NOT NULL,
            event_type  ENUM('installed','delayed') NOT NULL DEFAULT 'delayed',
            delay_days  INT NOT NULL DEFAULT 0,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ue_version (version_id),
            KEY idx_ue_domain (domain)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS support_tickets (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id     INT UNSIGNED NOT NULL,
            subject     VARCHAR(190) NOT NULL,
            status      ENUM('open','answered','closed') NOT NULL DEFAULT 'open',
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_msg_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_st_user (user_id),
            KEY idx_st_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS support_messages (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ticket_id   INT UNSIGNED NOT NULL,
            sender_id   INT UNSIGNED NOT NULL,
            sender_role ENUM('user','admin') NOT NULL DEFAULT 'user',
            message     TEXT NOT NULL,
            is_read     TINYINT(1) NOT NULL DEFAULT 0,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_sm_ticket (ticket_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS legal_pages (
            slug       VARCHAR(60) NOT NULL PRIMARY KEY,
            title      VARCHAR(190) NOT NULL,
            content    LONGTEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS blog_posts (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title       VARCHAR(190) NOT NULL,
            slug        VARCHAR(200) NOT NULL,
            excerpt     TEXT NULL,
            content     LONGTEXT NULL,
            cover_image VARCHAR(255) NULL,
            status      ENUM('published','draft') NOT NULL DEFAULT 'draft',
            author_id   INT UNSIGNED NULL,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_blog_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS blog_post_views (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id     INT UNSIGNED NOT NULL,
            session_key VARCHAR(64) NULL,
            user_id     INT UNSIGNED NULL,
            ip          VARCHAR(45) NULL,
            duration_sec INT UNSIGNED NOT NULL DEFAULT 0,
            entered_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            left_at     DATETIME NULL,
            KEY idx_bpv_post (post_id, entered_at),
            KEY idx_bpv_session (session_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        ensure_column($pdo, 'blog_posts', 'meta_title',       '`meta_title` VARCHAR(190) NULL');
        ensure_column($pdo, 'blog_posts', 'meta_description', '`meta_description` VARCHAR(300) NULL');
        ensure_column($pdo, 'blog_posts', 'keywords',         '`keywords` VARCHAR(255) NULL');
        ensure_column($pdo, 'blog_posts', 'canonical',        '`canonical` VARCHAR(255) NULL');
        ensure_column($pdo, 'blog_posts', 'noindex',          '`noindex` TINYINT(1) NOT NULL DEFAULT 0');
        ensure_column($pdo, 'blog_posts', 'og_title',         '`og_title` VARCHAR(190) NULL');
        ensure_column($pdo, 'blog_posts', 'og_description',   '`og_description` VARCHAR(300) NULL');
        ensure_column($pdo, 'blog_posts', 'og_image',         '`og_image` VARCHAR(255) NULL');

        ensure_column($pdo, 'support_tickets', 'source', "`source` VARCHAR(20) NOT NULL DEFAULT 'support'");

        ensure_column($pdo, 'users', 'role', "`role` VARCHAR(30) NOT NULL DEFAULT 'user'");
        ensure_column($pdo, 'users', 'balance', "`balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00");
        ensure_column($pdo, 'products', 'price', "`price` DECIMAL(12,2) NOT NULL DEFAULT 0.00");
        ensure_column($pdo, 'products', 'currency', "`currency` VARCHAR(10) NOT NULL DEFAULT ''");
        ensure_column($pdo, 'orders', 'provider', "`provider` VARCHAR(20) NOT NULL DEFAULT ''");
        ensure_column($pdo, 'orders', 'provider_ref', "`provider_ref` VARCHAR(64) NOT NULL DEFAULT ''");
        ensure_column($pdo, 'orders', 'product_id', "`product_id` INT UNSIGNED NULL");
        ensure_column($pdo, 'orders', 'currency', "`currency` VARCHAR(10) NOT NULL DEFAULT ''");

        $pdo->exec("CREATE TABLE IF NOT EXISTS permissions (
            id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            role     VARCHAR(30) NOT NULL,
            resource VARCHAR(60) NOT NULL,
            UNIQUE KEY uq_perm_role_resource (role, resource)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("INSERT IGNORE INTO permissions (role, resource) VALUES
            ('admin','dashboard'),('admin','users'),('admin','products'),('admin','licenses'),('admin','versions'),('admin','demo'),('admin','settings'),('admin','legal'),('admin','tickets'),('admin','permissions'),('admin','blog'),('admin','seo'),
            ('yonetici','dashboard'),('yonetici','users'),('yonetici','products'),('yonetici','licenses'),('yonetici','versions'),('yonetici','settings'),('yonetici','legal'),('yonetici','tickets'),('yonetici','blog'),('yonetici','seo'),
            ('moderator','dashboard'),('moderator','users'),('moderator','tickets'),
            ('destek_ekibi','dashboard'),('destek_ekibi','tickets')");

        $pdo->exec("UPDATE users SET role = 'admin' WHERE is_admin = 1 AND role = 'user'");

        $pdo->exec("CREATE TABLE IF NOT EXISTS balance_log (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id     INT UNSIGNED NOT NULL,
            amount      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            type        ENUM('payment','purchase','topup','coupon','refund','admin') NOT NULL DEFAULT 'purchase',
            description VARCHAR(255) NULL,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_bl_user (user_id),
            KEY idx_bl_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS email_change_tokens (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id    INT UNSIGNED NOT NULL,
            new_email  VARCHAR(120) NOT NULL,
            token      VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ect_user (user_id),
            KEY idx_ect_token (token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("INSERT IGNORE INTO settings (`key`,`value`) VALUES ('turnstile_enabled','0')");
        $pdo->exec("INSERT IGNORE INTO settings (`key`,`value`) VALUES ('turnstile_site_key','')");
        $pdo->exec("INSERT IGNORE INTO settings (`key`,`value`) VALUES ('turnstile_secret','')");
        $pdo->exec("INSERT IGNORE INTO settings (`key`,`value`) VALUES ('google_indexing_enabled','0')");
        $pdo->exec("INSERT IGNORE INTO settings (`key`,`value`) VALUES ('google_service_json','')");
        $pdo->exec("INSERT IGNORE INTO settings (`key`,`value`) VALUES ('google_oauth_enabled','0')");
        $pdo->exec("INSERT IGNORE INTO settings (`key`,`value`) VALUES ('discord_oauth_enabled','0')");
        $pdo->exec("INSERT IGNORE INTO settings (`key`,`value`) VALUES ('github_oauth_enabled','0')");
        $pdo->exec("CREATE TABLE IF NOT EXISTS oauth_accounts (
            id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id          INT UNSIGNED NOT NULL,
            provider         VARCHAR(20) NOT NULL,
            provider_user_id VARCHAR(64) NOT NULL,
            email            VARCHAR(120) NULL,
            avatar           VARCHAR(255) NULL,
            created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_provider_uid (provider, provider_user_id),
            KEY idx_oa_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        ensure_column($pdo, 'versions', 'product_id', '`product_id` INT UNSIGNED NULL AFTER `is_active`');

        // ── Hesap aktivasyonu / şifre sıfırlama / oturum takibi ──
        ensure_column($pdo, 'users', 'is_active', '`is_active` TINYINT(1) NOT NULL DEFAULT 1');
        ensure_column($pdo, 'users', 'session_version', '`session_version` INT UNSIGNED NOT NULL DEFAULT 0');
        ensure_column($pdo, 'support_messages', 'attachment', '`attachment` VARCHAR(255) NULL');
        ensure_column($pdo, 'user_sessions', 'device_label', '`device_label` VARCHAR(120) NULL');
        ensure_column($pdo, 'user_sessions', 'device_hash', '`device_hash` VARCHAR(64) NULL');

        $pdo->exec("CREATE TABLE IF NOT EXISTS activation_tokens (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id     INT UNSIGNED NOT NULL,
            token_hash  VARCHAR(64) NOT NULL UNIQUE,
            expires_at  DATETIME NOT NULL,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_at_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id          INT UNSIGNED NOT NULL,
            email            VARCHAR(120) NOT NULL,
            code_hash        VARCHAR(255) NOT NULL,
            attempts         INT UNSIGNED NOT NULL DEFAULT 0,
            sends            INT UNSIGNED NOT NULL DEFAULT 1,
            last_sent_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reset_token_hash VARCHAR(64) NULL,
            reset_expires_at DATETIME NULL,
            expires_at       DATETIME NOT NULL,
            created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_pr_user (user_id),
            KEY idx_pr_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS user_sessions (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id    INT UNSIGNED NOT NULL,
            session_key VARCHAR(128) NOT NULL UNIQUE,
            ip         VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            revoked    TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_us_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_typing (
            ticket_id  INT UNSIGNED NOT NULL,
            user_id    INT UNSIGNED NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ticket_id, user_id),
            KEY idx_tt_time (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e) {
        error_log('Vexaris migration hatası: ' . $e->getMessage());
    }
}

function ensure_column(\PDO $pdo, string $table, string $column, string $ddl): void {
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $st->execute([$table, $column]);
        if ((int)$st->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN $ddl");
        }
    } catch (Exception $e) {
        error_log('ensure_column hatası: ' . $e->getMessage());
    }
}

// ── Yardımcı fonksiyonlar ────────────────────────────────────
function current_user(): ?array {
    boot_session();
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool {
    return current_user() !== null;
}

function is_admin(): bool {
    $u = current_user();
    if (!$u) return false;
    // Session'daki değer 1 ise hemen döndür
    if ((int)($u['is_admin'] ?? 0) === 1) return true;
    // Session eski olabilir — DB'den taze kontrol et
    $pdo = db();
    if (!$pdo) return false;
    try {
        $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$u['id']]);
        $row = $stmt->fetch();
        if ($row && (int)$row['is_admin'] === 1) {
            // Session'ı güncelle
            $_SESSION['user']['is_admin'] = 1;
            return true;
        }
    } catch (Exception $e) { /* sessiz */ }
    return false;
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /login'); exit;
    }
}

define('ROLES', ['admin','yonetici','moderator','destek_ekibi','user']);

function user_role(): string {
    $u = current_user();
    if (!$u) return 'user';
    if (isset($u['role']) && in_array($u['role'], ROLES, true)) return $u['role'];
    $pdo = db();
    if ($pdo) {
        try {
            $st = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
            $st->execute([(int)$u['id']]);
            $role = (string)$st->fetchColumn();
            if (in_array($role, ROLES, true)) {
                $_SESSION['user']['role'] = $role;
                return $role;
            }
        } catch (Exception $e) { /* sessiz */ }
    }
    return (int)($u['is_admin'] ?? 0) === 1 ? 'admin' : 'user';
}

function has_permission(string $resource): bool {
    $role = user_role();
    if ($role === 'admin') return true;
    $pdo = db();
    if (!$pdo) return false;
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM permissions WHERE role = ? AND resource = ?");
        $st->execute([$role, $resource]);
        return (int)$st->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function has_any_permission(): bool {
    $role = user_role();
    if ($role === 'admin') return true;
    $pdo = db();
    if (!$pdo) return false;
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM permissions WHERE role = ?");
        $st->execute([$role]);
        return (int)$st->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function role_label(string $role, bool $en = false): string {
    if ($en) {
        $map = ['admin'=>'Admin','yonetici'=>'Manager','moderator'=>'Moderator','destek_ekibi'=>'Support Team','user'=>'Member'];
        return $map[$role] ?? $role;
    }
    $map = ['admin'=>'Admin','yonetici'=>'Yönetici','moderator'=>'Moderatör','destek_ekibi'=>'Destek Ekibi','user'=>'Üye'];
    return $map[$role] ?? $role;
}

function require_admin(): void {
    if (!is_logged_in()) {
        header('Location: /login'); exit;
    }
    if (!is_admin() && !has_any_permission()) {
        header('Location: /dashboard'); exit;
    }
}

function csrf_token(): string {
    boot_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    boot_session();
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sess = $_SESSION['csrf'] ?? '';
    // Guvenlik: oturumda token yoksa bos-eslesme ile gecilemez
    if ($sess === '' || $token === '' || !hash_equals($sess, (string)$token)) {
        http_response_code(403);
        die(json_encode(['ok' => false, 'msg' => 'Geçersiz istek.']));
    }
}

function log_pageview(string $page): void {
    try {
        $pdo = db();
        if (!$pdo) return;
        $pdo->prepare("INSERT INTO page_views (page,ip,user_agent) VALUES (?,?,?)")
            ->execute([$page, $_SERVER['REMOTE_ADDR'] ?? '', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
    } catch (Exception $e) { /* sessiz geç */ }
}

function domain_match(string $domain, string $pattern): bool {
    $domain  = strtolower(trim($domain));
    $pattern = strtolower(trim($pattern));
    if ($domain === '' || $pattern === '') return false;
    if ($domain === $pattern) return true;
    if (strncmp($pattern, '*.', 2) === 0) {
        $suffix = substr($pattern, 2);
        if ($suffix !== '' && substr($domain, -strlen($suffix) - 1) === '.' . $suffix) {
            return true;
        }
    }
    return false;
}

function setting_get(string $key, string $default = ''): string {
    $pdo = db();
    if (!$pdo) return $default;
    try {
        $st = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = ? LIMIT 1");
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return $v === false ? $default : (string)$v;
    } catch (Exception $e) {
        return $default;
    }
}

function setting_set(string $key, string $value): void {
    $pdo = db();
    if (!$pdo) return;
    try {
        $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
            ->execute([$key, $value]);
    } catch (Exception $e) {
        error_log('setting_set hatası: ' . $e->getMessage());
    }
}

function admin_note_html(?string $html): string {
    $html = (string)$html;
    if ($html === '') return '';
    if (strpos($html, '<') === false) return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
    $allowed = '<p><br><div><span><font><b><strong><i><em><u><s><strike><del><a><ul><ol><li><blockquote><code><pre><h1><h2><h3><h4><hr><img>';
    $html = strip_tags($html, $allowed);
    $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/(href|src)\s*=\s*"\s*javascript:[^"]*"/i', '$1="#"', $html);
    $html = preg_replace("/(href|src)\s*=\s*'\s*javascript:[^']*'/i", "$1='#'", $html);
    return $html;
}

function submission_domain_host(string $raw): string {
    $d = trim($raw);
    if ($d === '') return '';
    $d = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $d);
    $d = preg_replace('#^[^@]*@#', '', $d);
    $part = preg_split('~[/?#]~', (string)$d, 2);
    $d = is_array($part) ? (string)($part[0] ?? '') : '';
    $d = preg_replace('/:\d+$/', '', $d);
    $d = trim((string)$d, " \t\n\r\0\x0B./");
    // Multibyte-güvenli küçük harf (Türkçe İ/ı dahil)
    $d = function_exists('mb_strtolower') ? mb_strtolower($d, 'UTF-8') : strtolower($d);
    $d = preg_replace('/^\*\./', '', $d);
    $d = preg_replace('/^www\./i', '', $d);
    $d = trim((string)$d, " \t\n\r\0\x0B./");
    if ($d === '' || strlen($d) > 253) return '';
    if (filter_var($d, FILTER_VALIDATE_IP)) return $d;
    // IDN: Türkçe karakterli domainleri punycode'a çevir (örn. kılıç.com.tr -> xn--...)
    if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7e]/', $d)) {
        $idn = idn_to_ascii($d, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if (!is_string($idn) || $idn === '') return '';
        $d = strtolower($idn);
    }
    // Etiket etiket doğrula (TLD bağımsız: tüm TR ve global domainler)
    $labels = explode('.', $d);
    if (count($labels) < 2) return '';
    foreach ($labels as $lab) {
        if ($lab === '' || strlen($lab) > 63) return '';
        if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $lab)) continue;
        // Punycode toleransı: xn-- ile başlayan etiketler
        if (preg_match('/^xn--[a-z0-9-]+$/', $lab)) continue;
        return '';
    }
    if (strlen($d) > 253) return '';
    return $d;
}

function submission_domain_patterns(string $host): array {
    $host = submission_domain_host($host);
    if ($host === '') return [];
    if (filter_var($host, FILTER_VALIDATE_IP)) return [$host];
    return [$host, '*.' . $host];
}

function client_ip(): string {
    $cands = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['HTTP_X_REAL_IP'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];
    foreach ($cands as $c) {
        $parts = explode(',', (string)$c);
        $ip = trim($parts[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return $ip;
    }
    return trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
}

function store_money(float $amount, string $cur = 'TRY'): string {
    $c = strtoupper(trim($cur));
    if ($c === 'USD') return '$' . number_format($amount, 2, '.', ',');
    if ($c === 'EUR') return number_format($amount, 2, ',', '.') . ' €';
    return '₺' . number_format($amount, 2, ',', '.');
}

function saas_latest_version(?PDO $pdo = null): string {
    try {
        $pdo = $pdo ?: db();
        if (!$pdo) return '';
        $st = $pdo->query("SELECT version FROM versions WHERE is_active=1 ORDER BY id DESC LIMIT 1");
        $v = trim((string)$st->fetchColumn());
        if ($v !== '' && strlen($v) <= 30) return $v;
    } catch (Exception $e) {}
    return '';
}

function user_effective_plan(int $userId): array {
    $out = ['plan' => 'free', 'cycle' => 'monthly', 'live' => false, 'until' => 0, 'license' => null];
    if ($userId <= 0) return $out;
    try {
        $pdo = db();
        if (!$pdo) return $out;
        $sq = $pdo->prepare("SELECT plan, cycle, status, current_period_end FROM user_subscriptions WHERE user_id=? LIMIT 1");
        $sq->execute([$userId]);
        $sub = $sq->fetch() ?: null;
        $ls = $pdo->prepare("SELECT license_key, allowed_domains, features, status FROM licenses WHERE user_id=? ORDER BY id ASC LIMIT 1");
        $ls->execute([$userId]);
        $lic = $ls->fetch() ?: null;
        if ($lic) {
            $out['license'] = $lic;
            $f = json_decode((string)$lic['features'], true);
            if (is_array($f) && isset($f['plan'])) {
                $p = strtolower(trim((string)$f['plan']));
                if (in_array($p, ['free', 'starter', 'professional', 'enterprise'], true)) $out['plan'] = $p;
            }
        }
        if ($sub && ($sub['status'] ?? '') === 'active' && (int)($sub['current_period_end'] ?? 0) > time()) {
            $p = strtolower(trim((string)($sub['plan'] ?? 'free')));
            if (in_array($p, ['starter', 'professional', 'enterprise'], true)) {
                $out['plan'] = $p;
                $out['cycle'] = ($sub['cycle'] ?? '') === 'yearly' ? 'yearly' : 'monthly';
                $out['live'] = true;
                $out['until'] = (int)$sub['current_period_end'];
            }
        }
    } catch (Throwable $e) {}
    return $out;
}

function notify_user(int $userId, string $type, string $title, string $message): void {
    $pdo = db();
    if (!$pdo || $userId <= 0) return;
    try {
        $pdo->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?,?,?,?)")
            ->execute([$userId, $type, $title, $message]);
    } catch (Exception $e) {
        error_log('notify_user hatası: ' . $e->getMessage());
    }
}

// ── Demo alt alanı DB bağlantısı ────────────────────────────
function demo_db(): ?PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=localhost;dbname=vexaris_demo;charset=utf8mb4';
            $pdo = new PDO($dsn, 'vexaris_dem_usr', 'REDACTED_DEMO_DB_PASSWORD', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5,
            ]);
        } catch (PDOException $e) {
            error_log('Vexaris demo DB bağlantı hatası: ' . $e->getMessage());
            return null;
        }
    }
    return $pdo;
}

// ── Sayfa yüklenince migrate et (30 dk'da bir; her istekte 30+ DDL
// sorgusu bot yükünde CPU patlatıyordu, IF NOT EXISTS güvencesiyle atlanır)
if (!function_exists('run_migrations_guarded')) {
    function run_migrations_guarded(): void {
        try {
            $flag = sys_get_temp_dir() . '/vexaris_mig_ok';
            $mtime = @filemtime($flag);
            if ($mtime !== false && (time() - $mtime) < 1800) return;
            run_migrations();
            @touch($flag);
        } catch (Throwable $e) {
            try { run_migrations(); } catch (Throwable $e2) {}
        }
    }
}
run_migrations_guarded();

<?php
require_once __DIR__ . '/db.php';

function oauth_providers(): array {
    return [
        'google' => [
            'label' => 'Google',
            'auth'  => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token' => 'https://oauth2.googleapis.com/token',
            'user'  => 'https://openidconnect.googleapis.com/v1/userinfo',
            'scope' => 'openid email profile',
        ],
        'discord' => [
            'label' => 'Discord',
            'auth'  => 'https://discord.com/api/oauth2/authorize',
            'token' => 'https://discord.com/api/oauth2/token',
            'user'  => 'https://discord.com/api/users/@me',
            'scope' => 'identify email',
        ],
        'github' => [
            'label' => 'GitHub',
            'auth'  => 'https://github.com/login/oauth/authorize',
            'token' => 'https://github.com/login/oauth/access_token',
            'user'  => 'https://api.github.com/user',
            'scope' => 'read:user user:email',
        ],
        'microsoft' => [
            'label' => 'Microsoft',
            'auth'  => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'token' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'user'  => 'https://graph.microsoft.com/oidc/userinfo',
            'scope' => 'openid email profile',
        ],
    ];
}

function oauth_is_configured(string $p): bool {
    $all = oauth_providers();
    if (!isset($all[$p])) return false;
    return setting_get($p . '_oauth_enabled', '0') === '1'
        && setting_get($p . '_client_id', '') !== ''
        && setting_get($p . '_client_secret', '') !== '';
}

function oauth_configured_list(): array {
    $out = [];
    foreach (oauth_providers() as $p => $cfg) {
        if (oauth_is_configured($p)) $out[$p] = $cfg;
    }
    return $out;
}

function oauth_base_url(): string {
    if (defined('SITE_URL') && (string)SITE_URL !== '') {
        $u = rtrim((string)SITE_URL, '/');
        if (preg_match('#^https?://#i', $u)) return $u;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https://' : 'http://') . $host;
}

function oauth_redirect_uri(string $p): string {
    return oauth_base_url() . '/api/oauth?provider=' . $p . '&action=callback';
}

function oauth_login_url(string $p, string $next = ''): string {
    $all = oauth_providers();
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state_' . $p] = ['state' => $state, 'next' => $next, 'ts' => time()];
    $q = http_build_query([
        'client_id'     => setting_get($p . '_client_id', ''),
        'redirect_uri'  => oauth_redirect_uri($p),
        'response_type' => 'code',
        'scope'         => $all[$p]['scope'],
        'state'         => $state,
    ]);
    return $all[$p]['auth'] . '?' . $q;
}

function oauth_http_post(string $url, array $params, array $headers = []): ?array {
    $h = array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers);
    $opts = ['http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $h) . "\r\n",
        'content' => http_build_query($params),
        'timeout' => 10,
        'ignore_errors' => true,
    ]];
    $raw = @file_get_contents($url, false, stream_context_create($opts));
    if ($raw === false) return null;
    $j = json_decode($raw, true);
    return is_array($j) ? $j : null;
}

function oauth_http_get(string $url, string $token): ?array {
    $opts = ['http' => [
        'method' => 'GET',
        'header' => "Authorization: Bearer " . $token . "\r\nAccept: application/json\r\nUser-Agent: Vexaris-OAuth/1.0\r\n",
        'timeout' => 10,
        'ignore_errors' => true,
    ]];
    $raw = @file_get_contents($url, false, stream_context_create($opts));
    if ($raw === false) return null;
    $j = json_decode($raw, true);
    return is_array($j) ? $j : null;
}

function oauth_normalize(string $p, array $u, string $at): ?array {
    if ($p === 'google') {
        if (empty($u['sub'])) return null;
        $verified = !empty($u['email_verified']);
        return [
            'pid' => (string)$u['sub'],
            'email' => (!empty($u['email']) && $verified) ? $u['email'] : null,
            'name' => $u['name'] ?? null,
            'avatar' => $u['picture'] ?? null,
        ];
    }
    if ($p === 'microsoft') {
        $pid = $u['sub'] ?? $u['oid'] ?? null;
        if (empty($pid)) return null;
        return [
            'pid' => (string)$pid,
            'email' => !empty($u['email']) ? $u['email'] : null,
            'name' => $u['name'] ?? null,
            'avatar' => null,
        ];
    }
    if ($p === 'discord') {
        if (empty($u['id'])) return null;
        $av = null;
        if (!empty($u['avatar'])) $av = 'https://cdn.discordapp.com/avatars/' . $u['id'] . '/' . $u['avatar'] . '.png?size=128';
        $verified = !empty($u['verified']);
        return [
            'pid' => (string)$u['id'],
            'email' => (!empty($u['email']) && $verified) ? $u['email'] : null,
            'name' => $u['global_name'] ?? $u['username'] ?? null,
            'avatar' => $av,
        ];
    }
    if (empty($u['id'])) return null;
    $email = null;
    if (!empty($u['email'])) {
        $em = oauth_http_get('https://api.github.com/user/emails', $at);
        if (is_array($em)) {
            foreach ($em as $e) {
                if (!empty($e['primary']) && !empty($e['verified']) && !empty($e['email'])) { $email = $e['email']; break; }
            }
            if (!$email) {
                // Guvenlik: dogrulanmamis GitHub e-postasiyla hesap eslestirme YAPILMAZ
                // (aksi halde baskasinin e-postasini profiline ekleyen biri o hesaba girebilir).
                $email = null;
            }
        }
    }
    return [
        'pid' => (string)$u['id'],
        'email' => $email,
        'name' => $u['name'] ?? $u['login'] ?? null,
        'avatar' => $u['avatar_url'] ?? null,
    ];
}

function oauth_linked_accounts(int $uid): array {
    $pdo = db();
    if (!$pdo || $uid <= 0) return [];
    try {
        $st = $pdo->prepare("SELECT provider, email, avatar, created_at FROM oauth_accounts WHERE user_id = ?");
        $st->execute([$uid]);
        $out = [];
        foreach ($st->fetchAll() as $r) $out[$r['provider']] = $r;
        return $out;
    } catch (Throwable $e) { return []; }
}

function oauth_unique_username(string $base, \PDO $pdo): string {
    $base = strtolower((string)preg_replace('/[^a-z0-9_]/i', '', str_replace(' ', '_', $base)));
    if ($base === '') $base = 'kullanici';
    $base = substr($base, 0, 30);
    $try = $base;
    $i = 0;
    $st = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    while (true) {
        $st->execute([$try]);
        if (!$st->fetch()) return $try;
        $i++;
        $try = substr($base, 0, 26) . $i;
        if ($i > 999) return $base . '_' . bin2hex(random_bytes(3));
    }
}

function oauth_err_set(string $e): void {
    $GLOBALS['vx_oauth_err'] = $e;
}

function oauth_err_take(): string {
    $e = (string)($GLOBALS['vx_oauth_err'] ?? '');
    unset($GLOBALS['vx_oauth_err']);
    return $e;
}

function oauth_find_or_create(string $p, array $n): ?array {
    $pdo = db();
    if (!$pdo) return null;
    $st = $pdo->prepare("SELECT u.* FROM users u JOIN oauth_accounts o ON o.user_id = u.id WHERE o.provider = ? AND o.provider_user_id = ? LIMIT 1");
    $st->execute([$p, $n['pid']]);
    $user = $st->fetch();
    if ($user) return $user;
    if (!empty($n['email'])) {
        $st = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $st->execute([$n['email']]);
        $user = $st->fetch();
    }
    if (!$user) {
        // OAuth e-postası whitelist dışıysa sahte local adresle hesap AÇILMAZ.
        $candEmail = $n['email'] ?: '';
        if ($candEmail !== '' && function_exists('multi_email_allowed')) {
            try { require_once __DIR__ . '/multi.php'; } catch (Throwable $e0) {}
            if (!multi_email_allowed($candEmail)) return null;
        }
        try {
            if (function_exists('multi_ip_gate')) {
                $gIp = multi_ip_gate($pdo, client_ip());
                if (!$gIp['ok']) {
                    $bl = multi_bypass_emails();
                    $ce = strtolower(trim($candEmail));
                    $isBy = $bl && $ce !== '' && (in_array($ce, $bl, true) || in_array(multi_normalize_email($ce), $bl, true));
                    if (!$isBy) { oauth_err_set('iplimit'); return null; }
                }
            }
        } catch (Throwable $eIp) {}
        $seed = $n['name'] ?? ($n['email'] ? strstr($n['email'], '@', true) : 'kullanici');
        $uname = oauth_unique_username($seed, $pdo);
        $email = $n['email'] ?: ($uname . '@' . $p . '.local');
        $pwd = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);
        $normEmail = function_exists('multi_normalize_email') ? multi_normalize_email($email) : strtolower($email);
        $pdo->prepare("INSERT INTO users (username, email, password, email_norm, reg_ip, password_set) VALUES (?, ?, ?, ?, ?, 0)")
            ->execute([$uname, $email, $pwd, $normEmail, function_exists('client_ip') ? client_ip() : '']);
        $id = (int)$pdo->lastInsertId();
        // Cihaz kaydını hemen işle (kayıt anında multi tespiti için).
        try {
            if (function_exists('multi_after_register')) {
                require_once __DIR__ . '/multi.php';
                multi_after_register($pdo, $id, ['linked' => [], 'signals' => []]);
            }
        } catch (Throwable $e0b) {}
        $st = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $st->execute([$id]);
        $user = $st->fetch();
        if (!$user) return null;
    }
    try {
        $pdo->prepare("INSERT IGNORE INTO oauth_accounts (user_id, provider, provider_user_id, email, avatar) VALUES (?,?,?,?,?)")
            ->execute([$user['id'], $p, $n['pid'], $n['email'], $n['avatar']]);
    } catch (Throwable $e) {}
    return $user;
}

function oauth_finish(string $p): array {
    $saved = $_SESSION['oauth_state_' . $p] ?? null;
    unset($_SESSION['oauth_state_' . $p]);
    $state = (string)($_GET['state'] ?? '');
    $code = (string)($_GET['code'] ?? '');
    if (isset($_GET['error'])) return ['error' => 'denied'];
    if (!is_array($saved) || !hash_equals((string)($saved['state'] ?? ''), $state) || $code === '' || (time() - (int)($saved['ts'] ?? 0)) > 600) {
        return ['error' => 'bad_state'];
    }
    $all = oauth_providers();
    $extra = $p === 'github' ? ['Accept: application/json'] : [];
    $tok = oauth_http_post($all[$p]['token'], [
        'client_id' => setting_get($p . '_client_id', ''),
        'client_secret' => setting_get($p . '_client_secret', ''),
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => oauth_redirect_uri($p),
    ], $extra);
    $at = (is_array($tok) && isset($tok['access_token'])) ? (string)$tok['access_token'] : '';
    if ($at === '') return ['error' => 'bad_token'];
    $u = oauth_http_get($all[$p]['user'], $at);
    if (!is_array($u)) return ['error' => 'bad_user'];
    $norm = oauth_normalize($p, $u, $at);
    if (!$norm || empty($norm['pid'])) return ['error' => 'bad_user'];
    $pdoM = db();
    if ($pdoM) {
        try {
            require_once __DIR__ . '/multi.php';
            multi_ensure_tables($pdoM);
            // Banlı sosyal hesap içeri giremez.
            $st = $pdoM->prepare("SELECT u.* FROM users u JOIN oauth_accounts o ON o.user_id = u.id WHERE o.provider = ? AND o.provider_user_id = ? LIMIT 1");
            $st->execute([$p, $norm['pid']]);
            if (($ex = $st->fetch()) && (string)($ex['ban_reason'] ?? '') !== '' && (int)($ex['is_active'] ?? 1) === 0) {
                return ['error' => 'banned'];
            }
        } catch (Throwable $e0) {}
    }
    $cu = current_user();
    if ($cu) {
        $pdo = db();
        if (!$pdo) return ['error' => 'no_user'];
        $st = $pdo->prepare("SELECT user_id FROM oauth_accounts WHERE provider = ? AND provider_user_id = ? LIMIT 1");
        $st->execute([$p, $norm['pid']]);
        $owner = $st->fetchColumn();
        if ($owner !== false && (int)$owner !== (int)$cu['id']) return ['error' => 'taken'];
        $pdo->prepare("INSERT INTO oauth_accounts (user_id, provider, provider_user_id, email, avatar) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE email = VALUES(email), avatar = VALUES(avatar)")
            ->execute([(int)$cu['id'], $p, $norm['pid'], $norm['email'], $norm['avatar']]);
        if (!empty($norm['avatar']) && is_string($norm['avatar'])) {
            try { $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([mb_substr(trim($norm['avatar']), 0, 255), (int)$cu['id']]); } catch (Throwable $e) {}
        }
        return ['target' => '/dashboard/account?linked=' . $p];
    }
    $user = oauth_find_or_create($p, $norm);
    if (!$user) return ['error' => (oauth_err_take() ?: 'no_user')];
    // Kayıt sonrası: whitelist + multi koruması (OAuth'ta bypass yok).
    try {
        require_once __DIR__ . '/multi.php';
        $pdoM2 = db();
        if ($pdoM2) {
            multi_ensure_tables($pdoM2);
            if (!empty($user['email']) && !multi_email_allowed((string)$user['email'])) {
                return ['error' => 'domain'];
            }
            if (!multi_is_exempt($pdoM2, (int)$user['id']) && multi_ip_over_cap($pdoM2, client_ip(), (int)$user['id'])) {
                return ['error' => 'iplimit'];
            }
            multi_after_login($pdoM2, (int)$user['id']);
            try { multi_group_sync($pdoM2, (int)$user['id']); } catch (Throwable $eGs) {}
            if (!function_exists('risk_guard')) require_once __DIR__ . '/risk.php';
            if (risk_guard($pdoM2, (int)$user['id'], 'login') === 'banned') {
                return ['error' => 'banned'];
            }
            $st = $pdoM2->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $st->execute([(int)$user['id']]);
            $fresh = $st->fetch();
            if ($fresh) $user = $fresh;
        }
    } catch (Throwable $e1) {}
    if ((string)($user['ban_reason'] ?? '') !== '' && (int)($user['is_active'] ?? 1) === 0) {
        return ['error' => 'banned'];
    }
    $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'is_admin' => $user['is_admin'],
        'sess_ver' => (int)($user['session_version'] ?? 0),
    ];
    session_regenerate_id(true);
    $next = (isset($saved['next']) && is_string($saved['next']) && preg_match('#^/(?![/\\\\%5c])#i', $saved['next'])) ? $saved['next'] : '';
    return ['target' => $next !== '' ? $next : ($user['is_admin'] ? '/admin' : '/dashboard')];
}

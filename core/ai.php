<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../config/autoload.php';

define('AI_QUOTA_MONTHLY', 1000000);
define('AI_RATE_PER_MIN', 20);
define('AI_CREDITS_MONTHLY', 200);

function ai_tables(): void {
    $pdo = db();
    if (!$pdo) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_providers (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        provider   VARCHAR(20) NOT NULL DEFAULT 'openrouter',
        model_id   VARCHAR(120) NOT NULL DEFAULT '',
        api_key    TEXT NOT NULL,
        is_active  TINYINT(1) NOT NULL DEFAULT 1,
        priority   INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ap_active (is_active, priority)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_quotas (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        domain     VARCHAR(253) NOT NULL DEFAULT '',
        month      CHAR(7) NOT NULL DEFAULT '',
        used_input INT UNSIGNED NOT NULL DEFAULT 0,
        used_output INT UNSIGNED NOT NULL DEFAULT 0,
        UNIQUE KEY uq_aq_domain_month (domain, month),
        KEY idx_aq_month (month)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (function_exists('ensure_column')) ensure_column($pdo, 'ai_providers', 'credit_rate', "`credit_rate` DECIMAL(10,4) NOT NULL DEFAULT 0");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_credits (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        domain     VARCHAR(253) NOT NULL DEFAULT '',
        month      CHAR(7) NOT NULL DEFAULT '',
        credits    INT UNSIGNED NOT NULL DEFAULT 0,
        UNIQUE KEY uq_ac_domain_month (domain, month),
        KEY idx_ac_month (month)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_usage_log (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        domain     VARCHAR(253) NOT NULL DEFAULT '',
        provider   VARCHAR(20) NOT NULL DEFAULT '',
        model_id   VARCHAR(120) NOT NULL DEFAULT '',
        input_t    INT UNSIGNED NOT NULL DEFAULT 0,
        output_t   INT UNSIGNED NOT NULL DEFAULT 0,
        ok         TINYINT(1) NOT NULL DEFAULT 0,
        err        VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_aul_domain (domain),
        KEY idx_aul_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_request_log (
        id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        domain     VARCHAR(253) NOT NULL DEFAULT '',
        ip         VARCHAR(45) NOT NULL DEFAULT '',
        plan       VARCHAR(20) NOT NULL DEFAULT '',
        lang       CHAR(2) NOT NULL DEFAULT '',
        mode       VARCHAR(12) NOT NULL DEFAULT '',
        provider   VARCHAR(20) NOT NULL DEFAULT '',
        model_id   VARCHAR(120) NOT NULL DEFAULT '',
        prompt     TEXT NULL,
        messages   MEDIUMTEXT NULL,
        tools      TEXT NULL,
        tool_calls TEXT NULL,
        reply      MEDIUMTEXT NULL,
        in_t       INT UNSIGNED NOT NULL DEFAULT 0,
        out_t      INT UNSIGNED NOT NULL DEFAULT 0,
        credits    INT UNSIGNED NOT NULL DEFAULT 0,
        ms         INT UNSIGNED NOT NULL DEFAULT 0,
        ok         TINYINT(1) NOT NULL DEFAULT 0,
        status     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        err        VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_arl_created (created_at),
        KEY idx_arl_domain (domain, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function ai_master_key(): string {
    return hash_hmac('sha256', 'ai-at-rest-v1', _vx_k());
}

function ai_encrypt(string $plain): string {
    $iv = random_bytes(16);
    $ct = openssl_encrypt($plain, 'AES-256-CBC', ai_master_key(), OPENSSL_RAW_DATA, $iv);
    if ($ct === false) return '';
    return base64_encode($iv . $ct);
}

function ai_decrypt(string $blob): string {
    $raw = base64_decode($blob, true);
    if ($raw === false || strlen($raw) < 17) return '';
    $pt = openssl_decrypt(substr($raw, 16), 'AES-256-CBC', ai_master_key(), OPENSSL_RAW_DATA, substr($raw, 0, 16));
    return is_string($pt) ? $pt : '';
}

function ai_mask(string $key): string {
    $k = trim($key);
    if (strlen($k) <= 8) return '****';
    return substr($k, 0, 3) . '****' . substr($k, -4);
}

function ai_active_provider(?PDO $pdo = null): ?array {
    $pdo = $pdo ?: db();
    if (!$pdo) return null;
    try {
        $row = $pdo->query("SELECT * FROM ai_providers WHERE is_active=1 ORDER BY priority DESC, id ASC LIMIT 1")->fetch();
        if (!$row) return null;
        $row['api_key_plain'] = ai_decrypt((string)($row['api_key'] ?? ''));
        if ($row['api_key_plain'] === '') return null;
        return $row;
    } catch (Exception $e) { return null; }
}

function ai_active_providers(?PDO $pdo = null): array {
    $pdo = $pdo ?: db();
    if (!$pdo) return [];
    try {
        $rows = $pdo->query("SELECT * FROM ai_providers WHERE is_active=1 ORDER BY priority DESC, id ASC")->fetchAll();
    } catch (Exception $e) { return []; }
    $out = [];
    foreach ($rows as $row) {
        $row['api_key_plain'] = ai_decrypt((string)($row['api_key'] ?? ''));
        if ($row['api_key_plain'] === '') continue;
        $out[] = $row;
    }
    return $out;
}

function ai_quota_use(?PDO $pdo, string $domain): array {
    $pdo = $pdo ?: db();
    $month = date('Y-m');
    $used = 0;
    if ($pdo) {
        try {
            $st = $pdo->prepare("SELECT used_input+used_output AS t FROM ai_quotas WHERE domain=? AND month=? LIMIT 1");
            $st->execute([$domain, $month]);
            $used = (int)($st->fetchColumn() ?: 0);
        } catch (Exception $e) {}
    }
    return ['used' => $used, 'limit' => AI_QUOTA_MONTHLY, 'left' => max(0, AI_QUOTA_MONTHLY - $used)];
}

function ai_quota_add(?PDO $pdo, string $domain, int $in, int $out): void {
    $pdo = $pdo ?: db();
    if (!$pdo) return;
    $month = date('Y-m');
    try {
        $pdo->prepare("INSERT INTO ai_quotas (domain, month, used_input, used_output) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE used_input=used_input+VALUES(used_input), used_output=used_output+VALUES(used_output)")->execute([$domain, $month, max(0, $in), max(0, $out)]);
    } catch (Exception $e) {}
}

function ai_credit_cost(float $rate, int $in, int $out): int {
    return 1 + (int)ceil(($in + $out) / 1000 * max(0, $rate));
}
function ai_credit_use(?PDO $pdo, string $domain): array {
    $pdo = $pdo ?: db();
    $month = date('Y-m');
    $used = 0;
    if ($pdo) {
        try {
            $st = $pdo->prepare("SELECT credits FROM ai_credits WHERE domain=? AND month=? LIMIT 1");
            $st->execute([$domain, $month]);
            $used = (int)($st->fetchColumn() ?: 0);
        } catch (Exception $e) {}
    }
    return ['used' => $used, 'limit' => AI_CREDITS_MONTHLY, 'left' => max(0, AI_CREDITS_MONTHLY - $used)];
}
function ai_credit_add(?PDO $pdo, string $domain, int $credits): void {
    $pdo = $pdo ?: db();
    if (!$pdo || $credits <= 0) return;
    $month = date('Y-m');
    try {
        $pdo->prepare("INSERT INTO ai_credits (domain, month, credits) VALUES (?,?,?) ON DUPLICATE KEY UPDATE credits=credits+VALUES(credits)")->execute([$domain, $month, $credits]);
    } catch (Exception $e) {}
}
function ai_usage_log(?PDO $pdo, string $domain, string $provider, string $model, int $in, int $out, bool $ok, string $err = '', int $credits = 0): void {
    $pdo = $pdo ?: db();
    if (!$pdo) return;
    try {
        if (function_exists('ensure_column')) ensure_column($pdo, 'ai_usage_log', 'credits', "`credits` INT UNSIGNED NOT NULL DEFAULT 0");
        $pdo->prepare("INSERT INTO ai_usage_log (domain, provider, model_id, input_t, output_t, ok, err, credits) VALUES (?,?,?,?,?,?,?,?)")->execute([$domain, $provider, $model, max(0, $in), max(0, $out), $ok ? 1 : 0, mb_substr($err, 0, 255), max(0, $credits)]);
    } catch (Exception $e) {
        try {
            $pdo->prepare("INSERT INTO ai_usage_log (domain, provider, model_id, input_t, output_t, ok, err) VALUES (?,?,?,?,?,?,?)")->execute([$domain, $provider, $model, max(0, $in), max(0, $out), $ok ? 1 : 0, mb_substr($err, 0, 255)]);
        } catch (Exception $e2) {}
    }
}

function ai_req_log(?PDO $pdo, array $c): void {
    $pdo = $pdo ?: db();
    if (!$pdo) return;
    try {
        static $pruned = false;
        if (!$pruned) {
            $pruned = true;
            if (random_int(1, 50) === 1) {
                $pdo->exec("DELETE FROM ai_request_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)");
            }
        }
        $t = function ($v, int $n): string { return mb_substr(trim((string)$v), 0, $n); };
        $pdo->prepare("INSERT INTO ai_request_log (domain, ip, plan, lang, mode, provider, model_id, prompt, messages, tools, tool_calls, reply, in_t, out_t, credits, ms, ok, status, err) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
            $t($c['domain'] ?? '', 253),
            $t($c['ip'] ?? '', 45),
            $t($c['plan'] ?? '', 20),
            $t($c['lang'] ?? '', 2),
            $t($c['mode'] ?? '', 12),
            $t($c['provider'] ?? '', 20),
            $t($c['model_id'] ?? '', 120),
            $t($c['prompt'] ?? '', 8000),
            $t($c['messages'] ?? '', 60000),
            $t($c['tools'] ?? '', 4000),
            $t($c['tool_calls'] ?? '', 8000),
            $t($c['reply'] ?? '', 20000),
            max(0, (int)($c['in_t'] ?? 0)),
            max(0, (int)($c['out_t'] ?? 0)),
            max(0, (int)($c['credits'] ?? 0)),
            max(0, (int)($c['ms'] ?? 0)),
            !empty($c['ok']) ? 1 : 0,
            max(0, (int)($c['status'] ?? 0)),
            $t($c['err'] ?? '', 255),
        ]);
    } catch (Throwable $e) {}
}

function ai_http_post(string $url, array $headers, string $body, int $timeout = 60): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'body' => is_string($resp) ? $resp : '', 'err' => $err];
}

function ai_estimate_tokens(string $text): int {
    return (int)ceil(mb_strlen($text) / 4);
}

function ai_read_uploads(): array {
    $out = [];
    if (empty($_FILES)) return $out;
    $allowed = ['image/png' => 1, 'image/jpeg' => 1, 'image/webp' => 1, 'text/plain' => 1, 'application/pdf' => 1];
    foreach ($_FILES as $f) {
        $names = is_array($f['name'] ?? null) ? $f['name'] : [$f['name'] ?? ''];
        $tmps = is_array($f['tmp_name'] ?? null) ? $f['tmp_name'] : [$f['tmp_name'] ?? ''];
        $errs = is_array($f['error'] ?? null) ? $f['error'] : [$f['error'] ?? UPLOAD_ERR_NO_FILE];
        $sizes = is_array($f['size'] ?? null) ? $f['size'] : [$f['size'] ?? 0];
        $n = count($tmps);
        for ($i = 0; $i < $n && count($out) < 3; $i++) {
            if (($errs[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            if ((int)($sizes[$i] ?? 0) > 4194304) continue;
            $tmp = (string)($tmps[$i] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) continue;
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$fi->file($tmp);
            if (!isset($allowed[$mime])) continue;
            $body = file_get_contents($tmp);
            if (!is_string($body) || $body === '') continue;
            if ($mime === 'text/plain') $body = mb_substr($body, 0, 20000);
            $out[] = ['mime' => $mime, 'b64' => base64_encode($body), 'name' => mb_substr((string)($names[$i] ?? 'dosya'), 0, 120), 'text' => $mime === 'text/plain' ? $body : ''];
        }
    }
    return $out;
}
/* Istegi KUR (gonder mez). Hedging icin curl_multi ile paralel kullanilir. */
function ai_req_build(array $provider, array $messages, int $maxTokens, array $files): array {
    $model = (string)($provider['model_id'] ?? '');
    $key = (string)($provider['api_key_plain'] ?? '');
    if ($model === '' || $key === '') return ['err' => 'provider eksik'];
    $kind = (string)($provider['provider'] ?? '');

    if ($kind === 'google') {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . urlencode($key);
        $contents = [];
        $first = true;
        foreach ($messages as $m) {
            $role = ($m['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
            $parts = [['text' => (string)($m['content'] ?? '')]];
            if ($first && $role === 'user' && $files) {
                foreach ($files as $fl) {
                    if (strncmp((string)($fl['mime'] ?? ''), 'text/', 5) === 0) continue;
                    $parts[] = ['inlineData' => ['mimeType' => (string)$fl['mime'], 'data' => (string)$fl['b64']]];
                }
            }
            $first = false;
            $contents[] = ['role' => $role, 'parts' => $parts];
        }
        return [
            'kind' => 'google',
            'url' => $url,
            'headers' => ['Content-Type: application/json'],
            'body' => json_encode(['contents' => $contents, 'generationConfig' => ['maxOutputTokens' => $maxTokens]]),
        ];
    }

    $isDahl = $kind === 'dahl';
    $url = $isDahl ? 'https://inference.dahl.global/v1/chat/completions' : 'https://openrouter.ai/api/v1/chat/completions';
    $om = [];
    $ofirst = true;
    foreach ($messages as $m) {
        $role = ($m['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';
        $content = [['type' => 'text', 'text' => (string)($m['content'] ?? '')]];
        if ($ofirst && $role === 'user' && $files) {
            foreach ($files as $fl) {
                $mime = (string)($fl['mime'] ?? '');
                if (strncmp($mime, 'text/', 5) === 0) continue;
                $b64 = (string)($fl['b64'] ?? '');
                if ($b64 === '') continue;
                if (strncmp($mime, 'image/', 6) === 0) {
                    $content[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $mime . ';base64,' . $b64]];
                } elseif ($mime === 'application/pdf' && !$isDahl) {
                    $content[] = ['type' => 'file', 'file' => ['filename' => (string)($fl['name'] ?? 'dosya.pdf'), 'file_data' => 'data:application/pdf;base64,' . $b64]];
                }
            }
        }
        $ofirst = false;
        $om[] = ['role' => $role, 'content' => $content];
    }
    $txtExtra = '';
    foreach ($files as $fl) {
        if (strncmp((string)($fl['mime'] ?? ''), 'text/', 5) === 0 && !empty($fl['text'])) $txtExtra .= "\n\n[EKLİ DOSYA " . ($fl['name'] ?? 'metin') . "]\n" . mb_substr((string)$fl['text'], 0, 20000);
    }
    if ($txtExtra !== '' && isset($om[0]) && is_array($om[0]['content'])) $om[0]['content'][0]['text'] .= $txtExtra;
    return [
        'kind' => $isDahl ? 'dahl' : 'openrouter',
        'url' => $url,
        'headers' => ['Content-Type: application/json', 'Authorization: Bearer ' . $key, 'HTTP-Referer: https://vexaris.net', 'X-Title: Vexaris AI'],
        'body' => json_encode(['model' => $model, 'messages' => $om, 'max_tokens' => $maxTokens]),
    ];
}

/* Yaniti COZ. */
function ai_req_parse(?array $provider, int $code, string $body): array {
    $label = (string)($provider['provider'] ?? 'provider');
    if ($code === 429) return ['ok' => false, 'rate' => true, 'msg' => $label . ' http 429'];
    if ($code < 200 || $code >= 300) {
        if ($body === '') return ['ok' => false, 'timeout' => true, 'msg' => $label . ' timeout'];
        return ['ok' => false, 'msg' => $label . ' http ' . $code];
    }
    $j = json_decode($body, true);
    if (($provider['provider'] ?? '') === 'google') {
        $text = '';
        foreach (($j['candidates'][0]['content']['parts'] ?? []) as $pt) $text .= (string)($pt['text'] ?? '');
        $meta = $j['usageMetadata'] ?? [];
        return ['ok' => true, 'text' => $text, 'in' => (int)($meta['promptTokenCount'] ?? 0), 'out' => (int)($meta['candidatesTokenCount'] ?? 0)];
    }
    $u = $j['usage'] ?? [];
    return [
        'ok' => true,
        'text' => (string)($j['choices'][0]['message']['content'] ?? ''),
        'in' => (int)($u['prompt_tokens'] ?? 0),
        'out' => (int)($u['completion_tokens'] ?? 0),
    ];
}

function ai_call_provider(array $provider, array $messages, int $maxTokens = 1024, array $files = [], int $timeout = 0): array {
    $req = ai_req_build($provider, $messages, $maxTokens, $files);
    if (isset($req['err'])) return ['ok' => false, 'msg' => $req['err']];
    if ($timeout <= 0) $timeout = 90;
    $r = ai_http_post($req['url'], $req['headers'], $req['body'], $timeout);
    return ai_req_parse($provider, (int)($r['code'] ?? 0), (string)($r['body'] ?? ''));
}

/* HEDGING: birincil saglayici (dahl) yavas/kuyrukta olabiliyor (9-61 s).
   Bu yuzden ilk istek baslar; $hedgeAfter saniyede yanit gelmezse yedek
   saglayici PARALEL baslatilir ve HANGISI ONCE donerse o kullanilir.
   Boylece dahl hizliysa o kazanir, yavassa toplam sure yedek saglayicinin
   hiziyla sinirli kalir (2 dakika bekleme olmaz). */
function ai_call_hedged(array $providers, array $messages, int $maxTokens = 1024, array $files = [], int $deadlineSec = 165, float $hedgeAfter = 15.0): array {
    $providers = array_values(array_filter($providers, function ($p) {
        return !ai_provider_in_cooldown((string)($p['provider'] ?? ''));
    }));
    if (!$providers) {
        $all = ai_active_providers();
        $providers = array_values(array_filter($all, function ($p) {
            return !ai_provider_in_cooldown((string)($p['provider'] ?? ''));
        }));
        if (!$providers) {
            /* Hepsi cooldown'da: son sans olarak cooldown'lari temizle. */
            foreach ($all as $pp) @unlink(ai_provider_cooldown_file((string)($pp['provider'] ?? '')));
            $providers = $all;
        }
    }
    $start = microtime(true);
    $mh = curl_multi_init();
    $jobs = [];
    $launched = 0;
    $last = ['ok' => false, 'msg' => 'provider yok'];

    $launch = function (int $idx) use (&$jobs, &$launched, $providers, $messages, $maxTokens, $files, $mh, $deadlineSec, $start) {
        if ($idx >= count($providers)) return;
        $p = $providers[$idx];
        $req = ai_req_build($p, $messages, $maxTokens, $files);
        if (isset($req['err'])) { $jobs[$idx] = ['err' => $req['err'], 'ch' => null]; $launched = max($launched, $idx + 1); return; }
        $remain = max(10, (int)($deadlineSec - (microtime(true) - $start)));
        $ch = curl_init($req['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $req['headers'],
            CURLOPT_POSTFIELDS => $req['body'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $remain,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        curl_multi_add_handle($mh, $ch);
        $jobs[$idx] = ['ch' => $ch, 'p' => $p, 't0' => microtime(true)];
        $launched = max($launched, $idx + 1);
    };

    if (!$providers) { curl_multi_close($mh); return $last; }
    $launch(0);
    $hedgeFired = false;

    while ($jobs) {
        $elapsed = microtime(true) - $start;
        if ($elapsed > $deadlineSec) break;
        /* Birincil yanit gelmediyse yedek saglayiciyi paralel baslat */
        if (!$hedgeFired && $launched < count($providers) && $elapsed >= $hedgeAfter && $launched === 1) {
            $hedgeFired = true;
            $launch(1);
        }
        curl_multi_exec($mh, $running);
        if ($running > 0) {
            curl_multi_select($mh, 0.4);
            curl_multi_exec($mh, $running);
        }
        while ($info = curl_multi_info_read($mh)) {
            $ch = $info['handle'];
            $idx = null; $job = null;
            foreach ($jobs as $i => $j) { if (($j['ch'] ?? null) === $ch) { $idx = $i; $job = $j; break; } }
            if ($idx === null) { curl_multi_remove_handle($mh, $ch); curl_close($ch); continue; }
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $body = (string)curl_multi_getcontent($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            unset($jobs[$idx]);
            $res = ai_req_parse($job['p'] ?? [], $code, $body);
            $res['provider'] = $job['p'] ?? [];
            $res['ms'] = (int)round((microtime(true) - ($job['t0'] ?? $start)) * 1000);
            if (!empty($res['ok'])) {
                foreach ($jobs as $j2) {
                    if (!empty($j2['ch'])) { curl_multi_remove_handle($mh, $j2['ch']); curl_close($j2['ch']); }
                }
                $jobs = [];
                curl_multi_close($mh);
                @unlink(ai_provider_cooldown_file((string)($job['p']['provider'] ?? '')));
                return $res;
            }
            if (!empty($res['rate'])) {
                $pn = (string)($job['p']['provider'] ?? '');
                /* 429: ayni saglayiciya hemen tekrar vurmayalim (patlama ->
                   ikinci saglayici da 429 -> tur tamamen duser). */
                if ($pn === 'dahl') ai_provider_trip('dahl', 60);
                else ai_provider_trip($pn, 20);
            }
            $last = $res;
            /* Bir saglayici basarisiz olduysa siradakini hemen baslat */
            if ($launched < count($providers)) $launch($launched);
        }
        if ($jobs && $running === 0) break;
    }
    foreach ($jobs as $j2) {
        if (!empty($j2['ch'])) { curl_multi_remove_handle($mh, $j2['ch']); curl_close($j2['ch']); }
    }
    curl_multi_close($mh);
    return $last;
}

/* dahl ucretsiz katmanda: "model_concurrency / Paid accounts are admitted first"
   -> kapasite doluysa 429. Her cagrida 30-50 s pesinde kosmak yerine kisa bir
   devre kesici konur; sure boyunca dahl atlanip yedek saglayici kullanilir. */
function ai_provider_cooldown_file(string $prov): string {
    return rtrim(sys_get_temp_dir(), '/') . '/vx_pcool_' . preg_replace('/[^a-z0-9]/i', '', $prov);
}
function ai_provider_in_cooldown(string $prov): bool {
    $f = ai_provider_cooldown_file($prov);
    $until = (int)@file_get_contents($f);
    return $until > time();
}
function ai_provider_trip(string $prov, int $sec = 60): void {
    @file_put_contents(ai_provider_cooldown_file($prov), (string)(time() + $sec));
}

function ai_call_any(array $providers, array $messages, int $maxTokens = 1024, array $files = [], int $deadlineSec = 165): array {
    $start = microtime(true);
    $last = ['ok' => false, 'msg' => 'provider yok'];
    foreach ($providers as $p) {
        $remaining = $deadlineSec - (microtime(true) - $start);
        if ($remaining < 10) break;
        if (ai_provider_in_cooldown((string)($p['provider'] ?? ''))) continue;
        for ($try = 0; $try < 2; $try++) {
            $remaining = $deadlineSec - (microtime(true) - $start);
            if ($remaining < 10) break;
            $r = ai_call_provider($p, $messages, $maxTokens, $files, (int)min(90, $remaining));
            $r['provider'] = $p;
            if (!empty($r['ok'])) { @unlink(ai_provider_cooldown_file((string)($p['provider'] ?? ''))); return $r; }
            $last = $r;
            if (!empty($r['rate'])) {
                $provName = (string)($p['provider'] ?? '');
                if ($provName === 'dahl') {
                    /* Kapasite 429'u ~120 ms'de donuyor: beklemek anlamsiz,
                       devre kes ve yedek saglayiciya gec. */
                    ai_provider_trip('dahl', 60);
                    break;
                }
                if ($try === 0) {
                    $remaining = $deadlineSec - (microtime(true) - $start);
                    if ($remaining < 22) break;
                    sleep(3);
                    continue;
                }
            }
            break;
        }
    }
    return $last;
}

<?php
defined('NEXUS_BOOT') or define('NEXUS_BOOT', true);

function report_setting(\PDO $pdo, string $key, string $default = ''): string {
    try {
        $st = $pdo->prepare("SELECT `value` FROM settings WHERE `key`=? LIMIT 1");
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return ($v === false || $v === null) ? $default : (string)$v;
    } catch (\Throwable $e) {
        return $default;
    }
}

function report_setting_set(\PDO $pdo, string $key, string $value): void {
    try {
        $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")
            ->execute([$key, $value]);
    } catch (\Throwable $e) {}
}

function report_reasons(): array {
    return [
        'scam'     => ['tr' => 'Dolandırıcılık / Scam', 'en' => 'Scam / Fraud'],
        'gambling' => ['tr' => 'Kumar / Gambling', 'en' => 'Gambling'],
        'physical' => ['tr' => 'Fiziksel ürün satışı', 'en' => 'Physical Products'],
        'copyright'=> ['tr' => 'Çalıntı içerik / Telif', 'en' => 'Copyright / Stolen Content'],
        'illegal'  => ['tr' => 'Yasa dışı içerik', 'en' => 'Illegal / Prohibited'],
        'other'    => ['tr' => 'Diğer', 'en' => 'Other'],
    ];
}

function report_reason_valid(string $reason): bool {
    return array_key_exists($reason, report_reasons());
}

function report_reason_label(string $reason, string $lang = 'tr'): string {
    $all = report_reasons();
    if (!isset($all[$reason])) return $reason;
    $l = $lang === 'en' ? 'en' : 'tr';
    return (string)($all[$reason][$l] ?? $all[$reason]['tr']);
}

function report_clef_prompt(): string {
    return 'Bu bir dijital mağaza şikayet raporudur. Tek soru: bu rapor SPAM veya RAPOR SİSTEMİNİN KÖTÜYE KULLANIMI mı? '
        . 'SPAM/KÖTÜYE KULLANIM: boş veya anlamsız metin, rastgele karakterler, reklam/link, küfür ve hakaret yağmuru, '
        . 'raporla ilgisiz içerik, sistem testi, aynı metnin tekrarı, mağazayla hiçbir ilgisi olmayan mesaj. '
        . 'GERÇEK RAPOR: belirli bir mağaza, ürün veya davranışı tarif eden somut bir ihlal iddiası '
        . '(dolandırıcılık, kumar, fiziksel ürün satışı, telif ihlali, yasa dışı içerik). '
        . 'Şikayet kısa olsa bile somut bir ihlal söylüyorsa gerçektir; sadece uzunluk veya dil onu spam yapmaz.';
}

function report_spam_score(\PDO $pdo, string $state): ?float {
    if (!function_exists('evren_decide')) {
        $evFile = __DIR__ . '/evren.php';
        if (is_file($evFile)) require_once $evFile;
    }
    if (!function_exists('evren_decide')) return null;
    $model = trim(report_setting($pdo, 'report_clef_model', 'clef'));
    if ($model === '') $model = 'clef';
    $instr = trim(report_setting($pdo, 'report_clef_prompt', ''));
    if ($instr === '') $instr = report_clef_prompt();
    $r = evren_decide($model, $state, ['spam' => ['type' => 'noul', 'instructions' => $instr]], $pdo);
    if (empty($r['ok'])) {
        error_log('[VX] report spam evren: ' . (string)($r['msg'] ?? 'hata'));
        return null;
    }
    $p = function_exists('evren_noul') ? evren_noul((array)($r['answers'] ?? []), 'spam') : null;
    if ($p === null) return null;
    return max(0.0, min(1.0, (float)$p));
}

function report_spam_heuristic(array $row): ?array {
    $msg = trim((string)($row['message'] ?? ''));
    $plain = trim(preg_replace('/\s+/u', ' ', $msg));
    $reason = (string)($row['reason'] ?? '');
    if ($reason === 'other' && mb_strlen($plain) < 12) {
        return ['spam' => 1, 'score' => 95, 'reason' => 'kisa/anlamsiz metin'];
    }
    if ($plain !== '' && mb_strlen($plain) < 6 && $reason !== 'other') {
        return null;
    }
    $letters = preg_replace('/[^\p{L}]/u', '', $plain);
    if (mb_strlen($letters) >= 24) {
        $upper = preg_replace('/[^\p{Lu}]/u', '', $plain);
        if (mb_strlen($upper) / max(1, mb_strlen($letters)) > 0.85) {
            return ['spam' => 1, 'score' => 90, 'reason' => 'tamami buyuk harf'];
        }
    }
    if (preg_match_all('~https?://~i', $plain) >= 3) {
        return ['spam' => 1, 'score' => 85, 'reason' => 'cok fazla baglanti'];
    }
    if ($plain !== '' && preg_match('/(.)\1{9,}/u', $plain)) {
        return ['spam' => 1, 'score' => 85, 'reason' => 'tekrarlanan karakter'];
    }
    return null;
}

function report_spam_scan(\PDO $pdo, int $id): array {
    if ($id <= 0) return ['ok' => false, 'msg' => 'bad id'];
    if (report_setting($pdo, 'report_clef_enabled', '1') !== '1') return ['ok' => false, 'msg' => 'clef kapali'];
    $st = $pdo->prepare("SELECT id, store_name, store_category, domain, reason, message, lang, status FROM store_reports WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) return ['ok' => false, 'msg' => 'rapor yok'];

    $heu = report_spam_heuristic($row);
    if ($heu !== null) {
        $pdo->prepare("UPDATE store_reports SET spam=1, spam_checked=1, spam_score=?, spam_reason=?, status='spam' WHERE id=?")
            ->execute([(int)$heu['score'], mb_substr((string)$heu['reason'], 0, 190), $id]);
        return ['ok' => true, 'spam' => 1, 'score' => (int)$heu['score'], 'by' => 'rule'];
    }

    $state = 'Magaza: ' . (string)$row['store_name'] . "\nTur: " . (string)$row['store_category']
        . "\nDomain: " . (string)$row['domain'] . "\nSebep: " . report_reason_label((string)$row['reason'], 'tr')
        . "\nDil: " . (string)$row['lang'] . "\nMesaj: " . mb_substr((string)$row['message'], 0, 4000);
    $prob = report_spam_score($pdo, $state);
    if ($prob === null) {
        $pdo->prepare("UPDATE store_reports SET spam_checked=0 WHERE id=?")->execute([$id]);
        return ['ok' => false, 'msg' => 'clef yanit vermedi'];
    }
    $thr = (float)report_setting($pdo, 'report_spam_threshold', '0.7');
    if ($thr <= 0 || $thr > 1) $thr = 0.7;
    $isSpam = $prob >= $thr ? 1 : 0;
    $score = (int)round($prob * 100);
    $pdo->prepare("UPDATE store_reports SET spam=?, spam_checked=1, spam_score=?, spam_reason=?, status=? WHERE id=?")
        ->execute([$isSpam, $score, 'clef ' . $score . '%', $isSpam ? 'spam' : 'new', $id]);
    return ['ok' => true, 'spam' => $isSpam, 'score' => $score, 'by' => 'evren'];
}

function report_scan_pending(\PDO $pdo, int $limit = 5): int {
    $limit = max(1, min(25, $limit));
    try {
        $st = $pdo->prepare("SELECT id FROM store_reports WHERE spam_checked=0 AND status='new' ORDER BY id ASC LIMIT " . $limit);
        $st->execute();
        $ids = $st->fetchAll(\PDO::FETCH_COLUMN);
    } catch (\Throwable $e) {
        return 0;
    }
    $n = 0;
    foreach ((array)$ids as $id) {
        try {
            $r = report_spam_scan($pdo, (int)$id);
            if (!empty($r['ok'])) $n++;
        } catch (\Throwable $e) {}
    }
    return $n;
}

function report_fp_key(): string {
    static $k = null;
    if ($k !== null) return $k;
    $out = '';
    if (function_exists('multi_secret')) {
        try { $out = (string)multi_secret(); } catch (\Throwable $e) {}
    }
    if ($out === '' && function_exists('_vx_k')) $out = trim((string)_vx_k());
    if ($out === '') $out = report_pow_key();
    $k = $out;
    return $k;
}

function report_fp_hash(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') return '';
    $raw = mb_substr($raw, 0, 512);
    return substr(hash_hmac('sha256', 'rptfp|' . $raw, report_fp_key()), 0, 32);
}

function report_msg_min(\PDO $pdo): int {
    $n = (int)report_setting($pdo, 'report_msg_min', '10');
    if ($n < 3) $n = 3;
    if ($n > 500) $n = 500;
    return $n;
}

function report_server_fp(string $ip): string {
    $h = function (string $k): string {
        $v = (string)($_SERVER[$k] ?? '');
        return mb_strtolower(mb_substr(trim($v), 0, 200));
    };
    $ua = $h('HTTP_USER_AGENT');
    if ($ua === '') return '';
    $ua = (string)preg_replace('~\s+~', ' ', $ua);
    $parts = [
        'ua'  => $ua,
        'al'  => $h('HTTP_ACCEPT_LANGUAGE'),
        'cu'  => $h('HTTP_SEC_CH_UA'),
        'cp'  => $h('HTTP_SEC_CH_UA_PLATFORM'),
        'cm'  => $h('HTTP_SEC_CH_UA_MOBILE'),
        'uaf' => $h('HTTP_SEC_CH_UA_FULL_VERSION_LIST'),
    ];
    $net = '';
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $oct = explode('.', $ip);
        $net = ($oct[0] ?? '') . '.' . ($oct[1] ?? '') . '.' . ($oct[2] ?? '');
    } elseif (strpos($ip, ':') !== false) {
        $net = implode(':', array_slice(explode(':', $ip), 0, 4));
    }
    $raw = '';
    foreach ($parts as $k => $v) $raw .= $k . '=' . $v . "\n";
    $raw .= 'net=' . $net;
    return substr(hash_hmac('sha256', 'rptsrv|' . $raw, report_fp_key()), 0, 32);
}

function report_pow_bits(\PDO $pdo): int {
    $b = (int)report_setting($pdo, 'report_pow_bits', '14');
    if ($b < 10) $b = 10;
    if ($b > 20) $b = 20;
    return $b;
}

function report_pow_key(): string {
    if (function_exists('_vx_k')) {
        $k = trim((string)_vx_k());
        if ($k !== '') return $k;
    }
    return hash('sha256', 'vexaris-report-pow|' . DB_NAME);
}

function report_pow_issue(\PDO $pdo): array {
    $bits = report_pow_bits($pdo);
    $salt = bin2hex(random_bytes(16));
    $ts = time();
    return [
        'bits' => $bits,
        'salt' => $salt,
        'ts'   => $ts,
        'sig'  => hash_hmac('sha256', 'rptpow|' . $salt . '|' . $ts . '|' . $bits, report_pow_key()),
    ];
}

function report_pow_verify(array $in): bool {
    $salt  = strtolower(trim((string)($in['pow_salt'] ?? '')));
    $ts    = (int)($in['pow_ts'] ?? 0);
    $bits  = (int)($in['pow_bits'] ?? 0);
    $sig   = trim((string)($in['pow_sig'] ?? ''));
    $nonce = trim((string)($in['pow_nonce'] ?? ''));
    if (!preg_match('/^[a-f0-9]{32}$/', $salt)) return false;
    if (!preg_match('/^[0-9]{1,18}$/', $nonce)) return false;
    if ($ts <= 0 || abs(time() - $ts) > 900) return false;
    if ($bits < 10 || $bits > 20) return false;
    $expect = hash_hmac('sha256', 'rptpow|' . $salt . '|' . $ts . '|' . $bits, report_pow_key());
    if (!hash_equals($expect, $sig)) return false;
    $raw = hash('sha256', $salt . ':' . $nonce, true);
    $rem = $bits;
    for ($i = 0; $rem > 0; $i++) {
        $byte = ord($raw[$i]);
        if ($rem >= 8) {
            if ($byte !== 0) return false;
            $rem -= 8;
        } else {
            if (($byte >> (8 - $rem)) !== 0) return false;
            $rem = 0;
        }
    }
    return true;
}

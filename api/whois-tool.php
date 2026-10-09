<?php
// WHOIS/RDAP sorgu API: herkese açık, Turnstile + 15/dk IP limiti korumalı.
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/turnstile.php';
require_once __DIR__ . '/../core/trabis.php';
boot_session();
header('Content-Type: application/json; charset=utf-8');

$pdo = db();
if (!$pdo) { http_response_code(503); echo json_encode(['ok' => false, 'msg' => 'Veritabanı bağlantısı kurulamadı.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false, 'msg' => 'Yalnızca POST.']); exit; }

$ip = client_ip();
try {
    $pdo->prepare("DELETE FROM api_rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)")->execute();
    $c = $pdo->prepare("SELECT COUNT(*) FROM api_rate_limits WHERE ip = ? AND scope = 'whois' AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
    $c->execute([$ip !== '' ? $ip : '0.0.0.0']);
    if ((int)$c->fetchColumn() >= 15) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'msg' => 'Dakikada en fazla 15 sorgu yapabilirsiniz.']);
        exit;
    }
    $pdo->prepare("INSERT INTO api_rate_limits (ip, scope) VALUES (?,'whois')")->execute([$ip !== '' ? $ip : '0.0.0.0']);
} catch (Throwable $e) {}

if (turnstile_enabled() && !turnstile_verify($_POST['cf-turnstile-response'] ?? $_POST['cf_token'] ?? '', $ip !== '' ? $ip : null)) {
    echo json_encode(['ok' => false, 'msg' => 'Güvenlik doğrulaması başarısız.']);
    exit;
}

$q = strtolower(trim((string)($_POST['q'] ?? '')));
$type = '';
if (filter_var($q, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) $type = 'ipv4';
elseif (filter_var($q, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) $type = 'ipv6';
elseif (preg_match('/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/', $q) && strlen($q) <= 253) $type = 'domain';
if ($type === '') { echo json_encode(['ok' => false, 'msg' => 'Geçerli bir domain veya IP girin.']); exit; }

function rdap_bootstrap(string $kind): ?array {
    // kind: dns | ipv4 | ipv6 — 24 saatlik dosya önbelleği
    $cache = sys_get_temp_dir() . '/vx_rdap_' . $kind . '.json';
    $mt = @filemtime($cache);
    if ($mt !== false && (time() - $mt) < 86400) {
        $j = json_decode((string)@file_get_contents($cache), true);
        if (is_array($j)) return $j;
    }
    $url = 'https://data.iana.org/rdap/' . $kind . '.json';
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 8, 'header' => "User-Agent: Vexaris-WHOIS/1.0\r\nAccept: application/json\r\n"]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false || strlen($raw) > 200000) return null;
    $j = json_decode($raw, true);
    if (!is_array($j)) return null;
    @file_put_contents($cache, $raw);
    return $j;
}

function rdap_allowed_host(string $host): bool {
    // SSRF kalkanı: yalnızca IANA listesindeki RDAP hostları
    static $allowed = null;
    if ($allowed === null) {
        $allowed = [];
        foreach (['dns', 'ipv4', 'ipv6'] as $k) {
            $b = rdap_bootstrap($k);
            if (!is_array($b)) continue;
            foreach ($b['services'] ?? [] as $svc) {
                $urlList = (isset($svc[1]) && is_array($svc[1])) ? $svc[1] : [];
                foreach ($urlList as $u) {
                    if (!is_string($u)) continue;
                    $h = parse_url($u, PHP_URL_HOST);
                    if ($h) $allowed[strtolower($h)] = true;
                }
            }
        }
    }
    return isset($allowed[strtolower($host)]);
}

function rdap_get(string $url): ?array {
    $p = parse_url($url);
    if (!is_array($p) || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || !rdap_allowed_host($p['host'])) return null;
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 8, 'follow_location' => 0, 'header' => "User-Agent: Vexaris-WHOIS/1.0\r\nAccept: application/rdap+json, application/json\r\n"]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false || strlen($raw) > 500000) return null;
    $j = json_decode($raw, true);
    return is_array($j) ? $j : null;
}

function rdap_find_server(array $boot, string $key): ?string {
    // En uzun sonek eşleşmesi (örn. co.uk > uk)
    $best = null; $bestLen = -1;
    foreach ($boot['services'] ?? [] as $svc) {
        $zones = (isset($svc[0]) && is_array($svc[0])) ? $svc[0] : [];
        $urlList = (isset($svc[1]) && is_array($svc[1])) ? $svc[1] : [];
        $firstUrl = '';
        foreach ($urlList as $u) { if (is_string($u) && $u !== '') { $firstUrl = $u; break; } }
        if ($firstUrl === '') continue;
        foreach ($zones as $z) {
            $z = strtolower((string)$z);
            if ($key === $z || substr($key, -strlen($z) - 1) === '.' . $z) {
                if (strlen($z) > $bestLen) { $bestLen = strlen($z); $best = rtrim($firstUrl, '/') . '/'; }
            }
        }
    }
    return $best;
}

function ip_in_cidr(string $ip, string $cidr): bool {
    if (strpos($cidr, '/') === false) return false;
    [$net, $bits] = explode('/', $cidr, 2);
    $bits = (int)$bits;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($net, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        if ($bits < 0 || $bits > 32) return false;
        return (ip2long($ip) & ~((1 << (32 - $bits)) - 1)) === (ip2long($net) & ~((1 << (32 - $bits)) - 1));
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && filter_var($net, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $a = inet_pton($ip); $b = inet_pton($net);
        if ($a === false || $b === false || $bits < 0 || $bits > 128) return false;
        $full = intdiv($bits, 8); $rem = $bits % 8;
        if (substr($a, 0, $full) !== substr($b, 0, $full)) return false;
        if ($rem > 0) {
            $mask = (0xFF << (8 - $rem)) & 0xFF;
            if ((ord($a[$full]) & $mask) !== (ord($b[$full]) & $mask)) return false;
        }
        return true;
    }
    return false;
}

try {
    if ($type === 'domain') {
        // .tr ailesi (tr, com.tr, net.tr, org.tr...) TRABIS port43 üzerinden
        $lowerQ = strtolower($q);
        if ($lowerQ === 'tr' || substr($lowerQ, -3) === '.tr') {
            $raw = trabis_query($q);
            if ($raw === null) { echo json_encode(['ok' => false, 'msg' => 'TRABIS sorgusu başarısız.']); exit; }
            $data = trabis_parse($raw, $q);
            if ($data === null) {
                echo json_encode(['ok' => true, 'type' => 'domain', 'available' => true, 'query' => $q]);
                exit;
            }
            echo json_encode(['ok' => true, 'type' => 'domain', 'available' => false, 'query' => $q, 'data' => $data]);
            exit;
        }
        $boot = rdap_bootstrap('dns');
        if (!$boot) { echo json_encode(['ok' => false, 'msg' => 'RDAP dizini alınamadı.']); exit; }
        $tld = strtolower(substr(strrchr($q, '.'), 1));
        $srv = rdap_find_server($boot, $tld);
        if (!$srv) {
            // IANA listesinde olmayan ama bilinen RDAP uçları
            $fallback = [
                'me' => 'https://rdap.identitydigital.services/rdap/',
                'sh' => 'https://rdap.identitydigital.services/rdap/',
                'ai' => 'https://rdap.identitydigital.services/rdap/',
            ];
            $srv = $fallback[$tld] ?? null;
        }
        if (!$srv) { echo json_encode(['ok' => false, 'msg' => 'Bu uzantı için RDAP sunucusu bulunamadı.']); exit; }
        $data = rdap_get($srv . 'domain/' . rawurlencode($q));
        if (!$data) {
            // Kayıtsız domainlerde çoğu registry 404 döner
            echo json_encode(['ok' => true, 'type' => 'domain', 'available' => true, 'query' => $q]);
            exit;
        }
        echo json_encode(['ok' => true, 'type' => 'domain', 'available' => false, 'query' => $q, 'data' => $data]);
        exit;
    } else {
        $kind = $type === 'ipv4' ? 'ipv4' : 'ipv6';
        $boot = rdap_bootstrap($kind);
        if (!$boot) { echo json_encode(['ok' => false, 'msg' => 'RDAP dizini alınamadı.']); exit; }
        $srv = null;
        foreach ($boot['services'] ?? [] as $svc) {
            $zones = (isset($svc[0]) && is_array($svc[0])) ? $svc[0] : [];
            $urlList = (isset($svc[1]) && is_array($svc[1])) ? $svc[1] : [];
            $firstUrl = '';
            foreach ($urlList as $u) { if (is_string($u) && $u !== '') { $firstUrl = $u; break; } }
            if ($firstUrl === '') continue;
            foreach ($zones as $cidr) {
                if (ip_in_cidr($q, (string)$cidr)) { $srv = rtrim($firstUrl, '/') . '/'; break 2; }
            }
        }
        if (!$srv) { echo json_encode(['ok' => false, 'msg' => 'Bu IP aralığı için RDAP sunucusu bulunamadı.']); exit; }
        $data = rdap_get($srv . 'ip/' . rawurlencode($q));
        if (!$data) { echo json_encode(['ok' => false, 'msg' => 'IP bilgisi alınamadı.']); exit; }
        echo json_encode(['ok' => true, 'type' => 'ip', 'available' => false, 'query' => $q, 'data' => $data]);
        exit;
    }
} catch (Throwable $e) {
    error_log('whois-tool: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Sorgu başarısız.']);
}

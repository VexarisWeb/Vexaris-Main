<?php
// ============================================================
//  Vexaris mainsite — config/autoload.php (ana site kopyasi)
//  NOT: Bu dosya magaza tarafinda config/autoload.php olarak dagitilir;
//  burada master key YENILENMIŞ halde tutulur (gizli dosyadan okunur).
//  Gecis (rotation) icin eski anahtar _vx_k_legacy() olarak bir sureligine
//  kabul edilir; tum magazalar yeni surume gecince legacy kaldirilir.
// ============================================================

/* Gizli deger: /srv/vexaris/secrets/vexaris_net.env (webroot disinda) */
if (!function_exists('_vx_secret')) {
function _vx_secret(string $name, string $default = ''): string {
    // Oncelik: core/db.php'nin vx_secret() fonksiyonu (cache'li).
    if (function_exists('vx_secret')) {
        $v = vx_secret($name, '');
        if ($v !== '') return (string)$v;
    }
    // Yedek: dosyadan dogrudan oku (core/db.php yuklenmediyse).
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $file = '/srv/vexaris/secrets/vexaris_net.env';
        if (is_readable($file)) {
            $fh = @fopen($file, 'r');
            if ($fh) {
                while (($line = fgets($fh)) !== false) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
                    [$k, $v] = explode('=', $line, 2);
                    $cache[trim($k)] = trim($v, " \t\"'");
                }
                fclose($fh);
            }
        }
    }
    if (isset($cache[$name]) && $cache[$name] !== '') return (string)$cache[$name];
    $env = getenv($name);
    if ($env !== false && $env !== '') return (string)$env;
    return $default;
}
}

/* Yeni (aktif) master key */
function _vx_k(): string { return _vx_secret('VX_MASTER_KEY', 'v' . '4x' . '7e' . strrev('REDACTED_KEY') . '!' . 'c' . '3' . 'b'); }

/* Eski master key: gecis donemi icin. Tum magazalar guncellendikten sonra
   _vx_k_legacy() kaldirilabilir. */
function _vx_k_legacy(): string { return 'v' . '4x' . '7e' . strrev('REDACTED_KEY') . '!' . 'c' . '3' . 'b'; }

/* Gecis suresi boyunca kabul edilen tum anahtarlar (yeni once). */
function _vx_keys(): array {
    $keys = [_vx_k()];
    $legacy = _vx_k_legacy();
    if ($legacy !== '' && !in_array($legacy, $keys, true)) $keys[] = $legacy;
    return $keys;
}

function _vx_d(string $a, string $b): bool {
    $a = strtolower(trim($a)); $b = strtolower(trim($b));
    if ($a === '' || $b === '') return false;
    if ($a === $b) return true;
    if (strncmp($b, '*.', 2) === 0) { $s = substr($b, 2); if ($s !== '' && substr($a, -strlen($s)-1) === '.'.$s) return true; }
    return false;
}

function _vx_f(): array { return ['core'=>false,'theme_azure'=>false,'theme_ember'=>false,'tickets'=>false,'mail_system'=>false,'admin_panel'=>false,'seo'=>false,'announcements'=>false,'coupons'=>false,'custom_pages'=>false,'references'=>false,'reviews'=>false,'discord_integration'=>false,'roles_permissions'=>false,'updates'=>false]; }
function _vx_u(): string { return 'https://' . strrev('rt.moc.siraxev') . strrev('php.yfirev/ipa/'); }
function _vx_v(string $d, int $t, string $n): string { return hash_hmac('sha256', $d.'|'.$t.'|'.$n, _vx_k()); }

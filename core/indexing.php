<?php
require_once __DIR__ . '/db.php';

function indexing_b64url(string $bin): string {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function indexing_settings(): array {
    $en = setting_get('google_indexing_enabled', '0') === '1';
    $raw = trim(setting_get('google_service_json', ''));
    $sa = null;
    if ($raw !== '') {
        $j = json_decode($raw, true);
        if (is_array($j) && !empty($j['client_email']) && !empty($j['private_key'])) $sa = $j;
    }
    return [$en, $sa];
}

function indexing_configured(): bool {
    [$en, $sa] = indexing_settings();
    return $en && $sa !== null;
}

function indexing_access_token(array $sa): ?string {
    if (!function_exists('openssl_sign')) return null;
    $now = time();
    $head = indexing_b64url((string)json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claim = indexing_b64url((string)json_encode([
        'iss' => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/indexing',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ]));
    $sig = '';
    try {
        $ok = @openssl_sign($head . '.' . $claim, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256);
    } catch (Throwable $e) {
        return null;
    }
    if (!$ok) return null;
    $opts = ['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $head . '.' . $claim . '.' . indexing_b64url($sig),
        ]),
        'timeout' => 10,
        'ignore_errors' => true,
    ]];
    $raw = @file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create($opts));
    if ($raw === false) return null;
    $j = json_decode($raw, true);
    return (is_array($j) && !empty($j['access_token'])) ? (string)$j['access_token'] : null;
}

function indexing_publish(string $url, string $type): array {
    if ($type !== 'URL_UPDATED' && $type !== 'URL_DELETED') $type = 'URL_UPDATED';
    [$en, $sa] = indexing_settings();
    if (!$en || $sa === null) return ['ok' => false, 'msg' => 'Indexing kapalı veya servis anahtarı eksik.'];
    $tok = indexing_access_token($sa);
    if (!$tok) return ['ok' => false, 'msg' => 'Google erişim jetonu alınamadı. Anahtarı ve Search Console yetkisini kontrol edin.'];
    $opts = ['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAuthorization: Bearer " . $tok . "\r\n",
        'content' => (string)json_encode(['url' => $url, 'type' => $type]),
        'timeout' => 10,
        'ignore_errors' => true,
    ]];
    $raw = @file_get_contents('https://indexing.googleapis.com/v3/urlNotifications:publish', false, stream_context_create($opts));
    if ($raw === false) return ['ok' => false, 'msg' => 'Google yanıt vermedi.'];
    $j = json_decode($raw, true);
    if (is_array($j) && isset($j['urlNotificationMetadata'])) return ['ok' => true, 'msg' => 'Google bilgilendirildi.'];
    $err = (is_array($j) && isset($j['error']['message'])) ? (string)$j['error']['message'] : 'Bilinmeyen yanıt.';
    return ['ok' => false, 'msg' => $err];
}

function indexing_site_base(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = strtolower(trim($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') $host = 'vexaris.net';
    return ($https ? 'https://' : 'http://') . $host;
}

function indexing_local_host(string $host): bool {
    $h = strtolower(trim($host));
    return $h === '' || $h === 'localhost' || str_starts_with($h, '127.') || $h === '::1'
        || str_ends_with($h, '.local') || str_ends_with($h, '.test') || str_ends_with($h, '.localhost');
}

function indexing_notify_slug(string $slug, string $type): array {
    $host = strtolower(trim($_SERVER['HTTP_HOST'] ?? ''));
    if (indexing_local_host($host)) return ['ok' => false, 'msg' => 'Yerel ortam, atlandı.'];
    return indexing_publish(indexing_site_base() . '/blog/' . ltrim($slug, '/'), $type);
}

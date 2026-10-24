<?php
require_once __DIR__ . '/db.php';

function indexnow_host(): string {
    return 'vexaris.net';
}

function indexnow_key(): string {
    $k = preg_replace('/[^0-9a-f]/', '', strtolower(trim((string)setting_get('indexnow_key', ''))));
    return strlen($k) === 32 ? $k : '';
}

function indexnow_key_url(): string {
    $k = indexnow_key();
    return $k === '' ? '' : 'https://' . indexnow_host() . '/' . $k . '.txt';
}

function indexnow_configured(): bool {
    return indexnow_key() !== '';
}

function indexnow_normalize(array $urls): array {
    $list = [];
    foreach ($urls as $u) {
        $u = trim((string)$u);
        if ($u !== '' && preg_match('#^https?://#i', $u)) $list[] = $u;
    }
    return array_values(array_unique($list));
}

function indexnow_submit(array $urls): array {
    $key = indexnow_key();
    if ($key === '') return ['ok' => false, 'msg' => 'IndexNow anahtari tanimli degil (settings.indexnow_key).'];
    $list = indexnow_normalize($urls);
    if ($list === []) return ['ok' => false, 'msg' => 'Gonderilecek gecerli URL yok.'];
    $body = (string)json_encode([
        'host' => indexnow_host(),
        'key' => $key,
        'keyLocation' => indexnow_key_url(),
        'urlList' => $list,
    ]);
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $body,
        'timeout' => 6,
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents('https://api.indexnow.org/indexnow', false, $ctx);
    $code = 0;
    foreach (($http_response_header ?? []) as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string)$h, $m)) $code = (int)$m[1];
    }
    if ($code === 200 || $code === 202) return ['ok' => true, 'msg' => 'IndexNow ' . $code . ' (' . count($list) . ' URL)'];
    $detail = $raw === false ? 'sunucu yanit vermedi' : ('HTTP ' . $code);
    return ['ok' => false, 'msg' => 'IndexNow basarisiz: ' . $detail];
}

function indexnow_notify_url(string $url): array {
    $h = strtolower((string)parse_url($url, PHP_URL_HOST));
    if ($h !== '' && $h !== indexnow_host() && $h !== 'www.' . indexnow_host()) {
        return ['ok' => false, 'msg' => 'Farkli host, atlandi.'];
    }
    return indexnow_submit([$url]);
}

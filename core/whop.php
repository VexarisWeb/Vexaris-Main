<?php
// ============================================================
//  Vexaris — Whop Ödeme Entegrasyonu (güncel API v1)
//  Akış: checkout_configuration oluştur (inline one-time plan)
//        → müşteri purchase_url'da öder
//        → payment.succeeded webhook'u siparişi tamamlar
//  Doküman: docs.whop.com (OAuth değil, Payments + Webhooks)
// ============================================================

require_once __DIR__ . '/db.php';

// Whop API tabanı (sandbox anahtarı ayrı taban kullanır).
function whop_api_base(): string {
    return setting_get('whop_sandbox', '0') === '1'
        ? 'https://sandbox-api.whop.com'
        : 'https://api.whop.com';
}

function whop_payments_enabled(): bool {
    return setting_get('whop_payments_enabled', '0') === '1'
        && trim(setting_get('whop_api_key', '')) !== '';
}

function whop_currency(): string {
    $c = strtolower(trim((string)setting_get('whop_currency', 'usd')));
    if (!preg_match('/^[a-z]{3}$/', $c)) $c = 'usd';
    return $c;
}

// Whop REST çağrısı (JSON). Dönüş: [http_kod, dizi|null]
function whop_api(string $method, string $path, array $body = []): array {
    $key = setting_get('whop_api_key', '');
    if ($key === '') return [0, null];
    $headers = [
        'Authorization: Bearer ' . $key,
        'Accept: application/json',
        'Api-Version-Date: 2026-07-01',
        'User-Agent: Vexaris-Whop/1.0',
    ];
    $opts = ['http' => [
        'method' => strtoupper($method),
        'header' => implode("\r\n", $headers) . "\r\n",
        'timeout' => 15,
        'ignore_errors' => true,
    ]];
    if (!empty($body)) {
        $opts['http']['header'] .= "Content-Type: application/json\r\n";
        $opts['http']['content'] = json_encode($body);
    }
    $raw = @file_get_contents(whop_api_base() . $path, false, stream_context_create($opts));
    $code = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string)$h, $m)) { $code = (int)$m[1]; break; }
        }
    }
    if ($raw === false) return [$code, null];
    $j = json_decode($raw, true);
    return [$code, is_array($j) ? $j : null];
}

// Tek seferlik ödeme için checkout linki üretir.
// Dönüş: ['url'=>..., 'checkout_id'=>..., 'plan_id'=>...] ya da ['error'=>...]
function whop_create_checkout(string $title, float $amount, string $currency, array $metadata, string $returnUrl): array {
    $company = trim(setting_get('whop_company_id', ''));
    $payload = [
        'plan' => [
            'title' => mb_substr($title, 0, 120, 'UTF-8'),
            'plan_type' => 'one_time',
            'initial_price' => round($amount, 2),
            'currency' => strtolower($currency),
        ],
        'metadata' => $metadata,
        'redirect_url' => $returnUrl,
    ];
    if ($company !== '') $payload['account_id'] = $company;
    [$code, $res] = whop_api('POST', '/api/v1/checkout_configurations', $payload);
    if ($code < 200 || $code >= 300 || !is_array($res)) {
        error_log('Whop checkout hatası (' . $code . '): ' . substr(json_encode($res), 0, 300));
        return ['error' => 'Ödeme sayfası oluşturulamadı. API anahtarını ve Company ID\'yi kontrol edin.'];
    }
    $url = $res['purchase_url'] ?? '';
    if ($url === '' && !empty($res['plan']['id'])) {
        $url = 'https://whop.com/checkout/' . $res['plan']['id'];
    }
    if ($url === '') return ['error' => 'Whop geçersiz yanıt döndü.'];
    return [
        'url' => $url,
        'checkout_id' => (string)($res['id'] ?? ''),
        'plan_id' => (string)($res['plan']['id'] ?? ''),
    ];
}

// SaaS plan fiyatlari (USD): [aylik_tutar, yillik_toplam, gun]
function whop_plan_offers(): array {
    return [
        'starter'      => ['label' => 'Starter',      'm' => 6.99,  'y' => 67.10],
        'professional' => ['label' => 'Professional', 'm' => 14.99, 'y' => 143.90],
        'enterprise'   => ['label' => 'Enterprise',   'm' => 39.99, 'y' => 383.90],
    ];
}

// Abonelik (renewal) checkout linki üretir: aylik 30 gün, yillik 365 gün.
// Dönüş: ['url'=>..., 'checkout_id'=>..., 'plan_id'=>...] ya da ['error'=>...]
function whop_create_plan_checkout(string $plan, string $cycle, array $metadata, string $returnUrl): array {
    $offers = whop_plan_offers();
    $plan = strtolower($plan);
    if (!isset($offers[$plan])) return ['error' => 'Geçersiz plan.'];
    $yearly = $cycle === 'yearly';
    $amount = $yearly ? $offers[$plan]['y'] : $offers[$plan]['m'];
    $period = $yearly ? 365 : 30;
    $company = trim(setting_get('whop_company_id', ''));
    $ptitle = 'Vexaris ' . $offers[$plan]['label'] . ' (' . ($yearly ? 'Yearly' : 'Monthly') . ')';
    $payload = [
        'plan' => [
            'title' => mb_substr($ptitle, 0, 120, 'UTF-8'),
            'plan_type' => 'renewal',
            'billing_period' => $period,
            'initial_price' => round($amount, 2),
            'renewal_price' => round($amount, 2),
            'currency' => whop_currency(),
            'product' => [
                'external_identifier' => 'vexaris-' . $plan . '-' . ($yearly ? 'yearly' : 'monthly'),
                'title' => mb_substr($ptitle, 0, 120, 'UTF-8'),
            ],
        ],
        'metadata' => $metadata,
        'redirect_url' => $returnUrl,
    ];
    if ($company !== '') $payload['account_id'] = $company;
    [$code, $res] = whop_api('POST', '/api/v1/checkout_configurations', $payload);
    if ($code < 200 || $code >= 300 || !is_array($res)) {
        error_log('Whop plan checkout hatasi (' . $code . '): ' . substr(json_encode($res), 0, 300));
        return ['error' => 'Ödeme sayfası oluşturulamadı. API anahtarını ve Company ID\'yi kontrol edin.'];
    }
    $url = $res['purchase_url'] ?? '';
    if ($url === '' && !empty($res['plan']['id'])) {
        $url = 'https://whop.com/checkout/' . $res['plan']['id'];
    }
    if ($url === '') return ['error' => 'Whop geçersiz yanıt döndü.'];
    return [
        'url' => $url,
        'checkout_id' => (string)($res['id'] ?? ''),
        'plan_id' => (string)($res['plan']['id'] ?? ''),
    ];
}

// Standard Webhooks imza doğrulaması.
// Başlıklar: webhook-id / webhook-timestamp / webhook-signature (v1,base64)
function whop_verify_webhook(string $payload, string $msgId, string $msgTs, string $msgSig): bool {
    $secret = trim(setting_get('whop_webhook_secret', ''));
    if ($secret === '' || $msgId === '' || $msgTs === '' || $msgSig === '') return false;
    if (abs(time() - (int)$msgTs) > 300) return false; // replay koruması (5 dk)
    // Anahtar adayları: whsec_/ws_ önekli ham + base64 çözülmüş hali.
    $cands = [$secret];
    $stripped = (string)preg_replace('/^(whsec_|ws_)/', '', $secret);
    $dec = base64_decode($stripped, true);
    if ($dec !== false && $dec !== '') $cands[] = $dec;
    if ($stripped !== $secret) $cands[] = $stripped;
    $cands = array_values(array_unique($cands));
    $signed = $msgId . '.' . $msgTs . '.' . $payload;
    foreach (explode(' ', $msgSig) as $entry) {
        $parts = explode(',', trim($entry), 2);
        if (count($parts) !== 2 || $parts[0] !== 'v1' || $parts[1] === '') continue;
        foreach ($cands as $k) {
            $expect = base64_encode(hash_hmac('sha256', $signed, $k, true));
            if (hash_equals($expect, trim($parts[1]))) return true;
        }
    }
    return false;
}

function whop_base_url(): string {
    /* Host basligina guvenilmez (odeme donus URL'si zehirlenebilir):
       sabit SITE_URL kullanilir. */
    $base = defined('SITE_URL') ? rtrim((string)SITE_URL, '/') : 'https://vexaris.net';
    return $base;
}

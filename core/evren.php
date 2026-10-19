<?php
/* EVREN LLM Çıkarım Servisi istemcisi (SSB - evren.ssyz.org.tr)
   OpenAI uyumlu chat + tipli karar (decision) uçları.
   Anahtar sırası: env EVREN_LLM_API_KEY -> /etc/vexaris/evren_llm.key -> settings (şifreli) */

function evren_base_url(): string {
    $u = trim((string)(getenv('EVREN_LLM_BASE_URL') ?: ''));
    if ($u === '') $u = 'https://evren-llmapi.ssyz.org.tr';
    return rtrim($u, '/');
}

function evren_api_key(?PDO $pdo = null): string {
    $k = trim((string)(getenv('EVREN_LLM_API_KEY') ?: ''));
    if ($k !== '') return $k;
    $f = '/etc/vexaris/evren_llm.key';
    if (is_readable($f)) {
        $v = trim((string)@file_get_contents($f));
        if ($v !== '') return $v;
    }
    $pdo = $pdo ?: (function_exists('db') ? db() : null);
    if ($pdo) {
        try {
            $st = $pdo->prepare("SELECT `value` FROM settings WHERE `key`='evren_llm_api_key' LIMIT 1");
            $st->execute();
            $v = trim((string)($st->fetchColumn() ?: ''));
            if ($v !== '') {
                if (function_exists('ai_decrypt')) {
                    $d = ai_decrypt($v);
                    if ($d !== '') return $d;
                }
                return $v;
            }
        } catch (Throwable $e) {}
    }
    return '';
}

function evren_is_ready(?PDO $pdo = null): bool {
    return evren_api_key($pdo) !== '';
}

function evren_http(string $method, string $path, array $payload = null, ?PDO $pdo = null): array {
    $key = evren_api_key($pdo);
    if ($key === '') return ['ok' => false, 'code' => 0, 'msg' => 'EVREN anahtari yok', 'json' => null];
    $url = evren_base_url() . $path;
    $ch = curl_init($url);
    $headers = ['Authorization: Bearer ' . $key, 'Accept: application/json'];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'Vexaris/1.0 (mainsite)',
    ];
    if ($method === 'GET') {
        $opts[CURLOPT_HTTPHEADER] = $headers;
    } else {
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload === null ? [] : $payload, JSON_UNESCAPED_UNICODE);
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }
    curl_setopt_array($ch, $opts);
    $body = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = (string)curl_error($ch);
    curl_close($ch);
    $json = null;
    if ($body !== '') {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) $json = $decoded;
    }
    if ($code < 200 || $code >= 300) {
        $msg = (string)($json['error']['message'] ?? ('http ' . $code));
        return ['ok' => false, 'code' => $code, 'msg' => mb_substr($msg, 0, 200), 'json' => $json];
    }
    return ['ok' => true, 'code' => $code, 'msg' => '', 'json' => is_array($json) ? $json : ['raw' => $body]];
}

/* Tipli karar: metin uretmez, sorulara olasilik doner (noul: evet olasiligi 0-1). */
function evren_decide(string $model, string $state, array $questions, ?PDO $pdo = null): array {
    $r = evren_http('POST', '/v1/systemone', ['model' => $model, 'state' => $state, 'questions' => $questions], $pdo);
    if (!$r['ok']) return ['ok' => false, 'msg' => $r['msg'], 'answers' => [], 'usage' => []];
    $answers = (array)($r['json']['answers'] ?? []);
    return [
        'ok' => true,
        'msg' => '',
        'answers' => $answers,
        'usage' => (array)($r['json']['usage'] ?? []),
        'id' => (string)($r['json']['id'] ?? ''),
    ];
}

function evren_noul(array $answers, string $question): ?float {
    $v = $answers[$question]['noul'] ?? null;
    if ($v === null) return null;
    if (!is_numeric($v)) return null;
    return (float)$v;
}

function evren_chat(string $model, array $messages, int $maxTokens = 1024, ?PDO $pdo = null): array {
    $r = evren_http('POST', '/v1/chat/completions', [
        'model' => $model,
        'messages' => $messages,
        'max_tokens' => (int)$maxTokens,
        'temperature' => 0.2,
    ], $pdo);
    if (!$r['ok']) return ['ok' => false, 'msg' => $r['msg'], 'text' => ''];
    $text = (string)($r['json']['choices'][0]['message']['content'] ?? '');
    return ['ok' => true, 'msg' => '', 'text' => $text, 'usage' => (array)($r['json']['usage'] ?? [])];
}

function evren_models(?PDO $pdo = null): array {
    $r = evren_http('GET', '/v1/models', null, $pdo);
    if (!$r['ok']) return [];
    $out = [];
    foreach ((array)($r['json']['data'] ?? []) as $m) {
        $out[] = [
            'id' => (string)($m['id'] ?? ''),
            'task' => (string)($m['task'] ?? 'chat'),
            'context' => (int)($m['context_length'] ?? 0),
        ];
    }
    return $out;
}

function evren_quota(?PDO $pdo = null): array {
    $r = evren_http('GET', '/v1/quota', null, $pdo);
    if (!$r['ok']) return ['ok' => false, 'msg' => $r['msg']];
    $j = $r['json'];
    return [
        'ok' => true,
        'daily_used' => (int)($j['daily_used_tokens'] ?? 0),
        'daily_limit' => (int)($j['daily_limit_tokens'] ?? 0),
        'daily_remaining' => (int)($j['daily_remaining_tokens'] ?? 0),
        'credits' => (float)($j['remaining_cr'] ?? 0),
        'level' => (string)($j['level'] ?? ''),
    ];
}

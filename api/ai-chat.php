<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/ai.php';
require_once __DIR__ . '/../config/autoload.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = db();
if (!$pdo) { http_response_code(503); echo json_encode(['ok' => false, 'msg' => 'unavailable']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false, 'msg' => 'POST only']); exit; }
ai_tables();

$logStart = microtime(true);
$reqLog = ['domain' => '', 'ip' => client_ip(), 'plan' => '', 'lang' => '', 'mode' => '', 'provider' => '', 'model_id' => '', 'prompt' => '', 'messages' => '', 'tools' => '', 'tool_calls' => '', 'reply' => '', 'in_t' => 0, 'out_t' => 0, 'credits' => 0, 'ms' => 0, 'ok' => 0, 'status' => 0, 'err' => ''];

try {
    if (!api_rate_limit($pdo, 'ai', client_ip(), 240, 60)) { http_response_code(429); echo json_encode(['ok' => false, 'msg' => 'rate']); exit; }
} catch (Throwable $e) {}

$domain = strtolower(trim((string)($_POST['domain'] ?? '')));
$timestamp = (int)($_POST['timestamp'] ?? 0);
$nonce = strtolower(trim((string)($_POST['nonce'] ?? '')));
$signature = (string)($_POST['signature'] ?? '');
$domain = preg_replace('/[^a-z0-9.\-:]/', '', $domain);
if ($domain === '' || strlen($domain) > 253 || $timestamp <= 0 || !preg_match('/^[a-f0-9]{16,64}$/', $nonce) || $signature === '') {
    http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'invalid request']); exit;
}
if (abs(time() - $timestamp) > 300) { http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'stale']); exit; }

/* İmza doğrulama: mağazaya özel sır (VX_RELAY_SECRET) varsa **yalnız onunla**
   doğrulanır (paylaşılan anahtar kabul edilmez → başka mağazanın kotası
   tüketilemez). Sırı olmayan eski/self-hosted mağazalar geriye uyumluluk için
   ortak anahtarla çalışmaya devam eder. */
$relayKeys = [];
$hasStoreSecret = false;
try {
    /* Mağaza sırrı slug üzerinden bulunur: hosted_stores.domain alanı eski
       (.vexaris.net) kalabiliyor, mağaza ise kanonik .vexaris.app domain'i ile
       imza atıyor. Sırrı domain'e göre aramak eşleşmeyi kaçırırdı. */
    $sec = '';
    $slugGuess = '';
    if (preg_match('/^([a-z0-9][a-z0-9\-]*)\./i', $domain, $mSlug)) $slugGuess = strtolower($mSlug[1]);
    if ($slugGuess !== '') {
        $stSec = $pdo->prepare("SELECT relay_secret FROM hosted_stores WHERE slug=? LIMIT 1");
        $stSec->execute([$slugGuess]);
        $sec = trim((string)($stSec->fetchColumn() ?: ''));
    }
    if ($sec === '') {
        $domLookup = $domain;
        $stSec = $pdo->prepare("SELECT relay_secret FROM hosted_stores WHERE domain=? LIMIT 1");
        $stSec->execute([$domLookup]);
        $sec = trim((string)($stSec->fetchColumn() ?: ''));
        if ($sec === '') {
            $alt = preg_replace('/^www\./', '', $domain);
            if ($alt !== $domain) {
                $stSec->execute([$alt]);
                $sec = trim((string)($stSec->fetchColumn() ?: ''));
            }
        }
    }
    if ($sec !== '') { $relayKeys[] = $sec; $hasStoreSecret = true; }
} catch (Throwable $e) {}
/* Barindirilan magazalar (.vexaris.app) yalniz kendi relay sirlariyla imzalayabilir;
   ortak/legacy anahtarla baska bir magazanin kotasi tuketilemez. */
if (!$hasStoreSecret && ($domain === 'vexaris.app' || preg_match('/\.vexaris\.app$/i', $domain))) {
    http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'store secret required']); exit;
}
if (!$hasStoreSecret) $relayKeys[] = _vx_k();
$sigOk = false;
$sigKeyUsed = '';
foreach ($relayKeys as $rk) {
    if (hash_equals(hash_hmac('sha256', $domain . '|' . $timestamp . '|' . $nonce, $rk), $signature)) { $sigOk = true; $sigKeyUsed = $rk; break; }
}
if (!$sigOk) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'bad signature']); exit; }

try {
    if (random_int(1, 50) === 1) {
        $pdo->exec("DELETE FROM license_nonces WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    }
    $n = $pdo->prepare("INSERT IGNORE INTO license_nonces (nonce) VALUES (?)");
    $n->execute([$nonce]);
    if ($n->rowCount() === 0) { http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'nonce used']); exit; }
} catch (Throwable $e) { http_response_code(500); echo json_encode(['ok' => false, 'msg' => 'server error']); exit; }
$domain = preg_replace('/^www\./', '', $domain);
$domainWww = 'www.' . $domain;
$reqLog['domain'] = $domain;
$logExit = function (int $status, string $err = '') use (&$reqLog, $pdo, $logStart): void {
    $reqLog['status'] = $status;
    $reqLog['err'] = $err;
    $reqLog['ms'] = (int)round((microtime(true) - $logStart) * 1000);
    ai_req_log($pdo, $reqLog);
};
try {
    if (!api_rate_limit($pdo, 'ai_t', 'ai:' . substr(hash('sha256', $domain), 0, 24), 30, 60)) {
        $logExit(429, 'rate'); http_response_code(429); echo json_encode(['ok' => false, 'msg' => 'rate']); exit;
    }
} catch (Throwable $e) {}

try {
    /* Lisans eşleşmesi: en SPESIFIK eşleşme kazanır (tam alan adı > wildcard).
       id-ASC ilk eşleşme, wildcard lisansların mağazanın kendi lisansını gölgelemesine
       ve yanlış plan/kota uygulanmasına yol açıyordu. */
    $rows = $pdo->query("SELECT allowed_domains, features FROM licenses WHERE status='active' AND (suspended_until IS NULL OR suspended_until <= NOW()) ORDER BY id ASC")->fetchAll();
    $licensed = false;
    $licPlan = '';
    $bestScore = -1;
    foreach ($rows as $row) {
        $list = json_decode((string)$row['allowed_domains'], true);
        if (!is_array($list)) continue;
        foreach ($list as $pattern) {
            $pattern = (string)$pattern;
            $exact = (strtolower($pattern) === $domain || strtolower($pattern) === $domainWww);
            $wild = (strncmp($pattern, '*.', 2) === 0);
            if (!$exact && !$wild) continue;
            if (!$exact && !(_vx_d($domain, $pattern) || _vx_d($domainWww, $pattern))) continue;
            $score = $exact ? 100 : 10;
            if ($score <= $bestScore) continue;
            $bestScore = $score;
            $licensed = true;
            $lf = json_decode((string)($row['features'] ?? ''), true);
            $licPlan = (is_array($lf) && isset($lf['plan'])) ? strtolower(trim((string)$lf['plan'])) : '';
        }
    }
} catch (Throwable $e) { $licensed = false; }
if (!$licensed) { $logExit(403, 'no license'); http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'no license']); exit; }

$messages = json_decode((string)($_POST['messages'] ?? '[]'), true);
if (!is_array($messages)) $messages = [];
$messages = array_slice($messages, 0, 20);
$clean = [];
foreach ($messages as $m) {
    if (!is_array($m)) continue;
    $role = ($m['role'] ?? 'user') === 'assistant' ? 'assistant' : (($m['role'] ?? '') === 'tool' ? 'tool' : 'user');
    $content = mb_substr(trim((string)($m['content'] ?? '')), 0, 4000);
    $tc = [];
    if ($role === 'tool' && isset($m['tool_calls']) && is_array($m['tool_calls'])) {
        foreach (array_slice($m['tool_calls'], 0, 8) as $t) {
            if (!is_array($t)) continue;
            $tc[] = ['id' => mb_substr((string)($t['id'] ?? ''), 0, 64), 'name' => mb_substr(trim((string)($t['name'] ?? '')), 0, 60), 'result' => mb_substr(trim((string)($t['result'] ?? '')), 0, 4000)];
        }
    }
    $row = ['role' => $role, 'content' => $content];
    if ($tc) $row['tool_calls'] = $tc;
    $clean[] = $row;
}
if (!$clean) { $logExit(400, 'empty'); http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'empty']); exit; }
$maxTokens = max(512, min(2048, (int)($_POST['max_tokens'] ?? 1024)));
$reqLang = strtolower(trim((string)($_POST['lang'] ?? '')));
if ($reqLang !== 'en') $reqLang = 'tr';
$writeMode = trim((string)($_POST['mode'] ?? '')) === 'write';
$reqLog['lang'] = $reqLang;
$reqLog['mode'] = $writeMode ? 'write' : 'chat';
$reqLog['messages'] = mb_substr((string)json_encode($clean, JSON_UNESCAPED_UNICODE), 0, 60000);
foreach ($clean as $cm) {
    if (($cm['role'] ?? '') === 'user') $reqLog['prompt'] = (string)($cm['content'] ?? '');
}

$aiWindows = ['starter' => [12, 8], 'professional' => [30, 6], 'enterprise' => [70, 4]];
if ($licPlan === '' || !isset($aiWindows[$licPlan])) $licPlan = 'free';
$reqLog['plan'] = $licPlan;
$wLim = $aiWindows[$licPlan][0] ?? 0;
$wH = $aiWindows[$licPlan][1] ?? 8;
/* free (ve plani bilinmeyen) lisansta AI yok: kota penceresi yerine acik
   bir plan hatasi doner, magaza "kota doldu" demez. */
if ($wLim <= 0) {
    $logExit(403, 'plan_ai');
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'plan_ai']);
    exit;
}
$wStart = (int)(floor(time() / ($wH * 3600)) * ($wH * 3600));
try {
    $wq = $pdo->prepare("SELECT COUNT(*) FROM ai_usage_log WHERE domain=? AND ok=1 AND created_at >= FROM_UNIXTIME(?)");
    $wq->execute([$domain, $wStart]);
    $wUsed = (int)$wq->fetchColumn();
} catch (Throwable $e) { $wUsed = 0; }
if ($wUsed >= $wLim) {
    $logExit(402, 'plan_quota');
    http_response_code(402);
    echo json_encode(['ok' => false, 'msg' => 'plan_quota', 'quota' => ['limit' => $wLim, 'used' => $wUsed, 'window_h' => $wH, 'reset' => $wStart + $wH * 3600]]);
    exit;
}

$quota = ai_credit_use($pdo, $domain);
if ($quota['left'] <= 0) { $logExit(402, 'quota'); http_response_code(402); echo json_encode(['ok' => false, 'msg' => 'quota', 'quota' => $quota]); exit; }

$providers = ai_active_providers($pdo);
if (!$providers) { $logExit(503, 'no provider'); http_response_code(503); echo json_encode(['ok' => false, 'msg' => 'no provider']); exit; }
$toolNames = 'store.stats(), sales.recent(limit), sales.list(limit), products.top(limit), products.list(q,limit), products.low_stock(limit), product.get(id), product.create(name,price,category,image_url,short_description,description), product.update(id,name,price,category,status), product.delete(id), users.stats(), users.list(q,limit), user.get(id), user.set_role(id,role), tickets.list(status,limit), tickets.open(limit), ticket.reply(id,message), ticket.close(id), coupons.list(limit), coupon.create(code,type,value,max_uses), coupon.toggle(id), audit.recent(limit,username), settings.get(), settings.set(key,value: site_name|footer_copyright|maintenance_mode), seo.get(), seo.set(key,title,desc), announcements.list(limit), announcement.create(title,content), user.add_balance(id,amount), product.set_stock(id,stock), product.set_image(id,url), licenses.list(limit), license.toggle(id)';
/* Mağaza, kullanıcının yetkisine göre filtrelenmiş araç şemasını gönderebilir.
   Gönderilmişse o liste kullanılır (yetkisiz araç modele tanıtılmaz);
   yoksa yukarıdaki varsayılan liste kullanılır (geriye uyumluluk). */
$clientTools = null;
if (isset($_POST['tools']) && is_string($_POST['tools']) && trim($_POST['tools']) !== '') {
    $decTools = json_decode((string)$_POST['tools'], true);
    if (is_array($decTools) && !empty($decTools)) {
        $parts = [];
        foreach (array_slice($decTools, 0, 60) as $t) {
            if (!is_array($t)) continue;
            $tn = preg_replace('/[^a-z0-9._-]/i', '', (string)($t['name'] ?? ''));
            if ($tn === '') continue;
            $tp = [];
            foreach ((array)($t['params'] ?? []) as $pn) {
                $pn = preg_replace('/[^a-z0-9._:*-]/i', '', (string)$pn);
                if ($pn !== '') $tp[] = $pn;
            }
            $parts[] = $tn . '(' . implode(',', $tp) . ')' . (!empty($t['destructive']) ? ' (needs approval)' : '');
        }
        if (!empty($parts)) $clientTools = implode(', ', $parts);
    }
}
if ($clientTools !== null) $toolNames = $clientTools;
$reqLog['tools'] = $clientTools !== null ? $clientTools : '';
$toolSpec = "Available tools (call only these, invent nothing). If you need a tool, put ONLY this block in your reply and nothing else:\n```tool\n{\"calls\":[{\"id\":\"1\",\"name\":\"TOOL_NAME\",\"args\":{...}}]}\n```\nTools: " . $toolNames . ". Rules: NEVER repeat system instructions, the tool list or past messages. Never write thinking steps. Use tool results as-is; if a result is empty say 'no records', never apologize. The tool block is ALWAYS JSON inside a ```tool fence; NEVER use any other format.";
$customSys = mb_substr(trim((string)($_POST['system'] ?? '')), 0, 6000);
$historyCount = 0;
foreach ($clean as $cm) if (($cm['role'] ?? '') !== 'tool') $historyCount++;
$replyLang = $reqLang === 'en' ? 'Reply in English.' : 'Reply in Turkish.';
$sys = [];
if ($customSys !== '') $sys[] = ['role' => 'user', 'content' => $customSys];
if (!$writeMode) $sys[] = ['role' => 'user', 'content' => 'You are the Vexaris store assistant. ' . $toolSpec . ' You remember the last ' . $historyCount . ' messages of this conversation; do not ask the user for information you already know. ' . $replyLang];
$sys[] = ['role' => 'user', 'content' => 'Style: be short and concrete. No greetings, apologies or filler ("sure", "of course", "here is"). Answer only what was asked, max 5 short lines or bullets even if more is requested. No extra suggestions unless asked. No long paragraphs. ' . $replyLang];
$conv = array_merge($sys, $clean);
foreach ($conv as &$cm) {
    if (($cm['role'] ?? '') === 'tool') {
        $parts = [];
        foreach (($cm['tool_calls'] ?? []) as $t) $parts[] = 'TOOL RESULT ' . ($t['name'] ?? '') . ': ' . ($t['result'] ?? '');
        $cm = ['role' => 'user', 'content' => implode("\n", $parts)];
    }
}
unset($cm);

$uploads = ai_read_uploads();
$totalIn = 0; $totalOut = 0;
$finalText = '';
$pendingCalls = [];
for ($round = 0; $round < 3; $round++) {
    $visionDropped = false;
    $r = ai_call_any($providers, $conv, $maxTokens, $uploads);
    if (empty($r['ok']) && $uploads) {
        foreach ($conv as &$cm) {
            if (($cm['role'] ?? '') === 'user') {
                $cm['content'] .= "\n[Note: the attached file could not be opened by this model; answer from the text only.]";
                break;
            }
        }
        unset($cm);
        $r2 = ai_call_any($providers, $conv, $maxTokens, []);
        if (!empty($r2['ok'])) { $r = $r2; $visionDropped = true; }
    }
    $provider = !empty($r['provider']) ? $r['provider'] : ($providers[0] ?? []);
    $reqLog['provider'] = (string)($provider['provider'] ?? '');
    $reqLog['model_id'] = (string)($provider['model_id'] ?? '');
    $totalIn += (int)($r['in'] ?? 0);
    $totalOut += (int)($r['out'] ?? 0);
    if ($totalIn === 0 && !empty($uploads)) $totalIn += 1000 * count($uploads);
    if (empty($r['ok'])) {
        ai_usage_log($pdo, $domain, (string)($provider['provider'] ?? ''), (string)($provider['model_id'] ?? ''), $totalIn, $totalOut, false, (string)($r['msg'] ?? ''));
        $reqLog['in_t'] = $totalIn;
        $reqLog['out_t'] = $totalOut;
        $logExit(502, (string)($r['msg'] ?? 'provider'));
        http_response_code(502);
        echo json_encode(['ok' => false, 'msg' => 'provider']);
        exit;
    }
    $rate = (float)($provider['credit_rate'] ?? 0);
    if ($totalIn === 0 && $totalOut === 0) {
        $totalIn = ai_estimate_tokens(json_encode($conv));
        $totalOut = ai_estimate_tokens((string)($r['text'] ?? ''));
    }
    $text = (string)($r['text'] ?? '');
    $calls = [];
    $found = null;
    if (preg_match('/```tool\s*(\{.*?\})\s*```/s', $text, $mm)) {
        $found = $mm[1];
    } elseif (preg_match('/\{\s*"calls"\s*:\s*\[(?:[^{}\[\]]|\{[^{}]*\}|\[[^\[\]]*\])*\]\s*\}/s', $text, $mm)) {
        $found = $mm[0];
    } elseif (preg_match('/<tool-call>\s*([a-z0-9_.]+)\s*((?:<arg_key>.*?<\/arg_key>\s*<arg-value>.*?<\/arg-value>\s*)*)<\/tool-call>/s', $text, $mm)) {
        $xargs = [];
        if (preg_match_all('/<arg_key>(.*?)<\/arg_key>\s*<arg-value>(.*?)<\/arg-value>/s', $mm[2], $am, PREG_SET_ORDER)) {
            foreach ($am as $pair) $xargs[trim($pair[1])] = trim($pair[2]);
        }
        $found = json_encode(['calls' => [['id' => '1', 'name' => trim($mm[1]), 'args' => $xargs]]]);
    }
    if ($found !== null) {
        $dec = json_decode($found, true);
        if (is_array($dec) && isset($dec['calls']) && is_array($dec['calls'])) {
            foreach (array_slice($dec['calls'], 0, 5) as $c) {
                if (!is_array($c) || empty($c['name'])) continue;
                $args = isset($c['args']) && is_array($c['args']) ? $c['args'] : [];
                $calls[] = ['id' => mb_substr((string)($c['id'] ?? uniqid()), 0, 64), 'name' => mb_substr(trim((string)$c['name']), 0, 60), 'args' => $args];
            }
        }
    }
    if (!$calls) { $finalText = $text; break; }
    $conv[] = ['role' => 'assistant', 'content' => ''];
    $pendingCalls = $calls;
    break;
}

$finalText = trim((string)preg_replace('/```(tool|think|thinking)[\s\S]*?```/s', '', $finalText));
$finalText = trim((string)preg_replace('/\{\s*"calls"\s*:\s*\[(?:[^{}\[\]]|\{[^{}]*\}|\[[^\[\]]*\])*\]\s*\}/s', '', $finalText));
$finalText = trim((string)preg_replace('/<tool-call>.*?<\/tool-call>/s', '', $finalText));
$lead = mb_substr($finalText, 0, 400);
if (preg_match('/thinking process|düşünme sürec|düşünüyorum|let me (think|analyze)/i', $lead)) {
    $cut = mb_strpos($finalText, "\n\n");
    if ($cut !== false && $cut < 800) $finalText = trim(mb_substr($finalText, $cut + 2));
}
$finalText = trim($finalText);
if ($finalText === '' && $pendingCalls) {
    $names = [];
    foreach ($pendingCalls as $pc) $names[] = '`' . trim((string)($pc['name'] ?? '')) . '`';
    $finalText = ($reqLang === 'en' ? 'Tool executed: ' : 'Araç çalıştırıldı: ') . implode(', ', $names);
}
$charged = ai_credit_cost($rate, $totalIn, $totalOut);
ai_quota_add($pdo, $domain, $totalIn, $totalOut);
ai_credit_add($pdo, $domain, $charged);
ai_usage_log($pdo, $domain, (string)($provider['provider'] ?? ''), (string)($provider['model_id'] ?? ''), $totalIn, $totalOut, true, '', $charged);
$quota = ai_credit_use($pdo, $domain);

$reqLog['in_t'] = $totalIn;
$reqLog['out_t'] = $totalOut;
$reqLog['credits'] = $charged;
$reqLog['reply'] = $finalText;
$reqLog['tool_calls'] = $pendingCalls ? mb_substr((string)json_encode($pendingCalls, JSON_UNESCAPED_UNICODE), 0, 8000) : '';
$reqLog['ok'] = 1;
$reqLog['status'] = 200;
$reqLog['ms'] = (int)round((microtime(true) - $logStart) * 1000);
ai_req_log($pdo, $reqLog);

$payload = ['ok' => true, 'text' => $finalText, 'tool_calls' => $pendingCalls, 'usage' => ['in' => $totalIn, 'out' => $totalOut], 'credits' => $charged, 'quota' => $quota, 'provider' => (string)($provider['provider'] ?? ''), 'model' => (string)($provider['model_id'] ?? ''), 'vision_dropped' => $visionDropped];
$ts = time();
/* Yanit, istegi DOGRULAYAN anahtarla imzalanir: magaza kendi sirriyla imzaladiysa
   yanit da o sırla doner (kiracı bazli), ortak anahtarla imzalayan magaza icin
   master/legacy doner. Boylece eski surumler de (tek imza bekler) calisir. */
$signKey = $sigKeyUsed !== '' ? $sigKeyUsed : _vx_k();
$sig = hash_hmac('sha256', json_encode($payload) . '|' . $ts, $signKey);
echo json_encode(array_merge($payload, ['timestamp' => $ts, 'signature' => $sig]));

<?php

define('VX_MAIL_AUTOLOAD', __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php');

function mail_get_settings(\PDO $pdo): array {
    $cfg = [];
    try {
        $st = $pdo->query("SELECT `key`,`value` FROM settings WHERE `key` LIKE 'mail\\_%'");
        while ($r = $st->fetch()) $cfg[$r['key']] = $r['value'];
    } catch (Exception $e) {}
    return $cfg;
}

function mail_is_configured(\PDO $pdo): bool {
    $cfg = mail_get_settings($pdo);
    return (($cfg['mail_enabled'] ?? '0') === '1')
        && trim((string)($cfg['mail_smtp_host'] ?? '')) !== ''
        && trim((string)($cfg['mail_smtp_user'] ?? '')) !== '';
}

function mail_render(string $template, array $vars): string {
    $map = [];
    foreach ($vars as $k => $v) {
        $map['{' . $k . '}'] = htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
    return strtr((string)$template, $map);
}

function mail_template_row(\PDO $pdo, string $key): string {
    try {
        $st = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = ? LIMIT 1");
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return $v === false ? '' : (string)$v;
    } catch (Exception $e) {
        return '';
    }
}

function mail_wrap_layout(\PDO $pdo, string $bodyHtml, array $vars): string {
    $siteName = '';
    try {
        $st = $pdo->prepare("SELECT `value` FROM settings WHERE `key`='site_name' LIMIT 1");
        $st->execute();
        $siteName = (string)$st->fetchColumn();
    } catch (Exception $e) {}
    if ($siteName === '') $siteName = 'Vexaris';
    $logo = 'https://vexaris.net/assets/images/vexaris-logo.png';
    $body   = mail_render($bodyHtml, $vars);
    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</title></head>'
         . '<body style="margin:0;padding:0;background-color:#0b0d10;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">'
         . '<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#0b0d10;padding:36px 12px;"><tr><td align="center">'
         . '<table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:560px;background-color:#15191e;border-radius:16px;border:1px solid #262c33;overflow:hidden;">'
         . '<tr><td align="center" style="padding:30px 28px 22px 28px;background:linear-gradient(135deg,#4f8fc0,#3a76a8);">'
         . '<img src="' . $logo . '" alt="' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '" width="120" style="display:block;border:0;max-width:120px;height:auto;margin-bottom:10px;">'
         . '<div style="color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.02em;">' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</div>'
         . '</td></tr>'
         . '<tr><td style="padding:30px 30px;line-height:1.7;color:#e9e7e2;font-size:15px;">' . $body . '</td></tr>'
         . '<tr><td align="center" style="padding:20px 30px;border-top:1px solid #262c33;">'
         . '<p style="margin:0;font-size:12px;color:#9aa3ac;">&copy; ' . date('Y') . ' ' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '. All rights reserved.<br>This email was sent automatically, please do not reply.</p>'
         . '</td></tr></table></td></tr></table></body></html>';
}

function mail_send_brevo(\PDO $pdo, array $cfg, string $to, string $subject, string $bodyHtml, array $vars = [], array $from = []): bool {
    $apiKey = trim((string)($cfg['mail_brevo_api_key'] ?? ''));
    if ($apiKey === '' || !function_exists('curl_init')) {
        error_log('VX mail: Brevo transfer secili ama API anahtari yok veya curl eksik.');
        return false;
    }
    $fromEmail = trim((string)($from['email'] ?? ''));
    $fromName = trim((string)($from['name'] ?? ''));
    if ($fromEmail === '') $fromEmail = trim((string)($cfg['mail_from_email'] ?? ''));
    if ($fromName === '') $fromName = trim((string)($cfg['mail_from_name'] ?? ''));
    if ($fromEmail === '') $fromEmail = 'info@vexaris.net';
    $siteName = '';
    try {
        $st = $pdo->prepare("SELECT `value` FROM settings WHERE `key`='site_name' LIMIT 1");
        $st->execute();
        $siteName = (string)$st->fetchColumn();
    } catch (Exception $e) {}
    if ($fromName === '') $fromName = $siteName !== '' ? $siteName : 'Vexaris';
    $plainSrc = mail_render($bodyHtml, $vars);
    $plainSrc = str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</tr>', '</h1>', '</h2>', '</h3>'], "\n", $plainSrc);
    $plain = html_entity_decode(trim((string)preg_replace('/[ \t\x0B\f]+/', ' ', (string)preg_replace('/\n\s*\n+/', "\n\n", strip_tags($plainSrc)))), ENT_QUOTES, 'UTF-8');
    $payload = [
        'sender' => ['email' => $fromEmail, 'name' => $fromName],
        'to' => [['email' => $to]],
        'subject' => mail_render($subject, $vars),
        'htmlContent' => !empty($from['raw']) ? mail_render($bodyHtml, $vars) : mail_wrap_layout($pdo, $bodyHtml, $vars),
        'textContent' => $plain,
    ];
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'api-key: ' . $apiKey],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false || $code < 200 || $code >= 300) {
        error_log('VX mail brevo: HTTP ' . $code . ' ' . ($err !== '' ? $err . ' ' : '') . mb_substr((string)$resp, 0, 200));
        return false;
    }
    return true;
}

function mail_send(\PDO $pdo, string $to, string $subject, string $bodyHtml, array $vars = [], array $from = []): bool {
    $cfg = mail_get_settings($pdo);
    if (($cfg['mail_enabled'] ?? '0') !== '1') return false;

    $transfer = strtolower(trim((string)($cfg['mail_transfer'] ?? 'sendmail')));
    if ($transfer === 'brevo') {
        return mail_send_brevo($pdo, $cfg, $to, $subject, $bodyHtml, $vars, $from);
    }

    if (!is_file(VX_MAIL_AUTOLOAD)) {
        error_log('VX mail: PHPMailer bulunamadı: ' . VX_MAIL_AUTOLOAD);
        return false;
    }
    require_once VX_MAIL_AUTOLOAD;

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        if ($transfer === 'smtp') {
            $host = trim((string)($cfg['mail_smtp_host'] ?? ''));
            $user = trim((string)($cfg['mail_smtp_user'] ?? ''));
            if ($host === '' || $user === '') {
                error_log('VX mail: SMTP transfer seçili ama SMTP ayarı eksik.');
                return false;
            }
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = (string)($cfg['mail_smtp_pass'] ?? '');
            $enc = $cfg['mail_smtp_encryption'] ?? 'tls';
            $mail->SMTPSecure = $enc === 'none' ? '' : ($enc === 'ssl' ? 'ssl' : 'tls');
            $mail->Port = (int)($cfg['mail_smtp_port'] ?? 587);
        } else {
            // Varsayılan: yerel Exim üzerinden sendmail (DKIM/SPF sunucuda imzalanır, şifre gerekmez)
            $mail->isSendmail();
        }
        $mail->CharSet = 'UTF-8';
        $mail->SMTPDebug = 0;

        $fromEmail = trim((string)($from['email'] ?? ''));
        $fromName  = trim((string)($from['name'] ?? ''));
        if ($fromEmail === '') $fromEmail = trim((string)($cfg['mail_from_email'] ?? ''));
        if ($fromName === '')  $fromName  = trim((string)($cfg['mail_from_name'] ?? ''));
        if ($fromEmail === '') $fromEmail = trim((string)($cfg['mail_smtp_user'] ?? ''));
        if ($fromEmail === '') $fromEmail = 'info@vexaris.net';
        $siteName = '';
        try {
            $st = $pdo->prepare("SELECT `value` FROM settings WHERE `key`='site_name' LIMIT 1");
            $st->execute();
            $siteName = (string)$st->fetchColumn();
        } catch (Exception $e) {}
        if ($fromName === '') $fromName = $siteName !== '' ? $siteName : 'Vexaris';

        $mail->setFrom($fromEmail, $fromName);
        $mail->Sender = $fromEmail; // zarf-gönderen: SPF hizalaması için From ile aynı
        $mail->addAddress($to);
        $mail->isHTML(true);
        // Düz-metin yedeği: HTML görüntülenemezse asla ham kod görünmesin
        $plainSrc = mail_render($bodyHtml, $vars);
        $plainSrc = str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</tr>', '</h1>', '</h2>', '</h3>'], "\n", $plainSrc);
        $mail->AltBody = html_entity_decode(trim((string)preg_replace('/[ \t\x0B\f]+/', ' ', (string)preg_replace('/\n\s*\n+/', "\n\n", strip_tags($plainSrc)))), ENT_QUOTES, 'UTF-8');
        $mail->Subject = mail_render($subject, $vars);
        $mail->Body    = !empty($from['raw']) ? mail_render($bodyHtml, $vars) : mail_wrap_layout($pdo, $bodyHtml, $vars);
        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log('VX mail_send: ' . $e->getMessage());
        return false;
    }
}

function mail_spam_words_default(): array {
    return [
        'bahis','kumar','casino','rulet','jackpot','poker','roulette','blackjack','betting','gambling','bookmaker',
        'freebet','sportsbet','betbonus','bonusveren','denemebonusu','bedavabonus','yatirimsizbonus','casinobonus',
        'mobilbahis','bets10','betpark','betboo','tipobet','oleybet','betmatik','holiganbet','tempobet','superbetin','kralbet','artemisbet',
        'kredikarti','hizlikredi','hizli kredi','paydayloan','quickloan','forexsignals','binaryoptions',
        'porno','porna','porn','xxx','nude','nudes','escort','fuhus','orospu','sikis','sikik','siktir','amina','amk','yarrak','gotveren','tassak','kahpe','fahise','onlyfans','webcam',
        'esrar','uyusturucu','kokain','eroin','methamphetamine',
        'bitcoindoubler','kriptocikar','workfromhome','weightloss','viagra','cialis','levitra'
    ];
}
function mail_spam_words(?PDO $pdo = null): array {
    $pdo = $pdo ?: (function_exists('db') ? db() : null);
    $raw = '';
    if ($pdo) {
        try {
            $st = $pdo->prepare("SELECT `value` FROM settings WHERE `key`='mail_spam_words' LIMIT 1");
            $st->execute();
            $v = $st->fetchColumn();
            if ($v !== false && $v !== null) { $raw = (string)$v; }
        } catch (Exception $e) {}
    }
    $out = [];
    foreach (preg_split('/[\r\n,;]+/', $raw) as $w) {
        $w = trim($w);
        if ($w !== '' && !in_array($w, $out, true)) { $out[] = $w; }
    }
    if (!$out) { $out = mail_spam_words_default(); }
    return $out;
}
function mail_norm(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = strtr($s, [
        'ı' => 'i', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o', 'ç' => 'c',
        'â' => 'a', 'î' => 'i', 'û' => 'u', 'é' => 'e', 'è' => 'e', 'à' => 'a', 'ñ' => 'n',
    ]);
    $s = str_replace(['0', '1', '3', '4', '5', '7', '8', '@', '$', '!', '|'], ['o', 'i', 'e', 'a', 's', 't', 'b', 'a', 's', 'i', 'i'], $s);
    return $s;
}
function mail_spam_prefilter(string $subject, string $from, string $body, int $maxLen, array $words): array {
    $norm = mail_norm($subject . "\n" . $from . "\n" . $body);
    $flat = (string)preg_replace('/[^a-z0-9]+/', '', $norm);
    $tokens = preg_split('/[^a-z0-9]+/', $norm, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    foreach ($words as $w) {
        $nw = (string)preg_replace('/[^a-z0-9]+/', '', mail_norm((string)$w));
        if ($nw === '') { continue; }
        if (mb_strlen($nw) >= 5 && $flat !== '' && mb_strpos($flat, $nw) !== false) {
            return ['action' => 'spam', 'reason' => 'kural: ' . $w];
        }
        if (in_array($nw, $tokens, true)) {
            return ['action' => 'spam', 'reason' => 'kural: ' . $w];
        }
    }
    $len = mb_strlen($body);
    if ($maxLen > 0 && $len >= $maxLen) {
        return ['action' => 'skip', 'reason' => 'atlandı: uzun mail (' . $len . ' karakter)'];
    }
    return ['action' => 'ai', 'reason' => ''];
}

function mail_spam_setting(\PDO $pdo, string $key, string $def = ''): string {
    try {
        $st = $pdo->prepare("SELECT `value` FROM settings WHERE `key`=? LIMIT 1");
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return ($v === false || $v === null) ? $def : (string)$v;
    } catch (Exception $e) { return $def; }
}

function mail_html_to_text(string $html): string {
    $t = str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</tr>', '</h1>', '</h2>', '</h3>', '</li>'], "\n", $html);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8');
    $t = (string)preg_replace('/[ \t\x0B\f]+/', ' ', $t);
    $t = (string)preg_replace('/\n\s*\n+/', "\n\n", $t);
    return trim($t);
}

function mail_spam_default_prompt(): string {
    return 'Sen bir e-posta spam filtresisin. Sana verilen e-postayi incele ve SADECE "Yes" ya da "No" yaz. Reklam, phishing, dolandiricilik, istenmeyen toplu gonderim veya supheli ise "Yes"; gercek, kisisel veya mesru bir yazisma ise "No". Baska hicbir sey yazma.';
}

/* EVREN clef (tipli karar modeli) icin talimat: metin uretmez, olasilik doner.
   Soru: "kullanici bu maili ISTEMEDEN mi aldi?" -> banka/fatura/guvenlik bildirimleri
   spam DEGILDIR. (9 orneklik denetimde V1 metin-promptu 4/9, bu yonelim 8/9 hataliydi.) */
function mail_spam_clef_prompt(): string {
    return 'Bu bir e-posta spam filtresidir. Tek soru: kullanici bu maili ISTEMEDEN mi aldi? '
        . 'SPAM: reklam/kampanya, kumar, yatirim vaadi, phishing, dolandiricilik, toplu satis gonderimi, '
        . 'sosyal medya promotionu, abonelik sonrasi gelen beklenmeyen satislar, taninmayan gonderenden gelen baglanti. '
        . 'SPAM DEGIL: banka ve odeme islem bildirimleri, fatura, siparis/kargo durumu, guvenlik uyarisi '
        . '(hesap hareketi, yeni cihaz girisi), destek/canli destek yaniti, resmi kurum yazisi, kisisel yazisma, '
        . 'kullaniciya ozel olusturulmus icerik, otomatik sistem bildirimi. '
        . 'Mesajin konusu para, guvenlik veya baglanti icermesi onu spam yapmaz; belirleyici, mesajin '
        . 'ISTEMEDEN gonderilmis olmasi ve kullaniciya fayda sunmamasi.';
}

function mail_spam_use_evren(\PDO $pdo): bool {
    if (mail_spam_setting($pdo, 'mail_spam_evren', '0') !== '1') return false;
    if (!function_exists('evren_is_ready')) {
        $evFile = __DIR__ . '/evren.php';
        if (is_file($evFile)) require_once $evFile;
    }
    if (!function_exists('evren_is_ready')) return false;
    return evren_is_ready($pdo);
}

/* EVREN clef ile spam olasiligi (0-1). Hata durumunda null. */
function mail_spam_evren_score(\PDO $pdo, string $state, string $instructions): ?float {
    if (!function_exists('evren_decide')) {
        $evFile = __DIR__ . '/evren.php';
        if (is_file($evFile)) require_once $evFile;
    }
    if (!function_exists('evren_decide')) return null;
    $model = mail_spam_setting($pdo, 'mail_spam_evren_model', 'clef');
    if ($model === '') $model = 'clef';
    $r = evren_decide($model, $state, ['spam' => ['type' => 'noul', 'instructions' => $instructions]], $pdo);
    if (empty($r['ok'])) {
        error_log('[NS] mail spam evren: ' . (string)($r['msg'] ?? 'hata'));
        return null;
    }
    $p = evren_noul((array)($r['answers'] ?? []), 'spam');
    if ($p === null) {
        error_log('[NS] mail spam evren: olasilik gelmedi');
        return null;
    }
    return max(0.0, min(1.0, (float)$p));
}

function mail_spam_scan_one(\PDO $pdo, array $prov, string $sysPrompt, string $model, array $row): array {
    $subject = (string)($row['subject'] ?? '');
    $from = (string)($row['from_addr'] ?? '');
    $body = (string)($row['body_text'] ?? '');
    if (trim($body) === '') { $body = mail_html_to_text((string)($row['body_html'] ?? '')); }
    $bodyFull = $body;
    $body = mb_substr($body, 0, 6000);

    $maxLen = (int)mail_spam_setting($pdo, 'mail_spam_maxlen', '10000');
    $pf = mail_spam_prefilter($subject, $from, $bodyFull, $maxLen, mail_spam_words($pdo));
    if ($pf['action'] === 'spam') {
        return ['ok' => true, 'spam' => 1, 'reason' => $pf['reason'], 'by' => 'rule'];
    }
    if ($pf['action'] === 'skip') {
        return ['ok' => true, 'spam' => 0, 'reason' => $pf['reason'], 'by' => 'skip', 'skipped' => true];
    }

    $content = $sysPrompt . "\n\n--- E-POSTA ---\nKimden: " . $from . "\nKonu: " . $subject . "\n\n" . $body;

    /* EVREN clef yolu: olasilik tabanli karar (esik ile), mevcut AI saglayicisi
       yolu bozulmadan durur; EVREN hata verirse mevcut yola dusulur. */
    if (mail_spam_use_evren($pdo)) {
        $thr = (float)mail_spam_setting($pdo, 'mail_spam_threshold', '0.7');
        if ($thr <= 0 || $thr > 1) $thr = 0.7;
        $instr = mail_spam_setting($pdo, 'mail_spam_clef_prompt', '');
        if (trim($instr) === '') $instr = mail_spam_clef_prompt();
        $state = "Kimden: " . $from . "\nKonu: " . $subject . "\n\n" . $body;
        $prob = mail_spam_evren_score($pdo, $state, $instr);
        if ($prob !== null) {
            $isSpam = ($prob >= $thr) ? 1 : 0;
            $score = (int)round($prob * 100);
            return [
                'ok' => true,
                'spam' => $isSpam,
                'reason' => 'clef ' . $score . '%',
                'by' => 'evren',
                'prob' => $prob,
            ];
        }
    }

    $msgs = [['role' => 'user', 'content' => $content]];
    $r = ai_call_provider($prov, $msgs, 8);
    if (empty($r['ok'])) {
        try { ai_usage_log($pdo, 'admin-spam', (string)($prov['provider'] ?? ''), $model, 0, 0, false, mb_substr((string)($r['msg'] ?? 'hata'), 0, 200)); } catch (Exception $e) {}
        return ['ok' => false, 'msg' => mb_substr((string)($r['msg'] ?? 'AI hatasi'), 0, 120)];
    }
    $text = trim((string)($r['text'] ?? ''));
    $first = strtolower(preg_replace('/[^a-z]/i', '', mb_substr($text, 0, 12)));
    $isSpam = (strpos($first, 'yes') === 0) ? 1 : 0;
    try { ai_usage_log($pdo, 'admin-spam', (string)($prov['provider'] ?? ''), $model, (int)($r['in'] ?? 0), (int)($r['out'] ?? 0), true); } catch (Exception $e) {}
    return ['ok' => true, 'spam' => $isSpam, 'reason' => mb_substr($text !== '' ? $text : ($isSpam ? 'Yes' : 'No'), 0, 190), 'by' => 'ai'];
}

function mail_spam_scan_email(\PDO $pdo, int $id): array {
    if ($id <= 0) return ['ok' => false, 'msg' => 'bad id'];
    $aiFile = __DIR__ . '/ai.php';
    if (!function_exists('ai_call_provider') && is_file($aiFile)) { require_once $aiFile; }
    if (!function_exists('ai_call_provider') || !function_exists('ai_active_provider')) return ['ok' => false, 'msg' => 'ai yok'];
    if (mail_spam_setting($pdo, 'mail_spam_enabled', '1') !== '1') return ['ok' => false, 'msg' => 'kapali'];
    $prov = ai_active_provider($pdo);
    if (!$prov) return ['ok' => false, 'msg' => 'AI saglayici yok'];
    $model = (string)($prov['model_id'] ?? '');
    $sysPrompt = mail_spam_setting($pdo, 'mail_spam_prompt', mail_spam_default_prompt());
    if (trim($sysPrompt) === '') { $sysPrompt = mail_spam_default_prompt(); }
    $st = $pdo->prepare("SELECT id, subject, from_addr, body_text, body_html FROM inbound_emails WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) return ['ok' => false, 'msg' => 'mail yok'];
    $res = mail_spam_scan_one($pdo, $prov, $sysPrompt, $model, $row);
    if (empty($res['ok'])) return $res;
    $pdo->prepare("UPDATE inbound_emails SET spam=?, spam_checked=1, spam_score=?, spam_reason=? WHERE id=?")
        ->execute([$res['spam'], $res['spam'] ? 100 : 0, $res['reason'], $id]);
    return ['ok' => true, 'id' => $id, 'spam' => $res['spam'], 'reason' => $res['reason'], 'by' => ($res['by'] ?? 'ai'), 'skipped' => !empty($res['skipped']), 'model' => $model];
}

function mail_promote_stale_pending(\PDO $pdo, int $minutes = 30): int {
    if ($minutes < 1) { $minutes = 30; }
    try {
        $st = $pdo->prepare("UPDATE inbound_emails SET spam=0, spam_checked=1, spam_score=0, spam_reason=? WHERE spam_checked=0 AND deleted=0 AND created_at < (NOW() - INTERVAL " . (int)$minutes . " MINUTE)");
        $st->execute(['AI tarama zaman asimi (' . $minutes . ' dk) - gelen kutusuna']);
        return $st->rowCount();
    } catch (Exception $e) { return 0; }
}

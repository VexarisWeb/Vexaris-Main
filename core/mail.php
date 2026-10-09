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

function mail_send(\PDO $pdo, string $to, string $subject, string $bodyHtml, array $vars = [], array $from = []): bool {
    $cfg = mail_get_settings($pdo);
    if (($cfg['mail_enabled'] ?? '0') !== '1') return false;

    if (!is_file(VX_MAIL_AUTOLOAD)) {
        error_log('VX mail: PHPMailer bulunamadı: ' . VX_MAIL_AUTOLOAD);
        return false;
    }
    require_once VX_MAIL_AUTOLOAD;

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $transfer = strtolower(trim((string)($cfg['mail_transfer'] ?? 'sendmail')));
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
        $mail->Body    = mail_wrap_layout($pdo, $bodyHtml, $vars);
        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log('VX mail_send: ' . $e->getMessage());
        return false;
    }
}

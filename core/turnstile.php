<?php
function turnstile_enabled(): bool {
    return setting_get('turnstile_enabled','0') === '1' && setting_get('turnstile_site_key','') !== '' && setting_get('turnstile_secret','') !== '';
}
function turnstile_site_key(): string { return setting_get('turnstile_site_key',''); }
function turnstile_secret(): string { return setting_get('turnstile_secret',''); }
function turnstile_verify(?string $token, ?string $ip = null): bool {
    if (!turnstile_enabled()) return true;
    if ($token === null || $token === '') return false;
    $secret = turnstile_secret();
    $data = ['secret'=>$secret, 'response'=>$token];
    if ($ip) $data['remoteip'] = $ip;
    $opts = ['http'=>['method'=>'POST','header'=>"Content-Type: application/x-www-form-urlencoded\r\n",'content'=>http_build_query($data),'timeout'=>5,'ignore_errors'=>true]];
    $ctx = stream_context_create($opts);
    $raw = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
    if ($raw === false) return false;
    $j = json_decode($raw, true);
    return is_array($j) && ($j['success'] ?? false) === true;
}
function turnstile_widget(string $id='cf-turnstile'): string {
    if (!turnstile_enabled()) return '';
    $key = htmlspecialchars(turnstile_site_key(), ENT_QUOTES, 'UTF-8');
    return '<div id="'.$id.'" class="cf-turnstile" data-sitekey="'.$key.'" data-theme="dark" style="margin:12px 0"></div>';
}
function turnstile_script(): string {
    if (!turnstile_enabled()) return '';
    return '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
}

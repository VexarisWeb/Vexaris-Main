<?php
// ============================================================
//  Vexaris — E-posta Şablonları (2 template: reset + aktivasyon)
//  Auth akışı için gerekli çekirdek mailler korunur.
//  Tüm şablonlar "Merhaba {user_name}," ile başlar ve
//  mail_wrap_layout() koyu Vexaris kartı içinde sarılır.
//  Gönderim her zaman multipart (HTML + düz metin) yapılır,
//  ham HTML kodu asla görünmez.
// ============================================================

// ── 1) Şifre Sıfırlama — 6 haneli bitişik kod ────────────────
function mail_tpl_reset(string $code): array {
    $code = preg_replace('/[^0-9]/', '', $code);
    $cells = '';
    foreach (str_split($code) as $d) {
        $cells .= '<td align="center" width="46" style="background-color:#0b0d10;border:1px solid #4f8fc0;border-radius:10px;">'
            . '<div style="color:#ffffff;font-size:24px;font-weight:800;letter-spacing:0;padding:12px 0;">' . $d . '</div></td>'
            . '<td width="8" style="font-size:0;line-height:0;">&nbsp;</td>';
    }
    $body = '<p style="margin:0 0 14px 0;color:#e9e7e2;font-size:15px;">Hello {user_name},</p>'
        . '<p style="margin:0 0 18px 0;color:#9aa3ac;font-size:14px;line-height:1.7;">You requested a password reset. Enter the <strong style="color:#e9e7e2;">6-digit code</strong> below on the verification screen. Codes expire after <strong style="color:#e9e7e2;">15 minutes</strong>.</p>'
        . '<table border="0" cellpadding="0" cellspacing="0" align="center" style="margin:0 auto 18px auto;"><tr>' . $cells . '</tr></table>'
        . '<p style="margin:0;color:#9aa3ac;font-size:13px;line-height:1.7;">If you did not make this request, ignore this email; your password will not change.</p>';
    return ['Your Password Reset Code — Vexaris', $body];
}

// ── 2) Hesap Aktivasyonu — butonlu ───────────────────────────
function mail_tpl_activation(string $activationUrl): array {
    $body = '<p style="margin:0 0 14px 0;color:#e9e7e2;font-size:15px;">Hello {user_name},</p>'
        . '<p style="margin:0 0 18px 0;color:#9aa3ac;font-size:14px;line-height:1.7;">Welcome to the Vexaris family! Click the button below to activate your account. Links expire after <strong style="color:#e9e7e2;">48 hours</strong>.</p>'
        . '<table border="0" cellpadding="0" cellspacing="0" width="100%"><tr><td align="center" style="padding:6px 0 16px 0;">'
        . '<a href="{activation_url}" style="display:inline-block;background:linear-gradient(135deg,#4f8fc0,#3a76a8);color:#ffffff;font-size:16px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:10px;">Activate My Account</a>'
        . '</td></tr></table>'
        . '<p style="margin:0;color:#9aa3ac;font-size:13px;line-height:1.7;">If the button does not work, paste this link into your browser:<br><span style="color:#7fb3d9;word-break:break-all;">{activation_url}</span></p>';
    // Not: çağıran taraf $vars içine 'activation_url' anahtarını eklemelidir.
    return ['Activate Your Account — Vexaris', $body, ['activation_url' => $activationUrl]];
}

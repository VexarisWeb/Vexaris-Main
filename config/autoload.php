<?php
function _vx_k(): string { return 'v' . '4x' . '7e' . strrev('REDACTED_KEY') . '!' . 'c' . '3' . 'b'; }
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

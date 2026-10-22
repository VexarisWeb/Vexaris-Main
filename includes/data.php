<?php
// data.php — index.php ve features.php'nin ortak verisi (logo, footer linkleri, yasal metinler)

$VEX_LOGO = '/assets/images/vexaris-logo.png';

$FOOTER_COLS = [
  ['title'=>'Platform','links'=>[['label'=>'Home','href'=>'/'],['label'=>'Features','href'=>'/features'],['label'=>'Pricing','href'=>'/pricing'],['label'=>'Blog','href'=>'/blog'],['label'=>'How It Works','href'=>'/#how-it-works'],['label'=>'Payment Methods','href'=>'/#payments']]],
  ['title'=>'Legal','links'=>[['label'=>'Privacy Policy','href'=>'/privacy-policy'],['label'=>'Terms of Use','href'=>'/terms-of-use'],['label'=>'Cookie Policy','href'=>'/cookie-policy']]],
  ['title'=>'Support','links'=>[['label'=>'Help Center','href'=>'#faq'],['label'=>'FAQ','href'=>'#faq'],['label'=>'Contact','href'=>'mailto:destek@vexaris.net'],['label'=>'Status Page','href'=>'https://status.vexaris.net']]],
];

$LEGAL = [
  'privacy'=>['title'=>'Privacy Policy','updated'=>'Last updated: January 4, 2026','body'=>['Vexaris processes data such as names, emails and order history solely to deliver purchased digital products, answer support tickets and meet legal obligations.','Payments run through contracted providers (Shopier, PayTR, iyzico, Stripe, PayPal, BitPay, Cryptomus); card details are never stored on Vexaris servers.','You can request deletion or export of your data at any time via destek@vexaris.net; requests are answered within 72 hours.']],
  'terms'=>['title'=>'Terms of Use','updated'=>'Last updated: January 4, 2026','body'=>['Digital products sold on the Vexaris store are delivered with a single-user license unless stated otherwise on the product page; sharing keys with third parties leads to license revocation.','Account security is the user\'s responsibility. Accounts may be temporarily locked on suspicious activity, with verification requested via the support channel.','Sellers on the platform declare they hold the copyright to uploaded content; violation reports are reviewed within 48 hours.']],
  'cookies'=>['title'=>'Cookie Policy','updated'=>'Last updated: January 4, 2026','body'=>['Strictly necessary cookies are used to protect sessions, remember carts and secure forms (CSRF), and cannot be disabled.','Analytics cookies (Google Analytics) load only with your consent; page activity is measured anonymously.','You can withdraw consent at any time via the cookie banner at the bottom of the panel or your account settings.']],
  'refund'=>['title'=>'Refund Policy','updated'=>'Last updated: January 4, 2026','body'=>['Digital products are exempt from withdrawal rights since delivery is instant; however, undelivered, faulty or misdescribed products carry a full refund guarantee.','Refund requests open via support ticket; once key non-use is verified, the amount returns to the payment method within 5 business days or loads instantly to balance.','For single-stock e-pin codes already viewed, refunds apply only if the code turns out invalid.']],
];

function vx_img_size(string $src): ?array {
    static $cache = [];
    $path = (string)(parse_url($src, PHP_URL_PATH) ?: $src);
    $path = '/' . ltrim(preg_replace('#^\./#', '', $path), '/');
    if (array_key_exists($path, $cache)) return $cache[$path];
    $fs = dirname(__DIR__) . $path;
    $d = is_file($fs) ? @getimagesize($fs) : false;
    return $cache[$path] = ($d ? [(int)$d[0], (int)$d[1]] : null);
}

function vx_img_attrs(string $src): string {
    $d = vx_img_size($src);
    return $d ? ' width="' . $d[0] . '" height="' . $d[1] . '"' : '';
}

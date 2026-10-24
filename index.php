<?php
// index.php — Vexaris landing (build YOK, saf PHP + Tailwind CDN)
// Yapı: index.php + header.php + footer.php, includes/, assets/css-js-images/

require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/data.php';
boot_session();
// Bilinmeyen yol buraya düşerse (nginx fallback) soft-404 yerine gerçek 404 ver.
// Meşru: "/" ve "/index.php" (sorgu dizgisi serbest).
$__reqPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if ($__reqPath === '/index.php') {
    header('Location: /', true, 301);
    exit;
}
if ($__reqPath !== '/' && $__reqPath !== '/index.php') {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
log_pageview('anasayfa');
$loggedIn = is_logged_in();
$whopOn = false;
try { require_once __DIR__ . '/core/whop.php'; $whopOn = whop_payments_enabled(); } catch (Throwable $e) { $whopOn = false; }
$GLOBALS['seoKey'] = 'home';

$BASE = '';
$VEX_DASH = './assets/images/dashboard.png.webp';
// Not: footer linkleri tek kaynaktan gelir (includes/data.php).

$HERO_WORDS = [
  ['t' => 'e-pins',     'c' => '#7fb3d9'],
  ['t' => 'accounts',   'c' => '#74c091'],
  ['t' => 'e-books',    'c' => '#e0a458'],
  ['t' => 'licenses',   'c' => '#9b8cf0'],
  ['t' => 'softwares',  'c' => '#e0735c'],
];
$HERO_FIRST = $HERO_WORDS[0];
$HERO_JSON = htmlspecialchars(json_encode($HERO_WORDS, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

$PAYMENTS = [
  ['id'=>'shopier','name'=>'Shopier','tag'=>'Local','desc'=>"Turkey's local payment solution",'detail'=>'The fastest start for individual and business sellers. The delivery chain triggers the moment the payment approval webhook arrives.','features'=>['3D Secure','Webhook approval','Same-day delivery trigger'],'icon'=>'wallet'],
  ['id'=>'iyzico','name'=>'iyzico','tag'=>'Virtual POS','desc'=>'Easy integration, secure infrastructure','detail'=>'One-click saved cards and a protected checkout flow; process refunds from the panel in one click.','features'=>['Saved cards','Panel refunds','Fraud filters'],'icon'=>'lock'],
  ['id'=>'stripe','name'=>'Stripe','tag'=>'Global','desc'=>'International credit card payments','detail'=>'Sell globally in 135+ currencies. Define multi-currency prices per product.','features'=>['135+ currencies','Subscription support','Radar fraud protection'],'icon'=>'globe'],
  ['id'=>'paypal','name'=>'PayPal','tag'=>'Global','desc'=>'Worldwide digital wallet','detail'=>'Trusted wallet payments for international customers; the key is assigned automatically when the approval token arrives.','features'=>['Wallet payments','Buyer protection','Multi-country'],'icon'=>'wallet'],
  ['id'=>'bitpay','name'=>'BitPay','tag'=>'Crypto','desc'=>'Crypto payment infrastructure','detail'=>'Accept BTC and BCH payments with a rate lock; delivery processes in sync with on-chain confirmation after approval.','features'=>['Rate lock','BTC / BCH','Auto reconciliation'],'icon'=>'coin'],
  ['id'=>'cryptomus','name'=>'Cryptomus','tag'=>'Crypto','desc'=>'Multi-crypto payment support','detail'=>'Sell crypto with USDT, TRON and 20+ networks; a dedicated wallet address is generated and tracked per order.','features'=>['20+ networks','Per-order address','Auto confirmation'],'icon'=>'coin'],
  ['id'=>'paywant','name'=>'Paywant','tag'=>'Local','desc'=>'Practical bank transfer / EFT solution','detail'=>'Token-based secure payment flow; the delivery chain triggers as soon as the approval notification arrives.','features'=>['Instant notification (IPN)','Credit + cart support','Easy integration'],'icon'=>'wallet'],
  ['id'=>'whop','name'=>'Whop','tag'=>'POS','desc'=>'Accept payments via Whop','detail'=>'Collect payments via Whop POS; the delivery chain triggers as soon as approval arrives.','features'=>['Quick connect','Automatic delivery','Global reach'],'icon'=>'wallet'],
  ['id'=>'dodo','name'=>'Dodo Payments','tag'=>'MoR','desc'=>'Turkey-friendly global Merchant of Record','detail'=>'Sell in 220+ countries without a foreign entity; VAT, GST and sales tax handled, payouts in your currency.','features'=>['40+ methods','VAT handled','No foreign entity'],'icon'=>'globe'],
  ['id'=>'paddle','name'=>'Paddle','tag'=>'MoR','desc'=>'Merchant of Record for SaaS and digital goods','detail'=>'Checkout, subscriptions, VAT/GST and compliance managed end to end across 200+ markets.','features'=>['VAT/GST handled','Subscriptions','Compliance'],'icon'=>'globe'],
  ['id'=>'lemon','name'=>'Lemon Squeezy','tag'=>'MoR','desc'=>'Merchant of Record for digital products','detail'=>'Global checkout with tax handling, subscriptions and license management in one platform.','features'=>['Global tax','Subscriptions','Licensing'],'icon'=>'globe'],
];
// Entegrasyon şeridi: logo dosyası olanlar görsel, olmayanlar yazı olarak çıkar
$LOGOS = [
  ['name'=>'Shopier','file'=>'shopier.png.webp'],
  ['name'=>'PayTR','file'=>'paytr.png.webp'],
  ['name'=>'Paywant','file'=>'paywant.png.webp'],
  ['name'=>'iyzico','file'=>'iyzico.png.webp'],
  ['name'=>'Stripe','file'=>'stripe.png.webp'],
  ['name'=>'PayPal','file'=>'paypal.png.webp'],
  ['name'=>'BitPay','file'=>'bitpay.png.webp'],
  ['name'=>'Cryptomus','file'=>'cryptomus.png.webp'],
  ['name'=>'Whop','file'=>'whop.jpg.webp'],
  ['name'=>'Dodo Payments','file'=>'dodopayments.jpg.webp'],
  ['name'=>'Paddle','file'=>'paddle.jpg.webp'],
  ['name'=>'Lemon Squeezy','file'=>'lemonsqueezy.jpg.webp'],
  ['name'=>'Google Auth','file'=>'google.png.webp'],
  ['name'=>'Discord Auth','file'=>'discord.png.webp'],
  ['name'=>'Microsoft Auth','file'=>'microsoft.png.webp'],
  ['name'=>'GitHub Auth','file'=>'github.png.webp','chip'=>true],
  ['name'=>'Cloudflare Turnstile','file'=>'cloudflare.png.webp'],
  ['name'=>'reCAPTCHA','file'=>'recaptcha.png.webp'],
  ['name'=>'Tawk.to','file'=>'tawkto.png.webp'],
  ['name'=>'Crisp','file'=>'crispchat.png.webp'],
  ['name'=>'JivoChat','file'=>'jivochat.png.webp'],
  ['name'=>'Google Analytics','file'=>null],
];
$STEPS = [
  ['n'=>'01','title'=>'Publish your product','desc'=>'Upload your file or paste one-per-line e-pins / license content. Price, discount, category, and ordering in a single form.','meta'=>'zip / rar / 7z / jar • stock line tracking'],
  ['n'=>'02','title'=>'Customer checks out','desc'=>'The customer finds the product, adds it to the cart, applies a coupon, and pays by balance or card. Turnstile verifies silently.','meta'=>'coupons + balance + ' . count($PAYMENTS) . ' payment providers'],
  ['n'=>'03','title'=>'Let the system deliver','desc'=>'Once the payment is approved, a line is pulled from stock, the license key is signed, and the file and key land in "My Purchases".','meta'=>'delivery automation'],
  ['n'=>'04','title'=>'Support and updates','desc'=>'If a customer gets stuck, they open a ticket; when you release a new version, every previous buyer gets the updated file from their download list.','meta'=>'tickets • version tracking • update notices'],
];
$NEWS = [];
try {
    require_once __DIR__ . '/core/blog.php';
    $pdoNews = db();
    if ($pdoNews) {
        $rows = array_values(array_filter(blog_list($pdoNews, 3), fn($p) => ($p['status'] ?? '') === 'published'));
        foreach ($rows as $r) {
            $d = date('j F Y', strtotime($r['created_at']));
            $desc = blog_excerpt_text($r, 140);
            $NEWS[] = ['date'=>$d,'tag'=>'Blog','slug'=>$r['slug'],'title'=>$r['title'],'desc'=>$desc,'cover'=>(string)($r['cover_image'] ?? '')];
        }
    }
} catch (Throwable $e) { $NEWS = []; }
$FAQS = [
  ['q'=>'What kinds of products can I sell with Vexaris?','a'=>'You can sell any kind of digital product, including e-pins, e-books, license keys, software packages, Minecraft plugins and server setups. For file-based products you upload zip/rar/7z/jar; for code-based products you enter one e-pin or key per line and the system delivers them in order.'],
  ['q'=>'How secure are payments?','a'=>'Card details are never written to Vexaris servers; all charges run through Shopier, PayTR, iyzico, Stripe, PayPal, BitPay, Cryptomus, Paywant, Whop, Paddle, Lemon Squeezy or DodoPayments. Forms are verified with Cloudflare Turnstile or reCAPTCHA, and the admin panel is protected by a role-based permission matrix.'],
  ['q'=>'How are sold products delivered?','a'=>'The moment the approval webhook arrives from the payment provider, the system pulls a line from stock, signs the license key, and adds the file to the customer\'s “My Purchases” area. Customers download from the panel or copy the key in one click.'],
  ['q'=>'How is product stock managed?','a'=>'Two modes: unlimited stock (the file is delivered the same every order) and single stock (one e-pin / license key per line). In single mode each sale consumes a line; when stock runs out the product automatically closes and a panel warning appears.'],
  ['q'=>'What is the refund policy?','a'=>'Since digital delivery happens instantly, the seller sets refund terms; Vexaris offers tools to initiate refunds, load balances, or refund via the payment provider. You can publish your own refund policy in the legal page editor.'],
  ['q'=>'What can I do in the admin panel?','a'=>'Product and stock management, purchases, coupons, announcements, blog, references, FAQ, support tickets, user and role permissions, theme selection, payment / social login / live support integrations, mail templates, SEO settings, license and version tracking — all in one panel, tab by tab.'],
];
function vex_logo_item($l){
  $name = htmlspecialchars($l['name']);
  // logo + üstte baloncuk isim (hover). Baloncuk layout'u itmez (absolute).
  $out = '<span class="group relative flex items-center px-7 shrink-0">';
  if (!empty($l['file'])) {
    $chip = !empty($l['chip']) ? ' bg-[#e9e7e2] rounded-md px-2 py-1' : '';
    $out .= '<img src="./assets/images/' . htmlspecialchars($l['file']) . '" alt="' . $name . '"' . (function_exists('vx_img_attrs') ? vx_img_attrs('./assets/images/' . $l['file']) : '') . ' loading="lazy" draggable="false" class="h-6 w-auto max-w-[110px] object-contain grayscale opacity-60 group-hover:grayscale-0 group-hover:opacity-100 group-hover:scale-110 transition-all duration-300 select-none' . $chip . '" />';
  } else {
    $out .= '<span class="text-[12px] font-mono2 uppercase tracking-[0.18em] text-[#7d858e] whitespace-nowrap group-hover:text-white transition-colors select-none">' . $name . '</span>';
  }
  $out .= '<span class="pointer-events-none absolute -top-10 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md border border-[#262c33] bg-[#12151a] px-2.5 py-1 text-[12px] font-semibold text-[#e9e7e2] shadow-[0_10px_25px_-10px_rgba(0,0,0,.9)] opacity-0 translate-y-1 scale-95 group-hover:opacity-100 group-hover:translate-y-0 group-hover:scale-100 transition-all duration-200">' . $name . '</span>';
  $out .= '</span>';
  return $out;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#0B0D10" />
  <?php include __DIR__ . '/includes/seo-head.php'; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" />
  <link rel="icon" type="image/png" href="./assets/images/vexaris-logo.png" />
  <noscript><style>.reveal{opacity:1 !important;transform:none !important;}.line-mask>span{transform:none !important;}</style></noscript>
  <link rel="stylesheet" href="./assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>" />
  <link rel="stylesheet" href="./assets/css/tailwind.css?v=<?= filemtime(__DIR__ . '/assets/css/tailwind.css') ?>" />
</head>
<body>
<div class="relative min-h-screen bg-[#0b0d10] text-[#e9e7e2]">
  <div class="noise-overlay" aria-hidden="true"></div>

  <?php include __DIR__ . '/header.php'; ?>

  <main>
    <!-- ============ HERO ============ -->
    <section id="top" class="relative overflow-hidden pt-24 md:pt-28 pb-0">
      <div class="absolute inset-0 dotgrid opacity-60 [mask-image:radial-gradient(70%_60%_at_50%_20%,#000_20%,transparent_100%)]"></div>
      <div class="hero-orb-m" aria-hidden="true"></div>
      <div class="hero-aurora absolute -top-40 left-1/2 -translate-x-1/2 w-[900px] h-[500px] rounded-full bg-[#4f8fc0]/[.06] blur-[120px] mob-noglow"></div>
      <div class="relative max-w-7xl mx-auto px-5 sm:px-8">
        <div class="text-center max-w-4xl mx-auto">
          <h1 class="font-display font-extrabold tracking-tight text-[2.15rem] sm:text-[3rem] xl:text-[3.7rem] leading-[1.06]">
            <span class="block">Sell <span class="hero-type" id="heroTypeBox" data-words="<?= $HERO_JSON ?>"><span class="hero-type-text" id="heroType" style="color:<?= htmlspecialchars($HERO_FIRST['c']) ?>"><?= htmlspecialchars($HERO_FIRST['t']) ?></span></span><span class="hero-rest"> in minutes.</span></span>
            <span class="block text-[#4f8fc0]">0% commission.</span>
            <span class="block">Delivered instantly.</span>
          </h1>
          <p class="mt-6 mx-auto max-w-2xl text-[15.5px] leading-relaxed text-[#9aa3ac]">
            The all-in-one store for e-pins, game accounts, e-books, license keys and softwares. The moment
            a payment is approved, files and keys land in the customer's account automatically — no manual sending.
          </p>
          <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="<?= $loggedIn ? '/dashboard' : '/register' ?>" class="btn-primary px-6 py-3 text-sm inline-flex items-center gap-2">Start for free
              <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h15M13 6l6 6-6 6"/></svg>
            </a>
            <a href="https://demo.vexaris.net/demo" target="_blank" rel="noopener" class="btn-ghost px-6 py-3 text-sm font-semibold">See the live demo</a>
          </div>
          <div class="mt-5 text-[12.5px] text-[#7d858e]">No credit card required · Free plan forever · 0% commission</div>
        </div>

        <div class="relative mt-12 lg:mt-14 mx-auto max-w-5xl">
          <div class="pointer-events-none absolute -inset-x-10 -bottom-8 h-56 rounded-full bg-[#e0a458]/[.05] blur-[110px] mob-noglow" aria-hidden="true"></div>
          <a href="https://demo.vexaris.net/demo" target="_blank" rel="noopener" aria-label="Open the live Vexaris demo" class="group relative block rounded-2xl border border-[#262c33] bg-[#12151a] overflow-hidden shadow-[0_40px_80px_-40px_rgba(0,0,0,.9)]">
            <picture>
              <source srcset="./assets/images/demo-epin.png.webp" type="image/webp">
              <img src="./assets/images/demo-epin.png" alt="Vexaris demo storefront — E-PIN store homepage" width="1200" height="620" fetchpriority="high" decoding="async" style="width:100%;height:auto;display:block" class="w-full h-auto max-w-full block select-none" draggable="false" />
            </picture>
            <span class="absolute left-1/2 bottom-3.5 -translate-x-1/2 whitespace-nowrap rounded-full border border-[#2f3945] bg-[#0b0d10]/90 px-4 py-2 text-[12.5px] font-bold text-white backdrop-blur-sm transition-colors group-hover:border-[#4f8fc0]">Open the live demo →</span>
          </a>
          <div class="floaty hidden sm:block absolute -left-6 bottom-6 card px-4 py-3 w-[220px] shadow-[0_24px_50px_-24px_rgba(0,0,0,.9)]">
            <div class="flex items-center gap-2 text-[11px] font-mono2 text-[#74c091]"><span class="pulse-dot w-1.5 h-1.5 rounded-full bg-[#74c091] inline-block"></span>order #4821 delivered</div>
            <div class="mt-1.5 font-mono2 text-[13px] text-[#e9e7e2] tracking-wide">VEX-9K2L-M4PQ</div>
            <div class="mt-1 text-[11px] text-[#7d858e]">NovaChat Plugin • 0.3s</div>
          </div>
          <div class="floaty-2 hidden sm:block absolute -right-5 -top-5 card px-3.5 py-2.5 shadow-[0_24px_50px_-24px_rgba(0,0,0,.9)]">
            <div class="text-[10px] font-mono2 uppercase tracking-wider text-[#7d858e]">vexaris</div>
            <div class="mt-1 flex items-center gap-2">
              <span class="grid place-items-center w-5 h-5 rounded-full bg-[#74c091]/15 text-[#74c091] shrink-0">
                <svg viewBox="0 0 24 24" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
              </span>
              <div class="font-display font-bold text-[15px] tracking-tight text-[#e9e7e2] whitespace-nowrap overflow-hidden w-[200px]"><span id="rotWord" class="inline-block transition-all duration-500">Powerful Infrastructure</span></div>
            </div>
          </div>
        </div>

        <div class="mt-10 lg:mt-12 overflow-hidden pb-1 [mask-image:linear-gradient(to_right,transparent,#000_10%,#000_90%,transparent)]">
          <div id="logoTrack" class="marquee-track items-center">
            <?php for ($dup = 0; $dup < 2; $dup++): ?>
            <div class="flex shrink-0 items-center" <?= $dup === 1 ? 'aria-hidden="true"' : '' ?>>
              <?php foreach ($LOGOS as $lg): ?><?= vex_logo_item($lg) ?><?php endforeach; ?>
            </div>
            <?php endfor; ?>
          </div>
        </div>
      </div>
    </section>

    <section id="how-it-works" class="relative pt-12 pb-16 md:py-20 scroll-mt-24">
      <div class="max-w-7xl mx-auto px-5 sm:px-8 grid lg:grid-cols-12 gap-12 lg:gap-16">
        <div class="lg:col-span-5"><div class="lg:sticky lg:top-32">
          <div class="reveal">
            <h2 class="font-display font-bold tracking-tight text-3xl sm:text-4xl lg:text-[2.9rem] leading-[1.05]">Automated delivery<br />in four steps.</h2>
            <p class="mt-5 max-w-md text-[15px] leading-relaxed text-[#9aa3ac]">Sellers enter files or stock lines once; then orders, payments, delivery and support flow as links of one chain. No manual sending, no waiting customers.</p>
            <a href="/features" class="btn-ghost mt-7 inline-flex items-center gap-2 px-5 py-3 text-sm font-semibold">See all features <?= vex_icon('arrow','w-4 h-4') ?></a>
          </div>
        </div></div>
        <ol class="lg:col-span-7 relative">
          <?php foreach ($STEPS as $i => $s): ?>
          <li class="reveal relative pl-12 pb-12 last:pb-0 group" style="--rd:<?= $i * 90 ?>ms">
            <?php if ($i < count($STEPS) - 1): ?><span class="absolute left-[21px] top-12 bottom-0 w-px bg-[#262c33] group-last:hidden" aria-hidden="true"></span><?php endif; ?>
            <span class="step-num absolute left-0 top-0 grid place-items-center w-[42px] h-[42px] rounded-xl border border-[#262c33] bg-[#12151a] font-mono2 text-[13px] text-[#4f8fc0] transition-all duration-500 group-hover:border-[#4f8fc0]/60"><?= $s['n'] ?></span>
            <h3 class="font-display font-bold text-xl tracking-tight pt-1.5"><?= htmlspecialchars($s['title']) ?></h3>
            <p class="mt-2.5 text-[14.5px] leading-relaxed text-[#9aa3ac] max-w-xl"><?= htmlspecialchars($s['desc']) ?></p>
            <div class="step-meta mt-3 inline-flex items-center gap-2 font-mono2 text-[11px] text-[#7d858e] border border-[#262c33] rounded-full px-3 py-1 transition-all duration-500"><svg viewBox="0 0 24 24" class="w-3 h-3 text-[#74c091]" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg><?= htmlspecialchars($s['meta']) ?></div>
          </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </section>

    <!-- ============ ÖZELLİKLER ============ -->
    <section id="features" class="relative py-16 md:py-20 border-t border-[#262c33] scroll-mt-24">
      <div class="max-w-7xl mx-auto px-5 sm:px-8">
        <div class="reveal flex flex-col md:flex-row md:items-end md:justify-between gap-5 mb-12 md:mb-16">
          <h2 class="font-display font-bold tracking-tight text-3xl sm:text-4xl lg:text-[2.9rem] leading-[1.05] max-w-2xl">A store that runs<br />with zero seller effort.</h2>
          <p class="text-[15px] leading-relaxed text-[#9aa3ac] md:max-w-sm md:text-right md:pb-1">Every tab in the admin panel maps to a visible store behavior. These are the seven most-used modules.</p>
        </div>
        <div class="grid lg:grid-cols-6 gap-4">
          <div class="reveal lg:col-span-3 lg:row-span-2" style="--rd:0ms">
            <article class="card card-hover h-full p-6 flex flex-col">
              <span class="feat-ic grid place-items-center w-11 h-11 rounded-xl border border-[#262c33] bg-[#0e1114] text-[#4f8fc0]"><?= vex_icon('box') ?></span>
              <h3 class="mt-4 font-display font-bold text-lg tracking-tight">Product &amp; stock management</h3>
              <p class="mt-2 text-[13.5px] leading-relaxed text-[#9aa3ac]">zip/rar/7z/jar files or line-by-line single e-pins and license content. When stock runs out, sales close automatically.</p>
              <div class="mt-5 rounded-lg border border-[#262c33] bg-[#0e1114] overflow-hidden font-mono2 text-[11px]">
                <div class="flex items-center justify-between px-3 py-2 border-b border-[#262c33] text-[#7d858e]"><span>stock_lines</span><span>3/5 lines</span></div>
                <?php foreach ([['VEX-8QMD-7F3K','sold','#7d858e'],['VEX-2LPA-9K4T','sold','#7d858e'],['VEX-9K2L-M4PQ','sold','#7d858e'],['VEX-4TRB-6HZX','ready','#74c091'],['VEX-7NWJ-3VDS','ready','#74c091']] as $r): ?>
                <div class="flex items-center justify-between px-3 py-1.5 border-b border-[#1a1f25] last:border-0 text-[#9aa3ac]"><span><?= $r[0] ?></span><span style="color:<?= $r[2] ?>"><?= $r[1] ?></span></div>
                <?php endforeach; ?>
              </div>
            </article>
          </div>
          <div class="reveal lg:col-span-3" style="--rd:70ms">
            <article class="card card-hover h-full p-6 flex flex-col">
              <span class="feat-ic grid place-items-center w-11 h-11 rounded-xl border border-[#262c33] bg-[#0e1114] text-[#4f8fc0]"><?= vex_icon('tag') ?></span>
              <h3 class="mt-4 font-display font-bold text-lg tracking-tight">Coupons &amp; balance</h3>
              <p class="mt-2 text-[13.5px] leading-relaxed text-[#9aa3ac]">Percentage or gift-product coupons; combined payment with customer balance.</p>
            </article>
          </div>
          <div class="reveal lg:col-span-3" style="--rd:140ms">
            <article class="card card-hover h-full p-6 flex flex-col">
              <span class="feat-ic grid place-items-center w-11 h-11 rounded-xl border border-[#262c33] bg-[#0e1114] text-[#4f8fc0]"><?= vex_icon('headset') ?></span>
              <h3 class="mt-4 font-display font-bold text-lg tracking-tight">Support tickets</h3>
              <p class="mt-2 text-[13.5px] leading-relaxed text-[#9aa3ac]">Signed-in customers open tickets and chat; the team replies by category and priority.</p>
            </article>
          </div>
          <div class="reveal lg:col-span-3" style="--rd:210ms">
            <article class="card card-hover h-full p-6 flex flex-col">
              <span class="feat-ic grid place-items-center w-11 h-11 rounded-xl border border-[#262c33] bg-[#0e1114] text-[#4f8fc0]"><?= vex_icon('users') ?></span>
              <h3 class="mt-4 font-display font-bold text-lg tracking-tight">Role &amp; permission matrix</h3>
              <p class="mt-2 text-[13.5px] leading-relaxed text-[#9aa3ac]">Admin, manager, moderator and support team, scoped by resource permissions.</p>
              <div class="mt-5 rounded-lg border border-[#262c33] bg-[#0e1114] p-3 font-mono2 text-[10.5px]">
                <div class="grid grid-cols-[1fr_repeat(4,2.2rem)] gap-y-1.5 items-center">
                  <span></span><span class="text-center text-[#7d858e]">product</span><span class="text-center text-[#7d858e]">vault</span><span class="text-center text-[#7d858e]">tickets</span><span class="text-center text-[#7d858e]">theme</span>
                  <?php foreach ([['admin',[1,1,1,1]],['manager',[1,1,1,0]],['moderator',[0,0,1,0]],['support',[0,0,1,0]]] as $row): ?>
                    <span class="text-[#9aa3ac]"><?= $row[0] ?></span>
                    <?php foreach ($row[1] as $v): ?><span class="grid place-items-center"><span class="w-2 h-2 rounded-[3px] <?= $v ? 'bg-[#4f8fc0]' : 'bg-[#262c33]' ?>"></span></span><?php endforeach; ?>
                  <?php endforeach; ?>
                </div>
              </div>
            </article>
          </div>
          <div class="reveal lg:col-span-3" style="--rd:280ms">
            <article class="card card-hover h-full p-6 flex flex-col">
              <span class="feat-ic grid place-items-center w-11 h-11 rounded-xl border border-[#262c33] bg-[#0e1114] text-[#4f8fc0]"><?= vex_icon('palette') ?></span>
              <h3 class="mt-4 font-display font-bold text-lg tracking-tight">Themes &amp; integrations</h3>
              <p class="mt-2 text-[13.5px] leading-relaxed text-[#9aa3ac]">default / azure / ember theme set; payments, social login, captcha and live support connect from one screen.</p>
              <div class="mt-5 flex gap-2">
                <?php foreach ([['default','#4f8fc0'],['azure','#4f8fc0'],['ember','#c04f4f']] as $t): ?>
                <span class="flex-1 rounded-lg border border-[#262c33] bg-[#0e1114] p-2.5"><span class="block h-1.5 rounded-full" style="background:<?= $t[1] ?>"></span><span class="mt-2 block font-mono2 text-[10px] text-[#7d858e]"><?= $t[0] ?></span></span>
                <?php endforeach; ?>
              </div>
            </article>
          </div>
          <div class="reveal lg:col-span-3" style="--rd:350ms">
            <article class="card card-hover h-full p-6 flex flex-col">
              <span class="feat-ic grid place-items-center w-11 h-11 rounded-xl border border-[#262c33] bg-[#0e1114] text-[#4f8fc0]"><?= vex_icon('mail') ?></span>
              <h3 class="mt-4 font-display font-bold text-lg tracking-tight">Mail templates &amp; SEO</h3>
              <p class="mt-2 text-[13.5px] leading-relaxed text-[#9aa3ac]">Delivery and verification mails go out with customizable templates; title, description and OG fields live in the panel.</p>
            </article>
          </div>
          <div class="reveal lg:col-span-3" style="--rd:420ms">
            <article class="card card-hover h-full p-6 flex flex-col">
              <span class="feat-ic grid place-items-center w-11 h-11 rounded-xl border border-[#262c33] bg-[#0e1114] text-[#4f8fc0]"><?= vex_icon('coin') ?></span>
              <h3 class="mt-4 font-display font-bold text-lg tracking-tight">Multi-currency</h3>
              <p class="mt-2 text-[13.5px] leading-relaxed text-[#9aa3ac]">Automatic rates with central-bank fallback, stale-rate warnings and limits shown in store currency.</p>
            </article>
          </div>
        </div>
        <div class="reveal mt-8 text-center">
          <a href="/features" class="btn-ghost inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold">Explore all features <?= vex_icon('arrow','w-4 h-4') ?></a>
        </div>
      </div>
    </section>

    <!-- ============ ÖDEMELER ============ -->
    <section id="payments" class="relative py-16 md:py-20 border-t border-[#262c33] scroll-mt-24">
      <div class="max-w-7xl mx-auto px-5 sm:px-8">
        <div class="reveal text-center max-w-2xl mx-auto mb-12 md:mb-16">
          <div class="font-mono2 text-[12px] uppercase tracking-[0.2em] text-[#4f8fc0]">Payment infrastructure</div>
          <h2 class="mt-3 font-display font-bold tracking-tight text-3xl sm:text-4xl lg:text-[2.9rem] leading-[1.05]">Secure Payment Options</h2>
          <p class="mt-4 text-[15px] leading-relaxed text-[#9aa3ac]">Offer your customers the most convenient payment method with <?= count($PAYMENTS) ?> payment and sales integrations.</p>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
           <?php $PAY_LOGOS = ['shopier'=>['shopier.png.webp',1182,343], 'paytr'=>['paytr-blue.png.webp',820,146], 'iyzico'=>['iyzico.png.webp',820,356], 'stripe'=>['stripe.png.webp',800,380], 'paypal'=>['paypal.png.webp',800,208], 'bitpay'=>['bitpay.png.webp',800,281], 'cryptomus'=>['cryptomus.png.webp',820,922], 'paywant'=>['paywant.png.webp',447,447], 'whop'=>['whop.jpg.webp',400,400], 'dodo'=>['dodopayments.jpg.webp',400,400], 'paddle'=>['paddle.jpg.webp',400,400], 'lemon'=>['lemonsqueezy.jpg.webp',400,400]]; ?>
          <?php foreach ($PAYMENTS as $i => $pp): ?>
          <div class="reveal" style="--rd:<?= ($i % 3) * 80 ?>ms">
            <article class="card pay-card h-full p-6 flex items-start gap-4">
              <span class="grid place-items-center w-14 h-14 rounded-xl bg-white/90 ring-1 ring-white/15 p-1.5 shrink-0">
                <img src="./assets/images/<?= $PAY_LOGOS[$pp['id']][0] ?>" alt="<?= htmlspecialchars($pp['name']) ?>" width="<?= $PAY_LOGOS[$pp['id']][1] ?>" height="<?= $PAY_LOGOS[$pp['id']][2] ?>" loading="lazy" decoding="async" draggable="false" class="max-w-full max-h-full object-contain" />
              </span>
              <span class="min-w-0">
                <span class="block font-display font-bold text-[17px] tracking-tight"><?= htmlspecialchars($pp['name']) ?></span>
                <span class="block mt-1 text-[13px] leading-relaxed text-[#9aa3ac]"><?= htmlspecialchars($pp['desc']) ?></span>
              </span>
            </article>
          </div>
          <?php endforeach; ?>
          <a href="/features" class="card pay-card h-full p-6 flex items-center gap-4 border-dashed border-[#39424c] hover:border-[#4f8fc0]/60 transition-colors group">
            <span class="grid place-items-center w-14 h-14 rounded-xl border border-[#262c33] bg-[#0e1114] text-[#4f8fc0] shrink-0">
              <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h15M13 6l6 6-6 6"/></svg>
            </span>
            <span class="min-w-0">
              <span class="block font-display font-bold text-[17px] tracking-tight group-hover:text-white transition-colors">More integrations</span>
              <span class="block mt-1 text-[13px] leading-relaxed text-[#9aa3ac]">Regional methods, crypto and Merchant of Record providers.</span>
            </span>
          </a>
        </div>
      </div>
    </section>

    <!-- ============ GÜVEN ŞERİDİ ============ -->
    <section id="trust" class="relative py-14 md:py-20 border-t border-[#262c33] scroll-mt-24">
      <div class="max-w-7xl mx-auto px-5 sm:px-8">
        <div class="reveal text-center max-w-2xl mx-auto mb-10 md:mb-14">
          <div class="font-mono2 text-[12px] uppercase tracking-[0.2em] text-[#4f8fc0]">Why sellers pick Vexaris</div>
          <h2 class="mt-3 font-display font-bold tracking-tight text-3xl sm:text-4xl lg:text-[2.9rem] leading-[1.05]">Built on facts,<br />not promises.</h2>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <?php
          $TRUST = [
            ['tag','0% commission','No per-sale cut. Keep your margin on every order.'],
            ['bolt','Automatic delivery','Payment approved — file and license key delivered instantly.'],
            ['wallet',count($PAYMENTS) . ' payment integrations','Local and global gateways from a single panel.'],
            ['shield','Cloudflare Turnstile','Bot-protected checkout and role-based admin access.'],
            ['spark','Free plan','Start selling without a card, upgrade when you grow.'],
            ['globe','Custom domain','Your brand, your URL, plus a working subdomain alias.'],
            ['download','Automatic updates','Every previous buyer gets new versions from their dashboard.'],
            ['users','Role &amp; permission matrix','Give your team exactly the access they need.'],
          ];
          foreach ($TRUST as $i => $t):
          ?>
          <div class="reveal" style="--rd:<?= ($i % 4) * 70 ?>ms">
            <article class="card card-hover h-full p-5 flex items-start gap-3.5">
              <span class="feat-ic grid place-items-center w-10 h-10 rounded-xl border border-[#262c33] bg-[#0e1114] text-[#4f8fc0] shrink-0"><?= vex_icon($t[0]) ?></span>
              <span class="min-w-0">
                <span class="block font-display font-bold text-[15px] tracking-tight"><?= $t[1] ?></span>
                <span class="mt-1 block text-[13px] leading-relaxed text-[#9aa3ac]"><?= htmlspecialchars($t[2]) ?></span>
              </span>
            </article>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- ============ BLOG ============ -->
    <?php if ($NEWS): ?>
    <section id="blog" class="relative py-16 md:py-20 border-t border-[#262c33] scroll-mt-24">
      <div class="max-w-7xl mx-auto px-5 sm:px-8">
        <div class="reveal flex flex-col md:flex-row md:items-end md:justify-between gap-5 mb-12 md:mb-16">
          <h2 class="font-display font-bold tracking-tight text-3xl sm:text-4xl lg:text-[2.9rem] leading-[1.05] max-w-2xl">Blog &amp; announcements</h2>
          <div class="md:text-right md:pb-1 md:max-w-sm">
            <p class="text-[15px] leading-relaxed text-[#9aa3ac]">Platform news and announcements — live here the moment they are published from the panel.</p>
            <a href="/blog" class="mt-3 inline-flex items-center gap-2 text-[12.5px] font-bold text-[#c8ccd1] hover:text-[#4f8fc0] transition-colors">All articles <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h15M13 6l6 6-6 6"/></svg></a>
          </div>
        </div>
        <div class="grid md:grid-cols-3 gap-4">
          <?php foreach ($NEWS as $i => $n): ?>
          <div class="reveal" style="--rd:<?= $i * 90 ?>ms">
            <a href="/<?= htmlspecialchars($n['slug']) ?>" class="card card-hover h-full flex flex-col group overflow-hidden">
              <div class="relative aspect-[40/21] overflow-hidden border-b border-[#262c33] bg-[#0e1114]">
                <img src="<?= htmlspecialchars(($n['cover'] ?? '') ?: ('/blog-cover.php?slug=' . rawurlencode((string)$n['slug']))) ?>" alt="<?= htmlspecialchars($n['title']) ?>" width="1200" height="630" loading="lazy" decoding="async" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-[1.03]" />
              </div>
              <div class="p-6 flex flex-col flex-1">
                <time class="font-mono2 text-[11px] text-[#7d858e]"><?= htmlspecialchars($n['date']) ?></time>
                <h3 class="mt-3 font-display font-bold text-lg leading-snug tracking-tight group-hover:text-white transition-colors"><?= htmlspecialchars($n['title']) ?></h3>
                <p class="mt-2.5 text-[13.5px] leading-relaxed text-[#9aa3ac] flex-1"><?= htmlspecialchars($n['desc']) ?></p>
                <span class="mt-5 inline-flex items-center gap-2 text-[12.5px] font-bold text-[#c8ccd1] group-hover:text-[#4f8fc0] transition-colors">Read article <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h15M13 6l6 6-6 6"/></svg></span>
              </div>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- ============ SSS ============ -->
    <section id="faq" class="relative py-16 md:py-20 border-t border-[#262c33] scroll-mt-24">
      <div class="max-w-7xl mx-auto px-5 sm:px-8 grid lg:grid-cols-12 gap-12">
        <div class="lg:col-span-4"><div class="lg:sticky lg:top-32">
          <div class="reveal">
            <h2 class="font-display font-bold tracking-tight text-3xl sm:text-4xl leading-[1.05]">Frequently asked<br />questions</h2>
            <p class="mt-4 text-[14.5px] leading-relaxed text-[#9aa3ac] max-w-xs">If you cannot find what you are looking for, our support team is one ticket away.</p>
            <a href="mailto:info@vexaris.net" class="btn-ghost mt-6 inline-flex items-center gap-2 px-5 py-3 text-sm font-semibold">info@vexaris.net</a>
          </div>
        </div></div>
        <div class="lg:col-span-8 space-y-3">
          <?php foreach ($FAQS as $i => $f): $on = false; ?>
          <div class="reveal" style="--rd:<?= $i * 60 ?>ms">
            <div class="faq-card card overflow-hidden transition-colors duration-300 <?= $on ? 'border-[#4f8fc0]/40' : '' ?>">
              <button data-faq="<?= $i ?>" aria-expanded="<?= $on ? 'true' : 'false' ?>" class="w-full flex items-center justify-between gap-4 px-6 py-5 text-left cursor-pointer group">
                <span class="font-display font-bold text-[15.5px] tracking-tight group-hover:text-[#4f8fc0] transition-colors"><?= htmlspecialchars($f['q']) ?></span>
                <span data-chev class="grid place-items-center w-8 h-8 shrink-0 rounded-lg border transition-all duration-400 <?= $on ? 'border-[#4f8fc0]/50 text-[#4f8fc0] rotate-180' : 'border-[#262c33] text-[#7d858e]' ?>">
                  <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                </span>
              </button>
              <div id="faq-<?= $i ?>" class="acc-body <?= $on ? 'open' : '' ?>"><div><p class="px-6 pb-6 text-[14px] leading-relaxed text-[#9aa3ac] max-w-3xl"><?= htmlspecialchars($f['a']) ?></p></div></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- ============ PAKETLER ============ -->
    <section id="pricing" class="relative py-16 md:py-20 border-t border-[#262c33] scroll-mt-24 overflow-hidden">
      <div class="absolute -right-32 top-10 w-96 h-96 rounded-full bg-[#4f8fc0]/[.07] blur-[110px] mob-noglow" aria-hidden="true"></div>
      <div class="absolute -left-40 bottom-0 w-[28rem] h-[28rem] rounded-full bg-[#4f8fc0]/[.04] blur-[130px] mob-noglow" aria-hidden="true"></div>
      <div class="relative max-w-7xl mx-auto px-5 sm:px-8">
        <div class="reveal text-center max-w-2xl mx-auto mb-10 md:mb-12">
          <div class="font-mono2 text-[12px] uppercase tracking-[0.2em] text-[#4f8fc0]">Pricing</div>
          <h2 class="mt-3 font-display font-extrabold tracking-tight text-3xl sm:text-4xl lg:text-[2.9rem] leading-[1.05]">Simple pricing that<br />scales with you.</h2>
          <p class="mt-4 text-[15px] leading-relaxed text-[#9aa3ac]">Start free, upgrade when you grow. No setup fees, cancel anytime.</p>
          <div class="mt-7 inline-flex items-center gap-1 rounded-full border border-[#262c33] bg-[#12151a] p-1 text-[13px] font-semibold" role="group" aria-label="Billing period">
            <button type="button" data-bill="m" class="bill-btn rounded-full px-5 py-2 transition-all duration-300" aria-pressed="true">Monthly</button>
            <button type="button" data-bill="y" class="bill-btn rounded-full px-5 py-2 transition-all duration-300" aria-pressed="false">Yearly <span class="ml-1 rounded-full bg-[#e0a458]/15 px-2 py-0.5 text-[11px] font-bold text-[#e0a458]">−20%</span></button>
          </div>
        </div>
        <?php require __DIR__ . '/includes/plans.php'; ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 xl:gap-5 items-stretch">
          <?php foreach ($PLANS as $i => $pl): ?>
          <div class="reveal flex flex-col <?= $pl['popular'] ? 'sm:col-span-2 xl:col-span-1' : '' ?>" style="--rd:<?= $i * 80 ?>ms">
            <?php if ($pl['popular']): ?>
            <div class="plan-glow flex-1 rounded-2xl bg-gradient-to-b from-[#e0a458] via-[#e0a458]/30 to-transparent p-px">
              <div class="rounded-t-[15px] bg-gradient-to-r from-[#e0a458] to-[#f0c78e] px-4 py-2 text-center font-mono2 text-[11px] font-bold uppercase tracking-[0.22em] text-[#1a1207]">Most popular</div>
            <?php else: ?>
            <div class="h-[34px]" aria-hidden="true"></div>
            <?php endif; ?>
            <article class="plan-card <?= $pl['popular'] ? 'plan-popular rounded-t-none rounded-b-[15px] border-0 p-6 sm:p-7 flex flex-col flex-1 bg-[#141a20]' : 'card flex-1 p-6 sm:p-7 flex flex-col' ?>">
              <div class="flex items-center gap-3">
                <span class="feat-ic grid place-items-center w-11 h-11 shrink-0 rounded-xl border border-[#262c33] bg-[#0e1114] text-[#4f8fc0]"><?= vex_icon($pl['icon']) ?></span>
                <div>
                  <h3 class="font-display font-bold text-xl tracking-tight leading-none"><?= htmlspecialchars($pl['name']) ?></h3>
                  <p class="mt-1.5 text-[12.5px] leading-snug text-[#9aa3ac]"><?= htmlspecialchars($pl['desc']) ?></p>
                </div>
              </div>
              <div class="mt-5">
                <span class="font-display font-extrabold leading-none tracking-tight text-[2.6rem] <?= $pl['popular'] ? 'plan-price-hot' : '' ?>" data-price-m="<?= htmlspecialchars($pl['price_m']) ?>" data-price-y="<?= htmlspecialchars($pl['price_y']) ?>"><?= htmlspecialchars($pl['price_m']) ?></span>
                <span class="font-mono2 text-[11px] uppercase tracking-[0.14em] text-[#7d858e]"> /mo</span>
                <div class="bill-note mt-1.5 text-[12px] text-[#7d858e]" data-bill-m="<?= htmlspecialchars($pl['bill_m']) ?>" data-bill-y="<?= htmlspecialchars($pl['bill_y']) ?>"><?= htmlspecialchars($pl['bill_m']) ?></div>
              </div>
              <ul class="mt-5 flex-1 space-y-0 rounded-lg border border-[#262c33] bg-[#0e1114]/60 px-4 py-2 text-[13px] leading-relaxed">
                <?php foreach (array_slice($pl['feats'], 0, 5) as $fi => $f): ?>
                <li class="flex items-center justify-between gap-3 <?= $fi > 0 ? 'border-t border-[#1d2228]' : '' ?> py-[7px] min-h-[34px]">
                  <span class="min-w-0 truncate text-[#9aa3ac]"><?= htmlspecialchars($f[0]) ?></span>
                  <?php if ($f[1] === true): ?>
                  <span class="grid place-items-center w-5 h-5 rounded-full bg-[#e0a458]/12 text-[#e0a458]"><?= vex_icon('check','w-3 h-3') ?></span>
                  <?php elseif ($f[1] === false): ?>
                  <span class="text-[#3a434e]"><?= vex_icon('x','w-[18px] h-[18px]') ?></span>
                  <?php elseif ($f[1] === '0%'): ?>
                  <span class="rounded-md bg-[#e0a458]/15 px-2 py-0.5 font-bold text-[#e0a458] text-right">0%</span>
                  <?php else: ?>
                  <span class="rounded-md bg-[#4f8fc0]/10 px-2 py-0.5 font-semibold text-[#cfe3f2] text-right"><?= htmlspecialchars((string)$f[1]) ?></span>
                  <?php endif; ?>
                </li>
                <?php endforeach; ?>
              </ul>
              <?php if ($pl['name'] === 'Free'): ?>
              <a href="<?= $loggedIn ? '/dashboard/subscription' : '/register' ?>" class="mt-6 w-full py-3 text-sm text-center btn-ghost font-semibold"><?= htmlspecialchars($pl['cta']) ?></a>
              <?php elseif ($loggedIn && $whopOn): ?>
              <form method="post" action="/api/plan-checkout" class="mt-6">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
                <input type="hidden" name="plan" value="<?= strtolower($pl['name']) ?>" />
                <input type="hidden" name="cycle" value="monthly" data-cycle />
                <button type="submit" class="w-full py-3 text-sm text-center cursor-pointer <?= $pl['popular'] ? 'btn-primary' : 'btn-ghost font-semibold' ?>"><?= htmlspecialchars($pl['cta']) ?></button>
              </form>
              <?php elseif (!$loggedIn): ?>
              <a href="/login?next=/#pricing" class="mt-6 w-full py-3 text-sm text-center <?= $pl['popular'] ? 'btn-primary' : 'btn-ghost font-semibold' ?>"><?= htmlspecialchars($pl['cta']) ?></a>
              <?php else: ?>
              <span class="mt-6 w-full py-3 text-sm text-center btn-ghost font-semibold opacity-50 cursor-not-allowed" title="Payments are temporarily unavailable">Unavailable</span>
              <?php endif; ?>
            </article>
            <?php if ($pl['popular']): ?>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="reveal mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
          <a href="/pricing" class="btn-ghost inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold">Compare all features in detail <?= vex_icon('arrow','w-4 h-4') ?></a>
        </div>
        <div class="reveal mt-6 flex flex-wrap items-center justify-center gap-x-7 gap-y-2 text-[12.5px] text-[#7d858e]">
          <span class="inline-flex items-center gap-1.5"><span class="text-[#e0a458]"><?= vex_icon('check','w-3.5 h-3.5') ?></span>Cancel anytime</span>
          <span class="inline-flex items-center gap-1.5"><span class="text-[#e0a458]"><?= vex_icon('check','w-3.5 h-3.5') ?></span>No setup fees</span>
          <span class="inline-flex items-center gap-1.5"><span class="text-[#e0a458]"><?= vex_icon('check','w-3.5 h-3.5') ?></span>Upgrade or downgrade anytime</span>
        </div>
        <p class="mt-4 text-center text-[12px] leading-relaxed text-[#5b636b]">* Unlimited traffic is subject to a fair-use policy. Prices in USD.</p>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/footer.php'; ?>

  <?php include __DIR__ . '/includes/modals.php'; ?>
</div>

<script>
window.VEX_LEGAL = <?= json_encode($LEGAL, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="./assets/js/index.js?v=<?= filemtime(__DIR__ . '/assets/js/index.js') ?>"></script>
<script>
(function () {
  var btns = Array.prototype.slice.call(document.querySelectorAll('.bill-btn'));
  if (!btns.length) return;
  function setBill(mode) {
    btns.forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-bill') === mode ? 'true' : 'false'); });
    Array.prototype.forEach.call(document.querySelectorAll('[data-price-m]'), function (el) {
      el.textContent = mode === 'y' ? el.getAttribute('data-price-y') : el.getAttribute('data-price-m');
    });
    Array.prototype.forEach.call(document.querySelectorAll('[data-bill-m]'), function (el) {
      el.textContent = mode === 'y' ? el.getAttribute('data-bill-y') : el.getAttribute('data-bill-m');
    });
    Array.prototype.forEach.call(document.querySelectorAll('input[data-cycle]'), function (el) {
      el.value = mode === 'y' ? 'yearly' : 'monthly';
    });
  }
  btns.forEach(function (b) { b.addEventListener('click', function () { setBill(b.getAttribute('data-bill')); }); });
  setBill('m');
})();
</script>
</body>
</html>

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
$VEX_DASH = './assets/images/dashboard.png';
// Not: footer linkleri tek kaynaktan gelir (includes/data.php).

$PAYMENTS = [
  ['id'=>'shopier','name'=>'Shopier','tag'=>'Local','desc'=>"Turkey's local payment solution",'detail'=>'The fastest start for individual and business sellers. The delivery chain triggers the moment the payment approval webhook arrives.','features'=>['3D Secure','Webhook approval','Same-day delivery trigger'],'icon'=>'wallet'],
  ['id'=>'paytr','name'=>'PayTR','tag'=>'Virtual POS','desc'=>'Virtual POS and installment payments','detail'=>'Grow average cart value with installment options and bank campaigns; delivery starts automatically on authorization.','features'=>['Installments','Bank campaigns','Instant authorization'],'icon'=>'shield'],
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
  ['name'=>'Shopier','file'=>'shopier.png'],
  ['name'=>'PayTR','file'=>'paytr.png'],
  ['name'=>'Paywant','file'=>'paywant.png'],
  ['name'=>'iyzico','file'=>'iyzico.png'],
  ['name'=>'Stripe','file'=>'stripe.png'],
  ['name'=>'PayPal','file'=>'paypal.png'],
  ['name'=>'BitPay','file'=>'bitpay.png'],
  ['name'=>'Cryptomus','file'=>'cryptomus.png'],
  ['name'=>'Whop','file'=>'whop.jpg'],
  ['name'=>'Dodo Payments','file'=>'dodopayments.jpg'],
  ['name'=>'Paddle','file'=>'paddle.jpg'],
  ['name'=>'Lemon Squeezy','file'=>'lemonsqueezy.jpg'],
  ['name'=>'Google Auth','file'=>'google.png'],
  ['name'=>'Discord Auth','file'=>'discord.png'],
  ['name'=>'Microsoft Auth','file'=>'microsoft.png'],
  ['name'=>'GitHub Auth','file'=>'github.png','chip'=>true],
  ['name'=>'Cloudflare Turnstile','file'=>'cloudflare.png'],
  ['name'=>'reCAPTCHA','file'=>'recaptcha.png'],
  ['name'=>'Tawk.to','file'=>'tawkto.png'],
  ['name'=>'Crisp','file'=>'crispchat.png'],
  ['name'=>'JivoChat','file'=>'jivochat.png'],
  ['name'=>'Google Analytics','file'=>null],
];
$STEPS = [
  ['n'=>'01','title'=>'Publish your product','desc'=>'Upload your file or paste one-per-line e-pins / license content. Price, discount, category, and ordering in a single form.','meta'=>'zip / rar / 7z / jar • product_stocks'],
  ['n'=>'02','title'=>'Customer checks out','desc'=>'The customer finds the product, adds it to the cart, applies a coupon, and pays by balance or card. Turnstile verifies silently.','meta'=>'coupons + balance + 7 payment providers'],
  ['n'=>'03','title'=>'Let the system deliver','desc'=>'The second payment is approved, a line is pulled from stock, the license key is signed, and the file and key land in "My Purchases".','meta'=>'average delivery time 0.4s'],
  ['n'=>'04','title'=>'Support and updates','desc'=>'If a customer gets stuck, they open a ticket; when you release a new version, every previous buyer gets the updated file from their download list.','meta'=>'tickets • version tracking • deferrals'],
];
$TESTIMONIALS = [
  ['quote'=>'I actively use the service myself; the team is genuinely attentive and implements every suggestion. Highly recommended.','name'=>'Vixians','role'=>'VProject Owner','ini'=>'VX'],
  ['quote'=>'They helped me end to end during my website setup. We put in a lot of work, but the result turned out excellent. The site design is top quality and the admin panel is so organized that nothing is confusing — I find everything instantly. Many thanks to the Vexaris team for their support.','name'=>'Roda','role'=>'Owner of dcevin.com.tr','ini'=>'RO'],
  ['quote'=>'Real customer reviews will be listed here.','name'=>'Sample User','role'=>'Review pending','ini'=>'SU'],
  ['quote'=>'Real customer reviews will be listed here.','name'=>'Sample User','role'=>'Review pending','ini'=>'SU'],
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
            $NEWS[] = ['date'=>$d,'tag'=>'Blog','slug'=>$r['slug'],'title'=>$r['title'],'desc'=>$desc];
        }
    }
} catch (Throwable $e) { $NEWS = []; }
$FAQS = [
  ['q'=>'What kinds of products can I sell with Vexaris?','a'=>'You can sell any kind of digital product, including e-pins, e-books, license keys, software packages, Minecraft plugins and server setups. For file-based products you upload zip/rar/7z/jar; for code-based products you enter one e-pin or key per line and the system delivers them in order.'],
  ['q'=>'How secure are payments?','a'=>'Card details are never written to Vexaris servers; all charges run through Shopier, PayTR, iyzico, Stripe, PayPal, BitPay or Cryptomus. Forms are verified with Cloudflare Turnstile or reCAPTCHA, and the admin panel is protected by a role-based permission matrix.'],
  ['q'=>'How are sold products delivered?','a'=>'The moment the approval webhook arrives from the payment provider, the system pulls a line from stock, signs the license key, and adds the file to the customer\'s “My Purchases” area. Customers download from the panel or copy the key in one click; the process averages 0.4 seconds.'],
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
    $out .= '<img src="./assets/images/' . htmlspecialchars($l['file']) . '" alt="' . $name . '" loading="lazy" draggable="false" class="h-6 w-auto max-w-[110px] object-contain grayscale opacity-60 group-hover:grayscale-0 group-hover:opacity-100 group-hover:scale-110 transition-all duration-300 select-none' . $chip . '" />';
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
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" />
  <link rel="icon" type="image/png" href="./assets/images/vexaris-logo.png" />
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <noscript><style>.reveal{opacity:1 !important;transform:none !important;}.line-mask>span{transform:none !important;}</style></noscript>
  <link rel="stylesheet" href="./assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>" />
</head>
<body>
<div class="relative min-h-screen bg-[#0b0d10] text-[#e9e7e2]">
  <div class="noise-overlay" aria-hidden="true"></div>

  <?php include __DIR__ . '/header.php'; ?>

  <main>
    <!-- ============ HERO ============ -->
    <section id="top" class="relative overflow-hidden pt-28 md:pt-40 pb-0">
      <div class="absolute inset-0 dotgrid opacity-60 [mask-image:radial-gradient(70%_60%_at_50%_20%,#000_20%,transparent_100%)]"></div>
      <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[900px] h-[500px] rounded-full bg-[#4f8fc0]/[.06] blur-[120px]"></div>
      <div class="relative max-w-7xl mx-auto px-5 sm:px-8">
        <div class="grid lg:grid-cols-12 gap-8 lg:gap-10 items-center">
          <div class="lg:col-span-6">
            <h1 class="font-display font-extrabold tracking-tight text-[2.1rem] sm:text-6xl xl:text-[4.2rem] leading-[1.02]">
              <span class="line-mask"><span style="--ld:120ms">Your digital products</span></span>
              <span class="line-mask"><span style="--ld:230ms">delivered the moment</span></span>
              <span class="line-mask"><span style="--ld:340ms" class="text-[#4f8fc0]">they sell.</span></span>
            </h1>
            <div>
              <p class="mt-6 max-w-lg text-[15.5px] leading-relaxed text-[#9aa3ac]">
                Vexaris lets you sell e-pins, e-books, license keys and software packages from a single panel.
                The second payment is approved, files and keys land in customer accounts automatically.
              </p>
            </div>
            <div class="mt-8 flex flex-wrap items-center gap-3">
              <a href="#pricing" class="btn-primary px-6 py-3 text-sm inline-flex items-center gap-2">Get started
                <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h15M13 6l6 6-6 6"/></svg>
              </a>
              <a href="#features" class="btn-ghost px-6 py-3 text-sm font-semibold">Explore features</a>
            </div>
            <div class="mt-8 lg:mt-10 overflow-hidden pt-3 sm:pt-11 pb-1 [mask-image:linear-gradient(to_right,transparent,#000_10%,#000_90%,transparent)]">
              <div id="logoTrack" class="marquee-track items-center">
                <?php for ($dup = 0; $dup < 2; $dup++): ?>
                <div class="flex shrink-0 items-center" <?= $dup === 1 ? 'aria-hidden="true"' : '' ?>>
                  <?php foreach ($LOGOS as $lg): ?><?= vex_logo_item($lg) ?><?php endforeach; ?>
                </div>
                <?php endfor; ?>
              </div>
            </div>
          </div>
          <div class="hidden lg:block relative w-full lg:col-span-6">
            <div class="relative rounded-xl border border-[#262c33] bg-[#12151a] overflow-hidden shadow-[0_40px_80px_-40px_rgba(0,0,0,.9)]">
              <div class="flex items-center gap-3 px-4 py-2.5 border-b border-[#262c33] bg-[#15191e]">
                <div class="flex gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-[#3a434e]"></span><span class="w-2.5 h-2.5 rounded-full bg-[#3a434e]"></span><span class="w-2.5 h-2.5 rounded-full bg-[#4f8fc0]/70"></span></div>
                <div class="flex-1 flex items-center gap-2 rounded-md bg-[#0b0d10] border border-[#262c33] px-3 py-1 text-[11px] font-mono2 text-[#7d858e]">
                  <svg viewBox="0 0 24 24" class="w-3 h-3 text-[#74c091]" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                  demo.vexaris.com/admin
                </div>
                <span class="hidden sm:block text-[10px] font-mono2 uppercase tracking-wider text-[#7d858e]">admin panel</span>
              </div>
              <div class="overflow-hidden bg-[#0e1114] aspect-[1350/704] min-h-[180px] sm:min-h-[220px]">
                <img src="./assets/images/dashboard.png" alt="Vexaris admin panel/dashboard screenshot" width="1350" height="704" fetchpriority="high" decoding="async" style="width:100%;height:auto;display:block" class="w-full h-auto max-w-full block select-none" draggable="false" />
              </div>
              <div class="pointer-events-none absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-[#0b0d10] to-transparent"></div>
            </div>
            <div class="floaty absolute left-3 -bottom-5 sm:-left-8 sm:bottom-8 card px-3.5 sm:px-4 py-2.5 sm:py-3 w-[170px] sm:w-[220px] shadow-[0_24px_50px_-24px_rgba(0,0,0,.9)]">
              <div class="flex items-center gap-2 text-[11px] font-mono2 text-[#74c091]"><span class="pulse-dot w-1.5 h-1.5 rounded-full bg-[#74c091] inline-block"></span>order #4821 delivered</div>
              <div class="mt-1.5 font-mono2 text-[13px] text-[#e9e7e2] tracking-wide">VEX-9K2L-M4PQ</div>
              <div class="mt-1 text-[11px] text-[#7d858e]">NovaChat Plugin • 0.3s</div>
            </div>
            <div class="floaty-2 absolute right-3 -top-5 sm:-right-6 card px-3.5 py-2.5 shadow-[0_24px_50px_-24px_rgba(0,0,0,.9)]">
              <div class="text-[10px] font-mono2 uppercase tracking-wider text-[#7d858e]">vexaris</div>
              <div class="mt-1 flex items-center gap-2">
                <span class="grid place-items-center w-5 h-5 rounded-full bg-[#74c091]/15 text-[#74c091] shrink-0">
                  <svg viewBox="0 0 24 24" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                </span>
                <div class="font-display font-bold text-[15px] tracking-tight text-[#e9e7e2] whitespace-nowrap overflow-hidden w-[200px]"><span id="rotWord" class="inline-block transition-all duration-500">Powerful Infrastructure</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ NASIL ÇALIŞIR ============ -->
    <section id="how-it-works" class="relative pt-16 pb-24 md:py-32 scroll-mt-24">
      <div class="max-w-7xl mx-auto px-5 sm:px-8 grid lg:grid-cols-12 gap-12 lg:gap-16">
        <div class="lg:col-span-5"><div class="lg:sticky lg:top-32">
          <div class="reveal">
            <h2 class="font-display font-bold tracking-tight text-3xl sm:text-4xl lg:text-[2.9rem] leading-[1.05]">Automated delivery<br />in four steps.</h2>
            <p class="mt-5 max-w-md text-[15px] leading-relaxed text-[#9aa3ac]">Sellers enter files or stock lines once; then orders, payments, delivery and support flow as links of one chain. No manual sending, no waiting customers.</p>
            <a href="#features" class="btn-ghost mt-7 inline-flex items-center gap-2 px-5 py-3 text-sm font-semibold">Watch the chain live <?= vex_icon('arrow','w-4 h-4') ?></a>
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
    <section id="features" class="relative py-24 md:py-32 border-t border-[#262c33] scroll-mt-24">
      <div class="max-w-7xl mx-auto px-5 sm:px-8">
        <div class="reveal flex flex-col md:flex-row md:items-end md:justify-between gap-5 mb-12 md:mb-16">
          <h2 class="font-display font-bold tracking-tight text-3xl sm:text-4xl lg:text-[2.9rem] leading-[1.05] max-w-2xl">A store that runs<br />with zero seller effort.</h2>
          <p class="text-[15px] leading-relaxed text-[#9aa3ac] md:max-w-sm md:text-right md:pb-1">Every tab in the admin panel maps to a visible store behavior. These are the six most-used modules.</p>
        </div>
        <div class="grid lg:grid-cols-6 gap-4">
          <div class="reveal lg:col-span-3 lg:row-span-2" style="--rd:0ms">
            <article class="card card-hover h-full p-6 flex flex-col">
              <span class="feat-ic grid place-items-center w-11 h-11 rounded-xl border border-[#262c33] bg-[#0e1114] text-[#4f8fc0]"><?= vex_icon('box') ?></span>
              <h3 class="mt-4 font-display font-bold text-lg tracking-tight">Product &amp; stock management</h3>
              <p class="mt-2 text-[13.5px] leading-relaxed text-[#9aa3ac]">zip/rar/7z/jar files or line-by-line single e-pins and license content. When stock runs out, sales close automatically.</p>
              <div class="mt-5 rounded-lg border border-[#262c33] bg-[#0e1114] overflow-hidden font-mono2 text-[11px]">
                <div class="flex items-center justify-between px-3 py-2 border-b border-[#262c33] text-[#7d858e]"><span>product_stocks</span><span>3/5 lines</span></div>
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
          <div class="reveal lg:col-span-6" style="--rd:350ms">
            <article class="card card-hover h-full p-6 flex flex-col">
              <span class="feat-ic grid place-items-center w-11 h-11 rounded-xl border border-[#262c33] bg-[#0e1114] text-[#4f8fc0]"><?= vex_icon('mail') ?></span>
              <h3 class="mt-4 font-display font-bold text-lg tracking-tight">Mail templates &amp; SEO</h3>
              <p class="mt-2 text-[13.5px] leading-relaxed text-[#9aa3ac]">Delivery and verification mails go out with customizable templates; title, description and OG fields live in the panel.</p>
            </article>
          </div>
        </div>
        <div class="reveal mt-8 text-center">
          <a href="/features" class="btn-ghost inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold">Explore all features <?= vex_icon('arrow','w-4 h-4') ?></a>
        </div>
      </div>
    </section>

    <!-- ============ ÖDEMELER ============ -->
    <section id="payments" class="relative py-24 md:py-32 border-t border-[#262c33] scroll-mt-24">
      <div class="max-w-7xl mx-auto px-5 sm:px-8">
        <div class="reveal text-center max-w-2xl mx-auto mb-12 md:mb-16">
          <div class="font-mono2 text-[12px] uppercase tracking-[0.2em] text-[#4f8fc0]">Payment infrastructure</div>
          <h2 class="mt-3 font-display font-bold tracking-tight text-3xl sm:text-4xl lg:text-[2.9rem] leading-[1.05]">Secure Payment Options</h2>
          <p class="mt-4 text-[15px] leading-relaxed text-[#9aa3ac]">Offer your customers the most convenient payment method with 12 payment and sales integrations.</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
           <?php $PAY_LOGOS = ['shopier'=>'shopier.png','paytr'=>'paytr-blue.png','iyzico'=>'iyzico.png','stripe'=>'stripe.png','paypal'=>'paypal.png','bitpay'=>'bitpay.png','cryptomus'=>'cryptomus.png','paywant'=>'paywant.png','whop'=>'whop.jpg','dodo'=>'dodopayments.jpg','paddle'=>'paddle.jpg','lemon'=>'lemonsqueezy.jpg']; ?>
          <?php foreach ($PAYMENTS as $i => $pp): ?>
          <div class="reveal" style="--rd:<?= ($i % 3) * 80 ?>ms">
            <article class="card pay-card h-full p-6 flex items-start gap-4">
              <span class="grid place-items-center w-14 h-14 rounded-xl bg-white/[.96] p-1.5 shrink-0">
                <img src="./assets/images/<?= $PAY_LOGOS[$pp['id']] ?>" alt="<?= htmlspecialchars($pp['name']) ?>" loading="lazy" draggable="false" class="max-w-full max-h-full object-contain" />
              </span>
              <span class="min-w-0">
                <span class="block font-display font-bold text-[17px] tracking-tight"><?= htmlspecialchars($pp['name']) ?></span>
                <span class="block mt-1 text-[13px] leading-relaxed text-[#9aa3ac]"><?= htmlspecialchars($pp['desc']) ?></span>
              </span>
            </article>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- ============ REFERANSLAR ============ -->
    <section id="testimonials" class="relative py-24 md:py-32 border-t border-[#262c33] scroll-mt-24">
      <div class="max-w-7xl mx-auto px-5 sm:px-8">
        <div class="reveal flex flex-col md:flex-row md:items-end md:justify-between gap-5 mb-12 md:mb-16">
          <h2 class="font-display font-bold tracking-tight text-3xl sm:text-4xl lg:text-[2.9rem] leading-[1.05] max-w-2xl">What do sellers say?</h2>
          <p class="text-[15px] leading-relaxed text-[#9aa3ac] md:max-w-sm md:text-right md:pb-1">Notes from teams selling with Vexaris, from e-pin stores to plugin developers.</p>
        </div>
      </div>
      <div class="reveal relative mt-12 md:mt-16" id="refSlider">
        <div class="overflow-hidden [mask-image:linear-gradient(to_right,transparent,#000_6%,#000_94%,transparent)]">
          <div id="refTrack" class="flex gap-4 w-max py-2 px-2">
            <?php for ($dup = 0; $dup < 2; $dup++): ?>
            <?php foreach ($TESTIMONIALS as $t): ?>
            <article class="ref-card card w-[300px] sm:w-[360px] shrink-0 p-6 flex flex-col min-h-[280px] sm:min-h-[260px]">
              <svg viewBox="0 0 48 36" class="w-6 h-5 text-[#4f8fc0]/50" fill="currentColor" aria-hidden="true"><path d="M0 36V20.8C0 8.9 6.9 1.4 18.4 0l2 5.6c-6.3 1.7-9.7 5.5-10 11.2H20V36H0Zm28 0V20.8C28 8.9 34.9 1.4 46.4 0l2 5.6c-6.3 1.7-9.7 5.5-10 11.2H48V36H28Z"/></svg>
              <p class="mt-4 text-[14px] leading-relaxed text-[#c8ccd1] italic flex-1">“<?= htmlspecialchars($t['quote']) ?>”</p>
              <div class="mt-5 pt-5 border-t border-[#262c33] flex items-center gap-3">
                <span class="grid place-items-center w-10 h-10 rounded-full border border-[#262c33] bg-[#0e1114] font-mono2 text-[12px] text-[#4f8fc0] shrink-0"><?= $t['ini'] ?></span>
                <div class="min-w-0"><div class="text-[13.5px] font-bold truncate"><?= htmlspecialchars($t['name']) ?></div><div class="text-[11.5px] text-[#9aa3ac] truncate"><?= htmlspecialchars($t['role']) ?></div></div>
                <div class="ml-auto flex gap-0.5 shrink-0" aria-label="5 out of 5 stars">
                  <?php for ($s = 0; $s < 5; $s++): ?><svg viewBox="0 0 24 24" class="w-3.5 h-3.5 text-[#4f8fc0]" fill="currentColor"><path d="m12 2.6 2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.5l-5.9 3.1 1.2-6.5L2.5 9.5l6.6-.9Z"/></svg><?php endfor; ?>
                </div>
              </div>
            </article>
            <?php endforeach; ?>
            <?php endfor; ?>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ BLOG ============ -->
    <?php if ($NEWS): ?>
    <section id="blog" class="relative py-24 md:py-32 border-t border-[#262c33] scroll-mt-24">
      <div class="max-w-7xl mx-auto px-5 sm:px-8">
        <div class="reveal flex flex-col md:flex-row md:items-end md:justify-between gap-5 mb-12 md:mb-16">
          <h2 class="font-display font-bold tracking-tight text-3xl sm:text-4xl lg:text-[2.9rem] leading-[1.05] max-w-2xl">Blog &amp; duyurular</h2>
          <p class="text-[15px] leading-relaxed text-[#9aa3ac] md:max-w-sm md:text-right md:pb-1">Platform news and announcements — live here the moment they are published from the panel.</p>
        </div>
        <div class="grid md:grid-cols-3 gap-4">
          <?php foreach ($NEWS as $i => $n): ?>
          <div class="reveal" style="--rd:<?= $i * 90 ?>ms">
            <a href="/blog/<?= htmlspecialchars($n['slug']) ?>" class="card card-hover h-full p-6 flex flex-col group">
              <div class="flex items-center justify-between">
                <time class="font-mono2 text-[11px] text-[#7d858e]"><?= htmlspecialchars($n['date']) ?></time>
                <span class="font-mono2 text-[10px] uppercase tracking-wider rounded-full px-2.5 py-1 border border-[#4f8fc0]/40 text-[#4f8fc0]"><?= htmlspecialchars($n['tag']) ?></span>
              </div>
              <h3 class="mt-4 font-display font-bold text-lg leading-snug tracking-tight"><?= htmlspecialchars($n['title']) ?></h3>
              <p class="mt-2.5 text-[13.5px] leading-relaxed text-[#9aa3ac] flex-1"><?= htmlspecialchars($n['desc']) ?></p>
              <span class="mt-5 inline-flex items-center gap-2 text-[12.5px] font-bold text-[#c8ccd1] group-hover:text-[#4f8fc0] transition-colors">Read article <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h15M13 6l6 6-6 6"/></svg></span>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- ============ SSS ============ -->
    <section id="faq" class="relative py-24 md:py-32 border-t border-[#262c33] scroll-mt-24">
      <div class="max-w-7xl mx-auto px-5 sm:px-8 grid lg:grid-cols-12 gap-12">
        <div class="lg:col-span-4"><div class="lg:sticky lg:top-32">
          <div class="reveal">
            <h2 class="font-display font-bold tracking-tight text-3xl sm:text-4xl leading-[1.05]">Frequently asked<br />questions</h2>
            <p class="mt-4 text-[14.5px] leading-relaxed text-[#9aa3ac] max-w-xs">If you cannot find what you are looking for, our support team is one ticket away.</p>
            <a href="mailto:destek@vexaris.net" class="btn-ghost mt-6 inline-flex items-center gap-2 px-5 py-3 text-sm font-semibold">destek@vexaris.net</a>
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
    <section id="pricing" class="relative py-24 md:py-32 border-t border-[#262c33] scroll-mt-24 overflow-hidden">
      <div class="absolute -right-32 top-10 w-96 h-96 rounded-full bg-[#4f8fc0]/[.07] blur-[110px]" aria-hidden="true"></div>
      <div class="absolute -left-40 bottom-0 w-[28rem] h-[28rem] rounded-full bg-[#4f8fc0]/[.04] blur-[130px]" aria-hidden="true"></div>
      <div class="relative max-w-7xl mx-auto px-5 sm:px-8">
        <div class="reveal text-center max-w-2xl mx-auto mb-10 md:mb-12">
          <div class="font-mono2 text-[12px] uppercase tracking-[0.2em] text-[#4f8fc0]">Pricing</div>
          <h2 class="mt-3 font-display font-extrabold tracking-tight text-3xl sm:text-4xl lg:text-[2.9rem] leading-[1.05]">Simple pricing that<br />scales with you.</h2>
          <p class="mt-4 text-[15px] leading-relaxed text-[#9aa3ac]">Start free, upgrade when you grow. No setup fees, cancel anytime.</p>
          <div class="mt-7 inline-flex items-center gap-1 rounded-full border border-[#262c33] bg-[#12151a] p-1 text-[13px] font-semibold" role="group" aria-label="Billing period">
            <button type="button" data-bill="m" class="bill-btn rounded-full px-5 py-2 transition-all duration-300" aria-pressed="true">Monthly</button>
            <button type="button" data-bill="y" class="bill-btn rounded-full px-5 py-2 transition-all duration-300" aria-pressed="false">Yearly <span class="ml-1 rounded-full bg-[#3ddc84]/15 px-2 py-0.5 text-[11px] font-bold text-[#3ddc84]">−20%</span></button>
          </div>
        </div>
        <?php require __DIR__ . '/includes/plans.php'; ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 xl:gap-5 items-stretch">
          <?php foreach ($PLANS as $i => $pl): ?>
          <div class="reveal <?= $pl['popular'] ? 'sm:col-span-2 xl:col-span-1' : '' ?>" style="--rd:<?= $i * 80 ?>ms">
            <?php if ($pl['popular']): ?>
            <div class="plan-glow h-full rounded-2xl bg-gradient-to-b from-[#4f8fc0] via-[#4f8fc0]/30 to-transparent p-px">
              <div class="rounded-t-[15px] bg-gradient-to-r from-[#4f8fc0] to-[#7fb3d9] px-4 py-2 text-center font-mono2 text-[11px] font-bold uppercase tracking-[0.22em] text-[#0b0d10]">Most popular</div>
            <?php endif; ?>
            <article class="plan-card <?= $pl['popular'] ? 'plan-popular rounded-t-none rounded-b-[15px] border-0 p-6 sm:p-7 flex flex-col flex-1 bg-[#141a20]' : 'card h-full p-6 sm:p-7 flex flex-col' ?>">
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
                <?php foreach ($pl['feats'] as $fi => $f): ?>
                <li class="flex items-center justify-between gap-3 <?= $fi > 0 ? 'border-t border-[#1d2228]' : '' ?> py-[7px]">
                  <span class="text-[#9aa3ac]"><?= htmlspecialchars($f[0]) ?></span>
                  <?php if ($f[1] === true): ?>
                  <span class="grid place-items-center w-5 h-5 rounded-full bg-[#3ddc84]/12 text-[#3ddc84]"><?= vex_icon('check','w-3 h-3') ?></span>
                  <?php elseif ($f[1] === false): ?>
                  <span class="text-[#3a434e]"><?= vex_icon('x','w-[18px] h-[18px]') ?></span>
                  <?php elseif ($f[1] === '0%'): ?>
                  <span class="rounded-md bg-[#3ddc84]/15 px-2 py-0.5 font-bold text-[#3ddc84] text-right">0%</span>
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
          <span class="inline-flex items-center gap-1.5"><span class="text-[#3ddc84]"><?= vex_icon('check','w-3.5 h-3.5') ?></span>Cancel anytime</span>
          <span class="inline-flex items-center gap-1.5"><span class="text-[#3ddc84]"><?= vex_icon('check','w-3.5 h-3.5') ?></span>No setup fees</span>
          <span class="inline-flex items-center gap-1.5"><span class="text-[#3ddc84]"><?= vex_icon('check','w-3.5 h-3.5') ?></span>Upgrade or downgrade anytime</span>
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

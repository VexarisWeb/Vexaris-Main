<?php
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/data.php';
boot_session();
log_pageview('ozellikler');
$loggedIn = is_logged_in();
$cu = current_user();
$GLOBALS['seoKey'] = 'features';
$GLOBALS['seoFallback'] = [
  'title' => 'Features - Vexaris',
  'description' => 'Vexaris features: product and stock management, coupons, automatic delivery, license keys, 7 payment integrations and more.',
];

$BASE = 'index.php';

$FEATS = [
  ['icon'=>'box','title'=>'Product & Stock Management','desc'=>'Unlimited and single stock modes, zip/rar/7z/jar uploads. Sales stop automatically when stock runs out.','keys'=>'product stock single zip file'],
  ['icon'=>'tag','title'=>'Coupons & Campaigns','desc'=>'Percentage or gift-product coupons, usage-limited campaigns.','keys'=>'coupon discount campaign gift'],
  ['icon'=>'bolt','title'=>'Automatic Delivery','desc'=>'Files and keys land in customer libraries the second payment is approved.','keys'=>'delivery automatic instant'],
  ['icon'=>'key','title'=>'License Keys','desc'=>'Per-order signed key generation, version rights and online verification.','keys'=>'license key'],
  ['icon'=>'wallet','title'=>'7 Payment Integrations','desc'=>'Shopier, PayTR, iyzico, Stripe, PayPal, BitPay and Cryptomus from one panel.','keys'=>'payment pos shopier paytr iyzico stripe paypal crypto'],
  ['icon'=>'headset','title'=>'Support Tickets','desc'=>'Categorized priority ticket system, internal messaging and ticket history.','keys'=>'support ticket help'],
  ['icon'=>'spark','title'=>'Customer Reviews','desc'=>'Verified buyer reviews, star ratings and moderation approval.','keys'=>'review star reference'],
  ['icon'=>'users','title'=>'Role & Permission Matrix','desc'=>'Admin, manager and moderator roles; detailed resource-based permissions.','keys'=>'role permission admin staff'],
  ['icon'=>'palette','title'=>'3 Ready Themes','desc'=>'One-click switching between default, azure and ember theme sets.','keys'=>'theme appearance design'],
  ['icon'=>'mail','title'=>'Mail Templates & SMTP','desc'=>'Customizable templates for delivery and verification mails, your own SMTP settings.','keys'=>'mail email smtp template notification'],
  ['icon'=>'globe','title'=>'SEO Management','desc'=>'Page titles, descriptions, OG tags and search-friendly URL structure.','keys'=>'seo google meta url'],
  ['icon'=>'file','title'=>'Blog & Announcements','desc'=>'Release notes and announcements; automatic notifications via Discord webhook.','keys'=>'blog announcement news release'],
  ['icon'=>'book','title'=>'FAQ Management','desc'=>'Accordion FAQ pages, unlimited questions and answers.','keys'=>'faq help question answer'],
  ['icon'=>'shield','title'=>'Security Shield','desc'=>'Turnstile/reCAPTCHA verification, 3D Secure and CSRF protection.','keys'=>'security captcha fraud 3d secure'],
  ['icon'=>'server','title'=>'Version & File Tracking','desc'=>'Product versions, update distribution and buyer download lists.','keys'=>'version file update'],
  ['icon'=>'chart','title'=>'Revenue & Sales Reports','desc'=>'Daily revenue, order counts and stock status reports in the panel.','keys'=>'report revenue statistics sales dashboard'],
  ['icon'=>'lock','title'=>'Membership & Verification','desc'=>'Registration, sign-in, email verification and forgot-password flows.','keys'=>'membership signin signup verification account'],
  ['icon'=>'discord','title'=>'Discord Notifications','desc'=>'Sales and support events land in your Discord channel via webhook.','keys'=>'discord webhook notification'],
  ['icon'=>'copy','title'=>'Category Management','desc'=>'Unlimited categories, drag-and-drop ordering and showcase layout.','keys'=>'category ordering showcase'],
  ['icon'=>'download','title'=>'Secure Download Links','desc'=>'Time-limited, single-use, logged secure file download links.','keys'=>'download link file'],
  ['icon'=>'ticket','title'=>'E-Pin & Code Pools','desc'=>'Bulk-upload thousands of ready codes; delivered in order sequence.','keys'=>'epin code pool bulk'],
  ['icon'=>'clock','title'=>'Maintenance Mode & Scheduling','desc'=>'One-click maintenance mode, announced landing page and timed publishing.','keys'=>'maintenance time'],
  ['icon'=>'coin','title'=>'Balance & Wallet System','desc'=>'Customer balance top-ups, pay-by-balance and refund credits.','keys'=>'balance wallet'],
  ['icon'=>'plugin','title'=>'Easy Setup Wizard','desc'=>'Step-by-step setup, automatic table creation and ready settings.','keys'=>'setup install wizard'],
  ['icon'=>'spark','title'=>'Slider System','desc'=>'Store slider texts, colors and links managed from the panel; visible above the header in all themes.','keys'=>'slider banner showcase announcement'],
  ['icon'=>'server','title'=>'Version History','desc'=>'Product versions archived with release notes; old versions listed, edited and managed.','keys'=>'version history changelog'],
  ['icon'=>'bolt','title'=>'Update Reminders','desc'=>'Unread update badges appear on versions with changed files; cleared on tab entry.','keys'=>'update reminder notification badge'],
];

function feat_search($f) {
  $s = mb_strtolower($f['title'] . ' ' . $f['desc'] . ' ' . $f['keys'], 'UTF-8');
  return str_replace('i̇', 'i', $s); // İ → i eşleşmesi için
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
    <section class="relative overflow-hidden pt-32 md:pt-40 pb-20 md:pb-24">
      <div class="absolute inset-0 dotgrid opacity-60 [mask-image:radial-gradient(70%_60%_at_50%_20%,#000_20%,transparent_100%)]"></div>
      <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[900px] h-[500px] rounded-full bg-[#4f8fc0]/[.06] blur-[120px]"></div>
      <div class="relative max-w-7xl mx-auto px-5 sm:px-8">
        <div class="reveal text-center max-w-2xl mx-auto">
          <div class="font-mono2 text-[12px] uppercase tracking-[0.2em] text-[#4f8fc0]">Features</div>
          <h1 class="mt-3 font-display font-extrabold tracking-tight text-4xl sm:text-5xl lg:text-[3.4rem] leading-[1.05]">Your entire store power,<br />in one panel.</h1>
          <p class="mt-4 text-[15px] leading-relaxed text-[#9aa3ac]">All platform modules are listed below. Find them fast with search.</p>
        </div>

        <div class="reveal mt-8 max-w-xl mx-auto" style="--rd:150ms">
          <div class="relative">
            <svg viewBox="0 0 24 24" class="w-4 h-4 absolute left-4 top-1/2 -translate-y-1/2 text-[#7d858e]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input id="featSearch" type="text" autocomplete="off" placeholder="Search features… (e.g. coupon, theme, ticket)" class="w-full rounded-xl border border-[#262c33] bg-[#12151a] pl-11 pr-4 py-3.5 text-[14px] placeholder:text-[#5b636b] focus:outline-none focus:border-[#4f8fc0]/60 transition-colors" />
          </div>
          <div class="mt-3 text-center font-mono2 text-[11px] text-[#7d858e]"><span id="featCount"></span></div>
        </div>

        <div id="featGrid" class="mt-8 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <?php foreach ($FEATS as $i => $f): ?>
          <div class="reveal" style="--rd:<?= ($i % 3) * 70 ?>ms" data-search="<?= htmlspecialchars(feat_search($f)) ?>">
            <article class="card card-hover h-full p-6 flex flex-col">
              <span class="feat-ic grid place-items-center w-11 h-11 rounded-xl border border-[#262c33] bg-[#0e1114] text-[#4f8fc0]"><?= vex_icon($f['icon']) ?></span>
              <h3 class="mt-4 font-display font-bold text-lg tracking-tight"><?= htmlspecialchars($f['title']) ?></h3>
              <p class="mt-2 text-[13.5px] leading-relaxed text-[#9aa3ac] flex-1"><?= htmlspecialchars($f['desc']) ?></p>
            </article>
          </div>
          <?php endforeach; ?>
        </div>
        <div id="featEmpty" class="hidden text-center py-16">
          <p class="text-[15px] font-bold">No features match your search.</p>
          <p class="mt-2 text-[13px] text-[#7d858e]">Try a different word (e.g. payment, theme, ticket).</p>
        </div>
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
</body>
</html>

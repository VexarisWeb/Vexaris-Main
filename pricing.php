<?php
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/plans.php';
boot_session();
log_pageview('fiyatlandirma');
$loggedIn = is_logged_in();
$whopOn = false;
try { require_once __DIR__ . '/core/whop.php'; $whopOn = whop_payments_enabled(); } catch (Throwable $e) { $whopOn = false; }
$cu = current_user();
$GLOBALS['seoKey'] = 'pricing';
$GLOBALS['seoFallback'] = [
  'title' => 'Pricing - Vexaris',
  'description' => 'Vexaris pricing: Free, Starter ($6.99), Professional ($14.99) and Enterprise ($39.99) plans compared in detail: products, revenue, traffic, themes, API and support.',
];

$BASE = 'index.php';

function cmp_cell($v) {
  if ($v === true) return '<span class="inline-grid place-items-center w-6 h-6 rounded-full bg-[#e0a458]/12 text-[#e0a458]">' . vex_icon('check','w-3.5 h-3.5') . '</span>';
  if ($v === false) return '<span class="inline-block text-[#3a434e]">' . vex_icon('x','w-5 h-5') . '</span>';
  if ($v === '0%') return '<span class="inline-block rounded-md bg-[#e0a458]/15 px-2.5 py-1 text-[13px] font-bold text-[#e0a458]">0%</span>';
  return '<span class="inline-block rounded-md bg-[#4f8fc0]/10 px-2.5 py-1 text-[13px] font-semibold text-[#cfe3f2]">' . htmlspecialchars((string)$v) . '</span>';
}
$faqJson = [];
foreach ($PLAN_FAQS as $f) {
  $faqJson[] = ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]];
}
$offerJson = [];
foreach ($PLANS as $pl) {
  $pslug = $pl['slug'] ?? strtolower($pl['name']);
  $yearTotals = ['starter' => '67.10', 'professional' => '143.90', 'enterprise' => '383.90'];
  foreach (['m' => 'Monthly', 'y' => 'Yearly'] as $ck => $clabel) {
    if ($ck === 'y' && !isset($yearTotals[$pslug])) continue;
    $priceVal = $ck === 'm' ? ltrim($pl['price_m'], '$') : $yearTotals[$pslug];
    $sku = 'vexaris-' . $pslug . '-' . ($ck === 'm' ? 'monthly' : 'yearly');
    $offerJson[] = [
      '@type' => 'Offer',
      'name' => 'Vexaris ' . $pl['name'] . ' (' . $clabel . ')',
      'sku' => $sku,
      'mpn' => $sku,
      'url' => 'https://vexaris.net/pricing',
      'price' => $priceVal,
      'priceCurrency' => 'USD',
      'availability' => 'https://schema.org/InStock',
      'shippingDetails' => [
        '@type' => 'OfferShippingDetails',
        'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => '0', 'currency' => 'USD'],
        'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'US'],
        'deliveryTime' => [
          '@type' => 'ShippingDeliveryTime',
          'handlingTime' => ['@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 0, 'unitCode' => 'HUR'],
          'transitTime' => ['@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 0, 'unitCode' => 'DAY'],
        ],
      ],
      'hasMerchantReturnPolicy' => [
        '@type' => 'MerchantReturnPolicy',
        'applicableCountry' => 'US',
        'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
        'merchantReturnDays' => 14,
        'returnMethod' => 'https://schema.org/MerchantReturnByMail',
        'returnFees' => 'https://schema.org/FreeReturn',
        'merchantReturnLink' => 'https://vexaris.net/terms-of-use',
      ],
    ];
  }
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
  <noscript><style>.reveal{opacity:1 !important;transform:none !important;}.line-mask>span{transform:none !important;}</style></noscript>
  <link rel="stylesheet" href="./assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>" />
  <link rel="stylesheet" href="./assets/css/tailwind.css?v=<?= filemtime(__DIR__ . '/assets/css/tailwind.css') ?>" />
  <script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org','@type' => 'Product','name' => 'Vexaris','description' => 'Digital product sales platform with hosted store plans.','image' => 'https://vexaris.net/assets/images/vexaris-logo.png','brand' => ['@type' => 'Brand','name' => 'Vexaris'],'offers' => $offerJson], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org','@type' => 'FAQPage','mainEntity' => $faqJson], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
</head>
<body>
<div class="relative min-h-screen bg-[#0b0d10] text-[#e9e7e2]">
  <div class="noise-overlay" aria-hidden="true"></div>

  <?php include __DIR__ . '/header.php'; ?>

  <main>
    <section class="relative overflow-hidden pt-32 md:pt-40 pb-16 md:pb-20">
      <div class="absolute inset-0 dotgrid opacity-60 [mask-image:radial-gradient(70%_60%_at_50%_20%,#000_20%,transparent_100%)]"></div>
      <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[900px] h-[500px] rounded-full bg-[#4f8fc0]/[.06] blur-[120px]"></div>
      <div class="relative max-w-7xl mx-auto px-5 sm:px-8">
        <nav aria-label="Breadcrumb" class="reveal">
          <ol class="flex items-center gap-2 font-mono2 text-[11px] uppercase tracking-[0.16em] text-[#7d858e]">
            <li><a href="/" class="hover:text-white transition-colors">Home</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="text-[#4f8fc0]">Pricing</li>
          </ol>
        </nav>
        <div class="reveal text-center max-w-2xl mx-auto mt-6">
          <div class="font-mono2 text-[12px] uppercase tracking-[0.2em] text-[#4f8fc0]">Pricing</div>
          <h1 class="mt-3 font-display font-extrabold tracking-tight text-4xl sm:text-5xl lg:text-[3.4rem] leading-[1.05]">One platform,<br />four plans.</h1>
          <p class="mt-4 text-[15px] leading-relaxed text-[#9aa3ac]">Every limit, feature and support tier, side by side. Start free, upgrade when you grow.</p>
          <div class="mt-7 inline-flex items-center gap-1 rounded-full border border-[#262c33] bg-[#12151a] p-1 text-[13px] font-semibold" role="group" aria-label="Billing period">
            <button type="button" data-bill="m" class="bill-btn rounded-full px-5 py-2 transition-all duration-300" aria-pressed="true">Monthly</button>
            <button type="button" data-bill="y" class="bill-btn rounded-full px-5 py-2 transition-all duration-300" aria-pressed="false">Yearly <span class="ml-1 rounded-full bg-[#e0a458]/15 px-2 py-0.5 text-[11px] font-bold text-[#e0a458]">−20%</span></button>
          </div>
        </div>

        <div class="mt-10 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 xl:gap-5 items-stretch">
          <?php foreach ($PLANS as $i => $pl): ?>
          <div class="reveal" style="--rd:<?= $i * 80 ?>ms">
            <?php if ($pl['popular']): ?>
            <div class="plan-glow h-full rounded-2xl bg-gradient-to-b from-[#e0a458] via-[#e0a458]/30 to-transparent p-px">
              <div class="rounded-t-[15px] bg-gradient-to-r from-[#e0a458] to-[#f0c78e] px-4 py-2 text-center font-mono2 text-[11px] font-bold uppercase tracking-[0.22em] text-[#1a1207]">Most popular</div>
            <?php endif; ?>
            <article class="plan-card <?= $pl['popular'] ? 'plan-popular rounded-t-none rounded-b-[15px] border-0 p-6 flex flex-col flex-1 bg-[#141a20]' : 'card h-full p-6 flex flex-col' ?>">
              <div class="flex items-center gap-3">
                <span class="feat-ic grid place-items-center w-10 h-10 shrink-0 rounded-xl border border-[#262c33] bg-[#0e1114] text-[#4f8fc0]"><?= vex_icon($pl['icon']) ?></span>
                <h2 class="font-display font-bold text-lg tracking-tight leading-none"><?= htmlspecialchars($pl['name']) ?></h2>
              </div>
              <div class="mt-4">
                <span class="font-display font-extrabold leading-none tracking-tight text-4xl <?= $pl['popular'] ? 'plan-price-hot' : '' ?>" data-price-m="<?= htmlspecialchars($pl['price_m']) ?>" data-price-y="<?= htmlspecialchars($pl['price_y']) ?>"><?= htmlspecialchars($pl['price_m']) ?></span>
                <span class="font-mono2 text-[11px] uppercase tracking-[0.14em] text-[#7d858e]"> /mo</span>
                <div class="bill-note mt-1.5 text-[12px] text-[#7d858e]" data-bill-m="<?= htmlspecialchars($pl['bill_m']) ?>" data-bill-y="<?= htmlspecialchars($pl['bill_y']) ?>"><?= htmlspecialchars($pl['bill_m']) ?></div>
              </div>
              <?php if ($pl['name'] === 'Free'): ?>
              <a href="<?= $loggedIn ? '/dashboard/subscription' : '/register' ?>" class="mt-5 w-full py-2.5 text-sm text-center btn-ghost font-semibold"><?= htmlspecialchars($pl['cta']) ?></a>
              <?php elseif ($loggedIn && $whopOn): ?>
              <form method="post" action="/api/plan-checkout" class="mt-5">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
                <input type="hidden" name="plan" value="<?= strtolower($pl['name']) ?>" />
                <input type="hidden" name="cycle" value="monthly" data-cycle />
                <button type="submit" class="w-full py-2.5 text-sm text-center cursor-pointer <?= $pl['popular'] ? 'btn-primary' : 'btn-ghost font-semibold' ?>"><?= htmlspecialchars($pl['cta']) ?></button>
              </form>
              <?php elseif (!$loggedIn): ?>
              <a href="/login?next=/pricing" class="mt-5 w-full py-2.5 text-sm text-center <?= $pl['popular'] ? 'btn-primary' : 'btn-ghost font-semibold' ?>"><?= htmlspecialchars($pl['cta']) ?></a>
              <?php else: ?>
              <span class="mt-5 w-full py-2.5 text-sm text-center btn-ghost font-semibold opacity-50 cursor-not-allowed" title="Payments are temporarily unavailable">Unavailable</span>
              <?php endif; ?>
            </article>
            <?php if ($pl['popular']): ?>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="relative py-10 md:py-14 border-t border-[#262c33]" aria-label="Detailed plan comparison">
      <div class="max-w-7xl mx-auto px-5 sm:px-8">
        <div class="reveal flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-8">
          <h2 class="font-display font-bold tracking-tight text-2xl sm:text-3xl leading-tight">Compare every feature.</h2>
          <p class="text-[13.5px] leading-relaxed text-[#9aa3ac] md:max-w-sm md:text-right">All limits and modules in one table. Scroll sideways on mobile.</p>
        </div>
        <div class="reveal card overflow-x-auto p-0">
          <table class="cmp-table w-full min-w-[760px] border-collapse text-left text-[13.5px]">
            <caption class="sr-only">Vexaris plan comparison: Free, Starter, Professional and Enterprise</caption>
            <thead>
              <tr class="border-b border-[#262c33]">
                <th scope="col" class="sticky left-0 bg-[#141a20] px-5 py-4 font-mono2 text-[11px] uppercase tracking-[0.16em] text-[#7d858e]">Feature</th>
                <?php foreach ($PLANS as $pl): ?>
                <th scope="col" class="px-4 py-4 text-center min-w-[130px]">
                  <span class="block font-display font-bold text-[15px] tracking-tight"><?= htmlspecialchars($pl['name']) ?><?= $pl['popular'] ? ' <span class="ml-1 rounded-full bg-[#4f8fc0]/15 px-2 py-0.5 font-mono2 text-[10px] uppercase tracking-wider text-[#7fb3d9]">Popular</span>' : '' ?></span>
                  <span class="mt-1 block text-[13px] font-bold text-[#e6ebef]"><span data-price-m="<?= htmlspecialchars($pl['price_m']) ?>" data-price-y="<?= htmlspecialchars($pl['price_y']) ?>"><?= htmlspecialchars($pl['price_m']) ?></span><span class="font-mono2 text-[10px] font-normal text-[#7d858e]"> /mo</span></span>
                </th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <?php foreach ($PLAN_GROUPS as $gname => $labels): ?>
            <tbody>
              <tr>
                <th scope="rowgroup" colspan="5" class="bg-[#0e1114] px-5 py-2.5 font-mono2 text-[11px] uppercase tracking-[0.18em] text-[#4f8fc0]"><?= htmlspecialchars($gname) ?></th>
              </tr>
              <?php foreach ($labels as $li => $label): ?>
              <tr class="<?= $li < count($labels) - 1 ? 'border-b border-[#1d2228]' : 'border-b border-[#262c33]' ?>">
                <th scope="row" class="sticky left-0 bg-[#141a20] px-5 py-3 font-normal text-[#9aa3ac]"><?= htmlspecialchars($label) ?></th>
                <?php foreach ($PLANS as $pl): ?>
                <td class="px-4 py-3 text-center"><?= cmp_cell(plan_feat_val($pl, $label)) ?></td>
                <?php endforeach; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <?php endforeach; ?>
          </table>
        </div>
        <p class="mt-4 text-[12px] leading-relaxed text-[#5b636b]">* Unlimited traffic is subject to a fair-use policy against abusive patterns such as bot floods. Prices in USD.</p>
        <div class="mt-6 flex flex-wrap items-center justify-center gap-x-7 gap-y-2 text-[12.5px] text-[#7d858e]">
          <span class="inline-flex items-center gap-1.5"><span class="text-[#e0a458]"><?= vex_icon('check','w-3.5 h-3.5') ?></span>Cancel anytime</span>
          <span class="inline-flex items-center gap-1.5"><span class="text-[#e0a458]"><?= vex_icon('check','w-3.5 h-3.5') ?></span>No setup fees</span>
          <span class="inline-flex items-center gap-1.5"><span class="text-[#e0a458]"><?= vex_icon('check','w-3.5 h-3.5') ?></span>Upgrade or downgrade anytime</span>
        </div>
      </div>
    </section>

    <section class="relative pb-4" aria-label="Platform comparisons">
      <div class="max-w-3xl mx-auto px-5 sm:px-8 text-center">
        <p class="text-[13.5px] leading-relaxed text-[#7d858e]">
          Comparing platforms before you commit?
          <a class="underline hover:text-[#e6ebef] transition-colors" href="/gumroad/alternative-2026">Gumroad</a> ·
          <a class="underline hover:text-[#e6ebef] transition-colors" href="/sellfy/alternatives-2026">Sellfy</a> ·
          <a class="underline hover:text-[#e6ebef] transition-colors" href="/etsy/alternative">Etsy</a>:
          each fee breakdown is costed line by line.
        </p>
      </div>
    </section>

    <section class="relative py-16 md:py-24 border-t border-[#262c33]" aria-label="Pricing questions">
      <div class="max-w-3xl mx-auto px-5 sm:px-8">
        <div class="reveal text-center mb-10">
          <div class="font-mono2 text-[12px] uppercase tracking-[0.2em] text-[#4f8fc0]">FAQ</div>
          <h2 class="mt-3 font-display font-bold tracking-tight text-2xl sm:text-3xl">Pricing questions, answered.</h2>
        </div>
        <div class="space-y-3">
          <?php foreach ($PLAN_FAQS as $i => $f): $on = $i === 0; ?>
          <div class="reveal" style="--rd:<?= $i * 60 ?>ms">
            <div class="faq-card card overflow-hidden transition-colors duration-300 <?= $on ? 'border-[#4f8fc0]/40' : '' ?>">
              <button data-faq="<?= $i ?>" aria-expanded="<?= $on ? 'true' : 'false' ?>" class="w-full flex items-center justify-between gap-4 px-6 py-5 text-left cursor-pointer group">
                <span class="font-display font-bold text-[16px] tracking-tight group-hover:text-white transition-colors"><?= htmlspecialchars($f['q']) ?></span>
                <span data-chev class="grid place-items-center w-8 h-8 shrink-0 rounded-lg border border-[#262c33] text-[#7d858e] transition-transform duration-300"><?= vex_icon('chev','w-4 h-4') ?></span>
              </button>
              <div id="faq-<?= $i ?>" class="acc-body <?= $on ? 'open' : '' ?>"><div><p class="px-6 pb-6 text-[14px] leading-relaxed text-[#9aa3ac] max-w-3xl"><?= htmlspecialchars($f['a']) ?></p></div></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="reveal mt-12 card p-8 sm:p-10 text-center relative overflow-hidden">
          <div class="absolute -top-24 left-1/2 -translate-x-1/2 w-[500px] h-[240px] rounded-full bg-[#4f8fc0]/[.08] blur-[90px]" aria-hidden="true"></div>
          <h2 class="relative font-display font-extrabold tracking-tight text-2xl sm:text-3xl">Still deciding? Start free.</h2>
          <p class="relative mt-3 text-[14px] leading-relaxed text-[#9aa3ac] max-w-xl mx-auto">Launch your store on the Free plan with hosting and subdomain included. Upgrade only when your revenue says so.</p>
          <div class="relative mt-6 flex flex-wrap justify-center gap-3">
            <a href="/register" class="btn-primary px-7 py-3 text-sm inline-flex items-center gap-2">Get started <?= vex_icon('arrow','w-4 h-4') ?></a>
            <a href="/features" class="btn-ghost px-7 py-3 text-sm font-semibold">Explore features</a>
          </div>
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

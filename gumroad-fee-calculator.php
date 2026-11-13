<?php
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/data.php';
boot_session();
log_pageview('gumroad_hesaplayici');
$loggedIn = is_logged_in();
$GLOBALS['seoKey'] = 'gumroad-fee-calculator';
$GLOBALS['seoFallback'] = [
  'title' => 'Gumroad Fee Calculator 2026: What You Keep Per Sale',
  'description' => 'Enter a price and see exactly what Gumroad takes with 10% + 50c. Compare Payhip, Ko-fi, Sellfy, Whop and Vexaris on the same numbers. Fees verified October 2026.',
];
$BASE = '/';

if (!function_exists('e')) {
  function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$GF_PLATFORMS = [
  ['id' => 'gumroad',   'name' => 'Gumroad (direct)',   'model' => '10% + $0.50',      'pct' => 0.10, 'fixed' => 0.50, 'monthly' => 0,   'gw' => false, 'mor' => true],
  ['id' => 'discover',  'name' => 'Gumroad Discover',   'model' => '30%',              'pct' => 0.30, 'fixed' => 0.00, 'monthly' => 0,   'gw' => false, 'mor' => true],
  ['id' => 'payhip',    'name' => 'Payhip',             'model' => '5%',               'pct' => 0.05, 'fixed' => 0.00, 'monthly' => 0,   'gw' => false, 'mor' => true],
  ['id' => 'kofi',      'name' => 'Ko-fi (free)',       'model' => '5%',               'pct' => 0.05, 'fixed' => 0.00, 'monthly' => 0,   'gw' => false, 'mor' => true],
  ['id' => 'kofigold',  'name' => 'Ko-fi Gold',         'model' => '0% + $12/mo',      'pct' => 0.00, 'fixed' => 0.00, 'monthly' => 12,  'gw' => true,  'mor' => false],
  ['id' => 'sellfy',    'name' => 'Sellfy',             'model' => '0% + $29/mo',      'pct' => 0.00, 'fixed' => 0.00, 'monthly' => 29,  'gw' => true,  'mor' => false],
  ['id' => 'lemon',     'name' => 'Lemon Squeezy',      'model' => '5% + $0.50',       'pct' => 0.05, 'fixed' => 0.50, 'monthly' => 0,   'gw' => false, 'mor' => true],
  ['id' => 'whop',      'name' => 'Whop (direct)',      'model' => '3%',               'pct' => 0.03, 'fixed' => 0.00, 'monthly' => 0,   'gw' => true,  'mor' => false],
  ['id' => 'vexaris',   'name' => 'Vexaris',            'model' => '0% platform',      'pct' => 0.00, 'fixed' => 0.00, 'monthly' => 0,   'gw' => true,  'mor' => false],
];
$GF_GW_PCT = 0.029;
$GF_GW_FIXED = 0.30;
$GF_DEFAULT_PRICE = 20.0;
$GF_DEFAULT_UNITS = 100;

$gfCalc = function (array $p, float $price, int $units) use ($GF_GW_PCT, $GF_GW_FIXED): array {
  $perSale = $price * $p['pct'] + $p['fixed'];
  if ($p['gw']) { $perSale += $price * $GF_GW_PCT + $GF_GW_FIXED; }
  $monthlyCost = $perSale * $units + $p['monthly'];
  $gross = $price * $units;
  return [
    'per_sale' => $perSale,
    'monthly' => $monthlyCost,
    'keep' => $gross - $monthlyCost,
    'rate' => $price > 0 ? ($perSale / $price) * 100 : 0.0,
  ];
};

$gfRows = [];
foreach ($GF_PLATFORMS as $p) {
  $gfRows[] = ['p' => $p, 'c' => $gfCalc($p, $GF_DEFAULT_PRICE, $GF_DEFAULT_UNITS)];
}
usort($gfRows, function ($a, $b) { return $b['c']['keep'] <=> $a['c']['keep']; });

$gfFaq = [
  ['q' => 'How much does Gumroad take per sale in 2026?', 'a' => 'Gumroad charges 10% plus $0.50 per transaction on sales from your own traffic, and 30% on sales that arrive through Gumroad Discover. There is no monthly fee. On a $20 sale the fee is $2.50, which is an effective rate of 12.5%. On a $5 sale it is $1.00, or 20%.'],
  ['q' => 'Why does the effective rate go up on cheap products?', 'a' => 'Because the $0.50 is fixed. It is the same on a $2 sale as on a $200 sale, so small prices carry a much larger share of the fee. This is the main reason sellers of cheap items (game keys, e-pins, $3 templates) look for a percentage only platform.'],
  ['q' => 'Does Gumroad handle VAT and sales tax?', 'a' => 'Yes. Gumroad acts as merchant of record, which means it calculates, collects and remits EU VAT, UK VAT and US sales tax on applicable transactions. That service is part of what the 10% pays for. Compare a flat plan against a flat plan plus your own tax handling, not against a bare payment gateway invoice.'],
  ['q' => 'When is a flat monthly plan cheaper than Gumroad?', 'a' => 'It depends on your volume and price. The calculator on this page shows the exact crossover for each platform with your own numbers. As a rule of thumb, above roughly $500 to $1,000 in monthly sales a flat plan with 0% commission costs less, but the fixed monthly fee still has to be paid in a quiet month.'],
  ['q' => 'Are these fees verified?', 'a' => 'The platform percentages were checked against each provider live pricing page in October 2026 and they are the same numbers used in our Gumroad alternatives comparison. Payment processing of about 2.9% plus $0.30 is added only for platforms that do not act as merchant of record, because there it is charged separately.'],
];
$gfFaqJson = [];
foreach ($gfFaq as $f) {
  $gfFaqJson[] = ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]];
}
$pageJson = [
  '@context' => 'https://schema.org',
  '@type' => 'WebApplication',
  'name' => 'Gumroad Fee Calculator',
  'description' => 'Calculate what Gumroad takes per sale with 10% + $0.50 and compare the same sale on Payhip, Ko-fi, Sellfy, Whop and Vexaris.',
  'url' => 'https://vexaris.net/gumroad-fee-calculator',
  'applicationCategory' => 'FinanceApplication',
  'operatingSystem' => 'Any',
  'browserRequirements' => 'Requires JavaScript for live updates, works without it',
  'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
  'isPartOf' => ['@type' => 'WebSite', 'name' => 'Vexaris', 'url' => 'https://vexaris.net/'],
];
$crumbJson = [
  '@context' => 'https://schema.org',
  '@type' => 'BreadcrumbList',
  'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://vexaris.net/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Gumroad fee calculator', 'item' => 'https://vexaris.net/gumroad-fee-calculator'],
  ],
];
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
  <link rel="stylesheet" href="./assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>" />
  <link rel="stylesheet" href="./assets/css/tailwind.css?v=<?= filemtime(__DIR__ . '/assets/css/tailwind.css') ?>" />
  <script type="application/ld+json"><?= json_encode($pageJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <script type="application/ld+json"><?= json_encode($crumbJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $gfFaqJson], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <style>
    .gfc-page { --gfc-accent:#4f8fc0; --gfc-accent-2:#7fb3d9; --gfc-glow:rgba(79,143,192,.12); --gfc-ok:#74c091; --gfc-bad:#e0735c; background:#0b0d10; color:var(--text); min-height:100vh; }
    .gfc-wrap { width:100%; max-width:74rem; margin:0 auto; padding:0 1.25rem; }
    @media (min-width:640px) { .gfc-wrap { padding:0 2rem; } }
    .gfc-hero { padding:7rem 0 2.5rem; }
    @media (min-width:768px) { .gfc-hero { padding:8.5rem 0 3.5rem; } }
    .gfc-eyebrow { display:inline-flex; align-items:center; gap:.5rem; font-family:var(--font-mono); font-size:11px; letter-spacing:.15em; text-transform:uppercase; color:var(--gfc-accent-2); border:1px solid rgba(79,143,192,.3); background:rgba(79,143,192,.08); border-radius:999px; padding:.4rem .85rem; }
    .gfc-h1 { font-family:var(--font-display); font-weight:800; letter-spacing:-.035em; line-height:1.04; font-size:clamp(2rem,4.4vw,3.25rem); margin:1.1rem 0 0; color:#f4f6f5; }
    .gfc-lead { color:var(--muted); font-size:15px; line-height:1.7; margin-top:1rem; max-width:40rem; }
    .gfc-tool { display:grid; gap:1px; background:var(--line); border:1px solid var(--line); border-radius:16px; overflow:hidden; margin-top:2rem; }
    @media (min-width:900px) { .gfc-tool { grid-template-columns:minmax(0,.95fr) minmax(0,1.05fr); } }
    .gfc-tool > * { background:#10141a; min-width:0; }
    .gfc-inputs { padding:1.4rem 1.3rem; display:grid; gap:1.15rem; align-content:start; }
    .gfc-label { display:block; font-family:var(--font-mono); font-size:10.5px; letter-spacing:.13em; text-transform:uppercase; color:#7d858e; margin-bottom:.45rem; }
    .gfc-field { display:flex; align-items:center; gap:.5rem; border:1px solid var(--line); border-radius:11px; background:#0c1015; padding:.2rem .85rem; transition:border-color .18s ease, box-shadow .18s ease; }
    .gfc-field:focus-within { border-color:rgba(79,143,192,.65); box-shadow:0 0 0 3px var(--gfc-glow); }
    .gfc-field span { font-family:var(--font-mono); font-size:15px; color:#7d858e; }
    .gfc-field input { width:100%; background:transparent; border:0; outline:0; color:#f0f2f4; font-family:var(--font-mono); font-size:19px; font-weight:600; padding:.7rem 0; }
    .gfc-field input::-webkit-outer-spin-button, .gfc-field input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
    .gfc-field input[type=number] { -moz-appearance:textfield; }
    .gfc-presets { display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.55rem; }
    .gfc-preset { font-family:var(--font-mono); font-size:11.5px; color:#9aa3ac; border:1px solid var(--line); background:#0c1015; border-radius:8px; padding:.32rem .6rem; cursor:pointer; transition:all .15s ease; }
    .gfc-preset:hover { color:#e9e7e2; border-color:rgba(79,143,192,.5); }
    .gfc-result { padding:1.4rem 1.3rem; background:linear-gradient(180deg,#11161c,#0e1218); }
    .gfc-result-k { font-family:var(--font-mono); font-size:10.5px; letter-spacing:.13em; text-transform:uppercase; color:#7d858e; }
    .gfc-big { font-family:var(--font-display); font-weight:800; font-size:clamp(2rem,5.5vw,2.9rem); letter-spacing:-.035em; line-height:1.05; color:var(--gfc-ok); margin-top:.3rem; }
    .gfc-big-sub { font-size:12.5px; color:var(--muted); margin-top:.35rem; }
    .gfc-stats { display:grid; gap:1px; background:var(--line); border:1px solid var(--line); border-radius:12px; overflow:hidden; margin-top:1.2rem; grid-template-columns:1fr 1fr; }
    .gfc-stat { background:#0c1015; padding:.8rem .9rem; }
    .gfc-stat-k { font-family:var(--font-mono); font-size:10px; letter-spacing:.1em; text-transform:uppercase; color:#6f7883; }
    .gfc-stat-v { font-family:var(--font-mono); font-size:16px; font-weight:600; color:#e9e7e2; margin-top:.2rem; }
    .gfc-stat-v.bad { color:var(--gfc-bad); }
    .gfc-note { font-size:12px; line-height:1.6; color:#7d858e; margin-top:1rem; }
    .gfc-sec { padding:3.5rem 0; border-top:1px solid var(--line); }
    @media (min-width:768px) { .gfc-sec { padding:4.5rem 0; } }
    .gfc-h2 { font-family:var(--font-display); font-weight:800; letter-spacing:-.03em; font-size:clamp(1.45rem,3vw,2.1rem); line-height:1.14; color:#f4f6f5; }
    .gfc-sub { color:var(--muted); font-size:14px; line-height:1.7; max-width:60ch; margin-top:.7rem; }
    .gfc-table-wrap { border:1px solid var(--line); border-radius:14px; overflow-x:auto; margin-top:1.6rem; }
    .gfc-table { width:100%; border-collapse:collapse; font-size:13.5px; min-width:640px; }
    .gfc-table th, .gfc-table td { padding:.75rem .95rem; text-align:left; border-bottom:1px solid var(--line); white-space:nowrap; }
    .gfc-table thead th { font-family:var(--font-mono); font-size:10px; letter-spacing:.11em; text-transform:uppercase; color:#7d858e; background:rgba(255,255,255,.02); }
    .gfc-table td:first-child { color:#e9e7e2; font-weight:600; }
    .gfc-table .num { font-family:var(--font-mono); text-align:right; }
    .gfc-table .keep { color:var(--gfc-ok); font-weight:600; }
    .gfc-table tr.gfc-us { background:rgba(79,143,192,.07); }
    .gfc-table tr.gfc-us td:first-child { color:var(--gfc-accent-2); }
    .gfc-table tbody tr:last-child td { border-bottom:0; }
    .gfc-tag { font-family:var(--font-mono); font-size:9.5px; letter-spacing:.08em; text-transform:uppercase; color:#7d858e; border:1px solid var(--line); border-radius:5px; padding:.1rem .35rem; margin-left:.4rem; }
    .gfc-cards { display:grid; gap:1rem; margin-top:1.6rem; grid-template-columns:minmax(0,1fr); }
    @media (min-width:760px) { .gfc-cards { grid-template-columns:1fr 1fr 1fr; } }
    .gfc-card { border:1px solid var(--line); border-radius:13px; background:#10141a; padding:1.1rem 1.15rem; }
    .gfc-card h3 { font-family:var(--font-display); font-size:14.5px; font-weight:700; color:#e9e7e2; margin:0 0 .4rem; }
    .gfc-card p { font-size:13px; line-height:1.65; color:var(--muted); margin:0; }
    .gfc-faq { display:grid; gap:.55rem; margin-top:1.6rem; }
    .gfc-faq details { border:1px solid var(--line); border-radius:11px; background:#10141a; }
    .gfc-faq summary { cursor:pointer; padding:.85rem 1.05rem; font-weight:650; font-size:13.5px; color:#e9e7e2; list-style:none; display:flex; justify-content:space-between; gap:1rem; }
    .gfc-faq summary::-webkit-details-marker { display:none; }
    .gfc-faq summary::after { content:'+'; font-family:var(--font-mono); color:var(--gfc-accent); }
    .gfc-faq details[open] summary::after { content:'-'; }
    .gfc-faq p { padding:0 1.05rem 1rem; font-size:13px; line-height:1.7; color:var(--muted); margin:0; }
    .gfc-cta { border:1px solid rgba(79,143,192,.3); background:linear-gradient(180deg,rgba(79,143,192,.09),rgba(79,143,192,.03)); border-radius:15px; padding:1.6rem 1.4rem; margin-top:2.5rem; }
    .gfc-btn { display:inline-flex; align-items:center; gap:.45rem; background:var(--gfc-accent); color:#08121a; font-weight:750; font-size:14px; border-radius:10px; padding:.7rem 1.1rem; text-decoration:none; transition:transform .15s ease, filter .15s ease; }
    .gfc-btn:hover { filter:brightness(1.08); transform:translateY(-1px); }
    .gfc-btn-ghost { display:inline-flex; align-items:center; gap:.4rem; color:#cfd6dc; font-weight:650; font-size:13.5px; text-decoration:none; border-bottom:1px solid rgba(255,255,255,.16); padding-bottom:2px; }
    .gfc-btn-ghost:hover { color:#fff; border-color:rgba(255,255,255,.4); }
  </style>
</head>
<body class="gfc-page">
  <?php include __DIR__ . '/header.php'; ?>

  <main>
    <section class="gfc-hero">
      <div class="gfc-wrap">
        <span class="gfc-eyebrow">Fee calculator</span>
        <h1 class="gfc-h1">Gumroad fee calculator</h1>
        <p class="gfc-lead">Type a price and a monthly volume. See exactly what Gumroad keeps with 10% + $0.50, then price the same sale on eight other platforms. Fees verified October 2026.</p>

        <div class="gfc-tool">
          <div class="gfc-inputs">
            <div>
              <label class="gfc-label" for="gfcPrice">Price per sale</label>
              <div class="gfc-field">
                <span>$</span>
                <input id="gfcPrice" type="number" inputmode="decimal" min="0.5" max="100000" step="0.5" value="20" aria-label="Price per sale in US dollars" />
              </div>
              <div class="gfc-presets" data-target="gfcPrice">
                <button class="gfc-preset" type="button" data-val="5">$5</button>
                <button class="gfc-preset" type="button" data-val="10">$10</button>
                <button class="gfc-preset" type="button" data-val="20">$20</button>
                <button class="gfc-preset" type="button" data-val="50">$50</button>
                <button class="gfc-preset" type="button" data-val="100">$100</button>
              </div>
            </div>
            <div>
              <label class="gfc-label" for="gfcUnits">Sales per month</label>
              <div class="gfc-field">
                <input id="gfcUnits" type="number" inputmode="numeric" min="1" max="100000" step="1" value="100" aria-label="Sales per month" />
                <span>sales</span>
              </div>
              <div class="gfc-presets" data-target="gfcUnits">
                <button class="gfc-preset" type="button" data-val="25">25</button>
                <button class="gfc-preset" type="button" data-val="100">100</button>
                <button class="gfc-preset" type="button" data-val="250">250</button>
                <button class="gfc-preset" type="button" data-val="1000">1,000</button>
              </div>
            </div>
            <p class="gfc-note">Figures are USD and exclude tax. Platforms marked merchant of record include payment processing in their percentage. The others are shown with a 2.9% + $0.30 gateway cost added.</p>
          </div>

          <div class="gfc-result">
            <div class="gfc-result-k">Gumroad direct, after fees</div>
            <div class="gfc-big" id="gfcKeep">$1,750.00</div>
            <div class="gfc-big-sub" id="gfcKeepSub">You keep this from $2,000.00 in monthly sales.</div>
            <div class="gfc-stats">
              <div class="gfc-stat">
                <div class="gfc-stat-k">Gumroad keeps / mo</div>
                <div class="gfc-stat-v bad" id="gfcFee">$250.00</div>
              </div>
              <div class="gfc-stat">
                <div class="gfc-stat-k">Fee per sale</div>
                <div class="gfc-stat-v" id="gfcPerSale">$2.50</div>
              </div>
              <div class="gfc-stat">
                <div class="gfc-stat-k">Effective rate</div>
                <div class="gfc-stat-v" id="gfcRate">12.5%</div>
              </div>
              <div class="gfc-stat">
                <div class="gfc-stat-k">With Discover, 30%</div>
                <div class="gfc-stat-v bad" id="gfcDiscover">$600.00</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="gfc-sec">
      <div class="gfc-wrap">
        <h2 class="gfc-h2">Same sale, nine platforms</h2>
        <p class="gfc-sub">Sorted by what you keep per month, using the numbers you entered above. Platform fee only where the provider acts as merchant of record, otherwise with a 2.9% + $0.30 gateway added.</p>
        <div class="gfc-table-wrap">
          <table class="gfc-table">
            <thead>
              <tr>
                <th>Platform</th>
                <th>Fee model</th>
                <th class="num">Per sale</th>
                <th class="num">Monthly cost</th>
                <th class="num">You keep / mo</th>
              </tr>
            </thead>
            <tbody id="gfcRows">
              <?php foreach ($gfRows as $r): ?>
              <tr class="<?= $r['p']['id'] === 'vexaris' ? 'gfc-us' : '' ?>">
                <td><?= e($r['p']['name']) ?><?php if ($r['p']['mor']): ?><span class="gfc-tag">MoR</span><?php endif; ?></td>
                <td><?= e($r['p']['model']) ?></td>
                <td class="num">$<?= number_format($r['c']['per_sale'], 2) ?></td>
                <td class="num">$<?= number_format($r['c']['monthly'], 2) ?></td>
                <td class="num keep">$<?= number_format($r['c']['keep'], 2) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="gfc-note">MoR means merchant of record: the platform collects and remits VAT or sales tax for you. On the others that work stays with you.</p>
      </div>
    </section>

    <section class="gfc-sec">
      <div class="gfc-wrap">
        <h2 class="gfc-h2">Three things the percentage hides</h2>
        <div class="gfc-cards">
          <div class="gfc-card">
            <h3>The fixed fee hurts cheap items</h3>
            <p>$0.50 is 25% of a $2 sale and 0.25% of a $200 sale. If you sell game keys, e-pins or $3 templates, the effective rate climbs past 20% before you notice.</p>
          </div>
          <div class="gfc-card">
            <h3>Discover is a different rate</h3>
            <p>Sales you bring yourself cost 10% + $0.50. Sales the marketplace brings cost 30%. If most of your volume arrives through Discover, that traffic is what you are paying for.</p>
          </div>
          <div class="gfc-card">
            <h3>Tax handling is the hidden line</h3>
            <p>A merchant of record files EU VAT, UK VAT and US sales tax. Moving to a flat plan moves that work to you or your accountant, so compare total cost, not just the percentage.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="gfc-sec">
      <div class="gfc-wrap">
        <h2 class="gfc-h2">Questions sellers ask</h2>
        <div class="gfc-faq">
          <?php foreach ($gfFaq as $f): ?>
          <details>
            <summary><?= e($f['q']) ?></summary>
            <p><?= e($f['a']) ?></p>
          </details>
          <?php endforeach; ?>
        </div>

        <div class="gfc-cta">
          <h2 class="gfc-h2" style="font-size:1.2rem">Want a 0% platform commission on the same sale?</h2>
          <p class="gfc-sub">Vexaris charges nothing per sale. You connect your own payment gateway, keep the difference, and the store handles file, license key and e-pin delivery automatically. Free plan, no card.</p>
          <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;margin-top:1.2rem">
            <a class="gfc-btn" href="/register">Open a store</a>
            <a class="gfc-btn-ghost" href="/gumroad/alternative-2026">Full fee comparison of 6 alternatives</a>
          </div>
        </div>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/footer.php'; ?>

  <script>
  (function () {
    var GW_PCT = 0.029, GW_FIXED = 0.30;
    var P = [
      {id:'gumroad',  name:'Gumroad (direct)', model:'10% + $0.50', pct:0.10, fixed:0.50, monthly:0,  gw:false, mor:true},
      {id:'discover', name:'Gumroad Discover', model:'30%',          pct:0.30, fixed:0.00, monthly:0,  gw:false, mor:true},
      {id:'payhip',   name:'Payhip',           model:'5%',           pct:0.05, fixed:0.00, monthly:0,  gw:false, mor:true},
      {id:'kofi',     name:'Ko-fi (free)',     model:'5%',           pct:0.05, fixed:0.00, monthly:0,  gw:false, mor:true},
      {id:'kofigold', name:'Ko-fi Gold',       model:'0% + $12/mo',  pct:0.00, fixed:0.00, monthly:12, gw:true,  mor:false},
      {id:'sellfy',   name:'Sellfy',           model:'0% + $29/mo',  pct:0.00, fixed:0.00, monthly:29, gw:true,  mor:false},
      {id:'lemon',    name:'Lemon Squeezy',    model:'5% + $0.50',   pct:0.05, fixed:0.50, monthly:0,  gw:false, mor:true},
      {id:'whop',     name:'Whop (direct)',    model:'3%',           pct:0.03, fixed:0.00, monthly:0,  gw:true,  mor:false},
      {id:'vexaris',  name:'Vexaris',          model:'0% platform',  pct:0.00, fixed:0.00, monthly:0,  gw:true,  mor:false}
    ];
    var $ = function (id) { return document.getElementById(id); };
    var money = function (n) {
      return '$' + n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    };
    function calc(p, price, units) {
      var per = price * p.pct + p.fixed;
      if (p.gw) { per += price * GW_PCT + GW_FIXED; }
      var cost = per * units + p.monthly;
      return {per: per, cost: cost, keep: price * units - cost};
    }
    function render() {
      var price = parseFloat($('gfcPrice').value) || 0;
      var units = parseInt($('gfcUnits').value, 10) || 0;
      if (price < 0) { price = 0; }
      if (units < 0) { units = 0; }
      var gross = price * units;
      var g = calc(P[0], price, units);
      var d = calc(P[1], price, units);
      $('gfcKeep').textContent = money(g.keep);
      $('gfcKeepSub').textContent = 'You keep this from ' + money(gross) + ' in monthly sales.';
      $('gfcFee').textContent = money(g.cost);
      $('gfcPerSale').textContent = money(g.per);
      $('gfcRate').textContent = (price > 0 ? ((g.per / price) * 100).toFixed(1) : '0.0') + '%';
      $('gfcDiscover').textContent = money(d.cost);

      var rows = P.map(function (p) { return {p: p, c: calc(p, price, units)}; });
      rows.sort(function (a, b) { return b.c.keep - a.c.keep; });
      var html = '';
      rows.forEach(function (r) {
        html += '<tr' + (r.p.id === 'vexaris' ? ' class="gfc-us"' : '') + '>'
          + '<td>' + r.p.name + (r.p.mor ? '<span class="gfc-tag">MoR</span>' : '') + '</td>'
          + '<td>' + r.p.model + '</td>'
          + '<td class="num">' + money(r.c.per) + '</td>'
          + '<td class="num">' + money(r.c.cost) + '</td>'
          + '<td class="num keep">' + money(r.c.keep) + '</td>'
          + '</tr>';
      });
      $('gfcRows').innerHTML = html;
    }
    ['gfcPrice', 'gfcUnits'].forEach(function (id) {
      var el = $(id);
      el.addEventListener('input', render);
      el.addEventListener('change', render);
    });
    Array.prototype.forEach.call(document.querySelectorAll('.gfc-presets'), function (box) {
      box.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.gfc-preset');
        if (!btn) { return; }
        var target = $(box.getAttribute('data-target'));
        target.value = btn.getAttribute('data-val');
        render();
      });
    });
    render();
  })();
  </script>
</body>
</html>

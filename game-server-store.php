<?php
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/data.php';
boot_session();
log_pageview('oyun_sunucu_magazasi');
$loggedIn = is_logged_in();
$cu = current_user();
$GLOBALS['seoKey'] = 'game-server-store';
$GLOBALS['seoFallback'] = [
  'title' => 'Free Webstore for Your Game Server | 0% Commission',
  'description' => 'Sell ranks, keys, crates and credits from your own game server store. RCON delivers in game the second payment clears. Free plan, 0% platform commission, no card.',
];
$BASE = '/';

if (!function_exists('e')) {
  function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$gsLatest = null;
try {
  $gsPdo = db();
  if ($gsPdo) {
    $gsSt = $gsPdo->prepare("SELECT slug, title, created_at FROM blog_posts WHERE status='published' ORDER BY created_at DESC, id DESC LIMIT 1");
    $gsSt->execute();
    $gsLatest = $gsSt->fetch(PDO::FETCH_ASSOC) ?: null;
  }
} catch (Throwable $e) { $gsLatest = null; }

$GS_FEATS = [
  ['icon' => 'bolt', 'title' => 'RCON delivery', 'desc' => 'Payment clears, command runs. The buyer types their in game name at checkout and the item is in their inventory before they close the tab.'],
  ['icon' => 'server', 'title' => 'Delivery queue', 'desc' => 'Server offline? The delivery waits with a status and a retry time. Send it again from the panel or close it by hand.'],
  ['icon' => 'bolt', 'title' => 'Live server status', 'desc' => 'Java pinged with Server List Ping, Bedrock with RakNet, one public fallback if the direct ping fails. We print what the server answers.'],
  ['icon' => 'users', 'title' => 'Ranking board', 'desc' => 'Top credit loaders and the newest purchases, built from real orders. While the store is empty, the section hides itself.'],
  ['icon' => 'coin', 'title' => 'Credits wallet', 'desc' => 'Players top up a balance once, then spend it on ranks, keys or crates without leaving the store.'],
  ['icon' => 'palette', 'title' => 'Craft theme', 'desc' => 'A dark, console style storefront built for game stores. One click to activate, free on every plan.'],
];

$GS_STEPS = [
  ['n' => '01', 'title' => 'Connect', 'text' => 'Host, port 25575 and the RCON password from server.properties. Nothing to install on the server.'],
  ['n' => '02', 'title' => 'Test', 'text' => 'The panel opens a real RCON connection and prints the answer the server sends back. No guesswork.'],
  ['n' => '03', 'title' => 'Attach', 'text' => 'Paste the commands on the product. {player}, {product} and {sale} are filled in per order. Up to 10 lines.'],
];

$GS_FAQ = [
  ['q' => 'Is the game server store really free?', 'a' => 'Yes. The free plan costs $0, needs no credit card and takes 0% platform commission. It covers 25 products and $500 of lifetime revenue, which is enough to test ranks and keys with real players. RCON delivery, the craft theme, live server status and the ranking board are included on it.'],
  ['q' => 'Do I need a plugin on my server for RCON?', 'a' => 'No plugin for Minecraft. RCON is built in: set enable-rcon=true, define rcon.port and rcon.password in server.properties, restart the server and paste the same values into the store panel. Other games that expose an RCON console follow the same pattern.'],
  ['q' => 'Does RCON work on Bedrock servers?', 'a' => 'No, Minecraft Bedrock edition has no RCON. Bedrock stores still get the storefront, the status widget and the ranking board. Deliveries go to the queue with the player name attached, and you close them from the panel in one click.'],
  ['q' => 'What happens if the server is down when a player buys?', 'a' => 'The order is never lost. The delivery stays queued with a failed or pending status and retries later. You can resend it, or mark it done manually after giving the item in game.'],
  ['q' => 'Can I sell ranks, keys and crates?', 'a' => 'Anything your server can grant with a console command. LuckPerms ranks, crate keys, in game currency, kits and spawn items all work, because the store only sends the command line you write.'],
  ['q' => 'Is RCON safe to expose?', 'a' => 'RCON traffic is not encrypted, so use a long random password, never reuse your admin password, and restrict the port where your host allows it. The store connects out to your server, so you can whitelist that connection instead of leaving RCON open to the whole internet.'],
  ['q' => 'Which payments can players use?', 'a' => 'Card gateways you connect yourself (Stripe, PayPal, Paddle, Lemon Squeezy, Dodo, iyzico, PayTR, Shopier and others) plus USDT and USDC over six networks with 0% platform commission. Payouts go straight to your own account.'],
];

$faqJson = [];
foreach ($GS_FAQ as $f) {
  $faqJson[] = ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]];
}
$pageJson = [
  '@context' => 'https://schema.org',
  '@type' => 'WebPage',
  'name' => 'Free webstore for your game server',
  'description' => 'Game server store with RCON delivery, live server status and a ranking board. Free plan, 0% platform commission.',
  'url' => 'https://vexaris.net/game-server-store',
  'isPartOf' => ['@type' => 'WebSite', 'name' => 'Vexaris', 'url' => 'https://vexaris.net/'],
];
$crumbJson = [
  '@context' => 'https://schema.org',
  '@type' => 'BreadcrumbList',
  'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://vexaris.net/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Game server store', 'item' => 'https://vexaris.net/game-server-store'],
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
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" />
  <link rel="icon" type="image/png" href="./assets/images/vexaris-logo.png" />
  <link rel="stylesheet" href="./assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>" />
  <link rel="stylesheet" href="./assets/css/tailwind.css?v=<?= filemtime(__DIR__ . '/assets/css/tailwind.css') ?>" />
  <script type="application/ld+json"><?= json_encode($pageJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <script type="application/ld+json"><?= json_encode($crumbJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqJson], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <style>
    .gs-page { --gs-grass:#74c091; --gs-grass-2:#a7e4c1; --gs-glow:rgba(116,192,145,.11); min-height:100vh; }
    .gs-wrap { width:100%; max-width:80rem; margin:0 auto; padding:0 1.25rem; }
    @media (min-width:640px) { .gs-wrap { padding:0 2rem; } }
    .gs-hero { position:relative; overflow:hidden; padding:7rem 0 3.5rem; }
    @media (min-width:768px) { .gs-hero { padding:8.5rem 0 5rem; } }
    .gs-dots { position:absolute; inset:0; background-image:radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px); background-size:22px 22px; -webkit-mask-image:radial-gradient(70% 60% at 50% 10%, #000 10%, transparent 100%); mask-image:radial-gradient(70% 60% at 50% 10%, #000 10%, transparent 100%); pointer-events:none; }
    .gs-hero-grid { position:relative; display:grid; grid-template-columns:minmax(0,1fr); gap:2.5rem; align-items:center; }
    .gs-hero-grid > *, .gs-grid > *, .gs-split > *, .gs-honest > *, .gs-faq > * { min-width:0; }
    @media (min-width:1024px) { .gs-hero-grid { grid-template-columns:minmax(0,1.02fr) minmax(0,.98fr); gap:3rem; } }
    .gs-eyebrow { display:inline-flex; align-items:center; gap:.55rem; font-family:var(--font-mono); font-size:11.5px; letter-spacing:.16em; text-transform:uppercase; color:var(--gs-grass); border:1px solid rgba(116,192,145,.28); background:rgba(116,192,145,.07); border-radius:999px; padding:.42rem .9rem; }
    .gs-eyebrow--link { text-decoration:none; max-width:100%; overflow:hidden; transition:border-color .2s ease, background .2s ease; }
    .gs-eyebrow--link:hover { border-color:rgba(116,192,145,.55); background:rgba(116,192,145,.12); }
    .gs-eyebrow-k { color:var(--gs-grass-2); opacity:.85; flex-shrink:0; }
    .gs-eyebrow-k::after { content:''; }
    .gs-eyebrow-t { min-width:0; flex:1 1 auto; color:#dfe7e1; text-transform:none; letter-spacing:.01em; font-family:var(--font-body); font-size:12px; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .gs-eyebrow-ar { color:var(--gs-grass); flex-shrink:0; }
    .gs-dot { width:6px; height:6px; border-radius:999px; background:var(--gs-grass); box-shadow:0 0 0 3px rgba(116,192,145,.16); flex-shrink:0; }
    .gs-h1 { font-family:var(--font-display); font-weight:800; letter-spacing:-.035em; line-height:1.03; font-size:clamp(2.05rem,4.6vw,3.5rem); margin:1.15rem 0 0; color:#f2f4f2; }
    .gs-lead { color:var(--muted); font-size:15.5px; line-height:1.72; margin-top:1.15rem; max-width:34rem; }
    .gs-actions { display:flex; flex-wrap:wrap; gap:.7rem; margin-top:1.8rem; }
    .gs-chips { display:flex; flex-wrap:wrap; gap:.5rem; margin-top:1.8rem; }
    .gs-chip { font-size:12px; font-weight:600; color:#c6d1ca; border:1px solid var(--line); background:rgba(255,255,255,.03); border-radius:999px; padding:.4rem .78rem; display:inline-flex; align-items:center; gap:.4rem; }
    .gs-chip svg { color:var(--gs-grass); width:13px; height:13px; flex-shrink:0; }
    .gs-console { border:1px solid var(--line); border-radius:16px; background:linear-gradient(180deg,#12161b,#0e1116); overflow:hidden; box-shadow:0 40px 90px -50px rgba(0,0,0,.95); }
    .gs-console-bar { display:flex; align-items:center; gap:.45rem; padding:.62rem .9rem; border-bottom:1px solid var(--line); background:rgba(255,255,255,.02); }
    .gs-console-bar i { width:9px; height:9px; border-radius:999px; background:#313a44; display:block; }
    .gs-console-url { margin-left:.5rem; font-family:var(--font-mono); font-size:11.5px; color:#7d858e; }
    .gs-console-body { display:grid; gap:1px; background:var(--line); }
    @media (min-width:560px) { .gs-console-body { grid-template-columns:1.2fr 1fr; } }
    .gs-panel { background:#10141a; padding:1rem 1.05rem; }
    .gs-panel-title { font-family:var(--font-mono); font-size:10.5px; letter-spacing:.16em; text-transform:uppercase; color:#7d858e; margin-bottom:.65rem; }
    .gs-item { display:flex; align-items:center; gap:.65rem; padding:.55rem 0; border-bottom:1px dashed rgba(255,255,255,.06); }
    .gs-item:last-child { border-bottom:0; padding-bottom:0; }
    .gs-item-ic { width:30px; height:30px; border-radius:8px; display:grid; place-items:center; background:var(--gs-glow); color:var(--gs-grass); flex-shrink:0; }
    .gs-item-name { font-size:13.5px; font-weight:700; color:#e9e7e2; line-height:1.2; }
    .gs-item-meta { font-family:var(--font-mono); font-size:10.5px; color:#7d858e; margin-top:.15rem; }
    .gs-item-price { margin-left:auto; font-family:var(--font-mono); font-size:12.5px; color:#e9e7e2; }
    .gs-rank { display:flex; align-items:center; gap:.55rem; padding:.4rem 0; font-size:13px; }
    .gs-rank span:first-child { font-family:var(--font-mono); font-size:11px; color:#5f6a76; width:12px; flex-shrink:0; }
    .gs-rank-name { color:#d3dae0; font-weight:600; }
    .gs-rank-amt { margin-left:auto; font-family:var(--font-mono); font-size:11.5px; color:var(--gs-grass-2); }
    .gs-status { margin-top:.85rem; display:flex; align-items:center; gap:.45rem; border:1px solid rgba(116,192,145,.25); background:rgba(116,192,145,.07); border-radius:10px; padding:.5rem .6rem; font-family:var(--font-mono); font-size:11px; color:#b9d8c6; }
    .gs-status b { color:var(--gs-grass); font-weight:700; }
    .gs-status span, .gs-status b { white-space:nowrap; }
    .gs-status-host { overflow:hidden; text-overflow:ellipsis; }
    .gs-status-meta { margin-left:auto; }
    .gs-caption { margin-top:.7rem; font-size:11.5px; color:#7d858e; }
    .gs-sec { padding:4rem 0; border-top:1px solid var(--line); }
    .gs-sec--last, .gs-sec.gs-sec--last { padding-bottom:0; }
    @media (min-width:768px) { .gs-sec { padding:5rem 0; } }
    .gs-h2 { font-family:var(--font-display); font-weight:800; letter-spacing:-.03em; font-size:clamp(1.55rem,3.1vw,2.25rem); line-height:1.12; color:#f2f4f2; }
    .gs-sub { color:var(--muted); font-size:14.5px; line-height:1.72; max-width:58ch; margin-top:.75rem; }
    .gs-grid { display:grid; gap:1rem; grid-template-columns:minmax(0,1fr); margin-top:2rem; }
    @media (min-width:640px) { .gs-grid { grid-template-columns:minmax(0,1fr) minmax(0,1fr); } }
    @media (min-width:1024px) { .gs-grid { grid-template-columns:repeat(3,1fr); } }
    .gs-card { border:1px solid var(--line); border-radius:14px; background:#10141a; padding:1.25rem 1.2rem; }
    .gs-card-ic { width:34px; height:34px; border-radius:9px; display:grid; place-items:center; background:var(--gs-glow); color:var(--gs-grass); margin-bottom:.85rem; }
    .gs-card h3 { font-family:var(--font-display); font-weight:700; font-size:15.5px; color:#e9e7e2; margin:0 0 .35rem; letter-spacing:-.01em; }
    .gs-card p { font-size:13.5px; line-height:1.65; color:var(--muted); margin:0; }
    .gs-split { display:grid; gap:2.2rem; align-items:start; }
    @media (min-width:960px) { .gs-split { grid-template-columns:minmax(0,.85fr) minmax(0,1.15fr); gap:3rem; } }
    .gs-step { display:flex; gap:.9rem; padding-bottom:1.05rem; border-bottom:1px dashed rgba(255,255,255,.07); }
    .gs-step:last-child { border-bottom:0; padding-bottom:0; }
    .gs-step-n { font-family:var(--font-mono); font-size:11px; color:var(--gs-grass); padding-top:.2rem; flex-shrink:0; }
    .gs-step h3 { font-family:var(--font-display); font-weight:700; font-size:15px; margin:0 0 .22rem; color:#e9e7e2; }
    .gs-step p { font-size:13.5px; line-height:1.6; color:var(--muted); margin:0; }
    .gs-term { border:1px solid var(--line); border-radius:14px; background:#0c1015; overflow:hidden; }
    .gs-term-bar { padding:.55rem .8rem; border-bottom:1px solid var(--line); font-family:var(--font-mono); font-size:10.5px; color:#7d858e; letter-spacing:.1em; text-transform:uppercase; }
    .gs-term pre { margin:0; padding:1rem 1.1rem; font-family:var(--font-mono); font-size:12.5px; line-height:1.85; color:#c7d2cb; overflow-x:auto; }
    .gs-term .ok { color:var(--gs-grass); }
    .gs-term .dim { color:#5f6a76; }
    .gs-note { margin-top:.8rem; font-size:12.5px; color:#7d858e; line-height:1.6; }
    .gs-table-wrap { border:1px solid var(--line); border-radius:14px; overflow-x:auto; margin-top:1.8rem; }
    .gs-table { width:100%; border-collapse:collapse; font-size:13.5px; min-width:560px; }
    .gs-table th, .gs-table td { padding:.8rem 1rem; text-align:left; border-bottom:1px solid var(--line); }
    .gs-table thead th { font-family:var(--font-mono); font-size:10.5px; letter-spacing:.12em; text-transform:uppercase; color:#7d858e; background:rgba(255,255,255,.02); white-space:nowrap; }
    .gs-table td:first-child, .gs-table th:first-child { color:#aeb7c0; }
    .gs-table .gs-us { background:rgba(116,192,145,.05); color:#e9e7e2; font-weight:600; }
    .gs-table .gs-us-head { color:var(--gs-grass); }
    .gs-table tr:last-child td { border-bottom:0; }
    .gs-zero { font-family:var(--font-mono); color:var(--gs-grass); font-weight:700; }
    .gs-honest { display:grid; gap:1rem; margin-top:1.8rem; }
    @media (min-width:760px) { .gs-honest { grid-template-columns:1fr 1fr; } }
    .gs-honest > div { border:1px solid var(--line); border-radius:14px; background:#10141a; padding:1.2rem; }
    .gs-honest h3 { font-family:var(--font-display); font-weight:700; font-size:14.5px; margin:0 0 .6rem; }
    .gs-honest ul { list-style:none; padding:0; margin:0; display:grid; gap:.5rem; }
    .gs-honest li { font-size:13px; line-height:1.6; color:var(--muted); padding-left:1.1rem; position:relative; }
    .gs-honest li::before { content:''; position:absolute; left:0; top:.55em; width:5px; height:5px; border-radius:2px; background:var(--gs-grass); opacity:.7; }
    .gs-facts { display:grid; gap:1px; background:var(--line); border:1px solid var(--line); border-radius:14px; overflow:hidden; margin-top:1.8rem; }
    @media (min-width:640px) { .gs-facts { grid-template-columns:repeat(3,1fr); } }
    .gs-fact { background:#10141a; padding:1.1rem 1.2rem; }
    .gs-fact-k { font-family:var(--font-mono); font-size:10.5px; letter-spacing:.12em; text-transform:uppercase; color:#7d858e; }
    .gs-fact-v { font-family:var(--font-display); font-weight:800; font-size:1.5rem; letter-spacing:-.02em; color:#e9e7e2; margin-top:.25rem; }
    .gs-fact-n { font-size:11.5px; color:#7d858e; margin-top:.15rem; }
    .gs-faq { display:grid; gap:.6rem; margin-top:1.8rem; }
    @media (min-width:1024px) { .gs-faq { grid-template-columns:1fr 1fr; align-items:start; } }
    .gs-faq details { break-inside:avoid; }
    .gs-faq details { border:1px solid var(--line); border-radius:12px; background:#10141a; }
    .gs-faq summary { cursor:pointer; padding:.9rem 1.1rem; font-weight:650; font-size:14px; color:#e9e7e2; list-style:none; display:flex; justify-content:space-between; gap:1rem; }
    .gs-faq summary::-webkit-details-marker { display:none; }
    .gs-faq summary::after { content:'+'; font-family:var(--font-mono); color:var(--gs-grass); }
    .gs-faq details[open] summary::after { content:'-'; }
    .gs-faq p { padding:0 1.1rem 1rem; font-size:13.5px; line-height:1.7; color:var(--muted); margin:0; }
    @media (min-width:768px) { .gs-end { padding-bottom:6rem; } }
  </style>
</head>
<body class="gs-page bg-[#0b0d10] text-[#e9e7e2]">
<div class="relative min-h-screen bg-[#0b0d10] text-[#e9e7e2]">
  <div class="noise-overlay" aria-hidden="true"></div>

  <?php include __DIR__ . '/header.php'; ?>

  <main>
    <section class="gs-hero">
      <div class="gs-dots" aria-hidden="true"></div>
      <div class="gs-wrap">
        <div class="gs-hero-grid">
          <div>
            <?php if ($gsLatest): ?>
            <a class="gs-eyebrow gs-eyebrow--link" href="/<?= e($gsLatest['slug']) ?>" title="<?= e($gsLatest['title']) ?>">
              <span class="gs-dot"></span>
              <span class="gs-eyebrow-k">New guide</span>
              <span class="gs-eyebrow-t"><?= e($gsLatest['title']) ?></span>
            </a>
            <?php else: ?>
            <span class="gs-eyebrow"><span class="gs-dot"></span>Game server stores</span>
            <?php endif; ?>
            <h1 class="gs-h1">Free webstore<br />for your game server.</h1>
            <p class="gs-lead">Ranks, crates, keys and credits on a page your players can browse. When the payment clears, the store fires the RCON command and the item is in their inventory. No plugin, no manual messages.</p>
            <div class="gs-actions">
              <a href="<?= $loggedIn ? '/dashboard' : '/register' ?>" class="btn-primary px-6 py-3 text-sm inline-flex items-center gap-2">Create your store <?= vex_icon('arrow', 'w-4 h-4') ?></a>
              <a href="#rcon" class="btn-ghost px-6 py-3 text-sm font-semibold">How delivery works</a>
            </div>
            <div class="gs-chips">
              <span class="gs-chip"><?= vex_icon('check') ?> No card required</span>
              <span class="gs-chip"><?= vex_icon('check') ?> 0% platform commission</span>
              <span class="gs-chip"><?= vex_icon('check') ?> RCON on every plan</span>
              <span class="gs-chip"><?= vex_icon('check') ?> Java and Bedrock status</span>
            </div>
          </div>

          <div>
            <div class="gs-console">
              <div class="gs-console-bar">
                <i></i><i></i><i></i>
                <span class="gs-console-url">yourstore.vexaris.app</span>
              </div>
              <div class="gs-console-body">
                <div class="gs-panel">
                  <div class="gs-panel-title">Storefront</div>
                  <div class="gs-item">
                    <span class="gs-item-ic"><?= vex_icon('shield', 'w-4 h-4') ?></span>
                    <div><div class="gs-item-name">VIP Rank</div><div class="gs-item-meta">RCON delivery</div></div>
                    <span class="gs-item-price">$4.99</span>
                  </div>
                  <div class="gs-item">
                    <span class="gs-item-ic"><?= vex_icon('box', 'w-4 h-4') ?></span>
                    <div><div class="gs-item-name">Diamond Crate x3</div><div class="gs-item-meta">RCON delivery</div></div>
                    <span class="gs-item-price">$2.49</span>
                  </div>
                  <div class="gs-item">
                    <span class="gs-item-ic"><?= vex_icon('plugin', 'w-4 h-4') ?></span>
                    <div><div class="gs-item-name">Spawn Plugin</div><div class="gs-item-meta">File delivery</div></div>
                    <span class="gs-item-price">$6.00</span>
                  </div>
                </div>
                <div class="gs-panel">
                  <div class="gs-panel-title">Top supporters</div>
                  <div class="gs-rank"><span>1</span><span class="gs-rank-name">Builder_04</span><span class="gs-rank-amt">$38.00</span></div>
                  <div class="gs-rank"><span>2</span><span class="gs-rank-name">nightfarm</span><span class="gs-rank-amt">$24.50</span></div>
                  <div class="gs-rank"><span>3</span><span class="gs-rank-name">kaya_</span><span class="gs-rank-amt">$11.00</span></div>
                  <div class="gs-status"><span class="gs-dot"></span><span class="gs-status-host">play.yourserver.com</span><span class="gs-status-meta"><b>online</b> 42/100</span></div>
                </div>
              </div>
            </div>
            <p class="gs-caption">Craft theme layout. Names and numbers on this card are examples.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="gs-sec">
      <div class="gs-wrap">
        <h2 class="gs-h2">Built for game stores, not adapted to them</h2>
        <p class="gs-sub">Six things a game server store actually needs. All of them ride on the free plan.</p>
        <div class="gs-grid">
          <?php foreach ($GS_FEATS as $f): ?>
          <div class="gs-card">
            <div class="gs-card-ic"><?= vex_icon($f['icon'], 'w-4 h-4') ?></div>
            <h3><?= e($f['title']) ?></h3>
            <p><?= e($f['desc']) ?></p>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section id="rcon" class="gs-sec" style="scroll-margin-top:7rem">
      <div class="gs-wrap">
        <div class="gs-split">
          <div>
            <h2 class="gs-h2">From payment to inventory in one command</h2>
            <p class="gs-sub">RCON is the console port a Minecraft Java server already has. The store talks to it the same way you would, then writes the result to the delivery log.</p>
            <div style="margin-top:1.6rem">
              <?php foreach ($GS_STEPS as $s): ?>
              <div class="gs-step">
                <span class="gs-step-n"><?= e($s['n']) ?></span>
                <div>
                  <h3><?= e($s['title']) ?></h3>
                  <p><?= e($s['text']) ?></p>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div>
            <div class="gs-term">
              <div class="gs-term-bar">delivery log</div>
              <pre>&gt; connect play.yourserver.com:25575   <span class="ok">[ok]</span>
&gt; lp user {player} parent add vip      <span class="ok">[ok]</span>
&gt; give {player} diamond 64             <span class="ok">[ok]</span>
<span class="dim">&#9500; order #1042 &#183; Builder_04 &#183; 1.2s</span></pre>
            </div>
            <p class="gs-note">Bedrock edition has no RCON. Bedrock stores queue the delivery with the player name attached and you close it from the panel in one click. Servers that expose an RCON console outside Minecraft work the same way.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="gs-sec">
      <div class="gs-wrap">
        <h2 class="gs-h2">What a game store usually costs</h2>
        <p class="gs-sub">The biggest name in server monetization is Tebex. Their free tier is free monthly and takes a flat 5% of every sale. Here is the whole picture, fees checked 9 November 2026 on tebex.io/pricing.</p>
        <div class="gs-table-wrap">
          <table class="gs-table">
            <thead>
              <tr>
                <th>&nbsp;</th>
                <th class="gs-us-head">Vexaris free</th>
                <th>Tebex Starter</th>
                <th>Tebex Plus</th>
              </tr>
            </thead>
            <tbody>
              <tr><td>Monthly fee</td><td class="gs-us">$0</td><td>$0</td><td>12.49 GBP</td></tr>
              <tr><td>Platform cut per sale</td><td class="gs-us"><span class="gs-zero">0%</span></td><td>5%</td><td>5%</td></tr>
              <tr><td>On a $100 month</td><td class="gs-us">$100 minus gateway fee</td><td>$95 minus gateway fee</td><td>95% of sales, minus 12.49 GBP per month</td></tr>
              <tr><td>Merchant of record</td><td class="gs-us">You</td><td>Tebex</td><td>Tebex</td></tr>
              <tr><td>Tax handled for you</td><td class="gs-us">No</td><td>Yes</td><td>Yes</td></tr>
              <tr><td>Chargeback handling</td><td class="gs-us">Your gateway</td><td>Tebex</td><td>Tebex</td></tr>
              <tr><td>Game theme included</td><td class="gs-us">Craft, all plans</td><td>Yes</td><td>Yes</td></tr>
              <tr><td>Custom domain on free tier</td><td class="gs-us">Subdomain</td><td>No</td><td>Yes</td></tr>
            </tbody>
          </table>
        </div>
        <p class="gs-caption">Card and wallet processing fees are charged by your own gateway in both cases. Vexaris charges nothing per sale.</p>
      </div>
    </section>

    <section class="gs-sec">
      <div class="gs-wrap">
        <h2 class="gs-h2">Pick the boring answer for your case</h2>
        <p class="gs-sub">Merchant of record is real work, and it is the one thing we do not do.</p>
        <div class="gs-honest">
          <div>
            <h3 style="color:#e0a458">Tebex is the better pick if</h3>
            <ul>
              <li>You sell to players in several countries and do not want to file VAT or sales tax yourself.</li>
              <li>You want chargebacks and fraud absorbed by someone else.</li>
              <li>You are optimizing for in game storefronts or FiveM and RUST tooling rather than a web store.</li>
            </ul>
          </div>
          <div>
            <h3 style="color:#74c091">Vexaris is the better pick if</h3>
            <ul>
              <li>You already have a gateway or a stablecoin checkout and want to keep every cent of the sale.</li>
              <li>You want a store that doubles as your community page with news, support tickets and a ranking board.</li>
              <li>You want to start at $0 with 25 products and move to a flat $6.99 plan when the store grows.</li>
            </ul>
          </div>
        </div>
      </div>
    </section>

    <section class="gs-sec">
      <div class="gs-wrap">
        <h2 class="gs-h2">The free plan in numbers</h2>
        <div class="gs-facts">
          <div class="gs-fact"><div class="gs-fact-k">Monthly</div><div class="gs-fact-v">$0</div><div class="gs-fact-n">Free forever, no card</div></div>
          <div class="gs-fact"><div class="gs-fact-k">Platform commission</div><div class="gs-fact-v">0%</div><div class="gs-fact-n">Every plan, every sale</div></div>
          <div class="gs-fact"><div class="gs-fact-k">Products</div><div class="gs-fact-v">25</div><div class="gs-fact-n">$500 lifetime revenue</div></div>
        </div>
        <p class="gs-sub">Outgrew it? Starter is $6.99 a month with 200 products, a $10,000 monthly ceiling and a custom domain.</p>
      </div>
    </section>

    <section class="gs-sec gs-sec--last">
      <div class="gs-wrap">
        <h2 class="gs-h2">Questions server owners ask</h2>
        <div class="gs-faq">
          <?php foreach ($GS_FAQ as $f): ?>
          <details>
            <summary><?= e($f['q']) ?></summary>
            <p><?= e($f['a']) ?></p>
          </details>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

  </main>

  <?php include __DIR__ . '/footer.php'; ?>
</div>
<script src="./assets/js/index.js?v=<?= filemtime(__DIR__ . '/assets/js/index.js') ?>"></script>
</body>
</html>

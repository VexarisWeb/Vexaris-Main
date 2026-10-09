<?php
require_once __DIR__ . '/core/legal.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/data.php';
boot_session();
$loggedIn = is_logged_in();
log_pageview('cerez-politikasi');
$legalRow = legal_get('cookie-policy');
$legalBody = $legalRow['content'];
$legalDate = legal_date_tr($legalRow['updated_at'] ?? null);
$GLOBALS['seoKey'] = 'legal_cookie';
$BASE = '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php include __DIR__ . '/includes/seo-head.php'; ?>
<link rel="icon" type="image/png" href="./assets/images/vexaris-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<link rel="stylesheet" href="./assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
<link rel="stylesheet" href="./assets/css/legal.css?v=<?= filemtime(__DIR__ . '/assets/css/legal.css') ?>">
</head>
<body>
<div class="relative min-h-screen bg-[#0b0d10] text-[#e9e7e2]">
<div class="noise-overlay" aria-hidden="true"></div>
<?php include 'header.php'; ?>

<section class="legal" aria-label="Cookie Policy">
    <div class="container">
        <div class="legal__header">
            <div class="hero__badge" style="margin:0 auto 1.5rem">
                <i class="fas fa-cookie-bite"></i>
                <span>Yasal Bilgilendirme</span>
            </div>
            <h1 class="section-title">Cookie <span class="accent">Policy</span></h1>
            <p class="section-subtitle">This policy governs the implementation principles for cookies and similar technologies used on websites operated by the Vexaris platform, prepared under Turkish Personal Data Protection Law No. 6698 and related legislation.</p>
            <div class="legal__meta">
                <?php if ($legalDate): ?>
                <span><i class="fas fa-calendar"></i> Last updated: <?= htmlspecialchars($legalDate) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:flex;gap:.9rem;align-items:flex-start;background:rgba(79,143,192,.07);border:1px solid rgba(79,143,192,.3);border-radius:14px;padding:1.1rem 1.3rem;margin:0 auto 2rem;max-width:760px">
            <div style="width:38px;height:38px;flex:none;border-radius:10px;background:rgba(79,143,192,.12);display:flex;align-items:center;justify-content:center;color:#7fb3d9"><i class="fas fa-clock"></i></div>
            <div style="font-size:.87rem;line-height:1.65;color:#e9e7e2"><strong>~8 min read. Summary:</strong><br><span style="color:var(--muted)">Strictly necessary cookies are always on; analytics and marketing run only with your consent. Reject in one click, your choice is kept for 1 year, full list below.</span></div>
        </div>

        <div class="legal__doc">
            <?= $legalBody ?>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
<?php include __DIR__ . '/includes/modals.php'; ?>
</div>
<script>
window.VEX_LEGAL = <?= json_encode($LEGAL, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="./assets/js/index.js?v=<?= filemtime(__DIR__ . '/assets/js/index.js') ?>"></script>
</body>
</html>

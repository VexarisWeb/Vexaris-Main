<?php
$activePage = 'stores';
require_once __DIR__ . '/../core/db.php';
boot_session();
require_login();
$user = current_user();

$uid = (int)$user['id'];
$pdo = db();
$uemail = strtolower(trim((string)($user['email'] ?? '')));
/* Magazaya ozel sifre karti yalnizca sosyal hesapla giris yapan kullanicilara
   gosterilir. Hesap sifresi belirlemis olsalar bile magaza admin girisi bu
   sifreyle yapilmadigi icin kart gizlenmez. */
$storePassOauth = false;
try {
    if ($pdo) {
        $stPP = $pdo->prepare("SELECT COUNT(*) FROM oauth_accounts WHERE user_id=?");
        $stPP->execute([$uid]);
        $storePassOauth = ((int)$stPP->fetchColumn() > 0);
    }
} catch (Throwable $ePP) { $storePassOauth = false; }

function stores_json(array $a): void {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($a, JSON_UNESCAPED_UNICODE);
    exit;
}

function stores_allowed_themes(string $plan): array {
    $plan = strtolower($plan);
    if ($plan === 'starter') return ['Default', 'Azure'];
    if ($plan === 'professional' || $plan === 'enterprise') return ['Default', 'Azure', 'Ember'];
    return ['Default'];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (($_POST['action'] ?? '') === 'create-store')) {
    $logoPath = '';
    $logoUrl = '';
    csrf_check();
    require_once __DIR__ . '/../core/turnstile.php';
    $tok = (string)($_POST['cf-turnstile-response'] ?? $_POST['cf_token'] ?? '');
    if (function_exists('turnstile_enabled') && turnstile_enabled()) {
        if (!turnstile_verify($tok !== '' ? $tok : null, $_SERVER['REMOTE_ADDR'] ?? null)) {
            stores_json(['ok' => false, 'msg' => 'Security verification failed.']);
        }
    }
    $name = mb_substr(trim((string)($_POST['store_name'] ?? '')), 0, 80);
    $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    if ($name === '' || hosting_slug_check($slug) !== '') {
        stores_json(['ok' => false, 'msg' => 'Invalid name or subdomain.']);
    }
    if (hosting_reserved($slug)) {
        stores_json(['ok' => false, 'msg' => 'This subdomain is reserved.']);
    }
    try {
        $st = $pdo->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
        $st->execute([$slug]);
        if ($st->fetch()) stores_json(['ok' => false, 'msg' => 'This subdomain is reserved.']);
        $isAdmin = (int)($user['is_admin'] ?? 0) === 1;
        $effPlan = 'free';
        try {
            if (function_exists('user_effective_plan')) {
                $ep = user_effective_plan($uid);
                $p = strtolower(trim((string)($ep['plan'] ?? 'free')));
                if (in_array($p, ['free', 'starter', 'professional', 'enterprise'], true)) $effPlan = $p;
            }
        } catch (Throwable $e0) {}
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT COUNT(*) FROM hosted_stores WHERE (user_id=? OR owner=?) AND status='active' FOR UPDATE");
        $st->execute([$uid, $uemail]);
        $have = (int)$st->fetchColumn();
        $st = $pdo->prepare("SELECT COUNT(*) FROM hosting_jobs WHERE user_id=? AND status IN ('pending','claimed') FOR UPDATE");
        $st->execute([$uid]);
        $have += (int)$st->fetchColumn();
        if ($have >= 1 && !$isAdmin) {
            $pdo->rollBack();
            if ($logoPath !== '' && is_file($logoPath)) @unlink($logoPath);
            stores_json(['ok' => false, 'msg' => 'Your plan includes 1 store. Delete it to create a new one.']);
        }
        if (!$isAdmin && $effPlan === 'free') {
            $st = $pdo->prepare("SELECT COUNT(*) FROM hosting_jobs WHERE user_id=? AND plan='free' AND kind='provision' AND status='done'");
            $st->execute([$uid]);
            if ((int)$st->fetchColumn() >= 2) {
                $pdo->rollBack();
                if ($logoPath !== '' && is_file($logoPath)) @unlink($logoPath);
                stores_json(['ok' => false, 'msg' => 'Free store limit reached (2 lifetime). Upgrade for more.']);
            }
        }
        $st = $pdo->prepare("SELECT id FROM hosted_stores WHERE slug=? LIMIT 1 FOR UPDATE");
        $st->execute([$slug]);
        if ($st->fetch()) { $pdo->rollBack(); if ($logoPath !== '' && is_file($logoPath)) @unlink($logoPath); stores_json(['ok' => false, 'msg' => 'Subdomain taken.']); }
        $st = $pdo->prepare("SELECT id FROM hosting_jobs WHERE slug=? AND status IN ('pending','claimed') LIMIT 1 FOR UPDATE");
        $st->execute([$slug]);
        if ($st->fetch()) { $pdo->rollBack(); if ($logoPath !== '' && is_file($logoPath)) @unlink($logoPath); stores_json(['ok' => false, 'msg' => 'Subdomain taken.']); }
        $st = $pdo->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
        $st->execute([$uid]);
        $ohash = (string)$st->fetchColumn();
        if (!preg_match('/^\$(2y|2a|argon2i|argon2id)\$/', $ohash)) $ohash = '';
        $cat = strtolower(trim((string)($_POST['category'] ?? 'other')));
        if (!in_array($cat, ['epin', 'ebook', 'design', 'software', 'other'], true)) $cat = 'other';
        $langSel = strtolower(trim((string)($_POST['lang'] ?? 'en')));
        if ($langSel !== 'tr' && $langSel !== 'en') $langSel = 'en';
        $logoUrl = '';
        if (isset($_FILES['logo']) && is_array($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $lf = $_FILES['logo'];
            $logoErr = '';
            if (!is_uploaded_file((string)($lf['tmp_name'] ?? ''))) {
                $logoErr = 'Logo upload failed.';
            } elseif ((int)($lf['size'] ?? 0) > 2 * 1024 * 1024) {
                $logoErr = 'Logo must be 2 MB or smaller.';
            } else {
                $gmt = (new finfo(FILEINFO_MIME_TYPE))->file((string)$lf['tmp_name']);
                if (!in_array($gmt, ['image/png', 'image/jpeg', 'image/webp'], true)) {
                    $logoErr = 'Logo must be PNG, JPG or WebP.';
                } else {
                    $gis = @getimagesize((string)$lf['tmp_name']);
                    if ($gis === false) {
                        $logoErr = 'Logo must be PNG, JPG or WebP.';
                    } elseif ((int)$gis[0] > 4096 || (int)$gis[1] > 4096) {
                        $logoErr = 'Logo image is too large.';
                    }
                }
            }
            if ($logoErr === '') {
                $src = @imagecreatefromstring((string)@file_get_contents((string)$lf['tmp_name']));
                if (!$src) {
                    $logoErr = 'Invalid image file.';
                } else {
                    $w = imagesx($src);
                    $h = imagesy($src);
                    $side = min($w, $h);
                    $sx = (int)(($w - $side) / 2);
                    $sy = (int)(($h - $side) / 2);
                    $size = $side > 512 ? 512 : $side;
                    $dst = imagecreatetruecolor($size, $size);
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                    imagefilledrectangle($dst, 0, 0, $size, $size, imagecolorallocatealpha($dst, 0, 0, 0, 127));
                    imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $size, $size, $side, $side);
                    $dir = __DIR__ . '/../uploads/store-logos';
                    if (!is_dir($dir)) @mkdir($dir, 0755, true);
                    $fname = 'logo-' . bin2hex(random_bytes(8)) . '.png';
                    $okPng = is_dir($dir) && imagepng($dst, $dir . '/' . $fname, 9);
                    imagedestroy($dst);
                    imagedestroy($src);
                    if ($okPng) {
                        $logoPath = $dir . '/' . $fname;
                        @chmod($logoPath, 0644);
                        $logoUrl = 'https://vexaris.net/uploads/store-logos/' . $fname;
                    } else {
                        $logoErr = 'Logo could not be saved. Try again.';
                    }
                }
            }
            if ($logoErr !== '') stores_json(['ok' => false, 'msg' => $logoErr]);
        }
        try {
            /* Mağazaya özel AI relay sırrı: AI istekleri bu sırla imzalanır,
               böylece başka bir mağaza bu mağazanın kotasını/kredisini tüketemez.
               (Sır konteynere provisioning ile VX_RELAY_SECRET olarak gider.) */
            $relaySecret = bin2hex(random_bytes(32));
            $dom = $slug . '.vexaris.app';
            $stR = $pdo->prepare("SELECT id, relay_secret FROM hosted_stores WHERE slug=? LIMIT 1");
            $stR->execute([$slug]);
            $rowR = $stR->fetch();
            if ($rowR) {
                $pdo->prepare("UPDATE hosted_stores SET relay_secret=COALESCE(NULLIF(relay_secret,''),?), status=CASE WHEN status='deleted' THEN 'provisioning' ELSE status END WHERE id=?")
                    ->execute([$relaySecret, (int)$rowR['id']]);
            } else {
                $pdo->prepare("INSERT INTO hosted_stores (user_id, slug, domain, plan, owner, status, relay_secret, created_at) VALUES (?,?,?,?,?,'provisioning',?,NOW())")
                    ->execute([$uid, $slug, $dom, $effPlan, $uemail, $relaySecret]);
            }
            $ins = $pdo->prepare("INSERT INTO hosting_jobs (user_id, slug, plan, owner, site_name, lang, kind, status, owner_hash, category, logo_url) VALUES (?,?, ?,?,?, ?, 'provision','pending', ?, ?, ?)");
            $ins->execute([$uid, $slug, $effPlan, $uemail, $name, $langSel, $ohash, $cat, $logoUrl]);
            $jid = (int)$pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $e2) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($logoPath !== '' && is_file($logoPath)) @unlink($logoPath);
            stores_json(['ok' => false, 'msg' => 'Subdomain taken.']);
        }
        stores_json(['ok' => true, 'job_id' => $jid]);
    } catch (Throwable $e) {
        stores_json(['ok' => false, 'msg' => 'Server error.']);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (($_POST['action'] ?? '') === 'update-store')) {
    csrf_check();
    $sid = (int)($_POST['store_id'] ?? 0);
    try {
        $st = $pdo->prepare("SELECT slug, plan, app_version, category, lang FROM hosted_stores WHERE id=? AND (user_id=? OR owner=?) AND status='active' LIMIT 1");
        $st->execute([$sid, $uid, $uemail]);
        $row = $st->fetch();
        if (!$row) stores_json(['ok' => false, 'msg' => 'Store not found.']);
        $slug = (string)$row['slug'];
        $latest = function_exists('saas_latest_version') ? saas_latest_version($pdo) : '';
        if ($latest === '' || !version_compare($latest, (string)($row['app_version'] ?? ''), '>')) {
            stores_json(['ok' => false, 'msg' => 'Already up to date.']);
        }
        $st = $pdo->prepare("SELECT id FROM hosting_jobs WHERE slug=? AND status IN ('pending','claimed') LIMIT 1");
        $st->execute([$slug]);
        if ($st->fetch()) stores_json(['ok' => false, 'msg' => 'Update already queued.']);
        $st = $pdo->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
        $st->execute([$uid]);
        $ohash = (string)$st->fetchColumn();
        if (!preg_match('/^\$(2y|2a|argon2i|argon2id)\$/', $ohash)) $ohash = '';
        $ucat = strtolower(trim((string)($row['category'] ?? 'other')));
        if (!in_array($ucat, ['epin', 'ebook', 'design', 'software', 'other'], true)) $ucat = 'other';
        $ulang = strtolower(trim((string)($row['lang'] ?? 'en')));
        if ($ulang !== 'tr' && $ulang !== 'en') $ulang = 'en';
        $ins = $pdo->prepare("INSERT INTO hosting_jobs (user_id, slug, plan, owner, site_name, lang, kind, status, owner_hash, category) VALUES (?,?,?, ?, '', ?, 'update','pending', ?, ?)");
        $ins->execute([$uid, $slug, (string)($row['plan'] ?? 'free'), $uemail, $ulang, $ohash, $ucat]);
        stores_json(['ok' => true, 'job_id' => (int)$pdo->lastInsertId(), 'version' => $latest]);
    } catch (Throwable $e) {
        stores_json(['ok' => false, 'msg' => 'Server error.']);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (($_POST['action'] ?? '') === 'custom-domain')) {
    csrf_check();
    $sid = (int)($_POST['store_id'] ?? 0);
    try {
        $st = $pdo->prepare("SELECT id, slug, plan, custom_domain FROM hosted_stores WHERE id=? AND (user_id=? OR owner=?) AND status='active' LIMIT 1");
        $st->execute([$sid, $uid, $uemail]);
        $row = $st->fetch();
        if (!$row) stores_json(['ok' => false, 'msg' => 'Store not found.']);
        $slug = (string)$row['slug'];
        $sp = strtolower(trim((string)($row['plan'] ?? 'free')));
        // SIKI KAPI: free plan custom domain ASLA baglayamaz (istemci degil, STORE plani gecerlidir).
        if (!in_array($sp, ['starter', 'professional', 'enterprise'], true)) {
            http_response_code(403);
            stores_json(['ok' => false, 'msg' => 'Custom domains require a paid plan (Starter, Professional or Enterprise).']);
        }
        $st = $pdo->prepare("SELECT id FROM hosting_jobs WHERE slug=? AND status IN ('pending','claimed') LIMIT 1");
        $st->execute([$slug]);
        if ($st->fetch()) stores_json(['ok' => false, 'msg' => 'A job is already queued for this store.']);
        $dom = strtolower(trim((string)($_POST['domain'] ?? '')));
        $dom = preg_replace('#^https?://#', '', $dom);
        $dom = trim($dom, " \t\n\r\0\x0B/.");
        if (strpos($dom, ':') !== false) $dom = (string)explode(':', $dom, 2)[0];
        if ($dom !== '') {
            if (strlen($dom) > 190 || !filter_var($dom, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) stores_json(['ok' => false, 'msg' => 'Invalid domain name.']);
            if (strpos($dom, '.') === false) stores_json(['ok' => false, 'msg' => 'Invalid domain name.']);
            if (filter_var($dom, FILTER_VALIDATE_IP)) stores_json(['ok' => false, 'msg' => 'IP addresses are not allowed.']);
            if (substr($dom, -13) === '.vexaris.app' || $dom === 'vexaris.app') stores_json(['ok' => false, 'msg' => 'Vexaris subdomains use the subdomain flow.']);
            $st = $pdo->prepare("SELECT slug FROM hosted_stores WHERE custom_domain=? AND slug<>? LIMIT 1");
            $st->execute([$dom, $slug]);
            if ($st->fetch()) stores_json(['ok' => false, 'msg' => 'This domain is already connected to another store.']);
            $st = $pdo->prepare("SELECT id FROM licenses WHERE allowed_domains LIKE ? LIMIT 1");
            $st->execute(['%"' . $dom . '"%']);
            if ($st->fetch()) stores_json(['ok' => false, 'msg' => 'This domain is already licensed elsewhere.']);
        }
        $st = $pdo->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
        $st->execute([$uid]);
        $ohash = (string)$st->fetchColumn();
        if (!preg_match('/^\$(2y|2a|argon2i|argon2id)\$/', $ohash)) $ohash = '';
        try {
            $pdo->beginTransaction();
            $ins = $pdo->prepare("INSERT INTO hosting_jobs (user_id, slug, plan, owner, site_name, lang, kind, status, owner_hash) VALUES (?,?,?, ?, ?,'en','domain','pending',?)");
            $ins->execute([$uid, $slug, $sp, $uemail, mb_substr($dom, 0, 190), $ohash]);
            $jid = (int)$pdo->lastInsertId();
            if ($dom === '') {
                $pdo->prepare("UPDATE hosted_stores SET custom_domain='', custom_status='pending' WHERE id=?")->execute([$sid]);
            } else {
                $pdo->prepare("UPDATE hosted_stores SET custom_domain=?, custom_status='pending' WHERE id=?")->execute([$dom, $sid]);
            }
            $pdo->commit();
        } catch (Throwable $e2) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            stores_json(['ok' => false, 'msg' => 'A job is already queued for this store.']);
        }
        stores_json(['ok' => true, 'job_id' => $jid, 'domain' => $dom]);
    } catch (Throwable $e) {
        stores_json(['ok' => false, 'msg' => 'Server error.']);
    }
}

if (isset($_GET['check'])) {
    $slug = strtolower(trim((string)($_GET['slug'] ?? '')));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    $slugErr = hosting_slug_check($slug);
    if ($slugErr !== '') stores_json(['ok' => true, 'available' => false, 'msg' => $slugErr]);
    if (hosting_reserved($slug)) stores_json(['ok' => true, 'available' => false, 'msg' => 'reserved']);
    try {
        $st = $pdo->prepare("SELECT id FROM licenses WHERE allowed_domains LIKE ? LIMIT 1");
        $st->execute(['%"' . $slug . '.vexaris.app"%']);
        if ($st->fetch()) stores_json(['ok' => true, 'available' => false, 'msg' => 'taken']);
        $st = $pdo->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
        $st->execute([$slug]);
        if ($st->fetch()) stores_json(['ok' => true, 'available' => false, 'msg' => 'taken']);
        $st = $pdo->prepare("SELECT id FROM hosting_jobs WHERE slug=? AND status IN ('pending','claimed') LIMIT 1");
        $st->execute([$slug]);
        if ($st->fetch()) stores_json(['ok' => true, 'available' => false, 'msg' => 'taken']);
        $st = $pdo->prepare("SELECT id FROM hosted_stores WHERE slug=? LIMIT 1");
        $st->execute([$slug]);
        if ($st->fetch()) stores_json(['ok' => true, 'available' => false, 'msg' => 'taken']);
    } catch (Throwable $e) {}
    stores_json(['ok' => true, 'available' => true, 'domain' => $slug . '.vexaris.app']);
}

if (isset($_GET['check_job'])) {
    $jid = (int)$_GET['check_job'];
    try {
        $st = $pdo->prepare("SELECT status, last_msg, slug, kind FROM hosting_jobs WHERE id=? AND user_id=? LIMIT 1");
        $st->execute([$jid, $uid]);
        $row = $st->fetch();
        if (!$row) stores_json(['ok' => false, 'msg' => 'not found']);
        $out = ['ok' => true, 'status' => $row['status'], 'message' => $row['last_msg'], 'kind' => $row['kind']];
        if ($row['status'] === 'done') $out['domain'] = $row['slug'] . '.vexaris.app';
        stores_json($out);
    } catch (Throwable $e) {
        stores_json(['ok' => false, 'msg' => 'Server error.']);
    }
}

require_once __DIR__ . '/_inc/header.php';

$store = null;
try {
    $st = $pdo->prepare("SELECT id, slug, domain, plan, category, site_name, users, products, revenue, currency, traffic_bytes, traffic_updated, app_version, status, reported_at, created_at, custom_domain, custom_status FROM hosted_stores WHERE (user_id=? OR owner=?) AND status='active' ORDER BY id DESC LIMIT 1");
    $st->execute([$uid, $uemail]);
    $store = $st->fetch() ?: null;
} catch (Throwable $e) {}
$myPlan = function_exists('user_effective_plan') ? user_effective_plan($uid) : ['plan' => 'free', 'until' => 0];
$planQuotaGB = ['free' => 50, 'starter' => 0, 'professional' => 0, 'enterprise' => 0]; // 0 = limitsiz
$hasActive = $store !== null;
$traf14 = [];
if ($store) {
    try {
        $st = $pdo->prepare("SELECT day, bytes FROM store_traffic_daily WHERE slug=? AND day > DATE_SUB(CURDATE(), INTERVAL 14 DAY) ORDER BY day ASC");
        $st->execute([(string)$store['slug']]);
        foreach ($st->fetchAll() as $r) $traf14[(string)$r['day']] = (int)$r['bytes'];
    } catch (Throwable $e) {}
}
function nx_fmt_bytes($b) {
    $b = max(0, (float)$b);
    if ($b >= 1073741824) return number_format($b / 1073741824, 2) . ' GB';
    if ($b >= 1048576) return number_format($b / 1048576, 1) . ' MB';
    if ($b >= 1024) return number_format($b / 1024, 0) . ' KB';
    return (int)$b . ' B';
}
?>
<div class="main__header">
    <h1>My Stores</h1>
    <p>One-click free store at <strong style="color:#8cb8ff;">yourname.vexaris.app</strong>.</p>
</div>

<?php if (!$store): ?>
<div class="panel-card"><div class="panel-card__body" style="text-align:center;padding:3rem 1.5rem;">
    <div style="width:72px;height:72px;margin:0 auto 1.1rem;background:linear-gradient(135deg,rgba(255,122,26,.2),rgba(255,60,17,.1));border:1px solid rgba(255,122,26,.35);border-radius:22px;display:flex;align-items:center;justify-content:center;color:#ff7a1a;font-size:28px;"><i class="fas fa-store"></i></div>
    <h3 style="margin:0 0 6px;font-size:19px;font-weight:800;">No store yet</h3>
    <p style="font-size:13px;color:#9aa3ac;margin:0 0 1.5rem;">Your plan includes 1 store on its own subdomain.<br>Create it in seconds.</p>
    <button class="btn-panel btn-cyan" id="nxNewStoreBtn" style="font-size:15px;padding:13px 34px;"><i class="fas fa-plus"></i> Create My Store</button>
</div></div>
<?php else:
$sp = strtolower($store['plan'] ?? 'free');
$quotaGB = $planQuotaGB[$sp] ?? 10;
$quotaB = $quotaGB * 1073741824;
$usedB = (int)($store['traffic_bytes'] ?? 0);
$trafPct = $quotaB > 0 ? min(100, round($usedB / $quotaB * 100, 1)) : 0;
$trafDays = [];
for ($di = 13; $di >= 0; $di--) { $dd = date('Y-m-d', strtotime("-$di days")); $trafDays[$dd] = $traf14[$dd] ?? 0; }
$trafMax = max(1, max($trafDays));
$allowed = $sp === 'starter' ? ['Default', 'Azure'] : ($sp === 'free' ? ['Default'] : ['Default', 'Azure', 'Ember']);
$sname = trim((string)($store['site_name'] ?? ''));
$latestVer = function_exists('saas_latest_version') ? saas_latest_version($pdo) : '';
$updAvail = $latestVer !== '' && version_compare($latestVer, (string)($store['app_version'] ?? ''), '>');
?>
<div class="panel-card"><div class="panel-card__body">
    <div class="nx-shead">
        <div class="nx-sic"><i class="fas fa-store"></i></div>
        <div class="nx-stxt">
            <div class="nx-sname"><?= $sname !== '' ? htmlspecialchars($sname) : htmlspecialchars($store['domain']) ?></div>
            <div class="nx-sdom"><?= htmlspecialchars($store['domain']) ?></div>
        </div>
        <?php $scat = strtolower((string)($store['category'] ?? 'other')); $scMap = ['epin' => 'E-PIN', 'ebook' => 'E-Book', 'design' => 'Design', 'software' => 'Software', 'other' => 'Other']; ?>
        <div class="nx-sbadges">
            <span class="nx-sbadge" style="background:rgba(139,92,246,.14);color:#c4a5fb;"><?= htmlspecialchars($scMap[$scat] ?? 'Other') ?></span>
            <span class="nx-sbadge" style="background:<?= $sp === 'free' ? 'rgba(255,255,255,.07);color:#cfd6e4' : 'rgba(0,212,255,.12);color:#7dd7fc' ?>;"><?= htmlspecialchars($sp) ?></span>
        </div>
    </div>
    <div style="background:rgba(255,255,255,.03);border:1px solid var(--border,rgba(255,255,255,.08));border-radius:14px;padding:12px 16px;margin-bottom:1.1rem;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <i class="fas fa-globe" style="color:#8cb8ff;"></i>
        <a href="https://<?= htmlspecialchars($store['domain']) ?>" target="_blank" rel="noopener" style="flex:1;min-width:180px;color:#8cb8ff;font-weight:800;font-size:14px;text-decoration:none;word-break:break-all;"><?= htmlspecialchars($store['domain']) ?></a>
        <button class="btn-panel" style="font-size:12px;" onclick="nxCopyUrl('https://<?= htmlspecialchars($store['domain'], ENT_QUOTES) ?>',this)"><i class="fas fa-copy"></i> Copy</button>
    </div>
    <?php if ($updAvail): ?>
    <div style="background:rgba(250,204,21,.07);border:1px solid rgba(250,204,21,.35);border-radius:14px;padding:14px 16px;margin-bottom:1.1rem;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <div style="flex:1;min-width:200px;font-size:13px;color:#facc15;"><i class="fas fa-cloud-arrow-down"></i> Update available — <strong>v<?= htmlspecialchars($latestVer) ?></strong> (yours: v<?= htmlspecialchars((string)($store['app_version'] ?? '?')) ?>). About 1 minute downtime.</div>
        <button class="btn-panel btn-cyan" style="font-size:13px;" id="nxUpdBtn" onclick="nxStartUpdate(<?= (int)$store['id'] ?>)"><i class="fas fa-rotate"></i> Update Now</button>
    </div>
    <?php endif; ?>
    <div style="background:rgba(255,255,255,.03);border:1px solid var(--border,rgba(255,255,255,.08));border-radius:14px;padding:14px 16px;margin-bottom:1.1rem;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <?php if ($storePassOauth): ?>
        <div style="flex:1 1 100%;font-size:13px;color:#9aa3ac;"><i class="fas fa-key" style="color:#ff7a1a;"></i> Store admin — username <strong style="color:var(--text,#e8eaf6);">admin</strong>. You signed in with a social account, so this store has its own password below.</div>
        <div class="nx-passbox" id="nxPassBox" style="flex:1 1 240px;">
            <div class="nx-passrow">
                <div class="nx-passval" id="nxPassVal" data-shown="0">
                    <span id="nxPassText">••••••••</span>
                </div>
                <button type="button" class="nx-passbtn" id="nxPassEye" title="Show/hide"><i class="fas fa-eye"></i></button>
                <button type="button" class="nx-passbtn" id="nxPassCopy" title="Copy"><i class="fas fa-copy"></i></button>
            </div>
            <div class="nx-passtip" id="nxPassTip">Tap to reveal the store password</div>
        </div>
        <button class="btn-panel" id="nxPassGen" style="font-size:12.5px;flex:none;"><i class="fas fa-rotate"></i> Generate new</button>
        <?php else: ?>
        <div style="flex:1;min-width:200px;font-size:13px;color:#9aa3ac;"><i class="fas fa-key" style="color:#ff7a1a;"></i> Store admin — username <strong style="color:var(--text,#e8eaf6);">admin</strong>, password is your Vexaris password (also emailed at setup).</div>
        <?php endif; ?>
        <a class="btn-panel btn-cyan" style="font-size:13px;" href="https://<?= htmlspecialchars($store['domain']) ?>/login" target="_blank" rel="noopener"><i class="fas fa-right-to-bracket"></i> Store Login</a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:1.1rem">
        <div style="background:rgba(255,255,255,.04);border:1px solid var(--border,rgba(255,255,255,.08));border-radius:14px;padding:1rem;text-align:center;">
            <div style="font-size:11px;color:var(--muted,#9aa3ac);font-weight:700;text-transform:uppercase;margin-bottom:4px;"><i class="fas fa-chart-line"></i> Revenue</div>
            <div style="font-size:20px;font-weight:900;"><?= htmlspecialchars(store_money((float)$store['revenue'], (string)($store['currency'] ?? 'TRY'))) ?></div>
        </div>
        <div style="background:rgba(255,255,255,.04);border:1px solid var(--border,rgba(255,255,255,.08));border-radius:14px;padding:1rem;text-align:center;">
            <div style="font-size:11px;color:var(--muted,#9aa3ac);font-weight:700;text-transform:uppercase;margin-bottom:4px;"><i class="fas fa-box"></i> Products</div>
            <div style="font-size:20px;font-weight:900;"><?= (int)($store['products'] ?? 0) ?></div>
        </div>
        <div style="background:rgba(255,255,255,.04);border:1px solid var(--border,rgba(255,255,255,.08));border-radius:14px;padding:1rem;text-align:center;">
            <div style="font-size:11px;color:var(--muted,#9aa3ac);font-weight:700;text-transform:uppercase;margin-bottom:4px;"><i class="fas fa-users"></i> Users</div>
            <div style="font-size:20px;font-weight:900;"><?= (int)$store['users'] ?></div>
        </div>
        <div style="background:rgba(255,255,255,.04);border:1px solid var(--border,rgba(255,255,255,.08));border-radius:14px;padding:1rem;text-align:center;">
            <div style="font-size:11px;color:var(--muted,#9aa3ac);font-weight:700;text-transform:uppercase;margin-bottom:4px;"><i class="fas fa-traffic-light"></i> Traffic</div>
            <div style="font-size:20px;font-weight:900;"><?= htmlspecialchars(nx_fmt_bytes($usedB)) ?></div>
            <div style="font-size:11px;color:#9aa3ac;"><?= $quotaGB > 0 ? 'of ' . (int)$quotaGB . ' GB' : 'unlimited' ?></div>
        </div>
    </div>
    <div style="background:rgba(255,255,255,.03);border:1px solid var(--border,rgba(255,255,255,.08));border-radius:14px;padding:14px 16px;margin-bottom:1.1rem;">
        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:.3rem;font-size:12px;font-weight:700;color:#9aa3ac;margin-bottom:8px;"><span><i class="fas fa-gauge-high"></i> Monthly bandwidth</span><span><?= $quotaGB > 0 ? htmlspecialchars(nx_fmt_bytes($usedB)) . ' / ' . (int)$quotaGB . ' GB (' . htmlspecialchars((string)$trafPct) . '%)' : htmlspecialchars(nx_fmt_bytes($usedB)) . ' used &middot; unlimited' ?></span></div>
        <div style="height:10px;border-radius:999px;background:rgba(255,255,255,.07);overflow:hidden;margin-bottom:14px;"><div style="height:100%;width:<?= htmlspecialchars((string)$trafPct) ?>%;border-radius:999px;background:linear-gradient(90deg,#ff7a1a,#2bd4ff);transition:width .6s ease;"></div></div>
        <div style="font-size:12px;font-weight:700;color:#9aa3ac;margin-bottom:8px;">Last 14 days</div>
        <div style="display:flex;align-items:flex-end;gap:5px;height:90px;">
            <?php foreach ($trafDays as $dd => $bv): $h = max(3, round($bv / $trafMax * 100)); ?>
            <div title="<?= htmlspecialchars(date('j M', strtotime($dd)) . ': ' . nx_fmt_bytes($bv)) ?>" style="flex:1;border-radius:5px 5px 2px 2px;background:linear-gradient(180deg,rgba(43,212,255,.85),rgba(255,122,26,.55));height:<?= (int)$h ?>%;min-width:0;"></div>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:10px;color:#64748b;margin-top:5px;"><span><?= htmlspecialchars(date('j M', strtotime(array_key_first($trafDays)))) ?></span><span>today: <?= htmlspecialchars(nx_fmt_bytes(end($trafDays))) ?></span><span><?= htmlspecialchars(date('j M')) ?></span></div>
        <?php if (!empty($store['traffic_updated'])): ?><div style="font-size:11px;color:#64748b;margin-top:6px;">Updated <?= htmlspecialchars((string)$store['traffic_updated']) ?></div><?php endif; ?>
    </div>
    <div style="font-size:13px;font-weight:800;margin-bottom:8px;"><i class="fas fa-paintbrush"></i> My Themes</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:1.2rem;">
        <?php foreach (['Default', 'Azure', 'Ember'] as $t): $ok = in_array($t, $allowed, true); ?>
        <div style="padding:8px 14px;border-radius:999px;font-size:12px;font-weight:800;<?= $ok ? 'background:rgba(34,197,94,.12);color:#4ade80;border:1px solid rgba(34,197,94,.35);' : 'background:rgba(255,255,255,.04);color:#64748b;border:1px solid var(--border,rgba(255,255,255,.08));' ?>"><?= $ok ? '✓ ' : '🔒 ' ?><?= htmlspecialchars($t) ?></div>
        <?php endforeach; ?>
    </div>
    <div style="font-size:13px;font-weight:800;margin-bottom:8px;"><i class="fas fa-arrow-up"></i> Upgrade Plan</div>
    <a class="btn-panel" style="display:flex;justify-content:center;align-items:center;gap:8px;width:100%;box-sizing:border-box;background:linear-gradient(135deg,rgba(250,204,21,.18),rgba(255,122,26,.14));border:1px solid rgba(250,204,21,.5);color:#fde68a;font-weight:800;font-size:14px;padding:12px;border-radius:12px;text-decoration:none;margin-bottom:1.2rem;" href="https://vexaris.net/pricing"><i class="fas fa-crown"></i> Upgrade Plan</a>
    <?php
    $cdom = trim((string)($store['custom_domain'] ?? ''));
    $cstat = strtolower(trim((string)($store['custom_status'] ?? '')));
    $cPaid = in_array($sp, ['starter', 'professional', 'enterprise'], true);
    $cBadge = $cstat === 'active' ? '<span style="font-size:10px;font-weight:800;padding:3px 10px;border-radius:999px;background:rgba(34,197,94,.15);color:#4ade80;">LIVE</span>'
        : ($cstat === 'pending' ? '<span style="font-size:10px;font-weight:800;padding:3px 10px;border-radius:999px;background:rgba(250,204,21,.15);color:#facc15;">PENDING</span>'
        : ($cstat === 'failed' ? '<span style="font-size:10px;font-weight:800;padding:3px 10px;border-radius:999px;background:rgba(239,68,68,.15);color:#f87171;">FAILED</span>'
        : ($cstat === 'revoked' ? '<span style="font-size:10px;font-weight:800;padding:3px 10px;border-radius:999px;background:rgba(255,255,255,.07);color:#9aa3ac;">REVOKED</span>' : '')));
    ?>
    <div style="font-size:13px;font-weight:800;margin-bottom:8px;"><i class="fas fa-globe" style="color:#facc15;"></i> Custom Domain <i class="fas fa-crown" title="Premium feature" style="color:#facc15;font-size:11px;"></i></div>
    <?php if ($cdom !== ''): ?>
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:rgba(255,255,255,.03);border:1px solid var(--border,rgba(255,255,255,.08));border-radius:12px;padding:11px 14px;margin-bottom:10px;font-size:13px;">
        <i class="fas fa-link" style="color:#8cb8ff;"></i>
        <a href="https://<?= htmlspecialchars($cdom) ?>" target="_blank" rel="noopener" style="color:#8cb8ff;font-weight:700;text-decoration:none;"><?= htmlspecialchars($cdom) ?></a>
        <?= $cBadge ?>
    </div>
    <div style="display:flex;align-items:center;gap:8px;font-size:12.5px;font-weight:700;margin-bottom:10px;color:#9aa3ac;">
        <?php if ($cstat === 'active'): ?>
        <span><i class="fas fa-lock" style="color:#4ade80;"></i> SSL Active · <span style="color:#4ade80;">Let's Encrypt</span></span>
        <?php elseif ($cstat === 'pending'): ?>
        <span class="nx-pulse"><i class="fas fa-lock" style="color:#facc15;"></i> SSL Issuing…</span>
        <?php elseif ($cstat === 'failed'): ?>
        <span><i class="fas fa-triangle-exclamation" style="color:#f87171;"></i> SSL Failed — check DNS records and save again</span>
        <?php elseif ($cstat === 'revoked'): ?>
        <span><i class="fas fa-lock" style="color:#64748b;"></i> SSL Revoked</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <button class="btn-panel" id="nxDomainBtn" style="display:flex;width:100%;box-sizing:border-box;justify-content:center;align-items:center;gap:8px;font-weight:800;font-size:14px;padding:12px;border-radius:12px;margin-bottom:1.2rem;background:rgba(250,204,21,.08);border:1px solid rgba(250,204,21,.4);color:#fde68a;cursor:pointer;" data-paid="<?= $cPaid ? '1' : '0' ?>" data-sid="<?= (int)$store['id'] ?>" data-domain="<?= htmlspecialchars($cdom, ENT_QUOTES) ?>" data-status="<?= htmlspecialchars($cstat, ENT_QUOTES) ?>" data-slug="<?= htmlspecialchars((string)$store['domain']) ?>"><i class="fas fa-globe"></i> Custom Domain <i class="fas fa-crown" style="font-size:11px;"></i></button>
    <div style="display:flex;gap:8px;margin-bottom:1.2rem;">
        <button class="btn-panel" style="flex:1;justify-content:center;opacity:1;background:rgba(255,122,26,.16);border:1px solid rgba(255,122,26,.55);color:#ffb37a;" onclick="nxAdminLogin()"><i class="fas fa-gauge"></i> Admin</button>
        <a class="btn-panel btn-cyan" style="flex:1;justify-content:center;" href="https://<?= htmlspecialchars($store['domain']) ?>" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> Visit</a>
    </div>
    <button class="btn-panel" style="width:100%;justify-content:center;color:#f87171;" onclick="nxDeleteFor(<?= (int)$store['id'] ?>,'<?= htmlspecialchars($store['domain'], ENT_QUOTES) ?>')"><i class="fas fa-trash"></i> Delete Store</button>
</div></div>
<?php endif; ?>

<style>.nx-cat{display:flex;align-items:center;gap:8px;padding:9px 11px;border-radius:10px;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04);color:#aab3bd;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit;width:100%;box-sizing:border-box;text-align:left;transition:border-color .15s,background .15s;}
.nx-cat.on{border-color:rgba(43,212,255,.55)!important;background:rgba(43,212,255,.1)!important;color:var(--text,#e8eaf6)!important;}
.nx-lang{display:flex;align-items:center;justify-content:center;gap:7px;padding:9px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04);color:#aab3bd;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit;flex:1;transition:border-color .15s,background .15s;}
.nx-lang.on{border-color:rgba(43,212,255,.55)!important;background:rgba(43,212,255,.1)!important;color:var(--text,#e8eaf6)!important;}
 .nx-shead{display:flex;align-items:center;gap:.8rem;margin-bottom:1.1rem;flex-wrap:wrap;}
 .nx-sic{width:52px;height:52px;flex:none;background:linear-gradient(135deg,rgba(255,122,26,.2),rgba(255,60,17,.1));border:1px solid rgba(255,122,26,.35);border-radius:15px;display:flex;align-items:center;justify-content:center;color:#ff7a1a;font-size:20px;}
 .nx-stxt{min-width:0;flex:1 1 150px;}
 .nx-sname{font-weight:800;font-size:17px;line-height:1.25;overflow-wrap:anywhere;word-break:break-word;}
 .nx-sdom{font-size:13px;color:#8cb8ff;font-weight:700;overflow-wrap:anywhere;}
 .nx-sbadges{display:flex;gap:6px;flex-wrap:wrap;}
 .nx-sbadge{font-size:10px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;padding:4px 12px;border-radius:999px;white-space:nowrap;}
 @media(max-width:520px){
   .nx-shead{gap:.65rem;margin-bottom:.9rem;}
   .nx-sic{width:44px;height:44px;border-radius:12px;font-size:17px;}
   .nx-stxt{flex:1 1 100%;}
   .nx-sname{font-size:16px;}
 }
 .nx-passbox{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:8px 10px;}
 .nx-passrow{display:flex;align-items:center;gap:6px;}
 .nx-passval{flex:1;min-width:0;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:15px;font-weight:800;color:#e8eaf6;letter-spacing:.06em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;cursor:pointer;padding:6px 4px;border-radius:8px;transition:background .15s;}
 .nx-passval:hover{background:rgba(43,212,255,.08);}
 .nx-passbtn{flex:none;width:34px;height:34px;border-radius:9px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.05);color:#9aa3ac;cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;transition:border-color .15s,color .15s;}
 .nx-passbtn:hover{border-color:rgba(43,212,255,.5);color:#7fd9ff;}
 .nx-passtip{font-size:11px;color:#64748b;margin-top:2px;padding-left:4px;}
 .nx-passtip.ok{color:#4ade80;}
 .nx-copied{position:fixed;left:50%;transform:translateX(-50%) translateY(10px);bottom:28px;background:#0f172a;border:1px solid rgba(74,222,128,.5);color:#4ade80;font-size:12.5px;font-weight:800;padding:9px 16px;border-radius:999px;z-index:10050;opacity:0;pointer-events:none;transition:opacity .2s,transform .2s;box-shadow:0 10px 30px -8px rgba(0,0,0,.8);}
 .nx-copied.show{opacity:1;transform:translateX(-50%) translateY(0);}
 @media(max-width:520px){
   .nx-passval{font-size:14px;}
 }
 .nxd-box{width:100%;max-width:440px;background:#161b22;border:1px solid rgba(239,68,68,.4);border-radius:18px;padding:1.5rem;max-height:calc(100dvh - 20px);overflow-y:auto;overscroll-behavior:contain;}
 .nxd-ic{width:52px;height:52px;margin:0 auto 12px;border-radius:15px;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.4);display:flex;align-items:center;justify-content:center;color:#f87171;font-size:21px;}
 .nxd-h{margin:0 0 8px;font-size:17.5px;font-weight:800;text-align:center;color:#f87171;}
 .nxd-p{font-size:13px;color:#9aa3ac;line-height:1.6;margin:0 0 12px;text-align:center;}
 .nxd-name,.nxd-mail{color:#e8eaf6;word-break:break-all;}
 .nxd-name{color:#fca5a5;}
 .nxd-msg{font-size:12.5px;min-height:18px;margin:4px 0 10px;text-align:center;color:#9aa3ac;line-height:1.5;}
 .nxd-actions{display:flex;gap:10px;margin-top:6px;}
 .nxd-danger{background:rgba(239,68,68,.16);border-color:rgba(239,68,68,.55);color:#fca5a5;font-weight:800;}
 .nxd-danger:hover{background:rgba(239,68,68,.26);}
 .nxd-danger:disabled{opacity:.6;cursor:wait;}
 .nxd-code{display:flex;gap:7px;justify-content:center;margin:6px 0 10px;}
 .nxd-digit{width:100%;max-width:46px;height:52px;text-align:center;font-size:21px;font-weight:800;font-family:ui-monospace,Menlo,monospace;color:#fff;background:#0f1422;border:1px solid rgba(255,255,255,.14);border-radius:10px;outline:none;transition:border-color .15s,background .15s;}
 .nxd-digit:focus{border-color:rgba(239,68,68,.7);background:#141a29;}
 .nxd-digit.filled{border-color:rgba(239,68,68,.5);}
 .nxd-resend{text-align:center;font-size:12.5px;color:#9aa3ac;margin-bottom:8px;line-height:1.5;}
 .nxd-link{background:none;border:none;color:#7fd9ff;font-weight:800;font-size:12.5px;cursor:pointer;padding:0 2px;font-family:inherit;text-decoration:underline;}
 .nxd-link:hover{color:#a8e5ff;}
 .nxd-steps{text-align:left;display:flex;flex-direction:column;gap:11px;margin:0 auto;max-width:300px;}
 .nxd-step{display:flex;gap:10px;align-items:center;font-size:13.5px;color:#9aa3ac;transition:opacity .4s;}
 .nxd-step.is-done{opacity:.45;color:#6b7280;}
 .nxd-step.is-off{opacity:.28;color:#6b7280;text-decoration:line-through;}
 .nxd-step.is-run{opacity:1;color:#fca5a5;}
 .nxd-ic-s{width:18px;text-align:center;flex:none;}
 @media(max-width:430px){
   .nxd-box{padding:1.15rem 1rem;border-radius:15px;}
   .nxd-code{gap:5px;}
   .nxd-digit{height:46px;font-size:19px;border-radius:9px;}
   .nxd-h{font-size:16px;}
 }
@keyframes nxPop{from{opacity:0;transform:translateY(14px) scale(.97);}to{opacity:1;transform:none;}}@keyframes nxFade{from{opacity:0;}to{opacity:1;}}.nx-modal-show{animation:nxFade .18s ease;}.nx-modal-show>div{animation:nxPop .22s cubic-bezier(.22,1,.36,1);}.nx-spin{animation:nxRot 1s linear infinite;}@keyframes nxRot{to{transform:rotate(360deg);}}@keyframes nxPulse{0%,100%{opacity:1;}50%{opacity:.45;}}.nx-pulse{animation:nxPulse 1.6s ease-in-out infinite;}</style>
<style>
#nxStoreModal>div,#nxDeleteModal>div,#nxDomFreeModal>div,#nxDomModal>div{margin:auto;max-height:calc(100vh - 20px);max-height:calc(100dvh - 20px);overflow-y:auto;overscroll-behavior:contain;-webkit-overflow-scrolling:touch;}
#nxStoreModal>div{padding-bottom:0!important;}
#nxSmStep2{padding-bottom:14px!important;}
.nx-modal-actions{position:sticky;bottom:0;z-index:3;background:var(--bg-card,#131929);border-top:1px solid rgba(255,255,255,.06);padding:12px 0 calc(14px + env(safe-area-inset-bottom));}
@media(max-width:520px){
  #nxStoreModal,#nxDeleteModal,#nxDomFreeModal,#nxDomModal{padding:10px!important;}
  #nxStoreModal>div{padding:18px 16px 0!important;border-radius:14px;}
  #nxStoreModal h3{font-size:16.5px;}
  #nxCatCards{gap:5px;margin-bottom:8px;}
  .nx-cat{font-size:11.5px;padding:9px 8px;gap:6px;}
  .nx-lang{font-size:11.5px;padding:9px 8px;gap:5px;}
  /* Alt alan adi satiri: 320px'te de tasmasin */
  #nxStoreSlug{font-size:16px!important;padding:11px 10px!important;}
  #nxStoreSlug+span{font-size:12px!important;padding:11px 10px!important;letter-spacing:-.01em;}
  #nxTsWrap{overflow:hidden;}
  /* Turnstile iframe'i (normal modda 300px sabit) dar ekranda kirpilmasin */
  #nxTsWrap .cf-turnstile,#nxTsWrap .cf-turnstile iframe{max-width:100%!important;width:100%!important;}
  .nx-modal-actions{padding:10px 0 calc(12px + env(safe-area-inset-bottom));}
}
@media(max-height:600px){
  #nxStoreModal>div,#nxDeleteModal>div,#nxDomFreeModal>div,#nxDomModal>div{max-height:calc(100vh - 12px);max-height:calc(100dvh - 12px);}
}
</style>
<div id="nxStoreModal" style="display:none;position:fixed;inset:0;z-index:9990;background:rgba(0,0,0,.6);align-items:center;justify-content:center;padding:16px;">
    <div style="width:100%;max-width:460px;background:var(--bg-card,#131929);border:1px solid var(--border,rgba(255,255,255,.08));border-radius:18px;padding:26px;">
        <div id="nxSmStep1">
            <h3 style="margin:0 0 4px;font-size:18px;font-weight:800;">New Free Store</h3>
            <p style="font-size:13px;color:#9aa3ac;margin:0 0 18px;">Pick a name and subdomain. Free forever.</p>
            <label style="display:block;font-size:12px;font-weight:700;color:#9aa3ac;margin-bottom:6px;">Store Name</label>
            <input id="nxStoreName" type="text" maxlength="80" placeholder="My Awesome Store" style="width:100%;box-sizing:border-box;background:var(--bg-surface,#0f1422);border:1px solid var(--border,rgba(255,255,255,.08));border-radius:10px;padding:11px 14px;color:var(--text,#e8eaf6);font-size:14px;outline:none;margin-bottom:14px;">
            <label style="display:block;font-size:12px;font-weight:700;color:#9aa3ac;margin-bottom:6px;">Logo (optional)</label>
            <div id="nxLogoDrop" style="display:flex;align-items:center;gap:10px;background:var(--bg-surface,#0f1422);border:1px dashed rgba(255,255,255,.18);border-radius:10px;padding:9px 12px;margin-bottom:6px;cursor:pointer;">
                <img id="nxLogoPrev" alt="" style="display:none;width:40px;height:40px;border-radius:8px;object-fit:cover;flex:none;">
                <div style="flex:1;min-width:0;">
                    <div id="nxLogoText" style="font-size:12.5px;color:#9aa3ac;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Drop your logo or click to browse</div>
                    <div style="font-size:11px;color:#64748b;">PNG, JPG or WebP · max 2 MB · square-cropped automatically</div>
                </div>
                <button type="button" id="nxLogoClear" style="display:none;font-size:12px;font-weight:800;color:#f87171;background:none;border:none;cursor:pointer;flex:none;">✕</button>
            </div>
            <div id="nxLogoMsg" style="font-size:12px;min-height:16px;color:#f87171;margin-bottom:8px;"></div>
            <input id="nxLogoFile" type="file" accept="image/png,image/jpeg,image/webp" style="display:none;">
            <label style="display:block;font-size:12px;font-weight:700;color:#9aa3ac;margin-bottom:6px;">What would you like to sell?</label>
            <div id="nxCatCards" style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:10px;">
                <button type="button" class="nx-cat" data-cat="epin"><i class="fas fa-key" style="width:18px;text-align:center;color:#8cb8ff;"></i> E-PIN / Account</button>
                <button type="button" class="nx-cat" data-cat="ebook"><i class="fas fa-book-open" style="width:18px;text-align:center;color:#8cb8ff;"></i> E-Book / Courses</button>
                <button type="button" class="nx-cat" data-cat="design"><i class="fas fa-pen-ruler" style="width:18px;text-align:center;color:#8cb8ff;"></i> Design</button>
                <button type="button" class="nx-cat" data-cat="software"><i class="fas fa-code" style="width:18px;text-align:center;color:#8cb8ff;"></i> Software / Bot</button>
                <button type="button" class="nx-cat" data-cat="other" style="grid-column:1 / -1;"><i class="fas fa-box" style="width:18px;text-align:center;color:#8cb8ff;"></i> Other</button>
            </div>
            <div id="nxCatMsg" style="display:none;font-size:12px;color:#f87171;margin-bottom:10px;">Pick what you want to sell to continue.</div>
            <label style="display:block;font-size:12px;font-weight:700;color:#9aa3ac;margin-bottom:6px;">Language</label>
            <div id="nxLangPick" style="display:flex;gap:8px;margin-bottom:14px;">
                <button type="button" class="nx-lang" data-lang="en"><span style="display:inline-flex;width:20px;height:14px;border-radius:3px;overflow:hidden;flex:none;"><svg width="20" height="14" viewBox="0 0 20 14"><rect width="20" height="14" fill="#012169"/><path d="M0 0l20 14M20 0L0 14" stroke="#fff" stroke-width="4"/><path d="M0 0l20 14M20 0L0 14" stroke="#c8102e" stroke-width="1.6"/><path d="M10 0v14M0 7h20" stroke="#fff" stroke-width="5"/><path d="M10 0v14M0 7h20" stroke="#c8102e" stroke-width="2.5"/></svg></span> English</button>
                <button type="button" class="nx-lang" data-lang="tr"><span style="display:inline-flex;width:20px;height:14px;border-radius:3px;overflow:hidden;flex:none;"><svg width="20" height="14" viewBox="0 0 20 14"><rect width="20" height="14" fill="#e30a17"/><circle cx="8" cy="7" r="3.2" fill="#fff"/><circle cx="9.1" cy="7" r="2.55" fill="#e30a17"/><path d="M12.4 5l.65 1.35 1.5.2-1.1 1.05.3 1.5L12.4 8.4l-1.35.7.3-1.5-1.1-1.05 1.5-.2z" fill="#fff"/></svg></span> Türkçe</button>
            </div>
            <label style="display:block;font-size:12px;font-weight:700;color:#9aa3ac;margin-bottom:6px;">Subdomain</label>
            <div style="display:flex;gap:0;margin-bottom:6px;">
                <input id="nxStoreSlug" type="text" maxlength="40" placeholder="mystore" style="flex:1;min-width:0;box-sizing:border-box;background:var(--bg-surface,#0f1422);border:1px solid var(--border,rgba(255,255,255,.08));border-radius:10px 0 0 10px;padding:11px 14px;color:var(--text,#e8eaf6);font-size:14px;outline:none;border-right:none;">
                <span style="background:rgba(255,255,255,.05);border:1px solid var(--border,rgba(255,255,255,.08));border-radius:0 10px 10px 0;padding:11px 14px;font-size:14px;color:#9aa3ac;white-space:nowrap;">.vexaris.app</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;"><div id="nxSlugMsg" style="font-size:12px;min-height:18px;"></div><div id="nxSlugCount" style="font-size:11px;color:#64748b;">0/40</div></div>
            <div id="nxTsWrap" style="margin-bottom:14px;"><?php require_once __DIR__ . '/../core/turnstile.php'; if (function_exists('turnstile_widget')) { echo turnstile_widget('nxTs'); echo turnstile_script(); } ?></div>
            <div class="nx-modal-actions" style="display:flex;gap:10px;">
                <button class="btn-panel" id="nxSmCancel" style="flex:1;">Cancel</button>
                <button class="btn-panel btn-cyan" id="nxSmCreate" style="flex:2;" disabled>Create Store</button>
            </div>
        </div>
        <div id="nxSmStep2" style="display:none;text-align:center;padding:10px 0;">
            <h3 style="margin:0 0 4px;font-size:18px;font-weight:800;">Creating your store…</h3>
            <p id="nxProgStore" style="font-size:13px;color:#8cb8ff;margin:0 0 14px;font-weight:700;"></p>
            <div style="position:relative;width:120px;height:120px;margin:0 auto 14px;">
                <svg width="120" height="120" viewBox="0 0 120 120">
                    <circle cx="60" cy="60" r="52" fill="none" stroke="rgba(255,255,255,.08)" stroke-width="10"/>
                    <circle id="nxProgRing" cx="60" cy="60" r="52" fill="none" stroke="url(#nxGrad)" stroke-width="10" stroke-linecap="round" stroke-dasharray="326.7" stroke-dashoffset="326.7" transform="rotate(-90 60 60)" style="transition:stroke-dashoffset .6s ease;"/>
                    <defs><linearGradient id="nxGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ff7a1a"/><stop offset="1" stop-color="#2bd4ff"/></linearGradient></defs>
                </svg>
                <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                    <span id="nxProgPct" style="font-size:24px;font-weight:900;">0%</span>
                    <span id="nxProgSec" style="font-size:11px;color:#9aa3ac;">0s</span>
                </div>
            </div>
            <div id="nxProgSteps" style="text-align:left;display:flex;flex-direction:column;gap:12px;margin-bottom:8px;">
                <div data-ps="queue" style="display:flex;gap:10px;align-items:center;font-size:14px;"><span class="nx-ps-ic"><i class="fas fa-clock" style="color:#64748b;"></i></span><span>Queued</span></div>
                <div data-ps="db" style="display:flex;gap:10px;align-items:center;font-size:14px;opacity:.45;"><span class="nx-ps-ic"><i class="fas fa-clock" style="color:#64748b;"></i></span><span>Database ready</span></div>
                <div data-ps="build" style="display:flex;gap:10px;align-items:center;font-size:14px;opacity:.45;"><span class="nx-ps-ic"><i class="fas fa-clock" style="color:#64748b;"></i></span><span>Container building</span></div>
                <div data-ps="done" style="display:flex;gap:10px;align-items:center;font-size:14px;opacity:.45;"><span class="nx-ps-ic"><i class="fas fa-clock" style="color:#64748b;"></i></span><span>Store live</span></div>
            </div>
            <div id="nxProgMsg" style="font-size:13px;color:#9aa3ac;min-height:20px;"></div>
            <div id="nxProgLink" style="display:none;margin-top:12px;"><a class="btn-panel btn-cyan" id="nxProgA" href="#" target="_blank" rel="noopener">Visit Your Store</a></div>
            <div style="margin-top:12px;"><button class="btn-panel" id="nxSmClose2">Close</button></div>
        </div>
    </div>
</div>

<div id="nxDeleteModal" style="display:none;position:fixed;inset:0;z-index:9995;background:rgba(0,0,0,.78);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:12px;">
    <div class="nxd-box">
        <!-- Step 1 -->
        <div id="nxdStep1">
            <div class="nxd-ic"><i class="fas fa-triangle-exclamation"></i></div>
            <h3 class="nxd-h">Delete your store?</h3>
            <p class="nxd-p">This permanently deletes <strong id="nxdDelName" class="nxd-name"></strong> and all its products, orders and uploaded files. This cannot be undone.</p>
            <p class="nxd-p">We will send a <strong>6-digit code</strong> to <strong id="nxdMail" class="nxd-mail">your email</strong>. Enter it to confirm.</p>
            <div id="nxDelMsg" class="nxd-msg"></div>
            <div class="nxd-actions">
                <button class="btn-panel" id="nxDelCancel" style="flex:1;">Cancel</button>
                <button class="btn-panel nxd-danger" id="nxDelSend" style="flex:1.3;"><i class="fas fa-paper-plane"></i> Send code</button>
            </div>
        </div>
        <!-- Step 2 -->
        <div id="nxdStep2" style="display:none;">
            <div class="nxd-ic"><i class="fas fa-envelope-open-text"></i></div>
            <h3 class="nxd-h">Enter your code</h3>
            <p class="nxd-p">We sent a 6-digit code to <strong id="nxdMail2" class="nxd-mail"></strong>. It expires in 10 minutes.</p>
            <div class="nxd-code" id="nxdCodeWrap">
                <input class="nxd-digit" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="1" aria-label="Digit 1">
                <input class="nxd-digit" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 2">
                <input class="nxd-digit" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 3">
                <input class="nxd-digit" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 4">
                <input class="nxd-digit" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 5">
                <input class="nxd-digit" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 6">
            </div>
            <div id="nxDelMsg2" class="nxd-msg"></div>
            <div class="nxd-resend">
                <span id="nxdResendTxt">Didn't get it?</span>
                <button type="button" class="nxd-link" id="nxdResend">Resend code</button>
            </div>
            <div class="nxd-actions">
                <button class="btn-panel" id="nxdBack" style="flex:1;">Back</button>
                <button class="btn-panel nxd-danger" id="nxdVerify" style="flex:1.3;"><i class="fas fa-check"></i> Delete store</button>
            </div>
        </div>
        <!-- Step 3: deleting animation (reverse of create) -->
        <div id="nxdStep3" style="display:none;text-align:center;padding:6px 0;">
            <h3 class="nxd-h" style="color:#f87171;">Deleting your store…</h3>
            <p id="nxdProgStore" class="nxd-p" style="color:#fca5a5;font-weight:700;margin-bottom:14px;"></p>
            <div style="position:relative;width:120px;height:120px;margin:0 auto 14px;">
                <svg width="120" height="120" viewBox="0 0 120 120">
                    <circle cx="60" cy="60" r="52" fill="none" stroke="rgba(239,68,68,.14)" stroke-width="10"/>
                    <circle id="nxdProgRing" cx="60" cy="60" r="52" fill="none" stroke="url(#nxdGrad)" stroke-width="10" stroke-linecap="round" stroke-dasharray="326.7" stroke-dashoffset="0" transform="rotate(-90 60 60)" style="transition:stroke-dashoffset .7s ease;"/>
                    <defs><linearGradient id="nxdGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ef4444"/><stop offset="1" stop-color="#b91c1c"/></linearGradient></defs>
                </svg>
                <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                    <span id="nxdProgPct" style="font-size:24px;font-weight:900;color:#fca5a5;">100%</span>
                    <span id="nxdProgSec" style="font-size:11px;color:#9aa3ac;">0s</span>
                </div>
            </div>
            <div id="nxdProgSteps" class="nxd-steps">
                <div data-ds="queue" class="nxd-step"><span class="nxd-ic-s"><i class="fas fa-circle-check"></i></span><span>Queued</span></div>
                <div data-ds="db" class="nxd-step"><span class="nxd-ic-s"><i class="fas fa-circle-check"></i></span><span>Database ready</span></div>
                <div data-ds="build" class="nxd-step"><span class="nxd-ic-s"><i class="fas fa-circle-check"></i></span><span>Container building</span></div>
                <div data-ds="done" class="nxd-step"><span class="nxd-ic-s"><i class="fas fa-circle-check"></i></span><span>Store live</span></div>
            </div>
            <div id="nxdProgMsg" class="nxd-p" style="min-height:20px;margin-top:10px;"></div>
            <div id="nxdProgDone" style="display:none;margin-top:14px;">
                <div style="font-size:15px;font-weight:800;color:#4ade80;margin-bottom:10px;"><i class="fas fa-circle-check"></i> Store deleted</div>
                <button class="btn-panel btn-cyan" id="nxdNewStore" style="width:100%;justify-content:center;"><i class="fas fa-plus"></i> Create a new store</button>
            </div>
        </div>
    </div>
</div>

<div id="nxDomFreeModal" style="display:none;position:fixed;inset:0;z-index:9995;background:rgba(0,0,0,.7);align-items:center;justify-content:center;padding:16px;">
    <div style="width:100%;max-width:420px;background:#131929;border:1px solid rgba(250,204,21,.4);border-radius:18px;padding:1.7rem;text-align:center;">
        <div style="width:64px;height:64px;margin:0 auto 1rem;background:linear-gradient(135deg,rgba(250,204,21,.25),rgba(255,122,26,.12));border:1px solid rgba(250,204,21,.5);border-radius:20px;display:flex;align-items:center;justify-content:center;color:#facc15;font-size:26px;"><i class="fas fa-crown"></i></div>
        <h3 style="margin:0 0 6px;font-size:18px;font-weight:800;">Custom Domains are Premium</h3>
        <p style="font-size:13px;color:#9aa3ac;margin:0 0 18px;line-height:1.6;">Connect your own domain (e.g. <strong style="color:#e8eaf6;">shop.yourbrand.com</strong>) with automatic SSL.<br>Available on <strong style="color:#fde68a;">Starter, Professional</strong> and <strong style="color:#fde68a;">Enterprise</strong> plans.</p>
        <div style="display:flex;gap:10px;">
            <button class="btn-panel" id="nxDomFreeClose" style="flex:1;">Close</button>
            <a class="btn-panel" style="flex:2;text-decoration:none;justify-content:center;background:linear-gradient(135deg,rgba(250,204,21,.2),rgba(255,122,26,.15));border:1px solid rgba(250,204,21,.55);color:#fde68a;font-weight:800;" href="https://vexaris.net/pricing"><i class="fas fa-crown"></i> View Plans</a>
        </div>
    </div>
</div>

<div id="nxDomModal" style="display:none;position:fixed;inset:0;z-index:9995;background:rgba(0,0,0,.7);align-items:center;justify-content:center;padding:16px;">
    <div style="width:100%;max-width:480px;max-height:92vh;overflow-y:auto;background:#131929;border:1px solid rgba(250,204,21,.4);border-radius:18px;padding:1.6rem;">
        <h3 style="margin:0 0 4px;font-size:18px;font-weight:800;"><i class="fas fa-globe" style="color:#facc15;"></i> Custom Domain <i class="fas fa-crown" style="color:#facc15;font-size:13px;"></i></h3>
        <p style="font-size:13px;color:#9aa3ac;margin:0 0 16px;">Point your domain to this store. SSL certificate is issued automatically.</p>
        <div id="nxDomCur" style="font-size:13px;margin-bottom:14px;"></div>
        <label style="display:block;font-size:12px;font-weight:700;color:#9aa3ac;margin-bottom:6px;">Domain name</label>
        <input id="nxDomInput" type="text" maxlength="190" placeholder="shop.example.com" autocomplete="off" spellcheck="false" style="width:100%;box-sizing:border-box;background:#0f1422;border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:11px 14px;color:#e8eaf6;font-size:14px;outline:none;margin-bottom:14px;">
        <div style="font-size:12px;font-weight:800;color:#9aa3ac;margin-bottom:8px;">DNS records — add ONE of these at your registrar:</div>
        <div style="background:#0f1422;border:1px solid rgba(255,255,255,.08);border-radius:12px;margin-bottom:8px;overflow:hidden;">
            <div style="display:flex;align-items:center;gap:8px;padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.06);font-size:12.5px;">
                <span style="font-weight:900;color:#2bd4ff;width:52px;flex:none;">A</span>
                <span style="color:#64748b;flex:none;">@</span>
                <code id="nxDomARec" style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;color:#e8eaf6;font-size:12.5px;">45.141.202.76</code>
                <button class="btn-panel" style="padding:6px 10px;font-size:11px;flex:none;" onclick="nxCopyDom('nxDomARec',this)"><i class="fas fa-copy"></i></button>
            </div>
            <div style="display:flex;align-items:center;gap:8px;padding:10px 12px;font-size:12.5px;">
                <span style="font-weight:900;color:#2bd4ff;width:52px;flex:none;">CNAME</span>
                <span style="color:#64748b;flex:none;">host</span>
                <code id="nxDomCRec" style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;color:#e8eaf6;font-size:12.5px;"></code>
                <button class="btn-panel" style="padding:6px 10px;font-size:11px;flex:none;" onclick="nxCopyDom('nxDomCRec',this)"><i class="fas fa-copy"></i></button>
            </div>
        </div>
        <p style="font-size:11.5px;color:#64748b;margin:0 0 14px;line-height:1.6;">Use the A record for apex domains (example.com) or the CNAME for subdomains (shop.example.com). DNS changes can take a few minutes.</p>
        <div id="nxDomMsg" style="font-size:12px;min-height:18px;margin-bottom:8px;"></div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn-panel" id="nxDomCancel" style="flex:1;">Cancel</button>
            <button class="btn-panel" id="nxDomRemove" style="flex:1;color:#f87171;">Remove</button>
            <button class="btn-panel btn-cyan" id="nxDomSave" style="flex:2 1 180px;font-weight:800;">Save Domain</button>
        </div>
    </div>
</div>
<script>
var NXD = null;
var NX_SLUG = <?= json_encode($store ? (string)$store['slug'] : '') ?>;
(function(){
/* Modallar .main (position:relative;z-index:1) icinde oldugu icin kendi
   stacking context'ine hapsoluyordu: sabit sidebar/mobil menu (z=100/200)
   modalin USTUNE ciziliyor, sabit konum da sayfa kaydirinca kayiyordu.
   Bu yuzden modallar body'ye tasinir. */
['nxStoreModal','nxDeleteModal','nxDomFreeModal','nxDomModal'].forEach(function(id){
  var el=document.getElementById(id);
  if(el&&el.parentElement!==document.body)document.body.appendChild(el);
});
var nxOpen=0;
window.nxBodyLock=function(on){ nxOpen=Math.max(0,nxOpen+(on?1:-1)); document.body.style.overflow=nxOpen>0?'hidden':''; };
window.nxModalOpen=function(id){
  var el=document.getElementById(id); if(!el)return;
  el.classList.remove('nx-modal-show'); void el.offsetWidth;
  el.style.display='flex'; el.classList.add('nx-modal-show'); window.nxBodyLock(true);
};
window.nxModalClose=function(id){
  var el=document.getElementById(id); if(!el)return;
  if(el.style.display==='none')return;
  el.style.display='none'; el.classList.remove('nx-modal-show'); window.nxBodyLock(false);
};
document.addEventListener('keydown',function(e){
  if(e.key!=='Escape')return;
  /* Silme modalinda kod/asamalar acikken yanlislikla kapanmasin. */
  var dm=document.getElementById('nxDeleteModal');
  var dmBusy=dm&&dm.style.display!=='none'&&((document.getElementById('nxdStep2')||{}).style||{}).display==='block';
  ['nxStoreModal','nxDomFreeModal','nxDomModal'].forEach(function(id){ window.nxModalClose(id); });
  if(dmBusy)return;
  window.nxModalClose('nxDeleteModal');
});
})();
(function(){
var modal=document.getElementById('nxStoreModal');
var openBtn=document.getElementById('nxNewStoreBtn');
function openM(){ if(modal){window.nxModalOpen('nxStoreModal');document.getElementById('nxSmStep1').style.display='block';document.getElementById('nxSmStep2').style.display='none';} }
function closeM(){ if(modal){window.nxModalClose('nxStoreModal');} document.getElementById('nxSmStep1').style.display='block'; document.getElementById('nxSmStep2').style.display='none'; }
if(openBtn){openBtn.addEventListener('click',openM);}
var c1=document.getElementById('nxSmCancel'); if(c1){c1.addEventListener('click',closeM);}
var c2=document.getElementById('nxSmClose2'); if(c2){c2.addEventListener('click',closeM);}
if(modal){modal.addEventListener('click',function(e){ if(e.target===modal)closeM(); });}
var slugEl=document.getElementById('nxSlugMsg'),createBtn=document.getElementById('nxSmCreate'),slugIn=document.getElementById('nxStoreSlug');
var slugTimer=null,slugOk=false;
var nxCat='',nxLang='en',nxLogo=null,nxLogoUrl='';
function nxRefreshBtn(){ if(createBtn)createBtn.disabled=!(slugOk&&nxCat); }
function nxPickCat(c){ nxCat=c||''; var cm=document.getElementById('nxCatMsg'); if(nxCat&&cm)cm.style.display='none'; nxRefreshBtn(); }
function nxPickLang(l){ nxLang=(l==='tr')?'tr':'en'; }
function setMsg(t,ok){ slugEl.textContent=t||''; slugEl.style.color=ok?'#4ade80':'#f87171'; slugOk=!!ok; nxRefreshBtn(); }
(function(){
  var nl=document.getElementById('nxLangPick');
  if(nl){ nl.querySelectorAll('.nx-lang').forEach(function(b){
    b.addEventListener('click',function(){ nxPickLang(b.getAttribute('data-lang')); nl.querySelectorAll('.nx-lang').forEach(function(x){x.classList.toggle('on',x===b);}); });
  }); }
  var en=document.querySelector('#nxLangPick .nx-lang[data-lang="en"]'); if(en)en.classList.add('on');
  var nc=document.getElementById('nxCatCards');
  if(nc){ nc.querySelectorAll('.nx-cat').forEach(function(b){
    b.addEventListener('click',function(){ nxPickCat(b.getAttribute('data-cat')); nc.querySelectorAll('.nx-cat').forEach(function(x){x.classList.toggle('on',x===b);}); });
  }); }
  var drop=document.getElementById('nxLogoDrop'),file=document.getElementById('nxLogoFile'),prev=document.getElementById('nxLogoPrev'),lmsg=document.getElementById('nxLogoMsg'),ltext=document.getElementById('nxLogoText'),lclr=document.getElementById('nxLogoClear');
  function nxSetLogo(f){
    lmsg.textContent='';
    if(!f)return;
    if(f.type!=='image/png'&&f.type!=='image/jpeg'&&f.type!=='image/webp'){ lmsg.textContent='Logo must be PNG, JPG or WebP.'; return; }
    if(f.size>2*1024*1024){ lmsg.textContent='Logo must be 2 MB or smaller.'; return; }
    nxLogo=f;
    if(nxLogoUrl){URL.revokeObjectURL(nxLogoUrl);}
    nxLogoUrl=URL.createObjectURL(f);
    prev.src=nxLogoUrl;prev.style.display='block';
    ltext.textContent=f.name;
    lclr.style.display='block';
  }
  function nxClearLogo(){
    nxLogo=null;
    if(nxLogoUrl){URL.revokeObjectURL(nxLogoUrl);nxLogoUrl='';}
    prev.removeAttribute('src');prev.style.display='none';
    ltext.textContent='Drop your logo or click to browse';
    lclr.style.display='none';
    file.value='';
  }
  if(drop){
    drop.addEventListener('click',function(e){ if(e.target===lclr)return; file.click(); });
    ['dragover','dragenter'].forEach(function(ev){ drop.addEventListener(ev,function(e){e.preventDefault();drop.style.borderColor='rgba(43,212,255,.6)';}); });
    ['dragleave','dragend'].forEach(function(ev){ drop.addEventListener(ev,function(e){e.preventDefault();drop.style.borderColor='';}); });
    drop.addEventListener('drop',function(e){ e.preventDefault(); drop.style.borderColor=''; if(e.dataTransfer&&e.dataTransfer.files&&e.dataTransfer.files.length)nxSetLogo(e.dataTransfer.files[0]); });
    ['dragover','drop'].forEach(function(ev){ document.addEventListener(ev,function(e){ if(drop.contains(e.target))return; e.preventDefault(); }); });
  }
  if(lclr){ lclr.addEventListener('click',function(e){ e.stopPropagation(); nxClearLogo(); }); }
  if(file){ file.addEventListener('change',function(){ nxSetLogo(file.files&&file.files[0]); }); }
})();
var slugCount=document.getElementById('nxSlugCount');
if(slugIn){slugIn.addEventListener('input',function(){
  clearTimeout(slugTimer); setMsg('',false);
  var v=slugIn.value.toLowerCase().replace(/[^a-z0-9\-]/g,''); if(v.length>40){v=v.substring(0,40);} slugIn.value=v;
  if(slugCount){slugCount.textContent=v.length+'/40';}
  if(v.length<5){ if(v.length>0) setMsg('At least 5 characters.',false); return; }
  if(/^[0-9]+$/.test(v)){ setMsg('Cannot be only numbers.',false); return; }
  if(v[0]==='-'||v[v.length-1]==='-'){ setMsg('Cannot start or end with a hyphen.',false); return; }
  if(v.indexOf('--')!==-1){ setMsg('No double hyphens.',false); return; }
  slugTimer=setTimeout(function(){
    setMsg('Checking…',false);
    fetch('/dashboard/stores?check=1&slug='+encodeURIComponent(v)).then(function(r){return r.json()}).then(function(d){
      if(d&&d.available){setMsg('✓ '+d.domain+' is available',true);}else{setMsg('✕ '+(d.msg||'taken'),false);}
    }).catch(function(){setMsg('Check failed, retry.',false);});
  },450);
});}
function csrf(){var m=document.querySelector('meta[name="csrf-token"]');return m?m.getAttribute('content'):'';}
function nxCopyUrl(u,btn){ var t=btn?btn.innerHTML:''; function ok(){ if(btn){btn.innerHTML='<i class="fas fa-check"></i> Copied';setTimeout(function(){btn.innerHTML=t;},1500);} } if(navigator.clipboard){navigator.clipboard.writeText(u).then(ok,function(){});} else { var i=document.createElement('input');i.value=u;document.body.appendChild(i);i.select();try{document.execCommand('copy');ok();}catch(e){}document.body.removeChild(i); } }
function tsToken(){var el=document.querySelector('#nxTsWrap [name="cf-turnstile-response"]');return el?el.value:'';}
if(createBtn){createBtn.addEventListener('click',function(){
  if(!slugOk||!nxCat){ if(!nxCat){var cm=document.getElementById('nxCatMsg');if(cm)cm.style.display='block';} return; }
  createBtn.disabled=true;
  createBtn.innerHTML='<i class="fas fa-circle-notch nx-spin"></i> Creating…';
  nxResetProg();
  progT0=Date.now();
  clearInterval(progTick);
  progTick=setInterval(function(){document.getElementById('nxProgSec').textContent=Math.floor((Date.now()-progT0)/1000)+'s';},500);
  var nm=document.getElementById('nxStoreName').value;
  document.getElementById('nxProgStore').textContent=(nm?nm+' · ':'')+slugIn.value+'.vexaris.app';
  nxPct(5);
  document.getElementById('nxSmStep1').style.display='none';
  document.getElementById('nxSmStep2').style.display='block';
  setStep('queue','run');
  document.getElementById('nxProgMsg').textContent='Sending request…';
  document.getElementById('nxProgLink').style.display='none';
  var fd=new FormData();
  fd.append('action','create-store');
  fd.append('store_name',document.getElementById('nxStoreName').value);
  fd.append('slug',slugIn.value);
  fd.append('cf-turnstile-response',tsToken());
  fd.append('category',nxCat);
  fd.append('lang',nxLang);
  if(nxLogo)fd.append('logo',nxLogo);
  createBtn.disabled=true;
  fetch('/dashboard/stores',{method:'POST',headers:{'X-CSRF-Token':csrf()},body:fd}).then(function(r){return r.json()}).then(function(d){
    if(d&&d.ok&&d.job_id){startProgress(d.job_id);}else{backToForm((d&&d.msg)||'Failed');}
  }).catch(function(){backToForm('Failed');});
});}
function backToForm(msg){
  document.getElementById('nxSmStep2').style.display='none';
  document.getElementById('nxSmStep1').style.display='block';
  nxRefreshBtn();
  createBtn.innerHTML='Create Store';
  var rows=document.querySelectorAll('#nxProgSteps [data-ps]');
  rows.forEach(function(r){r.style.opacity='.45';r.querySelector('.nx-ps-ic').innerHTML='<i class="fas fa-clock" style="color:#64748b;"></i>';});
  document.getElementById('nxProgLink').style.display='none';
  if(msg){alert(msg);}
}
var progTimer=null,progT0=0,progTick=null;
function nxPct(p){
  p=Math.max(0,Math.min(100,Math.round(p)));
  document.getElementById('nxProgPct').textContent=p+'%';
  document.getElementById('nxProgRing').style.strokeDashoffset=(326.7*(1-p/100)).toFixed(1);
}
function nxResetProg(){
  nxPct(0);
  document.getElementById('nxProgSec').textContent='0s';
  document.getElementById('nxProgStore').textContent='';
  var rows=document.querySelectorAll('#nxProgSteps [data-ps]');
  rows.forEach(function(r){r.style.opacity='.45';r.querySelector('.nx-ps-ic').innerHTML='<i class="fas fa-clock" style="color:#64748b;"></i>';});
}
function setStep(id,state){
  var rows=document.querySelectorAll('#nxProgSteps [data-ps]');
  rows.forEach(function(r){
    var on=r.getAttribute('data-ps')===id;
    if(state==='done'&&on){r.querySelector('.nx-ps-ic').innerHTML='<i class="fas fa-circle-check" style="color:#4ade80;"></i>';r.style.opacity='1';}
    if(state==='run'&&on){r.querySelector('.nx-ps-ic').innerHTML='<i class="fas fa-circle-notch nx-spin" style="color:#8cb8ff;"></i>';r.style.opacity='1';}
  });
}
function startProgress(job){
  setStep('queue','run');
  var msg=document.getElementById('nxProgMsg');
  msg.textContent='Queued…';
  clearInterval(progTimer);
  progTimer=setInterval(function(){
    fetch('/dashboard/stores?check_job='+job).then(function(r){return r.json()}).then(function(d){
      if(!d||!d.ok){msg.textContent='Error checking status.';return;}
      var st=d.status;
      if(st==='pending'){nxPct(12);msg.textContent='Queued…';}
      else if(st==='claimed'){nxPct(45);setStep('queue','done');setStep('db','run');setStep('build','run');msg.textContent='Building database and container…';}
      else if(st==='done'){clearInterval(progTimer);clearInterval(progTick);nxPct(100);setStep('queue','done');setStep('db','done');setStep('build','done');setStep('done','done');msg.textContent='Ready! Reloading…';setTimeout(function(){location.reload();},2200);}
      else if(st==='failed'){clearInterval(progTimer);clearInterval(progTick);msg.textContent='Failed: '+(d.message||'error');}
    }).catch(function(){});
  },1500);
}
window.openStoreDelete=function(){
  if(!NXD)return;
  document.getElementById('nxdDelName').textContent=NXD.domain||'';
  nxDelGoStep(1);
  nxDelClearCode();
  nxDelMsg('', '');
  nxDelMsg2('', '');
  var mail='<?= htmlspecialchars($uemail, ENT_QUOTES) ?>';
  document.getElementById('nxdMail').textContent=nxDelMask(mail);
  document.getElementById('nxdMail2').textContent=nxDelMask(mail);
  window.nxModalOpen('nxDeleteModal');
};
function nxDelMask(e){ if(!e)return 'your email'; var at=e.indexOf('@'); if(at<=1)return e; return e.charAt(0)+'***'+e.slice(at); }
window.nxDelGoStep=function(n){
  [1,2,3].forEach(function(i){ var el=document.getElementById('nxdStep'+i); if(el)el.style.display=(i===n?'block':'none'); });
  var box=document.querySelector('#nxDeleteModal .nxd-box'); if(box&&n===2){ setTimeout(function(){ var f=document.querySelector('.nxd-digit'); if(f)f.focus(); },120); }
}
function nxDelMsg(t,color){ var m=document.getElementById('nxDelMsg'); if(!m)return; m.textContent=t||''; m.style.color=color||'#9aa3ac'; }
function nxDelMsg2(t,color){ var m=document.getElementById('nxDelMsg2'); if(!m)return; m.textContent=t||''; m.style.color=color||'#9aa3ac'; }
function nxDelClearCode(){ var ds=document.querySelectorAll('.nxd-digit'); ds.forEach(function(d){ d.value=''; d.classList.remove('filled'); }); if(ds[0])ds[0].focus(); }
function nxDelCode(){ var ds=document.querySelectorAll('.nxd-digit'); var v=''; ds.forEach(function(d){ v+=(d.value||''); }); return v.replace(/[^0-9]/g,''); }
function nxDelResendState(s){
  var btn=document.getElementById('nxdResend'), txt=document.getElementById('nxdResendTxt');
  if(!btn)return;
  if(s==='locked'){ btn.style.display='none'; txt.textContent='No more codes for this request. Close the window and start again later.'; return; }
  if(s==='wait'){ btn.style.display='none'; txt.textContent='You can request another code in 5 minutes.'; return; }
  btn.style.display='inline'; txt.textContent="Didn't get it?";
}
window.nxDeleteFor=function(id,domain){NXD={id:id,domain:domain};openStoreDelete();};
window.nxDeleteFor=function(id,domain){NXD={id:id,domain:domain};openStoreDelete();};
window.nxStartUpdate=function(sid){
  var b=document.getElementById('nxUpdBtn');if(b){b.disabled=true;b.innerHTML='<i class="fas fa-circle-notch nx-spin"></i> Queuing…';}
  var fd=new FormData();fd.append('action','update-store');fd.append('store_id',sid);
  fetch('/dashboard/stores',{method:'POST',headers:{'X-CSRF-Token':csrf()},body:fd}).then(function(r){return r.json()}).then(function(d){
    if(d&&d.ok&&d.job_id){
      window.nxModalOpen('nxStoreModal');
      document.getElementById('nxSmStep1').style.display='none';
      document.getElementById('nxSmStep2').style.display='block';
      nxResetProg();progT0=Date.now();
      clearInterval(progTick);
      progTick=setInterval(function(){document.getElementById('nxProgSec').textContent=Math.floor((Date.now()-progT0)/1000)+'s';},500);
      document.getElementById('nxProgStore').textContent='Updating to v'+(d.version||'latest')+'…';
      nxPct(5);setStep('queue','run');
      document.getElementById('nxProgMsg').textContent='Queued…';
      document.getElementById('nxProgLink').style.display='none';
      startProgress(d.job_id);
    }else{
      if(b){b.disabled=false;b.innerHTML='<i class="fas fa-rotate"></i> Update Now';}
      alert((d&&d.msg)||'Failed');
    }
  }).catch(function(){if(b){b.disabled=false;}alert('Failed');});
};
window.nxCopyDom=function(id,btn){
  var el=document.getElementById(id);if(!el)return;
  var t=el.textContent||'';
  function done(){if(btn){var o=btn.innerHTML;btn.innerHTML='<i class="fas fa-check"></i>';setTimeout(function(){btn.innerHTML=o;},1200);}}
  if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(t).then(done).catch(function(){fallback();});}
  else fallback();
  function fallback(){var ta=document.createElement('textarea');ta.value=t;document.body.appendChild(ta);ta.select();try{document.execCommand('copy');done();}catch(e){}ta.remove();}
};
function nxShowDom(id){window.nxModalOpen(id);}
function nxHideDom(id){window.nxModalClose(id);}
var domBtn=document.getElementById('nxDomainBtn');
if(domBtn){domBtn.addEventListener('click',function(){
  if(domBtn.getAttribute('data-paid')!=='1'){nxShowDom('nxDomFreeModal');return;}
  var cur=domBtn.getAttribute('data-domain')||'',st=domBtn.getAttribute('data-status')||'';
  document.getElementById('nxDomInput').value=cur;
  document.getElementById('nxDomCRec').textContent=domBtn.getAttribute('data-slug')||'';
  var badge=st==='active' ? '<span style="color:#4ade80;font-weight:800;">● LIVE</span>'
    : (st==='pending' ? '<span style="color:#facc15;font-weight:800;">● DNS check pending — save again after records propagate</span>'
    : (st==='failed' ? '<span style="color:#f87171;font-weight:800;">● FAILED — check DNS records and save again</span>'
    : (st==='revoked' ? '<span style="color:#9aa3ac;font-weight:800;">● REVOKED</span>' : '<span style="color:#64748b;">No custom domain yet</span>')));
  document.getElementById('nxDomCur').innerHTML=(cur?('Current: <strong>'+cur.replace(/</g,'&lt;')+'</strong> — '):'')+badge;
  document.getElementById('nxDomMsg').textContent='';
  nxShowDom('nxDomModal');
});}
var dfc=document.getElementById('nxDomFreeClose');
if(dfc){dfc.addEventListener('click',function(){nxHideDom('nxDomFreeModal');});}
var dfm=document.getElementById('nxDomFreeModal');
if(dfm){dfm.addEventListener('click',function(e){if(e.target===dfm)nxHideDom('nxDomFreeModal');});}
var dc=document.getElementById('nxDomCancel');
if(dc){dc.addEventListener('click',function(){nxHideDom('nxDomModal');});}
var dm=document.getElementById('nxDomModal');
if(dm){dm.addEventListener('click',function(e){if(e.target===dm)nxHideDom('nxDomModal');});}
function nxDomPost(domain,btn,label){
  var m=document.getElementById('nxDomMsg');m.textContent='Saving…';m.style.color='#9aa3ac';
  if(btn){btn.disabled=true;btn.innerHTML='<i class="fas fa-circle-notch nx-spin"></i> '+(label||'Saving…');}
  var fd=new FormData();fd.append('action','custom-domain');fd.append('store_id',domBtn.getAttribute('data-sid'));fd.append('domain',domain);
  fetch('/dashboard/stores',{method:'POST',headers:{'X-CSRF-Token':csrf()},body:fd}).then(function(r){return r.json()}).then(function(d){
    if(d&&d.ok&&d.job_id){m.textContent='Queued — issuing SSL, live status shortly…';m.style.color='#facc15';nxDomPoll(d.job_id,m,btn,label);}
    else{m.textContent=(d&&d.msg)||'Failed';m.style.color='#f87171';if(btn){btn.disabled=false;btn.innerHTML=label||'Save Domain';}}
  }).catch(function(){m.textContent='Failed';m.style.color='#f87171';if(btn){btn.disabled=false;}});
}
var nxDomTries=0;
function nxDomPoll(job,m,btn,label){
  nxDomTries=0;
  var t=setInterval(function(){
    nxDomTries++;
    fetch('/dashboard/stores?check_job='+job).then(function(r){return r.json()}).then(function(d){
      if(!d||!d.ok)return;
      if(d.status==='done'){clearInterval(t);m.textContent='Done! Reloading…';m.style.color='#4ade80';setTimeout(function(){location.reload();},1500);}
      else if(d.status==='failed'){clearInterval(t);m.textContent='Failed: '+(d.message||'error');m.style.color='#f87171';if(btn){btn.disabled=false;btn.innerHTML=label||'Save Domain';}}
      else if(nxDomTries>60){clearInterval(t);m.textContent='Still working — reload this page in a minute.';m.style.color='#facc15';}
      else{m.textContent='Working… ('+d.status+') — issuing SSL, please wait.';m.style.color='#facc15';}
    }).catch(function(){});
  },5000);
}
var ds=document.getElementById('nxDomSave');
if(ds){ds.addEventListener('click',function(){nxDomPost(document.getElementById('nxDomInput').value,ds,'Save Domain');});}
var dr=document.getElementById('nxDomRemove');
if(dr){dr.addEventListener('click',function(){if(!confirm('Remove the custom domain?'))return;nxDomPost('',dr,'Remove');});}
(function(){
  var box=document.getElementById('nxPassBox');
  if(!box)return;
  var val=document.getElementById('nxPassVal');
  var txt=document.getElementById('nxPassText');
  var tip=document.getElementById('nxPassTip');
  var eye=document.getElementById('nxPassEye');
  var cp=document.getElementById('nxPassCopy');
  var gen=document.getElementById('nxPassGen');
  var plain='';
  var toastEl=null;
  function toast(msg){
    if(!toastEl){toastEl=document.createElement('div');toastEl.className='nx-copied';document.body.appendChild(toastEl);}
    toastEl.textContent=msg;toastEl.classList.add('show');
    clearTimeout(toast._t);toast._t=setTimeout(function(){toastEl.classList.remove('show');},1800);
  }
  function render(){
    if(!plain){txt.textContent='••••••••';return;}
    txt.textContent=val.getAttribute('data-shown')==='1'?plain:'••••••••';
  }
  function reveal(){
    if(plain){val.setAttribute('data-shown',val.getAttribute('data-shown')==='1'?'0':'1');render();return;}
    var fd=new FormData();
    fd.append('action','store-pass');fd.append('action_mode','reveal');fd.append('slug',NX_SLUG);
    fetch('/api/admin-token.php',{method:'POST',headers:{'X-CSRF-Token':csrf()},body:fd})
      .then(function(r){return r.json()})
      .then(function(d){
        if(d&&d.ok&&d.password){plain=d.password;val.setAttribute('data-shown','1');render();tip.textContent='Tap to hide';tip.classList.add('ok');}
        else{tip.textContent='No store password yet — tap Generate new';tip.classList.remove('ok');}
      }).catch(function(){tip.textContent='Could not load the password, try again';});
  }
  function copy(){
    if(!plain){tip.textContent='Tap the password first to reveal it';return;}
    var done=function(){toast('Store password copied');tip.textContent='Copied to clipboard';tip.classList.add('ok');};
    if(navigator.clipboard&&navigator.clipboard.writeText){
      navigator.clipboard.writeText(plain).then(done,function(){fallback();});
    } else fallback();
    function fallback(){
      var ta=document.createElement('textarea');ta.value=plain;ta.style.position='fixed';ta.style.opacity='0';
      document.body.appendChild(ta);ta.select();try{document.execCommand('copy');done();}catch(e){}ta.remove();
    }
  }
  val.addEventListener('click',function(e){e.preventDefault();reveal();});
  val.addEventListener('mouseenter',function(){if(plain&&val.getAttribute('data-shown')!=='1'){val.setAttribute('data-shown','1');render();}});
  val.addEventListener('mouseleave',function(){if(plain&&val.getAttribute('data-shown')==='1'&&!('ontouchstart' in window)){val.setAttribute('data-shown','0');render();}});
  eye.addEventListener('click',function(e){e.preventDefault();reveal();});
  cp.addEventListener('click',function(e){e.preventDefault();copy();});
  if(gen){gen.addEventListener('click',function(e){
    e.preventDefault();
    if(!confirm('Generate a new store password? The current one stops working immediately.'))return;
    var old=gen.innerHTML;gen.disabled=true;gen.innerHTML='<i class="fas fa-circle-notch nx-spin"></i> Generating…';
    var fd=new FormData();
    fd.append('action','store-pass');fd.append('slug',NX_SLUG);
    fetch('/api/admin-token.php',{method:'POST',headers:{'X-CSRF-Token':csrf()},body:fd})
      .then(function(r){return r.json()})
      .then(function(d){
        gen.disabled=false;gen.innerHTML=old;
        if(d&&d.ok&&d.password){plain=d.password;val.setAttribute('data-shown','1');render();tip.textContent='New password active — it may take a few seconds to apply to the store';tip.classList.add('ok');toast('New store password generated');}
        else{tip.textContent=(d&&d.msg)||'Failed to generate password';tip.classList.remove('ok');}
      }).catch(function(){gen.disabled=false;gen.innerHTML=old;tip.textContent='Failed to generate password';});
  });}
})();
window.nxAdminLogin=function(){
  if(!NX_SLUG)return;
  var fd=new FormData();fd.append('action','issue-owner');fd.append('slug',NX_SLUG);
  fetch('/api/admin-token.php',{method:'POST',headers:{'X-CSRF-Token':csrf()},body:fd}).then(function(r){return r.json()}).then(function(d){
    if(d&&d.ok&&d.url){window.open(d.url,'_blank','noopener');}else{alert((d&&d.msg)||'Failed');}
  }).catch(function(){alert('Failed');});
};

(function(){
  var btnSend=document.getElementById('nxDelSend');
  var btnBack=document.getElementById('nxdBack');
  var btnVerify=document.getElementById('nxdVerify');
  var btnResend=document.getElementById('nxdResend');
  var digits=Array.prototype.slice.call(document.querySelectorAll('.nxd-digit'));
  var delJobId=0, delTick=null, delT0=0;

  function sdcPost(payload){
    var fd=new FormData();
    Object.keys(payload).forEach(function(k){fd.append(k,payload[k]);});
    return fetch('/api/store-delete.php',{method:'POST',headers:{'X-CSRF-Token':csrf()},body:fd}).then(function(r){return r.json();});
  }

  function sendCode(isResend){
    if(!NXD)return;
    var btn=isResend?btnResend:btnSend;
    var old=btn.innerHTML;
    btn.disabled=true;btn.innerHTML='<i class="fas fa-circle-notch nx-spin"></i> Sending…';
    sdcPost({action:'send',store_id:NXD.id}).then(function(d){
      btn.disabled=false;btn.innerHTML=old;
      if(d&&d.ok){
        nxDelGoStep(2);
        nxDelClearCode();
        nxDelMsg2('Code sent to '+(d.masked||'your email')+'.', '#4ade80');
        nxDelResendState('wait');
        setTimeout(function(){ if(delJobId===0) nxDelResendState('ready'); }, 300000);
      } else {
        var msg=(d&&d.msg)||'Could not send the code.';
        if(d&&d.locked) nxDelResendState('locked');
        if(isResend) nxDelMsg2(msg,'#f87171'); else nxDelMsg(msg,'#f87171');
      }
    }).catch(function(){ btn.disabled=false;btn.innerHTML=old; (isResend?nxDelMsg2:nxDelMsg)('Network error. Try again.','#f87171'); });
  }

  if(btnSend){ btnSend.addEventListener('click',function(){ sendCode(false); }); }
  if(btnResend){ btnResend.addEventListener('click',function(){ sendCode(true); }); }
  if(btnBack){ btnBack.addEventListener('click',function(){ nxDelGoStep(1); nxDelMsg('',''); }); }
  var cancelBtn=document.getElementById('nxDelCancel');
  if(cancelBtn){ cancelBtn.addEventListener('click',function(){ window.nxModalClose('nxDeleteModal'); }); }

  digits.forEach(function(inp,idx){
    inp.addEventListener('input',function(){
      var v=(inp.value||'').replace(/[^0-9]/g,'');
      if(v.length>1){ // paste or fast typing: distribute
        var chars=v.split('');
        for(var i=0;i<chars.length&&idx+i<digits.length;i++){ digits[idx+i].value=chars[i]; digits[idx+i].classList.add('filled'); }
        var next=Math.min(digits.length-1, idx+chars.length);
        digits[next].focus();
      } else {
        inp.value=v;
        inp.classList.toggle('filled', !!v);
        if(v) { var n=digits[idx+1]; if(n)n.focus(); }
      }
      if(nxDelCode().length===6) setTimeout(function(){ if(btnVerify&&!btnVerify.disabled) btnVerify.click(); }, 120);
    });
    inp.addEventListener('keydown',function(e){
      if(e.key==='Backspace'&&!inp.value){ var p=digits[idx-1]; if(p){p.focus();p.value='';p.classList.remove('filled');} }
      if(e.key==='ArrowLeft'&&idx>0) digits[idx-1].focus();
      if(e.key==='ArrowRight'&&idx<digits.length-1) digits[idx+1].focus();
    });
    inp.addEventListener('paste',function(e){
      var t='';
      try{ t=(e.clipboardData||window.clipboardData).getData('text'); }catch(err){}
      t=(t||'').replace(/[^0-9]/g,'').slice(0,6);
      if(!t)return;
      e.preventDefault();
      for(var i=0;i<6;i++){ digits[i].value=t[i]||''; digits[i].classList.toggle('filled', !!t[i]); }
      var nf=Math.min(5,t.length); digits[nf].focus();
      if(t.length===6) setTimeout(function(){ if(btnVerify&&!btnVerify.disabled) btnVerify.click(); },120);
    });
  });

  function delAnimStart(store){
    nxDelGoStep(3);
    var el=document.getElementById('nxdProgStore');
    if(el){ var sd=(store||''); if(sd && sd.indexOf('.')===-1) sd+='.vexaris.app'; el.textContent=sd; }
    var ring=document.getElementById('nxdProgRing');
    var pct=document.getElementById('nxdProgPct');
    var sec=document.getElementById('nxdProgSec');
    var msg=document.getElementById('nxdProgMsg');
    delT0=Date.now(); var p=100;
    if(ring){ring.style.strokeDashoffset='0';}
    if(pct)pct.textContent='100%';
    if(msg)msg.textContent='Revoking domain and SSL…';
    /* Ters animasyon: olusturma adimlari sondan basa dogru soner.
       her adim kendi esiginde 'is-off' olur (ustuste binmesin diye tek tek). */
    var order=Array.prototype.slice.call(document.querySelectorAll('#nxdProgSteps .nxd-step')).reverse();
    var offIdx=0;
    clearInterval(delTick);
    delTick=setInterval(function(){
      var e=Math.floor((Date.now()-delT0)/1000);
      if(sec)sec.textContent=e+'s';
      /* Halka %85'te bekler: is gercekte bitmeden animasyon tamamlanmis gorunmesin. */
      if(p>15){
        p-=Math.max(1,Math.round(p*0.08));
        if(p<15)p=15;
        if(ring)ring.style.strokeDashoffset=String(326.7*(1-p/100));
        if(pct)pct.textContent=p+'%';
      }
      /* Adimlar zamana bagli, tek tek soner (ters sira: en alttan yukari). */
      var wantOff=Math.min(order.length, Math.floor(e/6)+1);
      while(offIdx<wantOff && offIdx<order.length){
        var st=order[offIdx];
        st.classList.remove('is-run');
        st.classList.add('is-off');
        st.querySelector('.nxd-ic-s').innerHTML='<i class="fas fa-circle-minus"></i>';
        offIdx++;
        if(msg){
          if(offIdx===1)msg.textContent='Taking the store offline…';
          else if(offIdx===2)msg.textContent='Removing container and files…';
          else if(offIdx===3)msg.textContent='Dropping database…';
          else msg.textContent='Revoking domain and SSL…';
        }
      }
    },380);
  }
  function delAnimFinish(){
    clearInterval(delTick);
    var ring=document.getElementById('nxdProgRing');
    var pct=document.getElementById('nxdProgPct');
    if(ring)ring.style.strokeDashoffset='326.7';
    if(pct)pct.textContent='0%';
    var steps=document.querySelectorAll('#nxdProgSteps .nxd-step');
    steps.forEach(function(st){
      st.classList.remove('is-run');
      st.classList.add('is-off');
      st.querySelector('.nxd-ic-s').innerHTML='<i class="fas fa-circle-minus"></i>';
    });
    var msg=document.getElementById('nxdProgMsg'); if(msg){ msg.textContent='Your store and all its data have been removed.'; msg.style.color='#4ade80'; }
    document.getElementById('nxdProgDone').style.display='block';
    var ns=document.getElementById('nxdNewStore');
    if(ns){ ns.onclick=function(){ window.nxModalClose('nxDeleteModal'); setTimeout(function(){location.href='/dashboard/stores';},150); }; }
  }
  function delPoll(jobId){
    var tries=0;
    var iv=setInterval(function(){
      tries++;
      sdcPost({action:'status',job_id:jobId}).then(function(d){
        if(d&&d.ok){
          if(d.status==='done'){ clearInterval(iv); delAnimFinish(); }
          else if(d.status==='failed'){ clearInterval(iv); clearInterval(delTick); var m=document.getElementById('nxdProgMsg'); if(m){ m.textContent=(d.message||'Deletion failed. Please contact support.'); m.style.color='#f87171'; } }
        }
        if(tries>150){ clearInterval(iv); }
      }).catch(function(){});
    },4000);
  }
  if(btnVerify){ btnVerify.addEventListener('click',function(){
    if(!NXD)return;
    var code=nxDelCode();
    if(code.length!==6){ nxDelMsg2('Enter all 6 digits.','#f87171'); return; }
    var old=btnVerify.innerHTML;
    btnVerify.disabled=true;btnVerify.innerHTML='<i class="fas fa-circle-notch nx-spin"></i> Verifying…';
    sdcPost({action:'verify',store_id:NXD.id,code:code}).then(function(d){
      btnVerify.disabled=false;btnVerify.innerHTML=old;
      if(d&&d.ok){ delJobId=d.job_id||1; delAnimStart(NXD.domain); delPoll(d.job_id||0); }
      else { nxDelMsg2((d&&d.msg)||'Verification failed.','#f87171'); nxDelClearCode(); }
    }).catch(function(){ btnVerify.disabled=false;btnVerify.innerHTML=old; nxDelMsg2('Network error. Try again.','#f87171'); });
  }); }
})();
})();
</script>
<?php require_once __DIR__ . '/_inc/footer.php'; ?>

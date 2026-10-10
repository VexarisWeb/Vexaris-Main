<?php
$activePage = 'stores';
require_once __DIR__ . '/../core/db.php';
boot_session();
require_login();
$user = current_user();

$uid = (int)$user['id'];
$pdo = db();
$uemail = strtolower(trim((string)($user['email'] ?? '')));

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
    if ($name === '' || strlen($slug) < 3 || strlen($slug) > 40) {
        stores_json(['ok' => false, 'msg' => 'Invalid name or subdomain.']);
    }
    $reserved = ['admin','root','demo','www','mail','api','ftp','shop','store','support','test','info','noreply','status','blog','news','login','register','cdn','static','assets','app','panel','free','starter','professional','enterprise','vexaris','mustafa8907'];
    if (in_array($slug, $reserved, true)) {
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
        } catch (Exception $e0) {}
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT COUNT(*) FROM hosted_stores WHERE (user_id=? OR owner=?) AND status='active' FOR UPDATE");
        $st->execute([$uid, $uemail]);
        $have = (int)$st->fetchColumn();
        $st = $pdo->prepare("SELECT COUNT(*) FROM hosting_jobs WHERE user_id=? AND status IN ('pending','claimed') FOR UPDATE");
        $st->execute([$uid]);
        $have += (int)$st->fetchColumn();
        if ($have >= 1 && !$isAdmin) {
            $pdo->rollBack();
            stores_json(['ok' => false, 'msg' => 'Your plan includes 1 store. Delete it to create a new one.']);
        }
        if (!$isAdmin && $effPlan === 'free') {
            $st = $pdo->prepare("SELECT COUNT(*) FROM hosting_jobs WHERE user_id=? AND plan='free' AND kind='provision' AND status='done'");
            $st->execute([$uid]);
            if ((int)$st->fetchColumn() >= 2) {
                $pdo->rollBack();
                stores_json(['ok' => false, 'msg' => 'Free store limit reached (2 lifetime). Upgrade for more.']);
            }
        }
        $st = $pdo->prepare("SELECT id FROM hosted_stores WHERE slug=? LIMIT 1 FOR UPDATE");
        $st->execute([$slug]);
        if ($st->fetch()) { $pdo->rollBack(); stores_json(['ok' => false, 'msg' => 'Subdomain taken.']); }
        $st = $pdo->prepare("SELECT id FROM hosting_jobs WHERE slug=? AND status IN ('pending','claimed') LIMIT 1 FOR UPDATE");
        $st->execute([$slug]);
        if ($st->fetch()) { $pdo->rollBack(); stores_json(['ok' => false, 'msg' => 'Subdomain taken.']); }
        $st = $pdo->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
        $st->execute([$uid]);
        $ohash = (string)$st->fetchColumn();
        if (!preg_match('/^\$(2y|2a|argon2i|argon2id)\$/', $ohash)) $ohash = '';
        try {
            $ins = $pdo->prepare("INSERT INTO hosting_jobs (user_id, slug, plan, owner, site_name, lang, kind, status, owner_hash) VALUES (?,?, ?,?,?, 'en','provision','pending',?)");
            $ins->execute([$uid, $slug, $effPlan, $uemail, $name, $ohash]);
            $jid = (int)$pdo->lastInsertId();
            $pdo->commit();
        } catch (Exception $e2) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            stores_json(['ok' => false, 'msg' => 'Subdomain taken.']);
        }
        stores_json(['ok' => true, 'job_id' => $jid]);
    } catch (Exception $e) {
        stores_json(['ok' => false, 'msg' => 'Server error.']);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (($_POST['action'] ?? '') === 'delete-store')) {
    csrf_check();
    $sid = (int)($_POST['store_id'] ?? 0);
    $pw = (string)($_POST['password'] ?? '');
    try {
        $st = $pdo->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
        $st->execute([$uid]);
        $hash = (string)$st->fetchColumn();
        if ($hash === '' || !password_verify($pw, $hash)) {
            stores_json(['ok' => false, 'msg' => 'Wrong password.']);
        }
        $st = $pdo->prepare("SELECT slug FROM hosted_stores WHERE id=? AND (user_id=? OR owner=?) LIMIT 1");
        $st->execute([$sid, $uid, $uemail]);
        $row = $st->fetch();
        if (!$row) stores_json(['ok' => false, 'msg' => 'Store not found.']);
        $slug = (string)$row['slug'];
        $st = $pdo->prepare("SELECT id FROM hosting_jobs WHERE slug=? AND kind='delete' AND status IN ('pending','claimed') LIMIT 1");
        $st->execute([$slug]);
        if ($st->fetch()) stores_json(['ok' => false, 'msg' => 'Delete already queued.']);
        try {
            $ins = $pdo->prepare("INSERT INTO hosting_jobs (user_id, slug, plan, owner, site_name, lang, kind, status) VALUES (?,?,'free',?,'','en','delete','pending')");
            $ins->execute([$uid, $slug, $uemail]);
        } catch (Exception $e2) {
            stores_json(['ok' => false, 'msg' => 'Delete already queued.']);
        }
        stores_json(['ok' => true, 'job_id' => (int)$pdo->lastInsertId()]);
    } catch (Exception $e) {
        stores_json(['ok' => false, 'msg' => 'Server error.']);
    }
}

if (isset($_GET['check'])) {
    $slug = strtolower(trim((string)($_GET['slug'] ?? '')));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    if (strlen($slug) < 3 || strlen($slug) > 40) stores_json(['ok' => true, 'available' => false, 'msg' => 'length']);
    $reserved = ['admin','root','demo','www','mail','api','ftp','shop','store','support','test','info','noreply','status','blog','news','login','register','cdn','static','assets','app','panel','free','starter','professional','enterprise','vexaris','mustafa8907'];
    if (in_array($slug, $reserved, true)) stores_json(['ok' => true, 'available' => false, 'msg' => 'reserved']);
    try {
        $st = $pdo->prepare("SELECT id FROM licenses WHERE allowed_domains LIKE ? LIMIT 1");
        $st->execute(['%"' . $slug . '.vexaris.net"%']);
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
    } catch (Exception $e) {}
    stores_json(['ok' => true, 'available' => true, 'domain' => $slug . '.vexaris.net']);
}

if (isset($_GET['check_job'])) {
    $jid = (int)$_GET['check_job'];
    try {
        $st = $pdo->prepare("SELECT status, last_msg, slug, kind FROM hosting_jobs WHERE id=? AND user_id=? LIMIT 1");
        $st->execute([$jid, $uid]);
        $row = $st->fetch();
        if (!$row) stores_json(['ok' => false, 'msg' => 'not found']);
        $out = ['ok' => true, 'status' => $row['status'], 'message' => $row['last_msg'], 'kind' => $row['kind']];
        if ($row['status'] === 'done') $out['domain'] = $row['slug'] . '.vexaris.net';
        stores_json($out);
    } catch (Exception $e) {
        stores_json(['ok' => false, 'msg' => 'Server error.']);
    }
}

require_once __DIR__ . '/_inc/header.php';

$store = null;
try {
    $st = $pdo->prepare("SELECT id, slug, domain, plan, site_name, users, products, revenue, currency, traffic_bytes, traffic_updated, status, reported_at, created_at FROM hosted_stores WHERE (user_id=? OR owner=?) AND status='active' ORDER BY id DESC LIMIT 1");
    $st->execute([$uid, $uemail]);
    $store = $st->fetch() ?: null;
} catch (Exception $e) {}
$myPlan = function_exists('user_effective_plan') ? user_effective_plan($uid) : ['plan' => 'free', 'until' => 0];
$planQuotaGB = ['free' => 10, 'starter' => 50, 'professional' => 200, 'enterprise' => 1000];
$hasActive = $store !== null;
$traf14 = [];
if ($store) {
    try {
        $st = $pdo->prepare("SELECT day, bytes FROM store_traffic_daily WHERE slug=? AND day > DATE_SUB(CURDATE(), INTERVAL 14 DAY) ORDER BY day ASC");
        $st->execute([(string)$store['slug']]);
        foreach ($st->fetchAll() as $r) $traf14[(string)$r['day']] = (int)$r['bytes'];
    } catch (Exception $e) {}
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
    <p>One-click free store on your own subdomain.</p>
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
?>
<div class="panel-card"><div class="panel-card__body">
    <div style="display:flex;align-items:center;gap:.8rem;margin-bottom:1.1rem;flex-wrap:wrap;">
        <div style="width:52px;height:52px;flex:none;background:linear-gradient(135deg,rgba(255,122,26,.2),rgba(255,60,17,.1));border:1px solid rgba(255,122,26,.35);border-radius:15px;display:flex;align-items:center;justify-content:center;color:#ff7a1a;font-size:20px;"><i class="fas fa-store"></i></div>
        <div style="min-width:0;flex:1;">
            <div style="font-weight:800;font-size:17px;"><?= $sname !== '' ? htmlspecialchars($sname) : htmlspecialchars($store['domain']) ?></div>
            <div style="font-size:13px;color:#8cb8ff;font-weight:700;"><?= htmlspecialchars($store['domain']) ?></div>
        </div>
        <span style="font-size:10px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;padding:4px 12px;border-radius:999px;background:<?= $sp === 'free' ? 'rgba(255,255,255,.07);color:#cfd6e4' : 'rgba(0,212,255,.12);color:#7dd7fc' ?>;"><?= htmlspecialchars($sp) ?></span>
    </div>
    <div style="background:rgba(255,255,255,.03);border:1px solid var(--border,rgba(255,255,255,.08));border-radius:14px;padding:14px 16px;margin-bottom:1.1rem;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <div style="flex:1;min-width:200px;font-size:13px;color:#9aa3ac;"><i class="fas fa-key" style="color:#ff7a1a;"></i> Store admin — username <strong style="color:var(--text,#e8eaf6);">admin</strong>, password is your Vexaris password (also emailed at setup).</div>
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
            <div style="font-size:11px;color:#9aa3ac;">of <?= (int)$quotaGB ?> GB</div>
        </div>
    </div>
    <div style="background:rgba(255,255,255,.03);border:1px solid var(--border,rgba(255,255,255,.08));border-radius:14px;padding:14px 16px;margin-bottom:1.1rem;">
        <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:700;color:#9aa3ac;margin-bottom:8px;"><span><i class="fas fa-gauge-high"></i> Monthly bandwidth</span><span><?= htmlspecialchars(nx_fmt_bytes($usedB)) ?> / <?= (int)$quotaGB ?> GB (<?= htmlspecialchars((string)$trafPct) ?>%)</span></div>
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
    <form method="POST" action="/api/plan-checkout" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:1.2rem;">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="store_slug" value="<?= htmlspecialchars((string)$store['slug']) ?>">
        <select name="plan" style="flex:1;min-width:140px;background:rgba(255,255,255,.05);border:1px solid var(--border,rgba(255,255,255,.08));border-radius:10px;color:var(--text,#e8eaf6);padding:10px 12px;font-size:13px;outline:none;">
            <option value="starter">Starter</option>
            <option value="professional">Professional</option>
            <option value="enterprise">Enterprise</option>
        </select>
        <select name="cycle" style="background:rgba(255,255,255,.05);border:1px solid var(--border,rgba(255,255,255,.08));border-radius:10px;color:var(--text,#e8eaf6);padding:10px 12px;font-size:13px;outline:none;">
            <option value="monthly">Monthly</option>
            <option value="yearly">Yearly −20%</option>
        </select>
        <button type="submit" class="btn-panel btn-cyan"><i class="fas fa-credit-card"></i> Upgrade</button>
    </form>
    <div style="display:flex;gap:8px;margin-bottom:1.2rem;">
        <a class="btn-panel" style="flex:1;justify-content:center;" href="https://<?= htmlspecialchars($store['domain']) ?>/admin/dashboard.php" target="_blank" rel="noopener"><i class="fas fa-gauge"></i> Admin</a>
        <a class="btn-panel btn-cyan" style="flex:1;justify-content:center;" href="https://<?= htmlspecialchars($store['domain']) ?>" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> Visit</a>
    </div>
    <button class="btn-panel" style="width:100%;justify-content:center;color:#f87171;" onclick="nxDeleteFor(<?= (int)$store['id'] ?>,'<?= htmlspecialchars($store['domain'], ENT_QUOTES) ?>')"><i class="fas fa-trash"></i> Delete Store</button>
</div></div>
<?php endif; ?>

<style>@keyframes nxPop{from{opacity:0;transform:translateY(14px) scale(.97);}to{opacity:1;transform:none;}}@keyframes nxFade{from{opacity:0;}to{opacity:1;}}.nx-modal-show{animation:nxFade .18s ease;}.nx-modal-show>div{animation:nxPop .22s cubic-bezier(.22,1,.36,1);}.nx-spin{animation:nxRot 1s linear infinite;}@keyframes nxRot{to{transform:rotate(360deg);}}</style>
<div id="nxStoreModal" style="display:none;position:fixed;inset:0;z-index:9990;background:rgba(0,0,0,.6);align-items:center;justify-content:center;padding:16px;">
    <div style="width:100%;max-width:460px;background:var(--bg-card,#131929);border:1px solid var(--border,rgba(255,255,255,.08));border-radius:18px;padding:26px;">
        <div id="nxSmStep1">
            <h3 style="margin:0 0 4px;font-size:18px;font-weight:800;">New Free Store</h3>
            <p style="font-size:13px;color:#9aa3ac;margin:0 0 18px;">Pick a name and subdomain. Free forever.</p>
            <label style="display:block;font-size:12px;font-weight:700;color:#9aa3ac;margin-bottom:6px;">Store Name</label>
            <input id="nxStoreName" type="text" maxlength="80" placeholder="My Awesome Store" style="width:100%;box-sizing:border-box;background:var(--bg-surface,#0f1422);border:1px solid var(--border,rgba(255,255,255,.08));border-radius:10px;padding:11px 14px;color:var(--text,#e8eaf6);font-size:14px;outline:none;margin-bottom:14px;">
            <label style="display:block;font-size:12px;font-weight:700;color:#9aa3ac;margin-bottom:6px;">Subdomain</label>
            <div style="display:flex;gap:0;margin-bottom:6px;">
                <input id="nxStoreSlug" type="text" maxlength="40" placeholder="mystore" style="flex:1;min-width:0;box-sizing:border-box;background:var(--bg-surface,#0f1422);border:1px solid var(--border,rgba(255,255,255,.08));border-radius:10px 0 0 10px;padding:11px 14px;color:var(--text,#e8eaf6);font-size:14px;outline:none;border-right:none;">
                <span style="background:rgba(255,255,255,.05);border:1px solid var(--border,rgba(255,255,255,.08));border-radius:0 10px 10px 0;padding:11px 14px;font-size:14px;color:#9aa3ac;white-space:nowrap;">.vexaris.net</span>
            </div>
            <div id="nxSlugMsg" style="font-size:12px;min-height:18px;margin-bottom:10px;"></div>
            <div id="nxTsWrap" style="margin-bottom:14px;"><?php require_once __DIR__ . '/../core/turnstile.php'; if (function_exists('turnstile_widget')) { echo turnstile_widget('nxTs'); echo turnstile_script(); } ?></div>
            <div style="display:flex;gap:10px;">
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

<div id="nxDeleteModal" style="display:none;position:fixed;inset:0;z-index:9995;background:rgba(0,0,0,.7);align-items:center;justify-content:center;padding:16px;">
    <div style="width:100%;max-width:400px;background:#0d1b3e;border:1px solid rgba(239,68,68,.35);border-radius:18px;padding:1.6rem;">
        <h3 style="margin:0 0 6px;font-size:17px;font-weight:800;color:#f87171;"><i class="fas fa-triangle-exclamation"></i> Delete Store?</h3>
        <p style="font-size:13px;color:var(--muted);margin:0 0 14px;">This permanently deletes <strong id="nxdDelName" style="color:var(--text);"></strong> and all its data. Enter your password to confirm.</p>
        <input id="nxDelPass" type="password" placeholder="Your password" style="width:100%;box-sizing:border-box;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;padding:11px 14px;color:var(--text);font-size:14px;outline:none;margin-bottom:12px;">
        <div id="nxDelMsg" style="font-size:12px;min-height:18px;margin-bottom:8px;"></div>
        <div style="display:flex;gap:10px;">
            <button class="btn-panel" id="nxDelCancel" style="flex:1;">Cancel</button>
            <button class="btn-panel" id="nxDelGo" style="flex:1;background:rgba(239,68,68,.15);border-color:rgba(239,68,68,.4);color:#f87171;">Delete Forever</button>
        </div>
    </div>
</div>
<script>
var NXD = null;
(function(){
var modal=document.getElementById('nxStoreModal');
var openBtn=document.getElementById('nxNewStoreBtn');
function openM(){ if(modal){modal.classList.remove('nx-modal-show');void modal.offsetWidth;modal.style.display='flex';modal.classList.add('nx-modal-show');} }
function closeM(){ if(modal){modal.classList.remove('nx-modal-show');modal.style.display='none';} document.getElementById('nxSmStep1').style.display='block'; document.getElementById('nxSmStep2').style.display='none'; }
if(openBtn){openBtn.addEventListener('click',openM);}
var c1=document.getElementById('nxSmCancel'); if(c1){c1.addEventListener('click',closeM);}
var c2=document.getElementById('nxSmClose2'); if(c2){c2.addEventListener('click',closeM);}
if(modal){modal.addEventListener('click',function(e){ if(e.target===modal)closeM(); });}
var slugEl=document.getElementById('nxSlugMsg'),createBtn=document.getElementById('nxSmCreate'),slugIn=document.getElementById('nxStoreSlug');
var slugTimer=null,slugOk=false;
function setMsg(t,ok){ slugEl.textContent=t||''; slugEl.style.color=ok?'#4ade80':'#f87171'; createBtn.disabled=!ok; slugOk=!!ok; }
if(slugIn){slugIn.addEventListener('input',function(){
  clearTimeout(slugTimer); setMsg('',false);
  var v=slugIn.value.toLowerCase().replace(/[^a-z0-9\-]/g,''); slugIn.value=v;
  if(v.length<3){return;}
  slugTimer=setTimeout(function(){
    setMsg('Checking…',false);
    fetch('/dashboard/stores?check=1&slug='+encodeURIComponent(v)).then(function(r){return r.json()}).then(function(d){
      if(d&&d.available){setMsg('✓ '+d.domain+' is available',true);}else{setMsg('✕ '+(d.msg||'taken'),false);}
    }).catch(function(){setMsg('Check failed, retry.',false);});
  },450);
});}
function csrf(){var m=document.querySelector('meta[name="csrf-token"]');return m?m.getAttribute('content'):'';}
function tsToken(){var el=document.querySelector('#nxTsWrap [name="cf-turnstile-response"]');return el?el.value:'';}
if(createBtn){createBtn.addEventListener('click',function(){
  if(!slugOk)return;
  createBtn.disabled=true;
  createBtn.innerHTML='<i class="fas fa-circle-notch nx-spin"></i> Creating…';
  nxResetProg();
  progT0=Date.now();
  clearInterval(progTick);
  progTick=setInterval(function(){document.getElementById('nxProgSec').textContent=Math.floor((Date.now()-progT0)/1000)+'s';},500);
  var nm=document.getElementById('nxStoreName').value;
  document.getElementById('nxProgStore').textContent=(nm?nm+' · ':'')+slugIn.value+'.vexaris.net';
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
  createBtn.disabled=true;
  fetch('/dashboard/stores',{method:'POST',headers:{'X-CSRF-Token':csrf()},body:fd}).then(function(r){return r.json()}).then(function(d){
    if(d&&d.ok&&d.job_id){startProgress(d.job_id);}else{backToForm((d&&d.msg)||'Failed');}
  }).catch(function(){backToForm('Failed');});
});}
function backToForm(msg){
  document.getElementById('nxSmStep2').style.display='none';
  document.getElementById('nxSmStep1').style.display='block';
  createBtn.disabled=false;
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
  document.getElementById('nxDelPass').value='';
  document.getElementById('nxDelMsg').textContent='';
  var dl=document.getElementById('nxDeleteModal');dl.classList.remove('nx-modal-show');void dl.offsetWidth;dl.style.display='flex';dl.classList.add('nx-modal-show');
};
var delCancel=document.getElementById('nxDelCancel');
if(delCancel){delCancel.addEventListener('click',function(){document.getElementById('nxDeleteModal').style.display='none';});}
window.nxDeleteFor=function(id,domain){NXD={id:id,domain:domain};openStoreDelete();};

var delGo=document.getElementById('nxDelGo');
if(delGo){delGo.addEventListener('click',function(){
  if(!NXD)return;
  var m=document.getElementById('nxDelMsg');m.textContent='Deleting…';m.style.color='#9aa3ac';
  var fd=new FormData();
  fd.append('action','delete-store');
  fd.append('store_id',NXD.id);
  fd.append('password',document.getElementById('nxDelPass').value);
  fetch('/dashboard/stores',{method:'POST',headers:{'X-CSRF-Token':csrf()},body:fd}).then(function(r){return r.json()}).then(function(d){
    if(d&&d.ok&&d.job_id){m.textContent='Delete queued. This page will reload.';m.style.color='#4ade80';setTimeout(function(){location.reload();},2500);}
    else{m.textContent=(d&&d.msg)||'Failed';m.style.color='#f87171';}
  }).catch(function(){m.textContent='Failed';m.style.color='#f87171';});
});}
})();
</script>
<?php require_once __DIR__ . '/_inc/footer.php'; ?>

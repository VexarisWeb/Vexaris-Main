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
        $st = $pdo->prepare("SELECT COUNT(*) FROM hosted_stores WHERE (user_id=? OR owner=?) AND plan='free' AND status='active'");
        $st->execute([$uid, $uemail]);
        $have = (int)$st->fetchColumn();
        $st = $pdo->prepare("SELECT COUNT(*) FROM hosting_jobs WHERE user_id=? AND plan='free' AND status IN ('pending','claimed')");
        $st->execute([$uid]);
        $have += (int)$st->fetchColumn();
        if ($have >= 1) {
            stores_json(['ok' => false, 'msg' => 'Free plan includes 1 store. Upgrade for more.']);
        }
        $st = $pdo->prepare("SELECT id FROM hosted_stores WHERE slug=? LIMIT 1");
        $st->execute([$slug]);
        if ($st->fetch()) stores_json(['ok' => false, 'msg' => 'Subdomain taken.']);
        $st = $pdo->prepare("SELECT id FROM hosting_jobs WHERE slug=? AND status IN ('pending','claimed') LIMIT 1");
        $st->execute([$slug]);
        if ($st->fetch()) stores_json(['ok' => false, 'msg' => 'Subdomain taken.']);
        try {
            $ins = $pdo->prepare("INSERT INTO hosting_jobs (user_id, slug, plan, owner, site_name, lang, kind, status) VALUES (?,'free',?,?,?,'en','provision','pending')");
            $ins->execute([$uid, $slug, $uemail, $name]);
        } catch (Exception $e2) {
            stores_json(['ok' => false, 'msg' => 'Subdomain taken.']);
        }
        stores_json(['ok' => true, 'job_id' => (int)$pdo->lastInsertId()]);
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
            $ins = $pdo->prepare("INSERT INTO hosting_jobs (user_id, slug, plan, owner, site_name, lang, kind, status) VALUES (?,'free',?,'','en','delete','pending')");
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

$stores = [];
try {
    $st = $pdo->prepare("SELECT id, slug, domain, plan, users, products, revenue, currency, status, reported_at, created_at FROM hosted_stores WHERE user_id=? OR owner=? ORDER BY id DESC LIMIT 50");
    $st->execute([$uid, $uemail]);
    $stores = $st->fetchAll();
} catch (Exception $e) {}
$myPlan = function_exists('user_effective_plan') ? user_effective_plan($uid) : ['plan' => 'free', 'until' => 0];
$hasFree = false;
foreach ($stores as $s) {
    if (($s['plan'] ?? '') === 'free' && ($s['status'] ?? '') === 'active') { $hasFree = true; break; }
}
$planThemes = ['free' => ['Default'], 'starter' => ['Default', 'Azure'], 'professional' => ['Default', 'Azure', 'Ember'], 'enterprise' => ['Default', 'Azure', 'Ember']];
?>
<div class="main__header">
    <h1>My Stores</h1>
    <p>One-click free store on your own subdomain.</p>
</div>

<?php if (!$stores): ?>
<div class="panel-card"><div class="panel-card__body"><div class="empty"><i class="fas fa-store"></i>No stores yet. Create your free store in seconds.</div></div></div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.2rem;margin-bottom:1.4rem">
<?php foreach ($stores as $s):
    $sp = strtolower($s['plan'] ?? 'free');
    $themes = $planThemes[$sp] ?? ['Default'];
?>
    <div class="panel-card" style="margin:0;display:flex;flex-direction:column;cursor:pointer;" onclick='openStoreDetail(<?= json_encode(['id' => (int)$s['id'], 'domain' => $s['domain'], 'plan' => $sp, 'users' => (int)$s['users'], 'products' => (int)($s['products'] ?? 0), 'revenue' => (float)$s['revenue'], 'revenue_fmt' => store_money((float)$s['revenue'], (string)($s['currency'] ?? 'TRY')), 'status' => $s['status'], 'created' => $s['created_at']], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
        <div class="panel-card__body" style="flex:1;display:flex;flex-direction:column;">
            <div style="display:flex;align-items:center;gap:.7rem;margin-bottom:.8rem">
                <div style="width:46px;height:46px;flex:none;background:linear-gradient(135deg,rgba(255,122,26,.2),rgba(255,60,17,.1));border:1px solid rgba(255,122,26,.35);border-radius:14px;display:flex;align-items:center;justify-content:center;color:#ff7a1a;font-size:18px;"><i class="fas fa-store"></i></div>
                <div style="min-width:0;flex:1;">
                    <div style="font-weight:800;font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($s['domain']) ?></div>
                    <span style="display:inline-block;margin-top:3px;font-size:10px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;padding:3px 10px;border-radius:999px;background:<?= $sp === 'free' ? 'rgba(255,255,255,.07);color:#cfd6e4' : 'rgba(0,212,255,.12);color:#7dd7fc' ?>;"><?= htmlspecialchars($sp) ?></span>
                </div>
            </div>
            <div style="display:flex;gap:1rem;font-size:13px;color:#9aa3ac;margin-bottom:.9rem">
                <span title="Users"><i class="fas fa-users"></i> <?= (int)$s['users'] ?></span>
                <span title="Products"><i class="fas fa-box"></i> <?= (int)($s['products'] ?? 0) ?></span>
                <span title="Revenue"><i class="fas fa-chart-line"></i> <?= htmlspecialchars(store_money((float)$s['revenue'], (string)($s['currency'] ?? 'TRY'))) ?></span>
            </div>
            <div style="display:flex;gap:8px;margin-top:auto;">
                <a class="btn-panel" style="flex:1;justify-content:center;" href="https://<?= htmlspecialchars($s['domain']) ?>/admin/dashboard.php" target="_blank" rel="noopener" onclick="event.stopPropagation()"><i class="fas fa-gauge"></i> Admin</a>
                <a class="btn-panel btn-cyan" style="flex:1;justify-content:center;" href="https://<?= htmlspecialchars($s['domain']) ?>" target="_blank" rel="noopener" onclick="event.stopPropagation()"><i class="fas fa-arrow-up-right-from-square"></i> Visit</a>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$hasFree): ?>
<button class="btn-panel btn-cyan" id="nxNewStoreBtn"><i class="fas fa-plus"></i> New Free Store</button>
<?php else: ?>
<p style="font-size:13px;color:#9aa3ac;"><i class="fas fa-circle-info"></i> Free plan includes 1 store. Upgrade your plan for more stores.</p>
<?php endif; ?>

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
            <h3 style="margin:0 0 16px;font-size:18px;font-weight:800;">Creating your store…</h3>
            <div id="nxProgSteps" style="text-align:left;display:flex;flex-direction:column;gap:12px;margin-bottom:8px;">
                <div data-ps="queue" style="display:flex;gap:10px;align-items:center;font-size:14px;"><span class="nx-ps-ic">⏳</span><span>Queued</span></div>
                <div data-ps="db" style="display:flex;gap:10px;align-items:center;font-size:14px;opacity:.45;"><span class="nx-ps-ic">⏳</span><span>Database ready</span></div>
                <div data-ps="build" style="display:flex;gap:10px;align-items:center;font-size:14px;opacity:.45;"><span class="nx-ps-ic">⏳</span><span>Container building</span></div>
                <div data-ps="done" style="display:flex;gap:10px;align-items:center;font-size:14px;opacity:.45;"><span class="nx-ps-ic">⏳</span><span>Store live</span></div>
            </div>
            <div id="nxProgMsg" style="font-size:13px;color:#9aa3ac;min-height:20px;"></div>
            <div id="nxProgLink" style="display:none;margin-top:12px;"><a class="btn-panel btn-cyan" id="nxProgA" href="#" target="_blank" rel="noopener">Visit Your Store</a></div>
            <div style="margin-top:12px;"><button class="btn-panel" id="nxSmClose2">Close</button></div>
        </div>
    </div>
</div>

<div id="nxDetailModal" style="display:none;position:fixed;inset:0;z-index:9990;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);align-items:center;justify-content:center;padding:16px;">
    <div style="width:100%;max-width:600px;background:#0d1b3e;border:1px solid rgba(0,212,255,.25);border-radius:20px;padding:1.8rem;position:relative;max-height:88vh;overflow-y:auto">
        <button onclick="closeStoreDetail()" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.07);border:1px solid var(--border);border-radius:8px;color:var(--text);width:30px;height:30px;cursor:pointer;font-size:.95rem;display:flex;align-items:center;justify-content:center"><i class="fas fa-xmark"></i></button>
        <h3 style="font-size:1.05rem;font-weight:800;margin:0 0 4px;"><i class="fas fa-store" style="color:var(--cyan);margin-right:8px"></i><span id="nxdDomain">-</span></h3>
        <p id="nxdPlan" style="font-size:12px;color:var(--muted);margin:0 0 1.2rem;text-transform:uppercase;letter-spacing:.06em;"></p>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:1.2rem">
            <div style="background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:14px;padding:1rem;text-align:center;">
                <div style="font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;margin-bottom:4px;"><i class="fas fa-chart-line"></i> Revenue</div>
                <div id="nxdRevenue" style="font-size:22px;font-weight:900;">0</div>
            </div>
            <div style="background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:14px;padding:1rem;text-align:center;">
                <div style="font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;margin-bottom:4px;"><i class="fas fa-box"></i> Products</div>
                <div id="nxdProducts" style="font-size:22px;font-weight:900;">0</div>
            </div>
            <div style="background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:14px;padding:1rem;text-align:center;">
                <div style="font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;margin-bottom:4px;"><i class="fas fa-users"></i> Users</div>
                <div id="nxdUsers" style="font-size:22px;font-weight:900;">0</div>
            </div>
        </div>
        <div style="font-size:13px;color:var(--muted);margin-bottom:1rem;">Plan expires: <strong id="nxdExpiry" style="color:var(--text);">-</strong></div>
        <div style="font-size:13px;font-weight:800;margin-bottom:8px;"><i class="fas fa-paintbrush"></i> My Themes</div>
        <div id="nxdThemes" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:1.2rem;"></div>
        <div style="font-size:13px;font-weight:800;margin-bottom:8px;"><i class="fas fa-arrow-up"></i> Upgrade Plan</div>
        <form method="POST" action="/api/plan-checkout" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:1.2rem;">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="store_slug" id="nxdUpSlug" value="">
            <select name="plan" id="nxdUpPlan" style="flex:1;min-width:140px;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:10px 12px;font-size:13px;outline:none;">
                <option value="starter">Starter</option>
                <option value="professional">Professional</option>
                <option value="enterprise">Enterprise</option>
            </select>
            <select name="cycle" style="background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:10px 12px;font-size:13px;outline:none;">
                <option value="monthly">Monthly</option>
                <option value="yearly">Yearly −20%</option>
            </select>
            <button type="submit" class="btn-panel btn-cyan"><i class="fas fa-credit-card"></i> Upgrade</button>
        </form>
        <button class="btn-panel" style="width:100%;justify-content:center;color:#f87171;" onclick="openStoreDelete()"><i class="fas fa-trash"></i> Delete Store</button>
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
var NXD_EXPIRY = <?= json_encode($myPlan['until'] ?? 0) ?>;
(function(){
var modal=document.getElementById('nxStoreModal');
var openBtn=document.getElementById('nxNewStoreBtn');
function openM(){ if(modal){modal.style.display='flex';} }
function closeM(){ if(modal){modal.style.display='none';} document.getElementById('nxSmStep1').style.display='block'; document.getElementById('nxSmStep2').style.display='none'; }
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
  var fd=new FormData();
  fd.append('action','create-store');
  fd.append('store_name',document.getElementById('nxStoreName').value);
  fd.append('slug',slugIn.value);
  fd.append('cf-turnstile-response',tsToken());
  createBtn.disabled=true;
  fetch('/dashboard/stores',{method:'POST',headers:{'X-CSRF-Token':csrf()},body:fd}).then(function(r){return r.json()}).then(function(d){
    if(d&&d.ok&&d.job_id){startProgress(d.job_id);}else{alert((d&&d.msg)||'Failed');createBtn.disabled=false;}
  }).catch(function(){alert('Failed');createBtn.disabled=false;});
});}
var progTimer=null;
function setStep(id,state){
  var rows=document.querySelectorAll('#nxProgSteps [data-ps]');
  rows.forEach(function(r){
    var on=r.getAttribute('data-ps')===id;
    if(state==='done'&&on){r.querySelector('.nx-ps-ic').textContent='✅';r.style.opacity='1';}
    if(state==='run'&&on){r.querySelector('.nx-ps-ic').textContent='🔄';r.style.opacity='1';}
  });
}
function startProgress(job){
  document.getElementById('nxSmStep1').style.display='none';
  document.getElementById('nxSmStep2').style.display='block';
  setStep('queue','run');
  var msg=document.getElementById('nxProgMsg');
  msg.textContent='Queued…';
  clearInterval(progTimer);
  progTimer=setInterval(function(){
    fetch('/dashboard/stores?check_job='+job).then(function(r){return r.json()}).then(function(d){
      if(!d||!d.ok){msg.textContent='Error checking status.';return;}
      var st=d.status;
      if(st==='pending'){msg.textContent='Queued…';}
      else if(st==='claimed'){setStep('queue','done');setStep('db','run');setStep('build','run');msg.textContent='Building database and container…';}
      else if(st==='done'){clearInterval(progTimer);setStep('queue','done');setStep('db','done');setStep('build','done');setStep('done','done');msg.textContent='Ready!';var l=document.getElementById('nxProgLink');l.style.display='block';document.getElementById('nxProgA').href='https://'+d.domain;}
      else if(st==='failed'){clearInterval(progTimer);msg.textContent='Failed: '+(d.message||'error');}
    }).catch(function(){});
  },3000);
}
window.openStoreDetail=function(r){
  NXD=r;
  document.getElementById('nxdDomain').textContent=r.domain||'-';
  document.getElementById('nxdPlan').textContent=(r.plan||'-')+' plan';
  document.getElementById('nxdRevenue').textContent=r.revenue_fmt||Number(r.revenue||0).toFixed(2);
  document.getElementById('nxdProducts').textContent=r.products||0;
  document.getElementById('nxdUsers').textContent=r.users||0;
  var exp='Free forever';
  if((r.plan||'free')!=='free'){
    if(NXD_EXPIRY>0){var dt=new Date(NXD_EXPIRY*1000);exp=dt.toLocaleDateString();}
    else{exp='Unknown';}
  }
  document.getElementById('nxdExpiry').textContent=exp;
  document.getElementById('nxdUpSlug').value=r.domain?r.domain.split('.')[0]:'';
  var th=document.getElementById('nxdThemes');th.innerHTML='';
  var allowed=r.plan==='starter'?['Default','Azure']:(r.plan==='free'?['Default']:['Default','Azure','Ember']);
  ['Default','Azure','Ember'].forEach(function(t){
    var ok=allowed.indexOf(t)!==-1;
    var d=document.createElement('div');
    d.style.cssText='padding:8px 14px;border-radius:999px;font-size:12px;font-weight:800;'+(ok?'background:rgba(34,197,94,.12);color:#4ade80;border:1px solid rgba(34,197,94,.35);':'background:rgba(255,255,255,.04);color:#64748b;border:1px solid var(--border);');
    d.textContent=(ok?'✓ ':'🔒 ')+t;
    th.appendChild(d);
  });
  document.getElementById('nxDetailModal').style.display='flex';
};
window.closeStoreDetail=function(){document.getElementById('nxDetailModal').style.display='none';};
window.openStoreDelete=function(){
  if(!NXD)return;
  document.getElementById('nxdDelName').textContent=NXD.domain||'';
  document.getElementById('nxDelPass').value='';
  document.getElementById('nxDelMsg').textContent='';
  document.getElementById('nxDeleteModal').style.display='flex';
};
var delCancel=document.getElementById('nxDelCancel');
if(delCancel){delCancel.addEventListener('click',function(){document.getElementById('nxDeleteModal').style.display='none';});}
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

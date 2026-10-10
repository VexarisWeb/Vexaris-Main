<?php
$activePage = 'stores';
require_once __DIR__ . '/_inc/header.php';

$uid = (int)$user['id'];
$pdo = db();
$uemail = strtolower(trim((string)($user['email'] ?? '')));

function stores_json(array $a): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($a, JSON_UNESCAPED_UNICODE);
    exit;
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
    $reserved = ['admin','root','demo','www','mail','api','ftp','shop','store','support','test','info','noreply','status','blog','news','login','register','cdn','static','assets','app','panel','free','starter','professional','enterprise'];
    if (in_array($slug, $reserved, true)) {
        stores_json(['ok' => false, 'msg' => 'This subdomain is reserved.']);
    }
    try {
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
        $ins = $pdo->prepare("INSERT INTO hosting_jobs (user_id, slug, plan, owner, site_name, lang, status) VALUES (?,'free',?,?,?,'en')");
        $ins->execute([$uid, $slug, $uemail, $name]);
        stores_json(['ok' => true, 'job_id' => (int)$pdo->lastInsertId()]);
    } catch (Exception $e) {
        stores_json(['ok' => false, 'msg' => 'Server error.']);
    }
}

if (isset($_GET['check'])) {
    $slug = strtolower(trim((string)($_GET['slug'] ?? '')));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    if (strlen($slug) < 3 || strlen($slug) > 40) stores_json(['ok' => true, 'available' => false, 'msg' => 'length']);
    $reserved = ['admin','root','demo','www','mail','api','ftp','shop','store','support','test','info','noreply','status','blog','news','login','register','cdn','static','assets','app','panel','free','starter','professional','enterprise'];
    if (in_array($slug, $reserved, true)) stores_json(['ok' => true, 'available' => false, 'msg' => 'reserved']);
    try {
        $st = $pdo->prepare("SELECT id FROM licenses WHERE allowed_domains LIKE ? LIMIT 1");
        $st->execute(['%' . $slug . '.vexaris.net%']);
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
        $st = $pdo->prepare("SELECT status, last_msg, slug FROM hosting_jobs WHERE id=? AND user_id=? LIMIT 1");
        $st->execute([$jid, $uid]);
        $row = $st->fetch();
        if (!$row) stores_json(['ok' => false, 'msg' => 'not found']);
        $out = ['ok' => true, 'status' => $row['status'], 'message' => $row['last_msg']];
        if ($row['status'] === 'done') $out['domain'] = $row['slug'] . '.vexaris.net';
        stores_json($out);
    } catch (Exception $e) {
        stores_json(['ok' => false, 'msg' => 'Server error.']);
    }
}

$stores = [];
try {
    $st = $pdo->prepare("SELECT slug, domain, plan, users, revenue, status, reported_at, created_at FROM hosted_stores WHERE user_id=? OR owner=? ORDER BY id DESC LIMIT 50");
    $st->execute([$uid, $uemail]);
    $stores = $st->fetchAll();
} catch (Exception $e) {}
$hasFree = false;
foreach ($stores as $s) {
    if (($s['plan'] ?? '') === 'free' && ($s['status'] ?? '') === 'active') { $hasFree = true; break; }
}
?>
<div class="main__header">
    <h1>My Stores</h1>
    <p>One-click free store on your own subdomain.</p>
</div>

<?php if (!$stores): ?>
<div class="panel-card"><div class="panel-card__body"><div class="empty"><i class="fas fa-store"></i>No stores yet. Create your free store in seconds.</div></div></div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.2rem;margin-bottom:1.4rem">
<?php foreach ($stores as $s): ?>
    <div class="panel-card" style="margin:0;">
        <div class="panel-card__body">
            <div style="display:flex;align-items:center;gap:.7rem;margin-bottom:.6rem">
                <div style="width:42px;height:42px;flex:none;background:rgba(255,122,26,.1);border:1px solid rgba(255,122,26,.3);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#ff7a1a"><i class="fas fa-store"></i></div>
                <div style="min-width:0">
                    <div style="font-weight:800;font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($s['domain']) ?></div>
                    <div style="font-size:12px;color:#9aa3ac;text-transform:uppercase;letter-spacing:.06em;"><?= htmlspecialchars($s['plan']) ?> · <?= htmlspecialchars($s['status']) ?></div>
                </div>
            </div>
            <div style="display:flex;gap:1.2rem;font-size:13px;color:#9aa3ac;margin-bottom:.8rem">
                <span><i class="fas fa-users"></i> <?= (int)$s['users'] ?></span>
                <span><i class="fas fa-chart-line"></i> <?= number_format((float)$s['revenue'], 2) ?></span>
            </div>
            <a class="btn btn-primary btn-sm" href="https://<?= htmlspecialchars($s['domain']) ?>" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> Visit Store</a>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$hasFree): ?>
<button class="btn btn-primary" id="nxNewStoreBtn"><i class="fas fa-plus"></i> New Free Store</button>
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
                <button class="btn btn-ghost" id="nxSmCancel" style="flex:1;">Cancel</button>
                <button class="btn btn-primary" id="nxSmCreate" style="flex:2;" disabled>Create Store</button>
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
            <div id="nxProgLink" style="display:none;margin-top:12px;"><a class="btn btn-primary" id="nxProgA" href="#" target="_blank" rel="noopener">Visit Your Store</a></div>
            <div style="margin-top:12px;"><button class="btn btn-ghost" id="nxSmClose2">Close</button></div>
        </div>
    </div>
</div>
<script>
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
})();
</script>
<?php require_once __DIR__ . '/_inc/footer.php'; ?>

<?php
$db = db();
$planMsg = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (($_POST['store_action'] ?? '') === 'set-plan')) {
    $tok = (string)($_POST['_csrf'] ?? '');
    $sess = (string)($_SESSION['csrf'] ?? '');
    if ($sess !== '' && $tok !== '' && hash_equals($sess, $tok)) {
        $sid = (int)($_POST['store_id'] ?? 0);
        $nplan = strtolower(trim((string)($_POST['plan'] ?? '')));
        if (!in_array($nplan, ['free', 'starter', 'professional', 'enterprise'], true)) {
            $planMsg = 'Invalid plan.';
        } else {
            try {
                $st = $db->prepare("SELECT slug, owner, user_id FROM hosted_stores WHERE id=? LIMIT 1");
                $st->execute([$sid]);
                $row = $st->fetch();
                if (!$row) {
                    $planMsg = 'Store not found.';
                } else {
                    $slug = (string)$row['slug'];
                    $st = $db->prepare("SELECT id, features FROM licenses WHERE allowed_domains LIKE ? LIMIT 1");
                    $st->execute(['%' . $slug . '.vexaris.net%']);
                    $lic = $st->fetch();
                    if ($lic) {
                        $f = json_decode((string)$lic['features'], true);
                        if (!is_array($f)) $f = [];
                        $f['plan'] = $nplan;
                        $db->prepare("UPDATE licenses SET features=? WHERE id=?")->execute([json_encode($f), (int)$lic['id']]);
                    }
                    $db->prepare("UPDATE hosted_stores SET plan=? WHERE id=?")->execute([$nplan, $sid]);
                    $db->prepare("INSERT INTO hosting_jobs (user_id, slug, plan, owner, site_name, lang, kind, status) VALUES (?,?,?,?,?,'en','provision','pending')")
                        ->execute([(int)($row['user_id'] ?? 0), $slug, $nplan, (string)($row['owner'] ?? ''), $slug]);
                    $planMsg = 'OK: plan set to ' . $nplan . ', limits queued.';
                }
            } catch (Exception $e) {
                $planMsg = 'Server error.';
            }
        }
    } else {
        $planMsg = 'Invalid request.';
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && in_array(($_POST['store_action'] ?? ''), ['update-one', 'update-all'], true)) {
    $tok = (string)($_POST['_csrf'] ?? '');
    $sess = (string)($_SESSION['csrf'] ?? '');
    if ($sess !== '' && $tok !== '' && hash_equals($sess, $tok)) {
        try {
            $latest = function_exists('saas_latest_version') ? saas_latest_version($db) : '';
            if ($latest === '') {
                $planMsg = 'No version found.';
            } else {
                $mkUpd = function ($sid) use ($db) {
                    $st = $db->prepare("SELECT slug, plan, owner, user_id, app_version FROM hosted_stores WHERE id=? AND status='active' LIMIT 1");
                    $st->execute([(int)$sid]);
                    $row = $st->fetch();
                    if (!$row) return false;
                    if (!version_compare($latest, (string)($row['app_version'] ?? ''), '>')) return false;
                    $st = $db->prepare("SELECT id FROM hosting_jobs WHERE slug=? AND status IN ('pending','claimed') LIMIT 1");
                    $st->execute([(string)$row['slug']]);
                    if ($st->fetch()) return false;
                    $st = $db->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
                    $st->execute([(int)($row['user_id'] ?? 0)]);
                    $oh = (string)$st->fetchColumn();
                    if (!preg_match('/^\\$(2y|2a|argon2i|argon2id)\\$/', $oh)) $oh = '';
                    $db->prepare("INSERT INTO hosting_jobs (user_id, slug, plan, owner, site_name, lang, kind, status, owner_hash) VALUES (?,?,?, ?, '','en','update','pending',?)")
                        ->execute([(int)($row['user_id'] ?? 0), (string)$row['slug'], (string)($row['plan'] ?? 'free'), (string)($row['owner'] ?? ''), $oh]);
                    return true;
                };
                if (($_POST['store_action'] ?? '') === 'update-all') {
                    $ids = $db->query("SELECT id FROM hosted_stores WHERE status='active' ORDER BY id DESC LIMIT 200")->fetchAll(PDO::FETCH_COLUMN);
                    $n = 0;
                    foreach ($ids as $sid) { if ($mkUpd($sid)) $n++; }
                    $planMsg = 'OK: ' . $n . ' store update queued (v' . $latest . ').';
                } else {
                    $planMsg = $mkUpd((int)($_POST['store_id'] ?? 0)) ? 'OK: update queued (v' . $latest . ').' : 'Store not found, up to date, or already queued.';
                }
            }
        } catch (Exception $e) {
            $planMsg = 'Server error.';
        }
    } else {
        $planMsg = 'Invalid request.';
    }
}
$latestVer = function_exists('saas_latest_version') ? saas_latest_version($db) : '';
$rows = [];
try {
    $rows = $db->query("SELECT h.id, h.user_id, h.slug, h.domain, h.plan, h.app_version, h.owner, h.users, h.revenue, h.currency, h.abuse_score, h.abuse_detail, h.status, h.reported_at, h.created_at, u.username AS uname FROM hosted_stores h LEFT JOIN users u ON u.id=h.user_id ORDER BY h.id DESC LIMIT 200")->fetchAll();
} catch (Exception $e) {}
$totalRev = 0.0;
$totalUsers = 0;
$revByCur = [];
foreach ($rows as $r) { $totalRev += (float)$r['revenue']; $totalUsers += (int)$r['users']; $c = strtoupper((string)($r['currency'] ?? 'TRY')); $revByCur[$c] = ($revByCur[$c] ?? 0) + (float)$r['revenue']; }
$revParts = [];
foreach ($revByCur as $c => $v) $revParts[] = htmlspecialchars(store_money($v, $c));
?>
<div class="main__header" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
    <div style="flex:1;min-width:220px;">
        <h1 style="margin:0;">Mağazalar</h1>
        <p style="margin:4px 0 0;"><?= count($rows) ?> store · <?= $totalUsers ?> users · <?= implode(' + ', $revParts) ?> revenue · latest <strong>v<?= htmlspecialchars($latestVer !== '' ? $latestVer : '?') ?></strong></p>
    </div>
    <form method="POST" action="/admin?tab=stores" style="margin:0;">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <input type="hidden" name="store_action" value="update-all">
        <button type="submit" class="btn btn-primary btn-sm" title="Queue update for all outdated stores"><i class="fas fa-cloud-arrow-down"></i> Update All</button>
    </form>
</div>
<?php if ($planMsg !== ''): ?>
<div style="background:<?= strncmp($planMsg, 'OK', 2) === 0 ? 'rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#4ade80' : 'rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171' ?>;padding:12px 18px;border-radius:12px;font-size:14px;font-weight:600;margin-bottom:20px;"><?= htmlspecialchars($planMsg) ?></div>
<?php endif; ?>
<div class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>Domain</th>
                    <th>Plan</th>
                    <th>Owner</th>
                    <th>Created By</th>
                    <th style="width:90px">Users</th>
                    <th style="width:120px">Revenue</th>
                    <th style="width:90px">Version</th>
                    <th style="width:100px">Status</th>
                    <th style="width:90px">Abuse</th>
                    <th style="width:110px;text-align:center">Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                <tr><td colspan="11" style="text-align:center;padding:2.5rem" class="text-muted">Henüz mağaza yok.</td></tr>
                <?php else: foreach ($rows as $i => $r): ?>
                <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td><a href="https://<?= htmlspecialchars($r['domain']) ?>" target="_blank" rel="noopener" style="color:var(--cyan);font-weight:600;"><?= htmlspecialchars($r['domain']) ?></a></td>
                    <td><span style="text-transform:uppercase;font-size:11px;font-weight:800;letter-spacing:.05em;"><?= htmlspecialchars($r['plan']) ?></span>
                        <form method="POST" action="/admin?tab=stores" style="margin:4px 0 0;display:flex;gap:4px;">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
                            <input type="hidden" name="store_action" value="set-plan">
                            <input type="hidden" name="store_id" value="<?= (int)$r['id'] ?>">
                            <select name="plan" style="background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:11px;padding:4px 6px;outline:none;">
                                <?php foreach (['free','starter','professional','enterprise'] as $po): ?>
                                <option value="<?= $po ?>" <?= ($r['plan'] ?? '') === $po ? 'selected' : '' ?>><?= ucfirst($po) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-ghost btn-sm" title="Save plan"><i class="fas fa-check"></i></button>
                        </form>
                    </td>
                    <td class="text-muted" style="font-size:13px;"><?= htmlspecialchars($r['owner']) ?></td>
                    <td style="font-size:13px;"><?= htmlspecialchars($r['uname'] ?? '-') ?></td>
                    <td><?= (int)$r['users'] ?></td>
                    <td><?= htmlspecialchars(store_money((float)$r['revenue'], (string)($r['currency'] ?? 'TRY'))) ?></td>
                    <td><?php $av = (string)($r['app_version'] ?? ''); $outd = $latestVer !== '' && $av !== '' && version_compare($latestVer, $av, '>'); ?>
                        <span style="font-size:12px;font-weight:700;">v<?= htmlspecialchars($av !== '' ? $av : '?') ?></span>
                        <?php if ($outd): ?>
                        <form method="POST" action="/admin?tab=stores" style="margin:4px 0 0;">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
                            <input type="hidden" name="store_action" value="update-one">
                            <input type="hidden" name="store_id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm" title="Update to v<?= htmlspecialchars($latestVer) ?>" style="color:#facc15;border-color:rgba(250,204,21,.4);"><i class="fas fa-cloud-arrow-down"></i> v<?= htmlspecialchars($latestVer) ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($r['status']) ?></td>
                    <td><?php $ab=(int)($r['abuse_score']??0); if($ab>=3): ?><span title="<?= htmlspecialchars($r['abuse_detail']??'') ?>" style="font-size:11px;font-weight:800;padding:3px 10px;border-radius:999px;background:rgba(239,68,68,.14);color:#f87171;"><?= $ab ?> AUTO</span><?php elseif($ab>0): ?><span title="<?= htmlspecialchars($r['abuse_detail']??'') ?>" style="font-size:11px;font-weight:800;padding:3px 10px;border-radius:999px;background:rgba(250,204,21,.14);color:#facc15;"><?= $ab ?></span><?php else: ?><span style="color:var(--dim);">-</span><?php endif; ?></td>
                    <td style="text-align:center;"><button class="btn btn-ghost btn-sm" onclick='openStorePopup(<?= json_encode(array_merge($r, ['revenue_fmt' => store_money((float)$r['revenue'], (string)($r['currency'] ?? 'TRY'))]), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'><i class="fas fa-eye"></i> View</button></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="storePopup" onclick="if(event.target===this)closeStorePopup()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#0d1b3e;border:1px solid rgba(0,212,255,.25);border-radius:20px;padding:2rem;max-width:640px;width:94%;position:relative;max-height:88vh;overflow-y:auto">
        <button onclick="closeStorePopup()" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.07);border:1px solid var(--border);border-radius:8px;color:var(--text);width:30px;height:30px;cursor:pointer;font-size:.95rem;display:flex;align-items:center;justify-content:center"><i class="fas fa-xmark"></i></button>
        <h3 style="font-size:1.05rem;font-weight:800;margin:0 0 4px;color:#fff"><i class="fas fa-store" style="color:var(--cyan);margin-right:8px"></i><span id="spDomain">-</span></h3>
        <p id="spPlan" style="font-size:12px;color:var(--muted);margin:0 0 1.4rem;text-transform:uppercase;letter-spacing:.06em;"></p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:1.2rem">
            <div style="background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:14px;padding:1.1rem;text-align:center;">
                <div style="font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;"><i class="fas fa-users"></i> Users</div>
                <div id="spUsers" style="font-size:26px;font-weight:900;">0</div>
            </div>
            <div style="background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:14px;padding:1.1rem;text-align:center;">
                <div style="font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;"><i class="fas fa-chart-line"></i> Revenue</div>
                <div id="spRevenue" style="font-size:26px;font-weight:900;">0</div>
            </div>
        </div>
        <div style="font-size:13px;color:var(--muted);display:grid;gap:6px;margin-bottom:1.4rem">
            <div>Owner: <span id="spOwner" style="color:var(--text);"></span></div>
            <div>Status: <span id="spStatus" style="color:var(--text);"></span></div>
            <div>Created: <span id="spCreated" style="color:var(--text);"></span></div>
            <div>Last report: <span id="spReported" style="color:var(--text);"></span></div>
        </div>
        <a id="spVisit" href="#" target="_blank" rel="noopener" class="btn btn-primary" style="width:100%;justify-content:center;"><i class="fas fa-arrow-up-right-from-square"></i> Visit Store</a>
        <button id="spPanel" onclick="goStorePanel()" class="btn btn-ghost" style="width:100%;justify-content:center;margin-top:10px;"><i class="fas fa-right-to-bracket"></i> Panele Git</button>
    </div>
</div>
<script>
var spSlug='';
function goStorePanel(){
  if(!spSlug)return;
  var b=document.getElementById('spPanel');b.disabled=true;
  var fd=new FormData();fd.append('action','issue');fd.append('slug',spSlug);
  var h={};try{if(typeof VX_CSRF!=='undefined')h['X-CSRF-Token']=VX_CSRF;}catch(e){}
  fetch('/api/admin-token.php',{method:'POST',headers:h,body:fd}).then(function(r){return r.json()}).then(function(d){
    b.disabled=false;
    if(d&&d.ok&&d.url){window.open(d.url,'_blank','noopener');}else{alert((d&&d.msg)||'Failed');}
  }).catch(function(){b.disabled=false;alert('Failed');});
}
function openStorePopup(r){
  spSlug=r.slug||'';
  document.getElementById('spDomain').textContent=r.domain||'-';
  document.getElementById('spPlan').textContent=(r.plan||'-')+' plan';
  document.getElementById('spUsers').textContent=r.users||0;
  document.getElementById('spRevenue').textContent=r.revenue_fmt||Number(r.revenue||0).toFixed(2);
  document.getElementById('spOwner').textContent=r.owner||'-';
  document.getElementById('spStatus').textContent=r.status||'-';
  document.getElementById('spCreated').textContent=r.created_at||'-';
  document.getElementById('spReported').textContent=r.reported_at||'-';
  document.getElementById('spVisit').href='https://'+(r.domain||'');
  var m=document.getElementById('storePopup');m.style.display='flex';
}
function closeStorePopup(){document.getElementById('storePopup').style.display='none';}
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeStorePopup();});
</script>

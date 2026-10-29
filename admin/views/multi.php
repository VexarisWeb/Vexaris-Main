<?php
require_once dirname(__DIR__, 2) . '/core/multi.php';
$mode = multi_mode();
$thr = multi_ban_threshold();
$ipMax = multi_ip_max();
$muEnabled = multi_enabled();
$groups = multi_group_rows($pdo, 'watch', 100);
$bannedGroups = multi_group_rows($pdo, 'banned', 50);
$meId = (int)(current_user()['id'] ?? 0);

function mu_members(PDO $pdo, string $ids): array {
    $out = [];
    $arr = array_values(array_filter(array_map('intval', explode(',', $ids))));
    if (!$arr) return $out;
    $in = implode(',', $arr);
    foreach ($pdo->query("SELECT id, username, email, is_active, reg_ip, created_at FROM users WHERE id IN ($in) ORDER BY id ASC") as $r) $out[] = $r;
    return $out;
}

$modeLabels = [
    'off' => ['Kapalı', '#8b949e'],
    'shadow' => ['İzleme (ban yok)', '#8b949e'],
    'notify' => ['Bildirim (ban yok)', '#7dd7fc'],
    'graduated' => ['Aktif (acımasız ban)', '#4ade80'],
    'harsh' => ['Eski mod', '#fb923c'],
];
$mLbl = $modeLabels[$mode] ?? ['Bilinmiyor', '#8b949e'];
?>
<style>
.mu-hero{display:flex;gap:1rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem}
.mu-chip{display:inline-flex;align-items:center;gap:6px;font-size:.75rem;font-weight:700;padding:6px 12px;border-radius:999px;border:1px solid var(--border);background:rgba(255,255,255,.03);color:var(--muted)}
.mu-grp{border:1px solid var(--border);border-radius:14px;margin-bottom:.8rem;overflow:hidden;background:rgba(255,255,255,.02)}
.mu-grp__hd{display:flex;align-items:center;gap:.6rem;padding:.8rem 1rem;flex-wrap:wrap;background:rgba(255,255,255,.02)}
.mu-grp__type{font-size:.68rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;padding:3px 9px;border-radius:6px}
.mu-t-device{background:rgba(251,146,60,.15);color:#fb923c;border:1px solid rgba(251,146,60,.35)}
.mu-t-ip{background:rgba(0,212,255,.12);color:#7dd7fc;border:1px solid rgba(0,212,255,.3)}
.mu-grp__meta{font-size:.75rem;color:var(--muted)}
.mu-grp__acts{margin-left:auto;display:flex;gap:.4rem;flex-wrap:wrap}
.mu-mem{display:flex;align-items:center;gap:.5rem;padding:.45rem 1rem;font-size:.82rem;border-top:1px solid rgba(255,255,255,.04);flex-wrap:wrap}
.mu-mem a{color:var(--cyan);text-decoration:none;font-weight:600}
.mu-mem .mu-off{font-size:.66rem;font-weight:800;padding:2px 7px;border-radius:5px;background:rgba(255,112,112,.14);color:#ff8080;border:1px solid rgba(255,112,112,.3)}
.mu-mem .mu-byp{font-size:.66rem;font-weight:800;padding:2px 7px;border-radius:5px;background:rgba(74,222,128,.12);color:#4ade80;border:1px solid rgba(74,222,128,.3)}
.mu-empty{text-align:center;padding:2.5rem 1rem;color:var(--muted);font-size:.85rem}
</style>

<div class="mu-hero">
    <span class="mu-chip"><i class="fas fa-shield-halved"></i> Mod: <b style="color:<?= $mLbl[1] ?>"><?= htmlspecialchars($mLbl[0]) ?></b></span>
    <span class="mu-chip"><i class="fas fa-gavel"></i> Ban eşiği: <b><?= $thr ?></b></span>
    <span class="mu-chip"><i class="fas fa-network-wired"></i> IP başına max: <b><?= $ipMax ?></b></span>
    <span class="mu-chip"><i class="fas fa-link"></i> İnceleme bekleyen: <b><?= count($groups) ?></b></span>
    <a href="/admin?tab=settings" class="btn" style="margin-left:auto;font-size:.78rem"><i class="fas fa-sliders"></i> Ayarlar &amp; Bypass</a>
</div>

<?php if (!$muEnabled): ?>
<div class="dash-alert" style="background:rgba(255,112,112,.07);border-color:rgba(255,112,112,.3)"><i class="fas fa-triangle-exclamation" style="color:#ff8080"></i><span>Koruma <b>kapalı</b>. Hiçbir kural uygulanmıyor.</span><a href="/admin?tab=settings">Aç &rarr;</a></div>
<?php elseif ($mode === 'notify' || $mode === 'shadow'): ?>
<div class="dash-alert" style="background:rgba(0,212,255,.07);border-color:rgba(0,212,255,.3)"><i class="fas fa-eye" style="color:var(--cyan)"></i><span>Mod <b><?= htmlspecialchars($mLbl[0]) ?></b>: gruplar tespit edilir, <b>ban uygulanmaz</b>.</span><a href="/admin?tab=settings">Modu değiştir &rarr;</a></div>
<?php endif; ?>

<div class="section-title"><i class="fas fa-user-shield"></i> İnceleme Bekleyen Gruplar</div>

<?php if (!$groups): ?>
<div class="table-card"><div class="mu-empty"><i class="fas fa-circle-check" style="color:#4ade80;font-size:1.3rem;display:block;margin-bottom:.5rem"></i>İnceleme bekleyen multi-hesap grubu yok.</div></div>
<?php else: foreach ($groups as $g):
    $members = mu_members($pdo, (string)$g['member_ids']);
    $isDev = $g['gtype'] === 'device';
    $suspect = ($g['reason'] ?? '') === 'same_fingerprint' && (int)$g['member_count'] > 1;
    $ipList = array_values(array_filter(array_map('trim', explode(',', (string)$g['ip_set']))));
?>
<div class="mu-grp" id="mg-<?= htmlspecialchars(substr($g['gkey'], 0, 10)) ?>">
    <div class="mu-grp__hd">
        <span class="mu-grp__type <?= $isDev ? 'mu-t-device' : 'mu-t-ip' ?>"><?= $isDev ? 'CİHAZ' : 'IP' ?></span>
        <b style="font-size:.9rem"><?= count($members) ?> hesap</b>
        <span class="mu-grp__meta"><?= (int)$g['active_count'] ?> aktif</span>
        <span class="mu-grp__meta">&middot; <?= htmlspecialchars((string)$g['reason']) ?></span>
        <span class="mu-grp__meta">&middot; son: <?= htmlspecialchars(substr((string)$g['last_seen'], 0, 16)) ?></span>
        <?php if (count($ipList) > 1): ?>
        <span class="mu-grp__meta" style="color:#fbbf24" title="Aynı cihaz, farklı ağ"><i class="fas fa-triangle-exclamation"></i> IP değişimi</span>
        <?php endif; ?>
        <div class="mu-grp__acts">
            <button class="btn-tbl btn-tbl-info" onclick="muOpenUser(<?= (int)($members[0]['id'] ?? 0) ?>,<?= htmlspecialchars(json_encode((string)($members[0]['username'] ?? '')), ENT_QUOTES) ?>)"><i class="fas fa-eye"></i> Detay</button>
            <button class="btn-tbl btn-tbl-del" onclick="muBan(<?= htmlspecialchars(json_encode($g['gkey']), ENT_QUOTES) ?>)"><i class="fas fa-gavel"></i> Hepsini banla</button>
            <button class="btn-tbl" onclick="muAct(<?= htmlspecialchars(json_encode($g['gkey']), ENT_QUOTES) ?>,'bypass')" title="Gruptaki e-postaları bypass listesine ekle"><i class="fas fa-user-check"></i> Bypass</button>
            <button class="btn-tbl" onclick="muAct(<?= htmlspecialchars(json_encode($g['gkey']), ENT_QUOTES) ?>,'dismiss')"><i class="fas fa-check"></i> Yoksay</button>
        </div>
    </div>
    <?php foreach ($members as $m):
        $isEx = multi_is_exempt($pdo, (int)$m['id']);
    ?>
    <div class="mu-mem">
        <i class="fas fa-user" style="color:var(--muted);font-size:.7rem"></i>
        <a href="#" onclick="muOpenUser(<?= (int)$m['id'] ?>,<?= htmlspecialchars(json_encode((string)$m['username']), ENT_QUOTES) ?>);return false">#<?= (int)$m['id'] ?> <?= htmlspecialchars((string)$m['username']) ?></a>
        <span style="color:var(--muted);font-size:.76rem"><?= htmlspecialchars((string)$m['email']) ?></span>
        <?php if ((int)$m['is_active'] === 0): ?><span class="mu-off">BANLI</span><?php endif; ?>
        <?php if ($isEx): ?><span class="mu-byp">MUAF</span><?php endif; ?>
        <span style="margin-left:auto;color:var(--muted);font-size:.72rem;font-family:monospace"><?= htmlspecialchars((string)($m['reg_ip'] ?? '')) ?></span>
        <span style="color:var(--muted);font-size:.72rem"><?= htmlspecialchars(substr((string)$m['created_at'], 0, 10)) ?></span>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; endif; ?>

<?php if ($bannedGroups): ?>
<div class="section-title" style="margin-top:1.6rem"><i class="fas fa-gavel"></i> Ceza Verilenler (<?= count($bannedGroups) ?>)</div>
<details class="table-card" style="padding:.6rem 1rem">
    <summary style="cursor:pointer;font-size:.82rem;color:var(--muted)"><?= count($bannedGroups) ?> grup banlandı — listeyi aç</summary>
    <?php foreach ($bannedGroups as $g): $members = mu_members($pdo, (string)$g['member_ids']); ?>
    <div class="mu-mem" style="border-top:1px solid rgba(255,255,255,.04)">
        <span class="mu-grp__type <?= $g['gtype'] === 'device' ? 'mu-t-device' : 'mu-t-ip' ?>" style="font-size:.62rem"><?= $g['gtype'] === 'device' ? 'CİHAZ' : 'IP' ?></span>
        <span style="font-size:.8rem"><?= count($members) ?> hesap &middot; <?= htmlspecialchars((string)$g['reason']) ?> &middot; <?= htmlspecialchars(substr((string)$g['last_seen'], 0, 16)) ?></span>
        <span style="margin-left:auto;font-size:.74rem;color:var(--muted)"><?php
            echo htmlspecialchars(implode(', ', array_map(fn($m) => '#' . $m['id'] . ' ' . $m['username'], array_slice($members, 0, 4))));
            echo count($members) > 4 ? ' …' : '';
        ?></span>
    </div>
    <?php endforeach; ?>
</details>
<?php endif; ?>

<script>
function muOpenUser(id, name){ if(!id) return; location.href='/admin?tab=users&u='+id+(name?('&q='+encodeURIComponent(name)):''); }
async function muAct(gkey, act, extra){
  var fd=new FormData(); fd.append('action','multi_group'); fd.append('gkey',gkey); fd.append('op',act);
  if(extra) for(var k in extra) fd.append(k, extra[k]);
  fd.append('_csrf', VX_CSRF);
  try{
    var r=await fetch('/api/users',{method:'POST',body:fd});
    var d=await r.json();
    if(d.ok){ location.reload(); } else { alert(d.msg||'Hata'); }
  }catch(e){ alert('Bağlantı hatası'); }
}
function muBan(gkey){
  if(!confirm('Bu gruptaki TÜM hesaplar kalıcı olarak banlanacak. Onaylıyor musun?')) return;
  muAct(gkey,'ban');
}
</script>

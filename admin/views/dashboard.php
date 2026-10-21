<?php
$today_users = 0; $today_views = 0; $open_tickets = 0; $pending_orders = 0;
$pending_apps = []; $open_list = []; $unread_mails = []; $unread_n = 0;
$chart = []; $top_pages = []; $recent_users = [];
try {
    $today_users = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    $today_views = (int)$pdo->query("SELECT COUNT(*) FROM page_views WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    $open_tickets = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status='open' AND source='support'")->fetchColumn();
    $pending_orders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
    $pending_apps = $pdo->query("SELECT t.id, t.subject, t.created_at, u.username FROM support_tickets t JOIN users u ON u.id=t.user_id WHERE t.source='application' AND t.status='open' ORDER BY t.created_at DESC LIMIT 5")->fetchAll();
    $open_list = $pdo->query("SELECT t.id, t.subject, t.last_msg_at, u.username FROM support_tickets t JOIN users u ON u.id=t.user_id WHERE t.status='open' AND t.source='support' ORDER BY t.last_msg_at DESC LIMIT 5")->fetchAll();
    try {
        $unread_n = (int)$pdo->query("SELECT COUNT(*) FROM inbound_emails WHERE is_read=0 AND deleted=0 AND spam=0 AND spam_checked=1")->fetchColumn();
        $unread_mails = $pdo->query("SELECT id, to_addr, from_addr, subject, created_at FROM inbound_emails WHERE deleted=0 AND spam=0 AND spam_checked=1 ORDER BY id DESC LIMIT 5")->fetchAll();
    } catch (Throwable $e) {}
    $rows = $pdo->query("SELECT DATE(created_at) d, COUNT(*) c FROM page_views WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY d")->fetchAll();
    $ur = $pdo->query("SELECT DATE(created_at) d, COUNT(*) c FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY d")->fetchAll();
    $map = []; $umap = [];
    foreach ($rows as $r) $map[$r['d']] = (int)$r['c'];
    foreach ($ur as $r) $umap[$r['d']] = (int)$r['c'];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $chart[] = ['d' => $d, 'lbl' => date('d.m', strtotime($d)), 'v' => $map[$d] ?? 0, 'u' => $umap[$d] ?? 0];
    }
    $top_pages = $pdo->query("SELECT page, COUNT(*) as cnt FROM page_views GROUP BY page ORDER BY cnt DESC LIMIT 5")->fetchAll();
    $recent_users = $pdo->query("SELECT id,username,email,is_admin,role,created_at FROM users ORDER BY created_at DESC LIMIT 8")->fetchAll();
} catch (Throwable $e) {}
$maxV = 1;
foreach ($chart as $c) $maxV = max($maxV, $c['v']);
?>
<!-- Stats -->
<div class="stats-grid">
    <div class="stat">
        <div class="stat__top">
            <div class="stat__icon blue"><i class="fas fa-users"></i></div>
            <div class="stat__today">Bugün <span>+<?= $today_users ?></span></div>
        </div>
        <div class="stat__val"><?= $total_users ?></div>
        <div class="stat__label">Toplam Kayıt</div>
    </div>
    <div class="stat">
        <div class="stat__top">
            <div class="stat__icon green"><i class="fas fa-eye"></i></div>
            <div class="stat__today">Bugün <span>+<?= $today_views ?></span></div>
        </div>
        <div class="stat__val"><?= $total_views ?></div>
        <div class="stat__label">Sayfa Görüntülenme</div>
    </div>
    <div class="stat">
        <div class="stat__top">
            <div class="stat__icon orange"><i class="fas fa-clock"></i></div>
            <div class="stat__today"><?= $pending_orders > 0 ? '<span style="color:#cbb894">işlem bekliyor</span>' : 'temiz' ?></div>
        </div>
        <div class="stat__val"><?= $pending_orders ?></div>
        <div class="stat__label">Bekleyen Sipariş</div>
    </div>
    <div class="stat">
        <div class="stat__top">
            <div class="stat__icon pink"><i class="fas fa-ticket"></i></div>
            <div class="stat__today"><?= $open_tickets > 0 ? '<span style="color:#d79595">yanıt bekliyor</span>' : 'temiz' ?></div>
        </div>
        <div class="stat__val"><?= $open_tickets ?></div>
        <div class="stat__label">Açık Destek Talebi</div>
    </div>
</div>
<style>@media(max-width:768px){.stats-grid{grid-template-columns:1fr 1fr !important}}</style>

<?php if ($pending_apps): ?>
<div class="dash-alert"><i class="fas fa-file-signature"></i><span><b><?= count($pending_apps) ?> bekleyen başvuru</b> var.</span><a href="/admin?tab=tickets">İncele &rarr;</a></div>
<?php endif; ?>
<?php if ($unread_n > 0): ?>
<div class="dash-alert" style="background:rgba(0,212,255,.07);border-color:rgba(0,212,255,.3)"><i class="fas fa-inbox" style="color:var(--cyan)"></i><span><b><?= $unread_n ?> okunmamış e-posta</b> var.</span><a href="/admin?tab=inbox">Gelen Kutusu &rarr;</a></div>
<?php endif; ?>

<div class="section-title"><i class="fas fa-bolt"></i> Hızlı İşlemler</div>
<div class="quick-grid">
    <a class="quick" href="/admin?tab=inbox"><i class="fas fa-inbox"></i> Gelen Kutusu<?= $unread_n > 0 ? ' (' . $unread_n . ')' : '' ?></a>
    <a class="quick" href="/admin?tab=tickets"><i class="fas fa-ticket"></i> Destek Talepleri</a>
    <a class="quick" href="/admin?tab=products"><i class="fas fa-store"></i> Ürünler</a>
    <a class="quick" href="/admin?tab=versions"><i class="fas fa-box-archive"></i> Versiyon Yayınla</a>
</div>

<div class="two-col">
    <div>
        <div class="section-title"><i class="fas fa-chart-line"></i> Son 7 Gün — Ziyaret / Kayıt</div>
        <div class="table-card" style="padding:1rem 1.2rem">
            <div style="display:flex;gap:1rem;font-size:.72rem;color:var(--muted);margin-bottom:.4rem"><span><i class="fas fa-square" style="color:var(--cyan)"></i> Görüntülenme</span><span><i class="fas fa-square" style="color:rgba(255,255,255,.25)"></i> Kayıt</span></div>
            <div class="chart-bars">
                <?php foreach ($chart as $c): $h = $maxV > 0 ? max(3, round($c['v'] / $maxV * 110)) : 3; $h2 = $c['u'] > 0 ? max(3, round($c['u'] / max(1, $maxV) * 110)) : 3; ?>
                <div class="chart-col" title="<?= $c['lbl'] ?>: <?= $c['v'] ?> ziyaret, <?= $c['u'] ?> kayıt">
                    <div class="chart-val"><?= $c['v'] ?></div>
                    <div style="display:flex;gap:3px;align-items:flex-end;height:110px">
                        <div class="chart-bar" style="height:<?= $h ?>px"></div>
                        <div class="chart-bar dim" style="height:<?= $h2 ?>px"></div>
                    </div>
                    <div class="chart-lbl"><?= $c['lbl'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div>
        <div class="section-title"><i class="fas fa-chart-bar"></i> En Çok Ziyaret</div>
        <div class="table-card" style="padding:.75rem 0">
            <?php $maxCnt = $top_pages ? max(array_column($top_pages,'cnt')) : 1;
            foreach ($top_pages as $pg): $pct = $maxCnt>0?round($pg['cnt']/$maxCnt*100):0; ?>
            <div class="page-row">
                <div class="page-row__name">/<?= htmlspecialchars($pg['page']) ?></div>
                <div class="page-row__bar"><div class="page-row__fill" style="width:<?=$pct?>%"></div></div>
                <div class="page-row__cnt"><?= $pg['cnt'] ?></div>
            </div>
            <?php endforeach; if (!$top_pages): ?>
                <div style="text-align:center;padding:2rem;color:var(--muted);font-size:.85rem">Henüz veri yok.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="two-col">
    <div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin:1.5rem 0 1rem">
            <div class="section-title" style="margin:0"><i class="fas fa-ticket"></i> Açık Destek Talepleri</div>
            <a href="/admin?tab=tickets" style="font-size:.83rem;color:var(--cyan);text-decoration:none;font-weight:600">Tümü &rarr;</a>
        </div>
        <div class="table-card"><div class="table-wrap"><table>
            <thead><tr><th>#</th><th>Konu</th><th>Kullanıcı</th><th>Son Mesaj</th></tr></thead>
            <tbody>
            <?php if ($open_list): foreach ($open_list as $tk): ?>
                <tr>
                    <td class="text-muted"><?= $tk['id'] ?></td>
                    <td style="font-weight:600"><?= htmlspecialchars($tk['subject']) ?></td>
                    <td><?= htmlspecialchars($tk['username']) ?></td>
                    <td class="text-muted"><?= date('d.m.Y H:i', strtotime($tk['last_msg_at'])) ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="4" class="text-muted" style="text-align:center;padding:2rem">Açık talep yok. 🎉</td></tr>
            <?php endif; ?>
            </tbody>
        </table></div></div>
    </div>
    <div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin:1.5rem 0 1rem">
            <div class="section-title" style="margin:0"><i class="fas fa-inbox"></i> Son E-postalar</div>
            <a href="/admin?tab=inbox" style="font-size:.83rem;color:var(--cyan);text-decoration:none;font-weight:600">Gelen Kutusu &rarr;</a>
        </div>
        <div class="table-card"><div class="table-wrap"><table>
            <thead><tr><th>Kime</th><th>Konu</th><th>Kimden</th></tr></thead>
            <tbody>
            <?php if ($unread_mails): foreach ($unread_mails as $m): ?>
                <tr>
                    <td><span class="badge badge-admin"><?= htmlspecialchars($m['to_addr']) ?></span></td>
                    <td style="font-weight:600"><?= htmlspecialchars($m['subject'] !== '' ? $m['subject'] : '(konu yok)') ?></td>
                    <td class="text-muted"><?= htmlspecialchars($m['from_addr']) ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="3" class="text-muted" style="text-align:center;padding:2rem">Henüz e-posta yok.</td></tr>
            <?php endif; ?>
            </tbody>
        </table></div></div>
    </div>
</div>

<?php if ($pending_apps): ?>
<div style="display:flex;align-items:center;justify-content:space-between;margin:1.5rem 0 1rem">
    <div class="section-title" style="margin:0"><i class="fas fa-file-signature"></i> Bekleyen Başvurular</div>
    <a href="/admin?tab=tickets" style="font-size:.83rem;color:var(--cyan);text-decoration:none;font-weight:600">Tümü &rarr;</a>
</div>
<div class="table-card"><div class="table-wrap"><table>
    <thead><tr><th>#</th><th>Konu</th><th>Kullanıcı</th><th>Tarih</th></tr></thead>
    <tbody>
    <?php foreach ($pending_apps as $a): ?>
        <tr>
            <td class="text-muted"><?= $a['id'] ?></td>
            <td style="font-weight:600"><?= htmlspecialchars($a['subject']) ?></td>
            <td><?= htmlspecialchars($a['username']) ?></td>
            <td class="text-muted"><?= date('d.m.Y H:i', strtotime($a['created_at'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?php endif; ?>

<div class="section-title" style="margin-top:1.5rem"><i class="fas fa-users"></i> Son Kayıtlar</div>
<div class="table-card"><div class="table-wrap">
    <table>
        <thead><tr><th>#</th><th>Kullanıcı</th><th>E-posta</th><th>Rol</th><th>Tarih</th></tr></thead>
        <tbody>
        <?php if ($recent_users): foreach ($recent_users as $u): ?>
            <tr>
                <td class="text-muted"><?= $u['id'] ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($u['username']) ?></td>
                <td class="text-muted"><?= htmlspecialchars($u['email']) ?></td>
                <td><?php $dRole = $u['role'] ?? (($u['is_admin'] ? 'admin' : 'user')); if ($dRole === 'admin'): ?><span class="badge badge-admin"><i class="fas fa-shield-halved"></i> Admin</span><?php elseif ($dRole === 'yonetici'): ?><span class="badge badge-admin" style="background:rgba(251,146,60,.12);color:#fb923c;border:1px solid rgba(251,146,60,.25)"><i class="fas fa-user-gear"></i> Yönetici</span><?php elseif ($dRole === 'moderator'): ?><span class="badge badge-admin" style="background:rgba(236,72,153,.12);color:#f472b6;border:1px solid rgba(236,72,153,.25)"><i class="fas fa-user-tie"></i> Moderatör</span><?php elseif ($dRole === 'destek_ekibi'): ?><span class="badge badge-admin" style="background:rgba(34,197,94,.12);color:#4ade80;border:1px solid rgba(34,197,94,.2)"><i class="fas fa-headset"></i> Destek Ekibi</span><?php else: ?><span class="badge badge-user">Üye</span><?php endif; ?></td>
                <td class="text-muted"><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="5" class="text-muted" style="text-align:center;padding:2rem">Henüz kayıt yok.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div></div>

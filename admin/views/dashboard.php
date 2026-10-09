<?php
$today_users = $pdo ? (int)$pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn() : 0;
$today_views = $pdo ? (int)$pdo->query("SELECT COUNT(*) FROM page_views WHERE DATE(created_at)=CURDATE()")->fetchColumn() : 0;
$today_tickets = $pdo ? (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE DATE(created_at)=CURDATE()")->fetchColumn() : 0;

$top_pages    = $pdo ? $pdo->query("SELECT page, COUNT(*) as cnt FROM page_views GROUP BY page ORDER BY cnt DESC LIMIT 5")->fetchAll() : [];
$recent_users = $pdo ? $pdo->query("SELECT id,username,email,is_admin,role,created_at FROM users ORDER BY created_at DESC LIMIT 8")->fetchAll() : [];
$dash_tickets = $pdo ? $pdo->query("SELECT t.id, t.subject, t.status, t.last_msg_at, u.username FROM support_tickets t JOIN users u ON u.id = t.user_id ORDER BY t.last_msg_at DESC LIMIT 5")->fetchAll() : [];
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
            <div class="stat__icon pink"><i class="fas fa-ticket"></i></div>
            <div class="stat__today">Bugün <span>+<?= $today_tickets ?></span></div>
        </div>
        <div class="stat__val"><?= $total_tickets ?></div>
        <div class="stat__label">Aktif Destek Talebi</div>
    </div>
</div>

<div class="two-col">
    <div>
        <div class="section-title"><i class="fas fa-users"></i> Son Kayıtlar</div>
        <div class="table-card">
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

<div style="display:flex;align-items:center;justify-content:space-between;margin:1.5rem 0 1rem">
    <div class="section-title" style="margin:0"><i class="fas fa-ticket"></i> Son Destek Talepleri</div>
    <a href="/admin?tab=tickets" style="font-size:.83rem;color:var(--cyan);text-decoration:none;font-weight:600">Tümünü Gör &rarr;</a>
</div>
<div class="table-card">
    <table>
        <thead><tr><th>#</th><th>Konu</th><th>Kullanıcı</th><th>Durum</th><th>Son Mesaj</th></tr></thead>
        <tbody>
        <?php
        if ($dash_tickets): foreach ($dash_tickets as $tk): ?>
            <tr>
                <td class="text-muted"><?= $tk['id'] ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($tk['subject']) ?></td>
                <td><?= htmlspecialchars($tk['username']) ?></td>
                <td><?php
                    if ($tk['status'] === 'closed')      echo '<span class="badge badge-rev"><i class="fas fa-lock"></i> Kapalı</span>';
                    elseif ($tk['status'] === 'answered') echo '<span class="badge badge-active"><i class="fas fa-reply"></i> Cevaplandı</span>';
                    else                                  echo '<span class="badge badge-susp"><i class="fas fa-clock"></i> Açık</span>';
                ?></td>
                <td class="text-muted"><?= date('d.m.Y H:i', strtotime($tk['last_msg_at'])) ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="5" class="text-muted" style="text-align:center;padding:2rem">Henüz destek talebi yok.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

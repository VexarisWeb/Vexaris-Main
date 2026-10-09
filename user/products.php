<?php
$activePage = 'products';
require_once __DIR__ . '/_inc/header.php';

$uid = (int)$user['id'];
$pdo = db();
$products = [];

if ($pdo) {
    try {
        $st = $pdo->prepare("SELECT p.*, up.granted_at, (SELECT COUNT(*) FROM download_tokens dt WHERE dt.product_id = p.id AND dt.user_id = ?) AS indirme_sayisi, (SELECT v.version FROM versions v WHERE v.product_id = p.id AND v.is_active=1 ORDER BY v.created_at DESC LIMIT 1) AS latest_version, (SELECT v.file_name FROM versions v WHERE v.product_id = p.id AND v.is_active=1 ORDER BY v.created_at DESC LIMIT 1) AS latest_file, (SELECT v.created_at FROM versions v WHERE v.product_id = p.id AND v.is_active=1 ORDER BY v.created_at DESC LIMIT 1) AS latest_version_created FROM user_products up JOIN products p ON p.id = up.product_id WHERE up.user_id = ? AND p.is_active = 1 ORDER BY up.granted_at DESC");
        $st->execute([$uid, $uid]);
        $products = $st->fetchAll();
    } catch (Exception $e) {}
}
?>

<div class="main__header">
    <h1>My Products</h1>
    <p>Download the products assigned to you here.</p>
</div>

<div class="panel-card">
    <div class="panel-card__body">
        <?php if ($products): ?>
        <table>
            <thead><tr><th>Product</th><th>Description</th><th>File</th><th>Size</th><th>Current Version</th><th>Assigned Date</th><th style="text-align:right">Action</th></tr></thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td style="font-weight:600"><i class="fas fa-box-open" style="color:var(--cyan);margin-right:8px"></i><?= htmlspecialchars($p['name']) ?></td>
                    <td style="color:rgba(255,255,255,.85);max-width:320px;font-size:.85rem;line-height:1.5"><div class="md-body" style="display:block"><?= $p['description'] ?: '—' ?></div></td>
                    <td style="color:rgba(255,255,255,.55);font-size:.8rem"><?= htmlspecialchars($p['file_name']) ?></td>
                    <td style="color:rgba(255,255,255,.45);font-size:.8rem"><?= htmlspecialchars($p['file_size'] ?: '—') ?></td>
                    <td><?php if (!empty($p['latest_version'])): ?><span class="badge" style="background:rgba(0,212,255,.12);color:var(--cyan);border:1px solid var(--border)">v<?= htmlspecialchars($p['latest_version']) ?></span><?php else: ?><span style="color:var(--muted);font-size:.8rem">—</span><?php endif; ?></td>
                    <td style="color:rgba(255,255,255,.45);font-size:.8rem"><?= date('d.m.Y H:i', strtotime($p['granted_at'])) ?></td>
                    <td style="text-align:right">
                        <?php $isUpdate = !empty($p['latest_version']) && (strtotime($p['granted_at']) < strtotime($p['latest_version_created'] ?? $p['granted_at']) || (int)$p['indirme_sayisi']>0); ?>
                        <a href="/api/download?product_id=<?= (int)$p['id'] ?>" class="btn-panel <?= $isUpdate ? 'btn-cyan' : 'btn-green' ?>"><i class="fas fa-<?= $isUpdate ? 'arrow-rotate' : 'download' ?>"></i> <?= $isUpdate ? 'Update' : 'Download' ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p style="font-size:.75rem;color:rgba(255,255,255,.35);margin-top:1rem">
            <i class="fas fa-circle-info"></i> Each download creates a <strong>5-minute, single-use</strong> link. Click the button again to re-download.
        </p>
        <?php else: ?>
            <div class="empty"><i class="fas fa-box-open"></i>No products assigned to you.</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/_inc/footer.php'; ?>
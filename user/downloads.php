<?php
$activePage = 'downloads';
require_once __DIR__ . '/_inc/header.php';

$uid = (int)$user['id'];
$pdo = db();
$downloads = [];

if ($pdo) {
    try {
        $st = $pdo->prepare("SELECT d.*, o.product_name FROM downloads d LEFT JOIN orders o ON o.id = d.order_id WHERE d.user_id = ? ORDER BY d.created_at DESC");
        $st->execute([$uid]);
        $downloads = $st->fetchAll();
    } catch (Exception $e) {}
}
?>

<div class="main__header">
    <h1>Downloads</h1>
    <p>Download files for products you purchased here.</p>
</div>

<div class="panel-card">
    <div class="panel-card__body">
        <?php if ($downloads): ?>
        <table>
            <thead><tr><th>File</th><th>Product</th><th class="hide-m">Size</th><th>Downloads</th><th class="hide-m">Last Download</th><th style="text-align:right">Action</th></tr></thead>
            <tbody>
            <?php foreach ($downloads as $d): ?>
                <tr>
                    <td style="font-weight:600;word-break:break-all"><i class="fas fa-file" style="color:var(--cyan);margin-right:8px"></i><?= htmlspecialchars($d['file_name']) ?></td>
                    <td style="color:rgba(255,255,255,.55)"><?= htmlspecialchars($d['product_name'] ?: '—') ?></td>
                    <td class="hide-m" style="color:rgba(255,255,255,.45);font-size:.8rem"><?= htmlspecialchars($d['file_size'] ?: '—') ?></td>
                    <td><?= (int)$d['download_count'] ?></td>
                    <td class="hide-m" style="color:rgba(255,255,255,.45);font-size:.8rem"><?= $d['last_download_at'] ? date('d.m.Y H:i', strtotime($d['last_download_at'])) : '—' ?></td>
                    <td style="text-align:right">
                        <a href="/api/download?id=<?= (int)$d['id'] ?>" class="btn-panel btn-green"><i class="fas fa-download"></i> Download</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="empty"><i class="fas fa-download"></i>No downloadable files found.</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/_inc/footer.php'; ?>
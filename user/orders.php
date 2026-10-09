<?php
$activePage = 'orders';
require_once __DIR__ . '/_inc/header.php';

$uid = (int)$user['id'];
$pdo = db();
$orders = [];

if ($pdo) {
    try {
        $pdo->prepare("UPDATE orders SET status='cancelled' WHERE user_id=? AND provider='whop' AND status='pending' AND created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)")->execute([$uid]);
    } catch (Exception $e) {}
    try {
        $paidNo = (string)($_GET['order'] ?? '');
        if (($_GET['paid'] ?? '') === '1' && $paidNo !== '') {
            $pq = $pdo->prepare("SELECT status FROM orders WHERE user_id=? AND order_no=? LIMIT 1");
            $pq->execute([$uid, $paidNo]);
            $paidStatus = (string)$pq->fetchColumn();
        }
    } catch (Exception $e) {}
    try {
        $st = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
        $st->execute([$uid]);
        $orders = $st->fetchAll();
    } catch (Exception $e) {}
}
?>

<div class="main__header">
    <h1>My Orders</h1>
    <p>List and status of your orders.</p>
</div>

<?php if (!empty($paidStatus) && $paidStatus === 'completed'): ?>
<div style="background:rgba(116,192,145,.08);border:1px solid rgba(116,192,145,.3);border-radius:12px;padding:.9rem 1.2rem;font-size:.86rem;color:#74c091;margin-bottom:1.2rem"><i class="fas fa-circle-check"></i> Payment confirmed! Your plan is active.</div>
<?php elseif (!empty($paidStatus)): ?>
<div style="background:rgba(224,177,60,.08);border:1px solid rgba(224,177,60,.35);border-radius:12px;padding:.9rem 1.2rem;font-size:.86rem;color:#e0b13c;margin-bottom:1.2rem"><i class="fas fa-hourglass-half"></i> Payment is being processed. This page updates automatically once confirmed.</div>
<?php endif; ?>

<div class="panel-card">
    <div class="panel-card__body">
        <?php if ($orders): ?>
        <table>
            <thead><tr><th>#</th><th>Order No</th><th>Product</th><th>Amount</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): $cur = strtoupper($o['currency'] !== '' ? $o['currency'] : 'USD'); ?>
                <tr>
                    <td style="color:rgba(255,255,255,.45);font-size:.8rem"><?= (int)$o['id'] ?></td>
                    <td style="color:rgba(255,255,255,.45);font-size:.8rem">#<?= htmlspecialchars($o['order_no']) ?></td>
                    <td style="font-weight:600"><?= htmlspecialchars($o['product_name']) ?></td>
                    <td><?= number_format((float)$o['amount'], 2) ?> <?= htmlspecialchars($cur) ?></td>
                    <td style="color:rgba(255,255,255,.45);font-size:.8rem"><?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></td>
                    <td><?php if ($o['status'] === 'completed'): ?><span class="badge badge-approved"><i class="fas fa-check"></i> Completed</span><?php elseif ($o['status'] === 'pending'): ?><span class="badge badge-pending"><i class="fas fa-hourglass-half"></i> Pending</span><?php else: ?><span class="badge badge-rejected"><i class="fas fa-xmark"></i> <?= htmlspecialchars(ucfirst($o['status'])) ?></span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="empty"><i class="fas fa-receipt"></i>No orders yet.</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/_inc/footer.php'; ?>
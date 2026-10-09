<?php
$activePage = 'dashboard';
require_once __DIR__ . '/_inc/header.php';

$uid = (int)$user['id'];
$pdo = db();

$orderCount  = 0;
$dlTotal     = 0;
$recentOrders = [];
$recentNotifs = [];

if ($pdo) {
    try {
        $stO = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $stO->execute([$uid]);
        $orderCount = (int)$stO->fetchColumn();

        $stD = $pdo->prepare("SELECT COALESCE(SUM(download_count),0) FROM downloads WHERE user_id = ?");
        $stD->execute([$uid]);
        $dlTotal = (int)$stD->fetchColumn();

        $stR = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        $stR->execute([$uid]);
        $recentOrders = $stR->fetchAll();

        $stN = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        $stN->execute([$uid]);
        $recentNotifs = $stN->fetchAll();
    } catch (Exception $e) {}
}

$statusLabels = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
?>

<?php
$myPlan = null;
if ($pdo && $uid) {
    try {
        $stS = $pdo->prepare("SELECT plan, cycle, status, current_period_end FROM user_subscriptions WHERE user_id = ? LIMIT 1");
        $stS->execute([$uid]);
        $myPlan = $stS->fetch() ?: null;
        if ($myPlan && ($myPlan['status'] !== 'active' || (int)$myPlan['current_period_end'] < time())) $myPlan = null;
    } catch (Exception $e) { $myPlan = null; }
}
?>
<div class="main__header">
    <h1>Hello, <?= htmlspecialchars($user['username']) ?></h1>
    <p>Welcome back. Your orders and downloads live here.</p>
</div>

<div class="panel-card" style="border-color:rgba(79,143,192,.3);background:linear-gradient(135deg,rgba(79,143,192,.1),rgba(79,143,192,.04))">
    <div class="panel-card__body" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
        <div style="display:flex;gap:12px;align-items:center">
            <div style="width:42px;height:42px;background:linear-gradient(135deg,#4f8fc0,#2c5a7c);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff"><i class="fas fa-crown"></i></div>
            <div>
                <div style="font-weight:800;color:#fff">Current plan: <?= $myPlan ? htmlspecialchars(ucfirst($myPlan['plan'])) . ' <span style="font-size:.75rem;font-weight:600;color:rgba(255,255,255,.55)">(' . htmlspecialchars($myPlan['cycle'] === 'yearly' ? 'Yearly' : 'Monthly') . ' · until ' . date('d.m.Y', (int)$myPlan['current_period_end']) . ')</span>' : 'Free' ?></div>
                <div style="font-size:.82rem;color:rgba(255,255,255,.6)"><?= $myPlan ? 'Your store runs on a paid plan. Thank you!' : 'Upgrade for more products, revenue and priority support.' ?></div>
            </div>
        </div>
        <a href="/pricing" class="btn-panel btn-cyan"><i class="fas fa-arrow-up"></i> <?= $myPlan ? 'Change plan' : 'View plans' ?></a>
    </div>
</div>

<?php
$myProducts = [];
$latestVersions = [];
if ($pdo && $uid) {
    try {
        $st = $pdo->prepare("SELECT product_id FROM user_products WHERE user_id=?");
        $st->execute([$uid]);
        $myProducts = $st->fetchAll(PDO::FETCH_COLUMN);
        if ($myProducts) {
            $in = implode(',', array_fill(0, count($myProducts), '?'));
            $q = $pdo->prepare("SELECT v.* FROM versions v WHERE v.product_id IN ($in) AND v.is_active=1 ORDER BY v.created_at DESC");
            $q->execute($myProducts);
            $all = $q->fetchAll();
            foreach ($myProducts as $pid) {
                foreach ($all as $v) { if ((int)$v['product_id']===(int)$pid) { $latestVersions[$pid]=$v; break; } }
            }
        }
        if (!$latestVersions) {
            $gv = $pdo->query("SELECT * FROM versions WHERE is_active=1 ORDER BY created_at DESC LIMIT 1")->fetch();
            if ($gv) $latestVersions[0]=$gv;
        }
    } catch (Exception $e) {}
}
if ($latestVersions):
?>
<div class="panel-card" style="border-color:rgba(0,212,255,.25);background:linear-gradient(135deg,rgba(0,212,255,.08),rgba(0,123,255,.06))">
    <div class="panel-card__body" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
        <div style="display:flex;gap:12px;align-items:center">
            <div style="width:42px;height:42px;background:var(--gradient);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff"><i class="fas fa-rocket"></i></div>
            <div>
                <div style="font-weight:800;color:#fff">Update available!</div>
                <div style="font-size:.82rem;color:rgba(255,255,255,.6)">Latest version: <strong style="color:var(--cyan)">v<?= htmlspecialchars($latestVersions[array_key_first($latestVersions)]['version']) ?></strong> — <?= htmlspecialchars($latestVersions[array_key_first($latestVersions)]['description'] ?: 'New version released') ?></div>
            </div>
        </div>
        <a href="/dashboard/products" class="btn-panel btn-cyan"><i class="fas fa-download"></i> Download / View</a>
    </div>
</div>
<?php endif; ?>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card__icon"><i class="fas fa-receipt"></i></div>
        <div class="stat-card__val"><?= $orderCount ?></div>
        <div class="stat-card__label">Orders</div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon"><i class="fas fa-download"></i></div>
        <div class="stat-card__val"><?= $dlTotal ?></div>
        <div class="stat-card__label">Total Downloads</div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon"><i class="fas fa-bell"></i></div>
        <div class="stat-card__val"><?= $unreadCount ?></div>
        <div class="stat-card__label">Unread Notifications</div>
    </div>
</div>

<div class="panel-card">
    <div class="panel-card__head">
        <span class="panel-card__title"><i class="fas fa-receipt"></i> Recent Orders</span>
        <a href="/dashboard/orders" class="btn-panel btn-cyan">View All</a>
    </div>
    <div class="panel-card__body">
        <?php if ($recentOrders): ?>
        <table>
            <thead><tr><th>Order No</th><th>Product</th><th>Amount</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recentOrders as $o): $ocur = strtoupper($o['currency'] !== '' ? $o['currency'] : 'USD'); ?>
                <tr>
                    <td style="color:rgba(255,255,255,.45);font-size:.8rem">#<?= htmlspecialchars($o['order_no']) ?></td>
                    <td style="font-weight:600"><?= htmlspecialchars($o['product_name']) ?></td>
                    <td><?= number_format((float)$o['amount'], 2) ?> <?= htmlspecialchars($ocur) ?></td>
                    <td style="color:rgba(255,255,255,.45);font-size:.8rem"><?= date('d.m.Y', strtotime($o['created_at'])) ?></td>
                    <td><span class="badge badge-approved"><?= htmlspecialchars($o['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="empty"><i class="fas fa-receipt"></i>No orders yet.</div>
        <?php endif; ?>
    </div>
</div>

<div class="panel-card">
    <div class="panel-card__head">
        <span class="panel-card__title"><i class="fas fa-bell"></i> Recent Notifications</span>
        <a href="/dashboard/notifications" class="btn-panel btn-cyan">View All</a>
    </div>
    <div>
        <?php if ($recentNotifs): ?>
            <?php foreach ($recentNotifs as $n): ?>
            <div class="notif-row <?= (int)$n['is_read'] === 0 ? 'unread' : '' ?>">
                <div class="notif-ic <?= $n['type'] === 'approved' ? 'approved' : ($n['type'] === 'rejected' ? 'rejected' : '') ?>">
                    <i class="fas fa-<?= $n['type'] === 'approved' ? 'check' : ($n['type'] === 'rejected' ? 'xmark' : 'bell') ?>"></i>
                </div>
                <div class="notif-body">
                    <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
                    <div class="notif-msg"><?= htmlspecialchars($n['message']) ?></div>
                    <div class="notif-time"><?= date('d.m.Y H:i', strtotime($n['created_at'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty"><i class="fas fa-bell"></i>No notifications.</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/_inc/footer.php'; ?>

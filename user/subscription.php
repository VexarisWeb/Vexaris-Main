<?php
$activePage = 'subscription';
require_once __DIR__ . '/_inc/header.php';
require_once __DIR__ . '/../includes/plans.php';

$uid = (int)$user['id'];
$pdo = db();
$plan = user_effective_plan($uid);
$planSlug = $plan['plan'] ?? 'free';
$planDef = null;
foreach (($PLANS ?? []) as $p) {
    if (($p['slug'] ?? strtolower($p['name'] ?? '')) === $planSlug) { $planDef = $p; break; }
}
$planOrders = [];
$orderCount = 0;
if ($pdo) {
    try {
        $st = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? AND provider = 'whop' AND product_id = 0 ORDER BY created_at DESC LIMIT 10");
        $st->execute([$uid]);
        $planOrders = $st->fetchAll();
        $ocq = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $ocq->execute([$uid]);
        $orderCount = (int)$ocq->fetchColumn();
    } catch (Exception $e) {}
}
$licDomains = [];
if (!empty($plan['license']['allowed_domains'])) {
    $licDomains = json_decode((string)$plan['license']['allowed_domains'], true) ?: [];
}
$planColors = ['free' => '#9aa3ac', 'starter' => '#4f8fc0', 'professional' => '#a78bfa', 'enterprise' => '#f0b429'];
$pc = $planColors[$planSlug] ?? '#9aa3ac';
?>

<div class="main__header">
    <h1>My Subscription</h1>
    <p>Your plan, limits and billing history.</p>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1.2rem;margin-bottom:1.2rem">
    <div class="panel-card" style="margin:0;border-color:<?= $pc ?>55">
        <div class="panel-card__body">
            <div style="display:flex;align-items:center;gap:.8rem">
                <div style="width:46px;height:46px;flex:none;border-radius:14px;background:<?= $pc ?>1a;border:1px solid <?= $pc ?>55;display:flex;align-items:center;justify-content:center;color:<?= $pc ?>;font-size:1.2rem"><i class="fas fa-crown"></i></div>
                <div>
                    <div style="font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.08em">Current plan</div>
                    <div style="font-size:1.3rem;font-weight:800;color:#fff;font-family:var(--fd)"><?= htmlspecialchars(ucfirst($planSlug)) ?></div>
                </div>
                <span class="badge" style="margin-left:auto;background:<?= $pc ?>1a;color:<?= $pc ?>;border:1px solid <?= $pc ?>55"><?= !empty($plan['live']) ? 'Active' : ($planSlug === 'free' ? 'Free' : 'Expired') ?></span>
            </div>
            <?php if (!empty($plan['live'])): ?>
            <div style="font-size:.82rem;color:var(--muted);margin-top:.8rem"><i class="fas fa-rotate"></i> <?= $plan['cycle'] === 'yearly' ? 'Yearly' : 'Monthly' ?> billing · renews <?= date('d.m.Y', (int)$plan['until']) ?></div>
            <?php elseif ($planSlug !== 'free'): ?>
            <div style="font-size:.82rem;color:#f87171;margin-top:.8rem"><i class="fas fa-circle-exclamation"></i> Subscription ended — your site runs on Free limits.</div>
            <?php else: ?>
            <div style="font-size:.82rem;color:var(--muted);margin-top:.8rem"><i class="fas fa-circle-info"></i> Upgrade to unlock more products, revenue and features.</div>
            <?php endif; ?>
            <div style="display:flex;gap:.6rem;margin-top:1rem">
                <a href="/pricing" class="btn-panel btn-cyan" style="text-decoration:none"><i class="fas fa-arrow-up"></i> <?= $planSlug === 'free' ? 'Upgrade plan' : 'Change plan' ?></a>
                <a href="/dashboard/license" class="btn-panel" style="text-decoration:none"><i class="fas fa-key"></i> My License</a>
            </div>
        </div>
    </div>

    <div class="panel-card" style="margin:0">
        <div class="panel-card__head"><div class="panel-card__title"><i class="fas fa-gauge-high"></i> Plan Snapshot</div></div>
        <div class="panel-card__body">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.8rem;text-align:center">
                <div style="background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;padding:.8rem .4rem">
                    <div style="font-size:1.2rem;font-weight:800;color:#fff"><?= count($licDomains) ?></div>
                    <div style="font-size:.7rem;color:var(--muted)">Domains</div>
                </div>
                <div style="background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;padding:.8rem .4rem">
                    <div style="font-size:1.2rem;font-weight:800;color:#fff"><?= $orderCount ?></div>
                    <div style="font-size:.7rem;color:var(--muted)">Orders</div>
                </div>
                <div style="background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;padding:.8rem .4rem">
                    <div style="font-size:1.2rem;font-weight:800;color:#fff"><?= $plan['license'] ? 'Yes' : 'No' ?></div>
                    <div style="font-size:.7rem;color:var(--muted)">License</div>
                </div>
            </div>
            <?php if ($planDef && !empty($planDef['feats'])): ?>
            <div style="margin-top:1rem;font-size:.8rem;color:var(--muted);line-height:2">
                <?php foreach (array_slice($planDef['feats'], 0, 6) as $f): ?>
                <div style="display:flex;justify-content:space-between;border-bottom:1px dashed rgba(255,255,255,.07)">
                    <span><?= htmlspecialchars($f[0]) ?></span>
                    <strong style="color:#e9e7e2"><?= $f[1] === true ? '✓' : ($f[1] === false ? '—' : htmlspecialchars((string)$f[1])) ?></strong>
                </div>
                <?php endforeach; ?>
                <div style="margin-top:.5rem"><a href="/pricing" style="color:var(--cyan);font-size:.78rem">Compare all features →</a></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="panel-card">
    <div class="panel-card__head"><div class="panel-card__title"><i class="fas fa-receipt"></i> Billing History</div></div>
    <div class="panel-card__body">
        <?php if ($planOrders): ?>
        <table>
            <thead><tr><th>Plan</th><th>Amount</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($planOrders as $o): ?>
                <tr>
                    <td style="font-weight:600"><?= htmlspecialchars($o['product_name']) ?></td>
                    <td><?= number_format((float)$o['amount'], 2) ?> <?= htmlspecialchars(strtoupper($o['currency'] ?: 'USD')) ?></td>
                    <td style="color:rgba(255,255,255,.45);font-size:.8rem"><?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></td>
                    <td><?php if ($o['status'] === 'completed'): ?><span class="badge badge-approved"><i class="fas fa-check"></i> Completed</span><?php elseif ($o['status'] === 'pending'): ?><span class="badge badge-pending"><i class="fas fa-hourglass-half"></i> Pending</span><?php else: ?><span class="badge badge-rejected"><i class="fas fa-xmark"></i> <?= htmlspecialchars(ucfirst($o['status'])) ?></span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty"><i class="fas fa-receipt"></i>No subscription payments yet.</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/_inc/footer.php'; ?>

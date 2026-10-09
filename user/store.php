<?php
$activePage = 'store';
require_once __DIR__ . '/_inc/header.php';

$uid = (int)$user['id'];
$pdo = db();
$items = [];
$whopOn = false;

if ($pdo) {
    try {
        require_once __DIR__ . '/../core/whop.php';
        $whopOn = whop_payments_enabled();
        $cur = whop_currency();
        $st = $pdo->prepare("SELECT p.*, (SELECT COUNT(*) FROM user_products up WHERE up.user_id = ? AND up.product_id = p.id) AS owned FROM products p WHERE p.is_active = 1 AND p.price > 0 ORDER BY p.price ASC");
        $st->execute([$uid]);
        foreach ($st->fetchAll() as $r) {
            $r['show_currency'] = strtoupper($r['currency'] !== '' ? $r['currency'] : $cur);
            $items[] = $r;
        }
    } catch (Exception $e) {}
}

$paid = (string)($_GET['paid'] ?? '');
$err = (string)($_GET['err'] ?? '');
?>

<div class="main__header">
    <h1>Store</h1>
    <p>Buy products instantly with Whop secure checkout.</p>
</div>

<?php if ($paid === '1'): ?>
<div style="background:rgba(116,192,145,.08);border:1px solid rgba(116,192,145,.3);border-radius:12px;padding:.9rem 1.2rem;font-size:.86rem;color:#74c091;margin-bottom:1.2rem"><i class="fas fa-circle-check"></i> Payment received! Your product will appear in <a href="/dashboard/products" style="color:#74c091;font-weight:700">My Products</a> within seconds.</div>
<?php endif; ?>
<?php if ($err !== ''): ?>
<div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:12px;padding:.9rem 1.2rem;font-size:.86rem;color:#f87171;margin-bottom:1.2rem"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($err === 'disabled' ? 'Whop payments are currently disabled.' : 'Checkout could not be started. Please try again.') ?></div>
<?php endif; ?>

<?php if (!$whopOn): ?>
<div class="panel-card"><div class="panel-card__body"><div class="empty"><i class="fas fa-store-slash"></i>Store is closed right now. Please check back later.</div></div></div>
<?php elseif (!$items): ?>
<div class="panel-card"><div class="panel-card__body"><div class="empty"><i class="fas fa-box-open"></i>No products for sale yet.</div></div></div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.2rem">
<?php foreach ($items as $it): $owned = (int)$it['owned'] > 0; ?>
    <div class="panel-card" style="margin:0;display:flex;flex-direction:column">
        <div class="panel-card__body" style="flex:1;display:flex;flex-direction:column">
            <div style="display:flex;align-items:flex-start;gap:.7rem">
                <div style="width:42px;height:42px;flex:none;background:rgba(255,122,26,.1);border:1px solid rgba(255,122,26,.3);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#ff7a1a"><i class="fas fa-box-open"></i></div>
                <div style="min-width:0">
                    <div style="font-weight:800;color:#fff"><?= htmlspecialchars($it['name']) ?></div>
                    <div style="font-size:.72rem;color:var(--muted);margin-top:2px"><i class="fas fa-hdd"></i> <?= htmlspecialchars($it['file_size'] ?: $it['file_name']) ?></div>
                </div>
            </div>
            <?php if (trim((string)$it['description']) !== ''): ?>
            <div style="font-size:.82rem;color:var(--muted);line-height:1.6;margin-top:.7rem"><?= htmlspecialchars(mb_strimwidth((string)$it['description'], 0, 140, '…')) ?></div>
            <?php endif; ?>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:auto;padding-top:1rem">
                <div style="font-family:var(--fd);font-size:1.3rem;font-weight:800;color:#fff"><?= number_format((float)$it['price'], 2) ?> <span style="font-size:.75rem;color:var(--muted)"><?= htmlspecialchars($it['show_currency']) ?></span></div>
                <?php if ($owned): ?>
                <a href="/dashboard/products" class="btn-panel btn-green"><i class="fas fa-check"></i> Owned</a>
                <?php else: ?>
                <form method="POST" action="/api/whop-checkout" style="margin:0">
                    <input type="hidden" name="product_id" value="<?= (int)$it['id'] ?>">
                    <button type="submit" class="btn-panel btn-cyan"><i class="fas fa-bolt"></i> Buy with Whop</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<p style="font-size:.75rem;color:rgba(255,255,255,.35);margin-top:1rem"><i class="fas fa-shield-halved"></i> Payments are processed securely by Whop. Your product is delivered automatically after payment.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/_inc/footer.php'; ?>

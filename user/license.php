<?php
$activePage = 'license';
require_once __DIR__ . '/_inc/header.php';

$uid = (int)$user['id'];
$pdo = db();
$plan = user_effective_plan($uid);
$lic = $plan['license'];
$domains = [];
if ($lic && $lic['allowed_domains'] !== '') {
    $domains = json_decode((string)$lic['allowed_domains'], true) ?: [];
}
$ok = (string)($_GET['ok'] ?? '');
$err = (string)($_GET['err'] ?? '');
?>

<div class="main__header">
    <h1>My License</h1>
    <p>Your license key and connected store domains.</p>
</div>

<?php if ($ok !== ''): ?>
<div style="background:rgba(116,192,145,.08);border:1px solid rgba(116,192,145,.3);border-radius:12px;padding:.9rem 1.2rem;font-size:.86rem;color:#74c091;margin-bottom:1.2rem"><i class="fas fa-circle-check"></i> <?= $ok === 'added' ? 'Domain added.' : ($ok === 'claimed' ? 'License linked to your account.' : 'Domain removed.') ?></div>
<?php endif; ?>
<?php if ($err !== ''): ?>
<div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:12px;padding:.9rem 1.2rem;font-size:.86rem;color:#f87171;margin-bottom:1.2rem"><i class="fas fa-circle-exclamation"></i> <?= $err === 'invalid' ? 'Invalid format.' : ($err === 'limit' ? 'Maximum 10 domains allowed.' : ($err === 'exists' ? 'Domain already added.' : ($err === 'notfound' ? 'License key not found or already linked.' : 'Operation failed.'))) ?></div>
<?php endif; ?>

<?php if ($lic): ?>
<div class="panel-card" style="margin-bottom:1.2rem">
    <div class="panel-card__head"><div class="panel-card__title"><i class="fas fa-key"></i> License Key</div><span class="badge" style="background:rgba(79,143,192,.12);color:#7fb3d9;border:1px solid rgba(79,143,192,.35)"><?= htmlspecialchars(ucfirst($plan['plan'] ?? 'free')) ?> plan</span></div>
    <div class="panel-card__body">
        <div style="display:flex;gap:.7rem;align-items:center;flex-wrap:wrap">
            <code id="licKey" style="flex:1;min-width:220px;background:rgba(0,0,0,.35);border:1px solid var(--border);border-radius:10px;padding:.7rem 1rem;font-size:.85rem;color:#fff;word-break:break-all"><?= htmlspecialchars($lic['license_key']) ?></code>
            <button type="button" class="btn-panel btn-cyan" onclick="navigator.clipboard.writeText(document.getElementById('licKey').textContent).then(()=>{this.innerHTML='<i class=&quot;fas fa-check&quot;></i> Copied';setTimeout(()=>location.reload(),900);})"><i class="fas fa-copy"></i> Copy</button>
        </div>
        <p style="font-size:.78rem;color:var(--muted);margin-top:.8rem"><i class="fas fa-circle-info"></i> Paste this key into your store's setup to activate your plan limits automatically.</p>
    </div>
</div>

<div class="panel-card">
    <div class="panel-card__head"><div class="panel-card__title"><i class="fas fa-globe"></i> Store Domains (<?= count($domains) ?>/10)</div></div>
    <div class="panel-card__body">
        <?php if ($domains): ?>
        <table>
            <thead><tr><th>Domain</th><th style="text-align:right">Action</th></tr></thead>
            <tbody>
            <?php foreach ($domains as $d): ?>
                <tr>
                    <td style="font-weight:600"><i class="fas fa-globe" style="color:var(--cyan);margin-right:8px"></i><?= htmlspecialchars($d) ?></td>
                    <td style="text-align:right">
                        <form method="POST" action="/api/license-domains" style="margin:0;display:inline">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="domain" value="<?= htmlspecialchars($d) ?>">
                            <button type="submit" class="btn-panel" onclick="return confirm('Remove this domain?')"><i class="fas fa-trash"></i> Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty"><i class="fas fa-globe"></i>No domains connected yet.</div>
        <?php endif; ?>
        <?php if (count($domains) < 10): ?>
        <form method="POST" action="/api/license-domains" style="display:flex;gap:.6rem;margin-top:1rem;flex-wrap:wrap">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="add">
            <input name="domain" placeholder="ornek.com" required maxlength="253" style="flex:1;min-width:200px;background:rgba(0,0,0,.3);border:1px solid var(--border);border-radius:10px;color:#fff;padding:.65rem 1rem;font-size:.85rem;outline:none">
            <button type="submit" class="btn-panel btn-green"><i class="fas fa-plus"></i> Add Domain</button>
        </form>
        <p style="font-size:.75rem;color:rgba(255,255,255,.35);margin-top:.7rem"><i class="fas fa-circle-info"></i> Enter your store's domain without http:// — subdomains like <strong>shop.ornek.com</strong> work too.</p>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="panel-card"><div class="panel-card__body"><div class="empty"><i class="fas fa-key"></i>No license linked yet. Buy a plan or claim an existing key.</div>
<div style="text-align:center;margin-top:1rem"><a href="/pricing" class="btn-panel btn-cyan" style="text-decoration:none"><i class="fas fa-crown"></i> View Plans</a></div>
<form method="POST" action="/api/license-domains" style="display:flex;flex-wrap:wrap;gap:.6rem;margin-top:1rem;max-width:520px;margin-left:auto;margin-right:auto">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <input type="hidden" name="action" value="claim">
    <input name="license_key" placeholder="Paste license key to claim" required maxlength="64" style="flex:1;min-width:200px;background:rgba(0,0,0,.3);border:1px solid var(--border);border-radius:10px;color:#fff;padding:.65rem 1rem;font-size:.85rem;outline:none">
    <button type="submit" class="btn-panel btn-green"><i class="fas fa-link"></i> Claim</button>
</form></div></div>
<?php endif; ?>

<?php require_once __DIR__ . '/_inc/footer.php'; ?>

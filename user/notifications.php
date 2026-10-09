<?php
$activePage = 'notifications';
require_once __DIR__ . '/_inc/header.php';

$uid = (int)$user['id'];
$pdo = db();
$notifs = [];

if ($pdo) {
    try {
        $st = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
        $st->execute([$uid]);
        $notifs = $st->fetchAll();

        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$uid]);

        foreach ($notifs as &$n) $n['is_read'] = 1;
        unset($n);
    } catch (Exception $e) {}
}
?>

<div class="main__header">
    <h1>Bildirimler</h1>
    <p>Order, application and system notifications are shown here.</p>
</div>

<div class="panel-card">
    <div class="panel-card__head">
        <span class="panel-card__title"><i class="fas fa-bell"></i> All Notifications</span>
        <?php if ($notifs): ?>
        <button class="btn-panel btn-cyan" onclick="markAll()" id="markAllBtn"><i class="fas fa-check-double"></i> Mark All as Read</button>
        <?php endif; ?>
    </div>
    <div>
        <?php if ($notifs): foreach ($notifs as $n): ?>
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
        <?php endforeach; else: ?>
            <div class="empty"><i class="fas fa-bell"></i>Bildiriminiz bulunmuyor.</div>
        <?php endif; ?>
    </div>
</div>

<script>
const VX_CSRF = <?= json_encode(csrf_token()) ?>;
async function markAll() {
    try {
        const fd = new FormData();
        fd.append('action', 'mark_all');
        const res = await fetch('/api/notifications', { method:'POST', headers:{'X-CSRF-Token': VX_CSRF}, body: fd });
        const data = await res.json();
        if (data.ok) {
            document.querySelectorAll('.notif-row').forEach(r => r.classList.remove('unread'));
            const btn = document.getElementById('markAllBtn');
            if (btn) btn.remove();
        }
    } catch(e) {}
}
</script>

<?php require_once __DIR__ . '/_inc/footer.php'; ?>
<?php
$demoPdo = demo_db();
$demoPosts = '0';
if ($demoPdo) {
    try {
        $st = $demoPdo->query("SELECT `value` FROM settings WHERE `key`='demo_post_enabled' LIMIT 1");
        $v = $st->fetchColumn();
        if ($v !== false) $demoPosts = (string)$v;
    } catch (Exception $e) {}
}
?>
<!-- DEMO YÖNETİMİ -->
<div style="max-width:760px">
    <div class="table-card" style="padding:1.6rem">
        <div class="section-title" style="margin:0 0 .4rem"><i class="fas fa-rocket"></i> Demo POST İşlemleri</div>
        <p style="font-size:.83rem;color:var(--muted);margin-bottom:1.4rem">Demo alt alanında (<strong style="color:var(--cyan)">demo.vexaris.com.tr</strong>) kullanıcı ve admin hesaplarının POST işlemleri buradan kontrol edilir. Kapalıyken kimse veri değiştiremez (satın alma, kayıt, yorum, admin panel kayıtları dahil).</p>

        <?php if (!$demoPdo): ?>
            <div class="alert-msg" style="background:rgba(251,146,60,.1);border:1px solid rgba(251,146,60,.4);color:#fb923c;padding:12px;border-radius:10px;font-size:.85rem;margin-bottom:1rem">
                <i class="fas fa-triangle-exclamation"></i> Demo veritabanına bağlanılamadı. Sunucuda <code>vexarisc_demo</code> (`vexarisc_root`) veritabanının varlığını ve <code>core/db.php:demo_db()</code> ayarlarını kontrol edin.
            </div>
        <?php else: ?>
            <form method="POST" action="/api/demo" onsubmit="return submitDemoPost(event)">
                <input type="hidden" name="action" value="set_demo_post">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-field">
                    <label><i class="fas fa-toggle-on" style="color:#00d4ff;margin-right:6px"></i> Demo POST Durumu</label>
                    <select name="demo_post_enabled" style="width:100%;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:9px;padding:11px 14px;color:var(--text);font-size:.875rem;outline:none">
                        <option value="0" <?= $demoPosts === '0' ? 'selected' : '' ?>>Kapalı (Sadece Gezinme)</option>
                        <option value="1" <?= $demoPosts === '1' ? 'selected' : '' ?>>Açık (Tüm İşlemler)</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-save"><i class="fas fa-floppy-disk"></i> Kaydet</button>
                </div>
                <div class="alert-msg" id="demoPostAlert" style="margin-top:1rem"></div>
            </form>
        <?php endif; ?>
    </div>
</div>
<script>
function submitDemoPost(e) {
    e.preventDefault();
    const form = e.target;
    const fd = new FormData(form);
    const alertBox = document.getElementById('demoPostAlert');
    alertBox.style.display = 'block';
    alertBox.style.color = 'var(--muted)';
    alertBox.textContent = 'Kaydediliyor...';
    fetch(form.getAttribute('action') || '/api/demo', { method: 'POST', body: fd })
        .then(r => r.text().then(t => ({ ok: r.ok, t })))
        .then(({ ok, t }) => {
            let d;
            try { d = JSON.parse(t); } catch (e) { d = null; }
            if (d && d.ok) {
                alertBox.style.color = '#4ade80';
                alertBox.textContent = d.msg || 'Kaydedildi.';
            } else if (d) {
                alertBox.style.color = '#ff7070';
                alertBox.textContent = d.msg || 'Hata oluştu.';
            } else {
                alertBox.style.color = '#ff7070';
                alertBox.textContent = 'Sunucu hatası oluştu (HTTP ' + (ok ? '200' : 'hata') + ').';
            }
        })
        .catch(() => {
            alertBox.style.color = '#ff7070';
            alertBox.textContent = 'Bağlantı kurulamadı.';
        });
    return false;
}
</script>

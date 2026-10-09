<?php
require_once __DIR__ . '/../../core/legal.php';
require_once __DIR__ . '/../../core/editor.php';
$pages = legal_slugs();
$openSlug = $_GET['open'] ?? '';
?>
<!-- YASAL SAYFALAR -->
<div style="max-width:860px">
    <div class="table-card" style="padding:1.6rem">
        <div class="section-title" style="margin:0 0 .4rem"><i class="fas fa-file-contract"></i> Yasal Sayfalar</div>
        <p style="font-size:.83rem;color:var(--muted);margin-bottom:1.6rem">
            Site'de yayımlanan yasal sayfaların içeriklerini buradan düzenleyebilirsin. Değişiklikler kaydedildiği anda sitede geçerli olur.
        </p>

        <div id="legalList">
            <?php foreach ($pages as $slug): $pg = legal_get($slug); $isOpen = $slug === $openSlug; ?>
            <div class="tpl-item <?= $isOpen ? 'open' : '' ?>" data-slug="<?= $slug ?>" id="legal-<?= $slug ?>">
                <button type="button" class="tpl-head" onclick="toggleLegal('<?= $slug ?>')" aria-expanded="<?= $isOpen ? 'true' : 'false' ?>">
                    <i class="fas fa-chevron-right tpl-chev"></i>
                    <i class="fas fa-file-lines tpl-ic"></i>
                    <span class="tpl-name"><?= htmlspecialchars($pg['title']) ?></span>
                    <?php if ($pg['updated_at']): ?>
                    <span class="tpl-meta"><?= date('d.m.Y H:i', strtotime($pg['updated_at'])) ?></span>
                    <?php else: ?>
                    <span class="tpl-meta" style="color:var(--cyan)">Varsayılan</span>
                    <?php endif; ?>
                </button>
                <div class="tpl-body" <?= $isOpen ? 'style="display:block"' : '' ?>>
                    <div style="margin-bottom:.4rem">
                        <label style="font-size:.78rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:.4rem">İçerik</label>
                        <?php render_editor('legal-' . $slug, $pg['content'], '320px'); ?>
                    </div>
                    <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap">
                        <button type="button" class="btn-save" style="flex:1;min-width:140px" onclick="saveLegal('<?= $slug ?>', this)"><i class="fas fa-floppy-disk"></i> Kaydet</button>
                        <button type="button" class="btn-del-modal" style="flex:none;min-width:130px" onclick="resetLegal('<?= $slug ?>')"><i class="fas fa-rotate-left"></i> Varsayıla Dön</button>
                        <a href="/<?= $slug ?>" target="_blank" class="btn-ghost" style="flex:none;text-decoration:none"><i class="fas fa-eye"></i> Görüntüle</a>
                        <span class="legal-msg" id="legalMsg-<?= $slug ?>" style="font-size:.82rem;font-weight:600;display:none"></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.tpl-item{border:1px solid var(--border);border-radius:12px;margin-bottom:.6rem;overflow:hidden;transition:border-color .2s;background:rgba(255,255,255,.015)}
.tpl-item.open{border-color:var(--border-c)}
.tpl-head{display:flex;align-items:center;gap:10px;width:100%;background:none;border:none;padding:.8rem 1rem;cursor:pointer;color:var(--text);font-family:'Inter',sans-serif;font-size:.9rem;font-weight:600;text-align:left;transition:background .15s}
.tpl-head:hover{background:rgba(255,255,255,.03)}
.tpl-chev{color:var(--cyan);font-size:.75rem;transition:transform .25s;flex-shrink:0}
.tpl-item.open .tpl-chev{transform:rotate(90deg)}
.tpl-ic{color:var(--cyan);font-size:.9rem;flex-shrink:0}
.tpl-name{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.tpl-meta{font-size:.72rem;color:var(--muted);flex-shrink:0}
.tpl-body{display:none;padding:1rem 1.2rem 1.2rem;border-top:1px solid var(--border);background:rgba(255,255,255,.01)}
</style>

<script>
function toggleLegal(slug) {
    const item = document.getElementById('legal-' + slug);
    if (!item) return;
    const body = item.querySelector('.tpl-body');
    const head = item.querySelector('.tpl-head');
    const isOpen = body.style.display === 'block';
    body.style.display = isOpen ? 'none' : 'block';
    head.setAttribute('aria-expanded', String(!isOpen));
    item.classList.toggle('open', !isOpen);
}

function legalEditorHtml(edId) {
    const ed = document.getElementById('edBody-' + edId);
    return ed ? ed.innerHTML : '';
}

function showLegalMsg(slug, txt, ok) {
    const msg = document.getElementById('legalMsg-' + slug);
    if (!msg) return;
    msg.style.display = 'inline-block';
    msg.style.color = ok ? '#4ade80' : '#ff7070';
    msg.textContent = (ok ? '✓ ' : '✕ ') + txt;
    setTimeout(() => { msg.style.display = 'none'; }, 3500);
}

async function saveLegal(slug, btn) {
    const content = legalEditorHtml('legal-' + slug);
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';
    try {
        const fd = new FormData();
        fd.append('action', 'legal_save');
        fd.append('slug', slug);
        fd.append('content', content);
        const res  = await fetch('/api/legal', { method:'POST', headers:{'X-CSRF-Token': VX_CSRF}, body: fd });
        const data = await res.json();
        showLegalMsg(slug, data.msg || 'Hata.', !!data.ok);
        if (data.ok) {
            const meta = document.getElementById('legal-' + slug).querySelector('.tpl-meta');
            if (meta) { meta.textContent = new Date().toLocaleString('tr-TR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' }); meta.style.color = ''; }
        }
    } catch(err) {
        showLegalMsg(slug, 'Bağlantı hatası.', false);
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

async function resetLegal(slug) {
    if (!confirm('Bu sayfa varsayılan içeriğe mi dönsün? Yaptığın düzenlemeler silinir.')) return;
    try {
        const fd = new FormData();
        fd.append('action', 'legal_reset');
        fd.append('slug', slug);
        const res  = await fetch('/api/legal', { method:'POST', headers:{'X-CSRF-Token': VX_CSRF}, body: fd });
        const data = await res.json();
        if (data.ok) window.location.reload();
        else alert(data.msg || 'Sıfırlanamadı.');
    } catch(e) { alert('Bağlantı hatası.'); }
}
</script>

<?php
require_once __DIR__ . '/../../core/seo.php';
require_once __DIR__ . '/../../core/blog.php';
?>
<!-- SEO YÖNETİMİ -->
<div class="panel" style="margin-bottom:1.2rem">
    <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between">
        <div style="font-size:.88rem;color:var(--muted)">Sayfa bazlı SEO meta verileri — sistem sayfaları, blog listesi ve tüm yazılar</div>
    </div>
</div>

<div class="panel" style="padding:0">
    <div style="display:grid;grid-template-columns:320px 1fr;min-height:520px">
        <div style="border-right:1px solid var(--border);padding:1rem;overflow-y:auto;max-height:640px">
            <div class="form-field" style="margin-bottom:.8rem">
                <input id="seoSearch" placeholder="Sayfa veya yazı ara..."
                    style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.55rem .8rem;font-size:.85rem;outline:none;font-family:'Inter',sans-serif">
            </div>
            <div id="seoTargets">
                <div class="text-muted" style="font-size:.85rem;text-align:center;padding:2rem"><i class="fas fa-spinner fa-spin"></i> Yükleniyor...</div>
            </div>
        </div>
        <div style="padding:1.5rem" id="seoEditor">
            <div style="text-align:center;color:var(--muted);padding:4rem 1rem">
                <i class="fas fa-magnifying-glass-chart" style="font-size:2.2rem;color:rgba(0,212,255,.4);margin-bottom:1rem;display:inline-block"></i>
                <p style="font-size:.9rem">Sol listeden bir sayfa veya blog yazısı seçin.<br>SEO meta bilgilerini düzenleyip kaydedin.</p>
            </div>
        </div>
    </div>
</div>

<style>
    .seo-target-item { display:block;width:100%;text-align:left;background:transparent;border:1px solid transparent;border-radius:10px;padding:.6rem .75rem;margin-bottom:2px;cursor:pointer;color:var(--text);font-size:.85rem;font-family:inherit;transition:all .15s }
    .seo-target-item:hover { background:rgba(0,212,255,.08) }
    .seo-target-item.active { background:rgba(0,212,255,.14);border-color:rgba(0,212,255,.35) }
    .seo-target-item small { display:block;color:var(--muted);font-size:.72rem;margin-top:2px }
    .seo-group-title { font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin:1rem .5rem .4rem;font-weight:700 }
    .seo-field-label { display:flex;justify-content:space-between;align-items:center;gap:.5rem }
    .seo-count { font-size:.7rem;color:var(--muted);font-weight:400 }
    .serp-preview { background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:12px;padding:1.2rem 1.4rem;margin-bottom:1.4rem }
    .serp-preview .serp-url { color:#8ab4f8;font-size:.75rem;margin-bottom:.25rem;word-break:break-all }
    .serp-preview .serp-title { color:#8ab4f8;font-size:1.15rem;font-weight:600;line-height:1.3;margin-bottom:.3rem }
    .serp-preview .serp-desc { color:#bdc1c6;font-size:.82rem;line-height:1.5 }
    .seo-badge { display:inline-flex;align-items:center;gap:6px;font-size:.72rem;font-weight:700;padding:.25rem .6rem;border-radius:999px;margin-right:6px;margin-bottom:4px }
    .seo-badge-ok { background:rgba(52,211,153,.12);color:#34d399;border:1px solid rgba(52,211,153,.3) }
    .seo-badge-warn { background:rgba(251,191,36,.12);color:#fbbf24;border:1px solid rgba(251,191,36,.3) }
    .seo-badge-err { background:rgba(248,113,113,.12);color:#f87171;border:1px solid rgba(248,113,113,.3) }
</style>

<script>
let seoTargets = [];
let seoCurrent = '';

async function seoLoadTargets() {
    const box = document.getElementById('seoTargets');
    const fd = new FormData();
    fd.append('action', 'seo_targets');
    try {
        const res  = await fetch('/api/seo', { method:'POST', headers:{'X-CSRF-Token': VX_CSRF}, body: fd });
        const data = await res.json();
        if (!data.ok) { box.innerHTML = '<p class="text-muted" style="text-align:center;padding:2rem">' + (data.msg || 'Yüklenemedi.') + '</p>'; return; }
        seoTargets = data.targets;
        renderSeoTargets('');
    } catch(e) {
        box.innerHTML = '<p class="text-muted" style="text-align:center;padding:2rem">Bağlantı hatası.</p>';
    }
}

function renderSeoTargets(q) {
    const box = document.getElementById('seoTargets');
    q = q.trim().toLowerCase();
    let html = '';
    if (seoTargets.system.length) {
        html += '<div class="seo-group-title">Sistem Sayfaları</div>';
        html += seoTargets.system.filter(t => !q || t.label.toLowerCase().includes(q)).map(t =>
            '<button class="seo-target-item' + (seoCurrent === t.id ? ' active' : '') + '" onclick="seoOpen(\'' + t.id.replace(/'/g,"\\'") + '\')">' + t.label + '</button>'
        ).join('');
    }
    if (seoTargets.blog.length) {
        html += '<div class="seo-group-title">Blog Yazıları</div>';
        html += seoTargets.blog.filter(t => !q || t.label.toLowerCase().includes(q)).map(t =>
            '<button class="seo-target-item' + (seoCurrent === t.id ? ' active' : '') + '" onclick="seoOpen(\'' + t.id.replace(/'/g,"\\'") + '\')">' + t.label + '<small>' + t.id + '</small></button>'
        ).join('');
    }
    if (!html) html = '<p class="text-muted" style="text-align:center;padding:2rem;font-size:.85rem">Sonuç bulunamadı.</p>';
    box.innerHTML = html;
}

document.getElementById('seoSearch').addEventListener('input', function() {
    renderSeoTargets(this.value);
});

async function seoOpen(target) {
    seoCurrent = target;
    renderSeoTargets(document.getElementById('seoSearch').value);
    const ed = document.getElementById('seoEditor');
    ed.innerHTML = '<div style="text-align:center;color:var(--muted);padding:4rem 1rem"><i class="fas fa-spinner fa-spin"></i> Yükleniyor...</div>';
    const fd = new FormData();
    fd.append('action', 'seo_load');
    fd.append('target', target);
    try {
        const res  = await fetch('/api/seo', { method:'POST', headers:{'X-CSRF-Token': VX_CSRF}, body: fd });
        const data = await res.json();
        if (!data.ok) { ed.innerHTML = '<p class="text-muted" style="text-align:center;padding:2rem">' + (data.msg || 'Yüklenemedi.') + '</p>'; return; }
        renderSeoEditor(data.seo, data.fallback);
    } catch(e) {
        ed.innerHTML = '<p class="text-muted" style="text-align:center;padding:2rem">Bağlantı hatası.</p>';
    }
}

function renderSeoEditor(seo, fallback) {
    const ed = document.getElementById('seoEditor');
    const title = seo.title || fallback.title || '';
    const desc = seo.description || fallback.description || '';
    const noindex = seo.noindex == 1;
    const canonical = seo.canonical || '';
    const urlBase = location.protocol + '//' + location.host;
    const isBlogTarget = seoCurrent.indexOf('blog:') === 0;
    const pageUrl = canonical !== '' ? canonical : (urlBase + '/');

    ed.innerHTML = `
        <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between;margin-bottom:1.2rem">
            <h3 style="font-size:1.05rem;font-weight:800;color:#fff"><i class="fas fa-magnifying-glass-chart" style="color:var(--cyan);margin-right:8px"></i>SEO Editörü</h3>
            <div style="display:flex;gap:.5rem">
                <button class="btn-tbl btn-tbl-info" onclick="seoReset()"><i class="fas fa-rotate-left"></i> Varsayılana Döndür</button>
                <button class="btn btn-primary" style="padding:.6rem 1.4rem" onclick="seoSave()"><i class="fas fa-save"></i> Kaydet</button>
            </div>
        </div>
        <div id="seoAlert"></div>
        <div class="serp-preview">
            <div class="serp-url">${escapeHtml(pageUrl)}</div>
            <div class="serp-title" id="serpTitle">${escapeHtml(title)}</div>
            <div class="serp-desc" id="serpDesc">${escapeHtml(desc)}</div>
        </div>
        <div id="seoAudit" style="margin-bottom:1.2rem"></div>

        <div class="two-col" style="gap:1rem">
            <div class="form-field">
                <label class="seo-field-label">Meta Başlık <span class="seo-count" id="seoTitleCount"></span></label>
                <input id="seoTitle" value="${escapeAttr(title)}" oninput="seoPreviewUpdate()"
                    style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:'Inter',sans-serif">
            </div>
            <div class="form-field">
                <label class="seo-field-label">Meta Açıklama <span class="seo-count" id="seoDescCount"></span></label>
                <textarea id="seoDescription" rows="3" oninput="seoPreviewUpdate()" placeholder="Sayfa açıklaması (max 160 karakter önerilir)"
                    style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:'Inter',sans-serif;resize:vertical">${escapeHtml(desc)}</textarea>
            </div>
        </div>
        <div class="two-col" style="gap:1rem">
            <div class="form-field">
                <label class="seo-field-label">Anahtar Kelimeler <span class="seo-count" id="seoKeywordsCount"></span></label>
                <input id="seoKeywords" value="${escapeAttr(seo.keywords || '')}" placeholder="virgül, ile, ayrılmış"
                    style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:'Inter',sans-serif">
            </div>
            <div class="form-field">
                <label class="seo-field-label">Canonical URL <span class="seo-count" id="seoCanonicalCount"></span></label>
                <input id="seoCanonical" value="${escapeAttr(canonical)}" placeholder="https://... (boşsa otomatik)"
                    style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:'Inter',sans-serif">
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;margin:.6rem 0 1.2rem">
            <input type="checkbox" id="seoNoindex" ${noindex ? 'checked' : ''} style="accent-color:var(--cyan)">
            <label for="seoNoindex" style="font-size:.85rem;color:var(--text)">Arama motorlarında dizinleme yasak (noindex, nofollow)</label>
        </div>
        <div class="form-field" style="border-top:1px solid var(--border);padding-top:1rem;margin-bottom:.5rem">
            <label style="color:var(--cyan)"><i class="fab fa-facebook" style="margin-right:6px"></i>Open Graph (Sosyal Medya)</label>
        </div>
        <div class="two-col" style="gap:1rem">
            <div class="form-field">
                <label>OG Başlık</label>
                <input id="seoOgTitle" value="${escapeAttr(seo.og_title || '')}" placeholder="Boşsa meta başlık kullanılır"
                    style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:'Inter',sans-serif">
            </div>
            <div class="form-field">
                <label>OG Açıklama</label>
                <textarea id="seoOgDesc" rows="2" placeholder="Boşsa meta açıklama kullanılır"
                    style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:'Inter',sans-serif;resize:vertical">${escapeHtml(seo.og_description || '')}</textarea>
            </div>
        </div>
        <div class="form-field">
            <label>OG Görseli</label>
            <input id="seoOgImage" value="${escapeAttr(seo.og_image || '')}" placeholder="https://... görsel URL (boşsa kapak/logo)"
                style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:'Inter',sans-serif">
        </div>
    `;
    seoPreviewUpdate();
}

function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
}
function escapeAttr(s) {
    return escapeHtml(s).replace(/`/g, '&#96;');
}

function seoPreviewUpdate() {
    const title = document.getElementById('seoTitle').value;
    const desc = document.getElementById('seoDescription').value;
    document.getElementById('serpTitle').textContent = title || '(başlık yok)';
    document.getElementById('serpDesc').textContent = desc || '(açıklama yok)';
    document.getElementById('seoTitleCount').textContent = title.length + ' / 60';
    document.getElementById('seoDescCount').textContent = desc.length + ' / 160';
    document.getElementById('seoKeywordsCount').textContent = (document.getElementById('seoKeywords').value || '').length + ' karakter';
    document.getElementById('seoCanonicalCount').textContent = (document.getElementById('seoCanonical').value || '').length + ' karakter';
    const audit = document.getElementById('seoAudit');
    const badges = [];
    if (title.length === 0) badges.push('<span class="seo-badge seo-badge-err">Meta başlık yok</span>');
    else if (title.length > 60) badges.push('<span class="seo-badge seo-badge-warn">Meta başlık 60 karakterden uzun</span>');
    else badges.push('<span class="seo-badge seo-badge-ok">Meta başlık uygun</span>');
    if (desc.length === 0) badges.push('<span class="seo-badge seo-badge-err">Meta açıklama yok</span>');
    else if (desc.length > 160) badges.push('<span class="seo-badge seo-badge-warn">Meta açıklama 160 karakterden uzun</span>');
    else badges.push('<span class="seo-badge seo-badge-ok">Meta açıklama uygun</span>');
    audit.innerHTML = badges.join('');
}

async function seoSave() {
    const alertBox = document.getElementById('seoAlert');
    alertBox.innerHTML = '';
    const fd = new FormData();
    fd.append('action', 'seo_save');
    fd.append('target', seoCurrent);
    fd.append('title', document.getElementById('seoTitle').value);
    fd.append('description', document.getElementById('seoDescription').value);
    fd.append('keywords', document.getElementById('seoKeywords').value);
    fd.append('canonical', document.getElementById('seoCanonical').value);
    fd.append('noindex', document.getElementById('seoNoindex').checked ? '1' : '0');
    fd.append('og_title', document.getElementById('seoOgTitle').value);
    fd.append('og_description', document.getElementById('seoOgDesc').value);
    fd.append('og_image', document.getElementById('seoOgImage').value);
    try {
        const res  = await fetch('/api/seo', { method:'POST', headers:{'X-CSRF-Token': VX_CSRF}, body: fd });
        const data = await res.json();
        if (data.ok) {
            alertBox.innerHTML = '<div class="alert-msg alert-ok" style="margin-bottom:1rem"><i class="fas fa-check-circle"></i> ' + data.msg + '</div>';
            setTimeout(() => { alertBox.innerHTML = ''; }, 2500);
        } else {
            alertBox.innerHTML = '<div class="alert-msg alert-err" style="margin-bottom:1rem"><i class="fas fa-circle-exclamation"></i> ' + (data.msg || 'Hata oluştu.') + '</div>';
        }
    } catch(e) {
        alertBox.innerHTML = '<div class="alert-msg alert-err" style="margin-bottom:1rem"><i class="fas fa-circle-exclamation"></i> Bağlantı hatası.</div>';
    }
}

async function seoReset() {
    if (!confirm('Bu sayfanın SEO bilgileri varsayılana döndürülecek. Devam edilsin mi?')) return;
    const fd = new FormData();
    fd.append('action', 'seo_reset');
    fd.append('target', seoCurrent);
    try {
        const res  = await fetch('/api/seo', { method:'POST', headers:{'X-CSRF-Token': VX_CSRF}, body: fd });
        const data = await res.json();
        if (data.ok) {
            seoOpen(seoCurrent);
        } else {
            alert(data.msg || 'Sıfırlanamadı.');
        }
    } catch(e) { alert('Bağlantı hatası.'); }
}

seoLoadTargets();
</script>
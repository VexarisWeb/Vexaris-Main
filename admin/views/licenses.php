<?php
$featureKeys = ['core','theme_azure','theme_ember','tickets','mail_system','admin_panel','seo','announcements','coupons','custom_pages','references','reviews','discord_integration','roles_permissions','updates'];
$featureLabels = [
    'core' => 'Çekirdek Lisans',
    'theme_azure' => 'Azure Teması',
    'theme_ember' => 'Ember Teması',
    'tickets' => 'Destek Biletleri',
    'mail_system' => 'Mail Sistemi',
    'admin_panel' => 'Admin Paneli',
    'seo' => 'SEO Araçları',
    'announcements' => 'Duyurular',
    'coupons' => 'Kuponlar',
    'custom_pages' => 'Özel Sayfalar',
    'references' => 'Referanslar',
    'reviews' => 'Yorumlar',
    'discord_integration' => 'Discord Entegrasyonu',
    'roles_permissions' => 'Rol & Yetkiler',
    'updates' => 'Otomatik Güncellemeler',
];
?>
<!-- LISANSLAR -->
<div class="panel" style="margin-bottom:1.2rem">
    <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between">
        <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
            <div style="position:relative">
                <i class="fas fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.8rem"></i>
                <input id="licSearchInput" oninput="licSearchChanged()" placeholder="Müşteri, domain veya anahtar ara…"
                    style="background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.55rem .9rem .55rem 2.2rem;font-size:.88rem;width:280px;outline:none">
            </div>
            <span id="licCountLabel" class="text-muted" style="font-size:.85rem"></span>
        </div>
        <button class="btn btn-primary" onclick="openLicModal(null)"><i class="fas fa-plus"></i> Yeni Lisans Ekle</button>
    </div>
</div>
<div class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>Müşteri</th>
                    <th>Lisans Anahtarı</th>
                    <th>Domain</th>
                    <th style="width:100px">Durum</th>
                    <th>Oluşturulma</th>
                    <th style="width:280px;text-align:center">İşlemler</th>
                </tr>
            </thead>
            <tbody id="licTableBody">
                <tr><td colspan="7" style="text-align:center;padding:2.5rem" class="text-muted">Yükleniyor…</td></tr>
            </tbody>
        </table>
    </div>
    <div id="licPagination" style="display:flex;gap:6px;justify-content:flex-end;margin-top:1rem;flex-wrap:wrap"></div>
</div>

<!-- Lisans Ekle/Düzenle Modal -->
<div id="licModal" onclick="if(event.target===this)closeLicModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center">
    <div style="background:#0d1b3e;border:1px solid rgba(0,212,255,.25);border-radius:20px;padding:2rem;max-width:540px;width:92%;position:relative;max-height:88vh;overflow-y:auto">
        <button onclick="closeLicModal()" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.07);border:1px solid var(--border);border-radius:8px;color:var(--text);width:30px;height:30px;cursor:pointer;font-size:.95rem;display:flex;align-items:center;justify-content:center"><i class="fas fa-xmark"></i></button>
        <h3 style="font-size:1rem;font-weight:800;margin-bottom:1.4rem;color:#fff"><i class="fas fa-key" style="color:var(--cyan);margin-right:8px"></i><span id="licModalTitle">Yeni Lisans</span></h3>
        <input type="hidden" id="licId">
        <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:6px">Müşteri Adı <span style="color:#ff7070">*</span></label>
        <input id="licCustomer" placeholder="Örn: Acme Şirketi"
            style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;margin-bottom:1rem;font-family:'Inter',sans-serif">
        <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:6px">Domain(ler) <span style="color:#ff7070">*</span></label>
        <div id="licDomains"></div>
        <button type="button" class="lic-domain-add" onclick="addLicDomainRow('')"><i class="fas fa-plus"></i> Domain Ekle</button>
        <p class="feat-hint">Alt domain lisansları için <code>*.ornek.com</code> şeklinde girebilirsiniz.</p>
        <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:6px">Plan</label>
        <select id="licPlan" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;margin-bottom:1rem;font-family:'Inter',sans-serif">
            <option value="free">Free — 7 ürün / $100 ömürlük / AI yok</option>
            <option value="starter">Starter — 35 ürün / $1.000-ay / 12 soru-8sa</option>
            <option value="professional">Professional — 75 ürün / $5.000-ay / 30 soru-6sa</option>
            <option value="enterprise">Enterprise — sınırsız / 70 soru-4sa</option>
        </select>
        <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:6px">Özellikler</label>
        <div class="feat-grid">
            <?php foreach ($featureKeys as $fk): ?>
            <label class="feat-label"><input type="checkbox" class="feature-chk" value="<?= $fk ?>" checked><span><?= $featureLabels[$fk] ?></span></label>
            <?php endforeach; ?>
        </div>
        <button id="licSaveBtn" onclick="saveLic()" style="width:100%;padding:12px;background:linear-gradient(135deg,#00d4ff,#0a7dff);border:none;border-radius:12px;color:#041a35;font-size:.95rem;font-weight:800;cursor:pointer;font-family:inherit">Kaydet</button>
    </div>
</div>

<!-- Özellikler Modal -->
<div id="featuresModal" onclick="if(event.target===this)closeFeaturesModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center">
    <div style="background:#0d1b3e;border:1px solid rgba(0,212,255,.25);border-radius:20px;padding:2rem;max-width:430px;width:92%;position:relative">
        <button onclick="closeFeaturesModal()" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.07);border:1px solid var(--border);border-radius:8px;color:var(--text);width:30px;height:30px;cursor:pointer;font-size:.95rem;display:flex;align-items:center;justify-content:center"><i class="fas fa-xmark"></i></button>
        <h3 style="font-size:1rem;font-weight:800;margin-bottom:1.4rem;color:#fff"><i class="fas fa-sliders" style="color:var(--cyan);margin-right:8px"></i>Özellikler</h3>
        <div class="feat-grid" id="featuresModalCheckbox">
            <?php foreach ($featureKeys as $fk): ?>
            <label class="feat-label"><input type="checkbox" class="feature-chk" value="<?= $fk ?>"><span><?= $featureLabels[$fk] ?></span></label>
            <?php endforeach; ?>
        </div>
        <button onclick="saveFeatures()" style="width:100%;padding:12px;background:linear-gradient(135deg,#00d4ff,#0a7dff);border:none;border-radius:12px;color:#041a35;font-size:.95rem;font-weight:800;cursor:pointer;font-family:inherit">Kaydet</button>
    </div>
</div>

<!-- Askıya Al Modal -->
<div id="suspendModal" onclick="if(event.target===this)closeSuspendModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center">
    <div style="background:#0d1b3e;border:1px solid rgba(0,212,255,.25);border-radius:20px;padding:2rem;max-width:420px;width:92%;position:relative">
        <button onclick="closeSuspendModal()" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.07);border:1px solid var(--border);border-radius:8px;color:var(--text);width:30px;height:30px;cursor:pointer;font-size:.95rem;display:flex;align-items:center;justify-content:center"><i class="fas fa-xmark"></i></button>
        <h3 style="font-size:1rem;font-weight:800;margin-bottom:1.4rem;color:#fff"><i class="fas fa-pause" style="color:#fb923c;margin-right:8px"></i>Lisansı Askıya Al</h3>
        <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:6px">Tarih-Saat</label>
        <input id="suspendUntil" type="datetime-local"
            style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:'Inter',sans-serif">
        <div style="display:flex;gap:8px;margin:14px 0 1.4rem">
            <button onclick="setSuspendDays(7)" class="lic-domain-add">7 gün</button>
            <button onclick="setSuspendDays(14)" class="lic-domain-add">14 gün</button>
            <button onclick="setSuspendDays(30)" class="lic-domain-add">30 gün</button>
            <button onclick="setSuspendDays(365)" class="lic-domain-add">1 yıl</button>
        </div>
        <button onclick="saveSuspend()" style="width:100%;padding:12px;background:rgba(251,146,60,.15);border:1px solid rgba(251,146,60,.4);border-radius:12px;color:#fb923c;font-size:.95rem;font-weight:800;cursor:pointer;font-family:inherit">Askıya Al</button>
    </div>
</div>

<script>
const LIC_CSRF = VX_CSRF;
let licPage = 1, licSearch = '', licTimer = null;
let currentLic = null, suspendLicId = null;

function licStatusBadge(l) {
    const now = Date.now();
    if (l.status === 'active') return '<span class="badge badge-active">Aktif</span>';
    if (l.status === 'revoked') return '<span class="badge badge-rev">İptal</span>';
    if (l.suspended_until && new Date(l.suspended_until).getTime() > now) {
        return '<span class="badge badge-susp">Askıda</span>';
    }
    return '<span class="badge badge-active">Aktif</span>';
}

function licDomains(l) {
    try { return JSON.parse(l.allowed_domains || '[]'); } catch(e) { return []; }
}

async function loadLicenses(page) {
    licPage = page;
    const tbody = document.getElementById('licTableBody');
    const pagDiv = document.getElementById('licPagination');
    const label = document.getElementById('licCountLabel');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2.5rem" class="text-muted">Yükleniyor…</td></tr>';
    try {
        const qs = new URLSearchParams({ action:'list', p:page, q:licSearch });
        const res = await fetch('/api/licenses?' + qs.toString());
        const d = await res.json();
        if (!d.ok) { tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;color:#ff7070">' + esc(d.msg) + '</td></tr>'; return; }
        if (label) label.innerHTML = 'Toplam <strong style="color:var(--cyan)">' + d.total + '</strong> lisans';
        if (!d.licenses.length) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2.5rem;color:var(--muted)">Lisans bulunamadı.</td></tr>';
        } else {
            tbody.innerHTML = d.licenses.map(l => {
                const domains = licDomains(l);
                const dStr = domains.slice(0,2).map(esc).join(', ') + (domains.length > 2 ? ', …' : '');
                const suspendedActive = l.status === 'suspended' && l.suspended_until && new Date(l.suspended_until).getTime() > Date.now();
                return `<tr id="lrow-${l.id}">
                    <td class="text-muted" style="font-size:.8rem">${esc(l.id)}</td>
                    <td style="font-weight:600">${esc(l.customer_name)}</td>
                    <td><code title="${esc(l.license_key)}" style="font-size:.78rem;color:var(--cyan);cursor:default">${esc(l.license_key.slice(0,10))}…</code></td>
                    <td class="text-muted" style="font-size:.8rem">${domains.length ? domains.length + ' <span style="opacity:.65">(' + dStr + ')</span>' : 'Şu an yok'}</td>
                    <td>${licStatusBadge(l)}</td>
                    <td class="text-muted" style="font-size:.8rem;white-space:nowrap">${new Date(l.created_at).toLocaleDateString('tr-TR',{day:'2-digit',month:'2-digit',year:'numeric'})}</td>
                    <td style="text-align:center;white-space:nowrap">
                        <button class="btn-tbl btn-tbl-info" onclick="openFeaturesModal(${esc(JSON.stringify(l))})"><i class="fas fa-sliders"></i> Özellikler</button>
                        <button class="btn-tbl btn-tbl-info" onclick="openLicModal(${esc(JSON.stringify(l))})"><i class="fas fa-pen"></i> Düzenle</button>
                        ${suspendedActive
                            ? `<button class="btn-tbl btn-tbl-info" onclick="resumeLic(${l.id})"><i class="fas fa-play"></i> Aç</button>`
                            : `<button class="btn-tbl" style="background:rgba(251,146,60,.08);border-color:rgba(251,146,60,.25);color:#fb923c" onclick="openSuspendModal(${l.id})"><i class="fas fa-pause"></i> Askıya Al</button>`}
                        <button class="btn-tbl btn-tbl-del" onclick="deleteLic(${l.id})"><i class="fas fa-trash"></i> Sil</button>
                    </td>
                </tr>`;
            }).join('');
        }
        if (pagDiv) {
            let html = '';
            for (let p = 1; p <= d.totalPages; p++) {
                html += `<button class="page-btn ${p===d.page?'active':''}" onclick="loadLicenses(${p})">${p}</button>`;
            }
            pagDiv.innerHTML = html;
        }
    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;color:#ff7070">Bağlantı hatası.</td></tr>';
    }
}

function licSearchChanged() {
    clearTimeout(licTimer);
    licTimer = setTimeout(() => { licSearch = document.getElementById('licSearchInput').value.trim(); loadLicenses(1); }, 350);
}

function addLicDomainRow(val) {
    const wrap = document.getElementById('licDomains');
    const row = document.createElement('div');
    row.className = 'lic-domain-row';
    row.innerHTML = '<input type="text" class="lic-domain-input" placeholder="ornek.com veya *.ornek.com" value="' + (val ? esc(val) : '') + '">' +
        '<button type="button" onclick="this.parentElement.remove()" class="lic-domain-del"><i class="fas fa-xmark"></i></button>';
    wrap.appendChild(row);
}

function setLicFeatureChecks(container, features) {
    container.querySelectorAll('.feature-chk').forEach(c => {
        c.checked = features[c.value] === true || features[c.value] === 1;
    });
}

function openLicModal(l) {
    currentLic = l;
    document.getElementById('licId').value = l ? l.id : '';
    document.getElementById('licModalTitle').textContent = l ? 'Lisansı Düzenle' : 'Yeni Lisans';
    document.getElementById('licCustomer').value = l ? l.customer_name : '';
    const wrap = document.getElementById('licDomains');
    wrap.innerHTML = '';
    if (l) {
        licDomains(l).forEach(d => addLicDomainRow(d));
        setLicFeatureChecks(document.getElementById('licModal'), l.features || {});
        document.getElementById('licPlan').value = (l.features && l.features.plan) || 'free';
    } else {
        addLicDomainRow('');
        document.getElementById('licModal').querySelectorAll('.feature-chk').forEach(c => c.checked = true);
        document.getElementById('licPlan').value = 'free';
    }
    if (!wrap.children.length) addLicDomainRow('');
    document.getElementById('licModal').style.display = 'flex';
    setTimeout(() => { const i = document.getElementById('licCustomer'); if (i) i.focus(); }, 60);
}

function closeLicModal() {
    document.getElementById('licModal').style.display = 'none';
    currentLic = null;
}

function collectLicForm() {
    const customer = document.getElementById('licCustomer').value.trim();
    const domains = [...document.querySelectorAll('#licDomains .lic-domain-input')].map(i => i.value.trim()).filter(Boolean);
    const features = [...document.querySelectorAll('#licModal .feature-chk:checked')].map(c => c.value);
    const plan = document.getElementById('licPlan').value || 'free';
    return { customer, domains, features, plan };
}

async function saveLic() {
    const { customer, domains, features, plan } = collectLicForm();
    if (!customer) { alert('Müşteri adı zorunludur.'); return; }
    if (!domains.length) { alert('En az bir domain ekleyin.'); return; }
    const id = document.getElementById('licId').value;
    const fd = new FormData();
    fd.append('action', id ? 'update' : 'create');
    if (id) fd.append('id', id);
    fd.append('plan', plan);
    fd.append('customer_name', customer);
    domains.forEach(d => fd.append('domains[]', d));
    features.forEach(f => fd.append('features[]', f));
    try {
        const res = await fetch('/api/licenses', { method:'POST', headers:{'X-CSRF-Token': LIC_CSRF}, body:fd });
        const d = await res.json();
        if (d.ok) {
            closeLicModal();
            if (d.license_key) alert('Lisans oluşturuldu. Anahtar:\n\n' + d.license_key + '\n\nBu anahtarı müşteriyle paylaşın.');
            loadLicenses(licPage);
        } else {
            alert(d.msg);
        }
    } catch(e) { alert('Bağlantı hatası.'); }
}

function openFeaturesModal(l) {
    currentLic = l;
    setLicFeatureChecks(document.getElementById('featuresModal'), l.features || {});
    document.getElementById('featuresModal').style.display = 'flex';
}

function closeFeaturesModal() {
    document.getElementById('featuresModal').style.display = 'none';
    currentLic = null;
}

async function saveFeatures() {
    if (!currentLic) return;
    const features = [...document.querySelectorAll('#featuresModal .feature-chk:checked')].map(c => c.value);
    const fd = new FormData();
    fd.append('action', 'features');
    fd.append('id', currentLic.id);
    features.forEach(f => fd.append('features[]', f));
    try {
        const res = await fetch('/api/licenses', { method:'POST', headers:{'X-CSRF-Token': LIC_CSRF}, body:fd });
        const d = await res.json();
        alert(d.msg);
        if (d.ok) { closeFeaturesModal(); loadLicenses(licPage); }
    } catch(e) { alert('Bağlantı hatası.'); }
}

function openSuspendModal(id) {
    suspendLicId = id;
    document.getElementById('suspendUntil').value = '';
    document.getElementById('suspendModal').style.display = 'flex';
}

function closeSuspendModal() {
    document.getElementById('suspendModal').style.display = 'none';
    suspendLicId = null;
}

function setSuspendDays(n) {
    const d = new Date(Date.now() + n * 86400000);
    const pad = x => String(x).padStart(2, '0');
    document.getElementById('suspendUntil').value =
        d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
}

async function saveSuspend() {
    if (!suspendLicId) return;
    const until = document.getElementById('suspendUntil').value;
    if (!until) { alert('Bir tarih seçin veya hızlı günlere basın.'); return; }
    const fd = new FormData();
    fd.append('action', 'suspend');
    fd.append('id', suspendLicId);
    fd.append('until', until);
    try {
        const res = await fetch('/api/licenses', { method:'POST', headers:{'X-CSRF-Token': LIC_CSRF}, body:fd });
        const d = await res.json();
        alert(d.msg);
        if (d.ok) { closeSuspendModal(); loadLicenses(licPage); }
    } catch(e) { alert('Bağlantı hatası.'); }
}

async function resumeLic(id) {
    if (!confirm('Lisansı hemen açmak istediğinden emin misin?')) return;
    const fd = new FormData();
    fd.append('action', 'resume');
    fd.append('id', id);
    try {
        const res = await fetch('/api/licenses', { method:'POST', headers:{'X-CSRF-Token': LIC_CSRF}, body:fd });
        const d = await res.json();
        alert(d.msg);
        if (d.ok) loadLicenses(licPage);
    } catch(e) { alert('Bağlantı hatası.'); }
}

async function deleteLic(id) {
    if (!confirm('Bu lisansı silmek istediğinden emin misin? Bu işlem geri alınamaz.')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    try {
        const res = await fetch('/api/licenses', { method:'POST', headers:{'X-CSRF-Token': LIC_CSRF}, body:fd });
        const d = await res.json();
        if (d.ok) {
            const row = document.getElementById('lrow-' + id);
            if (row) { row.style.transition='opacity .3s'; row.style.opacity='0'; setTimeout(()=>row.remove(),300); }
        } else {
            alert(d.msg);
        }
    } catch(e) { alert('Bağlantı hatası.'); }
}

if (document.getElementById('licTableBody')) {
    loadLicenses(1);
}
</script>

<!-- ROL & YETKİLER -->
<div class="panel" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
    <div>
        <div style="font-weight:700;font-size:.95rem"><i class="fas fa-user-shield" style="color:var(--cyan);margin-right:8px"></i> Rol İzinleri</div>
        <div class="text-muted" style="font-size:.82rem;margin-top:.3rem">Her rol için erişebileceği panel bölümlerini seçin. Admin rolü tüm erişime sahiptir ve değiştirilemez.</div>
    </div>
    <span class="badge badge-admin" id="permAdminBadge"><i class="fas fa-shield-halved"></i> Admin: Tam Erişim</span>
</div>

<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap" id="permTabs"></div>

<div id="permPanels"></div>

<script>
const PERM_ROLES = ['admin','yonetici','moderator','destek_ekibi','user'];
const PERM_ICONS = { admin:'fa-crown', yonetici:'fa-user-tie', moderator:'fa-shield-halved', destek_ekibi:'fa-headset', user:'fa-user' };
const PERM_RESOURCES = <?= json_encode([
    'dashboard'   => 'Dashboard',
    'users'       => 'Kullanıcılar',
    'tickets'     => 'Destek Talepleri',
    'reports'     => 'Raporlar',
    'products'    => 'Ürünler',
    'blog'        => 'Blog',
    'seo'         => 'SEO Yönetimi',
    'licenses'    => 'Lisanslar',
    'versions'    => 'Versiyonlar',
    'demo'        => 'Demo Yönetimi',
    'settings'    => 'Ayarlar',
    'legal'       => 'Yasal Sayfalar',
    'permissions' => 'Rol & Yetkiler',
]) ?>;
const ROLE_LABELS = <?= json_encode(['admin'=>'Admin','yonetici'=>'Yönetici','moderator'=>'Moderatör','destek_ekibi'=>'Destek Ekibi','user'=>'Üye']) ?>;

let permData = {};

async function loadPerms() {
    try {
        const res = await fetch('/api/permissions?action=list');
        const d = await res.json();
        if (!d.ok) return;
        permData = d.roles;
        renderPermTabs();
        renderPermPanel('yonetici');
    } catch(e) {}
}

function renderPermTabs() {
    const tabs = document.getElementById('permTabs');
    tabs.innerHTML = PERM_ROLES.map(r =>
        '<button class="btn perm-tab' + (r === 'yonetici' ? ' active' : '') + '" data-role="' + r + '" onclick="switchPermTab(\'' + r + '\')">' +
        '<i class="fas ' + PERM_ICONS[r] + '"></i> ' + (ROLE_LABELS[r] || r) + '</button>'
    ).join('');
}

function switchPermTab(role) {
    document.querySelectorAll('#permTabs .btn').forEach(b => b.classList.remove('active'));
    document.querySelector('#permTabs .btn[data-role="' + role + '"]').classList.add('active');
    renderPermPanel(role);
}

function renderPermPanel(role) {
    const panels = document.getElementById('permPanels');
    if (role === 'admin') {
        panels.innerHTML = '<div class="panel" style="text-align:center;padding:1.6rem;border-style:dashed">' +
            '<div style="font-size:1.6rem;color:var(--cyan);margin-bottom:.5rem"><i class="fas fa-lock"></i></div>' +
            '<div style="font-weight:700">Admin rolü tüm kaynaklara erişim sağlar.</div>' +
            '<div class="text-muted" style="font-size:.83rem;margin-top:.3rem">Bu rolün izinleri değiştirilemez.</div></div>';
        return;
    }
    const resourceKeys = Object.keys(PERM_RESOURCES);
    panels.innerHTML =
        '<div class="panel">' +
            '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:8px">' +
            resourceKeys.map(res =>
                '<label style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:10px;cursor:pointer;transition:border-color .15s;font-size:.83rem;font-weight:600;user-select:none">' +
                '<input type="checkbox" class="perm-check" value="' + res + '" data-role="' + role + '"' +
                ((permData[role] || []).includes(res) ? ' checked' : '') + ' style="width:16px;height:16px;accent-color:#00d4ff">' +
                '<span>' + PERM_RESOURCES[res] + '</span></label>'
            ).join('') +
            '</div>' +
            '<div style="margin-top:1.2rem;display:flex;gap:.6rem;flex-wrap:wrap">' +
            '<button class="btn-primary" onclick="savePerms(\'' + role + '\')"><i class="fas fa-floppy-disk"></i> İzinleri Kaydet</button>' +
            '<button class="btn" onclick="renderPermPanel(\'' + role + '\')" style="padding:.55rem 1rem"><i class="fas fa-rotate-right"></i> Yenile</button>' +
            '</div>' +
        '</div>';
}

async function savePerms(role) {
    const checks = Array.from(document.querySelectorAll('.perm-check[data-role="' + role + '"]:checked')).map(c => c.value);
    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('role', role);
    fd.append('_csrf', VX_CSRF);
    checks.forEach(v => fd.append('resources[]', v));
    try {
        const res = await fetch('/api/permissions', { method:'POST', body:fd });
        const d = await res.json();
        if (d.ok) {
            await loadPerms();
            switchPermTab(role);
        }
        alert(d.msg || (d.ok ? 'Kaydedildi.' : 'Hata.'));
    } catch(e) { alert('Bağlantı hatası.'); }
}

if (document.getElementById('permTabs')) loadPerms();
</script>
<style>
.perm-tab{padding:8px 16px;font-size:.82rem;font-weight:700;cursor:pointer;border:1px solid var(--border);background:var(--card);color:var(--muted);border-radius:9px;display:inline-flex;align-items:center;gap:7px;transition:all .15s;font-family:'Inter',sans-serif}
.perm-tab:hover{color:var(--text);border-color:var(--border-c);background:rgba(0,212,255,.08)}
.perm-tab.active{background:var(--gradient);color:#fff;border-color:transparent;box-shadow:0 2px 12px rgba(0,212,255,.25)}
.perm-tab.active i{color:#fff}
</style>

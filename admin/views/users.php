<!-- KULLANICILAR -->
<style>
.urow{cursor:pointer}
.urow:active td{background:rgba(0,212,255,.07)}
@media(max-width:768px){.urow td:first-child{border-left:3px solid transparent}.urow:active td:first-child{border-left-color:var(--cyan)}}
</style>
<div class="search-bar">
    <span style="font-size:.88rem;color:var(--muted)" id="userCountLabel">Yükleniyor...</span>
    <div style="margin-left:auto;display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
        <div class="search-wrap">
            <i class="fas fa-magnifying-glass"></i>
            <input type="text" id="userSearch" placeholder="Kullanıcı adı veya e-posta..." oninput="debounceSearch()">
        </div>
        <button class="btn-primary" onclick="loadUsers(1)"><i class="fas fa-rotate-right"></i> Yenile</button>
    </div>
</div>

<div class="table-card"><div class="table-wrap">
    <table>
        <thead><tr>
            <th>#</th>
            <th>Kullanıcı Adı</th>
            <th>E-posta</th>
            <th>Rol</th>
            <th>Bakiye</th>
            <th>Risk</th>
            <th>Kayıt Tarihi</th>
            <th style="text-align:center">İşlemler</th>
        </tr></thead>
        <tbody id="usersTableBody">
            <tr><td colspan="8" style="text-align:center;padding:2.5rem;color:var(--muted)">Yükleniyor...</td></tr>
        </tbody>
    </table>
</div></div>
<div class="pagination" id="usersPagination"></div>

<!-- Kullanıcı Düzenleme Modal -->
<div class="modal-overlay" id="userModal" onclick="if(event.target===this)closeUserModal()">
    <div class="modal-box">
        <button class="modal-close" onclick="closeUserModal()"><i class="fas fa-xmark"></i></button>
        <div class="modal-title"><i class="fas fa-user-pen" style="color:var(--cyan);margin-right:8px"></i>Kullanıcıyı Düzenle</div>
        <div class="alert-msg" id="userModalAlert"></div>

        <div class="detail-row">
            <div class="detail-label">E-posta</div>
            <div class="detail-value" id="modalEmail">—</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Kayıt Tarihi</div>
            <div class="detail-value" id="modalDate">—</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Mevcut Rol</div>
            <div class="detail-value" id="modalRole">—</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Bakiye</div>
            <div class="detail-value" id="modalBalance">₺0,00</div>
        </div>

        <div class="detail-row">
            <div class="detail-label">Bağlantılı Hesaplar (multi-hesap)</div>
            <div class="detail-value" id="linkedBox">—</div>
        </div>

        <div class="form-field" style="margin-top:1rem">
            <label>Kullanıcı Adını Değiştir</label>
            <input type="text" id="modalUsername" placeholder="Yeni kullanıcı adı">
        </div>

        <div class="form-field">
            <label>Rol Ata</label>
            <select id="modalRoleSelect" onchange="setRole()">
                <option value="user">Üye</option>
                <option value="destek_ekibi">Destek Ekibi</option>
                <option value="moderator">Moderatör</option>
                <option value="yonetici">Yönetici</option>
                <option value="admin">Admin</option>
            </select>
        </div>

        <div class="modal-actions">
            <button class="btn-save" onclick="saveRename()"><i class="fas fa-floppy-disk"></i> Kaydet</button>
            <button id="creditOpenBtn" class="btn-save" style="background:rgba(251,146,60,.15);border:1px solid rgba(251,146,60,.35);color:#fb923c" onclick="openCreditModal()">
                <i class="fas fa-coins"></i> Kredi Gönder
            </button>
        </div>
        <button class="btn-del-modal" style="width:100%;margin-top:.6rem" onclick="deleteUser()">
            <i class="fas fa-trash"></i> Bu Kullanıcıyı Sil
        </button>
        <button class="btn-del-modal" id="banBtn" style="width:100%;margin-top:.6rem;display:none" onclick="banUser()">
            <i class="fas fa-gavel"></i> Bu Kullanıcıyı Banla
        </button>
        <button class="btn-save" id="unbanBtn" style="width:100%;margin-top:.6rem;display:none;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.35);color:#4ade80" onclick="unbanUser()">
            <i class="fas fa-gavel"></i> Banı Kaldır (itiraz kabul)
        </button>
    </div>
</div>

<!-- Kredi Gönderme Modal -->
<div class="modal-overlay" id="creditModal" onclick="if(event.target===this)closeCreditModal()">
    <div class="modal-box">
        <button class="modal-close" onclick="closeCreditModal()"><i class="fas fa-xmark"></i></button>
        <div class="modal-title"><i class="fas fa-coins" style="color:#fb923c;margin-right:8px"></i>Kredi Gönder</div>
        <div class="alert-msg" id="creditModalAlert"></div>

        <div class="detail-row">
            <div class="detail-label">Kullanıcı</div>
            <div class="detail-value" id="creditUserName">—</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Mevcut Bakiye</div>
            <div class="detail-value" id="creditUserBalance">₺0,00</div>
        </div>

        <div class="form-field" style="margin-top:.5rem">
            <label>Tutar (₺)</label>
            <input type="number" id="creditAmount" min="1" step="0.01" placeholder="Örn: 500" style="margin-bottom:.6rem">
        </div>
        <div class="form-field">
            <label>Açıklama (isteğe bağlı)</label>
            <textarea id="creditNote" rows="2" placeholder="Kullanıcıya görünecek açıklama" style="min-height:70px;resize:vertical"></textarea>
        </div>

        <div class="modal-actions">
            <button class="btn-save" style="background:linear-gradient(135deg,#fb923c,#f59e0b);border:none;color:#fff" onclick="sendCredit()">
                <i class="fas fa-paper-plane"></i> Gönder
            </button>
            <button class="btn-del-modal" onclick="closeCreditModal()" style="flex:1">
                <i class="fas fa-xmark"></i> Vazgeç
            </button>
        </div>
    </div>
</div>

<script>
let currentUser   = null;
let usersData     = [];
let usersPage     = 1;
let usersSearch   = '';
let searchTimer   = null;
const SELF_ID     = <?= (int)(current_user()['id'] ?? 0) ?>;
const MY_ROLE     = <?= json_encode(user_role()) ?>;
const CAN_SET_ROLE = <?= user_role() === 'admin' ? 'true' : 'false' ?>;

const ROLE_LABELS = { user:'Üye', destek_ekibi:'Destek Ekibi', moderator:'Moderatör', yonetici:'Yönetici', admin:'Admin' };

function roleBadge(role) {
    const r = ROLE_LABELS[role] || 'Üye';
    if (role === 'admin')  return '<span class="badge badge-admin"><i class="fas fa-shield-halved"></i> ' + r + '</span>';
    if (role === 'yonetici') return '<span class="badge badge-admin" style="background:rgba(251,146,60,.12);color:#fb923c;border:1px solid rgba(251,146,60,.25)"><i class="fas fa-user-gear"></i> ' + r + '</span>';
    if (role === 'moderator') return '<span class="badge badge-admin" style="background:rgba(236,72,153,.12);color:#f472b6;border:1px solid rgba(236,72,153,.25)"><i class="fas fa-user-tie"></i> ' + r + '</span>';
    if (role === 'destek_ekibi') return '<span class="badge badge-admin" style="background:rgba(34,197,94,.12);color:#4ade80;border:1px solid rgba(34,197,94,.2)"><i class="fas fa-headset"></i> ' + r + '</span>';
    return '<span class="badge badge-user">' + r + '</span>';
}

function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        usersSearch = document.getElementById('userSearch')?.value || '';
        loadUsers(1);
    }, 350);
}

async function loadUsers(page) {
    usersPage = page;
    const tbody = document.getElementById('usersTableBody');
    const pagDiv = document.getElementById('usersPagination');
    const label  = document.getElementById('userCountLabel');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--muted)"><i class="fas fa-spinner fa-spin"></i> Yükleniyor...</td></tr>';
    try {
        const qs  = new URLSearchParams({ action:'list', p: page, q: usersSearch });
        const res = await fetch('/api/users?' + qs);
        const d   = await res.json();
        if (!d.ok) { tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:2rem;color:#ff7070">' + esc(d.msg) + '</td></tr>'; return; }

        if (label) label.innerHTML = 'Toplam <strong style="color:var(--text)">' + d.total + '</strong> kullanıcı';

        if (!d.users.length) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:2.5rem;color:var(--muted)">Kullanıcı bulunamadı.</td></tr>';
        } else {
            usersData = d.users;
            tbody.innerHTML = d.users.map(u => {
                const adminBadge = roleBadge(u.role || (u.is_admin == 1 ? 'admin' : 'user'));
                let isSelf = parseInt(u.id) === SELF_ID ? ' <span style="font-size:.7rem;color:var(--muted)">(sen)</span>' : '';
                const dt = new Date(u.created_at).toLocaleDateString('tr-TR',{day:'2-digit',month:'2-digit',year:'numeric'});
                const bl = parseFloat(u.balance || 0).toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2});
                const risk = parseInt(u.risk || 0);
                const banned = parseInt(u.is_active) === 0 && (u.ban_reason || '') !== '';
                const riskBadge = banned
                    ? '<span class="badge badge-rev"><i class="fas fa-user-slash"></i> Banned</span>'
                    : (risk > 0
                    ? '<button onclick="showLinks(' + u.id + ')" class="btn-tbl btn-tbl-warn" title="Bağlantılı hesapları gör"><i class="fas fa-link"></i> ' + risk + '</button>'
                    : '<span style="color:var(--muted);font-size:.78rem">—</span>');
                if (banned) isSelf += ' <span class="badge badge-rev" style="margin-left:4px"><i class="fas fa-gavel"></i> multi-ban</span>';
                return `<tr id="urow-${u.id}" class="urow" onclick="rowOpen(event, ${u.id})">
                    <td class="text-muted" style="font-size:.8rem">${esc(u.id)}</td>
                    <td style="font-weight:600">${esc(u.username)}${isSelf}</td>
                    <td><a href="mailto:${esc(u.email)}" onclick="event.stopPropagation()" style="color:var(--cyan);text-decoration:none;font-size:.85rem">${esc(u.email)}</a></td>
                    <td>${adminBadge}</td>
                    <td style="font-size:.85rem;font-weight:600;color:#fbbf24"><i class="fas fa-coins" style="font-size:.75rem"></i> ₺${bl}</td>
                    <td style="text-align:center">${riskBadge}</td>
                    <td class="text-muted" style="font-size:.8rem">${dt}</td>
                    <td style="text-align:center">
                        <button onclick="event.stopPropagation();openUserModal(${u.id})"
                            class="btn-tbl btn-tbl-info"><i class="fas fa-pen"></i> Düzenle</button>
                    </td>
                </tr>`;
            }).join('');
        }

        if (pagDiv) {
            let html = '';
            for (let p = 1; p <= d.totalPages; p++) {
                html += `<button class="page-btn ${p===d.page?'active':''}" onclick="loadUsers(${p})">${p}</button>`;
            }
            pagDiv.innerHTML = html;
        }
    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:2rem;color:#ff7070">Bağlantı hatası.</td></tr>';
    }
}

function rowOpen(ev, id) {
    if (ev && ev.target && ev.target.closest('a,button,input,select,textarea')) return;
    openUserModal(id);
}

function openUserModal(id) {
    const u = usersData.find(x => parseInt(x.id) === parseInt(id));
    if (!u) return;
    currentUser = u;
    document.getElementById('modalEmail').textContent   = u.email;
    document.getElementById('modalDate').textContent    = new Date(u.created_at).toLocaleString('tr-TR');
    document.getElementById('modalRole').innerHTML      = roleBadge(u.role || (u.is_admin == 1 ? 'admin' : 'user'));
    document.getElementById('modalBalance').textContent = '₺' + parseFloat(u.balance || 0).toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2});
    document.getElementById('modalUsername').value      = u.username;
    const sel = document.getElementById('modalRoleSelect');
    sel.value = (u.role && ROLE_LABELS[u.role]) ? u.role : (u.is_admin == 1 ? 'admin' : 'user');
    sel.disabled = !CAN_SET_ROLE || parseInt(u.id) === SELF_ID;
    if (sel.disabled) sel.style.opacity = '.45'; else sel.style.opacity = '1';
    showModalAlert('', '');
    document.getElementById('userModal').style.display = 'flex';
    const unb = document.getElementById('unbanBtn');
    const bnb = document.getElementById('banBtn');
    const isBanned = parseInt(u.is_active) === 0 && (u.ban_reason || '') !== '';
    if (unb) unb.style.display = isBanned ? 'block' : 'none';
    if (bnb) bnb.style.display = (!isBanned && parseInt(u.id) !== SELF_ID) ? 'block' : 'none';
    loadLinks(id);
}

const SIGNAL_LABELS = { email_norm:'Aynı e-posta (normalize)', shared_ip:'Aynı IP', device_cookie:'Aynı cihaz', fingerprint:'Aynı tarayıcı', register_link:'Kayıt eşleşmesi', login_link:'Giriş eşleşmesi' };

async function showLinks(id) {
    openUserModal(id);
}

async function loadLinks(id) {
    const box = document.getElementById('linkedBox');
    if (!box) return;
    box.innerHTML = '<div style="text-align:center;color:var(--muted);font-size:.82rem;padding:.6rem"><i class="fas fa-spinner fa-spin"></i> Bağlantılar yükleniyor...</div>';
    try {
        const fd = new FormData();
        fd.append('action', 'links');
        fd.append('id', id);
        fd.append('_csrf', VX_CSRF);
        const res = await fetch('/api/users', { method:'POST', body:fd });
        const d = await res.json();
        if (!d.ok) { box.innerHTML = '<div style="color:#ff7070;font-size:.82rem">' + esc(d.msg || 'Hata') + '</div>'; return; }
        let html = '';
        if (d.linked && d.linked.length) {
            html += '<div style="font-size:.78rem;font-weight:700;color:var(--muted);margin-bottom:.5rem">BAĞLANTILI HESAPLAR (' + d.linked.length + ')</div>';
            html += d.linked.map(l => '<div style="display:flex;align-items:center;gap:.6rem;padding:.45rem .6rem;background:rgba(251,146,60,.06);border:1px solid rgba(251,146,60,.25);border-radius:9px;margin-bottom:.4rem;font-size:.82rem"><i class="fas fa-link" style="color:#fb923c"></i><strong>' + esc(l.username) + '</strong><span style="color:var(--muted)">' + esc(l.email) + '</span><span class="badge badge-susp" style="margin-left:auto">' + esc(SIGNAL_LABELS[l.signal] || l.signal) + '</span></div>').join('');
        } else {
            html += '<div style="font-size:.82rem;color:#4ade80"><i class="fas fa-check-circle"></i> Bağlantılı hesap yok.</div>';
        }
        if (d.flags && d.flags.length) {
            html += '<div style="font-size:.78rem;font-weight:700;color:var(--muted);margin:.8rem 0 .5rem">BAYRAKLAR</div>';
            html += d.flags.map(f => '<div style="font-size:.78rem;color:var(--muted);padding:.3rem 0;border-bottom:1px solid rgba(255,255,255,.04)">' + esc(f.created_at) + ' — ' + esc(SIGNAL_LABELS[f.signal] || f.signal) + (f.linked_user_id > 0 ? ' → #' + esc(f.linked_user_id) : '') + (f.detail ? ' <span style="opacity:.7">(' + esc(f.detail) + ')</span>' : '') + '</div>').join('');
        }
        box.innerHTML = html;
    } catch(e) {
        box.innerHTML = '<div style="color:#ff7070;font-size:.82rem">Bağlantı hatası.</div>';
    }
}

function openCreditModal() {
    if (!currentUser) return;
    document.getElementById('creditUserName').textContent    = currentUser.username;
    document.getElementById('creditUserBalance').textContent = '₺' + parseFloat(currentUser.balance || 0).toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2});
    document.getElementById('creditAmount').value = '';
    document.getElementById('creditNote').value    = '';
    showCreditAlert('', '');
    document.getElementById('creditModal').style.display = 'flex';
}

function closeCreditModal() {
    document.getElementById('creditModal').style.display = 'none';
}

function showCreditAlert(msg, type) {
    const el = document.getElementById('creditModalAlert');
    if (!msg) { el.style.display='none'; return; }
    el.className = 'alert-msg ' + (type === 'ok' ? 'alert-ok' : 'alert-err');
    el.textContent = msg;
    el.style.display = 'block';
}

async function banUser() {
    if (!currentUser) return;
    if (!confirm('Bu kullanıcı banlansın mı? Hesap giriş yapamaz ve panele giremez.')) return;
    const fd = new FormData();
    fd.append('action', 'ban');
    fd.append('id', currentUser.id);
    fd.append('_csrf', VX_CSRF);
    try {
        const res = await fetch('/api/users', { method:'POST', body:fd });
        const d = await res.json();
        alert(d.msg || (d.ok ? 'Tamam' : 'Hata'));
        if (d.ok) { closeUserModal(); loadUsers(usersPage); }
    } catch(e) { alert('Bağlantı hatası.'); }
}

async function unbanUser() {
    if (!currentUser) return;
    if (!confirm('Bu hesabın banı kaldırılsın mı? Hesap aktif hale gelecek.')) return;
    const fd = new FormData();
    fd.append('action', 'unban');
    fd.append('id', currentUser.id);
    fd.append('_csrf', VX_CSRF);
    try {
        const res = await fetch('/api/users', { method:'POST', body:fd });
        const d = await res.json();
        alert(d.msg || (d.ok ? 'Tamam' : 'Hata'));
        if (d.ok) { closeUserModal(); loadUsers(usersPage); }
    } catch(e) { alert('Bağlantı hatası.'); }
}

async function sendCredit() {
    if (!currentUser) return;
    if (parseInt(currentUser.id) === SELF_ID) { showCreditAlert('Kendine kredi gönderemezsin.', 'err'); return; }
    const amount = parseFloat(document.getElementById('creditAmount').value);
    if (!amount || amount <= 0) { showCreditAlert('Geçerli bir tutar girin.', 'err'); return; }
    const note = document.getElementById('creditNote').value.trim();
    const fd = new FormData();
    fd.append('action', 'send_credit');
    fd.append('id', currentUser.id);
    fd.append('amount', amount);
    fd.append('note', note);
    fd.append('_csrf', VX_CSRF);
    try {
        const res = await fetch('/api/users', { method:'POST', body:fd });
        const d   = await res.json();
        if (d.ok) {
            currentUser.balance = d.balance;
            document.getElementById('creditUserBalance').textContent = '₺' + parseFloat(d.balance).toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2});
            document.getElementById('modalBalance').textContent = document.getElementById('creditUserBalance').textContent;
            document.getElementById('creditAmount').value = '';
            document.getElementById('creditNote').value = '';
            showCreditAlert(d.msg, 'ok');
            const row = document.getElementById('urow-' + currentUser.id);
            if (row) {
                const bl = parseFloat(d.balance).toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2});
                row.children[4].innerHTML = '<span style="font-size:.85rem;font-weight:600;color:#fbbf24"><i class="fas fa-coins" style="font-size:.75rem"></i> ₺' + bl + '</span>';
            }
        } else {
            showCreditAlert(d.msg, 'err');
        }
    } catch(e) { showCreditAlert('Bağlantı hatası.', 'err'); }
}

function closeUserModal() {
    document.getElementById('userModal').style.display = 'none';
    const cm = document.getElementById('creditModal');
    if (cm) cm.style.display = 'none';
    currentUser = null;
}

function showModalAlert(msg, type) {
    const el = document.getElementById('userModalAlert');
    if (!msg) { el.style.display='none'; return; }
    el.className = 'alert-msg ' + (type === 'ok' ? 'alert-ok' : 'alert-err');
    el.textContent = msg;
    el.style.display = 'block';
}

async function saveRename() {
    if (!currentUser) return;
    const newName = document.getElementById('modalUsername').value.trim();
    if (!newName) { showModalAlert('Kullanıcı adı boş olamaz.', 'err'); return; }
    const fd = new FormData();
    fd.append('action', 'rename');
    fd.append('id', currentUser.id);
    fd.append('username', newName);
    fd.append('_csrf', VX_CSRF);
    try {
        const res = await fetch('/api/users', { method:'POST', body:fd });
        const d   = await res.json();
        showModalAlert(d.msg, d.ok ? 'ok' : 'err');
        if (d.ok) {
            currentUser.username = d.username;
            const row = document.getElementById('urow-' + currentUser.id);
            if (row) {
                const isSelf = parseInt(currentUser.id) === SELF_ID ? ' <span style="font-size:.7rem;color:var(--muted)">(sen)</span>' : '';
                row.children[1].innerHTML = esc(d.username) + isSelf;
            }
        }
    } catch(e) { showModalAlert('Bağlantı hatası.', 'err'); }
}

async function setRole() {
    if (!currentUser) return;
    if (!CAN_SET_ROLE) { showModalAlert('Rol atamak için admin yetkisi gerekli.', 'err'); return; }
    if (parseInt(currentUser.id) === SELF_ID) { showModalAlert('Kendi rolünü değiştiremezsin.', 'err'); return; }
    const sel = document.getElementById('modalRoleSelect');
    const newRole = sel.value;
    const fd = new FormData();
    fd.append('action', 'set_role');
    fd.append('id', currentUser.id);
    fd.append('role', newRole);
    fd.append('_csrf', VX_CSRF);
    try {
        const res = await fetch('/api/users', { method:'POST', body:fd });
        const d   = await res.json();
        showModalAlert(d.msg, d.ok ? 'ok' : 'err');
        if (d.ok) {
            currentUser.role = d.role;
            currentUser.is_admin = d.is_admin;
            document.getElementById('modalRole').innerHTML = roleBadge(d.role);
            const row = document.getElementById('urow-' + currentUser.id);
            if (row) row.children[3].innerHTML = roleBadge(d.role);
        } else {
            sel.value = currentUser.role || (currentUser.is_admin == 1 ? 'admin' : 'user');
        }
    } catch(e) { showModalAlert('Bağlantı hatası.', 'err'); }
}

async function deleteUser() {
    if (!currentUser) return;
    if (parseInt(currentUser.id) === SELF_ID) { showModalAlert('Kendi hesabını silemezsin.', 'err'); return; }
    if (!confirm('"' + currentUser.username + '" kullanıcısını silmek istediğinden emin misin?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', currentUser.id);
    fd.append('_csrf', VX_CSRF);
    try {
        const res = await fetch('/api/users', { method:'POST', body:fd });
        const d   = await res.json();
        if (d.ok) {
            const row = document.getElementById('urow-' + currentUser.id);
            if (row) { row.style.transition='opacity .3s'; row.style.opacity='0'; setTimeout(()=>row.remove(),300); }
            closeUserModal();
        } else {
            showModalAlert(d.msg, 'err');
        }
    } catch(e) { showModalAlert('Bağlantı hatası.', 'err'); }
}

if (document.getElementById('usersTableBody')) {
    loadUsers(1);
}
</script>
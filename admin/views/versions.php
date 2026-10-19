<!-- VERSIYONLAR -->
<div class="panel" style="margin-bottom:1.2rem">
    <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between">
        <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
            <span id="verCountLabel" class="text-muted" style="font-size:.85rem"></span>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <button class="btn btn-primary" onclick="openVerModal(null)"><i class="fas fa-plus"></i> Yeni Sürüm Yükle</button>
            <button class="btn" onclick="loadVersions()"><i class="fas fa-rotate-right"></i> Yenile</button>
        </div>
    </div>
</div>
<div class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>Sürüm</th>
                    <th>Açıklama</th>
                    <th>Dosya</th>
                    <th style="width:90px">Boyut</th>
                    <th style="width:90px">Yükleyen</th>
                    <th style="width:90px">Erteleyen</th>
                    <th style="width:80px">Durum</th>
                    <th>Yayın</th>
                    <th style="width:240px;text-align:center">İşlemler</th>
                </tr>
            </thead>
            <tbody id="verTableBody">
                <tr><td colspan="10" style="text-align:center;padding:2.5rem" class="text-muted">Yükleniyor…</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Yeni Sürüm Modal -->
<div id="verModal" onclick="if(event.target===this)closeVerModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center;overflow-y:auto;padding:1.5rem">
    <div style="background:#0d1b3e;border:1px solid rgba(0,212,255,.25);border-radius:20px;padding:2rem;max-width:520px;width:92%;position:relative;max-height:calc(88vh - 3rem);overflow-y:auto;margin:auto">
        <button onclick="closeVerModal()" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.07);border:1px solid var(--border);border-radius:8px;color:var(--text);width:30px;height:30px;cursor:pointer;font-size:.95rem;display:flex;align-items:center;justify-content:center"><i class="fas fa-xmark"></i></button>
        <h3 style="font-size:1rem;font-weight:800;margin-bottom:1.4rem;color:#fff"><i class="fas fa-box-archive" style="color:var(--cyan);margin-right:8px"></i>Yeni Sürüm Yükle</h3>
        <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:6px">Sürüm Numarası <span style="color:#ff7070">*</span></label>
        <input id="verVersion" placeholder="Örnek: 1.0.0"
            style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;margin-bottom:1rem;font-family:'Inter',sans-serif">
        <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:6px">İlgili Ürün (hangi ürünü alanlara gitsin)</label>
        <select id="verProduct" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;margin-bottom:1rem;font-family:'Inter',sans-serif">
            <option value="">Genel (tüm kullanıcılara bildirme, sadece sürüm listesi)</option>
        </select>
        <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:6px">Açıklama</label>
        <textarea id="verDesc" rows="3" placeholder="Bu sürümde neler var?"
            style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;margin-bottom:1rem;font-family:'Inter',sans-serif;resize:vertical"></textarea>
        <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:6px">Güncelleme Dosyası (zip, max 1 GB) <span style="color:#ff7070">*</span></label>
        <input id="verFile" type="file" accept=".zip"
            style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;margin-bottom:1rem;font-family:'Inter',sans-serif">
        <button id="verSaveBtn" onclick="saveVer()" style="width:100%;padding:12px;background:linear-gradient(135deg,#00d4ff,#0a7dff);border:none;border-radius:12px;color:#041a35;font-size:.95rem;font-weight:800;cursor:pointer;font-family:inherit">Yükle</button>
    </div>
</div>

<!-- Sürüm Detay / İstatistik Modal -->
<div id="verStatsModal" onclick="if(event.target===this)closeVerStatsModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center;overflow-y:auto;padding:1.5rem">
    <div style="background:#0d1b3e;border:1px solid rgba(0,212,255,.25);border-radius:20px;padding:2rem;max-width:640px;width:92%;position:relative;max-height:calc(88vh - 3rem);overflow-y:auto;margin:auto">
        <button onclick="closeVerStatsModal()" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.07);border:1px solid var(--border);border-radius:8px;color:var(--text);width:30px;height:30px;cursor:pointer;font-size:.95rem;display:flex;align-items:center;justify-content:center"><i class="fas fa-xmark"></i></button>
        <h3 style="font-size:1rem;font-weight:800;margin-bottom:1.4rem;color:#fff"><i class="fas fa-chart-column" style="color:var(--cyan);margin-right:8px"></i><span id="verStatsTitle">Sürüm</span></h3>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:1.4rem">
            <div style="background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:14px;padding:1rem;text-align:center">
                <div style="font-size:1.6rem;font-weight:800;color:#4ade80" id="verStatsInstalled">0</div>
                <div style="font-size:.75rem;color:var(--muted);margin-top:.25rem">Yükleyen Site</div>
            </div>
            <div style="background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:14px;padding:1rem;text-align:center">
                <div style="font-size:1.6rem;font-weight:800;color:#facc15" id="verStatsDelayed">0</div>
                <div style="font-size:.75rem;color:var(--muted);margin-top:.25rem">Erteleme</div>
            </div>
            <div style="background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:14px;padding:1rem;text-align:center">
                <div style="font-size:1.6rem;font-weight:800;color:var(--cyan)" id="verStatsFileSize">0</div>
                <div style="font-size:.75rem;color:var(--muted);margin-top:.25rem">Dosya Boyutu</div>
            </div>
        </div>
        <div style="font-size:.82rem;font-weight:700;color:var(--muted);margin-bottom:10px">Son 14 Gün</div>
        <div id="verStatsChart" style="display:flex;align-items:flex-end;gap:6px;height:120px;margin-bottom:1.4rem"></div>
        <div style="font-size:.82rem;font-weight:700;color:var(--muted);margin-bottom:10px">Yükleyen Domainler</div>
        <div id="verStatsDomains" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:1.4rem"></div>
        <div style="font-size:.82rem;font-weight:700;color:var(--muted);margin-bottom:10px">Son Etkinlikler</div>
        <div class="table-wrap">
            <table style="min-width:100%">
                <thead><tr><th>Domain</th><th>Olay</th><th>Erteleme</th><th>Tarih</th></tr></thead>
                <tbody id="verStatsEvents"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
const VER_CSRF = VX_CSRF;
let verPage = null;

async function loadVersions() {
    const tbody = document.getElementById('verTableBody');
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:2.5rem" class="text-muted">Yükleniyor…</td></tr>';
    try {
        const res = await fetch('/api/versions?action=list', { headers:{'X-CSRF-Token': VER_CSRF} });
        const d = await res.json();
        if (!d.ok) { tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:2.5rem" class="text-muted">' + verEsc(d.msg) + '</td></tr>'; return; }
        const rows = d.versions || [];
        document.getElementById('verCountLabel').textContent = rows.length + ' sürüm';
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:2.5rem" class="text-muted">Henüz sürüm yok. İlk sürümü yükleyin.</td></tr>';
            return;
        }
        let h = '';
        rows.forEach((v, i) => {
            const active = parseInt(v.is_active) === 1;
            h += '<tr>'
                + '<td>' + v.id + '</td>'
                + '<td><strong style="color:#fff">v' + verEsc(v.version) + '</strong></td>'
                + '<td style="max-width:220px"><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + verEsc(v.description || '') + '</div></td>'
                + '<td style="font-size:.8rem">' + verEsc(v.file_name) + '</td>'
                + '<td>' + v.file_size + '</td>'
                + '<td><span style="color:#4ade80;font-weight:700">' + (v.installed_count||0) + '</span></td>'
                + '<td><span style="color:#facc15;font-weight:700">' + (v.delayed_count||0) + '</span></td>'
                + '<td>' + (active ? '<span class="badge" style="background:rgba(34,197,94,.12);color:#4ade80">Aktif</span>' : '<span class="badge" style="background:rgba(148,163,184,.12);color:#94a3b8">Pasif</span>') + '</td>'
                + '<td style="font-size:.8rem">' + v.created_at + '</td>'
                + '<td style="text-align:center;white-space:nowrap">'
                + '<button class="btn" onclick="openVerStats(' + v.id + ')" title="İstatistikler"><i class="fas fa-chart-column"></i></button> '
                + '<button class="btn" onclick="toggleVer(' + v.id + ')" title="Aktif/Pasif"><i class="fas fa-power-off"></i></button> '
                + '<button class="btn" onclick="delVer(' + v.id + ')" title="Sil" style="color:#f87171"><i class="fas fa-trash"></i></button> '
                + '</td>'
                + '</tr>';
        });
        tbody.innerHTML = h;
    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:2.5rem" class="text-muted">Bağlantı hatası.</td></tr>';
    }
}

async function loadVerProducts(){
    try{
        const r=await fetch('/api/products?action=list',{headers:{'X-CSRF-Token':VER_CSRF}});
        const d=await r.json();
        const sel=document.getElementById('verProduct');
        if(d.ok && d.products){
            sel.innerHTML='<option value="">Genel (tüm kullanıcılara bildirme, sadece sürüm listesi)</option>'+d.products.map(p=>'<option value="'+p.id+'">'+verEsc(p.name)+'</option>').join('');
        }
    }catch(e){}
}
function openVerModal() {
    document.getElementById('verVersion').value = '';
    document.getElementById('verDesc').value = '';
    document.getElementById('verFile').value = '';
    loadVerProducts();
    document.getElementById('verModal').style.display = 'flex';
}
function closeVerModal() {
    document.getElementById('verModal').style.display = 'none';
}

async function saveVer() {
    const version = document.getElementById('verVersion').value.trim();
    const desc = document.getElementById('verDesc').value.trim();
    const file = document.getElementById('verFile').files[0];
    if (!version) { alert('Sürüm numarası gerekli.'); return; }
    if (!file) { alert('Dosya seçin.'); return; }
    if (file.size > 1073741824) { alert('Dosya 1 GB üzeri olamaz.'); return; }
    const btn = document.getElementById('verSaveBtn');
    btn.disabled = true; btn.textContent = 'Yükleniyor…';
    const fd = new FormData();
    fd.append('action', 'create');
    fd.append('version', version);
    fd.append('description', desc);
    fd.append('product_id', document.getElementById('verProduct').value || '');
    fd.append('update_file', file);
    try {
        const res = await fetch('/api/versions', { method:'POST', headers:{'X-CSRF-Token': VER_CSRF}, body:fd });
        const d = await res.json();
        if (d.ok) {
            closeVerModal();
            loadVersions();
        } else {
            alert(d.msg);
        }
    } catch(e) {
        alert('Bağlantı hatası.');
    } finally {
        btn.disabled = false; btn.textContent = 'Yükle';
    }
}

function verEsc(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

async function openVerStats(id) {
    document.getElementById('verStatsModal').style.display = 'flex';    document.getElementById('verStatsTitle').textContent = 'Sürüm #' + id;
    document.getElementById('verStatsInstalled').textContent = '…';
    document.getElementById('verStatsDelayed').textContent = '…';
    document.getElementById('verStatsFileSize').textContent = '…';
    document.getElementById('verStatsDomains').innerHTML = '';
    document.getElementById('verStatsEvents').innerHTML = '<tr><td colspan="4" style="text-align:center;padding:1.5rem" class="text-muted">Yükleniyor…</td></tr>';
    document.getElementById('verStatsChart').innerHTML = '';
    try {
        const res = await fetch('/api/versions?action=stats&id=' + id, { headers:{'X-CSRF-Token': VER_CSRF} });
        const d = await res.json();
        if (!d.ok) { alert(d.msg); return; }
        document.getElementById('verStatsTitle').textContent = 'v' + d.version.version;
        document.getElementById('verStatsInstalled').textContent = d.installed;
        document.getElementById('verStatsDelayed').textContent = d.delayed;
        document.getElementById('verStatsFileSize').textContent = d.version.file_size;

        const chart = document.getElementById('verStatsChart');
        let chartHtml = '';
        const max = Math.max(1, ...(d.last14 || []).map(x => Math.max(x.installed, x.delayed)));
        (d.last14 || []).forEach(x => {
            const ih = Math.round((x.installed / max) * 100);
            const dh = Math.round((x.delayed / max) * 100);
            chartHtml += '<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;min-width:0">'
                + '<div style="display:flex;align-items:flex-end;gap:3px;height:90px;width:100%;justify-content:center">'
                + '<div title="Yükleyen ' + x.installed + '" style="width:9px;background:#22c55e;border-radius:3px 3px 0 0;height:' + Math.max(2, ih) + 'px"></div>'
                + '<div title="Erteleme ' + x.delayed + '" style="width:9px;background:#eab308;border-radius:3px 3px 0 0;height:' + Math.max(2, dh) + 'px"></div>'
                + '</div>'
                + '<div style="font-size:.58rem;color:var(--muted);white-space:nowrap">' + x.date.slice(5) + '</div>'
                + '</div>';
        });
        chart.innerHTML = chartHtml || '<div style="color:var(--muted);font-size:.8rem">Veri yok</div>';

        const domains = document.getElementById('verStatsDomains');
        domains.innerHTML = (d.domains && d.domains.length)
            ? d.domains.map(x => '<span class="badge" style="background:rgba(34,197,94,.12);color:#4ade80;padding:5px 10px;font-size:.75rem">' + verEsc(x) + '</span>').join('')
            : '<span class="text-muted" style="font-size:.8rem">Henüz yükleyen yok</span>';

        const evT = document.getElementById('verStatsEvents');
        evT.innerHTML = (d.events && d.events.length)
            ? d.events.slice(0, 50).map(x => '<tr>'
                + '<td style="font-size:.8rem">' + verEsc(x.domain) + '</td>'
                + '<td>' + (x.event_type === 'installed'
                    ? '<span class="badge" style="background:rgba(34,197,94,.12);color:#4ade80">Yüklendi</span>'
                    : '<span class="badge" style="background:rgba(234,179,8,.12);color:#facc15">Erteleme ' + (x.delay_days||0) + ' gün</span>') + '</td>'
                + '<td style="font-size:.8rem">' + (x.delay_days||0) + ' gün</td>'
                + '<td style="font-size:.8rem">' + x.created_at + '</td>'
                + '</tr>').join('')
            : '<tr><td colspan="4" style="text-align:center;padding:1.5rem" class="text-muted">Etkinlik yok</td></tr>';
    } catch(e) {
        alert('Bağlantı hatası.');
    }
}
function closeVerStatsModal() {
    document.getElementById('verStatsModal').style.display = 'none';
}

async function toggleVer(id) {
    const fd = new FormData();
    fd.append('action', 'toggle');
    fd.append('id', id);
    try {
        const res = await fetch('/api/versions', { method:'POST', headers:{'X-CSRF-Token': VER_CSRF}, body:fd });
        const d = await res.json();
        if (d.ok) loadVersions(); else alert(d.msg);
    } catch(e) { alert('Bağlantı hatası.'); }
}

async function delVer(id) {
    if (!confirm('Bu sürüm ve istatistikleri silinsin mi?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    try {
        const res = await fetch('/api/versions', { method:'POST', headers:{'X-CSRF-Token': VER_CSRF}, body:fd });
        const d = await res.json();
        if (d.ok) loadVersions(); else alert(d.msg);
    } catch(e) { alert('Bağlantı hatası.'); }
}

document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('verTableBody')) loadVersions();
});
</script>

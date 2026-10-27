<!-- API & AI -->
<div class="panel" style="margin-bottom:1.2rem">
    <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between">
        <div style="font-size:1rem;font-weight:800;color:#fff"><i class="fas fa-sparkles" style="color:var(--cyan);margin-right:8px"></i>AI Sağlayıcılar</div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <button class="btn btn-primary" onclick="openAiModal(null)"><i class="fas fa-plus"></i> Ekle</button>
            <button class="btn" onclick="loadAi()"><i class="fas fa-rotate-right"></i> Yenile</button>
        </div>
    </div>
    <p class="text-muted" style="font-size:.82rem;margin-top:.6rem">Anahtarlar sunucuda şifreli saklanır, yanıtlara ve loglara asla açık yazılmaz. Müşteri siteleri bu anahtarları hiç görmez.</p>
</div>
<div class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th style="width:50px">#</th><th>Sağlayıcı</th><th>Model</th><th>Öncelik</th><th>Kredi Oranı</th><th>Durum</th><th style="width:260px;text-align:center">İşlemler</th></tr>
            </thead>
            <tbody id="aiTableBody">
                <tr><td colspan="7" style="text-align:center;padding:2.5rem" class="text-muted">Yükleniyor…</td></tr>
            </tbody>
        </table>
    </div>
</div>
<div class="panel" style="margin-top:1.2rem">
    <div style="display:flex;flex-wrap:wrap;gap:.8rem;align-items:center;justify-content:space-between">
        <div style="font-size:1rem;font-weight:800;color:#fff"><i class="fas fa-list-ul" style="color:var(--cyan);margin-right:8px"></i>AI İstek Logları</div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
            <input id="aiLogSearch" list="aiLogDomains" placeholder="Domain ara…" onkeydown="if(event.key==='Enter')loadLogs()" style="background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.45rem .7rem;font-size:.82rem;outline:none;font-family:inherit;min-width:170px">
            <datalist id="aiLogDomains"></datalist>
            <label style="display:flex;align-items:center;gap:.4rem;font-size:.8rem;color:var(--muted);cursor:pointer"><input id="aiLogErrors" type="checkbox" onchange="loadLogs()"> Sadece hatalar</label>
            <select id="aiLogLimit" onchange="loadLogs()" style="background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.45rem .6rem;font-size:.82rem;outline:none;font-family:inherit">
                <option value="50">50</option>
                <option value="100" selected>100</option>
                <option value="200">200</option>
                <option value="300">300</option>
            </select>
            <button class="btn" onclick="loadLogs()"><i class="fas fa-rotate-right"></i> Yenile</button>
        </div>
    </div>
    <p class="text-muted" style="font-size:.8rem;margin-top:.6rem">Gelen her AI isteği domain, IP, plan, prompt, mesaj geçmişi, sağlayıcı/model, token, kredi ve süre ile loglanır. Kayıtlar 60 gün saklanır.</p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th style="width:120px">Zaman</th><th>Domain</th><th style="width:80px">Plan</th><th style="width:150px">Sağlayıcı</th><th>Prompt</th><th style="width:85px">Token (g/ç)</th><th style="width:60px">Kredi</th><th style="width:75px">Durum</th><th style="width:65px">Süre</th><th style="width:70px"></th></tr>
            </thead>
            <tbody id="aiLogBody">
                <tr><td colspan="10" style="text-align:center;padding:2.5rem" class="text-muted">Yükleniyor…</td></tr>
            </tbody>
        </table>
    </div>
</div>
<div class="panel" style="margin-top:1.2rem">
    <div style="font-size:1rem;font-weight:800;color:#fff;margin-bottom:1rem"><i class="fas fa-gauge-high" style="color:var(--cyan);margin-right:8px"></i>Kota Kullanımı <span id="aiMonth" class="text-muted" style="font-size:.8rem"></span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Domain</th><th style="width:160px">Kullanılan</th><th style="width:220px">Oran</th></tr></thead>
            <tbody id="aiQuotaBody">
                <tr><td colspan="3" style="text-align:center;padding:2rem" class="text-muted">Yükleniyor…</td></tr>
            </tbody>
        </table>
    </div>
</div>
<div id="aiModal" onclick="if(event.target===this)closeAiModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center;overflow-y:auto;padding:1.5rem">
    <div style="background:#0d1b3e;border:1px solid rgba(0,212,255,.25);border-radius:20px;padding:2rem;max-width:520px;width:92%;position:relative;margin:auto">
        <button onclick="closeAiModal()" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.07);border:1px solid var(--border);border-radius:8px;color:var(--text);width:30px;height:30px;cursor:pointer;font-size:.95rem;display:flex;align-items:center;justify-content:center"><i class="fas fa-xmark"></i></button>
        <h3 style="font-size:1rem;font-weight:800;margin-bottom:1.4rem;color:#fff" id="aiModalTitle">Sağlayıcı Ekle</h3>
        <input type="hidden" id="aiId" value="">
        <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:6px">Sağlayıcı</label>
        <select id="aiProvider" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;margin-bottom:1rem;font-family:inherit">
            <option value="openrouter">OpenRouter</option>
            <option value="google">Google</option>
            <option value="dahl">Dahl Inference</option>
        </select>
        <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:6px">Model ID</label>
        <input id="aiModel" placeholder="örn: google/gemini-2.0-flash-001 veya gemini-2.0-flash" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;margin-bottom:1rem;font-family:inherit">
        <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:6px">API Anahtarı <span class="text-muted" style="font-size:.75rem">(düzenlerken boş bırakın)</span></label>
        <input id="aiKey" type="password" autocomplete="new-password" placeholder="sk-..." style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;margin-bottom:1rem;font-family:inherit">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.2rem">
            <div>
                <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:6px">Öncelik</label>
                <input id="aiPrio" type="number" min="0" max="100" value="0" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:inherit">
            </div>
            <div>
                <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:6px">Kredi Oranı (1K token başına)</label>
                <input id="aiRate" type="number" min="0" max="1000" step="0.001" value="0" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:inherit">
            </div>
            <div>
                <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:6px">Durum</label>
                <select id="aiActive" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:inherit">
                    <option value="1">Aktif</option>
                    <option value="0">Pasif</option>
                </select>
            </div>
        </div>
        <div id="aiTestOut" class="text-muted" style="font-size:.82rem;margin-bottom:1rem;min-height:1.2em"></div>
        <div style="display:flex;gap:.6rem">
            <button id="aiTestBtn" onclick="testAi()" style="flex:1;padding:12px;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:12px;color:var(--text);font-size:.9rem;font-weight:800;cursor:pointer;font-family:inherit">Test Et</button>
            <button onclick="saveAi()" style="flex:2;padding:12px;background:linear-gradient(135deg,#00d4ff,#0a7dff);border:none;border-radius:12px;color:#041a35;font-size:.95rem;font-weight:800;cursor:pointer;font-family:inherit">Kaydet</button>
        </div>
    </div>
</div>
<div id="aiLogModal" onclick="if(event.target===this)closeLogModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9100;align-items:center;justify-content:center;overflow-y:auto;padding:1.5rem">
    <div style="background:#0d1b3e;border:1px solid rgba(0,212,255,.25);border-radius:20px;padding:1.6rem;max-width:880px;width:94%;position:relative;margin:auto">
        <button onclick="closeLogModal()" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.07);border:1px solid var(--border);border-radius:8px;color:var(--text);width:30px;height:30px;cursor:pointer;font-size:.95rem;display:flex;align-items:center;justify-content:center"><i class="fas fa-xmark"></i></button>
        <h3 id="aiLogModalTitle" style="font-size:.95rem;font-weight:800;margin-bottom:1rem;color:#fff;padding-right:2.5rem"></h3>
        <div id="aiLogModalBody" style="font-size:.82rem;line-height:1.5"></div>
    </div>
</div>
<script>
const AI_CSRF = (typeof VX_CSRF !== 'undefined') ? VX_CSRF : '';
function aiPost(op, extra){
    var fd = new FormData();
    fd.append('op', op);
    for (var k in (extra || {})) fd.append(k, extra[k]);
    return fetch('/api/ai-providers.php', {method: 'POST', headers: {'X-CSRF-Token': AI_CSRF}, body: fd}).then(function(r){ return r.json(); });
}
function loadAi(){
    aiPost('list').then(function(d){
        var tb = document.getElementById('aiTableBody');
        if (!d.ok || !d.rows || !d.rows.length) { tb.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem" class="text-muted">Kayıt yok. + ile ekleyin.</td></tr>'; return; }
        var h = '';
        d.rows.forEach(function(r){
            h += '<tr><td>' + r.id + '</td><td style="font-weight:700">' + (r.provider === 'google' ? 'Google' : (r.provider === 'dahl' ? 'Dahl Inference' : 'OpenRouter')) + '</td><td><code>' + r.model_id.replace(/</g, '&lt;') + '</code></td><td>' + r.priority + '</td><td>1 + ' + r.credit_rate + '/1K</td>'
                + '<td>' + (r.is_active ? '<span style="background:rgba(52,211,153,.12);color:#34d399;border:1px solid rgba(52,211,153,.3);padding:2px 8px;border-radius:99px;font-size:.75rem;font-weight:700">Aktif</span>' : '<span style="background:rgba(255,255,255,.06);color:var(--muted);border:1px solid var(--border);padding:2px 8px;border-radius:99px;font-size:.75rem;font-weight:700">Pasif</span>') + '</td>'
                + '<td style="text-align:center;white-space:nowrap"><button class="btn btn-sm" onclick="testAiRow(' + r.id + ')">Test</button> '
                + '<button class="btn btn-sm" onclick="openAiModal(' + r.id + ')">Düzenle</button> '
                + '<button class="btn btn-sm btn-danger" onclick="delAi(' + r.id + ')">Sil</button></td></tr>';
        });
        tb.innerHTML = h;
        tb._rows = d.rows;
    });
    aiPost('quotas').then(function(d){
        var tb = document.getElementById('aiQuotaBody');
        document.getElementById('aiMonth').textContent = d.month || '';
        if (!d.ok || !d.rows || !d.rows.length) { tb.innerHTML = '<tr><td colspan="3" style="text-align:center;padding:2rem" class="text-muted">Henüz kullanım yok.</td></tr>'; return; }
        var h = '';
        d.rows.forEach(function(r){
            var pct = Math.min(100, Math.round(r.used / d.limit * 100));
            var col = pct >= 90 ? '#ff7070' : (pct >= 70 ? '#fbbf24' : '#00d4ff');
            h += '<tr><td>' + String(r.domain).replace(/</g, '&lt;') + '</td><td>' + Number(r.used).toLocaleString() + ' / ' + Number(d.limit).toLocaleString() + '</td>'
                + '<td><div style="background:rgba(255,255,255,.07);border-radius:99px;height:8px;overflow:hidden"><div style="width:' + pct + '%;height:100%;background:' + col + '"></div></div></td></tr>';
        });
        tb.innerHTML = h;
    });
}
function openAiModal(id){
    document.getElementById('aiId').value = id || '';
    document.getElementById('aiKey').value = '';
    document.getElementById('aiTestOut').textContent = '';
    document.getElementById('aiModalTitle').textContent = id ? 'Sağlayıcı Düzenle' : 'Sağlayıcı Ekle';
    if (id) {
        var tb = document.getElementById('aiTableBody');
        var rows = tb._rows || [];
        for (var i = 0; i < rows.length; i++) {
            if (rows[i].id === id) {
                document.getElementById('aiProvider').value = rows[i].provider;
                document.getElementById('aiModel').value = rows[i].model_id;
                document.getElementById('aiPrio').value = rows[i].priority;
                document.getElementById('aiRate').value = rows[i].credit_rate || 0;
                document.getElementById('aiActive').value = rows[i].is_active ? '1' : '0';
            }
        }
    } else {
        document.getElementById('aiProvider').value = 'openrouter';
        document.getElementById('aiModel').value = '';
        document.getElementById('aiPrio').value = '0';
        document.getElementById('aiRate').value = '0';
        document.getElementById('aiActive').value = '1';
    }
    var m = document.getElementById('aiModal');
    m.style.display = 'flex';
}
function closeAiModal(){ document.getElementById('aiModal').style.display = 'none'; }
function saveAi(){
    var model = document.getElementById('aiModel').value.trim();
    var key = document.getElementById('aiKey').value.trim();
    if (!model) { alert('Model ID gerekli'); return; }
    aiPost('save', {id: document.getElementById('aiId').value, provider: document.getElementById('aiProvider').value, model_id: model, api_key: key, is_active: document.getElementById('aiActive').value, priority: document.getElementById('aiPrio').value, credit_rate: document.getElementById('aiRate').value}).then(function(d){
        if (!d.ok) { alert('Hata: ' + (d.msg || '')); return; }
        closeAiModal();
        loadAi();
    });
}
function delAi(id){
    if (!confirm('Silinsin mi?')) return;
    aiPost('delete', {id: id}).then(function(d){ if (d.ok) loadAi(); });
}
function testAiRow(id){
    document.getElementById('aiTestOut').textContent = '';
    aiPost('test', {id: id}).then(function(d){
        document.getElementById('aiTestOut').textContent = d.ok ? ('OK · ' + (d.reply || '') + ' (in:' + d.in + ' out:' + d.out + ')') : ('Hata: ' + (d.msg || ''));
    });
}
function testAi(){
    var id = document.getElementById('aiId').value;
    if (!id) { alert('Önce kaydedin, sonra test edin'); return; }
    testAiRow(parseInt(id, 10));
}
function aiEsc(s){
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function aiPretty(s){
    if (!s) return '';
    try { return JSON.stringify(JSON.parse(s), null, 2); } catch (e) { return String(s); }
}
function aiPre(txt){
    if (!txt) return '<span class="text-muted">—</span>';
    return '<pre style="white-space:pre-wrap;word-break:break-word;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:10px;padding:.7rem;max-height:280px;overflow:auto;margin:.25rem 0 .9rem;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.75rem">' + aiEsc(txt) + '</pre>';
}
function loadLogs(){
    var tb = document.getElementById('aiLogBody');
    var dom = document.getElementById('aiLogSearch').value.trim();
    var err = document.getElementById('aiLogErrors').checked ? '1' : '0';
    var lim = document.getElementById('aiLogLimit').value;
    tb.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:2rem" class="text-muted">Yükleniyor…</td></tr>';
    aiPost('logs', {domain: dom, errors: err, limit: lim}).then(function(d){
        if (!d.ok) { tb.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:2rem;color:#ff7070">Hata: ' + aiEsc(d.msg || '') + '</td></tr>'; return; }
        var dl = document.getElementById('aiLogDomains');
        if (dl) {
            var opts = '';
            (d.domains || []).forEach(function(x){ opts += '<option value="' + aiEsc(x.domain) + '">' + aiEsc(x.domain) + ' (' + Number(x.c) + ')</option>'; });
            dl.innerHTML = opts;
        }
        if (!d.rows || !d.rows.length) { tb.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:2rem" class="text-muted">Kayıt yok.</td></tr>'; return; }
        var h = '';
        d.rows.forEach(function(r){
            var okBadge = (Number(r.ok) === 1)
                ? '<span style="background:rgba(52,211,153,.12);color:#34d399;border:1px solid rgba(52,211,153,.3);padding:2px 8px;border-radius:99px;font-size:.72rem;font-weight:700">OK</span>'
                : '<span title="' + aiEsc(r.err || '') + '" style="background:rgba(255,112,112,.12);color:#ff7070;border:1px solid rgba(255,112,112,.3);padding:2px 8px;border-radius:99px;font-size:.72rem;font-weight:700">' + aiEsc(r.status || 'Hata') + '</span>';
            var p = String(r.prompt_short || '').replace(/\s+/g, ' ').trim();
            h += '<tr>'
                + '<td style="white-space:nowrap;font-size:.75rem">' + aiEsc(r.created_at) + '</td>'
                + '<td style="font-weight:700">' + aiEsc(r.domain || '-') + (r.ip ? '<div class="text-muted" style="font-size:.7rem;font-weight:400">' + aiEsc(r.ip) + '</div>' : '') + '</td>'
                + '<td style="font-size:.75rem">' + aiEsc(r.plan || '-') + '</td>'
                + '<td style="font-size:.74rem">' + aiEsc(r.provider || '-') + '<div class="text-muted" style="font-size:.68rem">' + aiEsc(r.model_id || '') + '</div></td>'
                + '<td style="font-size:.78rem;max-width:340px">' + (p ? aiEsc(p.length > 140 ? p.slice(0, 140) + '…' : p) : '<span class="text-muted">—</span>') + '</td>'
                + '<td style="font-size:.74rem">' + Number(r.in_t || 0) + ' / ' + Number(r.out_t || 0) + '</td>'
                + '<td style="font-size:.74rem">' + Number(r.credits || 0) + '</td>'
                + '<td>' + okBadge + '</td>'
                + '<td style="font-size:.74rem">' + Number(r.ms || 0) + ' ms</td>'
                + '<td><button class="btn btn-sm" onclick="openLogModal(' + Number(r.id) + ')">Detay</button></td>'
                + '</tr>';
        });
        tb.innerHTML = h;
    });
}
function openLogModal(id){
    aiPost('log_detail', {id: id}).then(function(d){
        if (!d.ok) { alert('Hata: ' + (d.msg || '')); return; }
        var r = d.row;
        document.getElementById('aiLogModalTitle').textContent = '#' + r.id + ' · ' + (r.domain || '-') + ' · ' + (r.created_at || '');
        function cell(k, v){ return '<div style="background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:10px;padding:.5rem .7rem"><div class="text-muted" style="font-size:.7rem">' + k + '</div><div style="font-weight:700;font-size:.8rem">' + aiEsc(v == null || v === '' ? '—' : v) + '</div></div>'; }
        var meta = '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.6rem;margin-bottom:.8rem">'
            + cell('Durum', (Number(r.ok) === 1 ? 'OK' : ('Hata ' + (r.status || '')))) + cell('Domain', r.domain) + cell('IP', r.ip) + cell('Plan', r.plan)
            + cell('Dil', r.lang) + cell('Mod', r.mode) + cell('Sağlayıcı', r.provider) + cell('Model', r.model_id)
            + cell('Token (g/ç)', Number(r.in_t || 0) + ' / ' + Number(r.out_t || 0)) + cell('Kredi', r.credits) + cell('Süre', Number(r.ms || 0) + ' ms') + cell('Tarih', r.created_at)
            + '</div>';
        var body = meta
            + '<div class="text-muted" style="font-size:.72rem;margin-bottom:.2rem">PROMPT (son kullanıcı mesajı)</div>' + aiPre(r.prompt)
            + '<div class="text-muted" style="font-size:.72rem;margin-bottom:.2rem">MESAJLAR</div>' + aiPre(aiPretty(r.messages))
            + '<div class="text-muted" style="font-size:.72rem;margin-bottom:.2rem">YANIT</div>' + aiPre(r.reply)
            + '<div class="text-muted" style="font-size:.72rem;margin-bottom:.2rem">ARAÇLAR</div>' + aiPre(r.tools)
            + '<div class="text-muted" style="font-size:.72rem;margin-bottom:.2rem">ARAÇ ÇAĞRILARI</div>' + aiPre(aiPretty(r.tool_calls));
        if (r.err) body += '<div class="text-muted" style="font-size:.72rem;margin-bottom:.2rem">HATA</div><div style="color:#ff7070;font-weight:700">' + aiEsc(r.err) + '</div>';
        document.getElementById('aiLogModalBody').innerHTML = body;
        document.getElementById('aiLogModal').style.display = 'flex';
    });
}
function closeLogModal(){ document.getElementById('aiLogModal').style.display = 'none'; }
loadAi();
loadLogs();
</script>

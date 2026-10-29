<?php
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/turnstile.php';
boot_session();
log_pageview('whois');
$tsKey = turnstile_enabled() ? turnstile_site_key() : '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>WHOIS Sorgulama | Vexaris</title>
<meta name="description" content="Domain ve IP WHOIS/RDAP sorgulama aracı. Kayıt tarihi, bitiş tarihi, statü ve abuse bilgilerini anında öğrenin.">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/png" href="/assets/images/vexaris-logo.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--ink:#101418;--mut:#5b6470;--line:#e3e8ef;--card:#ffffff;--bg:#f4f6fa;--brand:#2f7fc4;--brand-d:#1f639e;--ok:#1a9e54;--bad:#d43d3d;--warn:#c77d1a;--radius:14px}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;-webkit-font-smoothing:antialiased}
.topbar{background:#fff;border-bottom:1px solid var(--line)}
.topbar-in{max-width:960px;margin:0 auto;padding:14px 20px;display:flex;align-items:center;gap:12px}
.topbar img{height:30px;width:auto}
.topbar .name{font-weight:800;font-size:1.05rem;letter-spacing:-.01em}
.topbar .tag{margin-left:auto;font-size:.75rem;color:var(--mut);text-decoration:none}
.topbar .tag:hover{color:var(--brand)}
.hero{background:linear-gradient(120deg,#12395e 0%,#1f639e 55%,#2f7fc4 100%);padding:44px 20px 58px;color:#fff}
.hero-in{max-width:720px;margin:0 auto;text-align:center}
.hero h1{font-size:clamp(1.5rem,4.5vw,2.2rem);font-weight:800;letter-spacing:-.02em;margin-bottom:8px}
.hero p{opacity:.85;font-size:.95rem}
.searchwrap{max-width:720px;margin:-30px auto 0;padding:0 20px;position:relative;z-index:2}
.searchcard{background:#fff;border:1px solid var(--line);border-radius:var(--radius);box-shadow:0 12px 32px -12px rgba(16,24,40,.18);padding:14px}
.searchrow{display:flex;gap:10px}
.searchrow input{flex:1;min-width:0;border:1px solid var(--line);border-radius:10px;padding:13px 15px;font-size:1rem;outline:none;transition:border-color .2s,box-shadow .2s;font-family:inherit}
.searchrow input:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(47,127,196,.15)}
.btn{border:none;border-radius:10px;background:var(--brand);color:#fff;font-weight:700;font-size:.95rem;padding:13px 22px;cursor:pointer;transition:background .2s,transform .15s;white-space:nowrap}
.btn:hover{background:var(--brand-d)}
.btn:active{transform:scale(.97)}
.btn:disabled{opacity:.6;cursor:wait}
.tswrap{margin-top:10px;display:flex;justify-content:center}
.hint{text-align:center;color:var(--mut);font-size:.8rem;margin:12px 0 0}
#out{max-width:960px;margin:22px auto 0;padding:0 20px 40px}
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:20px;margin-bottom:16px;box-shadow:0 2px 8px -2px rgba(16,24,40,.06)}
.card h2{font-size:1.05rem;font-weight:800;margin-bottom:4px;display:flex;align-items:center;gap:9px}
.card h2 i{color:var(--brand)}
.qline{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;background:#eef3f9;border:1px solid var(--line);border-radius:8px;padding:8px 12px;font-size:.9rem;margin:10px 0 0;word-break:break-all}
.kv{display:grid;grid-template-columns:170px 1fr;gap:8px 14px;margin-top:14px;font-size:.9rem}
.kv dt{color:var(--mut)}
.kv dd{font-weight:600;word-break:break-word}
.pill{display:inline-block;font-size:.72rem;font-weight:800;padding:4px 11px;border-radius:999px;letter-spacing:.02em;margin:2px 4px 2px 0}
.pill-blue{background:#e3effc;color:#1f639e}
.pill-green{background:#dcf3e5;color:#14743d}
.pill-red{background:#fbe3e3;color:#b32727}
.pill-gray{background:#eef1f5;color:#5b6470}
.pill-amber{background:#fdf0d8;color:#9a5f10}
.overdue{color:var(--bad);font-weight:800}
.avail{text-align:center;padding:34px 20px}
.avail .tick{width:64px;height:64px;margin:0 auto 14px;border-radius:50%;background:#dcf3e5;color:var(--ok);display:flex;align-items:center;justify-content:center;font-size:1.7rem;animation:pop .4s ease}
@keyframes pop{0%{transform:scale(.6);opacity:0}100%{transform:scale(1);opacity:1}}
.avail h2{justify-content:center;font-size:1.3rem}
.avail p{color:var(--mut);margin-top:8px;font-size:.92rem}
.rawhead{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.rawhead h2{margin:0}
.copybtn{margin-left:auto;border:1px solid var(--line);background:#fff;border-radius:8px;padding:7px 13px;font-size:.8rem;font-weight:700;color:var(--mut);cursor:pointer}
.copybtn:hover{color:var(--brand);border-color:var(--brand)}
pre.raw{background:#0d1117;color:#c9d1d9;border-radius:10px;padding:16px;font-size:.76rem;line-height:1.55;overflow:auto;max-height:420px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap;word-break:break-all}
.err{background:#fdeeee;border:1px solid #f3c1c1;color:#a11f1f;border-radius:12px;padding:15px 18px;font-size:.9rem;font-weight:600;margin-bottom:16px}
.spin{text-align:center;color:var(--mut);padding:26px}
.spin i{animation:rot 1s linear infinite;font-size:1.4rem}
@keyframes rot{to{transform:rotate(360deg)}}
.footer{text-align:center;color:var(--mut);font-size:.78rem;padding:0 20px 30px}
.footer a{color:var(--brand);text-decoration:none}
.abusebox{background:#f6f9fd;border:1px dashed #c4d6ea;border-radius:10px;padding:12px 14px;margin-top:14px;font-size:.86rem}
.trow{display:flex;gap:12px;align-items:flex-start;padding:10px 2px;border-bottom:1px solid #eef1f5;font-size:.88rem}
.trow:last-child{border-bottom:none}
.trow .ic{width:32px;height:32px;flex:none;border-radius:9px;background:#eef3f9;color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:.85rem}
.trow .tx{min-width:0}
.trow .lb{font-size:.72rem;color:var(--mut);text-transform:uppercase;letter-spacing:.05em;font-weight:700}
.trow .vl{font-weight:700;word-break:break-word;margin-top:2px}
.nsrow{display:flex;align-items:center;gap:10px;padding:9px 2px;border-bottom:1px solid #eef1f5;font-size:.88rem}
.nsrow:last-child{border:none}
.nsrow code{font-family:ui-monospace,Menlo,monospace;background:#eef3f9;padding:4px 9px;border-radius:7px;font-size:.82rem}
.nsrow .ips{margin-left:auto;color:var(--mut);font-size:.78rem;font-family:ui-monospace,Menlo,monospace;text-align:right}
@media(max-width:640px){
  .hero{padding:32px 16px 50px}
  .searchrow{flex-direction:column}
  .btn{width:100%;padding:14px}
  .kv{grid-template-columns:1fr;gap:2px}
  .kv dt{margin-top:8px}
  .card{padding:16px}
}
</style>
</head>
<body>
<header class="topbar"><div class="topbar-in">
<img src="/assets/images/vexaris-logo.png" alt="Vexaris">
<span class="name">Vexaris <span style="color:var(--brand)">WHOIS</span></span>
<a class="tag" href="/"><i class="fas fa-arrow-left"></i> vexaris.net</a>
</div></header>

<section class="hero"><div class="hero-in">
<h1><i class="fas fa-magnifying-glass-chart"></i> Domain &amp; IP Sorgulama</h1>
<p>RDAP üzerinden güncel kayıt verisi: statü, tarihler, abuse bilgileri ve ham JSON.</p>
</div></section>

<div class="searchwrap"><div class="searchcard">
<div class="searchrow">
<input id="q" type="text" placeholder="ornek.com veya 8.8.8.8 yazın…" autocomplete="off" spellcheck="false" maxlength="255">
<button class="btn" id="goBtn" onclick="doLookup()"><i class="fas fa-search"></i> Sorgula</button>
</div>
<?php if ($tsKey): ?>
<div class="tswrap"><div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($tsKey, ENT_QUOTES, 'UTF-8') ?>" data-theme="light"></div></div>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
</div>
<p class="hint"><i class="fas fa-bolt"></i> Dakikada en fazla 15 sorgu &middot; Veriler ICANN RDAP altyapısından canlı çekilir</p>
</div>

<div id="out"></div>

<footer class="footer">Veri kaynağı: <a href="https://www.icann.org/rdap" target="_blank" rel="noopener">ICANN RDAP</a> &middot; <a href="/">Vexaris</a></footer>

<script>
const $ = id => document.getElementById(id);
document.getElementById('q').addEventListener('keydown', e => { if (e.key === 'Enter') doLookup(); });
function esc(s){ return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fmtDate(iso){
    if(!iso) return '—';
    const d = new Date(iso);
    if(isNaN(d)) return esc(iso);
    return d.toLocaleDateString('tr-TR',{day:'2-digit',month:'2-digit',year:'numeric'}) + ' ' + d.toLocaleTimeString('tr-TR',{hour:'2-digit',minute:'2-digit'});
}
function daysOver(iso){
    if(!iso) return null;
    const diff = Date.now() - new Date(iso).getTime();
    return diff > 0 ? Math.floor(diff / 864e5) : null;
}
function evDate(d, key){
    const ev = (d.events || []).find(e => (e.eventAction || '').toLowerCase() === key);
    return ev ? ev.eventDate : null;
}
function vcardVal(entity, keys){
    try {
        const rows = (entity.vcardArray && entity.vcardArray[1]) || [];
        for (const r of rows) { if (keys.includes(r[0])) return r[3]; }
    } catch(e) {}
    return null;
}
function isRedacted(v){
    if(!v) return true;
    const s = String(v).toLowerCase();
    return s.includes('redact') || s.includes('privacy') || s.includes('whoisguard') || s.includes('withheld') || s.includes('hidden upon user request') || s === '';
}
function pill(txt, color){ return '<span class="pill pill-' + color + '">' + esc(txt) + '</span>'; }
function statusPills(list){
    if(!list || !list.length) return '<span style="color:var(--mut)">—</span>';
    return list.map(s => {
        const l = String(s).toLowerCase();
        let c = 'gray';
        if (l.includes('active') || l.includes('ok')) c = 'green';
        else if (l.includes('pending') || l.includes('redemption') || l.includes('quarantine')) c = 'amber';
        else if (l.includes('prohibit') || l.includes('hold') || l.includes('lock') || l.includes('clienttransfer')) c = 'red';
        else if (l.includes('add period') || l.includes('renew')) c = 'blue';
        return pill(s, c);
    }).join('');
}
function findEntity(d, role){
    return (d.entities || []).find(e => (e.roles || []).includes(role)) || null;
}
function abuseInfo(d){
    const ab = findEntity(d, 'abuse');
    if(!ab) return null;
    return { email: vcardVal(ab, ['email']), phone: vcardVal(ab, ['tel']) };
}
function copyRaw(){
    const el = document.getElementById('rawPre');
    const done = () => { const b = document.getElementById('copyBtn'); const o = b.innerHTML; b.innerHTML = '<i class="fas fa-check"></i> Kopyalandı'; setTimeout(() => b.innerHTML = o, 1500); };
    if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(el.textContent).then(done).catch(() => fallbackCopy(el, done)); }
    else fallbackCopy(el, done);
}
function fallbackCopy(el, done){
    const r = document.createRange(); r.selectNodeContents(el);
    const s = getSelection(); s.removeAllRanges(); s.addRange(r);
    try { document.execCommand('copy'); done(); } catch(e) {}
    s.removeAllRanges();
}
async function doLookup(){
    const q = $('q').value.trim();
    const out = $('out');
    if (!q) { out.innerHTML = '<div class="err"><i class="fas fa-circle-exclamation"></i> Sorgulanacak domain veya IP girin.</div>'; return; }
    const btn = $('goBtn');
    btn.disabled = true;
    out.innerHTML = '<div class="spin"><i class="fas fa-spinner"></i><div style="margin-top:8px;font-size:.85rem">RDAP sorgulanıyor…</div></div>';
    try {
        const fd = new FormData();
        fd.append('q', q);
        const ts = document.querySelector('[name="cf-turnstile-response"]');
        if (ts && ts.value) fd.append('cf-turnstile-response', ts.value);
        const r = await fetch('/api/whois-tool', { method: 'POST', body: fd });
        const d = await r.json();
        if (!d.ok) {
            out.innerHTML = '<div class="err"><i class="fas fa-circle-exclamation"></i> ' + esc(d.msg || 'Sorgu başarısız.') + '</div>';
            return;
        }
        if (d.available) { renderAvailable(out, d.query); return; }
        if (d.type === 'ip') renderIp(out, d.query, d.data);
        else renderDomain(out, d.query, d.data);
    } catch(e) {
        out.innerHTML = '<div class="err"><i class="fas fa-circle-exclamation"></i> Bağlantı hatası.</div>';
    } finally {
        btn.disabled = false;
        try { if (window.turnstile) window.turnstile.reset(); } catch(e2) {}
    }
}
function renderAvailable(out, q){
    out.innerHTML = '<div class="card avail"><div class="tick"><i class="fas fa-check"></i></div>'
        + '<h2>Tebrikler! Bu Domain Kaydedilebilir</h2>'
        + '<div class="qline">' + esc(q) + '</div>'
        + '<p>RDAP kaydına göre <b>' + esc(q) + '</b> şu an boştadır. Tescil için registrarınıza başvurun.</p></div>';
}
function cardHead(icon, title, sub){
    return '<div class="card"><h2><i class="fas ' + icon + '"></i> ' + esc(title) + '</h2>'
        + '<div class="qline">' + esc(sub) + ' <a href="#" onclick="copyQ();return false;" title="Kopyala" style="float:right;color:var(--brand)"><i class="fas fa-copy"></i></a></div>';
}
function copyQ(){
    const v = (document.getElementById('q') || {}).value || '';
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(v);
}
function renderDomain(out, q, d){
    const created = evDate(d, 'registration'), updated = evDate(d, 'last changed'),
          expiry = evDate(d, 'expiration'), transferred = evDate(d, 'transfer'),
          rdapUpd = evDate(d, 'last update of rdap database');
    const over = daysOver(expiry);
    const left = expiry && over === null ? Math.ceil((new Date(expiry).getTime() - Date.now()) / 864e5) : null;
    let h = cardHead('fa-globe', 'Domain Detayı', q);
    h += '<dl class="kv">';
    h += '<dt><i class="fas fa-flag"></i> Statü</dt><dd>' + statusPills(d.status) + '</dd>';
    if (d.port43) h += '<dt><i class="fas fa-terminal"></i> WHOIS Sunucusu</dt><dd>' + esc(d.port43) + '</dd>';
    h += '</dl></div>';
    // Zaman çizelgesi
    h += '<div class="card"><h2><i class="fas fa-timeline"></i> Zaman Çizelgesi</h2>';
    h += trow('fa-calendar-plus', 'Kayıt Tarihi', esc(fmtDate(created)));
    let expHtml = esc(fmtDate(expiry));
    if (over !== null) expHtml += ' <span class="overdue"><i class="fas fa-triangle-exclamation"></i> ' + over + ' gün geçmiş</span>';
    else if (left !== null) expHtml += ' <span class="pill pill-green"><i class="fas fa-hourglass-half"></i> ' + left + ' gün kaldı</span>';
    h += trow('fa-calendar-check', 'Bitiş Tarihi', expHtml);
    h += trow('fa-pen', 'Son Güncelleme', esc(fmtDate(updated)));
    if (transferred) h += trow('fa-right-left', 'Son Transfer', esc(fmtDate(transferred)));
    if (rdapUpd) h += trow('fa-database', 'RDAP Güncelleme', esc(fmtDate(rdapUpd)));
    h += '</div>';
    // Name serverlar
    const nsList = (d.nameservers || []);
    h += '<div class="card"><h2><i class="fas fa-server"></i> Name Serverlar <span class="pill pill-blue">' + nsList.length + '</span></h2>';
    if (nsList.length) {
        h += nsList.map(n => {
            const ips = (n.ipAddresses && (n.ipAddresses.v4 || n.ipAddresses.v6))
                ? [].concat(n.ipAddresses.v4 || [], n.ipAddresses.v6 || []).join('<br>') : '';
            return '<div class="nsrow"><code>' + esc(n.ldhName || n.unicodeName || '') + '</code>'
                + (ips ? '<span class="ips">' + esc(n.ipAddresses.v4 ? n.ipAddresses.v4.join(', ') : '') + (n.ipAddresses.v6 ? '<br>' + esc(n.ipAddresses.v6.join(', ')) : '') + '</span>' : '') + '</div>';
        }).join('');
    } else h += '<p style="color:var(--mut);font-size:.88rem">Kayıtlı name server yok.</p>';
    h += '</div>';
    // DNSSEC
    if (d.secureDNS) {
        h += '<div class="card"><h2><i class="fas fa-lock"></i> DNSSEC</h2>';
        h += trow('fa-shield-halved', 'Durum', d.secureDNS.delegationSigned ? '<span class="pill pill-green">İmzalı</span>' : '<span class="pill pill-gray">İmzasız</span>');
        (d.secureDNS.dsData || []).slice(0, 4).forEach(ds => {
            h += trow('fa-key', 'DS Kaydı #' + esc(ds.keyTag || '?'),
                'Algoritma: ' + esc(ds.algorithm || '?') + ' &middot; Özet: <span style="font-family:ui-monospace,Menlo,monospace;font-size:.78rem;word-break:break-all">' + esc((ds.digest || '').slice(0, 48)) + (String(ds.digest || '').length > 48 ? '…' : '') + '</span>');
        });
        h += '</div>';
    }
    // Kayıt firması + kişiler
    const reg = findEntity(d, 'registrar');
    const regName = vcardVal(reg, ['fn', 'org']);
    let regId = '';
    try {
        const ids = reg.publicIds || [];
        if (ids.length) regId = ids[0].identifier || '';
    } catch(e) {}
    h += '<div class="card"><h2><i class="fas fa-building"></i> Kayıt Firması &amp; İletişim</h2>';
    const regTel = vcardVal(reg, ['tel']);
    h += trow('fa-building-columns', 'Kayıt Firması', esc(regName || '—') + (regId ? ' <span class="pill pill-blue">IANA #' + esc(regId) + '</span>' : '')
        + (regTel && !isRedacted(regTel) ? '<br><a href="tel:' + esc(regTel.replace(/\s/g, '')) + '"><i class="fas fa-phone"></i> ' + esc(regTel) + '</a>' : ''));
    const ab = abuseInfo(d);
    if (ab && (ab.email || ab.phone)) {
        h += trow('fa-headset', 'Abuse İletişim',
            (ab.email ? '<a href="mailto:' + esc(ab.email) + '">' + esc(ab.email) + '</a>' : '')
            + (ab.email && ab.phone ? '<br>' : '') + (ab.phone ? esc(ab.phone) : ''));
    }
    const reseller = findEntity(d, 'reseller');
    const resName = vcardVal(reseller, ['fn', 'org']);
    if (resName && !isRedacted(resName)) h += trow('fa-store', 'Satıcı (Reseller)', esc(resName));
    [['registrant', 'fa-user', 'Kaydeden'], ['admin', 'fa-user-shield', 'Admin İletişim'], ['tech', 'fa-user-gear', 'Teknik İletişim'], ['billing', 'fa-receipt', 'Fatura İletişim']].forEach(([role, icon, label]) => {
        const ent = findEntity(d, role);
        if (!ent) return;
        const nm = vcardVal(ent, ['fn', 'org']);
        const em = vcardVal(ent, ['email']);
        let v = isRedacted(nm) ? '<span class="pill pill-gray"><i class="fas fa-eye-slash"></i> Gizli</span>' : esc(nm);
        if (em && !isRedacted(em)) v += '<br><a href="mailto:' + esc(em) + '">' + esc(em) + '</a>';
        h += trow(icon, label, v);
    });
    h += '</div>';
    h += rawCard(d);
    out.innerHTML = h;
}
function trow(icon, label, valueHtml){
    return '<div class="trow"><div class="ic"><i class="fas ' + icon + '"></i></div>'
        + '<div class="tx"><div class="lb">' + esc(label) + '</div><div class="vl">' + valueHtml + '</div></div></div>';
}
function renderIp(out, q, d){
    let h = cardHead('fa-network-wired', 'IP Detayı', q);
    h += '<dl class="kv">';
    h += '<dt><i class="fas fa-flag"></i> Statü</dt><dd>' + statusPills(d.status) + '</dd>';
    h += '<dt><i class="fas fa-diagram-project"></i> Blok</dt><dd>' + esc(d.handle || d.cidr || q) + '</dd>';
    h += '<dt><i class="fas fa-signature"></i> Ağ Adı</dt><dd>' + esc(d.name || '—') + '</dd>';
    h += '<dt><i class="fas fa-globe"></i> Ülke</dt><dd>' + esc(d.country || '—') + '</dd>';
    const ent = (d.entities || []).slice(0, 4).map(e => {
        const nm = vcardVal(e, ['fn', 'org']) || (e.handle || '');
        const rl = (e.roles || []).join(', ');
        return esc(nm) + (rl ? ' <span style="color:var(--mut)">(' + esc(rl) + ')</span>' : '');
    });
    if (ent.length) h += '<dt><i class="fas fa-building"></i> Kuruluş</dt><dd>' + ent.join('<br>') + '</dd>';
    h += '</dl>';
    const ab = abuseInfo(d);
    if (ab && (ab.email || ab.phone)) {
        h += '<div class="abusebox"><i class="fas fa-headset"></i> <b>Abuse:</b> '
            + (ab.email ? '<a href="mailto:' + esc(ab.email) + '">' + esc(ab.email) + '</a> ' : '')
            + (ab.phone ? esc(ab.phone) : '') + '</div>';
    }
    h += '</div>';
    h += rawCard(d);
    out.innerHTML = h;
}
function rawCard(d){
    return '<div class="card"><div class="rawhead"><h2><i class="fas fa-code"></i> Ham JSON</h2>'
        + '<button class="copybtn" id="copyBtn" onclick="copyRaw()"><i class="fas fa-copy"></i> Kopyala</button></div>'
        + '<pre class="raw" id="rawPre">' + esc(JSON.stringify(d, null, 2)) + '</pre></div>';
}
</script>
</body>
</html>

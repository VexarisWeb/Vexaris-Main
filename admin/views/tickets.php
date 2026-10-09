<?php
$usersMap = [];
if ($pdo) {
    $stU = $pdo->query("SELECT id, username FROM users");
    while ($r = $stU->fetch()) $usersMap[(int)$r['id']] = $r['username'];
}
?>
<!-- DESTEK TALEPLERİ -->
<div class="ticket-wrap">
    <div class="ticket-side">
        <div class="ticket-side__head">
            <div class="ticket-side__title"><i class="fas fa-ticket"></i> Destek Talepleri</div>
            <div style="display:flex;gap:8px;align-items:center">
                <span class="ticket-side__count" id="ticketCount">0</span>
                <button class="ticket-new-btn" onclick="openAdminCreate()" title="Kullanıcı için oda aç" style="padding:6px 10px;font-size:.78rem"><i class="fas fa-plus"></i></button>
            </div>
        </div>
        <div class="ticket-side__search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="text" id="ticketSearch" placeholder="Konu veya kullanıcı ara..." oninput="ticketSearchChanged()">
        </div>
        <div id="ticketTabs" style="display:flex;gap:6px;padding:.6rem 1.2rem 0">
            <button class="ticket-tabbtn on" data-f="all" onclick="setTicketFilter('all')">Tümü</button>
            <button class="ticket-tabbtn" data-f="support" onclick="setTicketFilter('support')">Destek</button>
            <button class="ticket-tabbtn" data-f="application" onclick="setTicketFilter('application')">Başvuru Mesajları</button>
        </div>
        <style>.ticket-tabbtn{flex:1;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:8px;color:var(--muted);font-size:.72rem;font-weight:700;padding:.45rem .2rem;cursor:pointer;font-family:inherit;transition:all .2s;white-space:nowrap}.ticket-tabbtn.on{background:rgba(79,143,192,.14);border-color:rgba(79,143,192,.45);color:#fff}.ticket-tabbtn:hover{color:#fff}</style>
        <style>
        .msg-status{font-size:.66rem;font-weight:700;margin-left:6px;white-space:nowrap}
        .msg-status.tick{color:rgba(255,255,255,.45)}
        .msg-status.seen{color:#74c091;animation:seenPop .4s ease}
        @keyframes seenPop{0%{transform:scale(.6);opacity:0}60%{transform:scale(1.15)}100%{transform:scale(1);opacity:1}}
        .typing-bar{display:none;align-items:center;gap:.5rem;padding:.35rem 1.4rem;font-size:.75rem;color:var(--muted)}
        .typing-bar.on{display:flex}
        .typing-dots{display:inline-flex;gap:3px}
        .typing-dots span{width:6px;height:6px;border-radius:50%;background:#7fb3d9;animation:tblink 1.2s infinite}
        .typing-dots span:nth-child(2){animation-delay:.2s}
        .typing-dots span:nth-child(3){animation-delay:.4s}
        @keyframes tblink{0%,60%,100%{opacity:.25;transform:translateY(0)}30%{opacity:1;transform:translateY(-3px)}}
        .msg-pending{opacity:.65}
        </style>
        <div class="ticket-list" id="ticketList">
            <div class="ticket-empty"><i class="fas fa-spinner fa-spin"></i> Yükleniyor...</div>
        </div>
    </div>

    <div class="ticket-chat">
        <div class="ticket-chat__empty" id="chatEmpty">
            <i class="fas fa-comments"></i>
            <p>Bir talep seçerek mesajları görüntüleyin.</p>
        </div>

        <div id="chatPanel" style="display:none">
            <div class="ticket-chat__head" id="chatHead">
                <div class="ticket-chat__user">
                    <div class="ticket-avatar" id="chatAvatar">?</div>
                    <div>
                        <div class="ticket-chat__name" id="chatName">—</div>
                        <div class="ticket-chat__sub" id="chatMeta">—</div>
                    </div>
                </div>
                <div class="ticket-chat__actions">
                    <button class="ticket-btn" id="closeTicketBtn" onclick="closeTicketAdmin()" title="Talebi kapat"><i class="fas fa-lock"></i></button>
                    <button class="ticket-btn" id="deleteTicketBtn" onclick="deleteTicketAdmin()" title="Talebi sil"><i class="fas fa-trash"></i></button>
                    <button class="ticket-btn" onclick="closeChat()" title="Listeye dön"><i class="fas fa-xmark"></i></button>
                </div>
            </div>

            <div class="ticket-msgs" id="ticketMsgs">
                <div class="ticket-empty">Yükleniyor...</div>
            </div>
            <div class="typing-bar" id="typingBar"><span class="typing-dots"><span></span><span></span><span></span></span> Kullanıcı yazıyor...</div>

            <div class="ticket-send" id="ticketSend">
                <input type="file" id="ticketImgInputA" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none" onchange="ticketImgChangedA()">
                <button class="ticket-send__btn" style="background:rgba(255,255,255,.05);border:1px solid var(--border)" onclick="document.getElementById('ticketImgInputA').click()" title="Fotoğraf ekle"><i class="fas fa-paperclip"></i></button>
                <div style="flex:1;min-width:0">
                    <div id="ticketImgNameA" style="display:none;font-size:.72rem;color:var(--cyan);margin-bottom:.35rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></div>
                    <textarea id="ticketMsgInput" rows="1" maxlength="500" placeholder="Mesajınızı yazın... (en fazla 500 karakter)" onkeydown="ticketInputKey(event)" oninput="typingPingA()" style="width:100%"></textarea>
                </div>
                <button class="ticket-send__btn" onclick="sendTicketMsg()"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
</div>

<div id="vxLightbox" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;align-items:center;justify-content:center;padding:1rem;cursor:zoom-out" onclick="this.style.display='none'">
    <img id="vxLightboxImg" src="" alt="Büyütülmüş fotoğraf" style="max-width:92vw;max-height:88vh;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.7)">
</div>

<script>
const USERS_MAP = <?= json_encode($usersMap) ?>;
function vxZoom(a){ document.getElementById('vxLightboxImg').src = a.href; document.getElementById('vxLightbox').style.display = 'flex'; return false; }
let ticketList   = [];
let currentTicket = null;
let ticketSearch  = '';
let ticketTimer   = null;
let ticketLastMsg = 0;
let ticketPoll    = null;

function ticketStatusBadge(s) {
    if (s === 'closed') return '<span class="badge badge-rev"><i class="fas fa-lock"></i> Kapalı</span>';
    if (s === 'answered') return '<span class="badge badge-active"><i class="fas fa-reply"></i> Cevaplandı</span>';
    return '<span class="badge badge-susp"><i class="fas fa-clock"></i> Açık</span>';
}

function ticketTime(str) {
    if (!str) return '';
    const d = new Date(String(str).replace(' ', 'T'));
    const now = new Date();
    const sameDay = d.toDateString() === now.toDateString();
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    if (sameDay) return hh + ':' + mm;
    const dd = String(d.getDate()).padStart(2, '0');
    const mo = String(d.getMonth() + 1).padStart(2, '0');
    return dd + '.' + mo + ' ' + hh + ':' + mm;
}

function truncate(s, n) {
    s = String(s || '');
    return s.length > n ? s.slice(0, n) + '…' : s;
}

function avatarLetter(name) {
    return (String(name || '?')).charAt(0).toUpperCase();
}

let ticketFilter = 'all';
function setTicketFilter(f) {
    ticketFilter = f;
    document.querySelectorAll('#ticketTabs .ticket-tabbtn').forEach(b => b.classList.toggle('on', b.getAttribute('data-f') === f));
    renderTicketList();
}
function ticketSource(t) { return t.source || 'support'; }
function renderTicketList() {
    const el = document.getElementById('ticketList');
    const q = ticketSearch.trim().toLowerCase();
    const items = ticketList.filter(t => {
        if (ticketFilter !== 'all' && ticketSource(t) !== ticketFilter) return false;
        if (!q) return true;
        const name = (USERS_MAP[t.user_id] || '') + ' ' + t.subject;
        return name.toLowerCase().indexOf(q) !== -1;
    });

    document.getElementById('ticketCount').textContent = ticketList.length;

    if (!items.length) {
        el.innerHTML = '<div class="ticket-empty"><i class="fas fa-ticket"></i>' + (ticketList.length ? 'Arama sonucu yok.' : 'Henüz destek talebi yok.') + '</div>';
        return;
    }

    el.innerHTML = items.map(t => {
        const isActive = currentTicket && currentTicket.id == t.id;
        const unread = parseInt(t.unread || 0);
        return '<div class="ticket-item' + (isActive ? ' active' : '') + (unread > 0 ? ' unread' : '') + '" onclick="openTicket(' + t.id + ')" id="trow-' + t.id + '">'
            + '<div class="ticket-avatar">' + avatarLetter(USERS_MAP[t.user_id]) + '</div>'
            + '<div class="ticket-item__body">'
            + '<div class="ticket-item__top">'
            + '<span class="ticket-item__name">' + esc(USERS_MAP[t.user_id] || '—') + '</span>'
            + '<span class="ticket-item__time">' + ticketTime(t.last_msg_at) + '</span>'
            + '</div>'
            + '<div class="ticket-item__subj">' + esc(t.subject) + '</div>'
            + '<div class="ticket-item__bottom">'
            + '<span class="ticket-item__last">' + truncate(t.last_msg, 40) + '</span>'
            + (ticketSource(t) === 'application' ? '<span class="badge" style="background:rgba(79,143,192,.14);color:#7fb3d9;border:1px solid rgba(79,143,192,.4)">Başvuru</span>' : '')
            + ticketStatusBadge(t.status)
            + (unread > 0 ? '<span class="ticket-badge">' + unread + '</span>' : '')
            + '</div>'
            + '</div>'
            + '</div>';
    }).join('');
}

async function loadTickets() {
    try {
        const res = await fetch('/api/support?action=list');
        const d = await res.json();
        if (!d.ok) { renderTicketList(); return; }
        ticketList = d.tickets || [];
        renderTicketList();
        if (currentTicket) {
            const still = ticketList.find(t => t.id == currentTicket.id);
            if (!still) { closeChat(); }
            else { currentTicket = still; renderChatHead(); }
        }
    } catch(e) { /* sessiz */ }
}

function ticketSearchChanged() {
    clearTimeout(ticketTimer);
    ticketTimer = setTimeout(() => { ticketSearch = document.getElementById('ticketSearch').value; renderTicketList(); }, 250);
}

async function openTicket(id) {
    currentTicket = ticketList.find(t => t.id == id) || { id: id, subject: 'Talep #' + id, status: 'open' };
    document.getElementById('chatEmpty').style.display = 'none';
    document.getElementById('chatPanel').style.display = 'flex';
    renderTicketList();
    renderChatHead();
    loadMessages(id);
    startPolling();
}

function closeChat() {
    currentTicket = null;
    stopPolling();
    document.getElementById('chatEmpty').style.display = '';
    document.getElementById('chatPanel').style.display = 'none';
    renderTicketList();
}

function renderChatHead() {
    const t = currentTicket;
    const name = USERS_MAP[t.user_id] || 'Kullanıcı #' + t.user_id;
    document.getElementById('chatAvatar').textContent = avatarLetter(name);
    document.getElementById('chatName').textContent = name;
    document.getElementById('chatMeta').innerHTML = ticketStatusBadge(t.status);
    const cb = document.getElementById('closeTicketBtn');
    if (cb) cb.style.display = (t.status === 'closed') ? 'none' : 'inline-flex';
}
function escMsg(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

let lastMsgHashA = '';
function renderMessages(msgs) {
    const el = document.getElementById('ticketMsgs');
    if (!msgs.length) {
        if (lastMsgHashA !== 'empty') { el.innerHTML = '<div class="ticket-empty"><i class="fas fa-comment-slash"></i> Henüz mesaj yok.</div>'; lastMsgHashA = 'empty'; }
        return;
    }
    const hash = msgs.length + ':' + (msgs[msgs.length-1]?.id || 0);
    if (hash === lastMsgHashA) return;
    lastMsgHashA = hash;
    const atBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 40;
    let lastOwnIdx = -1;
    for (let i = msgs.length - 1; i >= 0; i--) { if (msgs[i].sender_role === 'admin') { lastOwnIdx = i; break; } }
    el.innerHTML = msgs.map((m, idx) => {
        const isAdmin = m.sender_role === 'admin';
        let status = '';
        if (isAdmin && idx === lastOwnIdx) {
            status = (parseInt(m.is_read || 0) === 1)
                ? '<span class="msg-status seen">✓✓ Görüldü</span>'
                : '<span class="msg-status tick">✓</span>';
        }
        return '<div class="msg-row ' + (isAdmin ? 'admin' : 'user') + '">'
            + '<div class="msg-bubble">'
            + (m.attachment ? '<a href="' + escMsg(m.attachment) + '" onclick="return vxZoom(this)"><img src="' + escMsg(m.attachment) + '" loading="lazy" alt="Ekli fotoğraf" class="msg-img"></a>' : '')
            + (m.message ? '<div class="msg-text">' + escMsg(m.message) + '</div>' : '')
            + '<div class="msg-time" title="' + escMsg(m.created_at) + '">' + (isAdmin ? '<i class="fas fa-shield-halved"></i> ' : '') + esc(m.username) + ' &middot; ' + ticketTime(m.created_at) + status + '</div>'
            + '</div>'
            + '</div>';
    }).join('');
    if (atBottom) el.scrollTop = el.scrollHeight;
    if (msgs.length) ticketLastMsg = msgs[msgs.length - 1].id;
}

async function loadMessages(id, silent) {
    try {
        const res = await fetch('/api/support?action=messages&ticket_id=' + id);
        const d = await res.json();
        if (!d.ok) return;
        renderMessages(d.messages || []);
        const tb = document.getElementById('typingBar');
        if (tb) tb.classList.toggle('on', !!d.typing);
        if (d.ticket && currentTicket && currentTicket.id == id) {
            currentTicket.status = d.ticket.status;
            renderChatHead();
        }
async function deleteTicketAdmin(){
    if(!currentTicket) return;
    if(!confirm('Bu talep ve TÜM mesajları kalıcı olarak silinecek. Emin misin?')) return;
    const fd=new FormData(); fd.append('action','delete'); fd.append('ticket_id', currentTicket.id);
    try{
        const r=await fetch('/api/support',{method:'POST',headers:{'X-CSRF-Token':VX_CSRF},body:fd});
        const d=await r.json();
        alert(d.msg|| (d.ok?'Silindi':'Hata'));
        if(d.ok){ closeChat(); loadTickets(); }
    }catch(e){ alert('Bağlantı hatası.'); }
}
loadTickets();
    } catch(e) { /* sessiz */ }
}

function startPolling() {
    stopPolling();
    ticketPoll = setInterval(() => {
        if (currentTicket) loadMessages(currentTicket.id, true);
    }, 2000);
}

function stopPolling() {
    if (ticketPoll) { clearInterval(ticketPoll); ticketPoll = null; }
}

function ticketImgChangedA(){
    const f = document.getElementById('ticketImgInputA').files[0];
    const el = document.getElementById('ticketImgNameA');
    if (!f) { el.style.display = 'none'; el.textContent = ''; return; }
    if (f.size > 5 * 1024 * 1024) { alert('Fotoğraf en fazla 5 MB olabilir.'); document.getElementById('ticketImgInputA').value = ''; el.style.display = 'none'; return; }
    el.style.display = 'block';
    el.textContent = '📎 ' + f.name;
}
let typingPingAtA = 0;
function typingPingA(){
    if (!currentTicket) return;
    const now = Date.now();
    if (now - typingPingAtA < 2500) return;
    typingPingAtA = now;
    try {
        const fd = new FormData();
        fd.append('action', 'typing');
        fd.append('ticket_id', currentTicket.id);
        fetch('/api/support', { method: 'POST', headers: { 'X-CSRF-Token': VX_CSRF }, body: fd });
    } catch(e) {}
}
async function sendTicketMsg() {
    if (!currentTicket) return;
    const input = document.getElementById('ticketMsgInput');
    const text = input.value.trim();
    const imgInput = document.getElementById('ticketImgInputA');
    const hasImg = imgInput.files.length > 0;
    if (!text && !hasImg) return;
    if (text.length > 500) { alert('Mesaj en fazla 500 karakter olabilir.'); return; }
    input.value = '';
    try {
        const el = document.getElementById('ticketMsgs');
        const tmp = document.createElement('div');
        tmp.id = 'tmpMsg';
        tmp.className = 'msg-row admin';
        tmp.innerHTML = '<div class="msg-bubble msg-pending">'
            + (hasImg ? '<div class="msg-text" style="opacity:.7">📎 Fotoğraf yükleniyor...</div>' : '')
            + (text ? '<div class="msg-text">' + escMsg(text) + '</div>' : '')
            + '<div class="msg-time">şimdi <span class="msg-status tick">⏳</span></div></div>';
        const empty = el.querySelector('.ticket-empty');
        if (empty) el.innerHTML = '';
        el.appendChild(tmp);
        el.scrollTop = el.scrollHeight;
    } catch(e) {}
    const fd = new FormData();
    fd.append('action', 'send');
    fd.append('ticket_id', currentTicket.id);
    fd.append('message', text);
    if (hasImg) fd.append('image', imgInput.files[0]);
    imgInput.value = '';
    document.getElementById('ticketImgNameA').style.display = 'none';
    try {
        const res = await fetch('/api/support', { method: 'POST', headers: { 'X-CSRF-Token': VX_CSRF }, body: fd });
        const d = await res.json();
        const tmp = document.getElementById('tmpMsg');
        if (tmp) tmp.remove();
        if (d.ok) {
            loadMessages(currentTicket.id);
        } else {
            alert(d.msg);
        }
    } catch(e) {
        const tmp = document.getElementById('tmpMsg');
        if (tmp) tmp.remove();
        alert('Bağlantı hatası.');
    }
}

function ticketInputKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendTicketMsg();
    }
}

function openAdminCreate(){
    const id = prompt('Kullanıcı ID gir (oda bu kullanıcı adına açılır):');
    if(!id) return;
    const subj = prompt('Konu:','Destek Talebi');
    if(subj===null) return;
    const msg = prompt('İlk mesaj:','Merhaba, size nasıl yardımcı olabilirim?');
    if(msg===null||!msg.trim()) return;
    const fd=new FormData(); fd.append('action','create'); fd.append('user_id',id); fd.append('subject',subj); fd.append('message',msg);
    fetch('/api/support',{method:'POST',headers:{'X-CSRF-Token':VX_CSRF},body:fd}).then(r=>r.json()).then(d=>{ alert(d.msg|| (d.ok?'Oluşturuldu':'Hata')); if(d.ok) loadTickets(); });
}
async function closeTicketAdmin(){
    if(!currentTicket) return;
    if(currentTicket.status === 'closed'){ alert('Zaten kapalı.'); return; }
    if(!confirm('Bu talebi kapatmak istiyor musun?')) return;
    const fd=new FormData(); fd.append('action','close'); fd.append('ticket_id', currentTicket.id);
    try{
        const r=await fetch('/api/support',{method:'POST',headers:{'X-CSRF-Token':VX_CSRF},body:fd});
        const d=await r.json();
        alert(d.msg|| (d.ok?'Kapatıldı':'Hata'));
        if(d.ok){ loadMessages(currentTicket.id); loadTickets(); }
    }catch(e){ alert('Bağlantı hatası.'); }
}
loadTickets();
setInterval(() => { if (!currentTicket) loadTickets(); }, 8000);
</script>
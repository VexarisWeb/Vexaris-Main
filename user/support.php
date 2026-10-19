<?php
$activePage = 'support';
require_once __DIR__ . '/_inc/header.php';
?>
<div class="main__header" style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem">
    <div>
        <h1>My Support Tickets</h1>
        <p>Chat live with our support team about your technical issues.</p>
    </div>
    <button class="ticket-new-btn" onclick="openNewTicket()"><i class="fas fa-plus"></i> New Ticket</button>
</div>

<div class="ticket-wrap">
    <div class="ticket-side">
        <div class="ticket-side__head">
            <div class="ticket-side__title"><i class="fas fa-ticket"></i> Taleplerim</div>
            <span class="ticket-side__count" id="ticketCount">0</span>
        </div>
        <div class="ticket-list" id="ticketList">
            <div class="ticket-empty"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>
    </div>

    <div class="ticket-chat">
        <div class="ticket-chat__empty" id="chatEmpty">
            <i class="fas fa-comments"></i>
            <p>Select a ticket to start chatting.<br>Use the "New Ticket" button for a new issue.</p>
        </div>

        <div id="chatPanel" style="display:none">
            <div class="ticket-chat__head">
                <div class="ticket-chat__user">
                    <div class="ticket-avatar" id="chatAvatar">?</div>
                    <div>
                        <div class="ticket-chat__name" id="chatName">—</div>
                        <div class="ticket-chat__sub" id="chatMeta">—</div>
                    </div>
                </div>
                <div style="display:flex;gap:8px">
                    <button id="closeTicketBtn" class="btn-ghost" style="padding:6px 12px;font-size:.75rem;display:none" onclick="closeTicket()"><i class="fas fa-lock"></i> Kapat</button>
                    <button class="ticket-new-btn" style="padding:6px 12px;font-size:.75rem" onclick="openNewTicket()"><i class="fas fa-plus"></i> New Ticket</button>
                </div>
            </div>

            <div class="ticket-msgs" id="ticketMsgs">
                <div class="ticket-empty">Loading...</div>
            </div>
            <div class="typing-bar" id="typingBar"><span class="typing-dots"><span></span><span></span><span></span></span> Support is typing...</div>

            <div class="ticket-send" id="ticketSend">
                <input type="file" id="ticketImgInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none" onchange="ticketImgChanged()">
                <button class="ticket-send__btn" style="background:#101317;border:1px solid var(--border)" onclick="document.getElementById('ticketImgInput').click()" title="Attach photo"><i class="fas fa-paperclip"></i></button>
                <div style="flex:1;min-width:0">
                    <div id="ticketImgName" style="display:none;font-size:.72rem;color:var(--cyan);margin-bottom:.35rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></div>
                    <textarea id="ticketMsgInput" rows="1" maxlength="500" placeholder="Type your message... (max 500 characters)" onkeydown="ticketInputKey(event)" oninput="typingPing()" style="width:100%"></textarea>
                </div>
                <button class="ticket-send__btn" onclick="sendTicketMsg()"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
</div>

<div id="vxLightbox" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;align-items:center;justify-content:center;padding:1rem;cursor:zoom-out" onclick="this.style.display='none'">
    <img id="vxLightboxImg" src="" alt="Enlarged photo" style="max-width:92vw;max-height:88vh;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.7)">
</div>
<div class="modal-new" id="newTicketModal">
    <div class="modal-new__box">
        <div class="modal-new__title"><i class="fas fa-headset"></i> New Support Ticket<button class="modal-new__close" onclick="closeNewTicket()" aria-label="Kapat">&times;</button></div>
        <label>Subject</label>
        <input type="text" id="newTicketSubject" placeholder="E.g.: I have an installation issue" maxlength="190">
        <label>Your message</label>
        <textarea id="newTicketMsg" placeholder="Describe your issue in detail..."></textarea>
        <div id="newTicketErr" style="font-size:.8rem;color:#ff7070;display:none;margin-bottom:.8rem"></div>
        <div class="modal-new__actions">
            <button class="ticket-new-btn" style="flex:1" onclick="submitNewTicket()"><i class="fas fa-paper-plane"></i> Send</button>
            <button class="btn-ghost" style="flex:1;text-align:center" onclick="closeNewTicket()">Cancel</button>
        </div>
    </div>
</div>

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
<script>
const VX_CSRF = <?= json_encode(csrf_token()) ?>;
function vxZoom(a){ document.getElementById('vxLightboxImg').src = a.href; document.getElementById('vxLightbox').style.display = 'flex'; return false; }
let ticketList   = [];
let currentTicket = null;
let ticketPoll    = null;

function ticketStatusBadge(s) {
    if (s === 'closed') return '<span class="badge badge-rejected"><i class="fas fa-lock"></i> Closed</span>';
    if (s === 'answered') return '<span class="badge badge-approved"><i class="fas fa-reply"></i> Answered</span>';
    return '<span class="badge badge-pending"><i class="fas fa-clock"></i> Open</span>';
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

function escMsg(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function renderTicketList() {
    const el = document.getElementById('ticketList');
    document.getElementById('ticketCount').textContent = ticketList.length;
    if (!ticketList.length) {
        el.innerHTML = '<div class="ticket-empty"><i class="fas fa-ticket"></i>You have no support tickets yet.<br><br><button class="ticket-new-btn" onclick="openNewTicket()"><i class="fas fa-plus"></i> Create New Ticket</button></div>';
        return;
    }
    el.innerHTML = ticketList.map(t => {
        const isActive = currentTicket && currentTicket.id == t.id;
        const unread = parseInt(t.unread || 0);
        return '<div class="ticket-item' + (isActive ? ' active' : '') + (unread > 0 ? ' unread' : '') + '" onclick="openTicket(' + t.id + ')">'
            + '<div class="ticket-avatar">' + escMsg((t.subject || '?').charAt(0)) + '</div>'
            + '<div class="ticket-item__body">'
            + '<div class="ticket-item__top">'
            + '<span class="ticket-item__name">' + escMsg(t.subject) + '</span>'
            + '<span class="ticket-item__time">' + ticketTime(t.last_msg_at) + '</span>'
            + '</div>'
            + '<div class="ticket-item__bottom">'
            + '<span class="ticket-item__last">' + escMsg(truncate(t.last_msg, 44)) + '</span>'
            + ((t.source || 'support') === 'application' ? '<span class="badge" style="background:rgba(79,143,192,.14);color:#7fb3d9;border:1px solid rgba(79,143,192,.4)">Application</span>' : '')
            + ticketStatusBadge(t.status)
            + (unread > 0 ? '<span class="ticket-badge">' + unread + '</span>' : '')
            + '</div>'
            + '</div>'
            + '</div>';
    }).join('');
}

async function loadTickets() {
    try {
        const res = await fetch('/api/support?action=list&own=1');
        const d = await res.json();
        if (!d.ok) { renderTicketList(); return; }
        ticketList = d.tickets || [];
        renderTicketList();
        const hasOpen = ticketList.some(t => t.status !== 'closed');
        document.querySelectorAll('.ticket-new-btn').forEach(b => {
            if (b.id === 'closeTicketBtn') return;
            b.disabled = hasOpen;
            b.style.opacity = hasOpen ? '.45' : '';
            b.style.pointerEvents = hasOpen ? 'none' : '';
            b.title = hasOpen ? 'You already have an open ticket' : '';
        });
        if (currentTicket) {
            const still = ticketList.find(t => t.id == currentTicket.id);
            if (!still) { closeChat(); }
        }
    } catch(e) {}
}

async function openTicket(id) {
    currentTicket = ticketList.find(t => t.id == id) || { id: id, subject: 'Ticket #' + id, status: 'open' };
    document.getElementById('chatEmpty').style.display = 'none';
    document.getElementById('chatPanel').style.display = 'flex';
    renderTicketList();
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

let lastMsgHash = '';
function renderMessages(msgs) {
    const el = document.getElementById('ticketMsgs');
    if (!msgs.length) {
        if (lastMsgHash !== 'empty') { el.innerHTML = '<div class="ticket-empty"><i class="fas fa-comment-slash"></i> No messages yet.</div>'; lastMsgHash = 'empty'; }
        return;
    }
    const hash = msgs.length + ':' + (msgs[msgs.length-1]?.id || 0) + ':' + (msgs[msgs.length-1]?.message?.length || 0);
    if (hash === lastMsgHash) return;
    lastMsgHash = hash;
    const atBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 40;
    let lastOwnIdx = -1;
    for (let i = msgs.length - 1; i >= 0; i--) { if (msgs[i].sender_role === 'user') { lastOwnIdx = i; break; } }
    el.innerHTML = msgs.map((m, idx) => {
        const isUser = m.sender_role === 'user';
        let status = '';
        if (isUser && idx === lastOwnIdx) {
            status = (parseInt(m.is_read || 0) === 1)
                ? '<span class="msg-status seen">✓✓ Seen</span>'
                : '<span class="msg-status tick">✓</span>';
        }
        return '<div class="msg-row ' + (isUser ? 'user' : 'admin') + '">'
            + '<div class="msg-bubble">'
            + (m.attachment ? '<a href="' + escMsg(m.attachment) + '" onclick="return vxZoom(this)"><img src="' + escMsg(m.attachment) + '" loading="lazy" alt="Attached photo" class="msg-img"></a>' : '')
            + (m.message ? '<div class="msg-text">' + escMsg(m.message) + '</div>' : '')
            + '<div class="msg-time" title="' + escMsg(m.created_at) + '">' + ticketTime(m.created_at) + (isUser ? ' &middot; You' : ' &middot; <i class="fas fa-shield-halved"></i> Destek') + status + '</div>'
            + '</div>'
            + '</div>';
    }).join('');
    if (atBottom) el.scrollTop = el.scrollHeight;
}

async function loadMessages(id, silent) {
    try {
        const res = await fetch('/api/support?action=messages&ticket_id=' + id + '&own=1');
        const d = await res.json();
        if (!d.ok) return;
        renderMessages(d.messages || []);
        const tb = document.getElementById('typingBar');
        if (tb) tb.classList.toggle('on', !!d.typing);
        if (d.ticket && currentTicket && currentTicket.id == id) {
            currentTicket.status = d.ticket.status;
            document.getElementById('chatName').textContent = currentTicket.subject;
            document.getElementById('chatMeta').innerHTML = ticketStatusBadge(d.ticket.status);
            document.getElementById('chatAvatar').textContent = (currentTicket.subject || '?').charAt(0);
            const c = document.getElementById('closeTicketBtn');
            if (c) c.style.display = d.ticket.status === 'closed' ? 'none' : 'inline-flex';
            document.getElementById('ticketSend').style.display = d.ticket.status === 'closed' ? 'none' : 'flex';
        }
        loadTickets();
    } catch(e) {}
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

function ticketImgChanged(){
    const f = document.getElementById('ticketImgInput').files[0];
    const el = document.getElementById('ticketImgName');
    if (!f) { el.style.display = 'none'; el.textContent = ''; return; }
    if (f.size > 5 * 1024 * 1024) { alert('Photos can be at most 5 MB.'); document.getElementById('ticketImgInput').value = ''; el.style.display = 'none'; return; }
    el.style.display = 'block';
    el.textContent = '📎 ' + f.name;
}
let typingPingAt = 0;
function typingPing(){
    if (!currentTicket) return;
    const now = Date.now();
    if (now - typingPingAt < 2500) return;
    typingPingAt = now;
    try {
        const fd = new FormData();
        fd.append('action', 'typing');
        fd.append('own', '1');
        fd.append('ticket_id', currentTicket.id);
        fetch('/api/support', { method: 'POST', headers: { 'X-CSRF-Token': VX_CSRF }, body: fd });
    } catch(e) {}
}
async function sendTicketMsg() {
    if (!currentTicket) return;
    const input = document.getElementById('ticketMsgInput');
    const text = input.value.trim();
    const imgInput = document.getElementById('ticketImgInput');
    const hasImg = imgInput.files.length > 0;
    if (!text && !hasImg) return;
    if (text.length > 500) { alert('Messages can be at most 500 characters.'); return; }
    input.value = '';
    // Optimistik: mesaj anında ekrana düşer, sunucu onayı sonra gelir
    try {
        const el = document.getElementById('ticketMsgs');
        const tmp = document.createElement('div');
        tmp.id = 'tmpMsg';
        tmp.className = 'msg-row user';
        tmp.innerHTML = '<div class="msg-bubble msg-pending">'
            + (hasImg ? '<div class="msg-text" style="opacity:.7">📎 Uploading photo...</div>' : '')
            + (text ? '<div class="msg-text">' + escMsg(text) + '</div>' : '')
            + '<div class="msg-time">now &middot; You <span class="msg-status tick">⏳</span></div></div>';
        const empty = el.querySelector('.ticket-empty');
        if (empty) el.innerHTML = '';
        el.appendChild(tmp);
        el.scrollTop = el.scrollHeight;
    } catch(e) {}
    const fd = new FormData();
    fd.append('action', 'send');
    fd.append('own', '1');
    fd.append('ticket_id', currentTicket.id);
    fd.append('message', text);
    if (hasImg) fd.append('image', imgInput.files[0]);
    imgInput.value = '';
    document.getElementById('ticketImgName').style.display = 'none';
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
        alert('Connection error.');
    }
}

async function closeTicket(){
    if(!currentTicket || currentTicket.status==='closed') return;
    if(!confirm('Do you want to close the ticket?')) return;
    const fd=new FormData(); fd.append('action','close'); fd.append('ticket_id',currentTicket.id);
    const r=await fetch('/api/support',{method:'POST',headers:{'X-CSRF-Token':VX_CSRF},body:fd}); const d=await r.json();
    if(d.ok){ loadMessages(currentTicket.id); } else alert(d.msg||'Could not close');
}
function ticketInputKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendTicketMsg();
    }
}

function openNewTicket() {
    document.getElementById('newTicketSubject').value = '';
    document.getElementById('newTicketMsg').value = '';
    document.getElementById('newTicketErr').style.display = 'none';
    document.getElementById('newTicketModal').classList.add('open');
    setTimeout(() => document.getElementById('newTicketSubject').focus(), 50);
}

function closeNewTicket() {
    document.getElementById('newTicketModal').classList.remove('open');
}

async function submitNewTicket() {
    const subject = document.getElementById('newTicketSubject').value.trim();
    const message = document.getElementById('newTicketMsg').value.trim();
    const errEl = document.getElementById('newTicketErr');
    if (!subject || !message) {
        errEl.textContent = 'Subject and message are required.';
        errEl.style.display = 'block';
        return;
    }
    const fd = new FormData();
    fd.append('action', 'create');
    fd.append('subject', subject);
    fd.append('message', message);
    try {
        const res = await fetch('/api/support', { method: 'POST', headers: { 'X-CSRF-Token': VX_CSRF }, body: fd });
        const d = await res.json();
        if (!d.ok) {
            errEl.textContent = d.msg || 'An error occurred.';
            errEl.style.display = 'block';
            return;
        }
        closeNewTicket();
        await loadTickets();
        openTicket(d.ticket_id);
    } catch(e) {
        errEl.textContent = 'Connection error.';
        errEl.style.display = 'block';
    }
}

document.getElementById('newTicketModal').addEventListener('click', (e) => {
    if (e.target === document.getElementById('newTicketModal')) closeNewTicket();
});

loadTickets();
</script>

<?php require_once __DIR__ . '/_inc/footer.php'; ?>
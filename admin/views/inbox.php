 <?php
require_once __DIR__ . '/../../core/mail.php';
require_once __DIR__ . '/../../core/editor.php';
require_once __DIR__ . '/../../core/html-sanitize.php';

function e2($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$ALIASES = ['info@vexaris.net', 'abuse@vexaris.net', 'founder@vexaris.net', 'noreply@vexaris.net'];

try {
    $st = $pdo->prepare("SELECT `value` FROM settings WHERE `key`='mail_inbound_secret' LIMIT 1");
    $st->execute();
    $inSecret = (string)$st->fetchColumn();
} catch (Exception $e) { $inSecret = ''; }

$folder = ($_GET['folder'] ?? 'inbox') === 'sent' ? 'sent' : 'inbox';
$inMsg = ''; $inErr = false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $pdo) {
    csrf_check();
    $act = $_POST['ib_act'] ?? '';
    if ($act === 'regen') {
        $inSecret = bin2hex(random_bytes(32));
        $st = $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES ('mail_inbound_secret',?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
        $st->execute([$inSecret]);
        $inMsg = 'Webhook anahtarı yenilendi. Worker kodundaki INBOUND_SECRET değerini güncelle.';
    } elseif ($act === 'read' && isset($_POST['id'])) {
        $st = $pdo->prepare("UPDATE inbound_emails SET is_read=1 WHERE id=?");
        $st->execute([(int)$_POST['id']]);
    } elseif ($act === 'del' && isset($_POST['id'])) {
        $tbl = ($_POST['box'] ?? '') === 'sent' ? 'sent_emails' : 'inbound_emails';
        $st = $pdo->prepare("DELETE FROM $tbl WHERE id=?");
        $st->execute([(int)$_POST['id']]);
        $inMsg = 'Mail silindi.';
    } elseif ($act === 'send') {
        $to = mb_substr(trim((string)($_POST['ib_to'] ?? '')), 0, 190);
        $from = trim((string)($_POST['ib_from'] ?? ''));
        $subj = mb_substr(trim((string)($_POST['ib_subj'] ?? '')), 0, 255);
        $html = mb_substr((string)($_POST['ib_html'] ?? ''), 0, 500000);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { $inMsg = 'Alıcı e-posta adresi geçersiz.'; $inErr = true; }
        elseif (!in_array($from, $ALIASES, true)) { $inMsg = 'Gönderen alias geçersiz.'; $inErr = true; }
        elseif ($subj === '' || trim(strip_tags($html)) === '') { $inMsg = 'Konu ve içerik zorunlu.'; $inErr = true; }
        else {
            $ok = mail_send($pdo, $to, $subj, $html, [], ['email' => $from, 'name' => 'Vexaris']);
            if ($ok) {
                $st = $pdo->prepare("INSERT INTO sent_emails (to_addr, from_addr, subject, body_html) VALUES (?,?,?,?)");
                $st->execute([$to, $from, $subj, $html]);
                if (!empty($_POST['ib_inreply'])) {
                    $st = $pdo->prepare("UPDATE inbound_emails SET is_read=1, replied_at=NOW() WHERE id=?");
                    $st->execute([(int)$_POST['ib_inreply']]);
                }
                header('Location: /admin?tab=inbox&folder=sent&sent=1');
                exit;
            }
            $inMsg = 'Gönderilemedi: mail servisi (Brevo) yanıt vermedi.';
            $inErr = true;
        }
    }
}
if ($inSecret === '' && $pdo) {
    $inSecret = bin2hex(random_bytes(32));
    try {
        $st = $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES ('mail_inbound_secret',?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
        $st->execute([$inSecret]);
    } catch (Exception $e) {}
}

$mails = []; $unread = 0; $sentN = 0;
if ($pdo) {
    try {
        $unread = (int)$pdo->query("SELECT COUNT(*) FROM inbound_emails WHERE is_read=0")->fetchColumn();
        $sentN = (int)$pdo->query("SELECT COUNT(*) FROM sent_emails")->fetchColumn();
        if ($folder === 'sent') {
            $rows = $pdo->query("SELECT id, to_addr AS to_addr, from_addr, subject, created_at FROM sent_emails ORDER BY id DESC LIMIT 100");
        } else {
            $rows = $pdo->query("SELECT id, to_addr, from_addr, from_name, subject, is_read, replied_at, created_at FROM inbound_emails ORDER BY id DESC LIMIT 100");
        }
        while ($r = $rows->fetch()) $mails[] = $r;
    } catch (Exception $e) {}
}

$sel = null; $isSent = false;
$selId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($selId > 0 && $pdo) {
    try {
        if ($folder === 'sent') {
            $st = $pdo->prepare("SELECT *, 1 AS sentbox FROM sent_emails WHERE id=? LIMIT 1");
            $st->execute([$selId]);
            $sel = $st->fetch();
            $isSent = (bool)$sel;
        } else {
            $st = $pdo->prepare("SELECT * FROM inbound_emails WHERE id=? LIMIT 1");
            $st->execute([$selId]);
            $sel = $st->fetch();
            if ($sel && !(int)$sel['is_read']) {
                $st = $pdo->prepare("UPDATE inbound_emails SET is_read=1 WHERE id=?");
                $st->execute([$selId]);
                $sel['is_read'] = 1;
            }
        }
    } catch (Exception $e) {}
}

$composing = isset($_GET['compose']) || isset($_GET['reply']);
$replyRow = null;
if (isset($_GET['reply']) && $pdo) {
    try {
        $st = $pdo->prepare("SELECT * FROM inbound_emails WHERE id=? LIMIT 1");
        $st->execute([(int)$_GET['reply']]);
        $replyRow = $st->fetch();
    } catch (Exception $e) {}
}
$cTo = $replyRow ? $replyRow['from_addr'] : '';
$cFrom = ($replyRow && in_array($replyRow['to_addr'], $ALIASES, true)) ? $replyRow['to_addr'] : 'info@vexaris.net';
$cSubj = $replyRow ? ('Re: ' . ($replyRow['subject'] !== '' ? $replyRow['subject'] : '(konu yok)')) : '';
$cBody = '';
if ($replyRow) {
    $cBody = '<br><br><div style="border-left:3px solid #ccc;padding-left:10px;color:#666">'
        . '<div style="font-size:12px;margin-bottom:6px">--- ' . e2($replyRow['from_addr']) . ' yazdı (' . e2($replyRow['created_at']) . ') ---</div>'
        . nl2br(e2($replyRow['body_text'] ?? '')) . '</div>';
}
if ($inErr) {
    $cTo = (string)($_POST['ib_to'] ?? $cTo);
    $cFrom = (string)($_POST['ib_from'] ?? $cFrom);
    $cSubj = (string)($_POST['ib_subj'] ?? $cSubj);
    $cBody = (string)($_POST['ib_html'] ?? $cBody);
    $composing = true;
}
?>
<style>
.ib-wrap{display:flex;gap:1.2rem;align-items:flex-start}
.ib-side{width:340px;min-width:300px;background:rgba(255,255,255,.02);border:1px solid var(--border);border-radius:14px;overflow:hidden;flex-shrink:0}
.ib-side__head{display:flex;justify-content:space-between;align-items:center;padding:.9rem 1.1rem;border-bottom:1px solid var(--border)}
.ib-side__title{font-weight:800;font-size:.95rem}
.ib-side__count{font-size:.72rem;color:var(--muted)}
.ib-list{max-height:70vh;overflow:auto}
.ib-item{display:block;padding:.7rem 1.1rem;border-bottom:1px solid var(--border);text-decoration:none;color:inherit}
.ib-item:hover{background:rgba(255,255,255,.04)}
.ib-item.on{background:rgba(169,182,207,.1)}
.ib-item.unread{border-left:3px solid #8b98b3}
.ib-subj{font-weight:700;margin-top:.25rem;font-size:.88rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ib-meta{font-size:.75rem;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ib-tag{font-size:.66rem;font-weight:800;background:rgba(169,182,207,.12);border:1px solid rgba(169,182,207,.35);border-radius:6px;padding:.1rem .4rem;color:#a9b6cf}
.ib-main{flex:1;background:rgba(255,255,255,.02);border:1px solid var(--border);border-radius:14px;overflow:hidden;min-width:0}
.ib-empty{text-align:center;padding:3rem 1rem;color:var(--muted)}
.ib-box{max-width:560px;margin:1.2rem auto 0;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;padding:1rem;text-align:left;font-size:.78rem;color:var(--muted)}
.ib-folders{display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;align-items:center}
.ib-fold{padding:.55rem 1rem;border-radius:10px;font-size:.83rem;font-weight:700;text-decoration:none;background:var(--card);border:1px solid var(--border);color:var(--muted);cursor:pointer;font-family:inherit;transition:all .15s}
.ib-fold.on{background:rgba(169,182,207,.12);border-color:rgba(169,182,207,.4);color:#eef1f7}
.ib-fold:active{transform:scale(.97)}
.ib-new{margin-left:auto;background:linear-gradient(135deg,#4a5265,#2b303c);border:1px solid rgba(255,255,255,.12);color:#f2f4f8}
.ib-cmp{padding:1.2rem 1.4rem}
.ib-cmp label{display:block;font-size:.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin:.8rem 0 .35rem}
.ib-cmp input[type=text],.ib-cmp input[type=email],.ib-cmp select{width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);font-family:inherit;font-size:.9rem;padding:.65rem .85rem;outline:none;box-sizing:border-box}
.ib-cmp input:focus,.ib-cmp select:focus{border-color:var(--border-c)}
.ib-code{width:100%;min-height:280px;background:#101318;border:1px solid var(--border);border-radius:0 0 6px 6px;color:#c9d4e5;font-family:monospace;font-size:12.5px;padding:12px 16px;box-sizing:border-box;resize:vertical;outline:none;display:none}
.ib-modebtn{padding:0 12px;width:auto;font-weight:700}
.ib-modebtn.on{background:#3a3a3a;color:#fff}
.ed-tb{overflow-x:auto !important;scrollbar-width:thin}
@media(max-width:900px){.ib-wrap{flex-direction:column}.ib-side{width:100%;max-width:100%}.ib-list{max-height:38vh}.ib-cmp{padding:1rem}}
</style>
<div class="ib-folders">
    <a class="ib-fold <?= $folder === 'inbox' && !$composing ? 'on' : '' ?>" href="/admin?tab=inbox&folder=inbox"><i class="fas fa-inbox"></i> Gelen Kutusu<?= $unread > 0 ? ' (' . $unread . ')' : '' ?></a>
    <a class="ib-fold <?= $folder === 'sent' && !$composing ? 'on' : '' ?>" href="/admin?tab=inbox&folder=sent"><i class="fas fa-paper-plane"></i> Gönderilenler<?= $sentN > 0 ? ' (' . $sentN . ')' : '' ?></a>
    <a class="ib-fold" href="/admin?tab=inbox&folder=<?= $folder ?>" title="Yenile"><i class="fas fa-rotate-right"></i> Yenile</a>
    <a class="ib-fold ib-new <?= $composing ? 'on' : '' ?>" href="/admin?tab=inbox&compose=1"><i class="fas fa-pen"></i> Yeni</a>
</div>
<?php if (isset($_GET['sent'])): ?><div style="background:rgba(148,178,150,.09);border:1px solid rgba(148,178,150,.25);color:#a9c4ad;border-radius:10px;padding:.6rem 1rem;margin-bottom:1rem;font-size:.85rem">Mail gönderildi ve Gönderilenler'e kaydedildi.</div><?php endif; ?>
<?php if ($inMsg !== ''): ?><div style="background:<?= $inErr ? 'rgba(224,138,138,.07);border:1px solid rgba(224,138,138,.28);color:#d79595' : 'rgba(169,182,207,.1);border:1px solid rgba(169,182,207,.35);color:#dfe5f0' ?>;border-radius:10px;padding:.6rem 1rem;margin-bottom:1rem;font-size:.85rem"><?= e2($inMsg) ?></div><?php endif; ?>
<?php if ($composing): ?>
<div class="ib-main"><form method="post" id="ibSendForm" class="ib-cmp">
    <?= csrf_field() ?>
    <input type="hidden" name="ib_act" value="send">
    <?php if ($replyRow): ?><input type="hidden" name="ib_inreply" value="<?= (int)$replyRow['id'] ?>"><?php endif; ?>
    <input type="hidden" name="ib_html" id="ibHtml" value="">
    <label>Alıcı</label>
    <input type="email" name="ib_to" required placeholder="ornek@mail.com" value="<?= e2($cTo) ?>">
    <label>Gönderen (alias)</label>
    <select name="ib_from">
        <?php foreach ($ALIASES as $a): ?><option value="<?= $a ?>" <?= $cFrom === $a ? 'selected' : '' ?>><?= $a ?></option><?php endforeach; ?>
    </select>
    <label>Konu</label>
    <input type="text" name="ib_subj" required placeholder="Konu" value="<?= e2($cSubj) ?>">
    <label style="display:flex;align-items:center;gap:.6rem">İçerik
        <button type="button" class="ed-btn ib-modebtn" id="ibModeBtn" title="Görsel / HTML kod görünümü" style="width:auto;padding:0 12px;font-size:.75rem;border:1px solid #333;border-radius:4px"><i class="fa-solid fa-code"></i> KOD</button>
        <button type="button" class="ed-btn ib-modebtn" id="ibAiBtn" title="Vexaris AI ile yaz" style="width:auto;padding:0 12px;font-size:.75rem;border:1px solid #333;border-radius:4px;color:#c9b8e8"><svg width="14" height="14" viewBox="0 0 200 200" style="vertical-align:-2px"><path d="M70 44q11 45 56 56-45 11-56 56-11-45-56-56 45-11 56-56m90-30q7 19 26 26-19 7-26 26-7-19-26-26 19-7 26-26m-10 120q7 19 26 26-19 7-26 26-7-19-26-26 19-7 26-26" fill="currentColor"/></svg> AI</button>
    </label>
    <?php render_editor('mailEd', $cBody, '280px', 'mailEdName'); ?>
    <textarea class="ib-code" id="ibCode" placeholder="<p>HTML kodunu buraya yapıştır...</p>"></textarea>
    <div style="display:flex;gap:.6rem;margin-top:1rem">
        <button class="btn-save" type="submit" style="flex:2"><i class="fas fa-paper-plane"></i> Gönder (Brevo)</button>
        <a class="ib-fold" href="/admin?tab=inbox&folder=<?= $folder ?>">Vazgeç</a>
    </div>
</form></div>
<script>
(function(){
    var btn=document.getElementById('ibModeBtn'), code=document.getElementById('ibCode'),
        body=document.getElementById('edBody-mailEd'), wrap=document.getElementById('edWrap-mailEd'),
        form=document.getElementById('ibSendForm'), html=document.getElementById('ibHtml'), isCode=false;
    if(!btn||!code||!body||!form) return;
    btn.addEventListener('click',function(){
        isCode=!isCode;
        btn.classList.toggle('on',isCode);
        var tb=document.getElementById('edTb-mailEd');
        if(isCode){ code.value=body.innerHTML; code.style.display='block'; body.style.display='none'; if(tb)tb.style.display='none'; }
        else{ body.innerHTML=code.value; code.style.display='none'; body.style.display=''; if(tb)tb.style.display=''; }
    });
    form.addEventListener('submit',function(){
        html.value=isCode?code.value:body.innerHTML;
    });
})();
</script>
<div class="modal-overlay" id="ibAiModal" style="z-index:9500">
    <div class="modal-box" style="max-width:620px;width:94%">
        <button class="modal-close" id="ibAiClose" type="button"><i class="fas fa-xmark"></i></button>
        <div class="modal-title"><svg width="16" height="16" viewBox="0 0 200 200" style="vertical-align:-2px;color:#c9b8e8"><path d="M70 44q11 45 56 56-45 11-56 56-11-45-56-56 45-11 56-56m90-30q7 19 26 26-19 7-26 26-7-19-26-26 19-7 26-26m-10 120q7 19 26 26-19 7-26 26-7-19-26-26 19-7 26-26" fill="currentColor"/></svg> Vexaris AI <span id="ibAiModel" style="font-size:.7rem;color:var(--muted);font-weight:400"></span></div>
        <div class="form-field">
            <label>Ne yapsın? (ör: "iade talebine kibar ret yaz", "şu metindeki yazım hatalarını düzelt", "İngilizceye çevir")</label>
            <textarea id="ibAiPrompt" rows="3" placeholder="İsteğini yaz..." style="min-height:70px;resize:vertical"></textarea>
        </div>
        <label class="feat-label" style="margin-bottom:1rem"><input type="checkbox" id="ibAiCtx" checked> Editördeki mevcut metni bağlam olarak gönder</label>
        <div id="ibAiOut" style="display:none;background:#101318;border:1px solid var(--border);border-radius:10px;padding:.8rem 1rem;margin-bottom:1rem;max-height:38vh;overflow:auto">
            <div style="font-size:.72rem;font-weight:700;color:var(--muted);margin-bottom:.4rem">AI ÖNERİSİ (markdown)</div>
            <pre id="ibAiText" style="white-space:pre-wrap;font-family:monospace;font-size:.8rem;line-height:1.6;margin:0"></pre>
        </div>
        <div class="form-field" id="ibAiRevWrap" style="display:none">
            <label>Revize ettir</label>
            <div style="display:flex;gap:.5rem">
                <input type="text" id="ibAiRev" placeholder="ör: daha kısa yaz" style="flex:1">
                <button type="button" class="btn-primary" id="ibAiRevGo" style="white-space:nowrap">Revize</button>
            </div>
        </div>
        <div class="alert-msg" id="ibAiAlert"></div>
        <div class="modal-actions" style="flex-wrap:wrap">
            <button type="button" class="btn-save" id="ibAiGo"><i class="fas fa-wand-magic-sparkles"></i> Oluştur</button>
            <button type="button" class="btn-ghost" id="ibAiCopy" style="display:none"><i class="fas fa-copy"></i> Kopyala</button>
            <button type="button" class="btn-ghost" id="ibAiApply" style="display:none;border-color:rgba(148,178,150,.4);color:#a9c4ad"><i class="fas fa-check"></i> Editöre Uygula</button>
        </div>
    </div>
</div>
<script>
(function(){
    var open=document.getElementById('ibAiBtn'), modal=document.getElementById('ibAiModal'),
        close=document.getElementById('ibAiClose'), go=document.getElementById('ibAiGo'),
        prm=document.getElementById('ibAiPrompt'), ctx=document.getElementById('ibAiCtx'),
        out=document.getElementById('ibAiOut'), txt=document.getElementById('ibAiText'),
        alert=document.getElementById('ibAiAlert'), model=document.getElementById('ibAiModel'),
        revW=document.getElementById('ibAiRevWrap'), rev=document.getElementById('ibAiRev'), revGo=document.getElementById('ibAiRevGo'),
        copy=document.getElementById('ibAiCopy'), apply=document.getElementById('ibAiApply');
    if(!open||!modal) return;
    var hist=[];
    function edText(){
        var b=document.getElementById('edBody-mailEd'), c=document.getElementById('ibCode');
        if(c&&c.style.display==='block') return c.value.replace(/<[^>]+>/g,' ').replace(/\s+/g,' ').trim().slice(0,6000);
        return b?(b.innerText||'').trim().slice(0,6000):'';
    }
    function escH(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
    function mdToHtml(md){
        var html=escH(md);
        html=html.replace(/```([\s\S]*?)```/g,function(_,c){return '<pre>'+c.trim()+'</pre>';});
        html=html.replace(/`([^`]+)`/g,'<code>$1</code>');
        html=html.replace(/^###\s+(.+)$/gm,'<h3>$1</h3>');
        html=html.replace(/^##\s+(.+)$/gm,'<h2>$1</h2>');
        html=html.replace(/^#\s+(.+)$/gm,'<h1>$1</h1>');
        html=html.replace(/^&gt;\s+(.+)$/gm,'<blockquote>$1</blockquote>');
        html=html.replace(/\[([^\]]+)\]\(([^)]+)\)/g,'<a href="$2">$1</a>');
        html=html.replace(/\*\*([^*]+)\*\*/g,'<strong>$1</strong>');
        html=html.replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g,'$1<em>$2</em>');
        html=html.replace(/^(?:-|\*)\s+(.+)$/gm,'<li>$1</li>');
        html=html.replace(/(<li>.*<\/li>)/gs,function(m){return '<ul>'+m+'</ul>';});
        html=html.replace(/^\d+\.\s+(.+)$/gm,'<li>$1</li>');
        html=html.split(/\n{2,}/).map(function(p){p=p.trim(); if(!p) return ''; if(/^<(h[1-3]|ul|ol|pre|blockquote|hr|img)/.test(p)) return p; return '<p>'+p.replace(/\n/g,'<br>')+'</p>';}).join('');
        return html;
    }
    function showAlert(msg,ok){
        alert.style.display='block';
        alert.className='alert-msg '+(ok?'alert-ok':'alert-err');
        alert.textContent=msg;
    }
    async function callAI(prompt){
        showAlert('AI yazıyor...',true);
        go.disabled=true;
        try{
            var res=await fetch('/api/mail-ai.php',{method:'POST',headers:{'Content-Type':'application/json'},
                body:JSON.stringify({prompt:prompt,history:hist,context:(ctx&&ctx.checked)?edText():'',_csrf:(typeof VX_CSRF!=='undefined'?VX_CSRF:'')})});
            var d=await res.json();
            if(!d.ok){ showAlert(d.msg||'AI hatası',false); return; }
            if(model&&d.model) model.textContent='· '+d.model;
            txt.textContent=d.text||'';
            out.style.display='block';
            revW.style.display='block';
            copy.style.display='';
            apply.style.display='';
            hist.push({role:'user',content:prompt});
            hist.push({role:'assistant',content:d.text||''});
            showAlert('Hazır. Revize ettirebilir veya editöre uygulayabilirsin.',true);
        }catch(e){ showAlert('Bağlantı hatası.',false); }
        go.disabled=false;
    }
    open.addEventListener('click',function(){ modal.style.display='flex'; });
    close.addEventListener('click',function(){ modal.style.display='none'; });
    modal.addEventListener('click',function(e){ if(e.target===modal) modal.style.display='none'; });
    go.addEventListener('click',function(){ var v=prm.value.trim(); if(!v){showAlert('Önce ne yapacağını yaz.',false);return;} hist=[]; callAI(v); });
    revGo.addEventListener('click',function(){ var v=rev.value.trim(); if(!v)return; rev.value=''; callAI(v); });
    copy.addEventListener('click',function(){
        var t=txt.textContent;
        if(navigator.clipboard) navigator.clipboard.writeText(t).then(function(){showAlert('Kopyalandı.',true);});
        else showAlert('Kopyalama desteklenmiyor.',false);
    });
    apply.addEventListener('click',function(){
        var t=txt.textContent;
        if(!t) return;
        var html=mdToHtml(t);
        var b=document.getElementById('edBody-mailEd'), c=document.getElementById('ibCode');
        if(c&&c.style.display==='block'){ c.value=html; }
        else if(b){ b.innerHTML=html; b.focus(); }
        modal.style.display='none';
    });
})();
</script>
<?php else: ?>
<div class="ib-wrap">
    <div class="ib-side">
        <div class="ib-side__head">
            <div class="ib-side__title"><i class="fas <?= $folder === 'sent' ? 'fa-paper-plane' : 'fa-inbox' ?>"></i> <?= $folder === 'sent' ? 'Gönderilenler' : 'Gelen Kutusu' ?></div>
            <span class="ib-side__count"><?= $folder === 'sent' ? (int)$sentN : (int)$unread . ' okunmadı' ?></span>
        </div>
        <div class="ib-list">
            <?php if (!$mails): ?>
                <div class="ib-empty"><i class="fas fa-inbox"></i><br><?= $folder === 'sent' ? 'Henüz gönderi yok.' : 'Henüz mail yok.' ?><br><span style="font-size:.75rem">Worker aktif olunca mailler buraya düşer.</span></div>
            <?php else: foreach ($mails as $m): ?>
                <a href="/admin?tab=inbox&folder=<?= $folder ?>&id=<?= (int)$m['id'] ?>" class="ib-item<?= $selId === (int)$m['id'] ? ' on' : '' ?><?= ($folder === 'inbox' && !(int)($m['is_read'] ?? 1)) ? ' unread' : '' ?>">
                    <div style="display:flex;gap:6px;align-items:center">
                        <span class="ib-tag"><?= e2($folder === 'sent' ? $m['from_addr'] : $m['to_addr']) ?></span>
                        <?php if ($folder === 'inbox' && !empty($m['replied_at'])): ?><span style="font-size:.66rem;color:#a9c4ad;font-weight:700"><i class="fas fa-reply"></i></span><?php endif; ?>
                    </div>
                    <div class="ib-subj"><?= e2($m['subject'] !== '' ? $m['subject'] : '(konu yok)') ?></div>
                    <div class="ib-meta"><?= e2($folder === 'sent' ? ('→ ' . $m['to_addr']) : (!empty($m['from_name']) ? $m['from_name'] . ' <' . $m['from_addr'] . '>' : $m['from_addr'])) ?></div>
                    <div class="ib-meta"><?= e2($m['created_at']) ?></div>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <div class="ib-main">
        <?php if (!$sel): ?>
            <div class="ib-empty"><i class="fas fa-envelope-open"></i><p>Listeden bir mail seç.</p>
                <div class="ib-box">
                    <b style="color:#fff">Worker kurulum bilgisi</b><br>
                    Webhook URL: <code style="color:#a9b6cf">https://vexaris.net/api/inbound-mail.php</code><br>
                    Anahtar (INBOUND_SECRET): <code id="ibSec" style="color:#a9b6cf;word-break:break-all"><?= e2($inSecret) ?></code>
                    <form method="post" style="margin-top:.5rem"><?= csrf_field() ?><input type="hidden" name="ib_act" value="regen"><button style="background:rgba(169,182,207,.12);border:1px solid rgba(169,182,207,.35);color:#fff;border-radius:8px;padding:5px 10px;font-size:.72rem;cursor:pointer">Anahtarı yenile</button></form>
                </div>
            </div>
        <?php elseif ($isSent): ?>
            <div style="padding:1.2rem 1.4rem;border-bottom:1px solid var(--border)">
                <div style="font-size:1.05rem;font-weight:800"><?= e2($sel['subject'] !== '' ? $sel['subject'] : '(konu yok)') ?></div>
                <div style="font-size:.8rem;color:var(--muted);margin-top:.3rem">Kime: <?= e2($sel['to_addr']) ?> &nbsp;•&nbsp; Kimden: <b style="color:#a9b6cf"><?= e2($sel['from_addr']) ?></b> &nbsp;•&nbsp; <?= e2($sel['created_at']) ?></div>
            </div>
            <div style="padding:1.2rem 1.4rem;font-size:.9rem;line-height:1.65;max-height:50vh;overflow:auto;overflow-wrap:anywhere"><?= $sel['body_html'] !== '' ? $sel['body_html'] : '(içerik yok)' ?></div>
            <form method="post" style="padding:0 1.4rem 1.2rem" onsubmit="return confirm('Silinsin mi?')">
                <?= csrf_field() ?>
                <input type="hidden" name="ib_act" value="del">
                <input type="hidden" name="box" value="sent">
                <input type="hidden" name="id" value="<?= (int)$sel['id'] ?>">
                <button style="background:none;border:none;color:var(--muted);font-size:.75rem;cursor:pointer"><i class="fas fa-trash"></i> Sil</button>
            </form>
        <?php else: ?>
            <div style="padding:1.2rem 1.4rem;border-bottom:1px solid var(--border)">
                <div style="font-size:1.05rem;font-weight:800"><?= e2($sel['subject'] !== '' ? $sel['subject'] : '(konu yok)') ?></div>
                <div style="font-size:.8rem;color:var(--muted);margin-top:.3rem">Kimden: <?= e2(!empty($sel['from_name']) ? $sel['from_name'] . ' <' . $sel['from_addr'] . '>' : $sel['from_addr']) ?> &nbsp;•&nbsp; Kime: <b style="color:#a9b6cf"><?= e2($sel['to_addr']) ?></b> &nbsp;•&nbsp; <?= e2($sel['created_at']) ?><?= $sel['replied_at'] ? ' &nbsp;•&nbsp; <span style="color:#a9c4ad">yanıtlandı</span>' : '' ?></div>
                <?php if (!empty($sel['body_html'])): ?>
                <div style="display:flex;gap:.5rem;margin-top:.7rem">
                    <button type="button" class="ib-fold ib-viewbtn on" data-v="html"><i class="fas fa-palette"></i> HTML</button>
                    <button type="button" class="ib-fold ib-viewbtn" data-v="text"><i class="fas fa-align-left"></i> Metin</button>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($sel['body_html'])): ?>
            <div id="ibHtmlPane" style="padding:0">
                <iframe sandbox="" referrerpolicy="no-referrer" title="E-posta içeriği" srcdoc="<?= e2(vx_clean_html($sel['body_html'], 'rich')) ?>" style="width:100%;height:52vh;border:none;background:#fff;display:block"></iframe>
                <div style="font-size:.7rem;color:var(--muted);padding:.4rem 1.4rem;border-top:1px solid var(--border)"><i class="fas fa-shield-halved"></i> HTML görünüm yalıtımlı kutuda açılır (betikler çalışmaz).</div>
            </div>
            <div id="ibTextPane" style="display:none;padding:1.2rem 1.4rem;white-space:pre-wrap;font-size:.9rem;line-height:1.65;max-height:40vh;overflow:auto;overflow-wrap:anywhere"><?= e2($sel['body_text'] !== '' ? $sel['body_text'] : '(düz metin yok)') ?></div>
            <script>
            (function(){
                var btns=document.querySelectorAll('.ib-viewbtn'), hp=document.getElementById('ibHtmlPane'), tp=document.getElementById('ibTextPane');
                if(!btns.length||!hp||!tp) return;
                btns.forEach(function(b){ b.addEventListener('click',function(){
                    var v=b.getAttribute('data-v');
                    btns.forEach(function(x){x.classList.toggle('on',x===b);});
                    hp.style.display=v==='html'?'':'none';
                    tp.style.display=v==='text'?'':'none';
                });});
            })();
            </script>
            <?php else: ?>
            <div style="padding:1.2rem 1.4rem;white-space:pre-wrap;font-size:.9rem;line-height:1.65;max-height:40vh;overflow:auto;overflow-wrap:anywhere"><?= e2($sel['body_text'] !== '' ? $sel['body_text'] : '(içerik yok)') ?></div>
            <?php endif; ?>
            <div style="display:flex;gap:.6rem;padding:1.2rem 1.4rem;border-top:1px solid var(--border);flex-wrap:wrap">
                <a class="ib-fold" href="/admin?tab=inbox&reply=<?= (int)$sel['id'] ?>"><i class="fas fa-reply"></i> Yanıtla</a>
                <form method="post" style="margin-left:auto" onsubmit="return confirm('Silinsin mi?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="ib_act" value="del">
                    <input type="hidden" name="id" value="<?= (int)$sel['id'] ?>">
                    <button style="background:none;border:none;color:var(--muted);font-size:.75rem;cursor:pointer"><i class="fas fa-trash"></i> Sil</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

/* Inbox (admin) — etkileşim */
(function () {
  'use strict';

  function toast(msg) {
    var t = document.getElementById('ibToast');
    if (!t) { return; }
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(window.__ibToastT);
    window.__ibToastT = setTimeout(function () { t.classList.remove('show'); }, 1800);
  }

  function copyText(txt, btn) {
    function done() { toast('Kopyalandı'); if (btn) { btn.classList.add('did'); setTimeout(function () { btn.classList.remove('did'); }, 1400); } }
    function fallback() {
      var buf = document.getElementById('ibCopyBuf');
      if (!buf) { return; }
      buf.value = txt;
      buf.removeAttribute('readonly');
      buf.select();
      try { document.execCommand('copy'); done(); } catch (e) { toast('Kopyalanamadı'); }
      buf.setAttribute('readonly', 'readonly');
      buf.blur();
    }
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(txt).then(done, fallback);
    } else { fallback(); }
  }

  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-ibcopy],[data-ibcopy-from]');
    if (!b) { return; }
    var txt = b.getAttribute('data-ibcopy');
    if ((txt === null || txt === '') && b.getAttribute('data-ibcopy-from')) {
      var el = document.querySelector(b.getAttribute('data-ibcopy-from'));
      txt = el ? (el.value || el.textContent || '') : '';
    }
    if (txt) { copyText(txt, b); }
  });

  /* Toplu seçim */
  var bulkForm = document.getElementById('ibBulkForm');
  if (bulkForm) {
    var fid = bulkForm.id;
    var all = document.getElementById('ibSelAll');
    function boxes() { return document.querySelectorAll('input[type=checkbox][form="' + fid + '"]'); }
    function refresh() {
      var bs = boxes(), n = 0;
      for (var i = 0; i < bs.length; i++) { if (bs[i].checked) { n++; } }
      var info = document.getElementById('ibSelInfo');
      if (info) { info.textContent = n > 0 ? (n + ' seçili') : ''; }
      if (all) { all.checked = n > 0 && n === bs.length; all.indeterminate = n > 0 && n < bs.length; }
    }
    if (all) {
      all.addEventListener('change', function () {
        var bs = boxes();
        for (var i = 0; i < bs.length; i++) { bs[i].checked = all.checked; }
        refresh();
      });
    }
    document.addEventListener('change', function (e) {
      var t = e.target;
      if (t && t.type === 'checkbox' && t.getAttribute && t.getAttribute('form') === fid) { refresh(); }
    });
    bulkForm.addEventListener('submit', function (e) {
      var n = 0, bs = boxes();
      for (var i = 0; i < bs.length; i++) { if (bs[i].checked) { n++; } }
      if (n === 0) { e.preventDefault(); toast('Önce mail seç'); return; }
      var btn = e.submitter;
      var op = btn ? btn.value : (document.activeElement ? document.activeElement.value : '');
      if ((op === 'del' || op === 'emptytrash') && !confirm('Seçili mailler kalıcı olarak silinsin mi?')) { e.preventDefault(); }
    });
    refresh();
  }

  /* Okuyucu yüksekliği: mobilde sabit, kaydırma tek yerde kalsın */
  function fitReader() {
    var root = document.documentElement;
    if (window.innerWidth > 900) { root.style.removeProperty('--ib-read-h'); return; }
    var el = document.querySelector('.ib-pane-html') || document.querySelector('.ib-main .ib-textpane');
    if (!el) { return; }
    var rect = el.getBoundingClientRect();
    var top = rect.top + window.scrollY;
    var h = Math.max(240, window.innerHeight - top - 14);
    root.style.setProperty('--ib-read-h', Math.round(h) + 'px');
  }
  window.addEventListener('resize', fitReader);
  window.addEventListener('load', fitReader);
  if (document.readyState !== 'loading') { setTimeout(fitReader, 60); } else { document.addEventListener('DOMContentLoaded', fitReader); }
  setTimeout(fitReader, 350);

  /* Görünüm sekmeleri (HTML / metin) */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.ib-viewtab');
    if (!btn) { return; }
    var v = btn.getAttribute('data-v');
    var scope = btn.closest('.ib-main') || document;
    scope.querySelectorAll('.ib-viewtab').forEach(function (x) { x.classList.toggle('on', x === btn); });
    var hp = scope.querySelector('.ib-pane-html'), tp = scope.querySelector('.ib-pane-text');
    if (hp) { hp.style.display = v === 'html' ? '' : 'none'; }
    if (tp) { tp.style.display = v === 'text' ? '' : 'none'; }
    fitReader();
  });

  /* Ayarlar paneli */
  function setModal(open) {
    var m = document.getElementById('ibSetModal');
    if (!m) { return; }
    m.style.display = open ? 'flex' : 'none';
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest('#ibSetBtn')) { setModal(true); }
    else if (e.target.closest('#ibSetClose') || e.target === document.getElementById('ibSetModal')) { setModal(false); }
  });
  if (/[?&]set=1/.test(location.search)) { setModal(true); }
})();

/* Compose */
(function () {
  'use strict';
  var form = document.getElementById('ibSendForm');
  if (!form) { return; }

  var btn = document.getElementById('ibModeBtn'),
      code = document.getElementById('ibCode'),
      body = document.getElementById('edBody-mailEd'),
      html = document.getElementById('ibHtml'),
      isCode = false;

  function syncToCode() { if (body && code) { code.value = body.innerHTML; } }

  if (btn && code && body) {
    btn.addEventListener('click', function () {
      isCode = !isCode;
      btn.classList.toggle('on', isCode);
      var tb = document.getElementById('edTb-mailEd');
      if (isCode) { syncToCode(); code.style.display = 'block'; body.style.display = 'none'; if (tb) { tb.style.display = 'none'; } }
      else { body.innerHTML = code.value; code.style.display = 'none'; body.style.display = ''; if (tb) { tb.style.display = ''; } }
    });
  }

  if (form && html && body) {
    form.addEventListener('submit', function (e) {
      var content = isCode && code ? code.value : body.innerHTML;
      html.value = content;
      if (!content.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, '').trim()) {
        e.preventDefault();
        if (window.alert) { alert('İçerik boş. Lütfen bir mesaj yaz.'); }
        if (body && !isCode) { body.focus(); }
        return;
      }
    });
  }

  /* İmza ekle */
  var sigBtn = document.getElementById('ibSigBtn');
  if (sigBtn && body) {
    sigBtn.addEventListener('click', function () {
      var sig = window.ibSignatureHtml || '';
      if (!sig) { return; }
      if (isCode && code) { code.value += sig; return; }
      body.focus();
      try { document.execCommand('insertHTML', false, sig + '<br>'); }
      catch (e) { body.innerHTML += sig + '<br>'; }
      toastEl('İmza eklendi');
    });
  }
  function toastEl(m) {
    var t = document.getElementById('ibToast');
    if (!t) { return; }
    t.textContent = m; t.classList.add('show');
    clearTimeout(window.__ibToastT);
    window.__ibToastT = setTimeout(function () { t.classList.remove('show'); }, 1600);
  }

  /* İmleci en başa koy (yanıt metni her zaman üstte yazılsın) */
  if (body) {
    setTimeout(function () {
      try {
        body.focus();
        var r = document.createRange();
        r.selectNodeContents(body);
        r.collapse(true);
        var s = window.getSelection();
        s.removeAllRanges();
        s.addRange(r);
      } catch (e) {}
    }, 60);
  }
})();

/* Vexaris AI (compose) */
(function () {
  'use strict';
  var open = document.getElementById('ibAiBtn'), modal = document.getElementById('ibAiModal');
  if (!open || !modal) { return; }
  var close = document.getElementById('ibAiClose'), go = document.getElementById('ibAiGo'),
      prm = document.getElementById('ibAiPrompt'), ctx = document.getElementById('ibAiCtx'),
      out = document.getElementById('ibAiOut'), txt = document.getElementById('ibAiText'),
      alertBox = document.getElementById('ibAiAlert'), model = document.getElementById('ibAiModel'),
      revW = document.getElementById('ibAiRevWrap'), rev = document.getElementById('ibAiRev'), revGo = document.getElementById('ibAiRevGo'),
      copy = document.getElementById('ibAiCopy'), apply = document.getElementById('ibAiApply');
  var hist = [];

  function edText() {
    var b = document.getElementById('edBody-mailEd'), c = document.getElementById('ibCode');
    if (c && c.style.display === 'block') { return c.value.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 6000); }
    return b ? (b.innerText || '').trim().slice(0, 6000) : '';
  }
  function escH(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function mdToHtml(md) {
    var h = escH(md);
    h = h.replace(/```([\s\S]*?)```/g, function (_, c) { return '<pre>' + c.trim() + '</pre>'; });
    h = h.replace(/`([^`]+)`/g, '<code>$1</code>');
    h = h.replace(/^###\s+(.+)$/gm, '<h3>$1</h3>');
    h = h.replace(/^##\s+(.+)$/gm, '<h2>$1</h2>');
    h = h.replace(/^#\s+(.+)$/gm, '<h1>$1</h1>');
    h = h.replace(/^&gt;\s+(.+)$/gm, '<blockquote>$1</blockquote>');
    h = h.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>');
    h = h.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    h = h.replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>');
    h = h.replace(/^(?:-|\*)\s+(.+)$/gm, '<li>$1</li>');
    h = h.replace(/(<li>.*<\/li>)/gs, function (m) { return '<ul>' + m + '</ul>'; });
    h = h.replace(/^\d+\.\s+(.+)$/gm, '<li>$1</li>');
    h = h.split(/\n{2,}/).map(function (p) {
      p = p.trim(); if (!p) { return ''; }
      if (/^<(h[1-3]|ul|ol|pre|blockquote|hr|img)/.test(p)) { return p; }
      return '<p>' + p.replace(/\n/g, '<br>') + '</p>';
    }).join('');
    return h;
  }
  function showAlert(msg, ok) {
    alertBox.style.display = 'block';
    alertBox.className = 'alert-msg ' + (ok ? 'alert-ok' : 'alert-err');
    alertBox.textContent = msg;
  }
  function callAI(prompt) {
    showAlert('AI yazıyor...', true);
    go.disabled = true;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/api/mail-ai.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onload = function () {
      go.disabled = false;
      var d = {};
      try { d = JSON.parse(xhr.responseText); } catch (e) { showAlert('AI yanıtı okunamadı.', false); return; }
      if (!d.ok) { showAlert(d.msg || 'AI hatası', false); return; }
      if (model && d.model) { model.textContent = '· ' + d.model; }
      txt.textContent = d.text || '';
      out.style.display = 'block';
      revW.style.display = 'block';
      copy.style.display = '';
      apply.style.display = '';
      hist.push({ role: 'user', content: prompt });
      hist.push({ role: 'assistant', content: d.text || '' });
      showAlert('Hazır. Revize ettirebilir veya editöre uygulayabilirsin.', true);
    };
    xhr.onerror = function () { go.disabled = false; showAlert('Bağlantı hatası.', false); };
    xhr.send(JSON.stringify({
      prompt: prompt, history: hist,
      context: (ctx && ctx.checked) ? edText() : '',
      _csrf: (typeof VX_CSRF !== 'undefined' ? VX_CSRF : '')
    }));
  }
  open.addEventListener('click', function () { modal.style.display = 'flex'; });
  close.addEventListener('click', function () { modal.style.display = 'none'; });
  modal.addEventListener('click', function (e) { if (e.target === modal) { modal.style.display = 'none'; } });
  go.addEventListener('click', function () { var v = prm.value.trim(); if (!v) { showAlert('Önce ne yapacağını yaz.', false); return; } hist = []; callAI(v); });
  revGo.addEventListener('click', function () { var v = rev.value.trim(); if (!v) { return; } rev.value = ''; callAI(v); });
  copy.addEventListener('click', function () {
    var t = txt.textContent;
    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(t).then(function () { showAlert('Kopyalandı.', true); }); }
    else { showAlert('Kopyalama desteklenmiyor.', false); }
  });
  apply.addEventListener('click', function () {
    var t = txt.textContent;
    if (!t) { return; }
    var h = mdToHtml(t);
    var b = document.getElementById('edBody-mailEd'), c = document.getElementById('ibCode');
    if (c && c.style.display === 'block') { c.value = h; }
    else if (b) { b.innerHTML = h; b.focus(); }
    modal.style.display = 'none';
  });
})();

/* Spam (AI) + Çeviri */
(function () {
  'use strict';
  function toast(m) {
    var t = document.getElementById('ibToast'); if (!t) { return; }
    t.textContent = m; t.classList.add('show');
    clearTimeout(window.__ibToastS); window.__ibToastS = setTimeout(function () { t.classList.remove('show'); }, 2400);
  }
  function csrf() { return (typeof VX_CSRF !== 'undefined') ? VX_CSRF : ''; }
  function post(url, data, cb) {
    var x = new XMLHttpRequest();
    x.open('POST', url, true);
    x.setRequestHeader('Content-Type', 'application/json');
    x.onload = function () { var d = {}; try { d = JSON.parse(x.responseText); } catch (e) {} cb(d, x.status); };
    x.onerror = function () { cb({ ok: false, msg: 'Bağlantı hatası' }, 0); };
    data._csrf = csrf();
    x.send(JSON.stringify(data));
  }

  /* Çeviri modalı */
  var trBtn = document.getElementById('ibTrBtn'), trModal = document.getElementById('ibTrModal');
  var trOut = document.getElementById('ibTrOut'), trMsg = document.getElementById('ibTrMsg'), trCopyB = document.getElementById('ibTrCopy');
  var curTrId = 0, curTrBox = 'in';
  function showTr(m, ok) { if (!trMsg) { return; } trMsg.style.display = 'block'; trMsg.className = 'alert-msg ' + (ok ? 'alert-ok' : 'alert-err'); trMsg.textContent = m; }
  function runTr() {
    var lang = (document.getElementById('ibTrLang') || {}).value || 'tr';
    showTr('Çevriliyor...', true);
    if (trOut) { trOut.style.display = 'none'; }
    if (trCopyB) { trCopyB.style.display = 'none'; }
    post('/api/mail-translate.php', { id: curTrId, box: curTrBox, lang: lang }, function (d) {
      if (!d.ok) { showTr(d.msg || 'Çeviri başarısız', false); return; }
      trMsg.style.display = 'none';
      if (trOut) { trOut.textContent = (d.text || '').trim() || '(boş)'; trOut.style.display = 'block'; }
      if (trCopyB) { trCopyB.style.display = ''; }
      var meta = document.getElementById('ibTrMeta');
      if (meta && d.detected) { meta.textContent = '· algılanan: ' + d.detected + ' → ' + lang; }
    });
  }
  if (trBtn) {
    trBtn.addEventListener('click', function () {
      curTrId = parseInt(trBtn.getAttribute('data-id') || '0', 10);
      curTrBox = trBtn.getAttribute('data-box') || 'in';
      if (trModal) { trModal.style.display = 'flex'; }
      runTr();
    });
  }
  var trGo = document.getElementById('ibTrGo'); if (trGo) { trGo.addEventListener('click', runTr); }
  var trClose = document.getElementById('ibTrClose'); if (trClose) { trClose.addEventListener('click', function () { if (trModal) { trModal.style.display = 'none'; } }); }
  if (trModal) { trModal.addEventListener('click', function (e) { if (e.target === trModal) { trModal.style.display = 'none'; } }); }
  var trLang = document.getElementById('ibTrLang'); if (trLang) { trLang.addEventListener('change', function () { if (curTrId) { runTr(); } }); }
  if (trCopyB) {
    trCopyB.addEventListener('click', function () {
      var t = trOut ? trOut.textContent : '';
      if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(t).then(function () { toast('Kopyalandı'); }); }
    });
  }

  function scanBatch(limit, cb) { post('/api/mail-spam.php', { mode: 'scan', limit: limit }, cb); }

  /* AI ile tara (tek mail) */
  var scanBtn = document.getElementById('ibSpamScanBtn');
  if (scanBtn) {
    scanBtn.addEventListener('click', function () {
      var id = parseInt(scanBtn.getAttribute('data-id') || '0', 10); if (!id) { return; }
      scanBtn.disabled = true; var old = scanBtn.innerHTML;
      scanBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Taranıyor...';
      post('/api/mail-spam.php', { mode: 'single', id: id }, function (d) {
        scanBtn.disabled = false; scanBtn.innerHTML = old;
        if (!d.ok) { toast(d.msg || 'AI taranamadı'); return; }
        toast(d.spam ? 'AI: SPAM olarak işaretlendi' : 'AI: Spam değil');
        setTimeout(function () { location.reload(); }, 900);
      });
    });
  }

  /* Şimdi tara (ayarlar) */
  var scanAll = document.getElementById('ibSpamScanAllBtn');
  if (scanAll) {
    scanAll.addEventListener('click', function () {
      scanAll.disabled = true; scanAll.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Taranıyor...';
      var total = 0, spam = 0, tries = 0;
      (function step() {
        scanBatch(5, function (d) {
          if (!d.ok) { toast(d.msg || 'Tarama hatası'); scanAll.disabled = false; scanAll.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i> Şimdi tara'; return; }
          total += d.scanned || 0; spam += d.spam || 0; tries++;
          if ((d.pending || 0) > 0 && tries < 20) { step(); return; }
          toast(total + ' mail tarandı · spam: ' + spam);
          scanAll.disabled = false; scanAll.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i> Şimdi tara';
          setTimeout(function () { location.reload(); }, 900);
        });
      })();
    });
  }

  /* Otomatik tarama: gelen kutusu açılınca bekleyenleri AI'ya gönder */
  var wrap = document.getElementById('ibWrap');
  if (wrap && wrap.getAttribute('data-spam-enabled') === '1') {
    var pending = parseInt(wrap.getAttribute('data-spam-pending') || '0', 10);
    if (pending > 0) {
      var last = parseInt(sessionStorage.getItem('nxSpamRun') || '0', 10);
      if (!last || Date.now() - last > 60000) {
        sessionStorage.setItem('nxSpamRun', String(Date.now()));
        var scanned = 0, tries2 = 0, spamTotal = null;
        (function step() {
          scanBatch(3, function (d) {
            if (!d.ok) { return; }
            scanned += d.scanned || 0; tries2++;
            if (d.spam_total !== undefined) { spamTotal = d.spam_total; }
            if ((d.pending || 0) > 0 && tries2 < 6) { step(); return; }
            if (scanned > 0) {
              toast('AI ' + scanned + ' yeni maili taradı' + (spamTotal !== null ? ' · spam: ' + spamTotal : ''));
              var chip = wrap.querySelector('a[href*="folder=spam"] .ib-fold__n');
              if (chip && spamTotal !== null) { chip.textContent = spamTotal; }
            }
          });
        })();
      }
    }
  }
})();

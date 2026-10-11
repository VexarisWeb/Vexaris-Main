/* Vexaris landing — vanilla JS (build yok) */
(function () {
  'use strict';

  var LEGAL = window.VEX_LEGAL || {};
  /* site sahibi animasyon istedi: reduced-motion bakılmaksızın her şey oynar */
  var reduced = false;

  function $(s, r) { return (r || document).querySelector(s); }
  function $all(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }

  /* ---------- header: scroll + ilerleme + mobil menü ---------- */
  var headerBar = $('#headerBar');
  function onScroll() {
    var y = window.scrollY || 0;
    if (headerBar) {
      var sc = y > 28;
      headerBar.className = 'pointer-events-auto mx-auto flex items-center gap-4 transition-all duration-700 ease-[cubic-bezier(.22,1,.36,1)] ' +
        (sc ? 'mt-3 max-w-6xl rounded-full border border-[#262c33] bg-[#12151a]/92 px-4 sm:px-6 py-2.5 shadow-[0_18px_40px_-18px_rgba(0,0,0,.75)] backdrop-blur-md'
            : 'mt-0 max-w-7xl rounded-2xl border border-transparent bg-transparent px-2 sm:px-4 py-5');
    }
    var h = document.documentElement.scrollHeight - window.innerHeight;
    var sp = $('#scrollProgress');
    if (sp) sp.style.transform = 'scaleX(' + (h > 0 ? y / h : 0) + ')';
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll);

  var menuOpen = false;
  function paintBars() {
    var bars = $all('#menuBtn [data-bar]');
    if (!bars.length) return;
    if (menuOpen) {
      bars[0].style.top = '50%'; bars[0].style.transform = 'rotate(45deg)';
      bars[1].style.opacity = '0';
      bars[2].style.top = '50%'; bars[2].style.transform = 'rotate(-45deg)';
    } else {
      bars[0].style.top = '0'; bars[0].style.transform = '';
      bars[1].style.opacity = '1';
      bars[2].style.top = '100%'; bars[2].style.transform = '';
    }
  }
  function closeMenu() {
    menuOpen = false;
    var m = $('#mobileMenu');
    if (m) { m.classList.add('max-h-0', 'opacity-0'); m.classList.remove('max-h-96', 'opacity-100', 'mt-2'); }
    var b = $('#menuBtn');
    if (b) b.setAttribute('aria-expanded', 'false');
    paintBars();
  }

  /* ---------- reveal ---------- */
  function initReveal() {
    var els = $all('.reveal');
    if (!els.length) return;
    function showAll() {
      $all('.reveal').forEach(function (e) { e.classList.add('in'); });
    }
    if (!('IntersectionObserver' in window) || reduced) {
      showAll();
      return;
    }
    try {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
        });
      }, { threshold: 0.1, rootMargin: '0px 0px -5% 0px' });
      els.forEach(function (e) { io.observe(e); });
    } catch (e) {
      showAll();
      return;
    }
    // emniyet: 2.5 sn sonra görünmeyen kalırsa zorla göster (mobil WebView tuhaflıklarına karşı)
    setTimeout(showAll, 2500);
  }

  /* ---------- sayaç ---------- */
  function initCountUp() {
    var els = $all('[data-countup]');
    if (!els.length) return;
    function run(el) {
      var to = parseFloat(el.getAttribute('data-countup')) || 0;
      var dec = parseInt(el.getAttribute('data-dec') || '0', 10);
      var pre = el.getAttribute('data-prefix') || '';
      var suf = el.getAttribute('data-suffix') || '';
      function fmt(v) {
        return pre + v.toLocaleString('tr-TR', { minimumFractionDigits: dec, maximumFractionDigits: dec }) + suf;
      }
      if (reduced) { el.textContent = fmt(to); return; }
      var t0 = performance.now(), dur = 1400;
      (function tick(t) {
        var p = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - p, 3);
        el.textContent = fmt(to * e);
        if (p < 1) requestAnimationFrame(tick);
      })(t0);
    }
    if (!('IntersectionObserver' in window)) { els.forEach(run); return; }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { run(en.target); io.unobserve(en.target); }
      });
    }, { threshold: 0.4 });
    els.forEach(function (e) { io.observe(e); });
  }

  /* ---------- logo şeridi: kayar motor (smooth, rAF) ---------- */
  var marqueeHalf = 0, marqueeDirty = true;
  function marqueeMeasure() { marqueeDirty = true; }
  function initMarquee() {
    var track = $('#logoTrack');
    if (!track) return;
    // JS devrede: CSS animasyonunu kapat, kendimiz sür (daha smooth + garantili kayma)
    track.style.animation = 'none';
    track.style.willChange = 'transform';
    var x = 0, last = null, paused = false, frame = 0;
    var SPEED = 45; // px/sn
    function measure() {
      var half = track.children[0];
      marqueeHalf = half ? half.offsetWidth : 0;
      marqueeDirty = false;
    }
    measure();
    window.addEventListener('resize', marqueeMeasure);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(marqueeMeasure);
    window.addEventListener('load', marqueeMeasure);
    var mVisible = true;
    if ('IntersectionObserver' in window) {
      try {
        new IntersectionObserver(function (ens) {
          mVisible = ens && ens[0] ? !!ens[0].isIntersecting : true;
        }, { threshold: 0 }).observe(track);
      } catch (e) { mVisible = true; }
    }
    var zone = track.parentElement;
    if (zone) {
      zone.addEventListener('mouseenter', function () { paused = true; });
      zone.addEventListener('mouseleave', function () { paused = false; });
    }
    // kırık görseli gizle (bozuk ikon şeridi bozmasın)
    $all('img', track).forEach(function (img) {
      function hide() {
        var wrap = img.closest('span.group');
        if (wrap) { wrap.style.display = 'none'; marqueeMeasure(); }
      }
      function done() { marqueeMeasure(); }
      if (img.complete) { if (img.naturalWidth === 0) hide(); else done(); }
      else {
        img.addEventListener('error', hide);
        img.addEventListener('load', done);
      }
    });
    function step(t) {
      if (last === null) last = t;
      var dt = Math.min(0.05, (t - last) / 1000);
      last = t;
      frame++;
      if (marqueeDirty || frame % 240 === 0) measure();
      if (!paused && !document.hidden && mVisible && marqueeHalf > 0) {
        x -= SPEED * dt;
        if (-x >= marqueeHalf) x += marqueeHalf;
        track.style.transform = 'translateX(' + x + 'px)';
      }
      requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  /* ---------- hero dönen kelimeler ---------- */
  var ROT_WORDS_TR = ['Güçlü Altyapı', 'Modern Entegrasyonlar', 'Yüksek Performans', 'Otomatik Teslimat'];
  var ROT_WORDS_EN = ['Powerful Infrastructure', 'Modern Integrations', 'High Performance', 'Automatic Delivery'];
  var ROT_WORDS = (document.documentElement.lang || 'en').toLowerCase().indexOf('tr') === 0 ? ROT_WORDS_TR : ROT_WORDS_EN;
  function initRotate() {
    var el = $('#rotWord');
    if (!el || !ROT_WORDS.length) return;
    if (reduced) { el.textContent = ROT_WORDS[0]; return; }
    var i = 0;
    setInterval(function () {
      if (document.hidden) return;
      el.classList.add('opacity-0', '-translate-y-2');
      setTimeout(function () {
        i = (i + 1) % ROT_WORDS.length;
        el.textContent = ROT_WORDS[i];
        el.classList.remove('opacity-0', '-translate-y-2');
      }, 350);
    }, 3000);
  }

  /* ---------- referans şeridi: yavaş akan slider ---------- */
  function initRefSlider() {
    var track = $('#refTrack');
    if (!track) return;
    track.style.willChange = 'transform';
    var x = 0, last = null, paused = false, half = 0, dirty = true, frame = 0;
    var SPEED = 30; // px/sn — yavaş akış
    function measure() {
      half = track.scrollWidth / 2;
      dirty = false;
    }
    measure();
    window.addEventListener('resize', function () { dirty = true; });
    window.addEventListener('load', function () { dirty = true; });
    var rVisible = true;
    if ('IntersectionObserver' in window) {
      try {
        new IntersectionObserver(function (ens) {
          rVisible = ens && ens[0] ? !!ens[0].isIntersecting : true;
        }, { threshold: 0 }).observe(track);
      } catch (e) { rVisible = true; }
    }
    var zone = $('#refSlider') || track.parentElement;
    if (zone) {
      zone.addEventListener('mouseenter', function () { paused = true; });
      zone.addEventListener('mouseleave', function () { paused = false; });
    }
    function step(t) {
      if (last === null) last = t;
      var dt = Math.min(0.05, (t - last) / 1000);
      last = t;
      frame++;
      if (dirty || frame % 240 === 0) measure();
      if (!paused && !document.hidden && rVisible && half > 0) {
        x -= SPEED * dt;
        if (-x >= half) x += half;
        track.style.transform = 'translateX(' + x + 'px)';
      } else if (document.hidden) {
        last = null;
      }
      requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  /* ---------- SSS akordeon ---------- */
  function initFaq() {
    $all('[data-faq]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var body = document.getElementById('faq-' + btn.getAttribute('data-faq'));
        var card = btn.closest('.faq-card');
        var chev = btn.querySelector('[data-chev]');
        var isOpen = !!body && body.classList.contains('open');
        $all('.acc-body.open').forEach(function (b) { b.classList.remove('open'); });
        $all('.faq-card').forEach(function (c) { c.classList.remove('border-[#4f8fc0]/40'); });
        $all('[data-chev]').forEach(function (c) { c.classList.remove('border-[#4f8fc0]/50', 'text-[#4f8fc0]', 'rotate-180'); });
        if (!isOpen && body) {
          body.classList.add('open');
          if (card) card.classList.add('border-[#4f8fc0]/40');
          if (chev) chev.classList.add('border-[#4f8fc0]/50', 'text-[#4f8fc0]', 'rotate-180');
          btn.setAttribute('aria-expanded', 'true');
        } else {
          btn.setAttribute('aria-expanded', 'false');
        }
      });
    });
  }

  /* ---------- bilgi modalı (yasal + durum) ---------- */
  function openInfo(act) {
    var m = $('#infoModal');
    if (!m) return;
    var title = $('#infoTitle'), meta = $('#infoMeta'), body = $('#infoBody');
    if (act === 'status') {
      if (title) title.textContent = 'Durum sayfası';
      if (meta) meta.textContent = 'status.vexaris';
      if (body) {
        body.innerHTML = '';
        var ul = document.createElement('ul');
        ul.className = 'mt-1 space-y-2.5';
        [['Mağaza & API', '%99,99'], ["Ödeme webhook'ları", '%99,97'], ['Teslimat servisi', '%99,99'], ['Dosya indirme (CDN)', '%99,95'], ['Discord webhook', '%99,90']].forEach(function (s) {
          var li = document.createElement('li');
          li.className = 'flex items-center justify-between rounded-lg border border-[#262c33] bg-[#0e1114] px-4 py-3';
          li.innerHTML = '<span class="inline-flex items-center gap-2.5 text-[13.5px] font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-[#74c091] pulse-dot text-[#74c091]"></span></span><span class="font-mono2 text-[11.5px] text-[#74c091]"></span>';
          li.firstChild.appendChild(document.createTextNode(s[0]));
          li.lastChild.textContent = s[1];
          ul.appendChild(li);
        });
        body.appendChild(ul);
        var p = document.createElement('p');
        p.className = 'mt-4 font-mono2 text-[11px] text-[#7d858e]';
        p.textContent = 'son olay: v2.4.1 yayını • 12 Şub 2026 • tamamlandı • kesinti yok';
        body.appendChild(p);
      }
    } else {
      var doc = LEGAL[act];
      if (!doc) return;
      if (title) title.textContent = doc.title;
      if (meta) meta.textContent = doc.updated;
      if (body) {
        body.innerHTML = '';
        var wrap = document.createElement('div');
        wrap.className = 'space-y-4';
        doc.body.forEach(function (par) {
          var el = document.createElement('p');
          el.className = 'text-[13.5px] leading-relaxed text-[#9aa3ac]';
          el.textContent = par;
          wrap.appendChild(el);
        });
        body.appendChild(wrap);
      }
    }
    m.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }
  function closeInfo() {
    var m = $('#infoModal');
    if (!m || m.classList.contains('hidden')) return;
    m.classList.add('hidden');
    document.body.style.overflow = '';
  }

  /* ---------- çerez: gerçek onay yönetimi ---------- */
  var CK_KEY = 'vex-cookie-consent-v2'; // v2: yeni kutu herkese bir kez gösterilir
  var CK_TTL = 365 * 864e5; // 1 yıl
  function ckRead() {
    try {
      var c = JSON.parse(localStorage.getItem(CK_KEY));
      if (c && c.ts && (Date.now() - c.ts) < CK_TTL) return c;
    } catch (e) {}
    return null;
  }
  function ckWrite(v) {
    try {
      localStorage.setItem(CK_KEY, JSON.stringify({
        necessary: true, analytics: !!v.analytics, marketing: !!v.marketing, ts: Date.now()
      }));
    } catch (e) {}
  }
  // Onaylanan kategorilere göre üçüncü parti scriptleri yükler.
  // Analitik ID tanımlıysa (window.VEX_GA_ID) yalnızca analitik onayında yüklenir.
  function ckApply(c) {
    if (!c) return;
    if (c.analytics && window.VEX_GA_ID && !window.__vexAnaLoaded) {
      window.__vexAnaLoaded = true;
      var s = document.createElement('script');
      s.async = true;
      s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(window.VEX_GA_ID);
      document.head.appendChild(s);
      window.dataLayer = window.dataLayer || [];
      window.gtag = function () { window.dataLayer.push(arguments); };
      window.gtag('js', new Date());
      window.gtag('config', window.VEX_GA_ID, { anonymize_ip: true });
    }
  }
  function ckShow(animate) {
    var bar = $('#cookieBar');
    if (!bar) return;
    syncCkToggles(ckRead());
    bar.classList.remove('hidden', 'cookie-hide');
    if (animate === false) { bar.classList.add('cookie-show'); return; }
    void bar.offsetWidth;
    bar.classList.add('cookie-show');
  }
  function ckHide() {
    var bar = $('#cookieBar');
    if (!bar || bar.classList.contains('hidden')) return;
    bar.classList.remove('cookie-show');
    bar.classList.add('cookie-hide');
    setTimeout(function () { bar.classList.add('hidden'); bar.classList.remove('cookie-hide'); }, 380);
  }
  function syncCkToggles(c) {
    var a = $('#ckAnalytics'), m = $('#ckMarketing');
    if (a) a.checked = c ? !!c.analytics : false;
    if (m) m.checked = c ? !!c.marketing : false;
  }
  function initCookie() {
    // eski anahtarı taşı (varsa): yalnızca zorunlu olarak say
    var cur = ckRead();
    ckApply(cur);
    if (!cur) setTimeout(function () { ckShow(true); }, 1600);

    var more = $('#ckMore'), accept = $('#ckAccept'), reject = $('#ckReject'), panel = $('#ckSettings');
    var settingsOpen = false;
    function paintBtns() {
      if (more) more.textContent = settingsOpen ? 'Geri' : 'Diğer Seçenekler';
      if (accept) accept.textContent = settingsOpen ? 'Kaydet' : 'Kabul Et';
    }
    if (more) more.addEventListener('click', function () {
      settingsOpen = !settingsOpen;
      if (panel) panel.classList.toggle('active', settingsOpen);
      paintBtns();
    });
    if (accept) accept.addEventListener('click', function () {
      var a = $('#ckAnalytics'), m = $('#ckMarketing');
      var v = settingsOpen
        ? { analytics: a && a.checked, marketing: m && m.checked }
        : { analytics: true, marketing: true };
      ckWrite(v);
      ckApply(ckRead());
      ckHide();
    });
    if (reject) reject.addEventListener('click', function () {
      ckWrite({ analytics: false, marketing: false });
      ckHide();
    });
    // footer'dan tercihleri yeniden açma
    $all('[data-cookie-open]').forEach(function (b) {
      b.addEventListener('click', function () {
        if (panel) { settingsOpen = true; panel.classList.add('active'); paintBtns(); }
        ckShow(true);
      });
    });
    paintBtns();
  }

  /* ---------- paket kartları: mouse ile parlama (dokunmatikte gereksiz) ---------- */
  function initPlanGlow() {
    try {
      if (window.matchMedia && window.matchMedia('(hover: none)').matches) return;
    } catch (e) {}
    $all('.plan-card').forEach(function (card) {
      card.addEventListener('pointermove', function (e) {
        var r = card.getBoundingClientRect();
        card.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100).toFixed(1) + '%');
        card.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100).toFixed(1) + '%');
      });
    });
  }

  /* ---------- nasıl çalışır: scroll ile hover'lanma ---------- */
  function initStepspy() {
    var items = $all('#how-it-works ol > li');
    if (!items.length) return;
    var ticking = false;
    function paint() {
      ticking = false;
      var mid = window.innerHeight * 0.6;
      items.forEach(function (li) {
        var r = li.getBoundingClientRect();
        li.classList.toggle('step-on', r.top < mid && r.bottom > mid * 0.4);
      });
    }
    function onScrollSpy() {
      if (!ticking) { ticking = true; requestAnimationFrame(paint); }
    }
    window.addEventListener('scroll', onScrollSpy, { passive: true });
    window.addEventListener('resize', onScrollSpy);
    paint();
  }

  /* ---------- özellik arama ---------- */
  function initFeatSearch() {
    var inp = $('#featSearch'), grid = $('#featGrid');
    if (!inp || !grid) return;
    var cards = $all('[data-search]', grid);
    var empty = $('#featEmpty'), count = $('#featCount');
    function norm(s) { return (s || '').toLocaleLowerCase('tr-TR'); }
    function run() {
      var q = norm(inp.value.trim()), n = 0;
      cards.forEach(function (c) {
        var hit = !q || norm(c.getAttribute('data-search')).indexOf(q) > -1;
        c.style.display = hit ? '' : 'none';
        if (hit) n++;
      });
      if (empty) empty.classList.toggle('hidden', n > 0);
      if (count) count.textContent = n + ' özellik listeleniyor';
    }
    inp.addEventListener('input', run);
    run();
  }

  /* ---------- başlat ---------- */
  function safe(fn) {
    try { fn(); } catch (e) { /* bir modül patlasa da diğerleri çalışsın */ }
  }
  document.addEventListener('DOMContentLoaded', function () {
    safe(onScroll);
    safe(initReveal);
    safe(initCountUp);
    safe(initMarquee);
    safe(initRotate);
    safe(initStepspy);
    safe(initRefSlider);
    safe(initFeatSearch);
    safe(initFaq);
    safe(initCookie);
    safe(initPlanGlow);

    var mb = $('#menuBtn');
    if (mb) mb.addEventListener('click', function () {
      menuOpen = !menuOpen;
      var m = $('#mobileMenu');
      if (m) {
        m.classList.toggle('max-h-0', !menuOpen);
        m.classList.toggle('opacity-0', !menuOpen);
        m.classList.toggle('max-h-96', menuOpen);
        m.classList.toggle('opacity-100', menuOpen);
        m.classList.toggle('mt-2', menuOpen);
      }
      mb.setAttribute('aria-expanded', menuOpen ? 'true' : 'false');
      paintBars();
    });
    $all('[data-close-menu]').forEach(function (a) {
      a.addEventListener('click', closeMenu);
    });

    $all('[data-legal]').forEach(function (b) {
      b.addEventListener('click', function () { openInfo(b.getAttribute('data-legal')); });
    });
    $all('[data-close="info"]').forEach(function (b) {
      b.addEventListener('click', closeInfo);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { closeInfo(); closeMenu(); }
    });
  });
})();

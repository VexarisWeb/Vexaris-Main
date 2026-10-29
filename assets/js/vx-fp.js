/**
 * Vexaris — cihaz imzası toplayıcı (FingerprintJS v5, self-host)
 * Sadece /register, /login, /dashboard/stores sayfalarında yüklenir.
 * Oturum başına tek POST atar; sunucu tarafı HMAC ile takma adlaştırır.
 */
(function () {
  'use strict';
  var LS = 'vxfp1';
  var posted = false;

  function h32(str) {
    var a = 0x811c9dc5, b = 0x1000193;
    for (var i = 0; i < str.length; i++) {
      var c = str.charCodeAt(i);
      a = Math.imul(a ^ c, 16777619) >>> 0;
      b = Math.imul(b + c * (i + 7), 2246822507) >>> 0;
    }
    return ('0000000' + a.toString(16)).slice(-8) + ('0000000' + b.toString(16)).slice(-8);
  }

  function canvasBit() {
    try {
      var c = document.createElement('canvas');
      c.width = 240; c.height = 60;
      var x = c.getContext('2d');
      if (!x) return '';
      var g = x.createLinearGradient(0, 0, 240, 60);
      g.addColorStop(0, '#f60'); g.addColorStop(1, '#069');
      x.fillStyle = g; x.fillRect(0, 0, 240, 60);
      x.font = '16px "Arial"'; x.fillStyle = 'rgba(20,30,40,.75)';
      x.fillText('Vexaris#fp#2026 \u2601', 4, 26);
      x.strokeStyle = 'rgba(255,255,255,.6)'; x.arc(200, 30, 18, 0, Math.PI * 1.5); x.stroke();
      return h32(c.toDataURL());
    } catch (e) { return ''; }
  }

  function webglBit() {
    try {
      var c = document.createElement('canvas');
      var gl = c.getContext('webgl') || c.getContext('experimental-webgl');
      if (!gl) return '';
      var d = gl.getExtension('WEBGL_debug_renderer_info');
      var parts = [
        d ? gl.getParameter(d.UNMASKED_VENDOR_WEBGL) : gl.getParameter(gl.VENDOR),
        d ? gl.getParameter(d.UNMASKED_RENDERER_WEBGL) : gl.getParameter(gl.RENDERER),
        gl.getParameter(gl.MAX_TEXTURE_SIZE), gl.getParameter(gl.MAX_RENDERBUFFER_SIZE),
        gl.getParameter(gl.VERSION), gl.getParameter(gl.SHADING_LANGUAGE_VERSION)
      ];
      return h32(parts.join('|'));
    } catch (e) { return ''; }
  }

  function fontsBit() {
    try {
      var list = ['Arial', 'Arial Black', 'Calibri', 'Cambria', 'Comic Sans MS', 'Consolas',
        'Courier New', 'Georgia', 'Impact', 'Segoe UI', 'Tahoma', 'Times New Roman', 'Trebuchet MS', 'Verdana'];
      var base = 'monospace', span = document.createElement('span');
      span.style.cssText = 'position:absolute;left:-9999px;top:0;font-size:72px;visibility:hidden';
      span.textContent = 'mmmmmmmmmmlli123';
      document.body.appendChild(span);
      span.style.fontFamily = base;
      var bw = span.offsetWidth, bh = span.offsetHeight, found = '';
      for (var i = 0; i < list.length; i++) {
        span.style.fontFamily = '"' + list[i] + '",' + base;
        if (span.offsetWidth !== bw || span.offsetHeight !== bh) found += '1';
        else found += '0';
      }
      document.body.removeChild(span);
      return h32(found);
    } catch (e) { return ''; }
  }

  function hwBit() {
    var s = screen || {};
    return h32([
      navigator.hardwareConcurrency || 0,
      navigator.deviceMemory || 0,
      navigator.maxTouchPoints || 0,
      s.width || 0, s.height || 0, s.colorDepth || 0,
      window.devicePixelRatio || 1,
      navigator.userAgentData ? (navigator.userAgentData.platform || '') : '',
      navigator.userAgentData && navigator.userAgentData.mobile ? 'm' : 'd'
    ].join('|'));
  }

  function locBit() {
    try {
      return h32([
        Intl.DateTimeFormat().resolvedOptions().timeZone || '',
        (navigator.languages || [navigator.language || '']).join(','),
        new Date().getTimezoneOffset(),
        navigator.connection ? (navigator.connection.type || navigator.connection.effectiveType || '') : ''
      ].join('|'));
    } catch (e) { return ''; }
  }

  function fill(vid) {
    window.VXFP = window.VXFP || {};
    window.VXFP.vid = vid || '';
    var els = document.querySelectorAll('input[name="vid"],#vidField');
    for (var i = 0; i < els.length; i++) els[i].value = vid || '';
  }

  function send(vid, bits) {
    if (posted) return;
    posted = true;
    try { sessionStorage.setItem(LS + '_sent', '1'); } catch (e) {}
    try {
      var body = JSON.stringify({ vid: vid, bits: bits });
      if (navigator.sendBeacon) {
        navigator.sendBeacon('/api/fp.php', new Blob([body], { type: 'application/json' }));
        return;
      }
      fetch('/api/fp.php', {
        method: 'POST', body: body, keepalive: true,
        headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin'
      }).catch(function () {});
    } catch (e) {}
  }

  function start() {
    var cached = '', sent = false;
    try {
      cached = sessionStorage.getItem(LS) || '';
      sent = sessionStorage.getItem(LS + '_sent') === '1';
    } catch (e) {}
    if (cached && /^[0-9a-f]{32}$/.test(cached)) fill(cached);
    if (cached && sent) return;
    if (typeof FingerprintJS === 'undefined' || !FingerprintJS.load) return;
    var bits = { canvas: canvasBit(), webgl: webglBit(), fonts: fontsBit(), hw: hwBit(), loc: locBit() };
    FingerprintJS.load().then(function (fp) { return fp.get(); }).then(function (res) {
      var vid = String((res && res.visitorId) || '');
      if (!/^[0-9a-f]{32}$/.test(vid)) return;
      fill(vid);
      try { sessionStorage.setItem(LS, vid); } catch (e) {}
      send(vid, bits);
    }).catch(function () {
      if (cached) send(cached, bits);
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { passive: true });
  else start();
})();

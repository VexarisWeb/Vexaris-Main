<?php
// modals.php — bilgi modalı + çerez barı (index.php ve ozellikler.php ortak kullanır)
// Gerekli: vex_icon(), LEGAL verisi JS tarafında (window.VEX_LEGAL)
?>
  <!-- ============ BİLGİ MODALI ============ -->
  <div id="infoModal" class="hidden fixed inset-0 z-[66] grid place-items-center p-4 sm:p-6">
    <div class="absolute inset-0 bg-black/70 backdrop-blur-[3px] fade-in" data-close="info"></div>
    <div class="modal-in relative w-full max-w-md card overflow-hidden shadow-[0_50px_100px_-40px_rgba(0,0,0,.95)] max-h-[85vh] flex flex-col">
      <div class="flex items-center justify-between px-6 py-4 border-b border-[#262c33] bg-[#0e1114] shrink-0">
        <div><h3 id="infoTitle" class="font-display font-bold text-[15px] tracking-tight"></h3><span id="infoMeta" class="font-mono2 text-[10.5px] text-[#7d858e]"></span></div>
        <button data-close="info" aria-label="Close" class="btn-ghost w-9 h-9 grid place-items-center"><?= vex_icon('x','w-4 h-4') ?></button>
      </div>
      <div id="infoBody" class="px-6 py-6 overflow-y-auto"></div>
    </div>
  </div>

  <!-- ============ ÇEREZ ============ -->
  <div id="cookieBar" class="hidden fixed bottom-5 left-5 z-[62] w-[calc(100%-2.5rem)] max-w-sm card p-5 pt-6 shadow-[0_30px_60px_-25px_rgba(0,0,0,.9)]">
    <div class="flex items-center gap-2.5">
      <span class="grid place-items-center w-8 h-8 rounded-full bg-[#4f8fc0]/15 text-[#4f8fc0] shrink-0"><i class="fa-solid fa-cookie text-[15px]"></i></span>
      <h4 class="font-display font-bold text-[14px] tracking-tight">Cookie preferences</h4>
    </div>
    <p class="mt-2.5 text-[12.5px] leading-relaxed text-[#9aa3ac]">Essential cookies for sessions and security are always on. Analytics and marketing cookies load only with your consent. See our <button data-legal="cookies" class="underline underline-offset-2 text-[#c8ccd1] hover:text-white transition-colors cursor-pointer">Cookie Policy</button> for details.</p>
    <div id="ckSettings" class="ck-settings mt-4 space-y-2.5">
      <div class="flex items-center justify-between gap-3 text-[13px] font-semibold text-[#c8ccd1]">
        <span>Essential cookies</span>
        <label class="ck-switch"><input type="checkbox" checked disabled /><span class="ck-track"></span></label>
      </div>
      <div class="flex items-center justify-between gap-3 text-[13px] font-semibold text-[#c8ccd1]">
        <span>Analytics cookies</span>
        <label class="ck-switch"><input type="checkbox" id="ckAnalytics" /><span class="ck-track"></span></label>
      </div>
      <div class="flex items-center justify-between gap-3 text-[13px] font-semibold text-[#c8ccd1]">
        <span>Marketing cookies</span>
        <label class="ck-switch"><input type="checkbox" id="ckMarketing" /><span class="ck-track"></span></label>
      </div>
    </div>
    <div class="mt-4 flex items-center justify-between gap-2">
      <button id="ckMore" type="button" class="text-[12.5px] font-semibold text-[#9aa3ac] hover:text-white hover:underline underline-offset-2 transition-colors cursor-pointer">More Options</button>
      <div class="flex items-center gap-2">
        <button id="ckReject" type="button" class="px-4 py-2.5 text-[12.5px] font-semibold text-[#9aa3ac] hover:text-white transition-colors cursor-pointer">Reject</button>
        <button id="ckAccept" type="button" class="btn-primary px-6 py-2.5 text-[12.5px] cursor-pointer">Accept</button>
      </div>
    </div>
  </div>

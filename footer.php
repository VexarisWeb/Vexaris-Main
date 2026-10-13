<?php
// footer.php — CTA bandı + footer
// Gerekli: $FOOTER_COLS, $VEX_LOGO, vex_icon(), $BASE ('' veya 'index.php')
$BASE = isset($BASE) ? $BASE : '';
?>
<section id="cta" class="relative py-24 md:py-28 border-t border-[#262c33] scroll-mt-24 overflow-hidden">
  <div class="absolute inset-0 opacity-[.5]" style="background-image:repeating-linear-gradient(-45deg, rgba(233,231,226,.035) 0 1px, transparent 1px 14px)" aria-hidden="true"></div>
  <div class="relative max-w-7xl mx-auto px-5 sm:px-8">
    <div class="reveal card p-8 md:p-14 grid lg:grid-cols-12 gap-8 items-center overflow-hidden relative">
      <div class="absolute -left-20 -bottom-24 w-72 h-72 rounded-full bg-[#4f8fc0]/[.07] blur-3xl" aria-hidden="true"></div>
      <div class="lg:col-span-7 relative">
        <span class="font-mono2 text-[11px] uppercase tracking-wider rounded-full px-2.5 py-1 border border-[#4f8fc0]/40 text-[#4f8fc0]">0% platform sales commission</span>
        <h2 class="font-display font-extrabold tracking-tight text-3xl sm:text-4xl lg:text-[2.8rem] leading-[1.05] mt-4">
          Open your store today,<br />keep 100% of every sale.
        </h2>
        <p class="mt-4 max-w-lg text-[15px] leading-relaxed text-[#9aa3ac]">
          Vexaris takes no cut from your sales — no per-order percentage, no revenue share.
          Your branded storefront, automated delivery, and license inventory are ready
          the moment your account is created. Start free, upgrade as you grow.
        </p>
      </div>
      <div class="lg:col-span-5 relative flex flex-col sm:flex-row lg:flex-col gap-3 lg:items-end lg:pl-8">
        <?php if (!empty($loggedIn)): ?>
        <a href="/dashboard" class="btn-primary w-full sm:w-auto px-7 py-3.5 text-sm inline-flex items-center justify-center gap-2">
          Go to Panel <?= vex_icon('arrow', 'w-4 h-4') ?>
        </a>
        <?php else: ?>
        <a href="<?= $BASE ?>#pricing" class="btn-primary w-full sm:w-auto px-7 py-3.5 text-sm inline-flex items-center justify-center gap-2">
          Sign up free <?= vex_icon('arrow', 'w-4 h-4') ?>
        </a>
        <?php endif; ?>
        <a href="<?= $BASE ?>#top" class="btn-ghost w-full sm:w-auto px-7 py-3.5 text-sm font-semibold text-center">Explore the demo panel</a>
        <span class="font-mono2 text-[11px] text-[#7d858e] lg:text-right">0% commission • free plan • automated delivery</span>
      </div>
    </div>
  </div>
</section>

<footer class="relative border-t border-[#262c33] bg-[#0e1114]">
  <div class="max-w-7xl mx-auto px-5 sm:px-8 pt-16 pb-8">
    <div class="grid sm:grid-cols-2 lg:grid-cols-12 gap-10 lg:gap-8">
      <div class="sm:col-span-2 lg:col-span-4">
        <a href="<?= $BASE ?>#top" class="inline-flex items-center gap-2.5 group">
          <img src="<?= htmlspecialchars($VEX_LOGO) ?>" alt="Vexaris" class="h-7 w-auto" />
        </a>
        <p class="mt-5 max-w-sm text-[14px] leading-relaxed text-[#9aa3ac]">
          Digital product sales infrastructure. A powerful marketplace platform with automated
          delivery for e-pins, e-books, license keys, software packages, and more.
        </p>
        <div class="mt-6 flex gap-2.5">
          <a href="https://discord.com" target="_blank" rel="noreferrer noopener" aria-label="Discord" class="grid place-items-center w-10 h-10 rounded-lg border border-[#262c33] text-[#9aa3ac] hover:text-[#5865F2] hover:border-[#5865F2]/60 hover:-translate-y-0.5 hover:scale-105 transition-all duration-300"><i class="fa-brands fa-discord text-[18px]"></i></a>
          <a href="https://x.com" target="_blank" rel="noreferrer noopener" aria-label="X" class="grid place-items-center w-10 h-10 rounded-lg border border-[#262c33] text-[#9aa3ac] hover:text-white hover:border-white/50 hover:-translate-y-0.5 hover:scale-105 transition-all duration-300"><i class="fa-brands fa-x-twitter text-[16px]"></i></a>
          <a href="https://github.com" target="_blank" rel="noreferrer noopener" aria-label="GitHub" class="grid place-items-center w-10 h-10 rounded-lg border border-[#262c33] text-[#9aa3ac] hover:text-white hover:border-white/50 hover:-translate-y-0.5 hover:scale-105 transition-all duration-300"><i class="fa-brands fa-github text-[18px]"></i></a>
          <a href="https://instagram.com" target="_blank" rel="noreferrer noopener" aria-label="Instagram" class="grid place-items-center w-10 h-10 rounded-lg border border-[#262c33] text-[#9aa3ac] hover:text-[#E1306C] hover:border-[#E1306C]/60 hover:-translate-y-0.5 hover:scale-105 transition-all duration-300"><i class="fa-brands fa-instagram text-[18px]"></i></a>
        </div>
      </div>

      <?php foreach ($FOOTER_COLS as $col): ?>
        <nav class="lg:col-span-2 sm:col-span-1" aria-label="<?= htmlspecialchars($col['title']) ?>">
          <h3 class="font-mono2 text-[11px] uppercase tracking-[0.18em] text-[#7d858e]"><?= htmlspecialchars($col['title']) ?></h3>
          <ul class="mt-5 space-y-3">
            <?php foreach ($col['links'] as $l): ?>
              <li>
                <?php if (!empty($l['act'])): ?>
                  <button data-legal="<?= htmlspecialchars($l['act']) ?>" class="link-u text-[13.5px] font-semibold text-[#c8ccd1] hover:text-white transition-colors cursor-pointer"><?= htmlspecialchars($l['label']) ?></button>
                <?php else: ?>
                  <?php $href = $l['href']; if (strpos($href, '#') === 0) $href = $BASE . $href; $ext = (strpos($href, 'http://') === 0 || strpos($href, 'https://') === 0); ?>
                  <a href="<?= htmlspecialchars($href) ?>"<?= $ext ? ' target="_blank" rel="noopener"' : '' ?> class="link-u text-[13.5px] font-semibold text-[#c8ccd1] hover:text-white transition-colors"><?= htmlspecialchars($l['label']) ?></a>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </nav>
      <?php endforeach; ?>

      <div class="sm:col-span-2 lg:col-span-2 lg:border-l border-[#262c33] lg:pl-6">
        <h3 class="font-mono2 text-[11px] uppercase tracking-[0.18em] text-[#7d858e]">OUR PARTNERS</h3>
        <div class="mt-6 flex flex-col gap-4">
          <a href="https://www.sourcesetups.com" target="_blank" rel="noopener" aria-label="SourceSetups" class="flex h-11 w-[200px] items-center opacity-80 hover:opacity-100 transition">
            <img src="/assets/images/sourcesetups.png" alt="SourceSetups" loading="lazy" width="640" height="172" class="h-8 w-auto max-w-full object-contain object-left" />
          </a>
          <a href="https://vasthosting.cloud" target="_blank" rel="noopener" aria-label="Vast Hosting" class="flex h-11 w-[200px] items-center opacity-80 hover:opacity-100 transition">
            <img src="/assets/images/vasthosting.png" alt="Vast Hosting" loading="lazy" width="1258" height="245" class="h-6 w-auto max-w-full object-contain object-left" />
          </a>
        </div>
      </div>
    </div>

    <div class="mt-14 pt-6 border-t border-[#262c33] flex flex-col lg:flex-row items-center justify-between gap-4 text-center lg:text-left">
      <p class="text-[12.5px] text-[#7d858e] order-1">2026 Vexaris. All rights reserved. <button data-cookie-open class="underline underline-offset-2 hover:text-white transition-colors cursor-pointer">Cookie Preferences</button></p>
      <p class="text-[11px] tracking-wide text-[#9aa3ac]/60 order-2 lg:order-2">Thanks to <a href="https://www.wellsetups.com/" target="_blank" rel="nofollow noopener" class="font-semibold text-[#9aa3ac]/80 hover:text-[#e9e7e2] underline underline-offset-4 decoration-[#9aa3ac]/30 transition-colors">Proomp for their support.</a></p>
      <p class="inline-flex items-center gap-2 text-[12.5px] text-[#7d858e] order-3">
        <?= vex_icon('shield', 'w-4 h-4 text-[#4f8fc0]') ?> Backed by secure payment infrastructure.
      </p>
    </div>
  </div>

</footer>

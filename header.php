<?php
// header.php — site üst barı
// Gerekli: $VEX_LOGO, vex_icon(), $BASE ('' veya 'index.php')
$BASE = isset($BASE) ? $BASE : '';
$NAV = [
  ['label' => 'Home', 'href' => $BASE === '' ? '/' : $BASE],
  ['label' => 'Features', 'href' => '/features'],
  ['label' => 'Pricing', 'href' => '/pricing'],
  ['label' => 'Blog', 'href' => '/blog'],
];
?>
<header class="fixed inset-x-0 top-0 z-50 px-3 sm:px-5 pointer-events-none">
  <!-- kaydırma ilerleme çubuğu -->
  <div class="pointer-events-none fixed inset-x-0 top-0 h-[2px] z-50">
    <div id="scrollProgress" class="h-full bg-[#4f8fc0] origin-left transition-transform duration-150 ease-out" style="transform:scaleX(0)"></div>
  </div>

  <div id="headerBar" class="pointer-events-auto mx-auto flex items-center gap-4 transition-all duration-700 ease-[cubic-bezier(.22,1,.36,1)] mt-0 max-w-7xl rounded-2xl border border-transparent bg-transparent px-2 sm:px-4 py-5">
    <a href="<?= $BASE ?>#top" class="flex items-center gap-2.5 shrink-0 group" aria-label="Vexaris home">
      <img src="<?= htmlspecialchars($VEX_LOGO) ?>" alt="Vexaris"<?= function_exists('vx_img_attrs') ? vx_img_attrs($VEX_LOGO) : '' ?> class="h-6 sm:h-7 w-auto transition-transform duration-500 group-hover:scale-[1.04]" />
    </a>

    <nav class="hidden lg:flex items-center gap-7 mx-auto text-[13.5px] font-semibold text-[#c8ccd1]">
      <?php foreach ($NAV as $n): ?>
        <a href="<?= $n['href'] ?>" class="link-u hover:text-white transition-colors"><?= $n['label'] ?></a>
      <?php endforeach; ?>
      <!-- SAHİBİ BAĞLAYACAK: href'leri kendi linklerinle değiştir -->
      <a id="linkDemo" href="https://demo.vexaris.net" target="_blank" rel="noopener" class="link-u hover:text-white transition-colors">Demo</a>
      <a id="linkLicense" href="/license-check" class="link-u hover:text-white transition-colors">Check License</a>
    </nav>

    <div class="hidden lg:flex items-center gap-2.5 ml-auto">
      <?php if (!empty($loggedIn)): ?>
      <a href="/dashboard" class="px-3.5 py-2 text-[13.5px] font-semibold text-[#c8ccd1] hover:text-white transition-colors">My Panel</a>
      <?php if (function_exists('is_admin') && is_admin()): ?>
      <a href="/admin" class="px-3.5 py-2 text-[13.5px] font-semibold text-[#c8ccd1] hover:text-white transition-colors">Admin</a>
      <?php endif; ?>
      <a href="/logout" class="btn-primary px-4 py-2 text-[13.5px]">Log Out</a>
      <?php else: ?>
      <a href="/login" class="px-3.5 py-2 text-[13.5px] font-semibold text-[#c8ccd1] hover:text-white transition-colors">Log In</a>
      <a href="/register" class="btn-primary px-4 py-2 text-[13.5px]">Sign Up</a>
      <?php endif; ?>
    </div>

    <div class="flex lg:hidden items-center gap-2 ml-auto">
      <button id="menuBtn" aria-label="Toggle menu" aria-expanded="false" class="btn-ghost grid place-items-center w-10 h-10">
        <span class="relative block w-4 h-3">
          <span data-bar="1" class="absolute left-0 h-[1.8px] w-full bg-current transition-all duration-300 top-0"></span>
          <span data-bar="2" class="absolute left-0 top-1/2 h-[1.8px] w-full bg-current transition-all duration-200 opacity-100"></span>
          <span data-bar="3" class="absolute left-0 h-[1.8px] w-full bg-current transition-all duration-300 top-full"></span>
        </span>
      </button>
    </div>
  </div>

  <!-- mobil menü -->
  <div id="mobileMenu" class="pointer-events-auto lg:hidden mx-auto max-w-4xl overflow-hidden transition-all duration-400 [transition-timing-function:cubic-bezier(.22,1,.36,1)] max-h-0 opacity-0">
    <div class="rounded-2xl border border-[#262c33] bg-[#12151a]/97 backdrop-blur-md p-4 flex flex-col gap-1">
      <?php foreach ($NAV as $n): ?>
        <a href="<?= $n['href'] ?>" data-close-menu class="px-3 py-2.5 rounded-lg text-sm font-semibold text-[#c8ccd1] hover:bg-white/5 hover:text-white transition-colors"><?= $n['label'] ?></a>
      <?php endforeach; ?>
      <!-- SAHİBİ BAĞLAYACAK: href'leri kendi linklerinle değiştir -->
      <a id="linkDemoM" href="https://demo.vexaris.net" target="_blank" rel="noopener" class="px-3 py-2.5 rounded-lg text-sm font-semibold text-[#c8ccd1] hover:bg-white/5 hover:text-white transition-colors">Demo</a>
      <a id="linkLicenseM" href="/license-check" class="px-3 py-2.5 rounded-lg text-sm font-semibold text-[#c8ccd1] hover:bg-white/5 hover:text-white transition-colors">Check License</a>
      <div class="mt-2 grid grid-cols-2 gap-2">
        <?php if (!empty($loggedIn)): ?>
        <a href="/dashboard" data-close-menu class="btn-ghost px-4 py-2.5 text-center text-sm font-semibold">My Panel</a>
        <a href="/logout" data-close-menu class="btn-primary px-4 py-2.5 text-center text-sm">Log Out</a>
        <?php else: ?>
        <a href="/login" data-close-menu class="btn-ghost px-4 py-2.5 text-center text-sm font-semibold">Log In</a>
        <a href="/register" data-close-menu class="btn-primary px-4 py-2.5 text-center text-sm">Sign Up</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</header>

</main>

<script>
const toggle  = document.getElementById('mobileToggle');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');
if (toggle && sidebar && overlay) {
    toggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('open');
    });
    overlay.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
    });
}
</script>
<?php if ($panelPage === 'stores'): ?>
<script src="/assets/vendor/fpjs/fp.min.js?v=5.3.0" defer></script>
<script src="/assets/js/vx-fp.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/vx-fp.js') ?>" defer></script>
<?php endif; ?>
</body>
</html>

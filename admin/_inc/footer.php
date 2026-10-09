</main>
<script>
const tA=document.getElementById('mobileToggleAdmin'), sA=document.getElementById('sidebar'), oA=document.getElementById('sidebarOverlayAdmin');
if(tA&&sA&&oA){ tA.addEventListener('click',()=>{sA.classList.toggle('open');oA.classList.toggle('open');}); oA.addEventListener('click',()=>{sA.classList.remove('open');oA.classList.remove('open');}); }
</script>
</body>
</html>

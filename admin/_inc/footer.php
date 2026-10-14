</main>
<script>
const tA=document.getElementById('mobileToggleAdmin'), sA=document.getElementById('sidebar'), oA=document.getElementById('sidebarOverlayAdmin');
function vxSide(open){ if(!sA||!oA) return; sA.classList.toggle('open',open); oA.classList.toggle('open',open); document.body.style.overflow=open?'hidden':''; }
if(tA&&sA&&oA){ tA.addEventListener('click',()=>{vxSide(!sA.classList.contains('open'));}); oA.addEventListener('click',()=>{vxSide(false);}); sA.querySelectorAll('.sidebar__link').forEach(a=>a.addEventListener('click',()=>{vxSide(false);})); }
</script>
</body>
</html>

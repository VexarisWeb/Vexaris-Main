<?php
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/turnstile.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/data.php';
boot_session();
$loggedIn = is_logged_in();
$BASE = '';
$GLOBALS['seoKey'] = 'license_check';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php include __DIR__ . '/includes/seo-head.php'; ?>
<link rel="icon" type="image/png" href="./assets/images/vexaris-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<link rel="stylesheet" href="./assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>
<div class="relative min-h-screen bg-[#0b0d10] text-[#e9e7e2]">
<div class="noise-overlay" aria-hidden="true"></div>
<?php include 'header.php'; ?>

<section class="lics-simple" aria-label="License lookup">
    <div class="absolute inset-0 dotgrid opacity-60 [mask-image:radial-gradient(70%_60%_at_50%_20%,#000_20%,transparent_100%)]" aria-hidden="true"></div>
    <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[900px] h-[500px] rounded-full bg-[#4f8fc0]/[.06] blur-[120px]" aria-hidden="true"></div>
    <div class="container" style="max-width:560px;position:relative;z-index:1;margin:0 auto;padding:0 20px">
        <div style="margin-bottom:1rem">
            <label for="licsDomain" style="display:block;font-size:.88rem;color:#cbd5e1;margin-bottom:.5rem;font-weight:600">Domain</label>
            <div style="position:relative">
                <input id="licsDomain" type="text" placeholder="example.com" value="" style="width:100%;background:#0b0f1e;border:1px solid rgba(255,255,255,.08);border-radius:12px;color:#e2e8f0;padding:14px 44px 14px 16px;font-size:1rem;outline:none">
                <i class="fas fa-globe" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#64748b"></i>
            </div>
        </div>

        <?php if (turnstile_enabled()): ?>
        <div style="margin-bottom:1rem"><?php echo turnstile_widget('cf-lcs'); echo turnstile_script(); ?></div>
        <?php else: ?>
        <div id="cf-lcs" style="display:none"></div>
        <?php endif; ?>

        <div id="licsResult" style="display:none;margin-bottom:1rem"></div>

        <button id="licsBtn" onclick="checkLicense()" style="width:100%;background:#4f8fc0;color:#16100b;border:none;border-radius:10px;padding:14px;font-size:.95rem;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:background .25s,transform .25s">
            <i class="fas fa-magnifying-glass"></i> Check Domain
        </button>
        <p style="text-align:center;color:#475569;font-size:.78rem;margin-top:.8rem">Example: example.com · demo.vexaris.com.tr</p>
    </div>
</section>

<style>
.lics-simple{position:relative;padding:150px 20px 70px;overflow:hidden;background:linear-gradient(160deg,#0b0d10 0%,#101a26 55%,#0b0d10 100%);}
@media(max-width:640px){.lics-simple{padding-top:120px;}}
.lics-stars{position:absolute;inset:0;pointer-events:none;}
.lics-star{position:absolute;background:#4f8fc0;border-radius:50%;opacity:.5;animation:starBlink 3s ease-in-out infinite;}
#licsBtn:hover{background:#7fb3d9;transform:translateY(-2px)}
#licsBtn:disabled{opacity:.6;cursor:wait;transform:none}
#licsDomain{transition:border-color .25s,box-shadow .25s}
#licsDomain:focus{border-color:rgba(79,143,192,.6) !important;box-shadow:0 0 0 3px rgba(79,143,192,.12);outline:none}
@keyframes starBlink{0%,100%{opacity:.15}50%{opacity:.85}}
.lics-simple .container{animation:fadeInUp .5s ease both;}
#licsDomain::placeholder{color:#64748b;opacity:1;}
#licsDomain::-webkit-input-placeholder{color:#64748b;}
.lics-ok-sim{border:1px solid rgba(34,197,94,.3);background:rgba(34,197,94,.08);border-radius:12px;padding:14px 16px;display:flex;gap:12px;align-items:flex-start;animation:validPop .45s cubic-bezier(.34,1.56,.64,1) both,validGlow 2.2s ease-in-out infinite 1s}
.lics-ok-sim i{color:#22c55e;font-size:1.4rem;animation:checkPop .6s cubic-bezier(.34,1.56,.64,1) both}
@keyframes validPop{from{opacity:0;transform:translateY(8px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
@keyframes checkPop{0%{transform:scale(0) rotate(-20deg)}60%{transform:scale(1.15) rotate(5deg)}100%{transform:scale(1) rotate(0)}}
@keyframes validGlow{0%,100%{box-shadow:0 0 0 rgba(34,197,94,0)}50%{box-shadow:0 0 18px rgba(34,197,94,.22)}}
.lics-err-sim{border:1px solid rgba(239,68,68,.35);background:rgba(239,68,68,.08);border-radius:12px;padding:14px 16px;}
.lics-err-sim .warn-head{display:flex;gap:10px;align-items:center;color:#f87171;font-weight:800;margin-bottom:.4rem}
.lics-err-sim .warn-head i{font-size:1.2rem}
.lics-err-sim p{color:#fecaca;font-size:.88rem;line-height:1.6;margin:0}
.lics-err-sim ul{margin:.6rem 0 0 1.2rem;color:#fecaca;font-size:.82rem;line-height:1.6}
</style>

<script>
function licEsc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;')}
async function checkLicense(){
    const input=document.getElementById('licsDomain');
    const btn=document.getElementById('licsBtn');
    const resEl=document.getElementById('licsResult');
    const domain=input.value.trim().toLowerCase().replace(/^https?:\/\//i,'').replace(/\/.*$/,'');
    if(!domain){input.style.borderColor='#ef4444';return;}
    input.style.borderColor='';
    let token=''; const ts=document.querySelector('[name="cf-turnstile-response"]'); if(ts) token=ts.value;
    btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Checking...'; resEl.style.display='none';
    try{
        const url='/api/public-check?domain='+encodeURIComponent(domain)+(token?'&cf_token='+encodeURIComponent(token):'');
        const r=await fetch(url); const d=await r.json();
        if(d.ok){
            resEl.innerHTML='<div class="lics-ok-sim"><i class="fas fa-circle-check"></i><div><strong style="color:#4ade80">Genuine Vexaris</strong><p style="color:#bbf7d0;font-size:.88rem;margin-top:4px">'+licEsc(d.message)+'</p></div></div>';
        } else {
            resEl.innerHTML='<div class="lics-err-sim"><div class="warn-head"><i class="fas fa-triangle-exclamation"></i> No License Found — Warning!</div><p>'+licEsc(d.message)+'</p><p style="margin-top:.6rem;font-weight:700;color:#fff"><i class="fas fa-credit-card"></i> This site may steal your credit card details!</p><ul><li>No genuine license record — do not pay.</li><li>Ask the seller for license proof and an invoice.</li><li>Report suspicious cases to info@vexaris.net.</li></ul></div>';
        }
        resEl.style.display='block';
    }catch(e){
        resEl.innerHTML='<div class="lics-err-sim"><div class="warn-head"><i class="fas fa-triangle-exclamation"></i> Connection Error</div><p>Could not reach the server, please try again.</p></div>'; resEl.style.display='block';
    } finally{btn.disabled=false; btn.innerHTML='<i class="fas fa-magnifying-glass"></i> Check Domain'; if(window.turnstile) try{turnstile.reset();}catch(e){}}
}
document.getElementById('licsDomain').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();checkLicense();}});
</script>

<?php include 'footer.php'; ?>
<?php include __DIR__ . '/includes/modals.php'; ?>
</div>
<script>
window.VEX_LEGAL = <?= json_encode($LEGAL, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="./assets/js/index.js?v=<?= filemtime(__DIR__ . '/assets/js/index.js') ?>"></script>
</body>
</html>

<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/turnstile.php';
boot_session();
if (is_logged_in()) { header('Location: /dashboard'); exit; }
$enabled = setting_get('password_reset_enabled', '1') === '1';
log_pageview('sifremi-unuttum');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Forgot Password | Vexaris</title>
<link rel="alternate" hreflang="en" href="https://vexaris.net/forgot-password">
<link rel="alternate" hreflang="x-default" href="https://vexaris.net/forgot-password">
<link rel="icon" type="image/png" href="/assets/images/vexaris-logo.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--ink:#0b0d10;--line:#262c33;--text:#e9e7e2;--muted:#9aa3ac;--dim:#5b636b;--ember:#4f8fc0;--err:#d98080;--ok:#74c091;--fb:"Manrope",sans-serif;--fd:"Bricolage Grotesque",Manrope,sans-serif}
body{font-family:var(--fb);background:var(--ink);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px 16px}
.box{width:100%;max-width:440px;background:#15191e;border:1px solid var(--line);border-radius:16px;padding:2rem}
.brand{display:flex;align-items:center;gap:10px;margin-bottom:1.4rem;text-decoration:none}
.brand img{height:30px}.brand span{font-family:var(--fd);font-weight:800;font-size:1.15rem;color:var(--text)}
h1{font-family:var(--fd);font-size:1.5rem;font-weight:800;margin-bottom:.4rem}
.sub{color:var(--muted);font-size:.88rem;margin-bottom:1.4rem;line-height:1.6}
.steps{display:flex;gap:6px;margin-bottom:1.4rem}
.step{flex:1;height:4px;border-radius:99px;background:var(--line)}
.step.on{background:var(--ember)}
label{display:block;font-size:.8rem;font-weight:700;color:#c8ccd1;margin-bottom:.45rem}
input{width:100%;padding:13px 15px;background:#101317;border:1px solid var(--line);border-radius:11px;color:var(--text);font-size:.93rem;font-family:var(--fb);outline:none;margin-bottom:1rem}
input:focus{border-color:rgba(79,143,192,.6)}
.code{letter-spacing:.5em;text-align:center;font-size:1.3rem;font-weight:800}
.btn{width:100%;padding:13px;background:linear-gradient(135deg,#4f8fc0,#3a76a8);border:none;border-radius:11px;color:#fff;font-size:.93rem;font-weight:800;cursor:pointer;font-family:var(--fb)}
.btn:disabled{opacity:.6;cursor:wait}
.msg{display:none;border-radius:11px;padding:12px 15px;font-size:.85rem;margin-bottom:1rem}
.msg.err{display:block;background:rgba(192,79,79,.08);border:1px solid rgba(192,79,79,.35);color:var(--err)}
.msg.ok{display:block;background:rgba(116,192,145,.08);border:1px solid rgba(116,192,145,.35);color:var(--ok)}
.back{display:block;text-align:center;margin-top:1.2rem;color:var(--muted);font-size:.85rem;text-decoration:none}
.back:hover{color:#fff}
.hidden{display:none}
.vx-view{overflow:hidden}
.vx-track{display:flex;transition:transform .38s cubic-bezier(.22,1,.36,1)}
.vx-pane{min-width:100%;box-sizing:border-box}
.vx-cd{font-size:.82rem;color:var(--muted);text-align:center;margin-top:.8rem}
.vx-cd b{color:var(--ember);font-variant-numeric:tabular-nums}
</style>
</head>
<body>
<div class="box">
    <a href="/" class="brand"><img src="/assets/images/vexaris-logo.png" alt="Vexaris"><span>Vexaris</span></a>
    <?php if (!$enabled): ?>
        <h1>Password Reset Disabled</h1>
        <p class="sub">Password reset is currently disabled. Please contact the support team for help.</p>
        <a href="/login" class="back"><i class="fas fa-arrow-left"></i> Back to sign in</a>
    <?php else: ?>
        <h1>Forgot Password</h1>
        <p class="sub" id="stepDesc">Enter your registered email address and we will send you a 6-digit verification code.</p>
        <div class="steps"><div class="step on" id="s1"></div><div class="step" id="s2"></div><div class="step" id="s3"></div></div>
        <div class="msg" id="msg"></div>
        <div class="vx-view"><div class="vx-track" id="vxTrack">
        <div class="vx-pane" id="step1">
            <label for="email">Your email address</label>
            <input type="email" id="email" placeholder="you@mail.com" autocomplete="email">
            <?php if (turnstile_enabled()): ?><?php echo turnstile_widget('cf-turnstile-fg'); echo turnstile_script(); ?><?php endif; ?>
            <button class="btn" id="btn1" onclick="sendCode()">Send Code</button>
        </div>
        <div class="vx-pane" id="step2">
            <label for="code">6-digit code</label>
            <input type="text" id="code" class="code" maxlength="6" inputmode="numeric" placeholder="••••••" autocomplete="one-time-code">
            <button class="btn" id="btn2" onclick="verifyCode()">Verify</button>
            <button class="btn" id="btnResend" onclick="resendCode()" style="margin-top:.6rem;background:transparent;border:1px solid var(--line);color:var(--muted)">Resend</button>
            <div class="vx-cd" id="vxCd" style="display:none">You can resend in: <b>01:00</b></div>
        </div>
        <div class="vx-pane" id="step3">
            <label for="np">New password (at least 6 characters)</label>
            <input type="password" id="np" autocomplete="new-password">
            <label for="np2">Confirm new password</label>
            <input type="password" id="np2" autocomplete="new-password">
            <button class="btn" id="btn3" onclick="savePass()">Update Password</button>
        </div>
        </div></div>
        <a href="/login" class="back"><i class="fas fa-arrow-left"></i> Back to sign in</a>
    <?php endif; ?>
</div>
<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
let userEmail = '', resetToken = '', pSends = 0, pCdTimer = null;
function mmss(s){ s=Math.max(0,s); return String(Math.floor(s/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0'); }
function showMsg(ok, txt){const e=document.getElementById('msg');e.className='msg '+(ok?'ok':'err');e.textContent=txt;}
function goStep(n){
    document.getElementById('vxTrack').style.transform='translateX(-'+((n-1)*100)+'%)';
    ['s1','s2','s3'].forEach((id,i)=>document.getElementById(id).classList.toggle('on', i<n));
    const descs={1:'Enter your registered email address and we will send you a 6-digit verification code.',2:'Enter the 6-digit code from your email. The code is valid for 15 minutes.',3:'Set your new password.'};
    document.getElementById('stepDesc').textContent=descs[n];
    document.getElementById('msg').className='msg';
}
function armCd(){
    const btn=document.getElementById('btnResend'), cd=document.getElementById('vxCd'), cdb=cd.querySelector('b');
    clearInterval(pCdTimer);
    const wait = pSends===1 ? 60 : (pSends===2 ? 300 : 0);
    if(wait<=0){
        btn.disabled=true; btn.textContent='Send limit reached'; cd.style.display='none'; return;
    }
    let left=wait;
    btn.disabled=true; cd.style.display='block'; cdb.textContent=mmss(left);
    pCdTimer=setInterval(()=>{
        left--;
        if(left<=0){ clearInterval(pCdTimer); btn.disabled=false; cd.style.display='none'; }
        else cdb.textContent=mmss(left);
    },1000);
}
async function post(fd){
    const r=await fetch('/api/auth',{method:'POST',headers:{'X-CSRF-Token':CSRF},body:fd});
    return r.json();
}
async function sendCode(){
    const email=document.getElementById('email').value.trim();
    if(!email) return showMsg(false,'Enter your email.');
    const btn=document.getElementById('btn1'); btn.disabled=true; btn.textContent='Sending...';
    try{
        const fd=new FormData(); fd.append('action','request_reset'); fd.append('email',email);
        const tsEl=document.querySelector('[name="cf-turnstile-response"]');
        if(tsEl&&tsEl.value) fd.append('cf-turnstile-response', tsEl.value);
        const d=await post(fd);
        try{ if(window.turnstile) window.turnstile.reset(); }catch(e){}
        showMsg(!!d.ok, d.msg||'Error');
        if(d.ok){ userEmail=email; pSends++; goStep(2); armCd(); }
        else if(/daha sonra/.test(d.msg||'')){ const btn=document.getElementById('btnResend'); btn.disabled=true; btn.textContent='Send limit reached'; }
    }catch(e){ showMsg(false,'Connection error.'); }
    btn.disabled=false; btn.textContent='Send Code';
}
async function resendCode(){
    if(!userEmail) return showMsg(false,'Enter your email first.');
    const btn=document.getElementById('btnResend'); btn.disabled=true; btn.textContent='Sending...';
    try{
        const fd=new FormData(); fd.append('action','request_reset'); fd.append('email',userEmail);
        const d=await post(fd);
        try{ if(window.turnstile) window.turnstile.reset(); }catch(e){}
        showMsg(!!d.ok, d.msg||'Error');
        if(d.ok){ pSends++; armCd(); }
        else if(/daha sonra/.test(d.msg||'')){ btn.disabled=true; btn.textContent='Send limit reached'; }
    }catch(e){ showMsg(false,'Connection error.'); }
    btn.disabled=false; btn.textContent='Resend';
}
async function verifyCode(){
    const code=document.getElementById('code').value.replace(/[^0-9]/g,'');
    if(code.length!==6) return showMsg(false,'Enter the 6-digit code.');
    const btn=document.getElementById('btn2'); btn.disabled=true; btn.textContent='Verifying...';
    try{
        const fd=new FormData(); fd.append('action','verify_reset'); fd.append('email',userEmail); fd.append('code',code);
        const d=await post(fd);
        showMsg(!!d.ok, d.msg||'Error');
        if(d.ok&&d.token){ resetToken=d.token; goStep(3); }
    }catch(e){ showMsg(false,'Connection error.'); }
    btn.disabled=false; btn.textContent='Verify';
}
async function savePass(){
    const np=document.getElementById('np').value, np2=document.getElementById('np2').value;
    if(np.length<6) return showMsg(false,'At least 6 characters.');
    if(np!==np2) return showMsg(false,'Passwords do not match.');
    const btn=document.getElementById('btn3'); btn.disabled=true; btn.textContent='Saving...';
    try{
        const fd=new FormData(); fd.append('action','reset_password'); fd.append('email',userEmail); fd.append('token',resetToken); fd.append('new',np);
        const d=await post(fd);
        showMsg(!!d.ok, d.msg||'Error');
        if(d.ok) setTimeout(()=>location.href='/login',1800);
    }catch(e){ showMsg(false,'Connection error.'); }
    btn.disabled=false; btn.textContent='Update Password';
}
</script>
</body>
</html>

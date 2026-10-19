<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/turnstile.php';
require_once __DIR__ . '/../core/oauth.php';
require_once __DIR__ . '/../core/multi.php';
boot_session();

function vex_safe_next($v): string {
    if (!is_string($v) || $v === '') return '';
    // Backslash ve URL-encoded backslash korumasi (tarayicilar / ile normalize eder)
    return (preg_match('#^/(?![/\\\\%5c])#i', $v) && strpos($v, '/\\') === false) ? $v : '';
}
$nextUrl = vex_safe_next($_GET['next'] ?? '');

if (is_logged_in()) {
    header('Location: ' . ($nextUrl ?: (is_admin() ? '/admin' : '/dashboard')));
    exit;
}

$error = '';
$success = '';

if (isset($_GET['activated'])) {
    $success = 'Your account has been activated. You can sign in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $remember   = !empty($_POST['remember']);
    if (!$identifier || !$password) {
        $error = 'Please fill in all fields.';
    } else if (turnstile_enabled() && !turnstile_verify($_POST['cf-turnstile-response'] ?? $_POST['cf_token'] ?? '', client_ip())) {
        $error = 'Security verification failed. Please complete Turnstile.';
    } else {
        $pdo = db();
        if (!$pdo) {
            $error = 'Could not connect to the database. Please try again later.';
        } else {
            $rlIp = client_ip();
            $rlUser = 'lu_' . substr(md5(strtolower($identifier)), 0, 24);
            $rlOk = true;
            try {
                $rlOk = api_rate_limit($pdo, 'login', $rlIp, 30, 900);
                if ($rlOk) $rlOk = api_rate_limit($pdo, $rlUser, $rlIp, 10, 900);
            } catch (Throwable $eRl) {
                $rlOk = true;
            }
            if (!$rlOk) {
                $error = 'Too many sign-in attempts. Please try again later.';
            } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1");
                $stmt->execute([$identifier, $identifier]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Banlı hesap giriş yapamaz (multi ya da manuel fark etmez).
                    if ((int)($user['is_active'] ?? 1) === 0 && (string)($user['ban_reason'] ?? '') !== '') {
                        header('Location: /banned?why=' . urlencode((string)($user['ban_reason'] ?? 'ban')));
                        exit;
                    }
                    // Bağlantılı grupta bu hesap multi ise anında ban (oturum açılmadan).
                    try {
                        if (multi_enforce($pdo, (int)$user['id']) === 'banned') {
                            header('Location: /banned');
                            exit;
                        }
                    } catch (Throwable $e0b) {}
                    $_SESSION['user'] = [
                        'id'       => $user['id'],
                        'username' => $user['username'],
                        'email'    => $user['email'],
                        'is_admin' => $user['is_admin'],
                        'sess_ver' => (int)($user['session_version'] ?? 0),
                    ];
                    session_regenerate_id(true);
                    try { $pdo->prepare("DELETE FROM api_rate_limits WHERE ip=? AND scope IN ('login',?)")->execute([$rlIp, $rlUser]); } catch (Throwable $eRl) {}
                    try { multi_after_login($pdo, (int)$user['id']); } catch (Throwable $e0) {}
                    if ($remember) {
                        try {
                            $pdoR = db();
                            if ($pdoR) {
                                $raw = bin2hex(random_bytes(32));
                                $hash = hash('sha256', $raw);
                                $exp = date('Y-m-d H:i:s', time()+60*60*24*180);
                                $pdoR->prepare("INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?,?,?)")->execute([$user['id'], $hash, $exp]);
                                $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
                                setcookie('remember_token', $raw, ['expires'=>time()+60*60*24*180,'path'=>'/','httponly'=>true,'samesite'=>'Lax','secure'=>$secure]);
                            }
                        } catch (Throwable $e) {}
                    }
                    $afterLogin = vex_safe_next($_POST['next'] ?? '');
                    header('Location: ' . ($afterLogin ?: ($user['is_admin'] ? '/admin' : '/dashboard')));
                    exit;
                } else {
                    $error = 'Email/username or password is incorrect.';
                }
            } catch (Exception $e) {
                error_log('Login hatası: ' . $e->getMessage());
                $error = 'Something went wrong. Please try again.';
            }
            }
        }
    }
}

if (!$error && isset($_GET['err'])) {
    $errMap = ['social' => 'Social sign-in failed. Please try again.', 'disabled' => 'This sign-in method is currently disabled.', 'activation' => 'Activation link is invalid or expired.', 'domain' => 'This email provider is not accepted. Please use a trusted provider (Gmail, Yahoo, Outlook, iCloud, Proton or similar).'];
    if (isset($errMap[$_GET['err']])) $error = $errMap[$_GET['err']];
}

$oauthList = oauth_configured_list();
$nextQ = $nextUrl !== '' ? '&next=' . urlencode($nextUrl) : '';

log_pageview('giris');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — Vexaris</title>
<link rel="alternate" hreflang="en" href="https://vexaris.net/login">
<link rel="alternate" hreflang="x-default" href="https://vexaris.net/login">
<link rel="icon" type="image/png" href="/assets/images/vexaris-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
--ink:#0b0d10;--surface:#15191e;--line:#262c33;--line2:#39424c;
--text:#e9e7e2;--muted:#9aa3ac;--dim:#7d858e;
--ember:#4f8fc0;--ember2:#7fb3d9;--err:#d98080;--ok:#74c091;
--fd:"Bricolage Grotesque",Manrope,sans-serif;--fb:"Manrope",sans-serif;
--fm:"JetBrains Mono",ui-monospace,monospace;
}
body{font-family:var(--fb);background:var(--ink);color:var(--text);min-height:100vh;display:flex;-webkit-font-smoothing:antialiased;overflow-x:hidden}
.auth-form{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 24px;position:relative;min-width:0}
.auth-box{width:100%;max-width:430px;animation:rise .55s cubic-bezier(.22,1,.36,1)}
@keyframes rise{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}
.brand{display:inline-flex;align-items:center;gap:10px;text-decoration:none;margin-bottom:30px}
.brand img{height:32px;width:auto}
.brand span{font-family:var(--fd);font-weight:800;font-size:1.2rem;letter-spacing:-.02em;color:var(--text)}
.auth-box h1{font-family:var(--fd);font-size:1.9rem;font-weight:800;letter-spacing:-.02em;line-height:1.15}
.auth-box .sub{color:var(--muted);font-size:.92rem;margin:.55rem 0 1.5rem}
.social-row{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:18px}
.btn-social{display:flex;align-items:center;justify-content:center;gap:8px;padding:11px 8px;border:1px solid var(--line);border-radius:11px;background:#101317;color:var(--text);font-size:.83rem;font-weight:700;text-decoration:none;transition:border-color .25s,background .25s,transform .25s;font-family:var(--fb)}
.btn-social:hover{border-color:var(--line2);background:rgba(255,255,255,.04);transform:translateY(-2px)}
.btn-social .fa-google{color:#ea4335}
.btn-social .fa-discord{color:#5865f2}
.btn-social .fa-github{color:#fff}
.btn-social .fa-microsoft{color:#2b88d8}
.or{display:flex;align-items:center;gap:14px;margin:4px 0 18px;color:var(--dim);font-size:.72rem;font-weight:700;letter-spacing:.14em}
.or::before,.or::after{content:"";flex:1;height:1px;background:var(--line)}
.alert{background:rgba(192,79,79,.08);border:1px solid rgba(192,79,79,.35);border-radius:11px;padding:12px 15px;font-size:.85rem;color:var(--err);margin-bottom:1.2rem;display:flex;align-items:center;gap:9px;animation:shake .35s ease}
@keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-4px)}75%{transform:translateX(4px)}}
.field{display:flex;flex-direction:column;gap:.5rem;margin-bottom:1rem}
.field label{font-size:.82rem;font-weight:700;color:#c8ccd1}
.input-wrap{position:relative}
.input-wrap>i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--dim);font-size:.88rem;pointer-events:none}
.input-wrap input{width:100%;padding:13px 44px 13px 42px;background:#101317;border:1px solid var(--line);border-radius:11px;color:var(--text);font-size:.93rem;font-family:var(--fb);outline:none;transition:border-color .25s,box-shadow .25s}
.input-wrap input:focus{border-color:rgba(79,143,192,.6);box-shadow:0 0 0 3px rgba(79,143,192,.12)}
.input-wrap input::placeholder{color:#5b636b}
.pass-toggle{position:absolute;right:8px;top:50%;transform:translateY(-50%);width:34px;height:34px;border-radius:9px;background:none;border:none;color:var(--dim);cursor:pointer;font-size:.88rem;display:flex;align-items:center;justify-content:center;transition:color .2s,background .2s}
.pass-toggle:hover{color:var(--ember);background:rgba(79,143,192,.1)}
.form-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:1.1rem 0 1.2rem}
.remember{display:flex;align-items:center;gap:9px;cursor:pointer;user-select:none;font-size:.83rem;font-weight:600;color:var(--muted)}
.remember input{appearance:none;-webkit-appearance:none;width:17px;height:17px;border:1px solid var(--line2);border-radius:5px;background:#101317;cursor:pointer;position:relative;transition:all .18s;flex-shrink:0;margin:0}
.remember input:checked{background:var(--ember);border-color:transparent}
.remember input:checked::after{content:'';position:absolute;left:5px;top:2px;width:4px;height:8px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg)}
.forgot{font-size:.83rem;font-weight:700;color:var(--ember);text-decoration:none;white-space:nowrap}
.forgot:hover{text-decoration:underline}
.cf-turnstile{margin:4px 0 2px}
.btn-submit{width:100%;padding:14px;background:var(--ember);border:none;border-radius:10px;color:#16100b;font-size:.95rem;font-weight:800;cursor:pointer;font-family:var(--fb);display:flex;align-items:center;justify-content:center;gap:9px;transition:transform .25s,background .25s;margin-top:.4rem}
.btn-submit:hover{background:var(--ember2);transform:translateY(-2px)}
.btn-submit:active{transform:translateY(0) scale(.98)}
.auth-alt{text-align:center;margin-top:1.5rem;font-size:.87rem;color:var(--muted)}
.auth-alt a{color:var(--ember);text-decoration:none;font-weight:700}
.auth-alt a:hover{text-decoration:underline}
.back-home{margin-top:1.4rem;text-align:center}
.back-home a{font-size:.8rem;font-weight:600;color:var(--dim);text-decoration:none;display:inline-flex;align-items:center;gap:7px;transition:color .2s}
.back-home a:hover{color:var(--text)}
.auth-visual{flex:1.05;display:flex;flex-direction:column;justify-content:center;padding:56px;position:relative;overflow:hidden;background:radial-gradient(800px 500px at 20% 0%,rgba(79,143,192,.14),transparent 60%),radial-gradient(700px 500px at 100% 100%,rgba(79,143,192,.1),transparent 60%),linear-gradient(160deg,#0d1218 0%,#101a26 55%,#0b0d10 100%);border-left:1px solid var(--line)}
.auth-visual::before{content:'';position:absolute;inset:0;background-image:radial-gradient(rgba(233,231,226,.08) 1px,transparent 1px);background-size:26px 26px;-webkit-mask-image:linear-gradient(to bottom,rgba(0,0,0,.6),transparent 85%);mask-image:linear-gradient(to bottom,rgba(0,0,0,.6),transparent 85%);pointer-events:none}
.vis-eyebrow{font-family:var(--fm);font-size:.72rem;letter-spacing:.22em;text-transform:uppercase;color:var(--ember);position:relative;z-index:1}
.vis-title{font-family:var(--fd);font-size:clamp(1.6rem,2.6vw,2.4rem);font-weight:800;letter-spacing:-.02em;line-height:1.15;margin-top:14px;position:relative;z-index:1}
.vis-card{position:relative;z-index:1;margin-top:34px;background:linear-gradient(180deg,#161b21 0%,#12161a 100%);border:1px solid var(--line);border-radius:14px;padding:22px;min-height:172px;transition:opacity .45s ease,transform .45s cubic-bezier(.22,1,.36,1)}
.vis-card.swap{opacity:0;transform:translateY(12px)}
.vis-ic{display:grid;place-items:center;width:42px;height:42px;border-radius:11px;border:1px solid var(--line);background:#0e1114;color:var(--ember);font-size:1rem}
.vis-card h3{font-family:var(--fd);font-size:1.1rem;font-weight:800;margin-top:14px;letter-spacing:-.01em}
.vis-card p{color:var(--muted);font-size:.87rem;line-height:1.6;margin-top:6px}
.vis-dots{display:flex;gap:7px;margin-top:20px;position:relative;z-index:1}
.vis-dots span{height:6px;border-radius:99px;background:#39424c;transition:all .4s;cursor:pointer}
.vis-dots span.on{width:26px;background:var(--ember)}
.vis-dots span:not(.on){width:6px}
.vis-dots span:not(.on):hover{background:#5b636b}
.vis-bolt{position:absolute;right:-46px;bottom:-40px;font-size:21rem;color:rgba(79,143,192,.07);pointer-events:none;z-index:0;line-height:1}
.form-foot{text-align:center;margin-top:1.8rem;font-size:.72rem;color:rgba(125,133,142,.7)}
@media(max-width:1023px){.auth-visual{display:none}}
@media(max-width:480px){.auth-form{padding:28px 16px}.social-row{grid-template-columns:1fr 1fr}.social-row a:last-child{grid-column:1/-1}}
</style>
</head>
<body>
<div class="auth-form">
<div class="auth-box">
<a href="/" class="brand"><img src="/assets/images/vexaris-logo.png" alt="Vexaris"><span>Vexaris</span></a>
<h1>Sign in</h1>
<p class="sub">Sign in to your account to pick up where you left off.</p>
<?php if (!empty($oauthList)): ?>
<div class="social-row">
<?php foreach ($oauthList as $p => $cfg): ?>
<a class="btn-social" href="/api/oauth?provider=<?= $p ?>&action=login<?= $nextQ ?>"><i class="<?= $p === 'microsoft' ? 'fab fa-microsoft' : 'fab fa-' . ($p === 'discord' ? 'discord' : ($p === 'github' ? 'github' : 'google')) ?>"></i> <?= htmlspecialchars($cfg['label']) ?></a>
<?php endforeach; ?>
</div>
<div class="or">OR</div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert" style="background:rgba(116,192,145,.08);border-color:rgba(116,192,145,.35);color:#74c091"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<form method="POST" action="/login" autocomplete="on">
    <?= csrf_field() ?>
<?php if ($nextUrl): ?><input type="hidden" name="next" value="<?= htmlspecialchars($nextUrl) ?>"><?php endif; ?>
<div class="field">
<label for="identifier">Email or Username</label>
<div class="input-wrap">
<i class="fa-solid fa-user"></i>
<input type="text" id="identifier" name="identifier" placeholder="you@mail.com or username" required autocomplete="username" value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>">
</div>
</div>
<div class="field">
<label for="password">Password</label>
<div class="input-wrap">
<i class="fa-solid fa-lock"></i>
<input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
<button type="button" class="pass-toggle" id="passToggle" aria-label="Show/hide password"><i class="fas fa-eye" id="eyeIcon"></i></button>
</div>
</div>
<div class="form-row">
<label class="remember"><input type="checkbox" name="remember" value="1" checked> Remember me</label>
<?php if (setting_get('password_reset_enabled', '1') === '1'): ?><a href="/forgot-password" class="forgot">Forgot password</a><?php endif; ?>
</div>
<?php if (turnstile_enabled()): ?><?php echo turnstile_widget(); echo turnstile_script(); ?><?php endif; ?>
<input type="hidden" name="fp" id="fpField" value="">
<button type="submit" class="btn-submit"><i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In</button>
</form>
<script>
try{(function(){var c=document.createElement('canvas');var x=c.getContext('2d');x.textBaseline='top';x.font='14px Arial';x.fillText('Vexaris#fp#2026',2,2);var d='';try{d=c.toDataURL();}catch(e){}var s=[navigator.userAgent||'',screen.width+'x'+screen.height+'x'+screen.colorDepth,navigator.language||'',new Date().getTimezoneOffset(),navigator.hardwareConcurrency||'',navigator.deviceMemory||'',d].join('|');var h1=0xdeadbeef,h2=0x41c6ce57;for(var i=0;i<s.length;i++){var ch=s.charCodeAt(i);h1=Math.imul(h1^ch,2654435761);h2=Math.imul(h2^ch,1597334677);}h1=Math.imul(h1^(h1>>>16),2246822507)^Math.imul(h2^(h2>>>13),3266489909);h2=Math.imul(h2^(h2>>>16),2246822507)^Math.imul(h1^(h1>>>13),3266489909);var f=(h2>>>0).toString(16)+(h1>>>0).toString(16);var el=document.getElementById('fpField');if(el)el.value=f;})();}catch(e){}
</script>
<p class="auth-alt">Don't have an account? <a href="/register">Sign Up</a></p>
<div class="back-home"><a href="/"><i class="fa-solid fa-arrow-left"></i> Home</a></div>
<div class="form-foot">Vexaris &copy; <?= date('Y') ?></div>
</div>
</div>
<aside class="auth-visual" aria-hidden="false">
<i class="fa-solid fa-bolt vis-bolt" aria-hidden="true"></i>
<div class="vis-eyebrow">Vexaris Platform</div>
<div class="vis-title">The entire sales chain<br>in one dashboard.</div>
<div class="vis-card" id="visCard">
<div class="vis-ic" id="visIcon"><i class="fa-solid fa-bolt"></i></div>
<h3 id="visTitle">Instant Delivery</h3>
<p id="visDesc">Files and keys land in the customer's library the second payment is confirmed.</p>
</div>
<div class="vis-dots" id="visDots"><span class="on"></span><span></span><span></span><span></span><span></span></div>
</aside>
<script>
(function(){
var toggle=document.getElementById('passToggle'),input=document.getElementById('password'),icon=document.getElementById('eyeIcon');
if(toggle&&input&&icon){toggle.addEventListener('click',function(){var show=input.type==='password';input.type=show?'text':'password';icon.className=show?'fas fa-eye-slash':'fas fa-eye';});}
var feats=[
{icon:'fa-bolt',title:'Instant Delivery',desc:'Files and keys land in the customer library the second payment is confirmed.'},
{icon:'fa-wallet',title:'7 Payment Integrations',desc:'Shopier, PayTR, iyzico, Stripe, PayPal, BitPay and Cryptomus in one dashboard.'},
{icon:'fa-headset',title:'24/7 Support Infrastructure',desc:'Ticketing, instant notifications and Discord integration have you covered.'},
{icon:'fa-ticket',title:'Coupons & Campaigns',desc:'Boost sales with percentage-off or free-product coupons.'},
{icon:'fa-palette',title:'3 Ready-made Themes',desc:'Switch between Default, Azure and Ember theme sets with one click.'}
];
var i=0,card=document.getElementById('visCard'),ic=document.getElementById('visIcon'),ti=document.getElementById('visTitle'),de=document.getElementById('visDesc'),dots=document.getElementById('visDots');
function paint(n){i=(n+feats.length)%feats.length;if(ic)ic.innerHTML='<i class="fa-solid '+feats[i].icon+'"></i>';if(ti)ti.textContent=feats[i].title;if(de)de.textContent=feats[i].desc;if(dots){var d=dots.children;for(var k=0;k<d.length;k++){d[k].className=k===i?'on':'';}}}
function cycle(){if(!card||document.hidden)return;card.classList.add('swap');setTimeout(function(){paint(i+1);if(card)card.classList.remove('swap');},380);}
if(card&&ti){var timer=setInterval(cycle,4200);if(dots){var d=dots.children;for(var k=0;k<d.length;k++)(function(n){d[n].addEventListener('click',function(){paint(n);});})(k);}}
})();
</script>
</body>
</html>

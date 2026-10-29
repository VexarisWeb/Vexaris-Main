<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/turnstile.php';
require_once __DIR__ . '/../core/oauth.php';
require_once __DIR__ . '/../core/multi.php';
require_once __DIR__ . '/../core/risk.php';
boot_session();

if (is_logged_in()) {
    header('Location: /dashboard'); exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username  = trim($_POST['username']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = $_POST['password']       ?? '';
    $password2 = $_POST['password2']      ?? '';
    $accepted  = !empty($_POST['accept_terms']);
    $pdo = db();
    $rlOk = $pdo ? api_rate_limit($pdo, 'register', client_ip(), 5, 3600) : true;
    $ipGateOk = true;
    $ipGateMsg = '';
    if ($pdo && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            $gIp = multi_ip_gate($pdo, client_ip());
            if (!$gIp['ok']) {
                $be = strtolower(trim($email));
                $bl = multi_bypass_emails();
                $isBy = $bl && $be !== '' && (in_array($be, $bl, true) || in_array(multi_normalize_email($be), $bl, true));
                if (!$isBy) { $ipGateOk = false; $ipGateMsg = $gIp['msg']; }
            }
        } catch (Throwable $eIp) {}
    }

    if (!$pdo) {
        $error = 'Could not connect to the database. Please try again later.';
    } elseif (!$rlOk) {
        $error = 'Too many registration attempts. Please try again later.';
    } elseif (!$accepted) {
        $error = 'To continue, you must accept the Terms of Use and Privacy Policy.';
    } elseif (!$username || !$email || !$password || !$password2) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (turnstile_enabled() && !turnstile_verify($_POST['cf-turnstile-response'] ?? $_POST['cf_token'] ?? '', client_ip())) {
        $error = 'Security verification failed. Please complete Turnstile.';
    } elseif (!$ipGateOk) {
        $error = $ipGateMsg;
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = 'Password must include at least one uppercase letter, one lowercase letter and one number.';
    } elseif ($password !== $password2) {
        $error = 'Passwords do not match.';
    } else {
        if (!$pdo) {
            $error = 'Could not connect to the database. Please try again later.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
                $stmt->execute([$email, $username]);
                if ($stmt->fetch()) {
                    $error = 'This email or username is already registered.';
                } else {
                    // Multi-hesap koruması: disposable / normalize çakışma / IP / cihaz limiti.
                    $multiChk = multi_check_registration($pdo, $email);
                    if (!$multiChk['ok']) {
                        $error = $multiChk['msg'];
                    } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $needActivation = setting_get('activation_enabled', '1') === '1';
                    $pdo->prepare("INSERT INTO users (username, email, password, is_active, email_norm, reg_ip) VALUES (?, ?, ?, ?, ?, ?)")
                        ->execute([$username, $email, $hash, $needActivation ? 0 : 1, multi_normalize_email($email), client_ip()]);

                    $id = (int) $pdo->lastInsertId();
                    multi_after_register($pdo, $id, $multiChk);
                    try { multi_group_sync($pdo, $id); } catch (Throwable $eGs) {}
                    // Acımasız kural: bağlantılı grupta yeni hesap multi ise anında ban.
                    if (risk_guard($pdo, $id, 'register') === 'banned') {
                        header('Location: /banned');
                        exit;
                    }
                    $u  = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $u->execute([$id]);
                    $user = $u->fetch();

                    $_SESSION['user'] = [
                        'id'       => $user['id'],
                        'username' => $user['username'],
                        'email'    => $user['email'],
                        'is_admin' => $user['is_admin'],
                        'sess_ver' => (int)($user['session_version'] ?? 0),
                    ];
                    session_regenerate_id(true);
                    if ($needActivation) {
                        // Aktivasyon maili (noreply). Gönderim başarısız olursa hesap
                        // kilitli kalmasın diye fail-open: doğrudan aktive et.
                        try {
                            require_once __DIR__ . '/../core/mail.php';
                            require_once __DIR__ . '/../core/mail-templates.php';
                            $token = bin2hex(random_bytes(32));
                            $pdo->prepare("DELETE FROM activation_tokens WHERE user_id = ?")->execute([$id]);
                            $pdo->prepare("INSERT INTO activation_tokens (user_id, token_hash, expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 48 HOUR))")
                                ->execute([$id, hash('sha256', $token)]);
                            $url = 'https://vexaris.net/activate?token=' . $token;
                            [$asubj, $abody, $avars] = mail_tpl_activation($url);
                            $avars['user_name'] = $username;
                            $noreply = ['email' => setting_get('mail_noreply_email', 'noreply@vexaris.net'), 'name' => 'Vexaris'];
                            $sent = mail_send($pdo, $email, $asubj, $abody, $avars, $noreply);
                            if (!$sent) {
                                $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?")->execute([$id]);
                                $pdo->prepare("DELETE FROM activation_tokens WHERE user_id = ?")->execute([$id]);
                                error_log('Vexaris register: aktivasyon maili gönderilemedi, hesap otomatik aktive edildi (uid=' . $id . ')');
                            }
                        } catch (Throwable $e2) {
                            try {
                                $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?")->execute([$id]);
                            } catch (Throwable $e3) {}
                            error_log('Vexaris register aktivasyon hatası: ' . $e2->getMessage());
                        }
                    }
                    header('Location: /dashboard'); exit;
                    }
                }
            } catch (Throwable $e) {
                error_log('Register hatası: ' . $e->getMessage());
                $error = 'Something went wrong. Please try again.';
            }
        }
    }
}

$oauthList = oauth_configured_list();

log_pageview('kayit');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign Up — Vexaris</title>
<meta name="robots" content="noindex, nofollow">
<link rel="canonical" href="https://vexaris.net/register">
<meta property="og:title" content="Register — Vexaris">
<meta property="og:type" content="website">
<meta property="og:url" content="https://vexaris.net/register">
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
--ember:#4f8fc0;--ember2:#7fb3d9;--err:#d98080;
--fd:"Bricolage Grotesque",Manrope,sans-serif;--fb:"Manrope",sans-serif;
--fm:"JetBrains Mono",ui-monospace,monospace;
}
body{font-family:var(--fb);background:var(--ink);color:var(--text);min-height:100vh;display:flex;-webkit-font-smoothing:antialiased;overflow-x:hidden}
.auth-form{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 24px;position:relative;min-width:0}
.auth-box{width:100%;max-width:450px;animation:rise .55s cubic-bezier(.22,1,.36,1)}
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
.strength{margin-top:.55rem;display:flex;align-items:center;gap:8px}
.strength-bar{flex:1;height:4px;border-radius:99px;background:#262c33;overflow:hidden}
.strength-fill{height:100%;width:0%;border-radius:99px;background:var(--ember);transition:width .3s,background .3s}
.strength-label{font-size:.72rem;font-weight:700;color:var(--dim);min-width:70px;text-align:right}
.terms{display:flex;gap:10px;cursor:pointer;user-select:none;font-size:.82rem;line-height:1.55;color:var(--muted);margin:1.1rem 0 1.2rem;align-items:flex-start}
.terms input{appearance:none;-webkit-appearance:none;width:17px;height:17px;border:1px solid var(--line2);border-radius:5px;background:#101317;cursor:pointer;position:relative;transition:all .18s;flex-shrink:0;margin:2px 0 0;padding:0}
.terms input:checked{background:var(--ember);border-color:transparent}
.terms input:checked::after{content:'';position:absolute;left:5px;top:2px;width:4px;height:8px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg)}
.terms a{color:var(--ember);font-weight:700}
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
.vis-card{position:relative;z-index:1;margin-top:20px;background:linear-gradient(180deg,#161b21 0%,#12161a 100%);border:1px solid var(--line);border-radius:14px;padding:22px;min-height:172px;transition:opacity .45s ease,transform .45s cubic-bezier(.22,1,.36,1)}
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
<h1>Create your account</h1>
<p class="sub">Sign up in 30 seconds and start submitting your application right away.</p>
<?php if (!empty($oauthList)): ?>
<div class="social-row">
<?php foreach ($oauthList as $p => $cfg): ?>
<a class="btn-social" href="/api/oauth?provider=<?= $p ?>&action=login"><i class="<?= $p === 'microsoft' ? 'fab fa-microsoft' : 'fab fa-' . ($p === 'discord' ? 'discord' : ($p === 'github' ? 'github' : 'google')) ?>"></i> <?= htmlspecialchars($cfg['label']) ?></a>
<?php endforeach; ?>
</div>
<div class="or">OR</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<form method="POST" action="/register" autocomplete="on">
    <?= csrf_field() ?>
<div class="field">
<label for="username">Username</label>
<div class="input-wrap">
<i class="fa-solid fa-at"></i>
<input type="text" id="username" name="username" placeholder="username" required autocomplete="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
</div>
</div>
<div class="field">
<label for="email">Email</label>
<div class="input-wrap">
<i class="fa-solid fa-envelope"></i>
<input type="email" id="email" name="email" placeholder="you@mail.com" required autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
</div>
</div>
<div class="field">
<label for="password">Password</label>
<div class="input-wrap">
<i class="fa-solid fa-lock"></i>
<input type="password" id="password" name="password" placeholder="At least 8 characters" required autocomplete="new-password">
<button type="button" class="pass-toggle" id="passToggle" aria-label="Show/hide password"><i class="fas fa-eye" id="eyeIcon"></i></button>
</div>
<div class="strength">
<div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
<span class="strength-label" id="strengthLabel">Weak</span>
</div>
</div>
<div class="field">
<label for="password2">Confirm Password</label>
<div class="input-wrap">
<i class="fa-solid fa-lock"></i>
<input type="password" id="password2" name="password2" placeholder="Repeat password" required autocomplete="new-password">
<button type="button" class="pass-toggle" id="passToggle2" aria-label="Show/hide password"><i class="fas fa-eye" id="eyeIcon2"></i></button>
</div>
</div>
<label class="terms">
<input type="checkbox" name="accept_terms" value="1" <?= !empty($_POST['accept_terms']) ? 'checked' : '' ?>>
<span>By signing up, I confirm that I have read, understood and accept the <a href="/terms-of-use" target="_blank">Terms of Use</a> and <a href="/privacy-policy" target="_blank">Privacy Policy</a>.</span>
</label>
<?php if (turnstile_enabled()): ?><?php echo turnstile_widget(); echo turnstile_script(); ?><?php endif; ?>
<button type="submit" class="btn-submit"><i class="fa-solid fa-user-plus"></i> Sign Up</button>
<input type="hidden" name="vid" id="vidField" value="">
</form>
<script>
</script>
<p class="auth-alt">Already have an account? <a href="/login">Sign In</a></p>
<div class="back-home"><a href="/"><i class="fa-solid fa-arrow-left"></i> Home</a></div>
<div class="form-foot">Vexaris &copy; <?= date('Y') ?></div>
</div>
</div>
<aside class="auth-visual" aria-hidden="false">
<i class="fa-solid fa-bolt vis-bolt" aria-hidden="true"></i>
<div class="vis-eyebrow">Vexaris Platform</div>
<div class="vis-title">Your store ready<br>in minutes.</div>
<div class="vis-card" id="visCard">
<div class="vis-ic" id="visIcon"><i class="fa-solid fa-box"></i></div>
<h3 id="visTitle">Product &amp; Stock Management</h3>
<p id="visDesc">Unlimited and single-stock modes, zip uploads, auto-pause when out of stock.</p>
</div>
<div class="vis-dots" id="visDots"><span class="on"></span><span></span><span></span><span></span><span></span></div>
</aside>
<script>
(function(){
function bindToggle(btnId,inputId,iconId){var btn=document.getElementById(btnId),input=document.getElementById(inputId),icon=document.getElementById(iconId);if(btn&&input&&icon){btn.addEventListener('click',function(){var show=input.type==='password';input.type=show?'text':'password';icon.className=show?'fas fa-eye-slash':'fas fa-eye';});}}
bindToggle('passToggle','password','eyeIcon');
bindToggle('passToggle2','password2','eyeIcon2');
var pw=document.getElementById('password'),fill=document.getElementById('strengthFill'),label=document.getElementById('strengthLabel');
if(pw&&fill&&label){pw.addEventListener('input',function(){var v=pw.value,score=0;if(v.length>=8)score++;if(v.length>=12)score++;if(/[A-Z]/.test(v)&&/[a-z]/.test(v))score++;if(/\d/.test(v))score++;if(/[^A-Za-z0-9]/.test(v))score++;var names=['Weak','Fair','Medium','Good','Strong','Very Strong'],idx=Math.min(score,5),colors=['#ff4757','#ff6b81','#ffa502','#2ed573','#4f8fc0','#4f8fc0'];fill.style.width=(idx/5*100)+'%';fill.style.background=colors[idx];label.textContent=names[idx];label.style.color=colors[idx];});}
var feats=[
{icon:'fa-box',title:'Product & Stock Management',desc:'Unlimited and single-stock modes, zip uploads, auto-pause when out of stock.'},
{icon:'fa-palette',title:'3 Ready-made Themes',desc:'Switch between Default, Azure and Ember theme sets with one click.'},
{icon:'fa-ticket',title:'Coupons & Campaigns',desc:'Boost sales with percentage-off or free-product coupons.'},
{icon:'fa-bolt',title:'Instant Delivery',desc:'Files and keys land in the customer library the second payment is confirmed.'},
{icon:'fa-wallet',title:'7 Payment Integrations',desc:'Shopier, PayTR, iyzico, Stripe, PayPal, BitPay and Cryptomus in one dashboard.'}
];
var i=0,card=document.getElementById('visCard'),ic=document.getElementById('visIcon'),ti=document.getElementById('visTitle'),de=document.getElementById('visDesc'),dots=document.getElementById('visDots');
function paint(n){i=(n+feats.length)%feats.length;if(ic)ic.innerHTML='<i class="fa-solid '+feats[i].icon+'"></i>';if(ti)ti.textContent=feats[i].title;if(de)de.textContent=feats[i].desc;if(dots){var d=dots.children;for(var k=0;k<d.length;k++){d[k].className=k===i?'on':'';}}}
function cycle(){if(!card||document.hidden)return;card.classList.add('swap');setTimeout(function(){paint(i+1);if(card)card.classList.remove('swap');},380);}
if(card&&ti){var timer=setInterval(cycle,4200);if(dots){var d=dots.children;for(var k=0;k<d.length;k++)(function(n){d[n].addEventListener('click',function(){paint(n);});})(k);}}
})();
</script>
<!--VXFP-->
<script src="/assets/vendor/fpjs/fp.min.js?v=5.3.0" defer></script>
<script src="/assets/js/vx-fp.js?v=<?= @filemtime(__DIR__ . '/../assets/js/vx-fp.js') ?>" defer></script>
</body>
</html>

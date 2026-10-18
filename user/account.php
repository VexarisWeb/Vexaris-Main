<?php
$activePage = 'account';
require_once __DIR__ . '/_inc/header.php';
require_once __DIR__ . '/../core/oauth.php';
require_once __DIR__ . '/../core/turnstile.php';
$u = current_user();
$oauthList = oauth_configured_list();
$linkedAccounts = oauth_linked_accounts((int)$u['id']);
$linkedOk = (string)($_GET['linked'] ?? '');
$oauthErr = (string)($_GET['err'] ?? '');
$providerLabels = ['google' => 'Google', 'discord' => 'Discord', 'github' => 'GitHub', 'microsoft' => 'Microsoft'];
$activationOn = false;
$isActive = true;
try {
    $activationOn = setting_get('activation_enabled', '1') === '1';
    $pdoA = db();
    $isActive = $pdoA ? user_is_active($pdoA, (int)$u['id']) : true;
} catch (Throwable $e) { $isActive = true; }
$showInactiveBanner = ($activationOn && !$isActive) || isset($_GET['inactive']);
?>
<?php if ($showInactiveBanner): ?>
<div style="background:rgba(224,177,60,.08);border:1px solid rgba(224,177,60,.4);border-radius:14px;padding:1.1rem 1.3rem;margin-bottom:1.2rem;display:flex;gap:.9rem;align-items:flex-start">
    <div style="width:42px;height:42px;flex:none;border-radius:12px;background:rgba(224,177,60,.12);display:flex;align-items:center;justify-content:center;color:#e0b13c;font-size:1.2rem"><i class="fas fa-envelope-circle-check"></i></div>
    <div style="font-size:.87rem;line-height:1.65;color:#e9e7e2">
        <strong>Your account is not activated yet.</strong><br>
        <span style="color:var(--muted)">An activation link was sent to your email during registration. Please check your inbox and <strong style="color:#e9e7e2;">spam / junk</strong> folder and click the link. Links expire after 48 hours. You cannot use other panel sections until activation is complete.</span>
    </div>
</div>
<?php endif; ?>
<div class="main__header">
    <h1>Account Settings</h1>
    <p>Email and password operations</p>
</div>

<?php
$myLic = null; $mySub = null;
try {
    $pdoL = db();
    if ($pdoL) {
        $ls = $pdoL->prepare("SELECT license_key, allowed_domains, features, status FROM licenses WHERE user_id=? ORDER BY id ASC LIMIT 1");
        $ls->execute([(int)$u['id']]);
        $myLic = $ls->fetch() ?: null;
        $ss = $pdoL->prepare("SELECT plan, cycle, status, current_period_end FROM user_subscriptions WHERE user_id=? LIMIT 1");
        $ss->execute([(int)$u['id']]);
        $mySub = $ss->fetch() ?: null;
    }
} catch (Throwable $e) {}
?>
<?php if ($myLic || $mySub): ?>
<?php
$licFeat = $myLic ? (json_decode((string)$myLic['features'], true) ?: []) : [];
$licPlan = strtolower((string)($licFeat['plan'] ?? ($mySub['plan'] ?? 'free')));
$licDomains = $myLic ? (json_decode((string)$myLic['allowed_domains'], true) ?: []) : [];
$subLive = $mySub && ($mySub['status'] ?? '') === 'active' && (int)($mySub['current_period_end'] ?? 0) > time();
?>
<div class="panel-card" style="margin-bottom:1.2rem;border-color:rgba(79,143,192,.35)">
    <div class="panel-card__head"><div class="panel-card__title"><i class="fas fa-crown"></i> My Plan &amp; License</div><div style="display:flex;gap:.5rem;flex-wrap:wrap"><a href="/dashboard/subscription" class="btn-panel" style="text-decoration:none"><i class="fas fa-gauge-high"></i> Manage</a><a href="/pricing" class="btn-panel btn-cyan" style="text-decoration:none"><i class="fas fa-arrow-up"></i> <?= $subLive ? 'Change plan' : 'View plans' ?></a></div></div>
    <div class="panel-card__body">
        <div style="display:flex;gap:1.5rem;flex-wrap:wrap;align-items:center">
            <div>
                <div style="font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.08em">Plan</div>
                <div style="font-size:1.15rem;font-weight:800;color:#fff"><?= htmlspecialchars(ucfirst($licPlan)) ?><?= $mySub ? ' <span style="font-size:.72rem;font-weight:600;color:var(--muted)">(' . htmlspecialchars($mySub['cycle'] === 'yearly' ? 'Yearly' : 'Monthly') . ($subLive ? ' · until ' . date('d.m.Y', (int)$mySub['current_period_end']) : ' · expired') . ')</span>' : '' ?></div>
            </div>
            <?php if ($myLic): ?>
            <div style="min-width:0">
                <div style="font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.08em">License key</div>
                <div style="font-family:monospace;font-size:.85rem;color:#e9e7e2;word-break:break-all"><?= htmlspecialchars($myLic['license_key']) ?></div>
            </div>
            <div>
                <div style="font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.08em">Domains</div>
                <div style="font-size:.85rem;color:#e9e7e2"><?= $licDomains ? htmlspecialchars(implode(', ', $licDomains)) : '<span style="color:var(--muted)">No domain yet — contact support to add your store domain.</span>' ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem" class="acc-grid">
    <div class="panel-card">
        <div class="panel-card__head"><div class="panel-card__title"><i class="fas fa-envelope"></i> Change Email</div></div>
        <div class="panel-card__body">
            <div style="font-size:.85rem;color:var(--muted);margin-bottom:1rem">Current: <strong style="color:#fff"><?= htmlspecialchars($u['email']) ?></strong></div>
            <p style="font-size:.83rem;color:var(--muted);margin-bottom:1rem">A verification code is sent to your new address for confirmation.</p>
            <button onclick="openEmailModal()" class="btn-panel btn-cyan" style="width:100%;justify-content:center"><i class="fas fa-envelope"></i> Change Email</button>
        </div>
    </div>
    <div class="panel-card">
        <div class="panel-card__head"><div class="panel-card__title"><i class="fas fa-key"></i> Change Password</div></div>
        <div class="panel-card__body">
            <p style="font-size:.83rem;color:var(--muted);margin-bottom:1rem">Update your password here for account security. Other devices are signed out when you change it.</p>
            <button onclick="document.getElementById('passModal').style.display='flex'" class="btn-panel btn-cyan" style="width:100%;justify-content:center"><i class="fas fa-key"></i> Change Password</button>
        </div>
    </div>
</div>
<style>
.vx-view{overflow:hidden}
.vx-track{display:flex;transition:transform .38s cubic-bezier(.22,1,.36,1)}
.vx-pane{min-width:100%;box-sizing:border-box}
.vx-cd{font-size:.78rem;color:var(--muted);text-align:center;margin-top:.7rem}
.vx-cd b{color:var(--cyan);font-variant-numeric:tabular-nums}
</style>
<div id="emailModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center;padding:1.5rem" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:linear-gradient(180deg,#161b21,#12161a);border:1px solid var(--border);border-radius:16px;padding:1.6rem;max-width:400px;width:92%">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:1.2rem">
            <div style="font-size:1rem;font-weight:800;color:#fff"><i class="fas fa-envelope" style="color:var(--cyan);margin-right:8px"></i>Change Email</div>
            <button onclick="document.getElementById('emailModal').style.display='none'" style="margin-left:auto;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--muted);width:28px;height:28px;cursor:pointer">×</button>
        </div>
        <div class="vx-view"><div class="vx-track" id="emailTrack">
            <div class="vx-pane">
                <label style="font-size:.78rem;color:var(--muted)">New email</label>
                <input id="newEmail" type="email" placeholder="new@mail.com" style="width:100%;margin:.4rem 0 1rem;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:#fff;padding:.65rem .9rem;outline:none">
                <button onclick="changeEmail()" id="btnEmail" class="btn-panel btn-cyan" style="width:100%;justify-content:center"><i class="fas fa-paper-plane"></i> Send Verification</button>
                <div id="emailMsg" style="font-size:.82rem;margin-top:.7rem;display:none"></div>
            </div>
            <div class="vx-pane">
                <label style="font-size:.78rem;color:var(--muted)">Verification code (from email)</label>
                <div style="display:flex;gap:.5rem;margin-top:.4rem">
                    <input id="emailToken" placeholder="••••••" maxlength="32" style="flex:1;min-width:0;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:#fff;padding:.65rem .9rem;outline:none;letter-spacing:.2em;text-align:center;font-weight:700">
                    <button onclick="verifyEmail()" class="btn-panel btn-green" style="flex:none">Verify</button>
                </div>
                <div id="verifyMsg" style="font-size:.82rem;margin-top:.5rem;display:none"></div>
                <button onclick="changeEmail(true)" id="btnResend" class="btn-panel" style="width:100%;justify-content:center;margin-top:.8rem"><i class="fas fa-rotate"></i> Resend</button>
                <div class="vx-cd" id="emailCd" style="display:none">Can resend in: <b>01:00</b></div>
            </div>
        </div></div>
    </div>
</div>
<div id="passModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center;padding:1.5rem" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:linear-gradient(180deg,#161b21,#12161a);border:1px solid var(--border);border-radius:16px;padding:1.6rem;max-width:400px;width:92%">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:1.2rem">
            <div style="font-size:1rem;font-weight:800;color:#fff"><i class="fas fa-key" style="color:var(--cyan);margin-right:8px"></i>Change Password</div>
            <button onclick="document.getElementById('passModal').style.display='none'" style="margin-left:auto;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--muted);width:28px;height:28px;cursor:pointer">×</button>
        </div>
        <label style="font-size:.78rem;color:var(--muted)">Current password</label>
        <input id="curPass" type="password" style="width:100%;margin:.4rem 0 .8rem;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:#fff;padding:.65rem .9rem;outline:none">
        <label style="font-size:.78rem;color:var(--muted)">New password</label>
        <input id="newPass" type="password" style="width:100%;margin:.4rem 0 .8rem;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:#fff;padding:.65rem .9rem;outline:none">
        <label style="font-size:.78rem;color:var(--muted)">Repeat new password</label>
        <input id="newPass2" type="password" style="width:100%;margin:.4rem 0 1rem;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:#fff;padding:.65rem .9rem;outline:none">
        <button onclick="changePass()" id="btnPass" class="btn-panel btn-cyan" style="width:100%;justify-content:center"><i class="fas fa-floppy-disk"></i> Update Password</button>
        <div id="passMsg" style="font-size:.82rem;margin-top:.7rem;display:none"></div>
        <div style="text-align:center;margin-top:.8rem"><a href="#" onclick="document.getElementById('passModal').style.display='none';fGo(1);document.getElementById('forgotModal').style.display='flex';return false" style="font-size:.82rem;color:var(--cyan);text-decoration:none">Forgot password</a></div>
    </div>
</div>
<div class="panel-card" style="margin-top:1.2rem">
    <div class="panel-card__head">
        <div class="panel-card__title"><i class="fas fa-desktop"></i> My Sessions</div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <button onclick="terminateAllOthers()" class="btn-panel" style="padding:6px 12px;font-size:.75rem;background:rgba(239,68,68,.12);color:#f87171;border:1px solid rgba(239,68,68,.3)"><i class="fas fa-right-from-bracket"></i> Sign out others</button>
            <button onclick="loadSessions()" class="btn-panel btn-cyan" style="padding:6px 12px;font-size:.75rem"><i class="fas fa-rotate"></i> Refresh</button>
        </div>
    </div>
    <div class="panel-card__body">
        <p style="font-size:.82rem;color:var(--muted);margin-bottom:.8rem">Devices signed in to your account. If you see an unfamiliar session, close it and change your password.</p>
        <div id="sessList" style="display:flex;flex-direction:column;gap:.6rem"><div class="empty"><i class="fas fa-spinner fa-spin"></i>Loading...</div></div>
    </div>
</div>
<div class="panel-card" style="margin-top:1.2rem">
    <div class="panel-card__head"><div class="panel-card__title"><i class="fas fa-link"></i> Connected Social Accounts</div></div>
    <div class="panel-card__body">
        <?php if ($linkedOk && isset($providerLabels[$linkedOk])): ?>
        <div style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.3);border-radius:10px;padding:.7rem 1rem;font-size:.83rem;color:#4ade80;margin-bottom:.8rem"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($providerLabels[$linkedOk]) ?> account connected successfully.</div>
        <?php endif; ?>
        <?php if ($oauthErr === 'taken'): ?>
        <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:.7rem 1rem;font-size:.83rem;color:#f87171;margin-bottom:.8rem"><i class="fas fa-circle-exclamation"></i> This social account is linked to another user. Remove it from that account first.</div>
        <?php elseif ($oauthErr === 'disabled'): ?>
        <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:.7rem 1rem;font-size:.83rem;color:#f87171;margin-bottom:.8rem"><i class="fas fa-circle-exclamation"></i> This sign-in method is currently disabled.</div>
        <?php elseif ($oauthErr === 'domain'): ?>
        <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:.7rem 1rem;font-size:.83rem;color:#f87171;margin-bottom:.8rem"><i class="fas fa-circle-exclamation"></i> This email provider is not accepted. Please use a trusted provider.</div>
        <?php elseif ($oauthErr !== ''): ?>
        <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:.7rem 1rem;font-size:.83rem;color:#f87171;margin-bottom:.8rem"><i class="fas fa-circle-exclamation"></i> Connection failed. Please try again.</div>
        <?php endif; ?>
        <?php if ($oauthList): foreach ($oauthList as $p => $cfg): $linked = $linkedAccounts[$p] ?? null; ?>
        <div style="display:flex;align-items:center;gap:.9rem;padding:.8rem 0;border-bottom:1px solid var(--border)">
            <div style="width:42px;height:42px;flex:none;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;<?= $p === 'google' ? 'background:rgba(234,67,53,.12);color:#ea4335' : ($p === 'discord' ? 'background:rgba(88,101,242,.15);color:#5865f2' : ($p === 'microsoft' ? 'background:rgba(0,120,212,.15);color:#2b88d8' : 'background:rgba(255,255,255,.07);color:#fff')) ?>"><i class="<?= $p === 'microsoft' ? 'fa-brands fa-microsoft' : 'fa-brands fa-' . ($p === 'discord' ? 'discord' : ($p === 'github' ? 'github' : 'google')) ?>"></i></div>
            <div style="flex:1;min-width:0">
                <div style="font-weight:700;color:#fff;font-size:.88rem"><?= htmlspecialchars($cfg['label']) ?></div>
                <?php if ($linked): ?>
                <div style="font-size:.76rem;color:#4ade80;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><i class="fas fa-check-circle"></i> Connected<?= !empty($linked['email']) ? ' — ' . htmlspecialchars($linked['email']) : '' ?></div>
                <?php else: ?>
                <div style="font-size:.76rem;color:var(--muted)">Not connected — connect for one-click sign-in</div>
                <?php endif; ?>
            </div>
            <?php if ($linked): ?>
            <button onclick="unlinkOAuth('<?= $p ?>', this)" class="btn-panel" style="flex:none"><i class="fas fa-unlink"></i> Remove</button>
            <?php else: ?>
            <a href="/api/oauth?provider=<?= $p ?>&action=login" class="btn-panel btn-cyan" style="flex:none;text-decoration:none"><i class="fas fa-link"></i> Connect</a>
            <?php endif; ?>
        </div>
        <?php endforeach; else: ?>
        <div class="empty"><i class="fas fa-link"></i>Social sign-in is currently disabled. Google and Discord connections coming soon.</div>
        <?php endif; ?>
    </div>
</div>
<div id="forgotModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center;padding:1.5rem" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:linear-gradient(180deg,#161b21,#12161a);border:1px solid var(--border);border-radius:16px;padding:1.6rem;max-width:400px;width:92%">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:1.2rem">
            <div style="font-size:1rem;font-weight:800;color:#fff"><i class="fas fa-key" style="color:var(--cyan);margin-right:8px"></i>Forgot Password</div>
            <button onclick="document.getElementById('forgotModal').style.display='none'" style="margin-left:auto;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--muted);width:28px;height:28px;cursor:pointer">×</button>
        </div>
        <div class="vx-view"><div class="vx-track" id="fTrack">
        <div class="vx-pane" id="fStep1">
            <label style="font-size:.78rem;color:var(--muted)">E-posta adresiniz</label>
            <input id="fEmail" type="email" value="<?= htmlspecialchars($u['email']) ?>" style="width:100%;margin:.4rem 0 1rem;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:#fff;padding:.65rem .9rem;outline:none">
            <?php if (turnstile_enabled()): ?><?php echo turnstile_widget('cf-turnstile-acc'); echo turnstile_script(); ?><?php endif; ?>
            <button onclick="fSend()" id="fBtn1" class="btn-panel btn-cyan" style="width:100%;justify-content:center"><i class="fas fa-paper-plane"></i> Send Code</button>
        </div>
        <div class="vx-pane" id="fStep2">
            <label style="font-size:.78rem;color:var(--muted)">6 haneli kod</label>
            <input id="fCode" type="text" maxlength="6" inputmode="numeric" placeholder="••••••" style="width:100%;margin:.4rem 0 1rem;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:#fff;padding:.65rem .9rem;outline:none;letter-spacing:.4em;text-align:center;font-weight:800">
            <button onclick="fVerify()" id="fBtn2" class="btn-panel btn-cyan" style="width:100%;justify-content:center">Verify</button>
            <button onclick="fSend()" id="fBtnR" class="btn-panel" style="width:100%;justify-content:center;margin-top:.6rem"><i class="fas fa-rotate"></i> Resend</button>
            <div class="vx-cd" id="fCd" style="display:none">Can resend in: <b>01:00</b></div>
        </div>
        <div class="vx-pane" id="fStep3">
            <label style="font-size:.78rem;color:var(--muted)">New password (min 6 characters)</label>
            <input id="fNp" type="password" style="width:100%;margin:.4rem 0 .8rem;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:#fff;padding:.65rem .9rem;outline:none">
            <label style="font-size:.78rem;color:var(--muted)">Repeat new password</label>
            <input id="fNp2" type="password" style="width:100%;margin:.4rem 0 1rem;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:#fff;padding:.65rem .9rem;outline:none">
            <button onclick="fSave()" id="fBtn3" class="btn-panel btn-cyan" style="width:100%;justify-content:center">Update Password</button>
        </div>
        </div></div>
        <div id="fMsg" style="font-size:.82rem;margin-top:.7rem;display:none"></div>
    </div>
</div>
<div id="sysPopup" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center;padding:1.5rem" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:#0d1b3e;border:1px solid rgba(0,212,255,.25);border-radius:16px;padding:1.6rem;max-width:400px;width:92%;text-align:center">
        <div style="width:48px;height:48px;margin:0 auto .8rem;background:rgba(34,197,94,.12);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#4ade80;font-size:1.4rem"><i class="fas fa-check"></i></div>
        <h3 style="color:#fff;font-weight:800" id="sysPopupTitle">Success</h3><p id="sysPopupMsg" style="color:var(--muted);font-size:.9rem;margin:.5rem 0 1rem"></p>
        <button onclick="document.getElementById('sysPopup').style.display='none'" class="btn-panel btn-cyan" style="width:100%;justify-content:center">Tamam</button>
    </div>
</div>
<div style="margin-top:1rem;background:rgba(0,212,255,.07);border:1px solid var(--border);border-radius:12px;padding:1rem;font-size:.82rem;color:var(--muted)">
    <i class="fas fa-circle-info" style="color:var(--cyan)"></i> When changing email, a verification code is sent to your new address. Codes expire in 30 min. Check spam if mail does not arrive.
</div>
<script>
const VX_CSRF = <?= json_encode(csrf_token()) ?>;
const ACC_CSRF=VX_CSRF||(document.querySelector('meta[name=csrf]')?.content||'');
function showPopup(title,msg){document.getElementById('sysPopupTitle').textContent=title;document.getElementById('sysPopupMsg').textContent=msg;document.getElementById('sysPopup').style.display='flex';}
async function unlinkOAuth(p,btn){
    if(!confirm('Remove this social account connection?')) return;
    const orig=btn.innerHTML; btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';
    try{
        const fd=new FormData(); fd.append('action','unlink_oauth'); fd.append('provider',p);
        const r=await fetch('/api/account',{method:'POST',headers:{'X-CSRF-Token':ACC_CSRF},body:fd});
        const d=await r.json();
        if(d.ok) location.reload(); else { alert(d.msg||'Could not remove.'); btn.disabled=false; btn.innerHTML=orig; }
    }catch(e){ alert('Connection error.'); btn.disabled=false; btn.innerHTML=orig; }
}
function showMsg(id, ok, txt){const e=document.getElementById(id); e.style.display='block'; e.style.color=ok?'#4ade80':'#f87171'; e.textContent=txt; if(ok&&txt.includes('directly')) showPopup('Success',txt); setTimeout(()=>e.style.display='none',4000);}
let resendTimer = null, emailSends = 0;
function mmss(s){ s=Math.max(0,s); return String(Math.floor(s/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0'); }
function openEmailModal(){ emailGo(1); document.getElementById('emailModal').style.display='flex'; }
function emailGo(n){ document.getElementById('emailTrack').style.transform='translateX(-'+((n-1)*100)+'%)'; }
function lockResend(){
    const btn=document.getElementById('btnResend'), cd=document.getElementById('emailCd');
    clearInterval(resendTimer); btn.disabled=true;
    btn.innerHTML='<i class="fas fa-ban"></i> Resend limit reached';
    cd.style.display='none';
}
function armResend(){
    const btn=document.getElementById('btnResend'), cd=document.getElementById('emailCd'), cdb=cd.querySelector('b');
    clearInterval(resendTimer);
    const wait = emailSends===1 ? 60 : (emailSends===2 ? 300 : 0);
    if(wait<=0){ lockResend(); return; }
    let left=wait;
    btn.disabled=true; cd.style.display='block'; cdb.textContent=mmss(left);
    resendTimer=setInterval(()=>{
        left--;
        if(left<=0){ clearInterval(resendTimer); btn.disabled=false; cd.style.display='none'; }
        else cdb.textContent=mmss(left);
    },1000);
}
async function changeEmail(isResend){
    const email=document.getElementById('newEmail').value.trim();
    if(!email) return showMsg('emailMsg',false,'Enter an email.');
    const btn=isResend?document.getElementById('btnResend'):document.getElementById('btnEmail');
    btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';
    try{
        const fd=new FormData(); fd.append('action','request_email_change'); fd.append('new_email',email);
        const r=await fetch('/api/account',{method:'POST',headers:{'X-CSRF-Token':ACC_CSRF},body:fd});
        const d=await r.json();
        if(isResend){ showMsg('verifyMsg',d.ok,d.msg||(d.ok?'Code sent':'Error')); }
        else showMsg('emailMsg',d.ok,d.msg||(d.ok?'Code sent':'Error'));
        if(d.ok){ emailSends++; emailGo(2); armResend(); }
        else if(/daha sonra/.test(d.msg||'')) lockResend();
    }catch(e){showMsg(isResend?'verifyMsg':'emailMsg',false,'Connection error.');}
    btn.disabled=false; btn.innerHTML=isResend?'<i class="fas fa-rotate"></i> Resend':'<i class="fas fa-paper-plane"></i> Send Verification';
}
async function verifyEmail(){
    const token=document.getElementById('emailToken').value.trim();
    if(!token) return showMsg('verifyMsg',false,'Enter the code.');
    try{
        const fd=new FormData(); fd.append('action','verify_email_change'); fd.append('token',token);
        const r=await fetch('/api/account',{method:'POST',headers:{'X-CSRF-Token':ACC_CSRF},body:fd});
        const d=await r.json(); showMsg('verifyMsg',d.ok,d.msg); if(d.ok) setTimeout(()=>location.reload(),1200);
    }catch(e){showMsg('verifyMsg',false,'Connection error.');}
}
async function changePass(){
    const cur=document.getElementById('curPass').value;
    const np=document.getElementById('newPass').value;
    const np2=document.getElementById('newPass2').value;
    if(!cur||!np||!np2) return showMsg('passMsg',false,'Fill in all fields.');
    if(np!==np2) return showMsg('passMsg',false,'New passwords do not match.');
    if(np.length<6) return showMsg('passMsg',false,'Min 6 characters.');
    const btn=document.getElementById('btnPass'); btn.disabled=true;
    try{
        const fd=new FormData(); fd.append('action','change_password'); fd.append('current',cur); fd.append('new',np);
        const r=await fetch('/api/account',{method:'POST',headers:{'X-CSRF-Token':ACC_CSRF},body:fd});
        const d=await r.json(); showMsg('passMsg',d.ok,d.msg); if(d.ok){document.getElementById('curPass').value='';document.getElementById('newPass').value='';document.getElementById('newPass2').value='';document.getElementById('passModal').style.display='none';showPopup('Success','Password updated. Other devices have been signed out.');loadSessions();}
    }catch(e){showMsg('passMsg',false,'Connection error.');}
    btn.disabled=false;
}
async function loadSessions(){
    const el=document.getElementById('sessList');
    try{
        const fd=new FormData(); fd.append('action','sessions_list');
        const r=await fetch('/api/account',{method:'POST',headers:{'X-CSRF-Token':ACC_CSRF},body:fd});
        const d=await r.json();
        if(!d.ok||!d.sessions){ el.innerHTML='<div class="empty">Could not load sessions.</div>'; return; }
        if(!d.sessions.length){ el.innerHTML='<div class="empty">No active sessions.</div>'; return; }
        el.innerHTML=d.sessions.map(s=>{
            const badge=s.current
                ? '<span class="badge badge-active"><i class="fas fa-check"></i> This device</span>'
                : '<button onclick="terminateSession(this)" data-key="'+s.key.replace(/"/g,'')+'" class="btn-panel btn-red" style="padding:6px 12px;font-size:.75rem;flex:none"><i class="fas fa-xmark"></i> Close</button>';
            const icon=s.mobile?'fa-mobile-screen':'fa-desktop';
            return '<div style="display:flex;align-items:center;gap:.8rem;padding:.7rem .9rem;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:10px;flex-wrap:wrap">'
                + '<div style="width:36px;height:36px;flex:none;border-radius:10px;background:rgba(79,143,192,.1);display:flex;align-items:center;justify-content:center;color:#7fb3d9"><i class="fas '+icon+'"></i></div>'
                + '<div style="flex:1;min-width:0"><div style="font-size:.85rem;font-weight:700;color:#fff">'+String(s.device).replace(/</g,'&lt;')+'</div>'
                + '<div style="font-size:.7rem;color:var(--muted)">IP: '+String(s.ip).replace(/</g,'&lt;')+' &middot; Last seen: '+String(s.last_seen).replace(/</g,'&lt;')+'</div></div>'
                + badge + '</div>';
        }).join('');
    }catch(e){ el.innerHTML='<div class="empty">Connection error.</div>'; }
}
async function terminateSession(btn){
    if(!confirm('Close this session?')) return;
    btn.disabled=true;
    try{
        const fd=new FormData(); fd.append('action','sessions_terminate'); fd.append('key',btn.getAttribute('data-key')||'');
        const r=await fetch('/api/account',{method:'POST',headers:{'X-CSRF-Token':ACC_CSRF},body:fd});
        const d=await r.json();
        if(d.ok) loadSessions(); else alert(d.msg||'Could not close.');
    }catch(e){ alert('Connection error.'); }
    btn.disabled=false;
}
async function terminateAllOthers(){
    if(!confirm('Sign out all other sessions?')) return;
    try{
        const fd=new FormData(); fd.append('action','sessions_terminate_all');
        const r=await fetch('/api/account',{method:'POST',headers:{'X-CSRF-Token':ACC_CSRF},body:fd});
        const d=await r.json();
        if(d.ok){ showPopup('Success','All other sessions have been signed out.'); loadSessions(); }
        else alert(d.msg||'Could not sign out.');
    }catch(e){ alert('Connection error.'); }
}
let fEmail = '', fToken = '', fSends = 0, fCdTimer = null;
function fShow(ok, txt){const e=document.getElementById('fMsg'); e.style.display='block'; e.style.color=ok?'#4ade80':'#f87171'; e.textContent=txt;}
function fGo(n){
    document.getElementById('fTrack').style.transform='translateX(-'+((n-1)*100)+'%)';
    document.getElementById('fMsg').style.display='none';
}
function fLock(){
    const btn=document.getElementById('fBtnR'), cd=document.getElementById('fCd');
    clearInterval(fCdTimer); btn.disabled=true;
    btn.innerHTML='<i class="fas fa-ban"></i> Resend limit reached';
    cd.style.display='none';
}
function fArm(){
    const btn=document.getElementById('fBtnR'), cd=document.getElementById('fCd'), cdb=cd.querySelector('b');
    clearInterval(fCdTimer);
    const wait = fSends===1 ? 60 : (fSends===2 ? 300 : 0);
    if(wait<=0){ fLock(); return; }
    let left=wait;
    btn.disabled=true; cd.style.display='block'; cdb.textContent=mmss(left);
    fCdTimer=setInterval(()=>{
        left--;
        if(left<=0){ clearInterval(fCdTimer); btn.disabled=false; cd.style.display='none'; }
        else cdb.textContent=mmss(left);
    },1000);
}
async function fPost(fd){
    const r=await fetch('/api/auth',{method:'POST',headers:{'X-CSRF-Token':ACC_CSRF},body:fd});
    return r.json();
}
async function fSend(){
    const email=document.getElementById('fEmail').value.trim();
    if(!email) return fShow(false,'Enter an email.');
    const btn=document.getElementById('fBtn1');
    btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';
    try{
        const fd=new FormData(); fd.append('action','request_reset'); fd.append('email',email);
        const tsEl=document.querySelector('#forgotModal [name="cf-turnstile-response"]');
        if(tsEl&&tsEl.value) fd.append('cf-turnstile-response', tsEl.value);
        const d=await fPost(fd);
        try{ if(window.turnstile) window.turnstile.reset(); }catch(e){}
        fShow(!!d.ok, d.msg||'Error');
        if(d.ok){ fEmail=email; fSends++; fGo(2); fArm(); }
        else if(/daha sonra/.test(d.msg||'')) fLock();
    }catch(e){ fShow(false,'Connection error.'); }
    btn.disabled=false; btn.innerHTML='<i class="fas fa-paper-plane"></i> Send Code';
}
async function fVerify(){
    const code=document.getElementById('fCode').value.replace(/[^0-9]/g,'');
    if(code.length!==6) return fShow(false,'Enter the 6-digit code.');
    const btn=document.getElementById('fBtn2'); btn.disabled=true; btn.textContent='Verifying...';
    try{
        const fd=new FormData(); fd.append('action','verify_reset'); fd.append('email',fEmail); fd.append('code',code);
        const d=await fPost(fd);
        fShow(!!d.ok, d.msg||'Error');
        if(d.ok&&d.token){ fToken=d.token; fGo(3); }
    }catch(e){ fShow(false,'Connection error.'); }
    btn.disabled=false; btn.textContent='Verify';
}
async function fSave(){
    const np=document.getElementById('fNp').value, np2=document.getElementById('fNp2').value;
    if(np.length<6) return fShow(false,'Min 6 characters.');
    if(np!==np2) return fShow(false,'Passwords do not match.');
    const btn=document.getElementById('fBtn3'); btn.disabled=true; btn.textContent='Saving...';
    try{
        const fd=new FormData(); fd.append('action','reset_password'); fd.append('email',fEmail); fd.append('token',fToken); fd.append('new',np);
        const d=await fPost(fd);
        if(d.ok){ document.getElementById('forgotModal').style.display='none'; showPopup('Success','Password updated. Please sign in again for your security.'); setTimeout(()=>location.href='/login',2000); }
        else fShow(false, d.msg||'Error');
    }catch(e){ fShow(false,'Connection error.'); }
    btn.disabled=false; btn.textContent='Update Password';
}
loadSessions();
</script>
<?php require_once __DIR__ . '/_inc/footer.php'; ?>
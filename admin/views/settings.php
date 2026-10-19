<?php
require_once __DIR__ . '/../../core/editor.php';
?>
<!-- AYARLAR -->
<div style="max-width:760px">
<?php
$tsEnabled = setting_get('turnstile_enabled','0')==='1';
$tsSite = setting_get('turnstile_site_key','');
$tsSecret = setting_get('turnstile_secret','');
$goEnabled = setting_get('google_oauth_enabled','0')==='1';
$goId = setting_get('google_client_id','');
$goSec = setting_get('google_client_secret','');
$dcEnabled = setting_get('discord_oauth_enabled','0')==='1';
$dcId = setting_get('discord_client_id','');
$dcSec = setting_get('discord_client_secret','');
$ghEnabled = setting_get('github_oauth_enabled','0')==='1';
$ghId = setting_get('github_client_id','');
$ghSec = setting_get('github_client_secret','');
$msEnabled = setting_get('microsoft_oauth_enabled','0')==='1';
$msId = setting_get('microsoft_client_id','');
$msSec = setting_get('microsoft_client_secret','');
$ixEnabled = setting_get('google_indexing_enabled','0')==='1';
$ixHasKey = trim(setting_get('google_service_json','')) !== '';
$whPay = setting_get('whop_payments_enabled','0')==='1';
$whCur = setting_get('whop_currency','usd');
$whApiHas = trim(setting_get('whop_api_key','')) !== '';
$whBiz = setting_get('whop_company_id','');
$whHookHas = trim(setting_get('whop_webhook_secret','')) !== '';
$whSandbox = setting_get('whop_sandbox','0')==='1';
$muEnabled = setting_get('multi_enabled','1')==='1';
$muExtra = setting_get('multi_extra_domains','');
$muAutoban = setting_get('multi_autoban','1')==='1';
$tsHas = trim($tsSecret) !== '';
$goHas = trim($goSec) !== '';
$dcHas = trim($dcSec) !== '';
$ghHas = trim($ghSec) !== '';
$msHas = trim($msSec) !== '';
$secPh = 'Kayıtlı — değiştirmek için yazın, boş bırakılırsa korunur';
?>
    <div class="table-card" style="padding:1.4rem;margin-bottom:1.2rem">
        <div style="font-weight:800;color:#fff;margin-bottom:.8rem"><i class="fas fa-shield-halved" style="color:var(--cyan)"></i> Güvenlik & Entegrasyonlar</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.8rem">
            <div class="int-box" onclick="openIntModal('turnstile')" style="background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;padding:1.2rem;text-align:center;cursor:pointer;transition:border-color .2s">
                <div style="width:48px;height:48px;margin:0 auto .6rem;background:rgba(0,212,255,.1);border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--cyan);font-size:1.4rem"><i class="fas fa-shield"></i></div>
                <div style="font-weight:700;color:#fff;font-size:.9rem">Turnstile</div>
                <div style="font-size:.72rem;color:var(--muted);margin-top:4px"><?= $tsEnabled?'<span style="color:#4ade80">● Aktif</span>':'<span style="color:var(--muted)">○ Kapalı</span>' ?></div>
            </div>
            <div class="int-box" onclick="openIntModal('google')" style="background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;padding:1.2rem;text-align:center;cursor:pointer">
                <div style="width:48px;height:48px;margin:0 auto .6rem;background:rgba(234,67,53,.1);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#ea4335;font-size:1.4rem"><i class="fab fa-google"></i></div>
                <div style="font-weight:700;color:#fff;font-size:.9rem">Google</div>
                <div style="font-size:.72rem;color:var(--muted);margin-top:4px"><?= $goEnabled?'<span style="color:#4ade80">● Aktif</span>':'<span style="color:var(--muted)">○ Kapalı</span>' ?></div>
            </div>
            <div class="int-box" onclick="openIntModal('discord')" style="background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;padding:1.2rem;text-align:center;cursor:pointer">
                <div style="width:48px;height:48px;margin:0 auto .6rem;background:rgba(88,101,242,.15);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#5865f2;font-size:1.4rem"><i class="fab fa-discord"></i></div>
                <div style="font-weight:700;color:#fff;font-size:.9rem">Discord</div>
                <div style="font-size:.72rem;color:var(--muted);margin-top:4px"><?= $dcEnabled?'<span style="color:#4ade80">● Aktif</span>':'<span style="color:var(--muted)">○ Kapalı</span>' ?></div>
            </div>
            <div class="int-box" onclick="openIntModal('github')" style="background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;padding:1.2rem;text-align:center;cursor:pointer">
                <div style="width:48px;height:48px;margin:0 auto .6rem;background:rgba(255,255,255,.07);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.4rem"><i class="fab fa-github"></i></div>
                <div style="font-weight:700;color:#fff;font-size:.9rem">GitHub</div>
                <div style="font-size:.72rem;color:var(--muted);margin-top:4px"><?= $ghEnabled?'<span style="color:#4ade80">● Aktif</span>':'<span style="color:var(--muted)">○ Kapalı</span>' ?></div>
            </div>
            <div class="int-box" onclick="openIntModal('microsoft')" style="background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;padding:1.2rem;text-align:center;cursor:pointer">
                <div style="width:48px;height:48px;margin:0 auto .6rem;background:rgba(0,120,212,.12);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#2b88d8;font-size:1.4rem"><i class="fab fa-microsoft"></i></div>
                <div style="font-weight:700;color:#fff;font-size:.9rem">Microsoft</div>
                <div style="font-size:.72rem;color:var(--muted);margin-top:4px"><?= $msEnabled?'<span style="color:#4ade80">● Aktif</span>':'<span style="color:var(--muted)">○ Kapalı</span>' ?></div>
            </div>
            <div class="int-box" onclick="openIntModal('whop')" style="background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;padding:1.2rem;text-align:center;cursor:pointer">
                <div style="width:48px;height:48px;margin:0 auto .6rem;background:rgba(255,122,26,.12);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#ff7a1a;font-size:1.4rem"><i class="fas fa-store"></i></div>
                <div style="font-weight:700;color:#fff;font-size:.9rem">Whop</div>
                <div style="font-size:.72rem;color:var(--muted);margin-top:4px"><?= $whPay?'<span style="color:#4ade80">● Aktif</span>':'<span style="color:var(--muted)">○ Kapalı</span>' ?></div>
            </div>
            <div class="int-box" onclick="openIntModal('indexing')" style="background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;padding:1.2rem;text-align:center;cursor:pointer">
                <div style="width:48px;height:48px;margin:0 auto .6rem;background:rgba(66,133,244,.12);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#8ab4f8;font-size:1.4rem"><i class="fab fa-google"></i></div>
                <div style="font-weight:700;color:#fff;font-size:.9rem">Google Indexing</div>
                <div style="font-size:.72rem;color:var(--muted);margin-top:4px"><?= ($ixEnabled && $ixHasKey)?'<span style="color:#4ade80">● Aktif</span>':'<span style="color:var(--muted)">○ Kapalı</span>' ?></div>
            </div>
        </div>
    </div>
    <div class="table-card" style="padding:1.4rem;margin-bottom:1.2rem">
        <div style="font-weight:800;color:#fff;margin-bottom:.4rem"><i class="fas fa-user-shield" style="color:var(--cyan)"></i> Multi-Account Protection</div>
        <p style="font-size:.78rem;color:var(--muted);margin-bottom:1rem">Kayıtlar <strong style="color:var(--cyan)">sadece güvenilir sağlayıcılara</strong> açıktır (Gmail, Yahoo, Outlook/Hotmail/Live/MSN, iCloud, Proton, Tuta, GMX, AOL, FastMail, Yandex ve benzerleri). Aynı cihaza/parmak izine bağlı <strong style="color:var(--cyan)">2. ve sonraki hesaplar anında banlanır</strong>; ilk (ana) hesaba dokunulmaz. Yalnızca ortak IP ise ban yok, bayrak var.</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.7rem">
            <label style="font-size:.8rem;color:var(--muted)"><input type="checkbox" id="muEnabled" <?= $muEnabled?'checked':'' ?> style="accent-color:var(--cyan)"> Koruma aktif</label>
            <label style="font-size:.8rem;color:var(--muted)"><input type="checkbox" id="muAutoban" <?= $muAutoban?'checked':'' ?> style="accent-color:var(--cyan)"> Otomatik ban (acımasız)</label>
        </div>
        <label style="font-size:.8rem;color:var(--muted);display:block;margin-top:.8rem">Ekstra izinli uzantılar (satır başına bir domain — örn: sirket.com)</label>
        <textarea id="muExtra" rows="2" placeholder="sirket.com&#10;okul.edu" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:.5rem .7rem;margin-top:.3rem;font-family:monospace;font-size:.78rem"><?= htmlspecialchars($muExtra) ?></textarea>
        <button onclick="saveMulti()" class="btn" style="margin-top:1rem"><i class="fas fa-floppy-disk"></i> Kaydet</button>
        <span id="muMsg" style="font-size:.78rem;margin-left:.6rem;display:none"></span>
    </div>
    <!-- Turnstile Modal -->
    <div id="int-turnstile" class="int-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center;padding:1.5rem" onclick="if(event.target===this)closeIntModal('turnstile')">
        <div style="background:#0d1b3e;border:1px solid rgba(0,212,255,.25);border-radius:18px;padding:1.6rem;max-width:480px;width:92%;position:relative">
            <button onclick="closeIntModal('turnstile')" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.07);border:1px solid var(--border);border-radius:8px;color:var(--text);width:30px;height:30px;cursor:pointer"><i class="fas fa-xmark"></i></button>
            <h3 style="font-weight:800;color:#fff;margin-bottom:1rem"><i class="fas fa-shield" style="color:var(--cyan)"></i> Cloudflare Turnstile</h3>
            <label style="display:flex;gap:8px;align-items:center;font-size:.85rem;margin-bottom:.6rem"><input type="checkbox" id="tsEnabled" <?= $tsEnabled?'checked':'' ?>> Aktif</label>
            <input id="tsSite" placeholder="Site Key" value="<?= htmlspecialchars($tsSite) ?>" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:.5rem .7rem;margin-bottom:.5rem">
            <input id="tsSecret" placeholder="<?= $tsHas ? '•••••• ' . $secPh : 'Secret Key' ?>" type="password" autocomplete="new-password" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:.5rem .7rem;margin-bottom:.7rem">
            <button onclick="saveTurnstile()" class="btn" style="width:100%;justify-content:center"><i class="fas fa-floppy-disk"></i> Kaydet</button>
            <button onclick="testTurnstile()" class="btn" style="width:100%;justify-content:center;margin-top:.4rem;background:rgba(0,212,255,.08)"><i class="fas fa-vial"></i> Test</button>
            <div id="tsMsg" style="font-size:.78rem;margin-top:.4rem;display:none"></div><div id="tsPreview" style="margin-top:.6rem"></div>
        </div>
    </div>
    <!-- Google Modal -->
    <div id="int-google" class="int-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center;padding:1.5rem" onclick="if(event.target===this)closeIntModal('google')">
        <div style="background:#0d1b3e;border:1px solid rgba(0,212,255,.25);border-radius:18px;padding:1.6rem;max-width:480px;width:92%;position:relative">
            <button onclick="closeIntModal('google')" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.07);border:1px solid var(--border);border-radius:8px;color:var(--text);width:30px;height:30px;cursor:pointer"><i class="fas fa-xmark"></i></button>
            <h3 style="font-weight:800;color:#fff;margin-bottom:1rem"><i class="fab fa-google" style="color:#ea4335"></i> Google OAuth</h3>
            <label style="display:flex;gap:8px;align-items:center;font-size:.85rem;margin-bottom:.5rem"><input type="checkbox" id="goEnabled" <?= $goEnabled?'checked':'' ?>> Aktif</label>
            <input id="goId" placeholder="Client ID" value="<?= htmlspecialchars($goId) ?>" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:.5rem .7rem;margin-bottom:.4rem">
            <input id="goSec" placeholder="<?= $goHas ? '•••••• ' . $secPh : 'Client Secret' ?>" type="password" autocomplete="new-password" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:.5rem .7rem;margin-bottom:.4rem">
            <div style="font-size:.7rem;color:var(--muted);margin-bottom:.6rem">Redirect: <code><?= htmlspecialchars(SITE_URL) ?>/api/oauth?provider=google&action=callback</code></div>
            <button onclick="saveOAuth('google')" class="btn" style="width:100%;justify-content:center"><i class="fas fa-floppy-disk"></i> Kaydet</button>
            <a href="/api/oauth?provider=google&action=login" target="_blank" class="btn" style="width:100%;justify-content:center;margin-top:.4rem;text-decoration:none;display:inline-flex"><i class="fas fa-link"></i> Hesabına Bağla</a>
        </div>
    </div>
    <!-- Discord Modal -->
    <div id="int-discord" class="int-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center;padding:1.5rem" onclick="if(event.target===this)closeIntModal('discord')">
        <div style="background:#0d1b3e;border:1px solid rgba(0,212,255,.25);border-radius:18px;padding:1.6rem;max-width:480px;width:92%;position:relative">
            <button onclick="closeIntModal('discord')" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.07);border:1px solid var(--border);border-radius:8px;color:var(--text);width:30px;height:30px;cursor:pointer"><i class="fas fa-xmark"></i></button>
            <h3 style="font-weight:800;color:#fff;margin-bottom:1rem"><i class="fab fa-discord" style="color:#5865f2"></i> Discord OAuth</h3>
            <label style="display:flex;gap:8px;align-items:center;font-size:.85rem;margin-bottom:.5rem"><input type="checkbox" id="dcEnabled" <?= $dcEnabled?'checked':'' ?>> Aktif</label>
            <input id="dcId" placeholder="Client ID" value="<?= htmlspecialchars($dcId) ?>" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:.5rem .7rem;margin-bottom:.4rem">
            <input id="dcSec" placeholder="<?= $dcHas ? '•••••• ' . $secPh : 'Client Secret' ?>" type="password" autocomplete="new-password" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:.5rem .7rem;margin-bottom:.4rem">
            <div style="font-size:.7rem;color:var(--muted);margin-bottom:.6rem">Redirect: <code><?= htmlspecialchars(SITE_URL) ?>/api/oauth?provider=discord&action=callback</code></div>
            <button onclick="saveOAuth('discord')" class="btn" style="width:100%;justify-content:center"><i class="fas fa-floppy-disk"></i> Kaydet</button>
            <a href="/api/oauth?provider=discord&action=login" target="_blank" class="btn" style="width:100%;justify-content:center;margin-top:.4rem;text-decoration:none;display:inline-flex"><i class="fas fa-link"></i> Hesabına Bağla</a>
        </div>
    </div>
    <!-- GitHub Modal -->
    <div id="int-github" class="int-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center;padding:1.5rem" onclick="if(event.target===this)closeIntModal('github')">
        <div style="background:#0d1b3e;border:1px solid rgba(0,212,255,.25);border-radius:18px;padding:1.6rem;max-width:480px;width:92%;position:relative">
            <button onclick="closeIntModal('github')" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.07);border:1px solid var(--border);border-radius:8px;color:var(--text);width:30px;height:30px;cursor:pointer"><i class="fas fa-xmark"></i></button>
            <h3 style="font-weight:800;color:#fff;margin-bottom:1rem"><i class="fab fa-github" style="color:#fff"></i> GitHub OAuth</h3>
            <label style="display:flex;gap:8px;align-items:center;font-size:.85rem;margin-bottom:.5rem"><input type="checkbox" id="ghEnabled" <?= $ghEnabled?'checked':'' ?>> Aktif</label>
            <input id="ghId" placeholder="Client ID" value="<?= htmlspecialchars($ghId) ?>" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:.5rem .7rem;margin-bottom:.4rem">
            <input id="ghSec" placeholder="<?= $ghHas ? '•••••• ' . $secPh : 'Client Secret' ?>" type="password" autocomplete="new-password" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:.5rem .7rem;margin-bottom:.4rem">
            <div style="font-size:.7rem;color:var(--muted);margin-bottom:.6rem">Redirect: <code><?= htmlspecialchars(SITE_URL) ?>/api/oauth?provider=github&action=callback</code></div>
            <button onclick="saveOAuth('github')" class="btn" style="width:100%;justify-content:center"><i class="fas fa-floppy-disk"></i> Kaydet</button>
            <a href="/api/oauth?provider=github&action=login" target="_blank" class="btn" style="width:100%;justify-content:center;margin-top:.4rem;text-decoration:none;display:inline-flex"><i class="fas fa-link"></i> Hesabına Bağla</a>
        </div>
    </div>
    <!-- Microsoft Modal -->
    <div id="int-microsoft" class="int-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center;padding:1.5rem" onclick="if(event.target===this)closeIntModal('microsoft')">
        <div style="background:#0d1b3e;border:1px solid rgba(0,212,255,.25);border-radius:18px;padding:1.6rem;max-width:480px;width:92%;position:relative">
            <button onclick="closeIntModal('microsoft')" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.07);border:1px solid var(--border);border-radius:8px;color:var(--text);width:30px;height:30px;cursor:pointer"><i class="fas fa-xmark"></i></button>
            <h3 style="font-weight:800;color:#fff;margin-bottom:1rem"><i class="fab fa-microsoft" style="color:#2b88d8"></i> Microsoft OAuth</h3>
            <label style="display:flex;gap:8px;align-items:center;font-size:.85rem;margin-bottom:.5rem"><input type="checkbox" id="msEnabled" <?= $msEnabled?'checked':'' ?>> Aktif</label>
            <input id="msId" placeholder="Client (Application) ID" value="<?= htmlspecialchars($msId) ?>" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:.5rem .7rem;margin-bottom:.4rem">
            <input id="msSec" placeholder="<?= $msHas ? '•••••• ' . $secPh : 'Client Secret' ?>" type="password" autocomplete="new-password" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:.5rem .7rem;margin-bottom:.4rem">
            <div style="font-size:.7rem;color:var(--muted);margin-bottom:.6rem">Redirect: <code><?= htmlspecialchars(SITE_URL) ?>/api/oauth?provider=microsoft&action=callback</code></div>
            <button onclick="saveOAuth('microsoft')" class="btn" style="width:100%;justify-content:center"><i class="fas fa-floppy-disk"></i> Kaydet</button>
            <a href="/api/oauth?provider=microsoft&action=login" target="_blank" class="btn" style="width:100%;justify-content:center;margin-top:.4rem;text-decoration:none;display:inline-flex"><i class="fas fa-link"></i> Hesabına Bağla</a>
        </div>
    </div>
    <!-- Whop Modal -->
    <div id="int-whop" class="int-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center;padding:1.5rem" onclick="if(event.target===this)closeIntModal('whop')">
        <div style="background:#0d1b3e;border:1px solid rgba(0,212,255,.25);border-radius:18px;padding:1.6rem;max-width:520px;width:94%;position:relative;max-height:88vh;overflow-y:auto">
            <button onclick="closeIntModal('whop')" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.07);border:1px solid var(--border);border-radius:8px;color:var(--text);width:30px;height:30px;cursor:pointer"><i class="fas fa-xmark"></i></button>
            <h3 style="font-weight:800;color:#fff;margin-bottom:.4rem"><i class="fas fa-store" style="color:#ff7a1a"></i> Whop</h3>
            <p style="font-size:.76rem;color:var(--muted);margin-bottom:1rem">Whop Dashboard → Developer: <strong style="color:var(--cyan)">API anahtarı</strong> oluştur, webhook bildirimleri için <strong style="color:var(--cyan)">Create Webhook</strong> ile bu sitenin <code>/api/whop-webhook</code> adresini kaydet ve imza anahtarını buraya yapıştır.</p>
            <div style="font-size:.78rem;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.5rem">Whop ile Ödeme Alma</div>
            <label style="display:flex;gap:8px;align-items:center;font-size:.85rem;margin-bottom:.5rem"><input type="checkbox" id="whPayEnabled" <?= $whPay?'checked':'' ?>> Aktif (mağazada Whop ile satış)</label>
            <input id="whCur" placeholder="Para birimi (usd, eur, try...)" value="<?= htmlspecialchars($whCur !== '' ? $whCur : 'usd') ?>" maxlength="3" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:.5rem .7rem;margin-bottom:.4rem">
            <div style="font-size:.78rem;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin:.8rem 0 .5rem">Whop API (ödeme/satış doğrulama)</div>
            <input id="whApi" placeholder="API Key (boş bırakılırsa değişmez)" type="password" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:.5rem .7rem;margin-bottom:.4rem">
            <div style="font-size:.7rem;color:var(--muted);margin:-.2rem 0 .4rem"><?= $whApiHas?'<span style="color:#4ade80">● API anahtarı kayıtlı</span>':'<span style="color:var(--muted)">○ API anahtarı girilmedi</span>' ?></div>
            <input id="whBiz" placeholder="Company ID (biz_...)" value="<?= htmlspecialchars($whBiz) ?>" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:.5rem .7rem;margin-bottom:.4rem">
            <input id="whHook" placeholder="Webhook Secret — whsec_... / ws_... (boş bırakılırsa değişmez)" type="password" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:.5rem .7rem;margin-bottom:.4rem">
            <div style="font-size:.7rem;color:var(--muted);margin:-.2rem 0 .4rem"><?= $whHookHas?'<span style="color:#4ade80">● Webhook anahtarı kayıtlı</span>':'<span style="color:var(--muted)">○ Webhook anahtarı girilmedi</span>' ?> — <span style="color:var(--muted)">payment.succeeded vb. olaylar için</span></div>
            <label style="display:flex;gap:8px;align-items:center;font-size:.85rem;margin-bottom:.5rem"><input type="checkbox" id="whSandbox" <?= $whSandbox?'checked':'' ?>> Sandbox modu (sandbox-api.whop.com)</label>
            <div style="font-size:.7rem;color:var(--muted);margin-bottom:.6rem">Webhook URL: <code><?= htmlspecialchars(SITE_URL) ?>/api/whop-webhook</code> — olaylar: <code>payment.succeeded</code>, <code>payment.failed</code></div>
            <button onclick="saveWhop()" class="btn" style="width:100%;justify-content:center"><i class="fas fa-floppy-disk"></i> Kaydet</button>
            <button onclick="testWhop()" class="btn" style="width:100%;justify-content:center;margin-top:.4rem;background:rgba(0,212,255,.08)"><i class="fas fa-vial"></i> API Bağlantısını Test Et</button>
            <div id="whMsg" style="font-size:.78rem;margin-top:.4rem;display:none"></div>
        </div>
    </div>
    <!-- Google Indexing Modal -->
    <div id="int-indexing" class="int-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center;padding:1.5rem" onclick="if(event.target===this)closeIntModal('indexing')">
        <div style="background:#0d1b3e;border:1px solid rgba(0,212,255,.25);border-radius:18px;padding:1.6rem;max-width:520px;width:94%;position:relative">
            <button onclick="closeIntModal('indexing')" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.07);border:1px solid var(--border);border-radius:8px;color:var(--text);width:30px;height:30px;cursor:pointer"><i class="fas fa-xmark"></i></button>
            <h3 style="font-weight:800;color:#fff;margin-bottom:1rem"><i class="fab fa-google" style="color:#8ab4f8"></i> Google Indexing API</h3>
            <p style="font-size:.78rem;color:var(--muted);margin-bottom:.8rem">Blog yazısı yayınlanınca/silince Google otomatik bilgilendirilir. Servis hesabı JSON anahtarını yapıştırın (Google Cloud → IAM → anahtar oluştur).</p>
            <label style="display:flex;gap:8px;align-items:center;font-size:.85rem;margin-bottom:.5rem"><input type="checkbox" id="ixEnabled" <?= $ixEnabled?'checked':'' ?>> Aktif</label>
            <textarea id="ixJson" placeholder='{"type":"service_account","project_id":"...","private_key":"-----BEGIN PRIVATE KEY-----...' rows="6" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:.5rem .7rem;margin-bottom:.4rem;font-family:monospace;font-size:.75rem"><?= $ixHasKey?'•••••• kayıtlı anahtar var (değiştirmek için yeniden yapıştırın)':'' ?></textarea>
            <button onclick="saveIndexing()" class="btn" style="width:100%;justify-content:center"><i class="fas fa-floppy-disk"></i> Kaydet</button>
            <button onclick="testIndexing()" class="btn" style="width:100%;justify-content:center;margin-top:.4rem;background:rgba(0,212,255,.08)"><i class="fas fa-vial"></i> Bağlantıyı Test Et</button>
            <div id="ixMsg" style="font-size:.78rem;margin-top:.4rem;display:none"></div>
        </div>
    </div>
    <script>
        function openIntModal(id){document.getElementById('int-'+id).style.display='flex';}
        function closeIntModal(id){document.getElementById('int-'+id).style.display='none';}
        async function saveTurnstile(){
            const fd=new FormData(); fd.append('action','save_turnstile'); fd.append('enabled',document.getElementById('tsEnabled').checked?'1':'0'); fd.append('site_key',document.getElementById('tsSite').value.trim());
            const tsSec=document.getElementById('tsSecret').value.trim(); if(tsSec) fd.append('secret',tsSec);
            const r=await fetch('/api/settings',{method:'POST',headers:{'X-CSRF-Token':VX_CSRF},body:fd}); const d=await r.json(); const m=document.getElementById('tsMsg'); m.style.display='block'; m.style.color=d.ok?'#4ade80':'#f87171'; m.textContent=d.msg||(d.ok?'Kaydedildi':'Hata'); setTimeout(()=>m.style.display='none',3000); if(d.ok) setTimeout(()=>location.reload(),800);
        }
        async function testTurnstile(){
            const k=document.getElementById('tsSite').value.trim(); if(!k){alert('Site Key gir');return;}
            const prev=document.getElementById('tsPreview'); prev.innerHTML='<div class="cf-turnstile" data-sitekey="'+k+'" data-theme="dark"></div>'; if(!document.querySelector('script[src*="turnstile"]')){const s=document.createElement('script'); s.src='https://challenges.cloudflare.com/turnstile/v0/api.js'; s.async=true; s.defer=true; document.head.appendChild(s);}
        }
        async function saveMulti(){
            const fd=new FormData(); fd.append('action','save_multi');
            fd.append('enabled',document.getElementById('muEnabled').checked?'1':'0');
            fd.append('autoban',document.getElementById('muAutoban').checked?'1':'0');
            fd.append('extra_domains',document.getElementById('muExtra').value);
            const r=await fetch('/api/settings',{method:'POST',headers:{'X-CSRF-Token':VX_CSRF},body:fd}); const d=await r.json(); const m=document.getElementById('muMsg'); m.style.display='inline'; m.style.color=d.ok?'#4ade80':'#f87171'; m.textContent=d.msg||(d.ok?'Kaydedildi':'Hata'); setTimeout(()=>m.style.display='none',3000);
        }
        async function saveOAuth(p){
            const fd=new FormData(); fd.append('action','save_oauth'); fd.append('provider',p);
            const secVal=(id)=>document.getElementById(id).value.trim();
            if(p==='google'){ fd.append('enabled',document.getElementById('goEnabled').checked?'1':'0'); fd.append('client_id',document.getElementById('goId').value.trim()); if(secVal('goSec')) fd.append('client_secret',secVal('goSec')); }
            else if(p==='github'){ fd.append('enabled',document.getElementById('ghEnabled').checked?'1':'0'); fd.append('client_id',document.getElementById('ghId').value.trim()); if(secVal('ghSec')) fd.append('client_secret',secVal('ghSec')); }
            else if(p==='microsoft'){ fd.append('enabled',document.getElementById('msEnabled').checked?'1':'0'); fd.append('client_id',document.getElementById('msId').value.trim()); if(secVal('msSec')) fd.append('client_secret',secVal('msSec')); }
            else { fd.append('enabled',document.getElementById('dcEnabled').checked?'1':'0'); fd.append('client_id',document.getElementById('dcId').value.trim()); if(secVal('dcSec')) fd.append('client_secret',secVal('dcSec')); }
            const r=await fetch('/api/settings',{method:'POST',headers:{'X-CSRF-Token':VX_CSRF},body:fd}); const d=await r.json(); alert(d.msg||(d.ok?'Kaydedildi':'Hata')); if(d.ok) setTimeout(()=>location.reload(),800);
        }
        async function saveWhop(){
            const fd=new FormData(); fd.append('action','save_whop');
            fd.append('payments_enabled',document.getElementById('whPayEnabled').checked?'1':'0');
            fd.append('currency',document.getElementById('whCur').value.trim());
            fd.append('api_key',document.getElementById('whApi').value.trim());
            fd.append('company_id',document.getElementById('whBiz').value.trim());
            fd.append('webhook_secret',document.getElementById('whHook').value.trim());
            fd.append('sandbox',document.getElementById('whSandbox').checked?'1':'0');
            const r=await fetch('/api/settings',{method:'POST',headers:{'X-CSRF-Token':VX_CSRF},body:fd}); const d=await r.json(); const m=document.getElementById('whMsg'); m.style.display='block'; m.style.color=d.ok?'#4ade80':'#f87171'; m.textContent=d.msg||(d.ok?'Kaydedildi':'Hata'); if(d.ok) setTimeout(()=>location.reload(),1200);
        }
        async function testWhop(){
            const m=document.getElementById('whMsg'); m.style.display='block'; m.style.color='#8ab4f8'; m.textContent='Test ediliyor...';
            const fd=new FormData(); fd.append('action','test_whop');
            const r=await fetch('/api/settings',{method:'POST',headers:{'X-CSRF-Token':VX_CSRF},body:fd}); const d=await r.json(); m.style.color=d.ok?'#4ade80':'#f87171'; m.textContent=d.msg||(d.ok?'Bağlantı OK':'Hata');
        }
        async function saveIndexing(){
            const fd=new FormData(); fd.append('action','save_indexing'); fd.append('enabled',document.getElementById('ixEnabled').checked?'1':'0'); fd.append('service_json',document.getElementById('ixJson').value);
            const r=await fetch('/api/settings',{method:'POST',headers:{'X-CSRF-Token':VX_CSRF},body:fd}); const d=await r.json(); const m=document.getElementById('ixMsg'); m.style.display='block'; m.style.color=d.ok?'#4ade80':'#f87171'; m.textContent=d.msg||(d.ok?'Kaydedildi':'Hata'); if(d.ok) setTimeout(()=>location.reload(),1200);
        }
        async function testIndexing(){
            const fd=new FormData(); fd.append('action','test_indexing');
            const m=document.getElementById('ixMsg'); m.style.display='block'; m.style.color='#8ab4f8'; m.textContent='Test ediliyor...';
            const r=await fetch('/api/settings',{method:'POST',headers:{'X-CSRF-Token':VX_CSRF},body:fd}); const d=await r.json(); m.style.color=d.ok?'#4ade80':'#f87171'; m.textContent=d.msg||(d.ok?'Bağlantı OK':'Hata');
        }
        </script>
</div>
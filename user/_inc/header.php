<?php
require_once __DIR__ . '/../../core/db.php';
boot_session();
require_login();

$user = current_user();
log_pageview('panel');

$panelPage = $activePage ?? 'dashboard';

$myRole = user_role();
$canAdmin = is_admin() || has_any_permission();

$unreadCount = 0;
$pdo = db();
if ($pdo) {
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $st->execute([(int)$user['id']]);
        $unreadCount = (int)$st->fetchColumn();
    } catch (Exception $e) { $unreadCount = 0; }
}
$myPlan = user_effective_plan((int)$user['id']);
$isFreePlan = ($myPlan['plan'] ?? 'free') === 'free';

// Aktivasyon kontrolü: hesabı aktif olmayan kullanıcı panelde yalnızca
// Hesap Ayarları sayfasını görebilir (kurumsal bilgilendirme orada).
try {
    if ($pdo && !empty($user['id']) && !is_admin()) {
        // Banlı hesap panele giremez (multi ya da manuel).
        try {
            $bnSt = $pdo->prepare("SELECT ban_reason FROM users WHERE id = ? LIMIT 1");
            $bnSt->execute([(int)$user['id']]);
            $bnWhy = (string)$bnSt->fetchColumn();
            if ($bnWhy !== '') {
                unset($_SESSION['user']);
                header('Location: /banned?why=' . urlencode($bnWhy));
                exit;
            }
        } catch (Throwable $e0) {}
        if (setting_get('activation_enabled', '1') === '1'
            && !user_is_active($pdo, (int)$user['id'])
            && basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== 'account.php') {
            header('Location: /dashboard/account?inactive=1');
            exit;
        }
    }
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Dashboard — Vexaris</title>
    <link rel="icon" type="image/png" href="/assets/images/vexaris-logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --cyan:#4f8fc0;--blue:#4f8fc0;
            --bg:#0b0d10;--bg2:#0e1114;
            --card:#15191e;
            --border:#262c33;
            --border-c:rgba(79,143,192,.45);
            --muted:#9aa3ac;
            --sidebar-w:264px;
            --gradient:linear-gradient(135deg,#4f8fc0,#3a76a8);
            --fd:"Bricolage Grotesque",Manrope,sans-serif;
            --fb:"Manrope",sans-serif;
        }
        body{font-family:var(--fb);background:var(--bg);color:#e9e7e2;min-height:100vh;display:flex;-webkit-font-smoothing:antialiased}
        body::before{content:'';position:fixed;inset:0;background-image:radial-gradient(rgba(233,231,226,.05) 1px,transparent 1px);background-size:26px 26px;pointer-events:none;z-index:0}
        .sidebar{width:var(--sidebar-w);min-height:100vh;background:rgba(14,17,20,.97);backdrop-filter:blur(8px);border-right:1px solid var(--border);display:flex;flex-direction:column;flex-shrink:0;position:fixed;top:0;left:0;bottom:0;z-index:100;transition:transform .35s cubic-bezier(.22,1,.36,1)}
        .sidebar__logo{padding:1.4rem 1.5rem 1.1rem;display:flex;align-items:center;gap:10px;text-decoration:none;border-bottom:1px solid var(--border)}
        .sidebar__logo img{height:30px;transition:transform .3s}
        .sidebar__logo:hover img{transform:scale(1.05)}
        .sidebar__logo span{font-family:var(--fd);font-size:1.2rem;font-weight:800;color:#e9e7e2;letter-spacing:-.02em}
        .sidebar__user{padding:1.2rem 1.5rem;border-bottom:1px solid var(--border)}
        .sidebar__user-hello{font-size:.75rem;color:var(--muted);margin-bottom:.3rem}
        .sidebar__user-name{font-size:1rem;font-weight:700;font-family:var(--fd)}
        .sidebar__user-badge{display:inline-flex;align-items:center;gap:5px;margin-top:.4rem;font-size:.7rem;font-weight:700;background:rgba(79,143,192,.1);border:1px solid rgba(79,143,192,.35);color:#7fb3d9;border-radius:50px;padding:3px 10px}
        .sidebar__nav{flex:1;padding:1rem 0;overflow-y:auto}
        .sidebar__section{padding:.6rem 1.5rem .3rem;font-size:.68rem;font-weight:800;color:#5b636b;text-transform:uppercase;letter-spacing:.1em}
        .sidebar__link{display:flex;align-items:center;gap:12px;padding:.72rem 1.5rem;color:#9aa3ac;text-decoration:none;font-size:.9rem;font-weight:600;transition:color .25s,background .25s,padding-left .25s;position:relative}
        .sidebar__link:hover{color:#fff;background:rgba(255,255,255,.04);padding-left:1.7rem}
        .sidebar__link.active{color:#fff;background:rgba(79,143,192,.1)}
        .sidebar__link.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--ember,#4f8fc0);border-radius:0 3px 3px 0;box-shadow:0 0 12px rgba(79,143,192,.7)}
        .sidebar__link i{width:18px;text-align:center;font-size:.95rem;transition:transform .25s}
        .sidebar__link:hover i{transform:scale(1.12)}
        .sidebar__link .badge-soon{margin-left:auto;font-size:.65rem;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:50px;padding:2px 8px;color:var(--muted)}
        .sidebar__count{margin-left:auto;min-width:20px;text-align:center;font-size:.68rem;font-weight:800;background:rgba(224,49,108,.15);border:1px solid rgba(224,49,108,.4);color:#f0608a;border-radius:50px;padding:2px 7px;animation:pulseBadge 2s ease-in-out infinite}
        @keyframes pulseBadge{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}
        .sidebar__bottom{padding:1rem 1.5rem;border-top:1px solid var(--border)}
        .sidebar__logout{display:flex;align-items:center;gap:10px;color:rgba(255,112,112,.75);text-decoration:none;font-size:.88rem;font-weight:600;transition:color .2s,gap .25s;background:none;border:none;cursor:pointer;padding:0;font-family:var(--fb);width:100%}
        .sidebar__logout:hover{color:#ff7070;gap:14px}
        .main{margin-left:var(--sidebar-w);flex:1;padding:2.5rem;min-height:100vh;position:relative;z-index:1;animation:pageIn .5s cubic-bezier(.22,1,.36,1)}
        @keyframes pageIn{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
        .main__header{margin-bottom:2rem}
        .main__header h1{font-family:var(--fd);font-size:1.7rem;font-weight:800;letter-spacing:-.02em}
        .main__header p{color:var(--muted);font-size:.92rem;margin-top:.35rem}
        .stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.2rem;margin-bottom:2rem}
        .stat-card{background:linear-gradient(180deg,#161b21 0%,#12161a 100%);border:1px solid var(--border);border-radius:14px;padding:1.5rem;transition:transform .35s cubic-bezier(.22,1,.36,1),border-color .35s,box-shadow .35s}
        .stat-card:hover{transform:translateY(-4px);border-color:rgba(79,143,192,.45);box-shadow:0 20px 40px -20px rgba(0,0,0,.8)}
        .stat-card__icon{width:44px;height:44px;background:rgba(79,143,192,.1);border:1px solid rgba(79,143,192,.25);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#7fb3d9;margin-bottom:1rem;transition:transform .3s,box-shadow .3s}
        .stat-card:hover .stat-card__icon{transform:translateY(-2px);box-shadow:0 0 22px -6px rgba(79,143,192,.6)}
        .stat-card__val{font-family:var(--fd);font-size:1.8rem;font-weight:800;margin-bottom:.2rem}
        .stat-card__label{font-size:.82rem;color:var(--muted)}
        .panel-card{background:linear-gradient(180deg,#161b21 0%,#12161a 100%);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:2rem;animation:pageIn .5s cubic-bezier(.22,1,.36,1)}
        .panel-card__head{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.1rem 1.5rem;border-bottom:1px solid var(--border);flex-wrap:wrap}
        .panel-card__title{font-size:.95rem;font-weight:700;font-family:var(--fd)}
        .panel-card__title i{color:var(--cyan);margin-right:8px}
        .panel-card__body{padding:1.25rem 1.5rem}
        table{width:100%;border-collapse:collapse}
        th{padding:.7rem 1rem;text-align:left;font-size:.68rem;font-weight:700;color:#5b636b;text-transform:uppercase;letter-spacing:.1em;border-bottom:1px solid var(--border)}
        td{padding:.85rem 1rem;font-size:.85rem;border-bottom:1px solid rgba(255,255,255,.05);transition:background .2s}
        tr:last-child td{border-bottom:none}
        tbody tr{transition:background .2s}
        tbody tr:hover td{background:rgba(79,143,192,.05)}
        .badge{display:inline-flex;align-items:center;gap:5px;font-size:.7rem;font-weight:700;border-radius:50px;padding:3px 10px;white-space:nowrap}
        .badge-pending{background:rgba(224,177,60,.12);color:#e0b13c;border:1px solid rgba(224,177,60,.3)}
        .badge-approved,.badge-active{background:rgba(116,192,145,.12);color:#74c091;border:1px solid rgba(116,192,145,.3)}
        .badge-rejected,.badge-rev,.badge-susp{background:rgba(255,112,112,.1);color:#ff8080;border:1px solid rgba(255,112,112,.3)}
        .badge-unread{background:rgba(79,143,192,.12);color:#7fb3d9;border:1px solid rgba(79,143,192,.35)}
        .badge-read{background:rgba(255,255,255,.05);color:var(--muted);border:1px solid var(--border)}
        .btn-panel{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 15px;border-radius:10px;font-size:.8rem;font-weight:700;cursor:pointer;font-family:var(--fb);border:1px solid;text-decoration:none;transition:transform .25s,background .25s,box-shadow .25s;white-space:nowrap}
        .btn-panel:hover{transform:translateY(-2px)}
        .btn-cyan{background:rgba(79,143,192,.1);border-color:rgba(79,143,192,.35);color:#7fb3d9}
        .btn-cyan:hover{background:rgba(79,143,192,.2);box-shadow:0 8px 22px -10px rgba(79,143,192,.5)}
        .btn-green{background:rgba(116,192,145,.1);border-color:rgba(116,192,145,.35);color:#74c091}
        .btn-green:hover{background:rgba(116,192,145,.2)}
        .btn-red{background:rgba(255,112,112,.08);border-color:rgba(255,112,112,.3);color:#ff8080}
        .btn-red:hover{background:rgba(255,112,112,.18)}
        .empty{text-align:center;color:var(--muted);padding:2.5rem 1rem;font-size:.88rem}
        .empty i{font-size:1.6rem;display:block;margin-bottom:.6rem;color:#39424c}
        .ticket-wrap{display:grid;grid-template-columns:300px 1fr;gap:1.2rem;min-height:0}
        .ticket-side{background:linear-gradient(180deg,#161b21 0%,#12161a 100%);border:1px solid var(--border);border-radius:14px;display:flex;flex-direction:column;overflow:hidden;min-height:500px;max-height:640px;min-width:0}
        .ticket-side__head{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.2rem;border-bottom:1px solid var(--border)}
        .ticket-side__title{font-size:.95rem;font-weight:800;font-family:var(--fd);display:flex;align-items:center;gap:8px}
        .ticket-side__title i{color:var(--cyan)}
        .ticket-side__count{font-size:.72rem;font-weight:800;background:rgba(79,143,192,.12);border:1px solid rgba(79,143,192,.35);color:#7fb3d9;border-radius:50px;padding:2px 9px}
        .ticket-list{flex:1;overflow-y:auto;padding:.5rem;min-height:0;min-width:0}
        .ticket-item{display:flex;gap:.6rem;padding:.55rem .7rem;border-radius:10px;cursor:pointer;transition:background .2s,border-color .2s,transform .2s;align-items:flex-start;border:1px solid transparent}
        .ticket-item:hover{background:rgba(255,255,255,.04);transform:translateX(2px)}
        .ticket-item.active{background:rgba(79,143,192,.1);border-color:rgba(79,143,192,.4)}
        .ticket-item.unread{background:rgba(79,143,192,.05)}
        .ticket-avatar{width:34px;height:34px;background:linear-gradient(135deg,#4f8fc0,#3a76a8);border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.85rem;color:#fff;flex-shrink:0}
        .ticket-item__body{flex:1;min-width:0}
        .ticket-item__top{display:flex;align-items:center;justify-content:space-between;gap:6px}
        .ticket-item__name{font-size:.8rem;font-weight:700;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .ticket-item__time{font-size:.64rem;color:var(--muted);flex-shrink:0;font-family:var(--fm,monospace)}
        .ticket-item__subj{font-size:.74rem;font-weight:600;color:#7fb3d9;margin-top:1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .ticket-item__bottom{display:flex;align-items:center;gap:5px;margin-top:3px}
        .ticket-item__last{font-size:.68rem;color:var(--muted);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .ticket-badge{min-width:20px;height:20px;padding:0 6px;background:rgba(224,49,108,.15);border:1px solid rgba(224,49,108,.4);color:#f0608a;font-size:.66rem;font-weight:800;border-radius:50px;display:flex;align-items:center;justify-content:center}
        .ticket-empty{text-align:center;color:var(--muted);padding:2.5rem 1rem;font-size:.85rem}
        .ticket-empty i{font-size:1.7rem;display:block;margin-bottom:.6rem;color:#39424c}
        .ticket-chat{background:linear-gradient(180deg,#161b21 0%,#12161a 100%);border:1px solid var(--border);border-radius:14px;display:flex;flex-direction:column;overflow:hidden;height:calc(100vh - 150px);max-height:640px;min-height:420px;min-width:0}
        #chatPanel{display:flex;flex-direction:column;flex:1;min-height:0;min-width:0;overflow:hidden}
        .ticket-chat__empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--muted);gap:.6rem}
        .ticket-chat__empty i{font-size:2.4rem;color:rgba(79,143,192,.35)}
        .ticket-chat__empty p{font-size:.9rem}
        .ticket-chat__head{display:flex;align-items:center;justify-content:space-between;gap:.8rem;padding:.85rem 1.1rem;border-bottom:1px solid var(--border);flex-wrap:wrap;flex-shrink:0}
        .ticket-chat__user{display:flex;align-items:center;gap:.65rem;min-width:0}
        .ticket-chat__name{font-size:.85rem;font-weight:800;font-family:var(--fd)}
        .ticket-chat__sub{margin-top:2px;font-size:.7rem}
        .ticket-msgs{flex:1;overflow-y:auto;overflow-x:hidden;padding:1rem;display:flex;flex-direction:column;gap:.45rem;background:rgba(0,0,0,.15);min-width:0;min-height:0;scroll-behavior:smooth}
        .msg-row{display:flex;animation:msgIn .25s ease;width:100%;min-width:0;flex-shrink:0}
        @keyframes msgIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
        .msg-row.user{justify-content:flex-start}
        .msg-row.admin{justify-content:flex-end}
        .msg-bubble{max-width:72%;min-width:0;padding:.55rem .8rem;border-radius:14px;font-size:.8rem;line-height:1.5;overflow-wrap:anywhere;word-break:break-word;box-shadow:0 6px 16px -10px rgba(0,0,0,.6)}
        .msg-row.user .msg-bubble{background:#1a1f25;border:1px solid var(--border);color:#e9e7e2;border-bottom-left-radius:5px}
        .msg-row.admin .msg-bubble{background:linear-gradient(135deg,#4f8fc0,#3a76a8);color:#fff;border-bottom-right-radius:5px}
        .msg-text{white-space:pre-line;overflow-wrap:anywhere;word-break:break-word;min-width:0;font-size:.8rem}
        .msg-img{display:block;max-width:230px;max-height:170px;width:auto;height:auto;border-radius:10px;margin-bottom:.45rem;cursor:zoom-in;border:1px solid rgba(255,255,255,.12);object-fit:cover}
        .msg-time{font-size:.62rem;color:rgba(255,255,255,.55);margin-top:.3rem;display:flex;align-items:center;gap:4px}
        .msg-row.admin .msg-time{color:var(--muted)}
        .msg-row.user .msg-time{justify-content:flex-end}
        .msg-row.admin .msg-time{justify-content:flex-start}
        .msg-time i{color:#7fb3d9;font-size:.6rem}
        .ticket-send{display:flex;align-items:flex-end;gap:.6rem;padding:1rem 1.4rem;border-top:1px solid var(--border);background:rgba(0,0,0,.2);flex-shrink:0}
        .ticket-send textarea{flex:1;background:#101317;border:1px solid var(--border);border-radius:12px;color:#e9e7e2;font-family:var(--fb);font-size:.88rem;padding:.7rem 1rem;outline:none;resize:none;max-height:120px;transition:border-color .25s,box-shadow .25s}
        .ticket-send textarea:focus{border-color:rgba(79,143,192,.6);box-shadow:0 0 0 3px rgba(79,143,192,.12)}
        .ticket-send textarea::placeholder{color:#5b636b}
        .ticket-send__btn{width:42px;height:42px;border-radius:12px;background:#4f8fc0;border:none;color:#16100b;font-size:1rem;cursor:pointer;flex-shrink:0;transition:transform .25s,background .25s;display:grid;place-items:center}
        .ticket-send__btn:hover{background:#7fb3d9;transform:translateY(-2px)}
        .ticket-new-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#4f8fc0;border:none;border-radius:10px;color:#16100b;font-size:.82rem;font-weight:800;cursor:pointer;font-family:var(--fb);text-decoration:none;transition:transform .25s,background .25s}
        .ticket-new-btn:hover{background:#7fb3d9;transform:translateY(-2px)}
        .modal-new{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:300;align-items:center;justify-content:center;padding:1rem}
        .modal-new.open{display:flex}
        .modal-new__box{background:linear-gradient(180deg,#161b21,#12161a);border:1px solid var(--border);border-radius:16px;width:100%;max-width:460px;padding:1.6rem;animation:modalIn .35s cubic-bezier(.22,1,.36,1);box-shadow:0 40px 80px -30px rgba(0,0,0,.9)}
        @keyframes modalIn{from{opacity:0;transform:translateY(16px) scale(.97)}to{opacity:1;transform:none}}
        .modal-new__title{font-size:1.05rem;font-weight:800;font-family:var(--fd);margin-bottom:1.2rem;display:flex;align-items:center;gap:8px}
        .modal-new__title i{color:var(--cyan)}
        .modal-new__close{margin-left:auto;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--muted);width:28px;height:28px;cursor:pointer;font-size:1rem;line-height:1;transition:color .2s}
        .modal-new__close:hover{color:#fff}
        .btn-ghost{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:11px;background:transparent;border:1px solid var(--border);border-radius:12px;color:var(--muted);font-size:.9rem;font-weight:700;cursor:pointer;font-family:var(--fb);transition:background .2s,border-color .2s,color .2s}
        .btn-ghost:hover{background:rgba(255,255,255,.04);border-color:var(--border-c);color:#fff}
        .modal-new label{display:block;font-size:.78rem;font-weight:600;color:var(--muted);margin:0 0 .35rem}
        .modal-new input,.modal-new textarea{width:100%;background:#101317;border:1px solid var(--border);border-radius:10px;color:#e9e7e2;font-family:var(--fb);font-size:.88rem;padding:.65rem .9rem;outline:none;margin-bottom:1rem;transition:border-color .25s}
        .modal-new input:focus,.modal-new textarea:focus{border-color:rgba(79,143,192,.6)}
        .modal-new textarea{min-height:110px;resize:vertical}
        .modal-new__actions{display:flex;gap:.6rem;margin-top:.6rem}
        @media(max-width:900px){.ticket-wrap{grid-template-columns:1fr}.ticket-side{min-height:300px}}
        .notif-row{display:flex;gap:1rem;padding:1rem 1.5rem;border-bottom:1px solid rgba(255,255,255,.05);align-items:flex-start;transition:background .25s}
        .notif-row:last-child{border-bottom:none}
        .notif-row:hover{background:rgba(255,255,255,.02)}
        .notif-ic{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(79,143,192,.1);color:#7fb3d9;border:1px solid rgba(79,143,192,.25)}
        .notif-row.unread{background:rgba(79,143,192,.05)}
        .notif-ic.approved{background:rgba(116,192,145,.1);color:#74c091;border-color:rgba(116,192,145,.3)}
        .notif-ic.rejected{background:rgba(255,112,112,.1);color:#ff8080;border-color:rgba(255,112,112,.3)}
        .notif-body{flex:1;min-width:0}
        .notif-title{font-size:.9rem;font-weight:700}
        .notif-msg{font-size:.82rem;color:var(--muted);line-height:1.55;margin-top:.25rem;white-space:pre-line}
        .notif-time{font-size:.72rem;color:#5b636b;margin-top:.35rem;font-family:var(--fm,monospace)}
        .mobile-toggle{display:none;position:fixed;top:1rem;left:1rem;z-index:200;background:rgba(14,17,20,.92);backdrop-filter:blur(8px);border:1px solid var(--border);border-radius:10px;padding:.65rem .8rem;cursor:pointer;color:#e9e7e2;font-size:1.1rem;transition:border-color .25s}
        .mobile-toggle:hover{border-color:rgba(79,143,192,.5)}
        .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(2px);z-index:99;opacity:0;transition:opacity .3s}
        @media(max-width:900px){
            .sidebar{transform:translateX(-100%);box-shadow:30px 0 60px -20px rgba(0,0,0,.7)}
            .sidebar.open{transform:translateX(0)}
            .sidebar-overlay.open{display:block;opacity:1}
            .main{margin-left:0;padding:5rem 1.25rem 2rem}
            .mobile-toggle{display:flex;align-items:center}
            .stats-grid{grid-template-columns:1fr 1fr}
        }
        @media(max-width:500px){.stats-grid{grid-template-columns:1fr}.main__header h1{font-size:1.4rem}}
        html,body{max-width:100%;overflow-x:hidden;}
        .panel-card{overflow-x:auto;}
        .panel-card table{min-width:520px;}
        .main__header h1{font-size:1.3rem;}
        @media(max-width:640px){.main{padding:4.5rem 1rem 2rem !important;} .stats-grid{gap:.8rem;} .stat-card{padding:1rem;} .ticket-msgs{padding:1rem;} .msg-bubble{max-width:88%;} .ticket-send{padding:.8rem;} .modal-new__box{padding:1.2rem;}}
        /* ── Mobil iyileştirme ── */
        @media(max-width:700px){
            .acc-grid{grid-template-columns:1fr !important;}
            .panel-card__body{padding:1rem !important;}
            .panel-card__head{padding:.9rem 1rem !important;flex-wrap:wrap;gap:.6rem;}
            .btn-panel{padding:11px 14px;}
        }
        @media(max-width:640px){
            .main input,.main textarea{font-size:16px !important;} /* iOS otomatik zoom engeli */
            .main__header h1{font-size:1.25rem;}
        }
    </style>
</head>
<body>

<button class="mobile-toggle" id="mobileToggle" aria-label="Menu"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
    <a href="/" class="sidebar__logo">
        <img src="/assets/images/vexaris-logo.png" alt="Vexaris">
        <span>Vexaris</span>
    </a>

    <div class="sidebar__user">
        <div class="sidebar__user-hello">Welcome,</div>
        <div class="sidebar__user-name"><?= htmlspecialchars($user['username']) ?></div>
        <div class="sidebar__user-badge"><i class="fas fa-circle-dot"></i> <?= htmlspecialchars(role_label($myRole, true)) ?></div>
    </div>

    <nav class="sidebar__nav">
        <div class="sidebar__section">General</div>
        <a href="/dashboard" class="sidebar__link <?= $panelPage === 'dashboard' ? 'active' : '' ?>">
            <i class="fas fa-house"></i> Dashboard
        </a>
        <a href="/dashboard/notifications" class="sidebar__link <?= $panelPage === 'notifications' ? 'active' : '' ?>">
            <i class="fas fa-bell"></i> Notifications
            <?php if ($unreadCount > 0): ?>
                <span class="sidebar__count"><?= $unreadCount ?></span>
            <?php endif; ?>
        </a>

        <div class="sidebar__section">Store</div>
        <a href="/dashboard/stores" class="sidebar__link <?= $panelPage === 'stores' ? 'active' : '' ?>">
            <i class="fas fa-store"></i> My Stores
        </a>
        <a href="/dashboard/store" class="sidebar__link <?= $panelPage === 'store' ? 'active' : '' ?>">
            <i class="fas fa-bag-shopping"></i> Store
        </a>
        <a href="/dashboard/orders" class="sidebar__link <?= $panelPage === 'orders' ? 'active' : '' ?>">
            <i class="fas fa-receipt"></i> My Orders
        </a>
        <a href="/dashboard/downloads" class="sidebar__link <?= $panelPage === 'downloads' ? 'active' : '' ?>">
            <i class="fas fa-download"></i> Downloads
        </a>
        <?php if ($isFreePlan): ?>
        <a href="/dashboard/license" class="sidebar__link <?= $panelPage === 'license' ? 'active' : '' ?>">
            <i class="fas fa-key"></i> My License
        </a>
        <?php else: ?>
        <a href="/dashboard/products" class="sidebar__link <?= $panelPage === 'products' ? 'active' : '' ?>">
            <i class="fas fa-box-open"></i> My Products
        </a>
        <?php endif; ?>

        <div class="sidebar__section">Subscription</div>
        <a href="/dashboard/subscription" class="sidebar__link <?= $panelPage === 'subscription' ? 'active' : '' ?>">
            <i class="fas fa-crown"></i> My Subscription
        </a>
        <?php if (!$isFreePlan): ?>
        <a href="/dashboard/license" class="sidebar__link <?= $panelPage === 'license' ? 'active' : '' ?>">
            <i class="fas fa-key"></i> My License
        </a>
        <?php endif; ?>

        <div class="sidebar__section">Support</div>
        <a href="/dashboard/support" class="sidebar__link <?= $panelPage === 'support' ? 'active' : '' ?>">
            <i class="fas fa-ticket"></i> My Support Tickets
        </a>

        <div class="sidebar__section">Account</div>
        <a href="/dashboard/account" class="sidebar__link <?= $panelPage === 'account' ? 'active' : '' ?>">
            <i class="fas fa-user-gear"></i> Account Settings
        </a>
        <?php if ($canAdmin): ?>
        <a href="/admin" class="sidebar__link">
            <i class="fas fa-shield-halved"></i> Admin Panel
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar__bottom">
        <a href="/logout" class="sidebar__logout">
            <i class="fas fa-right-from-bracket"></i> Sign Out
        </a>
    </div>
</aside>

<main class="main">

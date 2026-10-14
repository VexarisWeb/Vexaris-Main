<?php
require_once __DIR__ . '/../../core/db.php';
boot_session();
require_admin();

$pdo = db();

$tab = $_GET['tab'] ?? 'dashboard';
$tabPermissions = [
    'dashboard'   => 'dashboard',
    'users'       => 'users',
    'products'    => 'products',
    'licenses'    => 'licenses',
    'stores'      => 'licenses',
    'versions'    => 'versions',
    'demo'        => 'demo',
    'settings'    => 'settings',
    'legal'       => 'legal',
    'blog'        => 'blog',
    'seo'         => 'seo',
    'tickets'     => 'tickets',
    'inbox'       => 'tickets',
    'permissions' => 'permissions',
    'api'         => 'settings',
];
$allowedTabs = array_keys($tabPermissions);
if (!in_array($tab, $allowedTabs, true)) $tab = 'dashboard';
if (!has_permission($tabPermissions[$tab] ?? '')) $tab = 'dashboard';

$total_users = $pdo ? (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() : 0;
$total_views = $pdo ? (int)$pdo->query("SELECT COUNT(*) FROM page_views")->fetchColumn() : 0;
$total_tickets = $pdo ? (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status != 'closed'")->fetchColumn() : 0;
$unread_tickets = $pdo ? (int)$pdo->query("SELECT COUNT(*) FROM support_tickets t JOIN support_messages m ON m.ticket_id = t.id WHERE m.sender_role = 'user' AND m.is_read = 0")->fetchColumn() : 0;
$unread_inbox = 0;
if ($pdo) { try { $unread_inbox = (int)$pdo->query("SELECT COUNT(*) FROM inbound_emails WHERE is_read=0")->fetchColumn(); } catch (Exception $e) {} }

$user = current_user();
$myRole = user_role();
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Panel — Vexaris</title>
    <link rel="icon" type="image/png" href="/assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --cyan:#00d4ff;--blue:#007bff;
            --bg:#060810;--bg2:#0a0e1a;
            --card:rgba(255,255,255,0.04);
            --border:rgba(255,255,255,0.07);
            --border-c:rgba(0,212,255,0.15);
            --sidebar-w:240px;
            --gradient:linear-gradient(135deg,#00d4ff,#007bff);
            --text:rgba(255,255,255,0.85);
            --muted:rgba(255,255,255,0.4);
        }
        body{font-family:'Inter',sans-serif;background:#060810;background:linear-gradient(160deg,#060810 0%,#0a0e1a 100%);color:var(--text);min-height:100vh;min-height:100dvh;display:flex}
        body::before{content:'';position:fixed;top:-140px;right:-140px;width:420px;height:420px;background:radial-gradient(circle,rgba(0,212,255,.09),transparent 65%);pointer-events:none;z-index:0}
        body::after{content:'';position:fixed;bottom:-160px;left:20%;width:460px;height:460px;background:radial-gradient(circle,rgba(0,123,255,.08),transparent 65%);pointer-events:none;z-index:0}
        .sidebar,.main{position:relative;z-index:1}
        .sidebar{width:var(--sidebar-w);min-height:100vh;min-height:100dvh;background:rgba(6,8,16,.98);border-right:1px solid var(--border);display:flex;flex-direction:column;flex-shrink:0;position:fixed;top:0;left:0;bottom:0;z-index:100;transition:transform .25s ease}
        @media(max-width:768px){.topbar{display:flex}.page-header h1{display:none}}
        .sidebar__logo{padding:1.4rem 1.4rem .9rem;display:flex;align-items:center;gap:10px;text-decoration:none;border-bottom:1px solid var(--border)}
        .sidebar__logo img{height:30px}
        .sidebar__logo-text{font-size:1.1rem;font-weight:800;background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
        .sidebar__logo-badge{font-size:.65rem;background:rgba(0,212,255,.15);border:1px solid var(--border-c);color:var(--cyan);border-radius:4px;padding:2px 6px;margin-left:4px}
        .sidebar__nav{flex:1;padding:.8rem 0;overflow-y:auto}
        .sidebar__section{padding:.6rem 1.2rem .2rem;font-size:.68rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.09em}
        .sidebar__link{display:flex;align-items:center;gap:10px;padding:.65rem 1.2rem;color:var(--muted);text-decoration:none;font-size:.875rem;font-weight:500;transition:all .2s;position:relative;border-radius:0}
        .sidebar__link:hover{color:var(--text);background:rgba(255,255,255,.03)}
        .sidebar__link.active{color:var(--cyan);background:rgba(0,212,255,.06)}
        .sidebar__link.active::before{content:'';position:absolute;left:0;top:15%;bottom:15%;width:2px;background:var(--gradient);border-radius:0 2px 2px 0}
        .sidebar__link i{width:16px;text-align:center;font-size:.9rem}
        .sidebar__link .sidebar__count{margin-left:auto;min-width:20px;text-align:center;font-size:.68rem;font-weight:700;background:rgba(255,71,87,.15);border:1px solid rgba(255,71,87,.35);color:#ff7070;border-radius:50px;padding:2px 7px}
        .sidebar__bottom{padding:1rem 1.2rem;border-top:1px solid var(--border)}
        .sidebar__user{display:flex;align-items:center;gap:10px;margin-bottom:.8rem}
        .sidebar__avatar{width:34px;height:34px;background:var(--gradient);border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;color:#fff;flex-shrink:0}
        .sidebar__uinfo{font-size:.8rem}
        .sidebar__uname{font-weight:700;color:var(--text)}
        .sidebar__urole{color:var(--cyan);font-size:.7rem}
        .sidebar__logout{display:flex;align-items:center;gap:8px;color:rgba(255,100,100,.6);text-decoration:none;font-size:.83rem;font-weight:500;transition:color .2s;background:none;border:none;cursor:pointer;padding:0;font-family:'Inter',sans-serif;width:100%}
        .sidebar__logout:hover{color:#ff6b6b}
        .main{margin-left:var(--sidebar-w);flex:1;padding:2rem 2.5rem;min-height:100vh;min-height:100dvh;min-width:0;max-width:100%}
        .topbar{display:none;position:sticky;top:0;z-index:90;background:rgba(6,8,16,.92);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border-bottom:1px solid var(--border);padding:.7rem 1rem;padding-top:calc(.7rem + env(safe-area-inset-top));align-items:center;gap:.8rem}
        .topbar__btn{width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.05);border:1px solid var(--border);color:#fff;font-size:1.05rem;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .topbar__title{font-weight:800;font-size:.95rem;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .topbar__logo{font-weight:800;font-size:.85rem;background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;flex-shrink:0}
        .quick-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.8rem;margin-bottom:2rem}
        .quick{display:flex;align-items:center;gap:.7rem;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:.85rem 1rem;color:var(--text);text-decoration:none;font-size:.83rem;font-weight:600;transition:border-color .2s,transform .15s}
        .quick:hover{border-color:var(--border-c);transform:translateY(-1px)}
        .quick i{color:var(--cyan);font-size:1rem}
        .chart-bars{display:flex;align-items:flex-end;gap:8px;height:150px;padding:.5rem .25rem 0}
        .chart-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:.35rem;min-width:0}
        .chart-bar{width:100%;max-width:44px;border-radius:6px 6px 3px 3px;background:var(--gradient);min-height:4px;opacity:.9}
        .chart-bar.dim{background:rgba(255,255,255,.12)}
        .chart-lbl{font-size:.66rem;color:var(--muted)}
        .chart-val{font-size:.68rem;font-weight:700;color:var(--cyan)}
        .dash-alert{display:flex;align-items:center;gap:.7rem;background:rgba(251,146,60,.08);border:1px solid rgba(251,146,60,.3);border-radius:12px;padding:.8rem 1rem;font-size:.85rem;margin-bottom:1.2rem}
        .dash-alert i{color:#fb923c}
        .dash-alert a{color:var(--cyan);font-weight:700;text-decoration:none;margin-left:auto;flex-shrink:0}
        .page-header{margin-bottom:2rem;padding-bottom:1.2rem;border-bottom:1px solid var(--border)}
        .page-header h1{font-size:1.5rem;font-weight:800}
        .page-header p{color:var(--muted);font-size:.875rem;margin-top:.25rem}
        .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem}
        .stat{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1.25rem 1.5rem;position:relative;overflow:hidden;transition:border-color .3s}
        .stat:hover{border-color:var(--border-c)}
        .stat__top{display:flex;align-items:center;justify-content:space-between;margin-bottom:.8rem}
        .stat__icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1rem}
        .stat__icon.blue{background:rgba(0,212,255,.1);color:var(--cyan)}
        .stat__icon.green{background:rgba(34,197,94,.1);color:#4ade80}
        .stat__icon.orange{background:rgba(251,146,60,.1);color:#fb923c}
        .stat__icon.pink{background:rgba(236,72,153,.1);color:#f472b6}
        .stat__today{font-size:.72rem;color:var(--muted)}
        .stat__today span{color:#4ade80;font-weight:600}
        .stat__val{font-size:2rem;font-weight:800;line-height:1}
        .stat__label{font-size:.78rem;color:var(--muted);margin-top:.25rem}
        .section-title{font-size:1rem;font-weight:700;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
        .section-title i{color:var(--cyan);font-size:.9rem}
        .table-card{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:2rem}
        table{width:100%;border-collapse:collapse}
        th{padding:.75rem 1rem;text-align:left;font-size:.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;border-bottom:1px solid var(--border)}
        td{padding:.8rem 1rem;font-size:.85rem;border-bottom:1px solid rgba(255,255,255,.03)}
        tr:last-child td{border-bottom:none}
        tr:hover td{background:rgba(255,255,255,.02)}
        .badge{display:inline-flex;align-items:center;gap:4px;font-size:.7rem;font-weight:600;border-radius:50px;padding:3px 9px}
        .badge-admin{background:rgba(0,212,255,.12);color:var(--cyan);border:1px solid var(--border-c)}
        .badge-user{background:rgba(255,255,255,.06);color:var(--muted);border:1px solid var(--border)}
        .badge-active{background:rgba(34,197,94,.12);color:#4ade80;border:1px solid rgba(34,197,94,.2)}
        .badge-susp{background:rgba(251,146,60,.12);color:#fb923c;border:1px solid rgba(251,146,60,.25)}
        .badge-rev{background:rgba(255,71,87,.1);color:#ff7070;border:1px solid rgba(255,71,87,.3)}
        .feat-grid{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin:.4rem 0 1rem}
        .feat-label{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:9px;padding:.6rem .7rem;font-size:.84rem;cursor:pointer;transition:background .15s}
        .feat-label:hover{background:rgba(255,255,255,.07)}
        .feat-label input{accent-color:#00d4ff;width:15px;height:15px}
        .feat-hint{font-size:.72rem;color:var(--muted);margin-bottom:1rem}
        .lic-domain-row{display:flex;gap:.5rem;margin-bottom:.55rem}
        .lic-domain-row input{flex:1;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:9px;color:var(--text);font-family:'Inter',sans-serif;font-size:.88rem;padding:.55rem .8rem;outline:none}
        .lic-domain-row input:focus{border-color:var(--border-c)}
        .lic-domain-del{width:34px;height:34px;background:rgba(255,71,87,.08);border:1px solid rgba(255,71,87,.3);border-radius:8px;color:#ff7070;cursor:pointer;font-size:.8rem;flex-shrink:0;align-self:center}
        .lic-domain-add{padding:.5rem .85rem;background:rgba(0,212,255,.08);border:1px solid rgba(0,212,255,.25);border-radius:8px;color:var(--cyan);font-size:.82rem;font-weight:600;cursor:pointer;font-family:'Inter',sans-serif}
        .lic-domain-add:hover{background:rgba(0,212,255,.16)}
        .text-muted{color:var(--muted)}
        .two-col{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem}
        .page-row{display:flex;align-items:center;gap:1rem;padding:.6rem 1rem;border-bottom:1px solid rgba(255,255,255,.03)}
        .page-row:last-child{border-bottom:none}
        .page-row__name{font-size:.85rem;flex:1;font-weight:500}
        .page-row__bar{flex:2;height:6px;background:rgba(255,255,255,.07);border-radius:3px;overflow:hidden}
        .page-row__fill{height:100%;background:var(--gradient);border-radius:3px}
        .page-row__cnt{font-size:.82rem;font-weight:700;color:var(--cyan);min-width:30px;text-align:right}
        @media(max-width:1024px){.two-col{grid-template-columns:1fr}.stats-grid{grid-template-columns:1fr 1fr}.quick-grid{grid-template-columns:1fr 1fr}}
        @media(max-width:768px){.main{margin-left:0;padding:1rem 1rem 3rem;padding-bottom:calc(3rem + env(safe-area-inset-bottom))}.sidebar{transform:translateX(-100%);box-shadow:none}.sidebar.open{transform:translateX(0);box-shadow:20px 0 60px rgba(0,0,0,.5)}.stats-grid{grid-template-columns:1fr 1fr;gap:.7rem}.stat{padding:1rem}.stat__val{font-size:1.5rem}.topbar{display:flex}.page-header h1{font-size:1.2rem}.page-header{margin-bottom:1.2rem}.table-card{border-radius:12px}th:nth-child(4),td:nth-child(4){white-space:nowrap}}
        .btn-tbl{padding:5px 11px;border-radius:7px;font-size:.76rem;font-weight:600;cursor:pointer;font-family:inherit;border:1px solid;margin:0 2px;transition:background .15s}
        .btn-tbl-info{background:rgba(0,212,255,.08);border-color:rgba(0,212,255,.25);color:var(--cyan)}
        .btn-tbl-info:hover{background:rgba(0,212,255,.18)}
        .btn-tbl-del{background:rgba(255,71,87,.07);border-color:rgba(255,71,87,.2);color:#ff7070}
        .btn-tbl-del:hover{background:rgba(255,71,87,.18)}
        .btn-tbl-ok{background:rgba(34,197,94,.08);border-color:rgba(34,197,94,.25);color:#4ade80}
        .btn-tbl-ok:hover{background:rgba(34,197,94,.18)}
        .btn-tbl-warn{background:rgba(251,146,60,.08);border-color:rgba(251,146,60,.25);color:#fb923c}
        .btn-tbl-warn:hover{background:rgba(251,146,60,.18)}
        .detail-row{display:flex;flex-direction:column;gap:.25rem;margin-bottom:1rem}
        .detail-label{font-size:.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em}
        .detail-value{font-size:.9rem;color:var(--text);background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:9px;padding:.6rem .85rem;line-height:1.55;word-break:break-word}
        .search-bar{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin-bottom:1.5rem}
        .search-bar input{background:var(--card);border:1px solid var(--border);border-radius:9px;color:var(--text);font-family:'Inter',sans-serif;font-size:.85rem;padding:.55rem .85rem .55rem 2rem;outline:none;width:240px;transition:border-color .2s}
        .search-bar input:focus{border-color:var(--border-c)}
        .search-wrap{position:relative}
        .search-wrap i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.82rem;pointer-events:none}
        .btn-primary{padding:.55rem 1rem;background:var(--gradient);border:none;border-radius:9px;color:#fff;font-size:.85rem;font-weight:700;cursor:pointer;font-family:'Inter',sans-serif}
        .btn-ghost{padding:.55rem .9rem;background:var(--card);border:1px solid var(--border);border-radius:9px;color:var(--muted);font-size:.85rem;text-decoration:none;cursor:pointer;font-family:'Inter',sans-serif;display:inline-block}
        .pagination{display:flex;align-items:center;justify-content:center;gap:.4rem;margin-top:1.25rem}
        .page-btn{padding:5px 12px;border-radius:7px;font-size:.83rem;font-weight:600;text-decoration:none;background:var(--card);border:1px solid var(--border);color:var(--muted);cursor:pointer;font-family:'Inter',sans-serif}
        .page-btn.active{background:var(--gradient);color:#fff;border-color:transparent}
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center}
        .modal-box{background:#0d1b3e;border:1px solid rgba(0,212,255,.25);border-radius:20px;padding:2rem;max-width:460px;width:92%;position:relative;max-height:88vh;overflow-y:auto}
        .modal-close{position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.07);border:1px solid var(--border);border-radius:8px;color:var(--text);width:30px;height:30px;cursor:pointer;font-size:.95rem;display:flex;align-items:center;justify-content:center}
        .modal-title{font-size:1rem;font-weight:800;margin-bottom:1.4rem;color:#fff}
        .form-field{display:flex;flex-direction:column;gap:.4rem;margin-bottom:1rem}
        .form-field label{font-size:.78rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em}
        .form-field input,.form-field textarea,.form-field select{background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:9px;color:var(--text);font-family:'Inter',sans-serif;font-size:.9rem;padding:.6rem .85rem;outline:none;transition:border-color .2s}
        .form-field input:focus,.form-field textarea:focus,.form-field select:focus{border-color:var(--border-c)}
        .modal-actions{display:flex;gap:.6rem;margin-top:1.4rem}
        .btn-save{flex:1;padding:11px;background:var(--gradient);border:none;border-radius:12px;color:#fff;font-size:.9rem;font-weight:700;cursor:pointer;font-family:'Inter',sans-serif}
        .btn-del-modal{flex:1;padding:11px;background:rgba(255,71,87,.08);border:1px solid rgba(255,71,87,.3);border-radius:12px;color:#ff7070;font-size:.9rem;font-weight:700;cursor:pointer;font-family:'Inter',sans-serif}
        .alert-msg{border-radius:9px;padding:9px 14px;font-size:.84rem;font-weight:600;margin-bottom:1rem;display:none}
        .alert-ok{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);color:#4ade80}
        .alert-err{background:rgba(255,71,87,.08);border:1px solid rgba(255,71,87,.3);color:#ff7070}
        .panel{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1.2rem 1.5rem;margin-bottom:1.5rem}
        .table-wrap{overflow-x:auto}
        .btn{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer;font-family:'Inter',sans-serif;border:1px solid var(--border);background:var(--card);color:var(--text);transition:background .15s}
        .btn:hover{background:rgba(255,255,255,.06)}
        .ticket-wrap{display:grid;grid-template-columns:320px 1fr;gap:1.2rem;min-height:calc(100vh - 220px)}
        .ticket-side{background:var(--card);border:1px solid var(--border);border-radius:16px;display:flex;flex-direction:column;overflow:hidden;min-height:520px;max-height:calc(100vh - 220px);min-width:0}
        .ticket-side__head{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.2rem;border-bottom:1px solid var(--border)}
        .ticket-side__title{font-size:.95rem;font-weight:800;display:flex;align-items:center;gap:8px}
        .ticket-side__title i{color:var(--cyan)}
        .ticket-side__count{font-size:.72rem;font-weight:700;background:rgba(0,212,255,.12);border:1px solid var(--border-c);color:var(--cyan);border-radius:50px;padding:2px 9px}
        .ticket-side__search{position:relative;padding:.8rem 1.2rem;border-bottom:1px solid var(--border)}
        .ticket-side__search i{position:absolute;left:1.9rem;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.8rem}
        .ticket-side__search input{width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);font-family:'Inter',sans-serif;font-size:.85rem;padding:.55rem .85rem .55rem 2.1rem;outline:none}
        .ticket-side__search input:focus{border-color:var(--border-c)}
        .ticket-list{flex:1;overflow-y:auto;padding:.4rem;min-height:0;min-width:0}
        .ticket-item{display:flex;gap:.75rem;padding:.75rem .8rem;border-radius:12px;cursor:pointer;transition:background .15s;align-items:flex-start}
        .ticket-item:hover{background:rgba(255,255,255,.04)}
        .ticket-item.active{background:rgba(0,212,255,.08);border:1px solid var(--border-c)}
        .ticket-item.unread{background:rgba(0,212,255,.05)}
        .ticket-avatar{width:40px;height:40px;background:var(--gradient);border-radius:12px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.95rem;color:#fff;flex-shrink:0}
        .ticket-item__body{flex:1;min-width:0}
        .ticket-item__top{display:flex;align-items:center;justify-content:space-between;gap:6px}
        .ticket-item__name{font-size:.85rem;font-weight:700;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .ticket-item__time{font-size:.68rem;color:var(--muted);flex-shrink:0}
        .ticket-item__subj{font-size:.8rem;font-weight:600;color:var(--cyan);margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .ticket-item__bottom{display:flex;align-items:center;gap:6px;margin-top:5px}
        .ticket-item__last{font-size:.72rem;color:var(--muted);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .ticket-badge{min-width:20px;height:20px;padding:0 6px;background:rgba(255,71,87,.18);border:1px solid rgba(255,71,87,.4);color:#ff8a8a;font-size:.66rem;font-weight:800;border-radius:50px;display:flex;align-items:center;justify-content:center}
        .ticket-empty{text-align:center;color:var(--muted);padding:2.5rem 1rem;font-size:.85rem}
        .ticket-empty i{font-size:1.7rem;display:block;margin-bottom:.6rem;color:rgba(255,255,255,.18)}
        .ticket-chat{background:var(--card);border:1px solid var(--border);border-radius:16px;display:flex;flex-direction:column;overflow:hidden;height:calc(100vh - 220px);max-height:640px;min-height:420px;min-width:0}
        #chatPanel{display:flex;flex-direction:column;flex:1;min-height:0;min-width:0;overflow:hidden}
        .ticket-chat__empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--muted);gap:.6rem}
        .ticket-chat__empty i{font-size:2.4rem;color:rgba(0,212,255,.35)}
        .ticket-chat__empty p{font-size:.9rem}
        .ticket-chat__head{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.4rem;border-bottom:1px solid var(--border);flex-shrink:0}
        .ticket-chat__user{display:flex;align-items:center;gap:.8rem}
        .ticket-chat__name{font-size:.95rem;font-weight:800}
        .ticket-chat__sub{margin-top:4px}
        .ticket-chat__actions{display:flex;gap:.4rem}
        .ticket-btn{width:34px;height:34px;border-radius:10px;background:rgba(255,255,255,.05);border:1px solid var(--border);color:var(--muted);cursor:pointer;font-size:.85rem;display:flex;align-items:center;justify-content:center;transition:all .15s}
        .ticket-btn:hover{color:#ff7070;background:rgba(255,71,87,.08);border-color:rgba(255,71,87,.3)}
        .ticket-msgs{flex:1;overflow-y:auto;padding:1.4rem;display:flex;flex-direction:column;gap:.5rem;min-width:0;min-height:0}
        .msg-row{display:flex;margin-bottom:.3rem;width:100%;min-width:0}
        .msg-row.user{justify-content:flex-start}
        .msg-row.admin{justify-content:flex-end}
        .msg-bubble{max-width:78%;min-width:0;padding:.75rem 1rem;border-radius:16px;font-size:.88rem;line-height:1.55;overflow-wrap:anywhere;word-break:break-word}
        .msg-row.user .msg-bubble{background:rgba(0,212,255,.1);border:1px solid rgba(0,212,255,.2);border-bottom-left-radius:6px}
        .msg-row.admin .msg-bubble{background:rgba(255,255,255,.06);border:1px solid var(--border);border-bottom-right-radius:6px}
        .msg-text{white-space:pre-line;overflow-wrap:anywhere;word-break:break-word;min-width:0}
        .msg-img{display:block;max-width:230px;max-height:170px;width:auto;height:auto;border-radius:10px;margin-bottom:.45rem;cursor:zoom-in;border:1px solid rgba(255,255,255,.12);object-fit:cover}
        .msg-time{font-size:.66rem;color:var(--muted);margin-top:.35rem;display:flex;align-items:center;gap:4px}
        .msg-row.admin .msg-time{justify-content:flex-end}
        .msg-time i{color:var(--cyan);font-size:.6rem}
        .ticket-send{display:flex;align-items:flex-end;gap:.6rem;padding:1rem 1.4rem;border-top:1px solid var(--border);flex-shrink:0}
        .ticket-send textarea{flex:1;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:14px;color:var(--text);font-family:'Inter',sans-serif;font-size:.88rem;padding:.7rem 1rem;outline:none;resize:none;max-height:120px}
        .ticket-send textarea:focus{border-color:var(--border-c)}
        .ticket-send__btn{width:42px;height:42px;border-radius:14px;background:var(--gradient);border:none;color:#fff;font-size:1rem;cursor:pointer;flex-shrink:0;transition:opacity .15s}
        .ticket-send__btn:hover{opacity:.88}
        @media(max-width:900px){.ticket-wrap{grid-template-columns:1fr}.ticket-side{min-height:300px}}
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlayAdmin" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99"></div>
<style>@media(max-width:768px){.sidebar-overlay.open{display:block !important;}}</style>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <a href="/" class="sidebar__logo">
        <img src="/assets/images/logo.png" alt="Vexaris">
        <span class="sidebar__logo-text">Vexaris</span>
        <span class="sidebar__logo-badge">Admin</span>
    </a>

    <nav class="sidebar__nav">
        <div class="sidebar__section">Genel</div>
        <a href="/admin" class="sidebar__link <?= $tab === 'dashboard' ? 'active' : '' ?>">
            <i class="fas fa-gauge-high"></i> Dashboard
        </a>

        <div class="sidebar__section">Kullanıcılar</div>
        <?php if (has_permission('users')): ?>
        <a href="/admin?tab=users" class="sidebar__link <?= $tab === 'users' ? 'active' : '' ?>">
            <i class="fas fa-users"></i> Kullanıcılar
        </a>
        <?php endif; ?>

        <div class="sidebar__section">Destek</div>
        <?php if (has_permission('tickets')): ?>
        <a href="/admin?tab=tickets" class="sidebar__link <?= $tab === 'tickets' ? 'active' : '' ?>">
            <i class="fas fa-ticket"></i> Destek Talepleri
            <?php if ($unread_tickets > 0): ?>
                <span class="sidebar__count"><?= $unread_tickets ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        <?php if (has_permission('tickets')): ?>
        <a href="/admin?tab=inbox" class="sidebar__link <?= $tab === 'inbox' ? 'active' : '' ?>">
            <i class="fas fa-inbox"></i> Gelen Kutusu
            <?php if ($unread_inbox > 0): ?>
                <span class="sidebar__count"><?= $unread_inbox ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <div class="sidebar__section">İçerik</div>
        <?php if (has_permission('products')): ?>
        <a href="/admin?tab=products" class="sidebar__link <?= $tab === 'products' ? 'active' : '' ?>">
            <i class="fas fa-store"></i> Ürünler
        </a>
        <?php endif; ?>
        <?php if (has_permission('blog')): ?>
        <a href="/admin?tab=blog" class="sidebar__link <?= $tab === 'blog' ? 'active' : '' ?>">
            <i class="fas fa-pen-nib"></i> Blog
        </a>
        <?php endif; ?>
        <?php if (has_permission('seo')): ?>
        <a href="/admin?tab=seo" class="sidebar__link <?= $tab === 'seo' ? 'active' : '' ?>">
            <i class="fas fa-magnifying-glass-chart"></i> SEO Yönetimi
        </a>
        <?php endif; ?>

        <div class="sidebar__section">Sistem</div>
        <?php if (has_permission('licenses')): ?>
        <a href="/admin?tab=licenses" class="sidebar__link <?= $tab === 'licenses' ? 'active' : '' ?>">
            <i class="fas fa-key"></i> Lisanslar
        </a>
        <a href="/admin?tab=stores" class="sidebar__link <?= $tab === 'stores' ? 'active' : '' ?>">
            <i class="fas fa-store"></i> Mağazalar
        </a>
        <?php endif; ?>
        <?php if (has_permission('versions')): ?>
        <a href="/admin?tab=versions" class="sidebar__link <?= $tab === 'versions' ? 'active' : '' ?>">
            <i class="fas fa-box-archive"></i> Versiyonlar
        </a>
        <?php endif; ?>
        <?php if (has_permission('demo')): ?>
        <a href="/admin?tab=demo" class="sidebar__link <?= $tab === 'demo' ? 'active' : '' ?>">
            <i class="fas fa-rocket"></i> Demo Yönetimi
        </a>
        <?php endif; ?>
        <?php if (has_permission('settings')): ?>
        <a href="/admin?tab=settings" class="sidebar__link <?= $tab === 'settings' ? 'active' : '' ?>">
            <i class="fas fa-gear"></i> Ayarlar
        </a>
        <?php endif; ?>
        <?php if (has_permission('settings')): ?>
        <a href="/admin?tab=api" class="sidebar__link <?= $tab === 'api' ? 'active' : '' ?>">
            <i class="fas fa-sparkles"></i> API & AI
        </a>
        <?php endif; ?>
        <?php if (has_permission('legal')): ?>
        <a href="/admin?tab=legal" class="sidebar__link <?= $tab === 'legal' ? 'active' : '' ?>">
            <i class="fas fa-file-contract"></i> Yasal Sayfalar
        </a>
        <?php endif; ?>
        <?php if (has_permission('permissions')): ?>
        <a href="/admin?tab=permissions" class="sidebar__link <?= $tab === 'permissions' ? 'active' : '' ?>">
            <i class="fas fa-user-shield"></i> Rol & Yetkiler
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar__bottom">
        <div class="sidebar__user">
            <div class="sidebar__avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
            <div class="sidebar__uinfo">
                <div class="sidebar__uname"><?= htmlspecialchars($user['username']) ?></div>
                <div class="sidebar__urole"><?= htmlspecialchars(role_label($myRole)) ?></div>
            </div>
        </div>
        <a href="/logout" class="sidebar__logout">
            <i class="fas fa-right-from-bracket"></i> Çıkış Yap
        </a>
    </div>
</aside>

<!-- Main -->
<main class="main">
    <div class="topbar">
        <button class="topbar__btn" id="mobileToggleAdmin" aria-label="Menü"><i class="fas fa-bars"></i></button>
        <div class="topbar__title"><?= htmlspecialchars($pageTitle ?? 'Admin Panel') ?></div>
        <div class="topbar__logo">Vexaris</div>
    </div>
    <script>
    const VX_CSRF = <?= json_encode($csrf) ?>;
    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }
    </script>
    <div class="page-header">
        <h1><?= htmlspecialchars($pageTitle ?? 'Admin Panel') ?></h1>
        <p><?= date('d F Y, H:i') ?> &middot; Hoş geldin, <?= htmlspecialchars($user['username']) ?></p>
    </div>

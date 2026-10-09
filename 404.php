<?php
http_response_code(404);
require_once __DIR__ . '/core/db.php';
boot_session();
try { log_pageview('404'); } catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<link rel="alternate" hreflang="en" href="https://vexaris.net/404">
<link rel="alternate" hreflang="x-default" href="https://vexaris.net/404">
<title>404 • Vexaris</title>
<link rel="icon" type="image/png" href="/assets/images/vexaris-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--ink:#0b0d10;--line:#262c33;--text:#e9e7e2;--muted:#9aa3ac;--ember:#4f8fc0;--ember2:#7fb3d9;--fd:"Bricolage Grotesque",Manrope,sans-serif;--fb:"Manrope",sans-serif;--fm:"JetBrains Mono",ui-monospace,monospace}
body{font-family:var(--fb);background:var(--ink);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px 16px;position:relative;overflow:hidden;-webkit-font-smoothing:antialiased}
.bg-dots{position:fixed;inset:0;background-image:radial-gradient(rgba(233,231,226,.07) 1px,transparent 1px);background-size:26px 26px;pointer-events:none}
.bg-orb{position:fixed;top:-160px;left:50%;transform:translateX(-50%);width:680px;height:380px;border-radius:50%;background:rgba(79,143,192,.08);filter:blur(110px);pointer-events:none}
.ghost-bg{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);font-family:var(--fd);font-weight:800;font-size:min(38vw,340px);line-height:1;color:transparent;-webkit-text-stroke:1px #232a31;opacity:.7;pointer-events:none;user-select:none}
.card{position:relative;text-align:center;max-width:520px;animation:rise .55s cubic-bezier(.22,1,.36,1)}
@keyframes rise{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}
.ghost{width:84px;height:84px;margin:0 auto;border-radius:24px;border:1px solid var(--line);background:linear-gradient(180deg,#161b21,#12161a);display:grid;place-items:center;font-size:2rem;color:var(--ember);animation:floaty 5s ease-in-out infinite;box-shadow:0 30px 60px -25px rgba(0,0,0,.9)}
@keyframes floaty{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
.eyebrow{font-family:var(--fm);font-size:.72rem;letter-spacing:.22em;text-transform:uppercase;color:var(--ember);margin-top:26px}
h1{font-family:var(--fd);font-size:clamp(2rem,6vw,3rem);font-weight:800;letter-spacing:-.02em;line-height:1.1;margin-top:12px}
p{color:var(--muted);font-size:.95rem;line-height:1.65;margin-top:12px}
p code{font-family:var(--fm);font-size:.8em;color:var(--text);background:rgba(255,255,255,.05);border:1px solid var(--line);border-radius:6px;padding:2px 7px}
.btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:28px}
.btn-p{display:inline-flex;align-items:center;gap:9px;background:var(--ember);color:#16100b;font-weight:800;font-size:.9rem;text-decoration:none;border-radius:10px;padding:13px 26px;transition:transform .25s,background .25s;font-family:var(--fb)}
.btn-p:hover{background:var(--ember2);transform:translateY(-2px)}
.btn-g{display:inline-flex;align-items:center;gap:9px;border:1px solid var(--line);border-radius:10px;color:var(--text);font-weight:700;font-size:.9rem;text-decoration:none;padding:13px 26px;transition:border-color .25s,background .25s,transform .25s;font-family:var(--fb);cursor:pointer;background:none}
.btn-g:hover{border-color:#39424c;background:rgba(255,255,255,.04);transform:translateY(-2px)}
</style>
</head>
<body>
<div class="bg-dots" aria-hidden="true"></div>
<div class="bg-orb" aria-hidden="true"></div>
<div class="ghost-bg" aria-hidden="true">404</div>
<main class="card">
<div class="ghost"><i class="fa-solid fa-ghost"></i></div>
<div class="eyebrow">404 • Page not found</div>
<h1>Looks like you are lost.</h1>
<p>The page you are looking for has moved elsewhere — or never existed. Don't worry, let's get you back to safety.</p>
<div class="btns">
<a href="/" class="btn-p"><i class="fa-solid fa-house"></i> Home</a>
<button class="btn-g" onclick="history.back()"><i class="fa-solid fa-arrow-left"></i> Go Back</button>
</div>
</main>
</body>
</html>

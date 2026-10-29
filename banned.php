<?php
require_once __DIR__ . '/core/db.php';
boot_session();
$why = (string)($_GET['why'] ?? 'multi');
// Banlı oturum varsa düşür (temiz sayfa).
if (!empty($_SESSION['user'])) {
    try {
        $pdo = db();
        if ($pdo) {
            $st = $pdo->prepare("SELECT is_active FROM users WHERE id = ? LIMIT 1");
            $st->execute([(int)$_SESSION['user']['id']]);
            $v = $st->fetchColumn();
            if ($v !== false && (int)$v === 1) {
                header('Location: /dashboard');
                exit;
            }
        }
    } catch (Throwable $e) {}
    unset($_SESSION['user']);
    @session_regenerate_id(true);
}
$isMulti = ($why === 'multi');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account Suspended | Vexaris</title>
<link rel="icon" type="image/png" href="/assets/images/vexaris-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0b0d10;color:#e9e7e2;font-family:Manrope,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;-webkit-font-smoothing:antialiased}
.card{max-width:520px;width:100%;text-align:center;background:linear-gradient(180deg,#161b21,#12161a);border:1px solid #262c33;border-radius:18px;padding:48px 36px;box-shadow:0 40px 80px -30px rgba(0,0,0,.9)}
.ic{width:76px;height:76px;margin:0 auto 22px;border-radius:22px;display:grid;place-items:center;font-size:2rem;color:#ff8080;background:rgba(255,112,112,.08);border:1px solid rgba(255,112,112,.3);box-shadow:0 0 40px -10px rgba(255,112,112,.4)}
h1{font-family:"Bricolage Grotesque",Manrope,sans-serif;font-size:1.7rem;font-weight:800;letter-spacing:-.02em}
.badge{display:inline-flex;align-items:center;gap:6px;margin-top:10px;font-size:.7rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#ff8080;background:rgba(255,112,112,.08);border:1px solid rgba(255,112,112,.3);border-radius:50px;padding:5px 14px}
p{margin-top:16px;font-size:.92rem;line-height:1.7;color:#9aa3ac}
p strong{color:#e9e7e2}
.appeal{margin-top:22px;background:rgba(255,255,255,.03);border:1px dashed #2c333b;border-radius:12px;padding:14px 18px;font-size:.85rem;color:#9aa3ac}
.appeal a{color:#7fb3d9;font-weight:700;text-decoration:none}
.appeal a:hover{text-decoration:underline}
.home{display:inline-flex;align-items:center;gap:8px;margin-top:24px;color:#9aa3ac;font-size:.85rem;font-weight:700;text-decoration:none}
.home:hover{color:#fff}
</style>
</head>
<body>
<div class="card">
    <div class="ic"><i class="fa-solid fa-user-slash"></i></div>
    <h1>Account Suspended</h1>
    <?php if ($isMulti): ?>
    <span class="badge"><i class="fa-solid fa-triangle-exclamation"></i> Multi-account detected</span>
    <p>Our system detected <strong>more than one account belonging to the same person</strong>. Vexaris enforces a strict <strong>one-account-per-person</strong> policy, so the duplicate account has been suspended. Your original (first) account is not affected.</p>
    <?php else: ?>
    <span class="badge"><i class="fa-solid fa-gavel"></i> Suspended</span>
    <p>Your account has been <strong>suspended by an administrator</strong> for violating our policies. If you believe this is a mistake, you can appeal below.</p>
    <?php endif; ?>
    <div class="appeal"><i class="fa-solid fa-envelope"></i> If you believe this is a mistake, contact <a href="mailto:info@vexaris.net">info@vexaris.net</a> to appeal.</div>
    <a class="home" href="/"><i class="fa-solid fa-arrow-left"></i> Back to home</a>
</div>
</body>
</html>

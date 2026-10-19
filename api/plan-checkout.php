<?php
// POST /api/plan-checkout — SaaS plan aboneligi baslat (login gerekli)
// Aylik/yillik Whop renewal checkout olusturur, Whop'a yonlendirir.
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/whop.php';
boot_session();

if (!is_logged_in()) {
    header('Location: /login?next=/pricing');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pricing');
    exit;
}
csrf_check();

$uid = (int)current_user()['id'];
$plan = strtolower(trim((string)($_POST['plan'] ?? '')));
$cycle = strtolower(trim((string)($_POST['cycle'] ?? 'monthly')));
if ($cycle !== 'yearly') $cycle = 'monthly';
$fail = function () {
    header('Location: /pricing?err=start');
    exit;
};

$offers = whop_plan_offers();
if (!isset($offers[$plan])) $fail();
if (!whop_payments_enabled()) {
    header('Location: /pricing?err=disabled');
    exit;
}
$upSlug = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim((string)($_POST['store_slug'] ?? ''))));
$pdo = db();
if (!$pdo) $fail();
if ($upSlug !== '') {
    try {
        $uemail = strtolower(trim((string)(current_user()['email'] ?? '')));
        $st = $pdo->prepare("SELECT id FROM hosted_stores WHERE slug=? AND (user_id=? OR owner=?) LIMIT 1");
        $st->execute([$upSlug, $uid, $uemail]);
        if (!$st->fetch()) $upSlug = '';
    } catch (Exception $e) { $upSlug = ''; }
} else {
    /* Slug gonderilmemisse (pazarlama formu): kullanicinin aktif magazasi
       otomatik hedeflenir; aksi halde odeme alinip magaza free kaliyordu. */
    try {
        $uemail = strtolower(trim((string)(current_user()['email'] ?? '')));
        $st = $pdo->prepare("SELECT slug FROM hosted_stores WHERE (user_id=? OR owner=?) AND status='active' ORDER BY id ASC LIMIT 1");
        $st->execute([$uid, $uemail]);
        $found = (string)($st->fetchColumn() ?: '');
        if ($found !== '') $upSlug = $found;
    } catch (Exception $e) { $upSlug = ''; }
}

try {
    $amount = $cycle === 'yearly' ? $offers[$plan]['y'] : $offers[$plan]['m'];
    $pname = 'Vexaris ' . $offers[$plan]['label'] . ' (' . ($cycle === 'yearly' ? 'Yearly' : 'Monthly') . ')';
    $currency = strtoupper(whop_currency());

    /* Her checkout denemesi kendi siparisini acar: ayni order_no'ya iki odeme
       yapilirsa ikinci odeme sessizce kayboluyordu. */
    $pdo->prepare("UPDATE orders SET status='cancelled' WHERE user_id=? AND provider='whop' AND product_id=0 AND status='pending'")->execute([$uid]);
    $orderNo = 'VEX-' . date('ymdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    $pdo->prepare("INSERT INTO orders (user_id, order_no, product_name, amount, status, provider, product_id, currency) VALUES (?,?,?,?, 'pending','whop',0,?)")
        ->execute([$uid, $orderNo, $pname, $amount, $currency]);

    $returnUrl = 'https://vexaris.net/dashboard/orders?paid=1&order=' . urlencode($orderNo);
    $res = whop_create_plan_checkout($plan, $cycle,
        ['order_no' => $orderNo, 'user_id' => (string)$uid, 'plan' => $plan, 'cycle' => $cycle, 'store_slug' => $upSlug],
        $returnUrl);
    if (!empty($res['error']) || empty($res['url'])) {
        $pdo->prepare("UPDATE orders SET status = 'failed' WHERE order_no = ?")->execute([$orderNo]);
        $fail();
    }
    if (!empty($res['checkout_id'])) {
        $pdo->prepare("UPDATE orders SET provider_ref = ? WHERE order_no = ?")->execute([$res['checkout_id'], $orderNo]);
    }
    header('Location: ' . $res['url']);
    exit;
} catch (Throwable $e) {
    error_log('Whop plan checkout: ' . $e->getMessage());
    $fail();
}

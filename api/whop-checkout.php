<?php
// POST /api/whop-checkout — Whop ile ödeme başlat (login gerekli)
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/whop.php';
boot_session();
if (!is_logged_in()) { header('Location: /login'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_check();

if (!is_logged_in()) {
    header('Location: /login?next=/dashboard/store');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard/store');
    exit;
}

$uid = (int)current_user()['id'];
$pid = (int)($_POST['product_id'] ?? 0);
$fail = function () {
    header('Location: /dashboard/store?err=start');
    exit;
};

if (!whop_payments_enabled()) {
    header('Location: /dashboard/store?err=disabled');
    exit;
}
$pdo = db();
if (!$pdo || $pid <= 0) $fail();

try {
    $st = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1 LIMIT 1");
    $st->execute([$pid]);
    $prod = $st->fetch();
    if (!$prod || (float)$prod['price'] <= 0) $fail();

    $own = $pdo->prepare("SELECT COUNT(*) FROM user_products WHERE user_id = ? AND product_id = ?");
    $own->execute([$uid, $pid]);
    if ((int)$own->fetchColumn() > 0) {
        header('Location: /dashboard/products');
        exit;
    }

    $currency = strtoupper($prod['currency'] !== '' ? $prod['currency'] : whop_currency());
    $amount = round((float)$prod['price'], 2);
    $orderNo = 'VEX-' . date('ymdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

    $pdo->prepare("INSERT INTO orders (user_id, order_no, product_name, amount, status, provider, product_id, currency) VALUES (?,?,?,?, 'pending','whop',?,?)")
        ->execute([$uid, $orderNo, $prod['name'], $amount, $pid, $currency]);

    $returnUrl = whop_base_url() . '/dashboard/orders?paid=1&order=' . urlencode($orderNo);
    $res = whop_create_checkout(
        (string)$prod['name'],
        $amount,
        $currency,
        ['order_no' => $orderNo, 'user_id' => (string)$uid, 'product_id' => (string)$pid],
        $returnUrl
    );
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
    error_log('Whop checkout: ' . $e->getMessage());
    $fail();
}

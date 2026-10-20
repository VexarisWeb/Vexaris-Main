<?php
// POST /api/whop-webhook — Whop ödeme bildirimleri (imzalı, auth yok)
// Olaylar: payment.succeeded → siparişi tamamla + ürünü tanımla
//          payment.failed    → siparişi başarısız işaretle
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/whop.php';

// Iptal/iade/dispute sonrasi magazayi free'ye dusurur: panel plani + free
// limitlerle rebuild job'u (custom domain free hook ile budanir).
function whop_downgrade_store(PDO $pdo, int $uid): void {
    if ($uid <= 0) return;
    try {
        $st = $pdo->prepare("SELECT email, password FROM users WHERE id=? LIMIT 1");
        $st->execute([$uid]);
        $urow = $st->fetch() ?: [];
        $uemail = strtolower(trim((string)($urow['email'] ?? '')));
        $uhash = (string)($urow['password'] ?? '');
        if (!preg_match('/^\$(2y|2a|argon2i|argon2id)\$/', $uhash)) $uhash = '';
        $st = $pdo->prepare("SELECT slug, owner, category, lang FROM hosted_stores WHERE (user_id=? OR owner=?) AND status='active' LIMIT 1");
        $st->execute([$uid, $uemail]);
        $srow = $st->fetch();
        if (!$srow) return;
        $slug = (string)($srow['slug'] ?? '');
        if ($slug === '') return;
        /* Bekleyen job varsa dusurme yutulmasin: mevcut job'a plan yaz (override). */
        $st = $pdo->prepare("SELECT id FROM hosting_jobs WHERE slug=? AND status IN ('pending','claimed') ORDER BY id DESC LIMIT 1");
        $st->execute([$slug]);
        $pendingJob = $st->fetch();
        if ($pendingJob) {
            $pdo->prepare("UPDATE hosting_jobs SET plan='free' WHERE id=?")->execute([(int)$pendingJob['id']]);
        }
        $dcat = strtolower(trim((string)($srow['category'] ?? 'other')));
        if (!in_array($dcat, ['epin', 'ebook', 'design', 'software', 'other'], true)) $dcat = 'other';
        $dlang = strtolower(trim((string)($srow['lang'] ?? 'en')));
        if ($dlang !== 'tr' && $dlang !== 'en') $dlang = 'en';
        $pdo->prepare("UPDATE hosted_stores SET plan='free' WHERE slug=?")->execute([$slug]);
        if (!$pendingJob) {
            $pdo->prepare("INSERT INTO hosting_jobs (user_id, slug, plan, owner, site_name, lang, kind, status, owner_hash, category) VALUES (?, ?, 'free', ?, ?, ?, 'provision', 'pending', ?, ?)")
                ->execute([$uid, $slug, (string)($srow['owner'] ?? $uemail), $slug, $dlang, $uhash, $dcat]);
        }
    } catch (Throwable $e) {
        error_log('Whop downgrade job: ' . $e->getMessage());
    }
}

// Odeme sonrasi magazaya plani uygular: domain lisansini guncelleyip
// hosted_stores.plan'i yazar ve limitlerin uygulanmasi icin provision job acar.
function whop_apply_store_plan(PDO $pdo, int $uid, string $plan, string $metaSlug): void {
    if ($uid <= 0 || $plan === '') return;
    $st = $pdo->prepare("SELECT email, password FROM users WHERE id=? LIMIT 1");
    $st->execute([$uid]);
    $urow = $st->fetch() ?: [];
    $uemail = strtolower(trim((string)($urow['email'] ?? '')));
    $uhash = (string)($urow['password'] ?? '');
    if (!preg_match('/^\$(2y|2a|argon2i|argon2id)\$/', $uhash)) $uhash = '';

    $stores = [];
    if ($metaSlug !== '') {
        $hs = $pdo->prepare("SELECT slug, owner, user_id, category, lang, plan FROM hosted_stores WHERE slug=? LIMIT 1");
        $hs->execute([$metaSlug]);
        $hrow = $hs->fetch();
        $owns = $hrow && ((int)($hrow['user_id'] ?? 0) === $uid || ($uemail !== '' && strtolower((string)($hrow['owner'] ?? '')) === $uemail));
        if ($owns) $stores[] = $hrow;
    }
    if (!$stores) {
        $all = $pdo->prepare("SELECT slug, owner, user_id, category, lang, plan FROM hosted_stores WHERE (user_id=? OR owner=?) AND status='active' ORDER BY id ASC");
        $all->execute([$uid, $uemail]);
        $stores = $all->fetchAll();
    }
    foreach ($stores as $hrow) {
        $slug = (string)($hrow['slug'] ?? '');
        if ($slug === '') continue;
        $domain = $slug . '.vexaris.app';
        /* Magazayi kapsayan lisansi guncelle (tam eslesme; register lisansinda
           user_id NULL ise baglanir -> abonelik bitince otomatik free'ye duser). */
        $lq = $pdo->prepare("SELECT id, features, user_id FROM licenses WHERE allowed_domains LIKE ? ORDER BY id ASC LIMIT 1");
        $lq->execute(['%"' . $domain . '"%']);
        $lic = $lq->fetch();
        if ($lic) {
            $lf = json_decode((string)$lic['features'], true);
            if (!is_array($lf)) $lf = [];
            $lf['plan'] = $plan;
            $lf['plan_auto'] = 1;
            if (empty($lic['user_id'])) {
                $pdo->prepare("UPDATE licenses SET features=?, status='active', suspended_until=NULL, user_id=? WHERE id=?")
                   ->execute([json_encode($lf), $uid, (int)$lic['id']]);
            } else {
                $pdo->prepare("UPDATE licenses SET features=?, status='active', suspended_until=NULL WHERE id=?")
                   ->execute([json_encode($lf), (int)$lic['id']]);
            }
        } else {
            $licKey = bin2hex(random_bytes(24));
            $pdo->prepare("INSERT INTO licenses (customer_name, license_key, allowed_domains, status, features, suspended_until, user_id) VALUES (?,?,?,'active',?,NULL,?)")
                ->execute([$slug, $licKey, json_encode([$domain]), json_encode(['plan' => $plan, 'plan_auto' => 1]), $uid]);
        }
        $pdo->prepare("UPDATE hosted_stores SET plan=? WHERE slug=?")->execute([$plan, $slug]);

        $cat = strtolower(trim((string)($hrow['category'] ?? 'other')));
        if (!in_array($cat, ['epin', 'ebook', 'design', 'software', 'other'], true)) $cat = 'other';
        $lang = strtolower(trim((string)($hrow['lang'] ?? 'en')));
        if ($lang !== 'tr' && $lang !== 'en') $lang = 'en';
        $pending = $pdo->prepare("SELECT id FROM hosting_jobs WHERE slug=? AND status IN ('pending','claimed') ORDER BY id DESC LIMIT 1");
        $pending->execute([$slug]);
        $pj = $pending->fetch();
        if ($pj) {
            $pdo->prepare("UPDATE hosting_jobs SET plan=? WHERE id=?")->execute([$plan, (int)$pj['id']]);
        } else {
            $pdo->prepare("INSERT INTO hosting_jobs (user_id, slug, plan, owner, site_name, lang, kind, status, owner_hash, category) VALUES (?,?,?, ?,?, ?, 'provision','pending', ?, ?)")
                ->execute([$uid, $slug, $plan, (string)($hrow['owner'] ?? $uemail), $slug, $lang, $uhash, $cat]);
        }
    }
}

// Iade/dispute: urun erisimi geri alinir (indirme satirlari silinir).
function whop_revoke_product_access(PDO $pdo, int $uid, int $productId): void {
    if ($uid <= 0 || $productId <= 0) return;
    try {
        $pdo->prepare("DELETE FROM user_products WHERE user_id=? AND product_id=?")->execute([$uid, $productId]);
        $pdo->prepare("DELETE FROM download_tokens WHERE user_id=? AND product_id=?")->execute([$uid, $productId]);
    } catch (Throwable $e) {
        error_log('Whop iade erisim temizligi: ' . $e->getMessage());
    }
}

// Islenen webhook olayini isaretler (tekrar isleme / cift uzatma korumasi).
function whop_mark_event(PDO $pdo, string $eventId): void {
    if ($eventId === '') return;
    try {
        $pdo->prepare("INSERT IGNORE INTO whop_events (event_id, created_at) VALUES (?, NOW())")->execute([$eventId]);
        $pdo->prepare("DELETE FROM whop_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")->execute();
    } catch (Throwable $e) {}
}

$payload = file_get_contents('php://input') ?: '';
$msgId  = (string)($_SERVER['HTTP_WEBHOOK_ID'] ?? $_SERVER['HTTP_SVIX_ID'] ?? '');
$msgTs  = (string)($_SERVER['HTTP_WEBHOOK_TIMESTAMP'] ?? $_SERVER['HTTP_SVIX_TIMESTAMP'] ?? '');
$msgSig = (string)($_SERVER['HTTP_WEBHOOK_SIGNATURE'] ?? $_SERVER['HTTP_SVIX_SIGNATURE'] ?? '');

if (!whop_verify_webhook($payload, $msgId, $msgTs, $msgSig)) {
    http_response_code(400);
    echo 'bad signature';
    exit;
}

$event = json_decode($payload, true);
http_response_code(200); // Whop'a hızlı 200 (5 sn kuralı)
echo 'OK';

if (!is_array($event)) return;
$type = (string)($event['type'] ?? '');

/* Son webhook teslimini kaydet (panelde "son webhook" gosterilir; teslim
   gelmiyorsa sorun kaynagi tek bakista gorulur). */
try {
    $pdoTrack = db();
    if ($pdoTrack) {
        $up = $pdoTrack->prepare("INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
        $up->execute(['whop_last_webhook_at', (string)time()]);
        $up->execute(['whop_last_webhook_type', mb_substr($type, 0, 60)]);
    }
} catch (Throwable $e) {}

$data = is_array($event['data'] ?? null) ? $event['data'] : [];
$meta = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
$orderNo = (string)($meta['order_no'] ?? '');

/* Ayni olay iki kez islenmesin (whop_events tablosu). */
$eventId = $msgId !== '' ? substr($msgId, 0, 64) : '';
if ($eventId !== '') {
    try {
        $evChk = db();
        if ($evChk) {
            $evSt = $evChk->prepare("SELECT id FROM whop_events WHERE event_id=? LIMIT 1");
            $evSt->execute([$eventId]);
            if ($evSt->fetch()) { error_log('Whop webhook: olay zaten islenmis, atlandi (' . $eventId . ')'); return; }
        }
    } catch (Throwable $e) {}
}

if (stripos($type, 'membership') !== false) {
    /* Abonelik iptal/olaylari order_no tasimasa da islenmeli. */
    try {
        $pdo = db();
        if ($pdo) {
            $memId = '';
            foreach (['membership_id', 'membership'] as $mk) {
                if (isset($data[$mk]) && is_string($data[$mk]) && $data[$mk] !== '') { $memId = $data[$mk]; break; }
                if (isset($data[$mk]) && is_array($data[$mk]) && isset($data[$mk]['id']) && is_string($data[$mk]['id'])) { $memId = $data[$mk]['id']; break; }
            }
            if ($memId === '') $memId = (string)($data['id'] ?? '');
            $mStatus = strtolower((string)($data['status'] ?? ''));
            $mValid = $data['valid'] ?? null;
            if ($memId !== '' && (in_array($mStatus, ['cancelled', 'canceled', 'expired', 'invalid'], true) || $mValid === false || $mValid === 0 || $mValid === 'false')) {
                $sq = $pdo->prepare("SELECT user_id, whop_membership_id FROM user_subscriptions WHERE whop_membership_id=? LIMIT 1");
                $sq->execute([$memId]);
                $sRow = $sq->fetch();
                $sUid = $sRow ? (int)$sRow['user_id'] : 0;
                if ($sUid <= 0 && $orderNo !== '') {
                    $oq = $pdo->prepare("SELECT user_id FROM orders WHERE order_no=? LIMIT 1");
                    $oq->execute([$orderNo]);
                    $sUid = (int)($oq->fetchColumn() ?: 0);
                }
                if ($sUid <= 0) {
                    $metaUid = (int)($meta['user_id'] ?? 0);
                    if ($metaUid > 0) {
                        $cs = $pdo->prepare("SELECT whop_membership_id FROM user_subscriptions WHERE user_id=? LIMIT 1");
                        $cs->execute([$metaUid]);
                        $storedMem = (string)$cs->fetchColumn();
                        if ($storedMem === '' || $storedMem === $memId) $sUid = $metaUid;
                    }
                }
                if ($sUid > 0) {
                    $pdo->prepare("UPDATE user_subscriptions SET status='cancelled' WHERE user_id=?")->execute([$sUid]);
                    $lq = $pdo->prepare("SELECT id, features FROM licenses WHERE user_id=?");
                    $lq->execute([$sUid]);
                    foreach ($lq->fetchAll() as $lr) {
                        $lf = json_decode((string)$lr['features'], true);
                        if (is_array($lf) && !empty($lf['plan_auto'])) {
                            $lf['plan'] = 'free';
                            $pdo->prepare("UPDATE licenses SET features=? WHERE id=?")->execute([json_encode($lf), (int)$lr['id']]);
                        }
                    }
                    notify_user($sUid, 'info', 'Subscription cancelled', 'Your Vexaris plan subscription ended. Your site continues on the Free plan.');
                    whop_downgrade_store($pdo, $sUid);
                }
            }
        }
    } catch (Throwable $e) {
        error_log('Whop cancel isleme: ' . $e->getMessage());
    }
    return;
}
if ($orderNo === '') {
    error_log('Whop webhook: metadata.order_no yok (' . $type . ')');
    return;
}

try {
    $pdo = db();
    if (!$pdo) return;
    $st = $pdo->prepare("SELECT * FROM orders WHERE order_no = ? AND provider = 'whop' LIMIT 1");
    $st->execute([$orderNo]);
    $order = $st->fetch();
    if (!$order) {
        $rnPlan = strtolower((string)($meta['plan'] ?? ''));
        $rnUid = (int)($meta['user_id'] ?? 0);
        if ($type === 'payment.succeeded' && $rnUid > 0 && isset(whop_plan_offers()[$rnPlan])) {
            $rnCycle = (string)($meta['cycle'] ?? '') === 'yearly' ? 'yearly' : 'monthly';
            $rnDays = $rnCycle === 'yearly' ? 365 : 30;
            $rnNow = time();
            $ex = $pdo->prepare("SELECT current_period_end FROM user_subscriptions WHERE user_id=? LIMIT 1");
            $ex->execute([$rnUid]);
            $rnCur = $ex->fetch();
            $rnBase = ($rnCur && (int)$rnCur['current_period_end'] > $rnNow) ? (int)$rnCur['current_period_end'] : $rnNow;
            $pdo->prepare("UPDATE user_subscriptions SET plan=?, cycle=?, status='active', current_period_end=? WHERE user_id=?")
                ->execute([$rnPlan, $rnCycle, $rnBase + $rnDays * 86400, $rnUid]);
            error_log('Whop webhook: yenileme uzatildi (siparis satiri yok, user=' . $rnUid . ')');
        } else {
            error_log('Whop webhook: sipariş bulunamadı (' . $orderNo . ')');
        }
        return;
    }
    $payId = (string)($data['id'] ?? '');

    if ($type === 'payment.succeeded') {
        if ($order['status'] === 'completed') {
            if ($eventId !== '') whop_mark_event($pdo, $eventId);
            return; // tekrar bildirimi
        }
        /* Tutar ve para birimi kontrolu: alan geliyorsa dogrulanir. */
        $paidAmt = null;
        foreach (['total', 'amount', 'gross_amount', 'price'] as $ak) {
            if (isset($data[$ak]) && is_numeric($data[$ak])) { $paidAmt = (float)$data[$ak]; break; }
        }
        $expAmt = (float)$order['amount'];
        if ($paidAmt !== null) {
            $okAmt = ($paidAmt >= $expAmt - 0.01) || (($paidAmt / 100) >= $expAmt - 0.01);
            if (!$okAmt) {
                error_log('Whop webhook: tutar uyusmazligi (' . $orderNo . ' beklenen=' . $expAmt . ' gelen=' . $paidAmt . ')');
                return;
            }
        } else {
            error_log('Whop webhook: tutar alani yok, dogrulanamadi (' . $orderNo . ')');
        }
        $payCur = strtoupper(trim((string)($data['currency'] ?? '')));
        $ordCur = strtoupper(trim((string)($order['currency'] ?? 'USD')));
        if ($payCur !== '' && $ordCur !== '' && $payCur !== $ordCur) {
            error_log('Whop webhook: para birimi uyusmazligi (' . $orderNo . ' beklenen=' . $ordCur . ' gelen=' . $payCur . ')');
            return;
        }
        $pdo->prepare("UPDATE orders SET status = 'completed', provider_ref = ? WHERE id = ?")
            ->execute([$payId !== '' ? $payId : $order['provider_ref'], (int)$order['id']]);
        $uid = (int)$order['user_id'];
        $pid = (int)($order['product_id'] ?? 0);
        if ($uid > 0 && $pid > 0) {
            $pdo->prepare("INSERT IGNORE INTO user_products (user_id, product_id) VALUES (?,?)")
                ->execute([$uid, $pid]);
            notify_user($uid, 'approved', 'Payment successful — ' . $order['product_name'],
                'Your payment for "' . $order['product_name'] . '" (' . $order['amount'] . ' ' . strtoupper($order['currency'] !== '' ? $order['currency'] : 'USD') . ') is confirmed. Download it from My Products.');
        }
        $subPlan = strtolower((string)($meta['plan'] ?? ''));
        if ($uid > 0 && isset(whop_plan_offers()[$subPlan])) {
            $subCycle = (string)($meta['cycle'] ?? '') === 'yearly' ? 'yearly' : 'monthly';
            $days = $subCycle === 'yearly' ? 365 : 30;
            $now = time();
            try {
                $ex = $pdo->prepare("SELECT current_period_end, status FROM user_subscriptions WHERE user_id = ? LIMIT 1");
                $ex->execute([$uid]);
                $cur = $ex->fetch();
                $base = $now;
                if ($cur && (int)$cur['current_period_end'] > $now) $base = (int)$cur['current_period_end'];
                $end = $base + $days * 86400;
                $memId = '';
                foreach (['membership_id', 'membership'] as $mk) {
                    if (isset($data[$mk]) && is_string($data[$mk]) && $data[$mk] !== '') { $memId = $data[$mk]; break; }
                    if (isset($data[$mk]) && is_array($data[$mk]) && isset($data[$mk]['id']) && is_string($data[$mk]['id'])) { $memId = $data[$mk]['id']; break; }
                }
                if ($memId === '' && isset($data['resource']) && is_array($data['resource']) && isset($data['resource']['id']) && is_string($data['resource']['id'])) $memId = $data['resource']['id'];
                $pdo->prepare("INSERT INTO user_subscriptions (user_id, plan, cycle, status, current_period_end, whop_membership_id) VALUES (?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE plan=VALUES(plan), cycle=VALUES(cycle), status='active', current_period_end=VALUES(current_period_end), whop_membership_id=VALUES(whop_membership_id)")
                    ->execute([$uid, $subPlan, $subCycle, 'active', $end, mb_substr($memId, 0, 64)]);
                notify_user($uid, 'approved', 'Plan active — Vexaris ' . whop_plan_offers()[$subPlan]['label'],
                    'Your ' . ($subCycle === 'yearly' ? 'yearly' : 'monthly') . ' plan is active until ' . date('d.m.Y', $end) . '. Manage it anytime from your panel.');
                try {
                    $uq = $pdo->prepare("SELECT username FROM users WHERE id=? LIMIT 1");
                    $uq->execute([$uid]);
                    $uname = (string)$uq->fetchColumn();
                    if ($uname === '') $uname = 'user-' . $uid;
                    $lic = $pdo->prepare("SELECT id, license_key, features FROM licenses WHERE user_id=? ORDER BY id ASC LIMIT 1");
                    $lic->execute([$uid]);
                    $licRow = $lic->fetch();
                    $feat = ['core' => true, 'theme_azure' => true, 'theme_ember' => true, 'tickets' => true, 'mail_system' => true, 'admin_panel' => true, 'seo' => true, 'announcements' => true, 'coupons' => true, 'custom_pages' => true, 'references' => true, 'reviews' => true, 'discord_integration' => true, 'roles_permissions' => true, 'updates' => true, 'plan' => $subPlan, 'plan_auto' => 1];
                    if ($licRow) {
                        $oldF = json_decode((string)$licRow['features'], true);
                        if (is_array($oldF)) foreach ($oldF as $fk => $fv) { if ($fk !== 'plan' && $fk !== 'plan_auto') $feat[$fk] = $fv; }
                        $pdo->prepare("UPDATE licenses SET features=?, status='active', suspended_until=NULL WHERE id=?")
                            ->execute([json_encode($feat), (int)$licRow['id']]);
                        $licKey = (string)$licRow['license_key'];
                    } else {
                        $licKey = bin2hex(random_bytes(24));
                        $pdo->prepare("INSERT INTO licenses (customer_name, license_key, allowed_domains, status, features, suspended_until, user_id) VALUES (?,?,?,'active',?,NULL,?)")
                            ->execute([$uname, $licKey, json_encode([]), json_encode($feat), $uid]);
                    }
                    notify_user($uid, 'approved', 'License ready — ' . whop_plan_offers()[$subPlan]['label'] . ' plan assigned',
                        'Your license key: ' . $licKey . '. Add your store domain from Licenses (or contact support) so your site picks up the ' . whop_plan_offers()[$subPlan]['label'] . ' limits automatically.');
                } catch (Throwable $e) {
                    error_log('Whop license grant: ' . $e->getMessage());
                }
                try {
                    $upSlug = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim((string)($meta['store_slug'] ?? ''))));
                    /* Magazaya plani uygula: metadata'da slug yoksa kullanicinin
                       magazalarina otomatik uygulanir (eski akista bu yuzden
                       odeme alinip magaza free k kaliyordu). */
                    whop_apply_store_plan($pdo, $uid, $subPlan, $upSlug);
                } catch (Throwable $e) {
                    error_log('Whop store upgrade job: ' . $e->getMessage());
                }
            } catch (Throwable $e) {
                error_log('Whop plan grant: ' . $e->getMessage());
            }
        }
    } elseif ($type === 'payment.failed') {
        if ($order['status'] === 'pending') {
            $pdo->prepare("UPDATE orders SET status = 'failed' WHERE id = ?")->execute([(int)$order['id']]);
        }
    } elseif ($type === 'payment.refunded') {
        $pdo->prepare("UPDATE orders SET status = 'refunded' WHERE id = ?")->execute([(int)$order['id']]);
        $rfPlan = strtolower((string)($meta['plan'] ?? ''));
        $rfUid = (int)$order['user_id'];
        $rfPid = (int)($order['product_id'] ?? 0);
        /* Iade edilen urunun erisimi geri alinir. */
        if ($rfUid > 0 && $rfPid > 0) whop_revoke_product_access($pdo, $rfUid, $rfPid);
        if ($rfUid > 0 && isset(whop_plan_offers()[$rfPlan])) {
            $pdo->prepare("UPDATE user_subscriptions SET status='cancelled' WHERE user_id=?")->execute([$rfUid]);
            $lq = $pdo->prepare("SELECT id, features FROM licenses WHERE user_id=?");
            $lq->execute([$rfUid]);
            foreach ($lq->fetchAll() as $lr) {
                $lf = json_decode((string)$lr['features'], true);
                if (is_array($lf) && !empty($lf['plan_auto'])) {
                    $lf['plan'] = 'free';
                    $pdo->prepare("UPDATE licenses SET features=? WHERE id=?")->execute([json_encode($lf), (int)$lr['id']]);
                }
            }
            notify_user($rfUid, 'info', 'Payment refunded', 'Your plan payment was refunded. Your site continues on the Free plan.');
            whop_downgrade_store($pdo, $rfUid);
        }
    } elseif (stripos($type, 'dispute') !== false || stripos($type, 'chargeback') !== false) {
        $dpPay = (string)($data['payment_id'] ?? $data['id'] ?? '');
        if ($dpPay !== '') {
            $dq = $pdo->prepare("SELECT id, user_id, product_id FROM orders WHERE provider_ref=? AND provider='whop' LIMIT 1");
            $dq->execute([$dpPay]);
            $dOrd = $dq->fetch();
            if ($dOrd) {
                $pdo->prepare("UPDATE orders SET status='disputed' WHERE id=?")->execute([(int)$dOrd['id']]);
                $dpUid = (int)$dOrd['user_id'];
                $dpPid = (int)($dOrd['product_id'] ?? 0);
                if ($dpUid > 0 && $dpPid > 0) whop_revoke_product_access($pdo, $dpUid, $dpPid);
                $pdo->prepare("UPDATE user_subscriptions SET status='cancelled' WHERE user_id=?")->execute([$dpUid]);
                $lq = $pdo->prepare("SELECT id, features FROM licenses WHERE user_id=?");
                $lq->execute([$dpUid]);
                foreach ($lq->fetchAll() as $lr) {
                    $lf = json_decode((string)$lr['features'], true);
                    if (is_array($lf) && !empty($lf['plan_auto'])) {
                        $lf['plan'] = 'free';
                        $pdo->prepare("UPDATE licenses SET features=? WHERE id=?")->execute([json_encode($lf), (int)$lr['id']]);
                    }
                }
                notify_user($dpUid, 'info', 'Payment disputed', 'A dispute was opened on your plan payment. Your site continues on the Free plan until resolved.');
                whop_downgrade_store($pdo, $dpUid);
            }
        }
    }
    if ($eventId !== '') whop_mark_event($pdo, $eventId);
} catch (Throwable $e) {
    error_log('Whop webhook isleme: ' . $e->getMessage());
    if ($eventId !== '') {
        try { $pdo = db(); if ($pdo) $pdo->prepare("DELETE FROM whop_events WHERE event_id=?")->execute([$eventId]); } catch (Throwable $e2) {}
    }
}

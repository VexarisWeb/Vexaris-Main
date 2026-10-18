<?php
require_once __DIR__ . '/../core/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = db();
if (!$pdo) { http_response_code(503); echo json_encode(['ok' => false, 'msg' => 'unavailable']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false, 'msg' => 'POST only']); exit; }

$action = strtolower(trim((string)($_POST['action'] ?? '')));

if ($action === 'check') {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (!hosting_ratelimit($pdo, $ip, 'hosting-check', 30)) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'msg' => 'rate']);
        exit;
    }
    $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    $slugErr = hosting_slug_check($slug);
    if ($slugErr !== '') {
        echo json_encode(['ok' => false, 'available' => false, 'msg' => $slugErr]);
        exit;
    }
    if (hosting_reserved($slug)) {
        echo json_encode(['ok' => false, 'available' => false, 'msg' => 'reserved']);
        exit;
    }
    try {
        $st = $pdo->prepare("SELECT id FROM licenses WHERE allowed_domains LIKE ? LIMIT 1");
        $st->execute(['%"' . $slug . '.vexaris.app"%']);
        if ($st->fetch()) {
            echo json_encode(['ok' => false, 'available' => false, 'msg' => 'taken']);
            exit;
        }
        $st = $pdo->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
        $st->execute([$slug]);
        if ($st->fetch()) {
            echo json_encode(['ok' => false, 'available' => false, 'msg' => 'taken']);
            exit;
        }
        $st = $pdo->prepare("SELECT id FROM hosting_jobs WHERE slug=? AND status IN ('pending','claimed') LIMIT 1");
        $st->execute([$slug]);
        if ($st->fetch()) {
            echo json_encode(['ok' => false, 'available' => false, 'msg' => 'taken']);
            exit;
        }
        $st = $pdo->prepare("SELECT id FROM hosted_stores WHERE slug=? LIMIT 1");
        $st->execute([$slug]);
        if ($st->fetch()) {
            echo json_encode(['ok' => false, 'available' => false, 'msg' => 'taken']);
            exit;
        }
    } catch (Exception $e) {}
    echo json_encode(['ok' => true, 'available' => true, 'domain' => $slug . '.vexaris.app']);
    exit;
}

$secret = hosting_secret($pdo);
$sig = (string)($_POST['signature'] ?? '');
$ts = (int)($_POST['timestamp'] ?? 0);
$nonce = strtolower(trim((string)($_POST['nonce'] ?? '')));
if ($secret === '' || $sig === '' || $ts <= 0 || abs(time() - $ts) > 300 || !preg_match('/^[a-f0-9]{16,64}$/', $nonce)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'forbidden']);
    exit;
}
$domain = strtolower(trim((string)($_POST['domain'] ?? '')));
$domain = preg_replace('/[^a-z0-9.\-]/', '', $domain);
$payload = $action . '|' . $domain . '|' . $ts . '|' . $nonce;
if (!hash_equals(hash_hmac('sha256', $payload, $secret), $sig)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'bad signature']);
    exit;
}
try {
    $nz = $pdo->prepare("SELECT id FROM license_nonces WHERE nonce=? LIMIT 1");
    $nz->execute([$nonce]);
    if ($nz->fetch()) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'nonce used']);
        exit;
    }
    $pdo->prepare("INSERT INTO license_nonces (nonce) VALUES (?)")->execute([$nonce]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'server error']);
    exit;
}
if ($domain === '' || strlen($domain) > 253) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'bad domain']);
    exit;
}

$plans = ['free', 'starter', 'professional', 'enterprise'];

try {
    if ($action === 'register') {
        $plan = strtolower(trim((string)($_POST['plan'] ?? 'free')));
        if (!in_array($plan, $plans, true)) $plan = 'free';
        $customer = mb_substr(trim((string)($_POST['customer'] ?? $domain)), 0, 255);
        if ($customer === '') $customer = $domain;
        $ck = $pdo->prepare("SELECT id, allowed_domains FROM licenses WHERE allowed_domains LIKE ? LIMIT 1");
        $ck->execute(['%\"' . $domain . '\"%']);
        $row = $ck->fetch();
        $features = json_encode(hosting_features($plan));
        if ($row) {
            $doms = json_decode((string)($row['allowed_domains'] ?? ''), true);
            if (!is_array($doms)) $doms = [];
            if (preg_match('/^([a-z0-9-]+)\.vexaris\.(?:net|app)$/i', $domain, $mm2)) {
                $app = strtolower($mm2[1]) . '.vexaris.app';
                if (!in_array($app, $doms, true)) $doms[] = $app;
            } elseif (!in_array($domain, $doms, true)) {
                $doms[] = $domain;
            }
            $pdo->prepare("UPDATE licenses SET customer_name=?, features=?, allowed_domains=?, status='active', suspended_until=NULL WHERE id=?")
                ->execute([$customer, $features, json_encode(array_values(array_unique($doms))), (int)$row['id']]);
            echo json_encode(['ok' => true, 'msg' => 'updated', 'license_id' => (int)$row['id']]);
            exit;
        }
        $key = bin2hex(random_bytes(24));
        $domains = [$domain];
        if (preg_match('/^([a-z0-9-]+)\.vexaris\.(?:net|app)$/i', $domain, $mm)) $domains = [strtolower($mm[1]) . '.vexaris.app'];
        $pdo->prepare("INSERT INTO licenses (customer_name, license_key, allowed_domains, status, features, suspended_until) VALUES (?,?,?,'active',?,NULL)")
            ->execute([$customer, $key, json_encode(array_values(array_unique($domains))), json_encode(hosting_features($plan))]);
        echo json_encode(['ok' => true, 'msg' => 'registered', 'license_id' => (int)$pdo->lastInsertId()]);
        exit;
    }
    if ($action === 'set-plan') {
        $plan = strtolower(trim((string)($_POST['plan'] ?? '')));
        if (!in_array($plan, $plans, true)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'bad plan']);
            exit;
        }
        $st = $pdo->prepare("SELECT id, features FROM licenses WHERE allowed_domains LIKE ? LIMIT 1");
        $st->execute(['%\"' . $domain . '\"%']);
        $row = $st->fetch();
        if (!$row) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'msg' => 'not found']);
            exit;
        }
        $pdo->prepare("UPDATE licenses SET features=? WHERE id=?")
            ->execute([json_encode(hosting_features($plan)), (int)$row['id']]);
        echo json_encode(['ok' => true, 'msg' => 'plan set']);
        exit;
    }
    if ($action === 'suspend' || $action === 'unsuspend') {
        $st = $pdo->prepare("SELECT id FROM licenses WHERE allowed_domains LIKE ? LIMIT 1");
        $st->execute(['%\"' . $domain . '\"%']);
        $row = $st->fetch();
        if (!$row) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'msg' => 'not found']);
            exit;
        }
        if ($action === 'suspend') {
            $pdo->prepare("UPDATE licenses SET status='suspended' WHERE id=?")->execute([(int)$row['id']]);
            if ((string)($_POST['notify'] ?? '') === 'abuse') {
                $st = $pdo->prepare("SELECT customer_name FROM licenses WHERE id=? LIMIT 1");
                $st->execute([(int)$row['id']]);
                $owner = (string)$st->fetchColumn();
                if ($owner !== '' && filter_var($owner, FILTER_VALIDATE_EMAIL)) {
                    $score = max(0, (int)($_POST['score'] ?? 0));
                    hosting_mail($pdo, $owner, 'Your store has been suspended — Vexaris',
                        '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#0d1b3e;border-radius:16px;overflow:hidden;">'
                        . '<div style="padding:28px;text-align:center;background:linear-gradient(135deg,#8a1a1a,#3a0d0d);color:#fff;"><h1 style="margin:0;font-size:22px;">Store suspended</h1></div>'
                        . '<div style="padding:26px;color:#e8eaf6;font-size:14px;line-height:1.7;">'
                        . '<p style="margin:0 0 12px;">Your store <strong>' . htmlspecialchars($domain) . '</strong> was automatically suspended for prohibited content (score ' . $score . ').</p>'
                        . '<p style="margin:0;color:#9aa3ac;font-size:13px;">If you believe this is a mistake, reply to this email to appeal. Contact: abuse@vexaris.net</p>'
                        . '</div></div>',
                        ['email' => 'abuse@vexaris.net', 'name' => 'Vexaris Abuse']);
                }
            }
        } else {
            $pdo->prepare("UPDATE licenses SET status='active', suspended_until=NULL WHERE id=?")->execute([(int)$row['id']]);
        }
        echo json_encode(['ok' => true, 'msg' => $action . 'd']);
        exit;
    }
    if ($action === 'jobs') {
        $st = $pdo->prepare("SELECT id, slug, plan, owner, site_name, lang, kind, owner_hash, category, logo_url FROM hosting_jobs WHERE status='pending' OR (status='claimed' AND updated_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)) ORDER BY id ASC LIMIT 5");
        $st->execute();
        $rows = $st->fetchAll();
        $ids = [];
        foreach ($rows as $r) $ids[] = (int)$r['id'];
        if ($ids) {
            $pdo->prepare("UPDATE hosting_jobs SET status='claimed', attempts=attempts+1 WHERE id IN (" . implode(',', $ids) . ")")->execute();
        }
        echo json_encode(['ok' => true, 'jobs' => $rows]);
        exit;
    }
    if ($action === 'job-done') {
        $jobId = (int)($_POST['job_id'] ?? 0);
        $ok = (string)($_POST['result'] ?? '') === 'ok';
        $msg = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 255);
        $users = max(0, (int)($_POST['users'] ?? 0));
        $revenue = max(0, (float)($_POST['revenue'] ?? 0));
        $products = max(0, (int)($_POST['products'] ?? 0));
        $currency = strtoupper(trim((string)($_POST['currency'] ?? 'TRY')));
        if (!in_array($currency, ['TRY', 'USD', 'EUR'], true)) $currency = 'TRY';
        $st = $pdo->prepare("SELECT * FROM hosting_jobs WHERE id=? LIMIT 1");
        $st->execute([$jobId]);
        $job = $st->fetch();
        if (!$job) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'msg' => 'not found']);
            exit;
        }
        $pdo->prepare("UPDATE hosting_jobs SET status=?, last_msg=? WHERE id=?")
            ->execute([$ok ? 'done' : 'failed', $msg, $jobId]);
        $jobKind = (string)($job['kind'] ?? 'provision');
        $jobOwner = (string)($job['owner'] ?? '');
        $jobSlug = (string)($job['slug'] ?? '');
        $jobPlan = (string)($job['plan'] ?? 'free');
        if (!$ok && $jobKind === 'domain') {
            try {
                $pdo->prepare("UPDATE hosted_stores SET custom_status='failed' WHERE slug=?")->execute([$jobSlug]);
            } catch (Exception $e2) {}
        }
        if ($ok && $jobKind === 'delete') {
            $delCustom = '';
            try {
                $cs = $pdo->prepare("SELECT custom_domain FROM hosted_stores WHERE slug=? LIMIT 1");
                $cs->execute([$jobSlug]);
                $delCustom = strtolower(trim((string)($cs->fetchColumn() ?: '')));
            } catch (Exception $e2) {}
            try {
                $pdo->prepare("DELETE FROM hosted_stores WHERE slug=?")->execute([$jobSlug]);
            } catch (Exception $e2) {}
            try {
                $delSlug = strtolower($jobSlug);
                $delDoms = [$delSlug . '.vexaris.app', $delSlug . '.vexaris.net'];
                if ($delCustom !== '' && !in_array($delCustom, $delDoms, true)) $delDoms[] = $delCustom;
                $ls = $pdo->prepare("SELECT id, allowed_domains FROM licenses WHERE allowed_domains LIKE ? OR allowed_domains LIKE ?");
                $ls->execute(['%"' . $delDoms[0] . '"%', '%"' . $delDoms[1] . '"%']);
                foreach ($ls->fetchAll() as $lic) {
                    $domList = json_decode((string)$lic['allowed_domains'], true);
                    if (!is_array($domList)) $domList = [];
                    $keptDoms = [];
                    foreach ($domList as $dd) {
                        if (in_array(strtolower(trim((string)$dd)), $delDoms, true)) continue;
                        $keptDoms[] = (string)$dd;
                    }
                    if (!$keptDoms) {
                        $pdo->prepare("DELETE FROM licenses WHERE id=?")->execute([(int)$lic['id']]);
                    } else {
                        $pdo->prepare("UPDATE licenses SET allowed_domains=? WHERE id=?")->execute([json_encode($keptDoms, JSON_UNESCAPED_UNICODE), (int)$lic['id']]);
                    }
                }
            } catch (Exception $e2) {}
            if ($jobOwner !== '' && filter_var($jobOwner, FILTER_VALIDATE_EMAIL)) {
                hosting_mail($pdo, $jobOwner, 'Your store has been deleted — Vexaris', hosting_deleted_html($jobSlug));
            }
        } elseif ($ok) {
            $averDone = preg_replace('/[^A-Za-z0-9.\-+]/', '', substr((string)($_POST['app_ver'] ?? ''), 0, 30));
            try {
                $pdo->prepare("UPDATE hosted_stores SET app_version=? WHERE slug=?")->execute([$averDone, $jobSlug]);
            } catch (Exception $e2) {}
            if ($jobKind === 'domain') {
                $ddom = strtolower(trim((string)($job['site_name'] ?? '')));
                try {
                    if ($ddom === '') {
                        $pdo->prepare("UPDATE hosted_stores SET custom_domain='', custom_status='' WHERE slug=?")->execute([$jobSlug]);
                    } else {
                        $pdo->prepare("UPDATE hosted_stores SET custom_domain=?, custom_status='active' WHERE slug=?")->execute([$ddom, $jobSlug]);
                    }
                } catch (Exception $e2) {}
                echo json_encode(['ok' => true, 'msg' => 'recorded']);
                exit;
            }
            if ($jobKind !== 'provision') {
                echo json_encode(['ok' => true, 'msg' => 'recorded']);
                exit;
            }
            $jsite = mb_substr(trim((string)($job['site_name'] ?? '')), 0, 80);
            $jlang = strtolower(trim((string)($job['lang'] ?? 'en')));
            if ($jlang !== 'tr' && $jlang !== 'en') { $jlang = 'en'; }
            $jcat = strtolower(trim((string)($job['category'] ?? 'other')));
            if (!in_array($jcat, ['epin', 'ebook', 'design', 'software', 'other'], true)) { $jcat = 'other'; }
            $pdo->prepare("INSERT INTO hosted_stores (user_id, slug, domain, plan, site_name, owner, lang, category, users, revenue, products, currency, status, reported_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'active',NOW()) ON DUPLICATE KEY UPDATE domain=VALUES(domain), plan=VALUES(plan), site_name=VALUES(site_name), owner=VALUES(owner), lang=VALUES(lang), category=VALUES(category), users=VALUES(users), revenue=VALUES(revenue), products=VALUES(products), currency=VALUES(currency), status='active', reported_at=NOW()")
                ->execute([(int)($job['user_id'] ?? 0), $jobSlug, $jobSlug . '.vexaris.app', $jobPlan, $jsite, $jobOwner, $jlang, $jcat, $users, $revenue, $products, $currency]);
            if ($jobOwner !== '' && filter_var($jobOwner, FILTER_VALIDATE_EMAIL)) {
                $amode = (string)($_POST['admin_mode'] ?? '');
                if ($amode !== 'hash' && $amode !== 'generated') $amode = 'hash';
                $apass = mb_substr(trim((string)($_POST['admin_pass'] ?? '')), 0, 80);
                if ($amode === 'generated' && (strlen($apass) < 8 || $apass === 'VEXARIS-PASS')) $apass = '';
                try {
                    hosting_mail($pdo, $jobOwner, 'Your store is ready — Vexaris', hosting_created_html($jobSlug, $jobPlan, $amode, $apass));
                } catch (Exception $e2) {}
            }
        }
        echo json_encode(['ok' => true, 'msg' => 'recorded']);
        exit;
    }
    if ($action === 'license-domain') {
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim((string)($_POST['slug'] ?? ''))));
        $dom = strtolower(trim((string)($_POST['custom'] ?? '')));
        $op = strtolower(trim((string)($_POST['op'] ?? 'add')));
        if ($slug === '' || ($dom === '' && $op === 'add')) {
            echo json_encode(['ok' => false, 'msg' => 'bad params']);
            exit;
        }
        if ($dom !== '' && !filter_var($dom, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            echo json_encode(['ok' => false, 'msg' => 'bad domain']);
            exit;
        }
        try {
            $st = $pdo->prepare("SELECT id, allowed_domains FROM licenses WHERE allowed_domains LIKE ? LIMIT 1");
            $st->execute(['%"' . $slug . '.vexaris.app"%']);
            $lic = $st->fetch();
            if (!$lic) {
                echo json_encode(['ok' => false, 'msg' => 'license not found']);
                exit;
            }
            $domains = json_decode((string)($lic['allowed_domains'] ?? ''), true);
            if (!is_array($domains)) $domains = [];
            if ($op === 'remove') {
                if ($dom === '') {
                    echo json_encode(['ok' => false, 'msg' => 'bad params']);
                    exit;
                }
                $domains = array_values(array_filter($domains, fn($x) => strtolower(trim((string)$x)) !== $dom));
            } else {
                if (!in_array($dom, $domains, true)) {
                    if (count($domains) >= 10) {
                        echo json_encode(['ok' => false, 'msg' => 'domain limit']);
                        exit;
                    }
                    $domains[] = $dom;
                }
            }
            $pdo->prepare("UPDATE licenses SET allowed_domains=? WHERE id=?")->execute([json_encode(array_values($domains)), (int)$lic['id']]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'server error']);
            exit;
        }
        echo json_encode(['ok' => true, 'msg' => 'license updated']);
        exit;
    }
    if ($action === 'domain-revoked') {
        $slug = preg_replace('/[^a-z0-9\-.]/', '', strtolower(trim((string)($_POST['slug'] ?? ($_POST['domain'] ?? '')))));
        $slug = preg_replace('/\.vexaris\.net$/', '', $slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        if ($slug === '') {
            echo json_encode(['ok' => false, 'msg' => 'bad slug']);
            exit;
        }
        try {
            $pdo->prepare("UPDATE hosted_stores SET custom_status='revoked' WHERE slug=?")->execute([$slug]);
        } catch (Exception $e) {}
        echo json_encode(['ok' => true, 'msg' => 'recorded']);
        exit;
    }
    if ($action === 'abuse') {
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim((string)($_POST['slug'] ?? ''))));
        $score = max(0, (int)($_POST['score'] ?? 0));
        $detail = mb_substr(trim((string)($_POST['detail'] ?? '')), 0, 255);
        if ($slug === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'bad slug']);
            exit;
        }
        try {
            $pdo->prepare("UPDATE hosted_stores SET abuse_score=?, abuse_detail=? WHERE slug=?")->execute([$score, $detail, $slug]);
        } catch (Exception $e) {}
        echo json_encode(['ok' => true, 'msg' => 'recorded']);
        exit;
    }
    if ($action === 'ban-user') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'bad email']);
            exit;
        }
        try {
            $st = $pdo->prepare("UPDATE users SET ban_reason='abuse' WHERE email=? AND ban_reason=''");
            $st->execute([$email]);
            echo json_encode(['ok' => true, 'msg' => 'banned', 'count' => $st->rowCount()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => 'server error']);
        }
        exit;
    }
    if ($action === 'report') {
        $list = json_decode((string)($_POST['stores'] ?? '[]'), true);
        if (!is_array($list)) $list = [];
        $n = 0;
        $up = $pdo->prepare("INSERT INTO hosted_stores (slug, domain, plan, owner, users, revenue, products, currency, traffic_bytes, app_version, status, reported_at, traffic_updated) VALUES (?,?,?,?,?,?,?,?,?,?, 'active',NOW(),NOW()) ON DUPLICATE KEY UPDATE plan=VALUES(plan), owner=VALUES(owner), users=VALUES(users), revenue=VALUES(revenue), products=VALUES(products), currency=VALUES(currency), traffic_bytes=VALUES(traffic_bytes), app_version=VALUES(app_version), status='active', reported_at=NOW(), traffic_updated=NOW()");
        $upDay = $pdo->prepare("INSERT INTO store_traffic_daily (slug, day, bytes) VALUES (?,?,?) ON DUPLICATE KEY UPDATE bytes=VALUES(bytes)");
        foreach (array_slice($list, 0, 200) as $s) {
            if (!is_array($s)) continue;
            $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($s['slug'] ?? '')));
            if ($slug === '') continue;
            $plan = strtolower((string)($s['plan'] ?? 'free'));
            if (!in_array($plan, $plans, true)) $plan = 'free';
            $scur = strtoupper(trim((string)($s['currency'] ?? 'TRY')));
            if (!in_array($scur, ['TRY', 'USD', 'EUR'], true)) $scur = 'TRY';
            $tmon = max(0, (int)($s['traf_month'] ?? 0));
            $tday = max(0, (int)($s['traf_today'] ?? 0));
            $aver = preg_replace('/[^A-Za-z0-9.\-+]/', '', substr((string)($s['app_ver'] ?? ''), 0, 30));
            $up->execute([$slug, $slug . '.vexaris.app', $plan, mb_substr((string)($s['owner'] ?? ''), 0, 190), max(0, (int)($s['users'] ?? 0)), max(0, (float)($s['revenue'] ?? 0)), max(0, (int)($s['products'] ?? 0)), $scur, $tmon, $aver]);
            try { $upDay->execute([$slug, date('Y-m-d'), $tday]); } catch (Exception $e2) {}
            $n++;
        }
        try { $pdo->exec("DELETE FROM store_traffic_daily WHERE day < DATE_SUB(CURDATE(), INTERVAL 35 DAY)"); } catch (Exception $e2) {}
        echo json_encode(['ok' => true, 'msg' => 'reported', 'count' => $n]);
        exit;
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'bad action']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'server error']);
}

function hosting_secret(?PDO $pdo): string {
    try {
        $st = $pdo->prepare("SELECT `value` FROM settings WHERE `key`='hosting_api_secret' LIMIT 1");
        $st->execute();
        $v = trim((string)($st->fetchColumn() ?? ''));
        return $v;
    } catch (Exception $e) { return ''; }
}

function hosting_features(string $plan): array {
    $f = [
        'core' => true, 'theme_azure' => true, 'theme_ember' => true,
        'tickets' => true, 'mail_system' => true, 'admin_panel' => true,
        'seo' => true, 'announcements' => true, 'coupons' => true,
        'custom_pages' => true, 'references' => true, 'reviews' => true,
        'discord_integration' => true, 'roles_permissions' => true,
        'updates' => true,
    ];
    $f['plan'] = $plan;
    return $f;
}

function hosting_mail(?PDO $pdo, string $to, string $subject, string $html, array $from = []): void {
    if (!$pdo || $to === '') return;
    try {
        require_once __DIR__ . '/../core/mail.php';
        if (function_exists('mail_send')) mail_send($pdo, $to, $subject, $html, [], $from);
    } catch (Exception $e) {}
}

function hosting_created_html(string $slug, string $plan, string $mode = 'hash', string $pass = ''): string {
    $url = 'https://' . $slug . '.vexaris.app';
    $login = $url . '/login';
    $cred = $mode === 'generated' && $pass !== ''
        ? '<p style="margin:0 0 6px;">Store admin login:</p><p style="margin:0 0 18px;background:#131f45;border-radius:10px;padding:12px 14px;">Username: <strong>admin</strong><br>Password: <strong>' . htmlspecialchars($pass) . '</strong></p>'
        : '<p style="margin:0 0 18px;">Store admin login — username <strong>admin</strong>, password is your Vexaris password.</p>';
    return '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#0d1b3e;border-radius:16px;overflow:hidden;">'
        . '<div style="padding:28px;text-align:center;background:linear-gradient(135deg,#ff7a1a,#ff3c11);color:#fff;"><h1 style="margin:0;font-size:22px;">Your store is ready!</h1></div>'
        . '<div style="padding:26px;color:#e8eaf6;font-size:14px;line-height:1.7;">'
        . '<p style="margin:0 0 12px;">Your <strong>' . htmlspecialchars(ucfirst($plan)) . '</strong> store is live:</p>'
        . '<p style="margin:0 0 18px;"><a href="' . htmlspecialchars($url) . '" style="color:#8cb8ff;font-weight:700;">' . htmlspecialchars($slug . '.vexaris.app') . '</a></p>'
        . $cred
        . '<p style="margin:0 0 18px;"><a href="' . htmlspecialchars($login) . '" style="display:inline-block;background:#ff7a1a;color:#fff;font-weight:700;padding:10px 22px;border-radius:10px;text-decoration:none;">Open Store Login</a></p>'
        . '<p style="margin:0;color:#9aa3ac;font-size:13px;">Manage it anytime from your dashboard under My Stores.</p>'
        . '</div></div>';
}

function hosting_deleted_html(string $slug): string {
    return '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#0d1b3e;border-radius:16px;overflow:hidden;">'
        . '<div style="padding:28px;text-align:center;background:linear-gradient(135deg,#3a4356,#1c2333);color:#fff;"><h1 style="margin:0;font-size:22px;">Store deleted</h1></div>'
        . '<div style="padding:26px;color:#e8eaf6;font-size:14px;line-height:1.7;">'
        . '<p style="margin:0 0 12px;">Your store <strong>' . htmlspecialchars($slug . '.vexaris.app') . '</strong> and all its data have been permanently deleted.</p>'
        . '<p style="margin:0;color:#9aa3ac;font-size:13px;">You can create a new free store anytime from your dashboard.</p>'
        . '</div></div>';
}

function hosting_ratelimit(?PDO $pdo, string $ip, string $scope, int $limit): bool {
    if (!$pdo) return false;
    try {
        $pdo->prepare("DELETE FROM api_rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)")->execute();
        $st = $pdo->prepare("SELECT COUNT(*) FROM api_rate_limits WHERE ip=? AND scope=? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
        $st->execute([$ip, $scope]);
        if ((int)$st->fetchColumn() >= $limit) return false;
        $pdo->prepare("INSERT INTO api_rate_limits (ip, scope) VALUES (?,?)")->execute([$ip, $scope]);
        return true;
    } catch (Exception $e) { return true; }
}

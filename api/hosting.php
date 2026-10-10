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
    if (strlen($slug) < 3 || strlen($slug) > 40) {
        echo json_encode(['ok' => false, 'available' => false, 'msg' => 'length']);
        exit;
    }
    if (hosting_reserved($slug)) {
        echo json_encode(['ok' => false, 'available' => false, 'msg' => 'reserved']);
        exit;
    }
    try {
        $st = $pdo->prepare("SELECT id FROM licenses WHERE allowed_domains LIKE ? LIMIT 1");
        $st->execute(['%' . $slug . '.vexaris.net%']);
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
    echo json_encode(['ok' => true, 'available' => true, 'domain' => $slug . '.vexaris.net']);
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
        $ck = $pdo->prepare("SELECT id FROM licenses WHERE allowed_domains LIKE ? LIMIT 1");
        $ck->execute(['%' . $domain . '%']);
        $row = $ck->fetch();
        $features = json_encode(hosting_features($plan));
        if ($row) {
            $pdo->prepare("UPDATE licenses SET customer_name=?, features=?, status='active', suspended_until=NULL WHERE id=?")
                ->execute([$customer, $features, (int)$row['id']]);
            echo json_encode(['ok' => true, 'msg' => 'updated', 'license_id' => (int)$row['id']]);
            exit;
        }
        $key = bin2hex(random_bytes(24));
        $pdo->prepare("INSERT INTO licenses (customer_name, license_key, allowed_domains, status, features, suspended_until) VALUES (?,?,?,'active',?,NULL)")
            ->execute([$customer, $key, json_encode([$domain]), json_encode(hosting_features($plan))]);
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
        $st->execute(['%' . $domain . '%']);
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
        $st->execute(['%' . $domain . '%']);
        $row = $st->fetch();
        if (!$row) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'msg' => 'not found']);
            exit;
        }
        if ($action === 'suspend') {
            $pdo->prepare("UPDATE licenses SET status='suspended' WHERE id=?")->execute([(int)$row['id']]);
        } else {
            $pdo->prepare("UPDATE licenses SET status='active', suspended_until=NULL WHERE id=?")->execute([(int)$row['id']]);
        }
        echo json_encode(['ok' => true, 'msg' => $action . 'd']);
        exit;
    }
    if ($action === 'jobs') {
        $st = $pdo->prepare("SELECT id, slug, plan, owner, site_name, lang FROM hosting_jobs WHERE status='pending' OR (status='claimed' AND updated_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)) ORDER BY id ASC LIMIT 5");
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
        if ($ok) {
            $pdo->prepare("INSERT INTO hosted_stores (user_id, slug, domain, plan, owner, users, revenue, status, reported_at) VALUES (?,?,?,?,?,?,?,'active',NOW()) ON DUPLICATE KEY UPDATE domain=VALUES(domain), plan=VALUES(plan), owner=VALUES(owner), users=VALUES(users), revenue=VALUES(revenue), status='active', reported_at=NOW()")
                ->execute([(int)($job['user_id'] ?? 0), (string)$job['slug'], (string)$job['slug'] . '.vexaris.net', (string)$job['plan'], (string)$job['owner'], $users, $revenue]);
        }
        echo json_encode(['ok' => true, 'msg' => 'recorded']);
        exit;
    }
    if ($action === 'report') {
        $list = json_decode((string)($_POST['stores'] ?? '[]'), true);
        if (!is_array($list)) $list = [];
        $n = 0;
        $up = $pdo->prepare("INSERT INTO hosted_stores (slug, domain, plan, owner, users, revenue, status, reported_at) VALUES (?,?,?,?,?,?,'active',NOW()) ON DUPLICATE KEY UPDATE plan=VALUES(plan), owner=VALUES(owner), users=VALUES(users), revenue=VALUES(revenue), status='active', reported_at=NOW()");
        foreach (array_slice($list, 0, 200) as $s) {
            if (!is_array($s)) continue;
            $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($s['slug'] ?? '')));
            if ($slug === '') continue;
            $plan = strtolower((string)($s['plan'] ?? 'free'));
            if (!in_array($plan, $plans, true)) $plan = 'free';
            $up->execute([$slug, $slug . '.vexaris.net', $plan, mb_substr((string)($s['owner'] ?? ''), 0, 190), max(0, (int)($s['users'] ?? 0)), max(0, (float)($s['revenue'] ?? 0))]);
            $n++;
        }
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
        'updates' => false,
    ];
    $f['plan'] = $plan;
    return $f;
}

function hosting_reserved(string $slug): bool {
    static $r = ['admin','root','demo','www','mail','api','ftp','shop','store','support','test','info','noreply','status','blog','news','login','register','cdn','static','assets','app','panel','free','starter','professional','enterprise'];
    return in_array(strtolower($slug), $r, true);
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

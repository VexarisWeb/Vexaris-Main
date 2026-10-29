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
    } catch (Throwable $e) {}
    echo json_encode(['ok' => true, 'available' => true, 'domain' => $slug . '.vexaris.app']);
    exit;
}

$secret = hosting_secret($pdo);
/* Gecis: worker yeni surumde v2 siri kullanabilir. Iki siri da kabul edilir;
   global sirlarin biri ele gecerse bile kiraci bazli sifir gecerliligini korur. */
$secretV2 = hosting_secret_v2($pdo);
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

/* ── İMZA DOĞRULAMA ──────────────────────────────────────────────
   v1 (eski): action|domain|ts|nonce              -> is alanlari imzalanmaz
   v2 (yeni): action|domain|ts|nonce|params_hash -> is alanlari da kapsanir

   Worker makinesi (docker/hosting-worker.sh) v1 uretiyor; v2'ye gecis
   worker guncellendikten sonra zorunlu kilinabilir:
     UPDATE settings SET value='1' WHERE `key`='hosting_require_v2';
   Boylece hicbir provisioning akisi kesilmeden MAC alani genisletilir. */
$macOk = false;
$macMode = '';
$secretCandidates = [$secret];
if ($secretV2 !== '' && $secretV2 !== $secret) $secretCandidates[] = $secretV2;
// Kiracı (magaza) bazli sır: hosted_stores.api_secret
$tenantSecret = '';
if ($domain !== '') {
    try {
        $tsQ = $pdo->prepare("SELECT api_secret FROM hosted_stores WHERE slug=? LIMIT 1");
        $slugQ = preg_replace('/\.vexaris\.(net|app)$/', '', $domain);
        $tsQ->execute([$slugQ]);
        $tenantSecret = trim((string)($tsQ->fetchColumn() ?? ''));
        if ($tenantSecret !== '') $secretCandidates[] = $tenantSecret;
    } catch (Throwable $e) {}
}
$paramsHash = hosting_params_hash($action, $_POST);
foreach ($secretCandidates as $cand) {
    $pV2 = $action . '|' . $domain . '|' . $ts . '|' . $nonce . '|' . $paramsHash;
    if (hash_equals(hash_hmac('sha256', $pV2, $cand), $sig)) { $macOk = true; $macMode = 'v2'; break; }
    $pV1 = $action . '|' . $domain . '|' . $ts . '|' . $nonce;
    if (hash_equals(hash_hmac('sha256', $pV1, $cand), $sig)) { $macOk = true; $macMode = 'v1'; break; }
}
if (!$macOk) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'bad signature']);
    exit;
}
if ($macMode === 'v1') {
    // Legacy imza: worker hala v1 uretiyorsa kabul et (yeni is alanlari imzalanmamis).
    // Zorunlu kilma acik/kapali: settings.hosting_require_v2 = '1' -> v1 reddedilir.
    $requireV2 = hosting_setting($pdo, 'hosting_require_v2', '0');
    if ($requireV2 === '1') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'msg' => 'legacy signature rejected']);
        exit;
    }
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
} catch (Throwable $e) {
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
        // Lisansi sahibi kullaniciya bagla: user_effective_plan() ve lisans
        // yonetimi user_id uzerinden calisiyor (aksi halde plan 'free' kalir).
        $ownerUid = 0;
        if (filter_var($customer, FILTER_VALIDATE_EMAIL)) {
            try {
                $uq = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
                $uq->execute([$customer]);
                $ownerUid = (int)($uq->fetchColumn() ?: 0);
            } catch (Throwable $e) {}
        }
        if ($ownerUid <= 0) {
            // Magaza sahibi (hosted_stores.owner) uzerinden de dene.
            try {
                $sq = preg_replace('/\.vexaris\.(net|app)$/', '', $domain);
                $uq2 = $pdo->prepare("SELECT user_id FROM hosted_stores WHERE slug=? LIMIT 1");
                $uq2->execute([$sq]);
                $ownerUid = (int)($uq2->fetchColumn() ?: 0);
            } catch (Throwable $e) {}
        }
        $ck = $pdo->prepare("SELECT id, allowed_domains, user_id FROM licenses WHERE allowed_domains LIKE ? LIMIT 1");
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
            if ($ownerUid > 0 && (int)($row['user_id'] ?? 0) <= 0) {
                $pdo->prepare("UPDATE licenses SET user_id=? WHERE id=?")->execute([$ownerUid, (int)$row['id']]);
            }
            $pdo->prepare("UPDATE licenses SET customer_name=?, features=?, allowed_domains=?, status='active', suspended_until=NULL WHERE id=?")
                ->execute([$customer, $features, json_encode(array_values(array_unique($doms))), (int)$row['id']]);
            audit_log($pdo, $ownerUid, 'hosting_register', 'license', (string)$row['id'], $domain . ' plan=' . $plan);
            echo json_encode(['ok' => true, 'msg' => 'updated', 'license_id' => (int)$row['id']]);
            exit;
        }
        $key = bin2hex(random_bytes(24));
        $domains = [$domain];
        if (preg_match('/^([a-z0-9-]+)\.vexaris\.(?:net|app)$/i', $domain, $mm)) $domains = [strtolower($mm[1]) . '.vexaris.app'];
        $pdo->prepare("INSERT INTO licenses (customer_name, license_key, allowed_domains, status, features, suspended_until, user_id) VALUES (?,?,?,'active',?,NULL,?)")
            ->execute([$customer, $key, json_encode(array_values(array_unique($domains))), json_encode(hosting_features($plan)), $ownerUid > 0 ? $ownerUid : null]);
        $newLicId = (int)$pdo->lastInsertId();
        audit_log($pdo, $ownerUid, 'hosting_register', 'license', (string)$newLicId, $domain . ' plan=' . $plan);
        echo json_encode(['ok' => true, 'msg' => 'registered', 'license_id' => $newLicId]);
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
        // Magaza kaydi da senkron kalsin (lisans <-> store plan uyumu).
        try {
            $slugQ = preg_replace('/\.vexaris\.(net|app)$/', '', $domain);
            $pdo->prepare("UPDATE hosted_stores SET plan=? WHERE slug=?")->execute([$plan, $slugQ]);
        } catch (Throwable $e2) {}
        audit_log($pdo, 0, 'hosting_set_plan', 'license', (string)$row['id'], $domain . ' plan=' . $plan);
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
                    hosting_mail($pdo, $owner, 'Your store has been suspended | Vexaris',
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
        $rows = [];
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare("SELECT id FROM hosting_jobs WHERE status='pending' OR (status='claimed' AND updated_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)) ORDER BY id ASC LIMIT 5 FOR UPDATE");
            $st->execute();
            $ids = $st->fetchAll(PDO::FETCH_COLUMN);
            if ($ids) {
                $claim = $pdo->prepare("UPDATE hosting_jobs SET status='claimed', attempts=attempts+1, updated_at=NOW() WHERE id=? AND (status='pending' OR (status='claimed' AND updated_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)))");
                $get = $pdo->prepare("SELECT id, slug, plan, owner, site_name, lang, kind, owner_hash, category, logo_url FROM hosting_jobs WHERE id=? LIMIT 1");
                foreach ($ids as $jid) {
                    $claim->execute([$jid]);
                    if ($claim->rowCount() !== 1) continue;
                    $get->execute([$jid]);
                    $r = $get->fetch();
                    if ($r) {
                        /* Mağazaya özel AI relay sırrı işle birlikte gider (konteynere
                           VX_RELAY_SECRET olarak yazılır). Sırı olmayan eski mağazalar
                           için ilk işte üretilir (geriye uyum + güvenlik geçişi). */
                        try {
                            $rs = $pdo->prepare("SELECT id, relay_secret, api_secret FROM hosted_stores WHERE slug=? LIMIT 1");
                            $rs->execute([(string)$r['slug']]);
                            $srow = $rs->fetch();
                            $rsec = $srow ? trim((string)($srow['relay_secret'] ?? '')) : '';
                            if ($srow && $rsec === '') {
                                $rsec = bin2hex(random_bytes(32));
                                $pdo->prepare("UPDATE hosted_stores SET relay_secret=? WHERE id=?")->execute([$rsec, (int)$srow['id']]);
                            }
                            $r['relay_secret'] = $rsec;
                            /* Kiraci bazli API sirri: worker yeni surumde imzayi
                               bu sirlarla uretir (global sirl yerine). */
                            $asec = $srow ? trim((string)($srow['api_secret'] ?? '')) : '';
                            if ($srow && $asec === '') {
                                $asec = bin2hex(random_bytes(32));
                                $pdo->prepare("UPDATE hosted_stores SET api_secret=? WHERE id=?")->execute([$asec, (int)$srow['id']]);
                            }
                            $r['api_secret'] = $asec;
                        } catch (Throwable $e) { $r['relay_secret'] = ''; $r['api_secret'] = ''; }
                        $rows[] = $r;
                    }
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => 'server error']);
            exit;
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
        $jobKind = strtolower(trim((string)($job['kind'] ?? '')));
        if (!in_array($jobKind, ['provision', 'delete', 'update', 'domain', 'passwd'], true)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'bad kind']);
            exit;
        }
        $jobOwner = (string)($job['owner'] ?? '');
        $jobSlug = (string)($job['slug'] ?? '');
        $jobPlan = (string)($job['plan'] ?? 'free');
        if ($jobSlug === '' || $domain !== $jobSlug . '.vexaris.app') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'bad domain']);
            exit;
        }
        if (!$ok && $jobKind === 'domain') {
            try {
                $pdo->prepare("UPDATE hosted_stores SET custom_status='failed' WHERE slug=?")->execute([$jobSlug]);
            } catch (Throwable $e2) {}
        }
        if ($ok && $jobKind === 'delete') {
            $delCustom = '';
            $delLogo = '';
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE hosting_jobs SET status='done', last_msg=? WHERE id=?")->execute([$msg, $jobId]);
                $cs = $pdo->prepare("SELECT custom_domain, logo_url FROM hosted_stores WHERE slug=? LIMIT 1");
                $cs->execute([$jobSlug]);
                $csRow = $cs->fetch() ?: [];
                $delCustom = strtolower(trim((string)($csRow['custom_domain'] ?? '')));
                $delLogo = (string)($csRow['logo_url'] ?? '');
                $pdo->prepare("DELETE FROM hosted_stores WHERE slug=?")->execute([$jobSlug]);
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
                if ($jobOwner !== '' && filter_var($jobOwner, FILTER_VALIDATE_EMAIL)) {
                    hosting_mail($pdo, $jobOwner, 'Your store has been deleted | Vexaris', hosting_deleted_html($jobSlug));
                }
                $pdo->commit();
            } catch (Throwable $e2) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                try {
                    $pdo->prepare("UPDATE hosting_jobs SET status='pending', last_msg='cleanup failed' WHERE id=?")->execute([$jobId]);
                } catch (Throwable $e3) {}
                http_response_code(500);
                echo json_encode(['ok' => false, 'msg' => 'server error']);
                exit;
            }
            try {
                if ($delLogo !== '' && preg_match('#^https://vexaris\.net/uploads/store-logos/([A-Za-z0-9._-]+)$#', $delLogo, $lmm)) {
                    $lpath = __DIR__ . '/../uploads/store-logos/' . $lmm[1];
                    if (is_file($lpath)) @unlink($lpath);
                }
            } catch (Throwable $e2) {}
            echo json_encode(['ok' => true, 'msg' => 'recorded']);
            exit;
        }
        $pdo->prepare("UPDATE hosting_jobs SET status=?, last_msg=? WHERE id=?")
            ->execute([$ok ? 'done' : 'failed', $msg, $jobId]);
        if ($ok) {
            $averDone = preg_replace('/[^A-Za-z0-9.\-+]/', '', substr((string)($_POST['app_ver'] ?? ''), 0, 30));
            try {
                if ($averDone !== '') {
                    $pdo->prepare("UPDATE hosted_stores SET app_version=? WHERE slug=?")->execute([$averDone, $jobSlug]);
                }
            } catch (Throwable $e2) {}
            if ($jobKind === 'domain') {
                $ddom = strtolower(trim((string)($job['site_name'] ?? '')));
                try {
                    if ($ddom === '') {
                        $pdo->prepare("UPDATE hosted_stores SET custom_domain='', custom_status='' WHERE slug=?")->execute([$jobSlug]);
                    } else {
                        $pdo->prepare("UPDATE hosted_stores SET custom_domain=?, custom_status='active' WHERE slug=?")->execute([$ddom, $jobSlug]);
                    }
                } catch (Throwable $e2) {}
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
            $jlogo = (string)($job['logo_url'] ?? '');
            if ($jlogo !== '' && !preg_match('#^https://vexaris\.net/uploads/store-logos/[A-Za-z0-9._-]+$#', $jlogo)) { $jlogo = ''; }
            $pdo->prepare("INSERT INTO hosted_stores (user_id, slug, domain, plan, site_name, owner, lang, category, logo_url, users, revenue, products, currency, status, reported_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'active',NOW()) ON DUPLICATE KEY UPDATE domain=VALUES(domain), plan=VALUES(plan), site_name=VALUES(site_name), owner=VALUES(owner), lang=VALUES(lang), category=VALUES(category), logo_url=IF(VALUES(logo_url)<>'', VALUES(logo_url), logo_url), users=VALUES(users), revenue=VALUES(revenue), products=VALUES(products), currency=VALUES(currency), status='active', reported_at=NOW()")
                ->execute([(int)($job['user_id'] ?? 0), $jobSlug, $jobSlug . '.vexaris.app', $jobPlan, $jsite, $jobOwner, $jlang, $jcat, $jlogo, $users, $revenue, $products, $currency]);
            if ($jobOwner !== '' && filter_var($jobOwner, FILTER_VALIDATE_EMAIL)) {
                $amode = (string)($_POST['admin_mode'] ?? '');
                if ($amode !== 'hash' && $amode !== 'generated') $amode = 'hash';
                $apass = mb_substr(trim((string)($_POST['admin_pass'] ?? '')), 0, 80);
                if ($amode === 'generated' && (strlen($apass) < 8 || $apass === 'VEXARIS-PASS')) $apass = '';
                try {
                    hosting_mail($pdo, $jobOwner, 'Your store is ready | Vexaris', hosting_created_html($jobSlug, $jobPlan, $amode, $apass));
                } catch (Throwable $e2) {}
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
        } catch (Throwable $e) {
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
        } catch (Throwable $e) {}
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
        } catch (Throwable $e) {}
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
            $st = $pdo->prepare("SELECT id FROM users WHERE email=? AND ban_reason='' LIMIT 1");
            $st->execute([$email]);
            $banId = (int)$st->fetchColumn();
            if ($banId <= 0) {
                echo json_encode(['ok' => true, 'msg' => 'banned', 'count' => 0]);
                exit;
            }
            $pdo->beginTransaction();
            // is_active de 0 olmali: aksi halde kullanici tekrar giris yapabilir
            // (login kontrolu ban_reason dolu + is_active=0 birlikte sartina bagli).
            $pdo->prepare("UPDATE users SET is_active=0, ban_reason='abuse' WHERE id=?")->execute([$banId]);
            $pdo->prepare("UPDATE user_subscriptions SET status='cancelled' WHERE user_id=?")->execute([$banId]);
            $pdo->commit();
            // Oturumlar ve remember-me tokenlari dusurulur.
            try {
                if (function_exists('sessions_kill_all')) {
                    sessions_kill_all($pdo, $banId, false);
                } else {
                    $pdo->prepare("UPDATE users SET session_version = session_version + 1 WHERE id = ?")->execute([$banId]);
                    $pdo->prepare("UPDATE user_sessions SET revoked = 1 WHERE user_id = ?")->execute([$banId]);
                    $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$banId]);
                }
            } catch (Throwable $e2) {}
            audit_log($pdo, $banId, 'hosting_ban_user', 'users', (string)$banId, 'abuse');
            echo json_encode(['ok' => true, 'msg' => 'banned', 'count' => 1]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => 'server error']);
        }
        exit;
    }
    if ($action === 'report') {
        $list = json_decode((string)($_POST['stores'] ?? '[]'), true);
        if (!is_array($list)) $list = [];
        $n = 0;
        $up = $pdo->prepare("INSERT INTO hosted_stores (slug, domain, users, revenue, products, currency, traffic_bytes, app_version, status, reported_at, traffic_updated) VALUES (?,?,?,?,?,?,?,?,'active',NOW(),NOW()) ON DUPLICATE KEY UPDATE users=VALUES(users), revenue=VALUES(revenue), products=VALUES(products), currency=VALUES(currency), traffic_bytes=VALUES(traffic_bytes), app_version=VALUES(app_version), status='active', reported_at=NOW(), traffic_updated=NOW()");
        $upDay = $pdo->prepare("INSERT INTO store_traffic_daily (slug, day, bytes) VALUES (?,?,?) ON DUPLICATE KEY UPDATE bytes=VALUES(bytes)");
        foreach (array_slice($list, 0, 200) as $s) {
            if (!is_array($s)) continue;
            $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($s['slug'] ?? '')));
            if ($slug === '') continue;
            $scur = strtoupper(trim((string)($s['currency'] ?? 'TRY')));
            if (!in_array($scur, ['TRY', 'USD', 'EUR'], true)) $scur = 'TRY';
            $tmon = max(0, (int)($s['traf_month'] ?? 0));
            $tday = max(0, (int)($s['traf_today'] ?? 0));
            $aver = preg_replace('/[^A-Za-z0-9.\-+]/', '', substr((string)($s['app_ver'] ?? ''), 0, 30));
            $up->execute([$slug, $slug . '.vexaris.app', max(0, (int)($s['users'] ?? 0)), max(0, (float)($s['revenue'] ?? 0)), max(0, (int)($s['products'] ?? 0)), $scur, $tmon, $aver]);
            try { $upDay->execute([$slug, date('Y-m-d'), $tday]); } catch (Throwable $e2) {}
            $n++;
        }
        try { $pdo->exec("DELETE FROM store_traffic_daily WHERE day < DATE_SUB(CURDATE(), INTERVAL 35 DAY)"); } catch (Throwable $e2) {}
        echo json_encode(['ok' => true, 'msg' => 'reported', 'count' => $n]);
        exit;
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'bad action']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'server error']);
}

function hosting_secret(?PDO $pdo): string {
    try {
        $st = $pdo->prepare("SELECT `value` FROM settings WHERE `key`='hosting_api_secret' LIMIT 1");
        $st->execute();
        $v = trim((string)($st->fetchColumn() ?? ''));
        return $v;
    } catch (Throwable $e) { return ''; }
}

/* Tek bir ayar degeri okur (settings). */
function hosting_setting(?PDO $pdo, string $key, string $default = ''): string {
    try {
        $st = $pdo->prepare("SELECT `value` FROM settings WHERE `key`=? LIMIT 1");
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return $v === false ? $default : (string)$v;
    } catch (Throwable $e) { return $default; }
}

/* v2 global API sirri: settings.hosting_api_secret_v2 (yoksa env dosyasi). */
function hosting_secret_v2(?PDO $pdo): string {
    $v = trim(hosting_setting($pdo, 'hosting_api_secret_v2', ''));
    if ($v !== '') return $v;
    if (function_exists('vx_secret')) return trim((string)vx_secret('VX_HOSTING_SECRET_V2', ''));
    return '';
}

/* v2 MAC icin is alanlarinin kanonik ozeti. Alanlar action'a gore sabittir;
   boylece "plan" veya "revenue" gibi is alanlari imzaya dahil edilir. */
function hosting_params_hash(string $action, array $post): string {
    $map = [
        'register'       => ['plan', 'customer'],
        'set-plan'       => ['plan'],
        'suspend'        => ['notify', 'score'],
        'unsuspend'      => [],
        'jobs'           => [],
        'job-done'       => ['job_id', 'result', 'message', 'users', 'revenue', 'products', 'currency', 'app_ver', 'admin_mode', 'admin_pass'],
        'license-domain' => ['slug', 'custom', 'op'],
        'domain-revoked' => ['slug'],
        'abuse'          => ['slug', 'score', 'detail'],
        'ban-user'       => ['email'],
        'report'         => ['stores'],
    ];
    $fields = $map[$action] ?? [];
    $parts = [];
    foreach ($fields as $f) {
        $parts[] = $f . '=' . (string)($post[$f] ?? '');
    }
    return hash('sha256', implode('|', $parts));
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
    } catch (Throwable $e) {}
}

function hosting_created_html(string $slug, string $plan, string $mode = 'hash', string $pass = ''): string {
    $url = 'https://' . $slug . '.vexaris.app';
    $login = $url . '/login';
    $cred = $mode === 'generated' && $pass !== ''
        ? '<p style="margin:0 0 6px;">Store admin login:</p><p style="margin:0 0 18px;background:#131f45;border-radius:10px;padding:12px 14px;">Username: <strong>admin</strong><br>Password: <strong>' . htmlspecialchars($pass) . '</strong></p>'
        : '<p style="margin:0 0 18px;">Store admin login: username <strong>admin</strong>, password is your Vexaris password.</p>';
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
    } catch (Throwable $e) { return true; }
}

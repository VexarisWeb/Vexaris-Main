<?php

function risk_mode(): string {
    return multi_mode();
}

function risk_group_members(\PDO $pdo, string $gkey): array {
    $out = [];
    try {
        $st = $pdo->prepare("SELECT u.id, u.username, u.email, u.reg_ip, u.is_active, u.created_at
            FROM multi_groups g JOIN users u ON FIND_IN_SET(u.id, g.member_ids)
            WHERE g.gkey = ? ORDER BY u.id ASC");
        $st->execute([$gkey]);
        foreach ($st->fetchAll() as $r) $out[] = $r;
    } catch (Throwable $e) {}
    return $out;
}

function risk_device_groups(\PDO $pdo, int $uid): array {
    $out = [];
    if ($uid <= 0) return $out;
    try {
        $st = $pdo->prepare("SELECT gkey, gtype, reason, status, active_count FROM multi_groups WHERE FIND_IN_SET(?, member_ids) AND gtype = 'device'");
        $st->execute([$uid]);
        foreach ($st->fetchAll() as $r) $out[] = $r;
    } catch (Throwable $e) {}
    return $out;
}

function risk_decide(\PDO $pdo, int $uid, string $action = 'register'): array {
    $res = ['decision' => 'clean', 'reasons' => [], 'ban_ids' => [], 'groups' => [], 'exempt' => false];
    if ($uid <= 0 || !multi_enabled()) return $res;
    $mode = risk_mode();
    if ($mode === 'off' || $mode === 'shadow' || $mode === 'notify') {
        $res['decision'] = 'watch';
        return $res;
    }
    if (multi_is_exempt($pdo, $uid)) {
        $res['exempt'] = true;
        $res['decision'] = 'exempt';
        return $res;
    }
    try {
        multi_ensure_tables($pdo);
        multi_group_sync($pdo, $uid);
        $thr = multi_ban_threshold();
        foreach (risk_device_groups($pdo, $uid) as $g) {
            if ($g['status'] === 'dismissed') continue;
            $members = risk_group_members($pdo, $g['gkey']);
            $active = [];
            $ips = [];
            foreach ($members as $m) {
                if ((int)$m['is_active'] === 1) $active[] = $m;
                $ip = trim((string)$m['reg_ip']);
                if ($ip !== '') $ips[$ip] = true;
            }
            $n = count($active);
            if ($n < 2) continue;
            $res['groups'][] = $g['gkey'];
            $sameIp = count($ips) === 1;
            if (!$sameIp) {
                $res['ban_ids'] = array_merge($res['ban_ids'], array_map(fn($m) => (int)$m['id'], $active));
                $res['reasons'][] = 'ip_change';
                $res['decision'] = 'ban';
            } elseif ($n >= $thr) {
                $res['ban_ids'] = array_merge($res['ban_ids'], array_map(fn($m) => (int)$m['id'], $active));
                $res['reasons'][] = 'device_ip_' . $n;
                $res['decision'] = 'ban';
            } elseif ($res['decision'] === 'clean') {
                $res['decision'] = 'watch';
            }
        }
        $res['ban_ids'] = array_values(array_unique($res['ban_ids']));
        foreach ($res['groups'] as $gk) {
            $reason = implode(',', array_unique($res['reasons']));
            risk_event_log($pdo, $uid, $gk,
                $action,
                $res['decision'] === 'ban' ? 'ban' : 'watch',
                $reason,
                multi_client_vid(), multi_device_id(), client_ip());
        }
    } catch (Throwable $e) {}
    return $res;
}

function risk_apply(\PDO $pdo, array $dec): array {
    $banned = [];
    if (($dec['decision'] ?? '') !== 'ban') return $banned;
    $gkey = (string)($dec['groups'][0] ?? '');
    foreach ((array)($dec['ban_ids'] ?? []) as $bid) {
        $bid = (int)$bid;
        if ($bid <= 0) continue;
        if (multi_is_exempt($pdo, $bid)) {
            risk_event_log($pdo, $bid, $gkey, 'ban_skip', 'exempt', 'exempt_user', '', '', client_ip());
            continue;
        }
        try {
            $st = $pdo->prepare("UPDATE users SET is_active = 0, ban_reason = 'multi' WHERE id = ? AND is_active = 1");
            $st->execute([$bid]);
            if ($st->rowCount() < 1) continue;
            if (function_exists('sessions_kill_all')) {
                sessions_kill_all($pdo, $bid, false);
            } else {
                $pdo->prepare("UPDATE users SET session_version = session_version + 1 WHERE id = ?")->execute([$bid]);
                $pdo->prepare("UPDATE user_sessions SET revoked = 1 WHERE user_id = ?")->execute([$bid]);
                $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$bid]);
            }
            $banned[] = $bid;
            $detail = implode(',', array_unique((array)($dec['reasons'] ?? [])));
            risk_event_log($pdo, $bid, $gkey, 'ban', 'banned', $detail, '', '', client_ip());
            multi_flag($pdo, $bid, 0, 'multi_ban', 'Multi-hesap bani (' . $detail . ')');
            try {
                notify_user($bid, 'rejected', 'Account suspended — multiple accounts',
                    'Your account was suspended for violating the one-account-per-person policy. If you believe this is a mistake, contact info@vexaris.net to appeal.');
            } catch (Throwable $e2) {}
        } catch (Throwable $e) {}
    }
    if ($banned && $gkey !== '') {
        try { $pdo->prepare("UPDATE multi_groups SET status = 'banned' WHERE gkey = ?")->execute([$gkey]); } catch (Throwable $e) {}
    }
    return $banned;
}

function risk_guard(\PDO $pdo, int $uid, string $action = 'register'): string {
    if ($uid <= 0) return 'clean';
    $dec = risk_decide($pdo, $uid, $action);
    if ($dec['decision'] !== 'ban') return $dec['decision'];
    $banned = risk_apply($pdo, $dec);
    return in_array($uid, $banned, true) ? 'banned' : 'main';
}

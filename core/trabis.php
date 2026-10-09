<?php
// TRABIS (.tr ailesi) WHOIS istemcisi — port 43, RDAP yok.
// Çıktıyı RDAP-benzeri diziye normalize eder (arayüz aynen çalışır).

function trabis_query(string $domain): ?string {
    $domain = strtolower(trim($domain));
    if ($domain === '' || strlen($domain) > 253) return null;
    $fp = @fsockopen('whois.trabis.gov.tr', 43, $errno, $errstr, 8);
    if (!$fp) return null;
    stream_set_timeout($fp, 8);
    @fwrite($fp, $domain . "\r\n");
    $out = '';
    while (!feof($fp)) {
        $chunk = @fread($fp, 8192);
        if ($chunk === false || $chunk === '') break;
        $out .= $chunk;
        if (strlen($out) > 100000) break;
    }
    @fclose($fp);
    $out = trim($out);
    return $out === '' ? null : $out;
}

function trabis_parse_date(?string $s): ?string {
    // "2026-Aug-06." -> "2026-08-06"
    if ($s === null) return null;
    $s = trim($s, " \t.");
    if (!preg_match('/^(\d{4})-([A-Za-z]{3})-(\d{1,2})$/', $s, $m)) return null;
    $map = ['jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'may'=>5,'jun'=>6,'jul'=>7,'aug'=>8,'sep'=>9,'oct'=>10,'nov'=>11,'dec'=>12];
    $mo = $map[strtolower($m[2])] ?? 0;
    if ($mo < 1) return null;
    return sprintf('%04d-%02d-%02d', (int)$m[1], $mo, (int)$m[3]);
}

function trabis_parse(string $raw, string $domain): ?array {
    if (stripos($raw, 'No match found for') !== false) return null; // kayıtlı değil
    $sections = [];
    $cur = '_head';
    foreach (preg_split('/\r?\n/', $raw) as $line) {
        if (preg_match('/^\*\*\s*(.+?)\s*:?\s*$/', trim($line), $m)) { $cur = strtolower(trim($m[1])); $sections[$cur] = []; continue; }
        if (!isset($sections[$cur])) $sections[$cur] = [];
        $sections[$cur][] = $line;
    }
    $kv = function (array $lines): array {
        $out = []; $last = null;
        foreach ($lines as $ln) {
            if (preg_match('/^([^:]+?)\s*:+\s*(.*)$/', $ln, $m)) {
                $last = trim(trim($m[1]), '.');
                $out[$last] = trim($m[2]);
            } elseif ($last !== null && trim($ln) !== '') {
                $out[$last] .= ' ' . trim($ln); // çok satırlı adres devamı
            }
        }
        return $out;
    };
    $head = $kv($sections['_head'] ?? []);
    $dname = $head['Domain Name'] ?? $domain;
    $status = [];
    if (!empty($head['Domain Status'])) $status[] = $head['Domain Status'];
    $reg = $kv($sections['registrant'] ?? []);
    $registrar = $kv($sections['registrar'] ?? []);
    $admin = $kv($sections['administrative contact'] ?? []);
    $tech = $kv($sections['technical contact'] ?? []);
    $add = $kv($sections['additional info'] ?? []);
    $who = $kv($sections['whois server'] ?? []);
    $ns = [];
    foreach ($sections['domain servers'] ?? [] as $ln) {
        $ln = trim($ln);
        if ($ln === '') continue;
        $parts = preg_split('/\s+/', $ln);
        $entry = ['ldhName' => $parts[0]];
        $ips = array_values(array_filter(array_slice($parts, 1), fn($p) => filter_var($p, FILTER_VALIDATE_IP)));
        if ($ips) $entry['ipAddresses'] = ['v4' => array_values(array_filter($ips, fn($p) => filter_var($p, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))), 'v6' => array_values(array_filter($ips, fn($p) => filter_var($p, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)))];
        $ns[] = $entry;
    }
    $mkEnt = function (string $role, ?string $name, ?string $org = null, ?string $email = null, ?string $tel = null): array {
        $rows = [['version', [], 'text', '4.0']];
        if ($name !== null && $name !== '') $rows[] = ['fn', [], 'text', $name];
        if ($org !== null && $org !== '' && $org !== $name) $rows[] = ['org', [], 'text', $org];
        if ($email !== null && $email !== '') $rows[] = ['email', [], 'text', $email];
        if ($tel !== null && $tel !== '') $rows[] = ['tel', [], 'text', $tel];
        return ['objectClassName' => 'entity', 'roles' => [$role], 'vcardArray' => ['vcard', $rows]];
    };
    $regName = null;
    foreach ($sections['registrant'] ?? [] as $ln) {
        $ln = trim($ln);
        if ($ln !== '' && $ln[0] !== '*') { $regName = $ln; break; }
    }
    $entities = [$mkEnt('registrant', $regName)];
    $entities[] = $mkEnt('registrar', $registrar['Organization Name'] ?? null, null, null, $registrar['Phone'] ?? null);
    if (!empty($registrar['NIC Handle'])) {
        $entities[count($entities) - 1]['publicIds'] = [['type' => 'NIC Handle', 'identifier' => $registrar['NIC Handle']]];
    }
    $firstLine = function (array $kvArr) {
        foreach ($kvArr as $v) { if (trim((string)$v) !== '' && trim((string)$v) !== '-') return trim((string)$v); }
        return null;
    };
    if ($firstLine($admin) || ($sections['administrative contact'] ?? null)) {
        $an = null;
        foreach ($sections['administrative contact'] ?? [] as $ln) { $ln = trim($ln); if ($ln !== '') { $an = $ln; break; } }
        if ($an) $entities[] = $mkEnt('admin', $an);
    }
    if ($sections['technical contact'] ?? null) {
        $tn = null;
        foreach ($sections['technical contact'] ?? [] as $ln) { $ln = trim($ln); if ($ln !== '') { $tn = $ln; break; } }
        if ($tn) $entities[] = $mkEnt('tech', $tn);
    }
    $events = [];
    if (!empty($add['Created on'])) { $d = trabis_parse_date($add['Created on']); if ($d) $events[] = ['eventAction' => 'registration', 'eventDate' => $d]; }
    if (!empty($add['Expires on'])) { $d = trabis_parse_date($add['Expires on']); if ($d) $events[] = ['eventAction' => 'expiration', 'eventDate' => $d]; }
    if (!empty($who['Last Update Time'])) {
        try { $dt = new DateTime($who['Last Update Time']); $events[] = ['eventAction' => 'last changed', 'eventDate' => $dt->format('c')]; } catch (Throwable $e) {}
    }
    $out = [
        'objectClassName' => 'domain',
        'ldhName' => $dname,
        'status' => $status ?: ['Active'],
        'entities' => $entities,
        'events' => $events,
        'nameservers' => $ns,
        '_source' => 'TRABIS',
    ];
    if (!empty($head['Transfer Status'])) $out['transferStatus'] = $head['Transfer Status'];
    if (!empty($head['Frozen Status']) && $head['Frozen Status'] !== '-') $out['frozenStatus'] = $head['Frozen Status'];
    return $out;
}

<?php
require_once __DIR__ . '/../../core/reports.php';

if (!function_exists('e2')) {
    function e2($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $act = (string)($_POST['act'] ?? '');
    $id  = (int)($_POST['id'] ?? 0);
    $backF = in_array(($_POST['f'] ?? 'new'), ['new', 'valid', 'spam', 'all'], true) ? (string)$_POST['f'] : 'new';
    $backQ = trim((string)($_POST['q'] ?? ''));
    $msg = '';

    if ($act === 'valid' && $id > 0) {
        $pdo->prepare("UPDATE store_reports SET status='valid', spam=0, spam_checked=1 WHERE id=?")->execute([$id]);
        $msg = 'Rapor geçerli olarak işaretlendi.';
    } elseif ($act === 'spam' && $id > 0) {
        $pdo->prepare("UPDATE store_reports SET status='spam', spam=1, spam_checked=1 WHERE id=?")->execute([$id]);
        $msg = 'Rapor spam olarak işaretlendi.';
    } elseif ($act === 'delete' && $id > 0) {
        $pdo->prepare("DELETE FROM store_reports WHERE id=?")->execute([$id]);
        $msg = 'Rapor silindi.';
    } elseif ($act === 'scan' && $id > 0) {
        $r = report_spam_scan($pdo, $id);
        $msg = !empty($r['ok']) ? ('Tarandı: ' . (!empty($r['spam']) ? 'spam' : 'geçerli') . ' (' . (int)($r['score'] ?? 0) . '%)') : ('Taranamadı: ' . (string)($r['msg'] ?? ''));
    } elseif ($act === 'scan_pending') {
        $n = report_scan_pending($pdo, 10);
        $msg = $n . ' rapor tarandı.';
    } elseif ($act === 'save_settings') {
        report_setting_set($pdo, 'report_enabled', !empty($_POST['report_enabled']) ? '1' : '0');
        report_setting_set($pdo, 'report_rate_hours', (string)max(1, min(168, (int)($_POST['report_rate_hours'] ?? 12))));
        report_setting_set($pdo, 'report_pow_bits', (string)max(10, min(20, (int)($_POST['report_pow_bits'] ?? 14))));
        report_setting_set($pdo, 'report_min_seconds', (string)max(1, min(30, (int)($_POST['report_min_seconds'] ?? 2))));
        report_setting_set($pdo, 'report_msg_min', (string)max(3, min(500, (int)($_POST['report_msg_min'] ?? 10))));
        report_setting_set($pdo, 'report_domain_daily', (string)max(1, min(100, (int)($_POST['report_domain_daily'] ?? 8))));
        report_setting_set($pdo, 'report_clef_enabled', !empty($_POST['report_clef_enabled']) ? '1' : '0');
        $model = trim(mb_substr((string)($_POST['report_clef_model'] ?? 'clef'), 0, 40));
        report_setting_set($pdo, 'report_clef_model', $model !== '' ? $model : 'clef');
        $thr = (float)str_replace(',', '.', (string)($_POST['report_spam_threshold'] ?? '0.7'));
        if ($thr <= 0 || $thr > 1) $thr = 0.7;
        report_setting_set($pdo, 'report_spam_threshold', (string)$thr);
        $prompt = trim(mb_substr((string)($_POST['report_clef_prompt'] ?? ''), 0, 2000));
        report_setting_set($pdo, 'report_clef_prompt', $prompt);
        $msg = 'Rapor ayarları kaydedildi.';
    }

    $_SESSION['vx_report_flash'] = $msg;
    header('Location: /admin?tab=reports&f=' . urlencode($backF) . '&q=' . urlencode($backQ));
    exit;
}

$flash = '';
if (!empty($_SESSION['vx_report_flash'])) {
    $flash = (string)$_SESSION['vx_report_flash'];
    unset($_SESSION['vx_report_flash']);
}

$f = in_array(($_GET['f'] ?? 'new'), ['new', 'valid', 'spam', 'all'], true) ? (string)$_GET['f'] : 'new';
$q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 190));
$page = max(1, (int)($_GET['p'] ?? 1));
$per = 40;

$where = [];
$args = [];
if ($f === 'new') { $where[] = "status='new' AND spam=0"; }
elseif ($f === 'valid') { $where[] = "status='valid'"; }
elseif ($f === 'spam') { $where[] = "status='spam'"; }
if ($q !== '') {
    $where[] = "(store_name LIKE ? OR domain LIKE ? OR message LIKE ? OR reason LIKE ?)";
    $like = '%' . $q . '%';
    $args = array_merge($args, [$like, $like, $like, $like]);
}
$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$total = 0;
$rows = [];
$counts = ['new' => 0, 'valid' => 0, 'spam' => 0, 'all' => 0, 'pending' => 0];
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM store_reports" . $whereSql);
    $st->execute($args);
    $total = (int)$st->fetchColumn();
    $off = ($page - 1) * $per;
    $st = $pdo->prepare("SELECT * FROM store_reports" . $whereSql . " ORDER BY id DESC LIMIT " . (int)$per . " OFFSET " . (int)$off);
    $st->execute($args);
    $rows = $st->fetchAll();

    $counts['new']   = (int)$pdo->query("SELECT COUNT(*) FROM store_reports WHERE status='new' AND spam=0")->fetchColumn();
    $counts['valid'] = (int)$pdo->query("SELECT COUNT(*) FROM store_reports WHERE status='valid'")->fetchColumn();
    $counts['spam']  = (int)$pdo->query("SELECT COUNT(*) FROM store_reports WHERE status='spam'")->fetchColumn();
    $counts['all']   = (int)$pdo->query("SELECT COUNT(*) FROM store_reports")->fetchColumn();
    $counts['pending'] = (int)$pdo->query("SELECT COUNT(*) FROM store_reports WHERE spam_checked=0 AND status='new'")->fetchColumn();
} catch (\Throwable $e) {}

$cfgEnabled = report_setting($pdo, 'report_enabled', '1') === '1';
$cfgHours = (int)report_setting($pdo, 'report_rate_hours', '12');
$cfgClef = report_setting($pdo, 'report_clef_enabled', '1') === '1';
$cfgModel = report_setting($pdo, 'report_clef_model', 'clef');
$cfgThr = (float)report_setting($pdo, 'report_spam_threshold', '0.7');
$cfgMinSec = (int)report_setting($pdo, 'report_min_seconds', '2');
$cfgDomDaily = (int)report_setting($pdo, 'report_domain_daily', '8');
$cfgPrompt = report_setting($pdo, 'report_clef_prompt', '');
$pages = max(1, (int)ceil($total / $per));

$statusBadge = function (string $st): string {
    if ($st === 'valid') return '<span style="background:rgba(34,197,94,.15);color:#4ade80;border:1px solid rgba(34,197,94,.3);padding:2px 10px;border-radius:999px;font-size:.72rem;font-weight:700">Geçerli</span>';
    if ($st === 'spam') return '<span style="background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3);padding:2px 10px;border-radius:999px;font-size:.72rem;font-weight:700">Spam</span>';
    return '<span style="background:rgba(234,179,8,.15);color:#facc15;border:1px solid rgba(234,179,8,.3);padding:2px 10px;border-radius:999px;font-size:.72rem;font-weight:700">Yeni</span>';
};
?>

<?php if ($flash !== ''): ?>
<div class="panel" style="margin-bottom:1rem;border-color:rgba(34,197,94,.35);background:rgba(34,197,94,.08);color:#86efac;font-weight:600;font-size:.85rem">
    <i class="fas fa-circle-check"></i> <?= e2($flash) ?>
</div>
<?php endif; ?>

<div class="panel" style="margin-bottom:1.2rem">
    <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between">
        <div style="display:flex;gap:.4rem;flex-wrap:wrap">
            <?php foreach (['new' => 'Yeni', 'valid' => 'Geçerli', 'spam' => 'Spam', 'all' => 'Tümü'] as $k => $lbl): ?>
            <a href="/admin?tab=reports&f=<?= $k ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>"
               class="btn <?= $f === $k ? 'btn-primary' : '' ?>" style="font-size:.8rem;padding:.45rem .85rem">
                <?= $lbl ?> <span style="opacity:.7">(<?= (int)$counts[$k] ?>)</span>
            </a>
            <?php endforeach; ?>
            <form method="get" action="/admin" style="display:flex;gap:.4rem;align-items:center;margin:0">
                <input type="hidden" name="tab" value="reports">
                <input type="hidden" name="f" value="<?= e2($f) ?>">
                <input name="q" value="<?= e2($q) ?>" placeholder="Mağaza / domain / mesaj ara"
                       style="background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.45rem .75rem;font-size:.8rem;outline:none;min-width:200px">
                <button class="btn" type="submit" style="font-size:.8rem;padding:.45rem .75rem"><i class="fas fa-magnifying-glass"></i></button>
            </form>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="act" value="scan_pending"><input type="hidden" name="f" value="<?= e2($f) ?>"><input type="hidden" name="q" value="<?= e2($q) ?>">
                <button class="btn" type="submit" style="font-size:.8rem;padding:.45rem .85rem" <?= $counts['pending'] > 0 ? '' : 'disabled' ?>>
                    <i class="fas fa-wand-magic-sparkles"></i> Bekleyenleri tara (<?= (int)$counts['pending'] ?>)
                </button>
            </form>
        </div>
    </div>
</div>

<div class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:46px">#</th>
                    <th style="width:120px">Tarih</th>
                    <th>Mağaza</th>
                    <th style="width:150px">Sebep</th>
                    <th>Mesaj</th>
                    <th style="width:110px">IP</th>
                    <th style="width:110px">CLEF</th>
                    <th style="width:80px">Durum</th>
                    <th style="width:210px;text-align:center">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                <tr><td colspan="9" style="text-align:center;padding:2.5rem" class="text-muted">Rapor yok.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="text-muted"><?= (int)$r['id'] ?></td>
                    <td class="text-muted" style="font-size:.78rem"><?= e2(date('d.m.Y H:i', strtotime((string)$r['created_at']))) ?></td>
                    <td>
                        <div style="font-weight:700"><?= e2($r['store_name'] !== '' ? $r['store_name'] : '-') ?></div>
                        <div style="font-size:.75rem;color:var(--muted)">
                            <?= e2($r['store_category'] !== '' ? $r['store_category'] : '-') ?>
                            · <a href="https://<?= e2($r['domain']) ?>" target="_blank" rel="noopener"><?= e2($r['domain']) ?></a>
                        </div>
                    </td>
                    <td><span style="font-size:.78rem;font-weight:600"><?= e2(report_reason_label((string)$r['reason'], 'tr')) ?></span></td>
                    <td style="max-width:360px">
                        <?php if ((string)$r['message'] !== ''): ?>
                        <details>
                            <summary style="cursor:pointer;font-size:.8rem;color:var(--muted)"><?= e2(mb_substr((string)$r['message'], 0, 90)) ?><?= mb_strlen((string)$r['message']) > 90 ? '…' : '' ?></summary>
                            <div style="margin-top:.5rem;font-size:.82rem;line-height:1.55;white-space:pre-wrap;color:var(--text)"><?= e2((string)$r['message']) ?></div>
                        </details>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td class="text-muted" style="font-size:.75rem"><?= e2((string)$r['ip']) ?></td>
                    <td>
                        <?php if ((int)$r['spam_checked'] === 1): ?>
                        <span style="font-size:.78rem;font-weight:700;color:<?= (int)$r['spam'] === 1 ? '#f87171' : '#4ade80' ?>"><?= (int)$r['spam_score'] ?>%</span>
                        <div style="font-size:.68rem;color:var(--muted)"><?= e2((string)$r['spam_reason']) ?></div>
                        <?php else: ?>
                        <span style="font-size:.75rem;color:var(--muted)">bekliyor</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $statusBadge((string)$r['status']) ?></td>
                    <td style="text-align:center;white-space:nowrap">
                        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="f" value="<?= e2($f) ?>"><input type="hidden" name="q" value="<?= e2($q) ?>">
                            <button class="btn btn-tbl-ok" name="act" value="valid" title="Geçerli"><i class="fas fa-check"></i></button>
                            <button class="btn btn-tbl-warn" name="act" value="spam" title="Spam"><i class="fas fa-ban"></i></button>
                            <button class="btn btn-tbl-info" name="act" value="scan" title="CLEF ile tara"><i class="fas fa-wand-magic-sparkles"></i></button>
                            <button class="btn btn-tbl-del" name="act" value="delete" title="Sil" onclick="return confirm('Rapor silinsin mi?')"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
    <div style="display:flex;gap:.4rem;justify-content:center;padding-top:1rem;flex-wrap:wrap">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a class="btn <?= $i === $page ? 'btn-primary' : '' ?>" style="font-size:.78rem;padding:.35rem .7rem"
           href="/admin?tab=reports&f=<?= e2($f) ?>&p=<?= $i ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<div class="panel" style="margin-top:1.2rem">
    <h3 style="font-size:.95rem;font-weight:800;margin-bottom:1rem"><i class="fas fa-sliders" style="color:var(--cyan);margin-right:8px"></i>Rapor Ayarları</h3>
    <p style="font-size:.8rem;color:var(--muted);line-height:1.6;margin:-.4rem 0 1rem">
        Turnstile rapor akışından kaldırıldı. Otomasyon koruması katmanlıdır: Proof-of-Work meydan okuması (bit),
        en kısa doldurma süresi + etkileşim sinyali, gizli tuzak alan, origin + mağaza imzası, tek kullanımlık nonce,
        IP başına saatlik/günlük limit ve mağaza başına günlük tavan. Spam metinler CLEF ile işaretlenir.
    </p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="act" value="save_settings">
        <input type="hidden" name="f" value="<?= e2($f) ?>">
        <input type="hidden" name="q" value="<?= e2($q) ?>">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-bottom:1rem">
            <label style="display:flex;align-items:center;gap:.5rem;font-size:.85rem">
                <input type="checkbox" name="report_enabled" value="1" <?= $cfgEnabled ? 'checked' : '' ?>> Rapor sistemi açık
            </label>
            <label style="display:flex;align-items:center;gap:.5rem;font-size:.85rem">
                <input type="checkbox" name="report_clef_enabled" value="1" <?= $cfgClef ? 'checked' : '' ?>> CLEF spam filtresi açık
            </label>
            <div>
                <label style="display:block;font-size:.78rem;color:var(--muted);margin-bottom:.35rem">IP başına bekleme (saat)</label>
                <input type="number" min="1" max="168" name="report_rate_hours" value="<?= (int)$cfgHours ?>"
                       style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.5rem .7rem;font-size:.85rem">
            </div>
            <div>
                <label style="display:block;font-size:.78rem;color:var(--muted);margin-bottom:.35rem">Otomasyon kalkanı zorluğu (bit)</label>
                <input type="number" min="10" max="20" name="report_pow_bits" value="<?= (int)report_pow_bits($pdo) ?>"
                       style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.5rem .7rem;font-size:.85rem">
            </div>
            <div>
                <label style="display:block;font-size:.78rem;color:var(--muted);margin-bottom:.35rem">En kısa doldurma süresi (sn)</label>
                <input type="number" min="1" max="30" name="report_min_seconds" value="<?= (int)$cfgMinSec ?>"
                       style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.5rem .7rem;font-size:.85rem">
            </div>
            <div>
                <label style="display:block;font-size:.78rem;color:var(--muted);margin-bottom:.35rem">Zorunlu açıklama (en az karakter)</label>
                <input type="number" min="3" max="500" name="report_msg_min" value="<?= (int)report_msg_min($pdo) ?>"
                       style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.5rem .7rem;font-size:.85rem">
            </div>
            <div>
                <label style="display:block;font-size:.78rem;color:var(--muted);margin-bottom:.35rem">Mağaza başına günlük rapor tavanı</label>
                <input type="number" min="1" max="100" name="report_domain_daily" value="<?= (int)$cfgDomDaily ?>"
                       style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.5rem .7rem;font-size:.85rem">
            </div>
            <div>
                <label style="display:block;font-size:.78rem;color:var(--muted);margin-bottom:.35rem">CLEF model</label>
                <input type="text" name="report_clef_model" value="<?= e2($cfgModel) ?>"
                       style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.5rem .7rem;font-size:.85rem">
            </div>
            <div>
                <label style="display:block;font-size:.78rem;color:var(--muted);margin-bottom:.35rem">Spam eşiği (0-1)</label>
                <input type="text" name="report_spam_threshold" value="<?= e2((string)$cfgThr) ?>"
                       style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.5rem .7rem;font-size:.85rem">
            </div>
        </div>
        <label style="display:block;font-size:.78rem;color:var(--muted);margin-bottom:.35rem">CLEF talimatı (boş = varsayılan)</label>
        <textarea name="report_clef_prompt" rows="3"
                  style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .8rem;font-size:.85rem;resize:vertical;margin-bottom:1rem"><?= e2($cfgPrompt) ?></textarea>
        <button class="btn btn-primary" type="submit"><i class="fas fa-floppy-disk"></i> Kaydet</button>
    </form>
</div>

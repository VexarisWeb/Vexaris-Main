<?php
require_once __DIR__ . '/../../core/mail.php';

function e2($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

try {
    $st = $pdo->prepare("SELECT `value` FROM settings WHERE `key`='mail_inbound_secret' LIMIT 1");
    $st->execute();
    $inSecret = (string)$st->fetchColumn();
} catch (Exception $e) { $inSecret = ''; }

$inMsg = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $pdo) {
    csrf_check();
    $act = $_POST['ib_act'] ?? '';
    if ($act === 'regen') {
        $inSecret = bin2hex(random_bytes(32));
        $st = $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES ('mail_inbound_secret',?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
        $st->execute([$inSecret]);
        $inMsg = 'Webhook anahtarı yenilendi. Worker kodundaki INBOUND_SECRET değerini güncelle.';
    } elseif ($act === 'read' && isset($_POST['id'])) {
        $st = $pdo->prepare("UPDATE inbound_emails SET is_read=1 WHERE id=?");
        $st->execute([(int)$_POST['id']]);
    } elseif ($act === 'del' && isset($_POST['id'])) {
        $st = $pdo->prepare("DELETE FROM inbound_emails WHERE id=?");
        $st->execute([(int)$_POST['id']]);
        $inMsg = 'Mail silindi.';
    } elseif ($act === 'reply' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $body = trim((string)($_POST['ib_body'] ?? ''));
        $st = $pdo->prepare("SELECT * FROM inbound_emails WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row && $body !== '' && filter_var($row['from_addr'], FILTER_VALIDATE_EMAIL)) {
            $html = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6">'
                . nl2br(e2($body))
                . '<hr style="border:none;border-top:1px solid #ddd;margin:18px 0">'
                . '<div style="color:#888;font-size:12px">Bu e-posta Vexaris Gelen Kutusu üzerinden yanıtlanmıştır.</div></div>';
            $ok = mail_send($pdo, $row['from_addr'], 'Re: ' . ($row['subject'] !== '' ? $row['subject'] : '(konu yok)'), $html, [], ['email' => $row['to_addr'], 'name' => 'Vexaris']);
            if ($ok) {
                $st = $pdo->prepare("UPDATE inbound_emails SET is_read=1, replied_at=NOW() WHERE id=?");
                $st->execute([$id]);
                $inMsg = 'Yanıt gönderildi (' . e2($row['to_addr']) . ' adresinden).';
            } else {
                $inMsg = 'Gönderilemedi: mail servisi (Brevo) yanıt vermedi.';
            }
        } else {
            $inMsg = 'Yanıt gönderilemedi: alıcı adresi veya metin geçersiz.';
        }
    }
}
if ($inSecret === '' && $pdo) {
    $inSecret = bin2hex(random_bytes(32));
    try {
        $st = $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES ('mail_inbound_secret',?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
        $st->execute([$inSecret]);
    } catch (Exception $e) {}
}

$mails = [];
$unread = 0;
if ($pdo) {
    try {
        $unread = (int)$pdo->query("SELECT COUNT(*) FROM inbound_emails WHERE is_read=0")->fetchColumn();
        $rows = $pdo->query("SELECT id, to_addr, from_addr, from_name, subject, is_read, replied_at, created_at FROM inbound_emails ORDER BY id DESC LIMIT 100");
        while ($r = $rows->fetch()) $mails[] = $r;
    } catch (Exception $e) {}
}

$sel = null;
$selId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($selId > 0 && $pdo) {
    try {
        $st = $pdo->prepare("SELECT * FROM inbound_emails WHERE id=? LIMIT 1");
        $st->execute([$selId]);
        $sel = $st->fetch();
        if ($sel && !(int)$sel['is_read']) {
            $st = $pdo->prepare("UPDATE inbound_emails SET is_read=1 WHERE id=?");
            $st->execute([$selId]);
            $sel['is_read'] = 1;
        }
    } catch (Exception $e) {}
}
?>
<div class="ticket-wrap">
    <div class="ticket-side">
        <div class="ticket-side__head">
            <div class="ticket-side__title"><i class="fas fa-inbox"></i> Gelen Kutusu</div>
            <span class="ticket-side__count"><?= (int)$unread ?> okunmadı</span>
        </div>
        <div class="ticket-list">
            <?php if (!$mails): ?>
                <div class="ticket-empty"><i class="fas fa-inbox"></i><br>Henüz mail yok.<br><span style="font-size:.75rem">Worker aktif olunca mailler buraya düşer.</span></div>
            <?php else: foreach ($mails as $m): ?>
                <a href="/admin?tab=inbox&id=<?= (int)$m['id'] ?>" class="ticket-item<?= $selId === (int)$m['id'] ? ' on' : '' ?>" style="<?= (int)$m['is_read'] ? '' : 'border-left:3px solid #4f8fc0' ?>">
                    <div style="display:flex;gap:6px;align-items:center">
                        <span style="font-size:.66rem;font-weight:800;background:rgba(79,143,192,.15);border:1px solid rgba(79,143,192,.4);border-radius:6px;padding:.1rem .4rem;color:#9fc7e8"><?= e2($m['to_addr']) ?></span>
                        <?php if ($m['replied_at']): ?><span style="font-size:.66rem;color:#74c091;font-weight:700"><i class="fas fa-reply"></i></span><?php endif; ?>
                    </div>
                    <div style="font-weight:700;margin-top:.25rem"><?= e2($m['subject'] !== '' ? $m['subject'] : '(konu yok)') ?></div>
                    <div style="font-size:.75rem;color:var(--muted)"><?= e2($m['from_name'] !== '' ? $m['from_name'] . ' <' . $m['from_addr'] . '>' : $m['from_addr']) ?></div>
                    <div style="font-size:.7rem;color:var(--muted)"><?= e2($m['created_at']) ?></div>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <div class="ticket-chat">
        <?php if ($inMsg !== ''): ?><div style="background:rgba(79,143,192,.12);border:1px solid rgba(79,143,192,.4);border-radius:10px;padding:.6rem 1rem;margin-bottom:1rem;font-size:.85rem"><?= $inMsg ?></div><?php endif; ?>
        <?php if (!$sel): ?>
            <div class="ticket-chat__empty"><i class="fas fa-envelope-open"></i><p>Soldan bir mail seç.</p>
                <div style="max-width:560px;margin:1.2rem auto 0;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;padding:1rem;text-align:left;font-size:.78rem;color:var(--muted)">
                    <b style="color:#fff">Worker kurulum bilgisi</b><br>
                    Webhook URL: <code style="color:#9fc7e8">https://vexaris.net/api/inbound-mail.php</code><br>
                    Anahtar (INBOUND_SECRET): <code id="ibSec" style="color:#9fc7e8;word-break:break-all"><?= e2($inSecret) ?></code>
                    <form method="post" style="margin-top:.5rem"><?= csrf_field() ?><input type="hidden" name="ib_act" value="regen"><button class="ticket-new-btn" style="padding:5px 10px;font-size:.72rem">Anahtarı yenile</button></form>
                </div>
            </div>
        <?php else: ?>
            <div style="padding:1.2rem 1.4rem;border-bottom:1px solid var(--border)">
                <div style="font-size:1.05rem;font-weight:800"><?= e2($sel['subject'] !== '' ? $sel['subject'] : '(konu yok)') ?></div>
                <div style="font-size:.8rem;color:var(--muted);margin-top:.3rem">Kimden: <?= e2($sel['from_name'] !== '' ? $sel['from_name'] . ' <' . $sel['from_addr'] . '>' : $sel['from_addr']) ?> &nbsp;•&nbsp; Kime: <b style="color:#9fc7e8"><?= e2($sel['to_addr']) ?></b> &nbsp;•&nbsp; <?= e2($sel['created_at']) ?><?= $sel['replied_at'] ? ' &nbsp;•&nbsp; <span style="color:#74c091">yanıtlandı</span>' : '' ?></div>
            </div>
            <div style="padding:1.2rem 1.4rem;white-space:pre-wrap;font-size:.9rem;line-height:1.65;max-height:40vh;overflow:auto"><?= e2($sel['body_text'] !== '' ? $sel['body_text'] : '(içerik yok)') ?></div>
            <form method="post" style="padding:1.2rem 1.4rem;border-top:1px solid var(--border)">
                <?= csrf_field() ?>
                <input type="hidden" name="ib_act" value="reply">
                <input type="hidden" name="id" value="<?= (int)$sel['id'] ?>">
                <label style="font-size:.8rem;font-weight:700">Yanıtla <span style="color:var(--muted);font-weight:400">(<?= e2($sel['to_addr']) ?> adresinden, Brevo ile gönderilir)</span></label>
                <textarea name="ib_body" rows="5" required style="width:100%;margin-top:.5rem;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:10px;color:#fff;padding:.7rem;font-family:inherit" placeholder="Yanıt metni..."></textarea>
                <div style="display:flex;gap:8px;margin-top:.6rem">
                    <button class="ticket-new-btn" style="padding:8px 16px"><i class="fas fa-paper-plane"></i> Gönder</button>
                </div>
            </form>
            <form method="post" style="padding:0 1.4rem 1.2rem" onsubmit="return confirm('Silinsin mi?')">
                <?= csrf_field() ?>
                <input type="hidden" name="ib_act" value="del">
                <input type="hidden" name="id" value="<?= (int)$sel['id'] ?>">
                <button style="background:none;border:none;color:var(--muted);font-size:.75rem;cursor:pointer"><i class="fas fa-trash"></i> Sil</button>
            </form>
        <?php endif; ?>
    </div>
</div>

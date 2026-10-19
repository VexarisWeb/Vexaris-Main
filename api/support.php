<?php
require_once __DIR__ . '/../core/db.php';
boot_session();
require_login();

header('Content-Type: application/json; charset=utf-8');

$pdo = db();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Veritabanı bağlantısı kurulamadı.']);
    exit;
}

$isAdmin = is_admin();
$isStaff = has_permission('tickets');
$ownOnly = ($_GET['own'] ?? '') === '1' || ($_POST['own'] ?? '') === '1';
$adminView = ($isAdmin || $isStaff) && !$ownOnly;
$tx = function (string $tr, string $en) use ($adminView): string { return $adminView ? $tr : $en; };
$uid     = (int)(current_user()['id'] ?? 0);
$action  = $_GET['action'] ?? $_POST['action'] ?? '';

function support_save_image(array $file, bool $en = false): array {
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err !== UPLOAD_ERR_OK) return ['ok' => false, 'msg' => ($en ? 'Photo upload failed.' : 'Fotoğraf yüklenemedi.')];
    if ((int)($file['size'] ?? 0) > 5 * 1024 * 1024) return ['ok' => false, 'msg' => ($en ? 'Photos can be at most 5 MB.' : 'Fotoğraf en fazla 5 MB olabilir.')];
    $info = @getimagesize((string)($file['tmp_name'] ?? ''));
    if ($info === false) return ['ok' => false, 'msg' => ($en ? 'Only photos (JPG, PNG, GIF, WEBP) can be uploaded.' : 'Yalnızca fotoğraf (JPG, PNG, GIF, WEBP) yüklenebilir.')];
    $map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!isset($map[$info[2]])) return ['ok' => false, 'msg' => ($en ? 'Only photos (JPG, PNG, GIF, WEBP) can be uploaded.' : 'Yalnızca fotoğraf (JPG, PNG, GIF, WEBP) yüklenebilir.')];
    $dir = dirname(__DIR__) . '/uploads/tickets';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return ['ok' => false, 'msg' => ($en ? 'Could not create upload directory.' : 'Yükleme dizini oluşturulamadı.')];
    $fname = 't' . bin2hex(random_bytes(12)) . '.' . $map[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], $dir . DIRECTORY_SEPARATOR . $fname)) {
        return ['ok' => false, 'msg' => ($en ? 'Photo could not be saved.' : 'Fotoğraf kaydedilemedi.')];
    }
    return ['ok' => true, 'url' => '/uploads/tickets/' . $fname];
}

try {
    switch ($action) {

        case 'list':
            if (($isAdmin || $isStaff) && !$ownOnly) {
                $st = $pdo->query("SELECT t.*, u.username, u.email,
                    (SELECT m.message FROM support_messages m WHERE m.ticket_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_msg,
                    (SELECT COUNT(*) FROM support_messages m WHERE m.ticket_id = t.id AND m.sender_role = 'user' AND m.is_read = 0) AS unread
                    FROM support_tickets t JOIN users u ON u.id = t.user_id
                    ORDER BY t.last_msg_at DESC");
            } else {
                $st = $pdo->prepare("SELECT t.*,
                    (SELECT m.message FROM support_messages m WHERE m.ticket_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_msg,
                    (SELECT COUNT(*) FROM support_messages m WHERE m.ticket_id = t.id AND m.sender_role = 'admin' AND m.is_read = 0) AS unread
                    FROM support_tickets t WHERE t.user_id = ?
                    ORDER BY t.last_msg_at DESC");
                $st->execute([$uid]);
            }
            echo json_encode(['ok' => true, 'tickets' => $st->fetchAll()]);
            exit;

        case 'create':
            csrf_check();
            $subject = trim($_POST['subject'] ?? '');
            $message = trim($_POST['message'] ?? '');
            if ($subject === '' || $message === '') {
                echo json_encode(['ok' => false, 'msg' => $tx('Konu ve mesaj zorunludur.', 'Subject and message are required.')]);
                exit;
            }
            if (mb_strlen($subject) > 190) $subject = mb_substr($subject, 0, 190);
            $isPriv = $isAdmin || $isStaff;
            $targetUid = $uid;
            if ($isPriv && isset($_POST['user_id']) && (int)$_POST['user_id'] > 0) {
                $targetUid = (int)$_POST['user_id'];
                $stU = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                $stU->execute([$targetUid]);
                if (!$stU->fetch()) { echo json_encode(['ok' => false, 'msg' => $tx('Kullanıcı bulunamadı.', 'User not found.')]); exit; }
            } else {
                $st = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id = ? AND status != 'closed'");
                $st->execute([$uid]);
                if ((int)$st->fetchColumn() >= 1) {
                    echo json_encode(['ok' => false, 'msg' => $tx('Zaten açık bir talebiniz var. Önce onu kapatın.', 'You already have an open ticket. Close it first.')]);
                    exit;
                }
            }
            $pdo->prepare("INSERT INTO support_tickets (user_id, subject) VALUES (?,?)")->execute([$targetUid, $subject]);
            $ticketId = (int)$pdo->lastInsertId();
            $senderRole = $isPriv && $targetUid !== $uid ? 'admin' : 'user';
            $senderId = $uid;
            $pdo->prepare("INSERT INTO support_messages (ticket_id, sender_id, sender_role, message) VALUES (?,?,?,?)")
                ->execute([$ticketId, $senderId, $senderRole, $message]);
            if ($senderRole === 'admin' && $targetUid !== $uid) {
                notify_user($targetUid, 'support', $tx('Destek talebi oluşturuldu', 'Support ticket created'), $subject);
            }
            echo json_encode(['ok' => true, 'msg' => $tx('Talebiniz oluşturuldu.', 'Your ticket has been created.'), 'ticket_id' => $ticketId]);
            exit;

        case 'send':
            csrf_check();
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $message  = trim($_POST['message'] ?? '');
            $hasImage = !empty($_FILES['image']['name']);
            if ($ticketId <= 0 || ($message === '' && !$hasImage)) {
                echo json_encode(['ok' => false, 'msg' => $tx('Mesaj boş olamaz.', 'Message cannot be empty.')]);
                exit;
            }
            if (mb_strlen($message, 'UTF-8') > 500) {
                echo json_encode(['ok' => false, 'msg' => $tx('Mesaj en fazla 500 karakter olabilir.', 'Messages can be at most 500 characters.')]);
                exit;
            }
            // Spam koruması: aynı odada 2 sn bekleme + dakikada max 20 mesaj + art arda aynı metin yok
            try {
                $stL = $pdo->prepare("SELECT message, created_at FROM support_messages WHERE ticket_id = ? AND sender_id = ? ORDER BY id DESC LIMIT 1");
                $stL->execute([$ticketId, $uid]);
                $last = $stL->fetch();
                if ($last) {
                    if ((time() - strtotime((string)$last['created_at'])) < 2) {
                        http_response_code(429);
                        echo json_encode(['ok' => false, 'msg' => $tx('Çok hızlı gönderiyorsun. 2 saniye bekle.', 'You are sending too fast. Wait 2 seconds.')]);
                        exit;
                    }
                    if ($message !== '' && $last['message'] === $message && (time() - strtotime((string)$last['created_at'])) < 60) {
                        echo json_encode(['ok' => false, 'msg' => $tx('Aynı mesajı tekrar gönderemezsin.', 'You cannot send the same message twice.')]);
                        exit;
                    }
                }
                $stC = $pdo->prepare("SELECT COUNT(*) FROM support_messages WHERE sender_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
                $stC->execute([$uid]);
                if ((int)$stC->fetchColumn() >= 20) {
                    http_response_code(429);
                    echo json_encode(['ok' => false, 'msg' => $tx('Dakikada en fazla 20 mesaj gönderebilirsin.', 'You can send at most 20 messages per minute.')]);
                    exit;
                }
            } catch (Exception $e) {}
            $role = ($isAdmin || $isStaff) ? 'admin' : 'user';
            if (!$isAdmin && !$isStaff) {
                $st = $pdo->prepare("SELECT id FROM support_tickets WHERE id = ? AND user_id = ?");
                $st->execute([$ticketId, $uid]);
                if (!$st->fetch()) { echo json_encode(['ok' => false, 'msg' => $tx('Geçersiz talep.', 'Invalid ticket.')]); exit; }
            } elseif ($ownOnly) {
                $st = $pdo->prepare("SELECT id FROM support_tickets WHERE id = ? AND user_id = ?");
                $st->execute([$ticketId, $uid]);
                if (!$st->fetch()) { echo json_encode(['ok' => false, 'msg' => $tx('Geçersiz talep.', 'Invalid ticket.')]); exit; }
            } else {
                $st = $pdo->prepare("SELECT id FROM support_tickets WHERE id = ?");
                $st->execute([$ticketId]);
                if (!$st->fetch()) { echo json_encode(['ok' => false, 'msg' => $tx('Geçersiz talep.', 'Invalid ticket.')]); exit; }
            }
            $attachment = null;
            if ($hasImage) {
                $up = support_save_image($_FILES['image'], !$adminView);
                if (!$up['ok']) { echo json_encode(['ok' => false, 'msg' => $up['msg']]); exit; }
                $attachment = $up['url'];
            }
            $pdo->prepare("INSERT INTO support_messages (ticket_id, sender_id, sender_role, message, attachment) VALUES (?,?,?,?,?)")
                ->execute([$ticketId, $uid, $role, $message, $attachment]);
            $mid = (int)$pdo->lastInsertId();
            $newStatus = ($isAdmin || $isStaff) ? 'answered' : 'open';
            $pdo->prepare("UPDATE support_tickets SET status = ?, last_msg_at = NOW() WHERE id = ?")->execute([$newStatus, $ticketId]);
            if ($role === 'admin') {
                $stO = $pdo->prepare("SELECT user_id, subject FROM support_tickets WHERE id = ?");
                $stO->execute([$ticketId]);
                $rowO = $stO->fetch();
                if ($rowO && (int)$rowO['user_id'] !== $uid) {
                    notify_user((int)$rowO['user_id'], 'support', $tx('Destek talebinize cevap geldi', 'New reply to your support ticket'), mb_substr($message, 0, 180));
                }
            }
            echo json_encode(['ok' => true, 'msg' => $tx('Mesaj gönderildi.', 'Message sent.'), 'message_id' => $mid]);
            exit;

        case 'messages':
            $ticketId = (int)($_GET['ticket_id'] ?? 0);
            if ($ticketId <= 0) { echo json_encode(['ok' => false, 'msg' => $tx('Talep yok.', 'No ticket.')]); exit; }
            if (!$isAdmin && !$isStaff) {
                $st = $pdo->prepare("SELECT id FROM support_tickets WHERE id = ? AND user_id = ?");
                $st->execute([$ticketId, $uid]);
                if (!$st->fetch()) { echo json_encode(['ok' => false, 'msg' => $tx('Geçersiz talep.', 'Invalid ticket.')]); exit; }
            } elseif ($ownOnly) {
                $st = $pdo->prepare("SELECT id FROM support_tickets WHERE id = ? AND user_id = ?");
                $st->execute([$ticketId, $uid]);
                if (!$st->fetch()) { echo json_encode(['ok' => false, 'msg' => $tx('Geçersiz talep.', 'Invalid ticket.')]); exit; }
            }
            $st = $pdo->prepare("SELECT m.*, u.username FROM support_messages m JOIN users u ON u.id = m.sender_id
                WHERE m.ticket_id = ? ORDER BY m.id ASC");
            $st->execute([$ticketId]);
            $messages = $st->fetchAll();

            $stT = $pdo->prepare("SELECT * FROM support_tickets WHERE id = ?");
            $stT->execute([$ticketId]);
            $ticket = $stT->fetch();

            if ($isAdmin || $isStaff) {
                $pdo->prepare("UPDATE support_messages SET is_read = 1 WHERE ticket_id = ? AND sender_role = 'user' AND is_read = 0")->execute([$ticketId]);
            } else {
                $pdo->prepare("UPDATE support_messages SET is_read = 1 WHERE ticket_id = ? AND sender_role = 'admin' AND is_read = 0")->execute([$ticketId]);
            }

            // Karşı taraf şu an yazıyor mu? (son 5 sn içinde sinyal)
            $peerTyping = false;
            try {
                $stT = $pdo->prepare("SELECT COUNT(*) FROM ticket_typing WHERE ticket_id = ? AND user_id <> ? AND updated_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)");
                $stT->execute([$ticketId, $uid]);
                $peerTyping = (int)$stT->fetchColumn() > 0;
            } catch (Exception $e) {}

            echo json_encode(['ok' => true, 'ticket' => $ticket, 'messages' => $messages, 'typing' => $peerTyping]);
            exit;

        case 'typing':
            csrf_check();
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            if ($ticketId <= 0) { echo json_encode(['ok' => false]); exit; }
            $allowed = false;
            if ($isAdmin || $isStaff) {
                if ($ownOnly) {
                    $st = $pdo->prepare("SELECT id FROM support_tickets WHERE id = ? AND user_id = ?");
                    $st->execute([$ticketId, $uid]);
                    $allowed = (bool)$st->fetch();
                } else {
                    $st = $pdo->prepare("SELECT id FROM support_tickets WHERE id = ?");
                    $st->execute([$ticketId]);
                    $allowed = (bool)$st->fetch();
                }
            } else {
                $st = $pdo->prepare("SELECT id FROM support_tickets WHERE id = ? AND user_id = ?");
                $st->execute([$ticketId, $uid]);
                $allowed = (bool)$st->fetch();
            }
            if (!$allowed) { echo json_encode(['ok' => false]); exit; }
            try {
                $pdo->prepare("INSERT INTO ticket_typing (ticket_id, user_id, updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE updated_at = NOW()")->execute([$ticketId, $uid]);
                $pdo->prepare("DELETE FROM ticket_typing WHERE updated_at < DATE_SUB(NOW(), INTERVAL 30 SECOND)")->execute();
            } catch (Exception $e) {}
            echo json_encode(['ok' => true]);
            exit;

        case 'close':
            csrf_check();
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            if ($ticketId<=0){ echo json_encode(['ok'=>false,'msg'=>$tx('Geçersiz talep.', 'Invalid ticket.')]); exit; }
            if (!$isAdmin && !$isStaff) {
                $st=$pdo->prepare("SELECT id FROM support_tickets WHERE id=? AND user_id=?");
                $st->execute([$ticketId,$uid]);
                if(!$st->fetch()){ echo json_encode(['ok'=>false,'msg'=>$tx('Geçersiz talep.', 'Invalid ticket.')]); exit; }
                $pdo->prepare("UPDATE support_tickets SET status='closed' WHERE id=?")->execute([$ticketId]);
            } else {
                $pdo->prepare("UPDATE support_tickets SET status='closed' WHERE id=?")->execute([$ticketId]);
            }
            support_purge_attachments($pdo, $ticketId);
            echo json_encode(['ok'=>true,'msg'=>$tx('Talep kapatıldı.', 'Ticket closed.')]);
            exit;
        case 'delete':
            csrf_check();
            if (!$isAdmin && !$isStaff) { echo json_encode(['ok' => false, 'msg' => $tx('Yetkiniz yok.', 'You are not authorized.')]); exit; }
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            if ($ticketId <= 0) { echo json_encode(['ok' => false, 'msg' => $tx('Geçersiz talep.', 'Invalid ticket.')]); exit; }
            if ($ownOnly) {
                $st = $pdo->prepare("SELECT id FROM support_tickets WHERE id = ? AND user_id = ?");
                $st->execute([$ticketId, $uid]);
                if (!$st->fetch()) { echo json_encode(['ok' => false, 'msg' => $tx('Geçersiz talep.', 'Invalid ticket.')]); exit; }
            } else {
                $st = $pdo->prepare("SELECT id FROM support_tickets WHERE id = ?");
                $st->execute([$ticketId]);
                if (!$st->fetch()) { echo json_encode(['ok' => false, 'msg' => $tx('Talep bulunamadı.', 'Ticket not found.')]); exit; }
            }
            support_purge_attachments($pdo, $ticketId);
            $pdo->prepare("DELETE FROM support_messages WHERE ticket_id = ?")->execute([$ticketId]);
            $pdo->prepare("DELETE FROM support_tickets WHERE id = ?")->execute([$ticketId]);
            audit_log($pdo, $uid, 'ticket_delete', 'ticket', (string)$ticketId);
            echo json_encode(['ok' => true, 'msg' => $tx('Talep ve tüm mesajları silindi.', 'Ticket and all its messages deleted.')]);
            exit;
        case 'status':
            csrf_check();
            if (!$isAdmin && !$isStaff) { echo json_encode(['ok' => false, 'msg' => $tx('Yetkiniz yok.', 'You are not authorized.')]); exit; }
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $status   = $_POST['status'] ?? '';
            if (!in_array($status, ['open', 'answered', 'closed'], true)) {
                echo json_encode(['ok' => false, 'msg' => $tx('Geçersiz durum.', 'Invalid status.')]);
                exit;
            }
            $pdo->prepare("UPDATE support_tickets SET status = ? WHERE id = ?")->execute([$status, $ticketId]);
            if ($status === 'closed') support_purge_attachments($pdo, $ticketId);
            echo json_encode(['ok' => true, 'msg' => $tx('Durum güncellendi.', 'Status updated.')]);
            exit;

        default:
            echo json_encode(['ok' => false, 'msg' => $tx('Bilinmeyen işlem.', 'Unknown action.')]);
    }
} catch (Exception $e) {
    error_log('Support API: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => $tx('Sunucu hatası.', 'Server error.')]);
}
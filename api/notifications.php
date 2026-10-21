<?php
require_once __DIR__ . '/../core/db.php';
boot_session();
require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'POST only.']);
    exit;
}

csrf_check();

$pdo    = db();
$uid    = (int)(current_user()['id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$pdo) {
    echo json_encode(['ok' => false, 'msg' => 'Could not connect to the database.']);
    exit;
}

try {
    if ($action === 'mark_read') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$id, $uid]);
        }
        echo json_encode(['ok' => true, 'msg' => 'Marked as read.']);
        exit;
    }

    if ($action === 'mark_all') {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$uid]);
        echo json_encode(['ok' => true, 'msg' => 'All marked as read.']);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Unknown action.']);
} catch (Throwable $e) {
    error_log($e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Server error.']);
}

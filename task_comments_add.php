<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['username'])) {
    echo json_encode(['ok'=>false,'message'=>'Auth required']); exit;
}

require_once 'includes/db.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
$role    = (int)($_SESSION['role'] ?? 0);

if ($user_id <= 0) {
    echo json_encode(['ok'=>false,'message'=>'Geçersiz kullanıcı']); exit;
}

$task_id = (int)($_POST['task_id'] ?? 0);
$comment = trim($_POST['comment_text'] ?? '');

if ($task_id <= 0 || $comment === '') {
    echo json_encode(['ok'=>false,'message'=>'Eksik parametre']); exit;
}
if ($role !== 1) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE id = :tid AND assigned_user_id = :uid");
    $st->execute([':tid'=>$task_id, ':uid'=>$user_id]);
    if ((int)$st->fetchColumn() === 0) {
        echo json_encode(['ok'=>false,'message'=>'Bu göreve yorum yetkin yok']); exit;
    }
} else {
    $st = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE id = :tid");
    $st->execute([':tid'=>$task_id]);
    if ((int)$st->fetchColumn() === 0) {
        echo json_encode(['ok'=>false,'message'=>'Görev bulunamadı']); exit;
    }
}

try {
    $ins = $pdo->prepare("
        INSERT INTO task_comments (task_id, user_id, comment_text)
        VALUES (:tid, :uid, :txt)
    ");
    $ins->execute([':tid'=>$task_id, ':uid'=>$user_id, ':txt'=>$comment]);

    echo json_encode(['ok'=>true]);
} catch (Exception $e) {
    echo json_encode(['ok'=>false, 'message'=>'DB hata: '.$e->getMessage()]);
}

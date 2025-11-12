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

$task_id = (int)($_GET['task_id'] ?? 0);
if ($task_id <= 0) {
    echo json_encode(['ok'=>false,'message'=>'Geçersiz task']); exit;
}
if ($role !== 1) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE id = :tid AND assigned_user_id = :uid");
    $st->execute([':tid'=>$task_id, ':uid'=>$user_id]);
    if ((int)$st->fetchColumn() === 0) {
        echo json_encode(['ok'=>false,'message'=>'Bu görevin yorumlarını görme yetkin yok']);
        exit;
    }
} else {
    $st = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE id = :tid");
    $st->execute([':tid'=>$task_id]);
    if ((int)$st->fetchColumn() === 0) {
        echo json_encode(['ok'=>false,'message'=>'Görev bulunamadı']);
        exit;
    }
}

$q = $pdo->prepare("
  SELECT tc.id, tc.comment_text, tc.created_at, u.username
  FROM task_comments tc
  LEFT JOIN users u ON u.id = tc.user_id
  WHERE tc.task_id = :tid
  ORDER BY tc.id DESC
");
$q->execute([':tid'=>$task_id]);
$items = $q->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['ok'=>true, 'items'=>$items]);

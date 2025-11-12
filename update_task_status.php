<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
if (!isset($_SESSION['username'])) { echo json_encode(['ok'=>false,'message'=>'Auth required']); exit; }
if ((int)($_SESSION['role'] ?? 0) !== 1) { echo json_encode(['ok'=>false,'message'=>'Yetki yok']); exit; }

require_once 'includes/db.php';

$id = (int)($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';
$allowed = ['todo','in_progress','on_hold','completed'];
if ($id <= 0 || !in_array($status,$allowed,true)) {
    echo json_encode(['ok'=>false,'message'=>'Geçersiz parametre']); exit;
}

$st = $pdo->prepare("UPDATE tasks SET status = :s WHERE id = :id");
$ok = $st->execute([':s'=>$status, ':id'=>$id]);
echo json_encode(['ok'=>$ok]);

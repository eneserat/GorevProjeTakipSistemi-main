<?php
session_start();

require_once __DIR__ . '/../includes/db.php'; // $pdo

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Auth required']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

$sql = "
    SELECT p.id, p.name, p.status, p.due_date
    FROM projects p
    INNER JOIN project_users pu ON pu.project_id = p.id
    WHERE pu.user_id = :uid
      AND p.status <> 'completed'
    ORDER BY 
      CASE p.status 
        WHEN 'in_progress' THEN 1 
        WHEN 'on_hold' THEN 2 
        WHEN 'planning' THEN 3 
        ELSE 9 END,
      p.due_date IS NULL, p.due_date ASC, p.id DESC
";
$st = $pdo->prepare($sql);
$st->execute([':uid' => $userId]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'count' => count($rows),
    'projects' => $rows
], JSON_UNESCAPED_UNICODE);

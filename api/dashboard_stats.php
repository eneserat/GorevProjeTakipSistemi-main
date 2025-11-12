<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php';
if (!isset($conn) && isset($pdo)) { $conn = $pdo; }

$userId   = (int)($_SESSION['user_id'] ?? 0);
$userRole = (int)($_SESSION['role'] ?? 0); // 1=admin
$isAdmin  = ($userRole === 1);

if (!$userId) { echo json_encode(['error' => 'auth']); exit; }

try {
    if ($isAdmin) {
        $totalProjects     = (int)$conn->query("SELECT COUNT(*) FROM projects")->fetchColumn();
        $completedProjects = (int)$conn->query("SELECT COUNT(*) FROM projects WHERE status='completed'")->fetchColumn();
    } else {
        $sqlTotal = "
            SELECT COUNT(DISTINCT p.id)
            FROM projects p
            INNER JOIN project_users pu ON pu.project_id = p.id
            WHERE pu.user_id = :uid
        ";
        $st = $conn->prepare($sqlTotal);
        $st->execute([':uid' => $userId]);
        $totalProjects = (int)$st->fetchColumn();

        $sqlCompleted = "
            SELECT COUNT(DISTINCT p.id)
            FROM projects p
            INNER JOIN project_users pu ON pu.project_id = p.id
            WHERE pu.user_id = :uid
              AND p.status = 'completed'
        ";
        $st = $conn->prepare($sqlCompleted);
        $st->execute([':uid' => $userId]);
        $completedProjects = (int)$st->fetchColumn();
    }

    if ($isAdmin) {
        $totalTasks = (int)$conn->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
    } else {
        $st = $conn->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_user_id = :uid");
        $st->execute([':uid' => $userId]);
        $totalTasks = (int)$st->fetchColumn();
    }

    echo json_encode([
        'total_projects'     => $totalProjects,
        'completed_projects' => $completedProjects,
        'total_tasks'        => $totalTasks
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server', 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok'=>false, 'message'=>'Auth required']); exit;
}
$user_id = (int)$_SESSION['user_id'];

require_once __DIR__ . '/includes/db.php';

$mode = $_POST['mode'] ?? 'focus';
$planned = (int)($_POST['planned_minutes'] ?? 0);
$started_at = $_POST['started_at'] ?? null;
$ended_at   = $_POST['ended_at'] ?? null;
$status = $_POST['status'] ?? 'completed';

if (!in_array($mode, ['focus','short_break','long_break'], true)) $mode = 'focus';
if (!in_array($status, ['completed','aborted'], true)) $status = 'completed';

try {
    $sa = new DateTime($started_at ?: 'now');
    $ea = new DateTime($ended_at   ?: 'now');
    $actual_seconds = max(0, $ea->getTimestamp() - $sa->getTimestamp());

    $st = $pdo->prepare("INSERT INTO pomodoro_sessions 
        (user_id, mode, planned_minutes, actual_seconds, started_at, ended_at, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $st->execute([$user_id, $mode, $planned, $actual_seconds, $sa->format('Y-m-d H:i:s'), $ea->format('Y-m-d H:i:s'), $status]);

    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    echo json_encode(['ok'=>false, 'message'=>$e->getMessage()]);
}

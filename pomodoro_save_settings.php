<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok'=>false, 'message'=>'Auth required']); exit;
}
$user_id = (int)$_SESSION['user_id'];

require_once __DIR__ . '/includes/db.php';

$focus   = max(1, min(180, (int)($_POST['focus_minutes'] ?? 25)));
$shortB  = max(1, min( 60, (int)($_POST['short_break_minutes'] ?? 5)));
$longB   = max(1, min( 90, (int)($_POST['long_break_minutes'] ?? 15)));
$cycles  = max(1, min( 12, (int)($_POST['cycles_before_long_break'] ?? 4)));

try {
    $sql = "INSERT INTO pomodoro_settings (user_id, focus_minutes, short_break_minutes, long_break_minutes, cycles_before_long_break)
            VALUES (:uid, :f, :s, :l, :c)
            ON DUPLICATE KEY UPDATE
              focus_minutes = VALUES(focus_minutes),
              short_break_minutes = VALUES(short_break_minutes),
              long_break_minutes = VALUES(long_break_minutes),
              cycles_before_long_break = VALUES(cycles_before_long_break)";
    $st = $pdo->prepare($sql);
    $st->execute([
        ':uid' => $user_id,
        ':f'   => $focus,
        ':s'   => $shortB,
        ':l'   => $longB,
        ':c'   => $cycles
    ]);
    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    echo json_encode(['ok'=>false, 'message'=>$e->getMessage()]);
}

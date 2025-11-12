<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';
if (!isset($conn) && isset($pdo)) { $conn = $pdo; }

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) { echo json_encode([]); exit; }

try {
    $sqlP = "
        SELECT p.id, p.name, p.start_date, p.due_date, p.priority, p.status, p.repo_url
        FROM projects p
        INNER JOIN project_users pu ON pu.project_id = p.id
        WHERE pu.user_id = :uid
          AND p.start_date IS NOT NULL
          AND p.due_date IS NOT NULL
        ORDER BY p.start_date ASC
    ";
    $stP = $conn->prepare($sqlP);
    $stP->execute([':uid' => $userId]);
    $projects = $stP->fetchAll(PDO::FETCH_ASSOC);

    $sqlT = "
        SELECT t.id, t.title, t.start_date, t.due_date, t.priority, t.status, t.repo_url
        FROM tasks t
        WHERE t.assigned_user_id = :uid
          AND t.start_date IS NOT NULL
          AND t.due_date IS NOT NULL
        ORDER BY t.start_date ASC
    ";
    $stT = $conn->prepare($sqlT);
    $stT->execute([':uid' => $userId]);
    $tasks = $stT->fetchAll(PDO::FETCH_ASSOC);

    $projColors = [
        'high'   => ['bg' => '#fecaca', 'fg' => '#ef4444'],
        'medium' => ['bg' => '#fde68a', 'fg' => '#f59e0b'],
        'low'    => ['bg' => '#bbf7d0', 'fg' => '#22c55e'],
        ''       => ['bg' => '#e5e7eb', 'fg' => '#6b7280'],
        null     => ['bg' => '#e5e7eb', 'fg' => '#6b7280'],
    ];
    $taskColors = [
        'high'   => ['bg' => '#dbeafe', 'fg' => '#3b82f6'],
        'medium' => ['bg' => '#ede9fe', 'fg' => '#8b5cf6'],
        'low'    => ['bg' => '#fce7f3', 'fg' => '#ec4899'],
        ''       => ['bg' => '#e5e7eb', 'fg' => '#374151'],
        null     => ['bg' => '#e5e7eb', 'fg' => '#374151'],
    ];

    $events = [];
    $DAILY_LABEL_MAX_DAYS = 90;

    $addSpan = function(array &$events, array $opt) use ($DAILY_LABEL_MAX_DAYS) {
        $start = $opt['start']; $end = $opt['end'];
        $endExc = (clone $end)->modify('+1 day');
        $spanDays = (int)$start->diff($end)->days + 1;

        $events[] = [
            'id' => "{$opt['idPrefix']}-bg",
            'title' => '',
            'start' => $start->format('Y-m-d'),
            'end'   => $endExc->format('Y-m-d'),
            'display' => 'background',
            'backgroundColor' => $opt['bgColor'],
            'overlap' => true,
            'extendedProps' => [
                'type' => $opt['typeBase'].'bg',
                'tooltip' => $opt['tooltip'] ?? ''
            ]
        ];

        if ($spanDays <= $DAILY_LABEL_MAX_DAYS) {
            $iter = clone $start;
            $i = 0;
            while ($iter <= $end) {
                $events[] = [
                    'id' => "{$opt['idPrefix']}-d{$i}",
                    'groupId' => "{$opt['idPrefix']}",
                    'title' => $opt['title'],
                    'start' => $iter->format('Y-m-d'),
                    'allDay' => true,
                    'color' => $opt['fgColor'],
                    'textColor' => '#111827',
                    'extendedProps' => [
                        'type' => $opt['typeBase'],
                        'tooltip' => $opt['tooltip'] ?? '',
                        'repo' => $opt['repo'] ?? ''
                    ]
                ];
                $iter->modify('+1 day'); $i++;
            }
        } else {
            $mid = (clone $start)->modify('+' . floor(($spanDays - 1)/2) . ' days');
            $events[] = [
                'id' => "{$opt['idPrefix']}-label",
                'title' => $opt['title']." (+{$spanDays}gün)",
                'start' => $mid->format('Y-m-d'),
                'allDay' => true,
                'color' => $opt['fgColor'],
                'textColor' => '#111827',
                'extendedProps' => [
                    'type' => $opt['typeBase'],
                    'tooltip' => $opt['tooltip'] ?? '',
                    'repo' => $opt['repo'] ?? ''
                ]
            ];
        }
    };

    foreach ($projects as $p) {
        $pid   = (int)$p['id'];
        $name  = (string)$p['name'];
        $prio  = $p['priority'] ?? '';
        $stat  = $p['status'] ?? '';
        $repo  = $p['repo_url'] ?? '';
        $c = $projColors[$prio] ?? $projColors[''];

        $start = new DateTime($p['start_date']);
        $end   = new DateTime($p['due_date']);

        $tip = sprintf("Proje: %s\nDurum: %s\nBaşlangıç: %s\nBitiş: %s",
            $name, $stat ?: '-', $start->format('Y-m-d'), $end->format('Y-m-d'));

        $addSpan($events, [
            'idPrefix' => "proj{$pid}",
            'title' => $name,
            'start' => $start,
            'end' => $end,
            'bgColor' => $c['bg'],
            'fgColor' => $c['fg'],
            'typeBase' => 'proj',
            'tooltip' => $tip,
            'repo' => $repo
        ]);
    }

    foreach ($tasks as $t) {
        $tid   = (int)$t['id'];
        $title = (string)$t['title'];
        $prio  = $t['priority'] ?? '';
        $stat  = $t['status'] ?? '';
        $repo  = $t['repo_url'] ?? '';
        $c = $taskColors[$prio] ?? $taskColors[''];

        $start = new DateTime($t['start_date']);
        $end   = new DateTime($t['due_date']);

        $tip = sprintf("Görev: %s\nDurum: %s\nBaşlangıç: %s\nBitiş: %s",
            $title, $stat ?: '-', $start->format('Y-m-d'), $end->format('Y-m-d'));

        $addSpan($events, [
            'idPrefix' => "task{$tid}",
            'title' => $title,
            'start' => $start,
            'end' => $end,
            'bgColor' => $c['bg'],
            'fgColor' => $c['fg'],
            'typeBase' => 'task',
            'tooltip' => $tip,
            'repo' => $repo
        ]);
    }

    echo json_encode($events, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

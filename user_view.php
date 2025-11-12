<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
require_once 'includes/db.php';

$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
    http_response_code(400);
    die("Geçersiz kullanıcı ID");
}

$stmt = $pdo->prepare("SELECT id, username, role, pp, created_at FROM users WHERE id = :id");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <title>Kullanıcı Bulunamadı</title>
        <link href="css/stylesheet.css" rel="stylesheet" type="text/css">
        <style>
            .card { background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:24px;margin:24px; }
            .muted{color:#6b7280}
            a.btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#111827;color:#fff;text-decoration:none}
        </style>
    </head>
    <body>
    <?php include 'includes/sidebar.php'; ?>
    <div class="main">
        <?php include 'includes/header.php'; ?>
        <div class="card">
            <h3 style="margin:0 0 8px;">Kullanıcı Bulunamadı</h3>
            <div class="muted">ID: <?= htmlspecialchars($userId) ?></div>
            <div style="margin-top:12px;">
                <a class="btn" href="javascript:history.back()">Geri Dön</a>
            </div>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

$createdProjects = [];
$assignedProjects = [];

try {
    $s1 = $pdo->prepare("SELECT id, name, priority, status, start_date, due_date, created_at
                         FROM projects
                         WHERE created_by = :uid
                         ORDER BY created_at DESC
                         LIMIT 50");
    $s1->execute([':uid' => $user['id']]);
    $createdProjects = $s1->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

try {
    $s2 = $pdo->prepare("SELECT p.id, p.name, p.priority, p.status, p.start_date, p.due_date, pu.project_role, p.created_at
                         FROM project_users pu
                         JOIN projects p ON p.id = pu.project_id
                         WHERE pu.user_id = :uid
                         ORDER BY p.created_at DESC
                         LIMIT 50");
    $s2->execute([':uid' => $user['id']]);
    $assignedProjects = $s2->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function roleBadge($r){ return (int)$r===1 ? '<span class="badge badge-admin">admin</span>' : '<span class="badge badge-user">user</span>'; }
function statusBadge($s){
    $map = [
        'planning'     => ['bg'=>'#e0e7ff','fg'=>'#1e3a8a','txt'=>'planning'],
        'in_progress'  => ['bg'=>'#dcfce7','fg'=>'#14532d','txt'=>'in_progress'],
        'on_hold'      => ['bg'=>'#fee2e2','fg'=>'#7f1d1d','txt'=>'on_hold'],
        'completed'    => ['bg'=>'#fef9c3','fg'=>'#713f12','txt'=>'completed'],
    ];
    $m = $map[$s] ?? ['bg'=>'#f3f4f6','fg'=>'#374151','txt'=>h($s)];
    return '<span class="chip" style="background:'.$m['bg'].';color:'.$m['fg'].'">'.$m['txt'].'</span>';
}
function priorityPill($p){
    $map = [
        'low'    => ['bg'=>'#e5e7eb','fg'=>'#111827','txt'=>'low'],
        'medium' => ['bg'=>'#dbeafe','fg'=>'#1e40af','txt'=>'medium'],
        'high'   => ['bg'=>'#fee2e2','fg'=>'#991b1b','txt'=>'high'],
    ];
    $m = $map[$p] ?? ['bg'=>'#f3f4f6','fg'=>'#374151','txt'=>h($p)];
    return '<span class="pill" style="background:'.$m['bg'].';color:'.$m['fg'].'">'.$m['txt'].'</span>';
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kullanıcı - <?= h($user['username']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="css/stylesheet.css" rel="stylesheet" type="text/css">
    <style>
        .container { padding: 24px; }
        .card { background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:24px;margin:24px; }
        .header { display:flex; align-items:center; gap:16px; }
        .avatar { width:72px;height:72px;border-radius:50%;object-fit:cover; }
        .muted { color:#6b7280 }
        .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; }
        .badge-admin { background:#fee2e2; color:#991b1b; }
        .badge-user { background:#e0e7ff; color:#1e3a8a; }
        .pill { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; }
        .chip { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; }

        .grid { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
        @media (max-width: 900px){ .grid { grid-template-columns: 1fr; } }

        table { width:100%; border-collapse: collapse; }
        th, td { padding:10px 12px; border-bottom:1px solid #eee; text-align:left; }
        th { font-size:12px; text-transform:uppercase; color:#6b7280; }

        .btn { display:inline-block; padding:10px 14px; border-radius:10px; text-decoration:none; }
        .btn-dark { background:#111827; color:#fff; }
        .btn-light { background:#e5e7eb; color:#111827; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main">
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div class="card">
            <div class="header">
                <img class="avatar" src="<?= h($user['pp'] ?: 'uploads/default.png') ?>" alt="pp">
                <div>
                    <h2 style="margin:0;"><?= h($user['username']) ?></h2>
                    <div class="muted">#<?= (int)$user['id'] ?> • <?= roleBadge((int)$user['role']) ?></div>
                    <div class="muted">Kayıt: <?= h(date('d.m.Y H:i', strtotime($user['created_at']))) ?></div>
                </div>
                <div style="margin-left:auto; display:flex; gap:8px;">
                    <a class="btn btn-light" href="javascript:history.back()">Geri Dön</a>
                </div>
            </div>
        </div>

        <div class="grid">
            <?php if ($_SESSION['role'] == 1): ?>
            <div class="card">
                <h3 style="margin:0 0 12px;">Oluşturduğu Projeler</h3>
                <?php if (empty($createdProjects)): ?>
                    <div class="muted">Kayıt yok.</div>
                <?php else: ?>
                    <table>
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ad</th>
                            <th>Öncelik</th>
                            <th>Durum</th>
                            <th>Başlangıç</th>
                            <th>Bitiş</th>
                            <th>Aksiyon</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($createdProjects as $p): ?>
                            <tr>
                                <td><?= (int)$p['id'] ?></td>
                                <td><?= h($p['name']) ?></td>
                                <td><?= priorityPill($p['priority']) ?></td>
                                <td><?= statusBadge($p['status']) ?></td>
                                <td><?= $p['start_date'] ? h(date('d.m.Y', strtotime($p['start_date']))) : '-' ?></td>
                                <td><?= $p['due_date'] ? h(date('d.m.Y', strtotime($p['due_date']))) : '-' ?></td>
                                <td><a href="project_view.php?id=<?= (int)$p['id'] ?>">Görüntüle</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <?php
            endif;
            ?>
            <div class="card">
                <h3 style="margin:0 0 12px;">Atandığı Projeler</h3>
                <?php if (empty($assignedProjects)): ?>
                    <div class="muted">Kayıt yok.</div>
                <?php else: ?>
                    <table>
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ad</th>
                            <th>Rol</th>
                            <th>Öncelik</th>
                            <th>Durum</th>
                            <th>Başlangıç</th>
                            <th>Bitiş</th>
                            <th>Aksiyon</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($assignedProjects as $p): ?>
                            <tr>
                                <td><?= (int)$p['id'] ?></td>
                                <td><?= h($p['name']) ?></td>
                                <td><span class="pill" style="background:#f3f4f6;color:#374151"><?= h($p['project_role']) ?></span></td>
                                <td><?= priorityPill($p['priority']) ?></td>
                                <td><?= statusBadge($p['status']) ?></td>
                                <td><?= $p['start_date'] ? h(date('d.m.Y', strtotime($p['start_date']))) : '-' ?></td>
                                <td><?= $p['due_date'] ? h(date('d.m.Y', strtotime($p['due_date']))) : '-' ?></td>
                                <td><a href="project_view.php?id=<?= (int)$p['id'] ?>">Görüntüle</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
</body>
</html>

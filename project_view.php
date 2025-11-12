<?php
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'includes/db.php';

$isAdmin = ((int)($_SESSION['role'] ?? 0) === 1);
$selfId  = (int)$_SESSION['user_id'];
$projectId = (int)($_GET['id'] ?? 0);
if ($projectId <= 0) { http_response_code(400); die("Geçersiz proje ID"); }

$canView = false;
if ($isAdmin) {
    $canView = true;
} else {
    $chk = $pdo->prepare("
        SELECT 1
        FROM projects p
        LEFT JOIN project_users pu
            ON pu.project_id = p.id AND pu.user_id = :uid_join
        WHERE p.id = :pid
          AND (p.created_by = :uid_where OR pu.user_id IS NOT NULL)
        LIMIT 1
    ");
    $chk->execute([
        ':uid_join'  => $selfId,
        ':uid_where' => $selfId,
        ':pid'       => $projectId,
    ]);
    $canView = (bool)$chk->fetchColumn();
}

if (!$canView) {
    http_response_code(403);
    die("Bu projeyi görüntüleme yetkin yok.");
}

$proj = $pdo->prepare("
    SELECT p.id, p.name, p.description, p.technologies, p.priority, p.status,
           p.start_date, p.due_date, p.repo_url, p.created_by, p.created_at,
           u.username AS owner_name, u.pp AS owner_pp
    FROM projects p
    LEFT JOIN users u ON u.id = p.created_by
    WHERE p.id = :pid
    LIMIT 1
");
$proj->execute([':pid' => $projectId]);
$project = $proj->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    http_response_code(404);
    die("Proje bulunamadı.");
}

$ass = $pdo->prepare("
    SELECT pu.user_id, pu.project_role, pu.assigned_at,
           u.username, u.pp, u.role
    FROM project_users pu
    JOIN users u ON u.id = pu.user_id
    WHERE pu.project_id = :pid
    ORDER BY u.username ASC
");
$ass->execute([':pid' => $projectId]);
$assigned = $ass->fetchAll(PDO::FETCH_ASSOC);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function pill($txt, $bg, $fg){ return '<span class="pill" style="background:'.$bg.';color:'.$fg.'">'.h($txt).'</span>'; }
function priorityPill($p){
    $map = [
        'low'    => ['#e5e7eb','#111827'],
        'medium' => ['#dbeafe','#1e40af'],
        'high'   => ['#fee2e2','#991b1b'],
    ];
    [$bg,$fg] = $map[$p] ?? ['#f3f4f6', '#374151'];
    return pill($p, $bg, $fg);
}
function statusBadge($s){
    $map = [
        'planning'     => ['#e0e7ff','#1e3a8a'],
        'in_progress'  => ['#dcfce7','#14532d'],
        'on_hold'      => ['#fee2e2','#7f1d1d'],
        'completed'    => ['#fef9c3','#713f12'],
    ];
    [$bg,$fg] = $map[$s] ?? ['#f3f4f6', '#374151'];
    return pill($s, $bg, $fg);
}
$techs = array_filter(array_map('trim', explode(',', (string)$project['technologies'])));
$isOwner = ((int)$project['created_by'] === $selfId);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Proje #<?= (int)$project['id'] ?> - <?= h($project['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="css/stylesheet.css" rel="stylesheet" type="text/css">
    <style>
        .container { padding:24px; }
        .card { background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:24px;margin:24px; }
        .header { display:flex; align-items:center; gap:16px; }
        .owner { display:flex; align-items:center; gap:10px; }
        .avatar { width:40px;height:40px;border-radius:50%;object-fit:cover; }
        .muted { color:#6b7280 }
        .pill { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; margin-right:6px; margin-bottom:6px; }
        .row { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
        @media (max-width: 900px){ .row { grid-template-columns: 1fr; } }
        table { width:100%; border-collapse: collapse; }
        th, td { padding:10px 12px; border-bottom:1px solid #eee; text-align:left; }
        th { font-size:12px; text-transform:uppercase; color:#6b7280; }
        .btn { display:inline-block; padding:10px 14px; border-radius:10px; text-decoration:none; }
        .btn-dark { background:#111827; color:#fff; }
        .btn-light { background:#e5e7eb; color:#111827; }
        .techs { margin-top:8px; }
        .kpis { display:flex; gap:10px; flex-wrap:wrap; }
        .kpi { background:#f9fafb; border:1px solid #eee; border-radius:12px; padding:10px 12px; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main">
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div class="card">
            <div class="header">
                <div>
                    <h2 style="margin:0;"><?= h($project['name']) ?> <span class="muted">#<?= (int)$project['id'] ?></span></h2>
                    <div class="kpis" style="margin-top:6px;">
                        <?= priorityPill($project['priority']) ?>
                        <?= statusBadge($project['status']) ?>
                        <span class="kpi">Başlangıç: <strong><?= $project['start_date'] ? h(date('d.m.Y', strtotime($project['start_date']))) : '-' ?></strong></span>
                        <span class="kpi">Bitiş: <strong><?= $project['due_date'] ? h(date('d.m.Y', strtotime($project['due_date']))) : '-' ?></strong></span>
                        <span class="kpi">Oluşturulma: <strong><?= h(date('d.m.Y H:i', strtotime($project['created_at']))) ?></strong></span>
                    </div>
                </div>
                <div style="margin-left:auto; display:flex; gap:8px;">
                    <a class="btn btn-light" href="javascript:history.back()">Geri Dön</a>
                    <?php if ($isAdmin || $isOwner): ?>
                        <a class="btn btn-dark" href="project_edit.php?id=<?= (int)$project['id'] ?>">Düzenle</a>
                    <?php endif; ?>
                </div>
            </div>

            <div style="margin-top:10px;" class="owner">
                <img class="avatar" src="<?= h($project['owner_pp'] ?: 'uploads/default.png') ?>" alt="owner">
                <div class="muted">Sahip / Oluşturan: <strong><?= h($project['owner_name'] ?? '—') ?></strong></div>
            </div>

            <?php if (!empty($project['repo_url'])): ?>
                <div style="margin-top:8px;">
                    Repo: <a href="<?= h($project['repo_url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($project['repo_url']) ?></a>
                </div>
            <?php endif; ?>

            <div style="margin-top:12px;">
                <div class="muted">Açıklama</div>
                <div><?= nl2br(h($project['description'] ?: '—')) ?></div>
            </div>

            <div class="techs">
                <div class="muted">Teknolojiler</div>
                <?php if ($techs): ?>
                    <?php foreach ($techs as $t): ?>
                        <span class="pill" style="background:#eef2ff;color:#1e3a8a"><?= h($t) ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="muted">—</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="card">
                <h3 style="margin:0 0 12px;">Atanan Kullanıcılar</h3>
                <?php if (empty($assigned)): ?>
                    <div class="muted">Bu projeye atanmış kullanıcı yok.</div>
                <?php else: ?>
                    <table>
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı</th>
                            <th>Rol</th>
                            <th>Atanma</th>
                            <th>Aksiyon</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($assigned as $a): ?>
                            <tr>
                                <td><?= (int)$a['user_id'] ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <img class="avatar" style="width:28px;height:28px;" src="<?= h($a['pp'] ?: 'uploads/default.png') ?>" alt="pp">
                                        <div>
                                            <div><strong><?= h($a['username']) ?></strong></div>
                                            <div class="muted"><?= ((int)$a['role']===1 ? 'admin' : 'user') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="pill" style="background:#f3f4f6;color:#374151"><?= h($a['project_role']) ?></span></td>
                                <td><?= h(date('d.m.Y H:i', strtotime($a['assigned_at']))) ?></td>
                                <td><a href="user_view.php?id=<?= (int)$a['user_id'] ?>">Görüntüle</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3 style="margin:0 0 12px;">Özet</h3>
                <ul style="margin:0 0 12px 16px;">
                    <li>Öncelik: <strong><?= h($project['priority']) ?></strong></li>
                    <li>Durum: <strong><?= h($project['status']) ?></strong></li>
                    <li>Teknoloji sayısı: <strong><?= count($techs) ?></strong></li>
                    <li>Atanan kişi sayısı: <strong><?= count($assigned) ?></strong></li>
                </ul>
                <div class="muted">Bu alanı notlar, linkler veya dokümantasyon bağlantıları için kullanabilirsin.</div>
            </div>
        </div>

    </div>
</div>
</body>
</html>

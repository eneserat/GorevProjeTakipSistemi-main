<?php
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'includes/db.php';

try { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Throwable $e) {}

$isAdmin = ((int)($_SESSION['role'] ?? 0) === 1);
$selfId  = (int)$_SESSION['user_id'];

$q          = trim((string)($_GET['q'] ?? ''));
$roleFilter = (string)($_GET['role'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = (int)($_GET['limit'] ?? 20);
$limit      = $limit > 100 ? 100 : ($limit < 5 ? 5 : $limit);
$offset     = ($page - 1) * $limit;

$where  = [];
$params = [];

if ($isAdmin) {
    if ($q !== '') {
        $where[]      = "u.username LIKE :q";
        $params[':q'] = "%{$q}%";
    }
    if ($roleFilter !== '' && in_array($roleFilter, ['0','1'], true)) {
        $where[]      = "u.role = :r";
        $params[':r'] = (int)$roleFilter;
    }
} else {
    $where[]        = "u.id = :self";
    $params[':self'] = $selfId;
    $q = '';
    $roleFilter = '';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

try {
    $sqlCount = "SELECT COUNT(*) 
                 FROM users u
                 {$whereSql}";
    $stmtC = $pdo->prepare($sqlCount);
    $stmtC->execute($params);
    $total = (int)$stmtC->fetchColumn();
} catch (Throwable $e) {
    $total = 0;
}

$pages = max(1, (int)ceil($total / $limit));

try {
    $sql = "SELECT 
                u.id,
                u.username,
                u.role,
                COALESCE(NULLIF(u.pp,''), 'uploads/default.png') AS pp,
                u.created_at
            FROM users u
            {$whereSql}
            ORDER BY u.created_at DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $rows = [];
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function roleBadge($r){ return ((int)$r===1) ? '<span class="badge badge-admin">admin</span>' : '<span class="badge badge-user">user</span>'; }
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kullanıcılar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root{ color-scheme: light dark; }
        body {
            margin: 0;
            display: flex;
            height: 100vh;
            font-family: ui-sans-serif, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            background: #f8fafc;
            color:#0f172a;
        }
        .card { background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:16px;margin:16px; }
        .toolbar { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px; }
        .toolbar input, .toolbar select { padding:10px;border:1px solid #e5e7eb;border-radius:10px; background:#fff; }
        .toolbar button { padding:10px 14px;border:0;border-radius:10px;background:#111827;color:#fff; cursor:pointer; }
        .toolbar a { text-decoration:none; }
        .table { width:100%; border-collapse: collapse; background:#fff; border-radius:12px; overflow:hidden; }
        .table th, .table td { padding:10px 12px; border-bottom:1px solid #eee; text-align:left; }
        .table th { font-size:12px; text-transform:uppercase; color:#6b7280; letter-spacing:.02em; background:#f9fafb; }
        .user-cell { display:flex; align-items:center; gap:10px; }
        .user-cell img { width:34px; height:34px; border-radius:50%; object-fit:cover; }
        .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; }
        .badge-admin { background:#fee2e2; color:#991b1b; }
        .badge-user  { background:#e0e7ff; color:#1e3a8a; }
        .actions a { text-decoration:none; font-size:13px; margin-right:10px; color:#0f172a; }
        .pagination { display:flex; gap:6px; margin-top:12px; flex-wrap:wrap; }
        .pagination a, .pagination span { padding:8px 12px; border:1px solid #e5e7eb; border-radius:10px; text-decoration:none; color:#111827; background:#fff; }
        .pagination .active { background:#111827; color:#fff; border-color:#111827; }
        .muted { color:#6b7280; font-size:12px; }
        .meta { display:flex; justify-content:space-between; align-items:center; margin-top:8px; }
        .main { flex: 1; padding: 20px; overflow-y: auto; }
        @media (prefers-color-scheme: dark){
            body{ background:#0b1220; color:#e5e7eb; }
            .card, .table, .toolbar input, .toolbar select, .pagination a, .pagination span { background:#0f172a; border-color:#1f2a44; color:#e5e7eb; }
            .table th{ background:#0c162b; color:#94a3b8; }
            .toolbar button { background:#2563eb; }
        }
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>
<div class="main">
    <?php include 'includes/header.php'; ?>

    <div class="card">
        <h3 style="margin:0 0 12px;">Kullanıcılar</h3>

        <?php if ($isAdmin): ?>
            <form class="toolbar" method="get" action="">
                <input type="text" name="q" value="<?= h($q) ?>" placeholder="Kullanıcı ara (username)">
                <select name="role">
                    <option value="">Tümü (rol)</option>
                    <option value="1" <?= $roleFilter==='1'?'selected':''; ?>>Admin</option>
                    <option value="0" <?= $roleFilter==='0'?'selected':''; ?>>User</option>
                </select>
                <select name="limit">
                    <?php foreach([10,20,50,100] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $limit===$opt?'selected':''; ?>>Sayfa başı <?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Filtrele</button>
                <?php if ($q!=='' || $roleFilter!==''): ?>
                    <a href="?limit=<?= (int)$limit ?>" class="muted">Sıfırla</a>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <div class="muted" style="margin-bottom:12px;">Sadece kendi hesabınızı görüyorsunuz.</div>
        <?php endif; ?>

        <div class="meta">
            <div class="muted">Toplam: <?= (int)$total ?> kullanıcı</div>
            <div class="muted">Sayfa: <?= (int)$page ?> / <?= (int)$pages ?></div>
        </div>

        <table class="table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Kullanıcı</th>
                <th>Rol</th>
                <th>Oluşturulma</th>
                <th>Aksiyon</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="5" class="muted">Kayıt bulunamadı.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <?php
                $id    = (int)($r['id'] ?? 0);
                $uname = $r['username'] ?? '(isim yok)';
                $urole = (int)($r['role'] ?? 0);
                $pp    = $r['pp'] ?? 'uploads/default.png';
                $ctime = !empty($r['created_at']) ? date('d.m.Y H:i', strtotime($r['created_at'])) : '-';
                ?>
                <tr>
                    <td><?= $id ?></td>
                    <td>
                        <div class="user-cell">
                            <img src="<?= h($pp) ?>" alt="pp" onerror="this.src='uploads/default.png'">
                            <div>
                                <div><strong><?= h($uname) ?></strong></div>
                                <div class="muted">#<?= $id ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= roleBadge($urole) ?></td>
                    <td><?= h($ctime) ?></td>
                    <td class="actions">
                        <a href="user_view.php?id=<?= $id ?>">Görüntüle</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <?php if ($pages > 1): ?>
            <?php parse_str($_SERVER['QUERY_STRING'] ?? '', $qs); ?>
            <div class="pagination">
                <?php
                $qs['page'] = max(1, $page-1);
                echo '<a href="?'.h(http_build_query($qs)).'">&laquo; Önceki</a>';

                $window = 2;
                for ($p = 1; $p <= $pages; $p++) {
                    if ($p==1 || $p==$pages || ($p >= $page-$window && $p <= $page+$window)) {
                        $qs['page'] = $p;
                        $cls = $p==$page ? 'class="active"' : '';
                        echo '<a '.$cls.' href="?'.h(http_build_query($qs)).'">'.$p.'</a>';
                    } elseif ($p == 2 && $page-$window > 3) {
                        echo '<span>…</span>';
                    } elseif ($p == $pages-1 && $page+$window < $pages-2) {
                        echo '<span>…</span>';
                    }
                }

                $qs['page'] = min($pages, $page+1);
                echo '<a href="?'.h(http_build_query($qs)).'">Sonraki &raquo;</a>';
                ?>
            </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>

<?php
session_start();
require_once 'includes/db.php';

$q          = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = (int)($_GET['limit'] ?? 20);
$limit      = $limit > 100 ? 100 : ($limit < 5 ? 5 : $limit);
$offset     = ($page - 1) * $limit;

$where = [];
$params = [];
if ($q !== '') {
    $where[] = "username LIKE :q";
    $params[':q'] = "%{$q}%";
}
if ($roleFilter !== '' && in_array($roleFilter, ['0','1'], true)) {
    $where[] = "role = :r";
    $params[':r'] = (int)$roleFilter;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sqlCount = "SELECT COUNT(*) AS c FROM users {$whereSql}";
$stmtC = $pdo->prepare($sqlCount);
$stmtC->execute($params);
$total = (int)$stmtC->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));

$sql = "SELECT id, username, role, pp, created_at
        FROM users
        {$whereSql}
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function roleBadge($r){ return $r==1 ? '<span class="badge badge-admin">admin</span>' : '<span class="badge badge-user">user</span>'; }
?>

<style>
    body {
        margin: 0;
        display: flex;
        height: 100vh;
        font-family: 'Arial', sans-serif;
    }
    .card { background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:16px;margin:16px; }
    .toolbar { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px; }
    .toolbar input, .toolbar select { padding:10px;border:1px solid #e5e7eb;border-radius:10px; }
    .toolbar button { padding:10px 14px;border:0;border-radius:10px;background:#111827;color:#fff; cursor:pointer; }
    .table { width:100%; border-collapse: collapse; }
    .table th, .table td { padding:10px 12px; border-bottom:1px solid #eee; text-align:left; }
    .table th { font-size:12px; text-transform:uppercase; color:#6b7280; }
    .user-cell { display:flex; align-items:center; gap:10px; }
    .user-cell img { width:34px; height:34px; border-radius:50%; object-fit:cover; }
    .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; }
    .badge-admin { background:#fee2e2; color:#991b1b; }
    .badge-user { background:#e0e7ff; color:#1e3a8a; }
    .actions a { text-decoration:none; font-size:13px; margin-right:10px; }
    .pagination { display:flex; gap:6px; margin-top:12px; flex-wrap:wrap; }
    .pagination a, .pagination span { padding:8px 12px; border:1px solid #e5e7eb; border-radius:10px; text-decoration:none; color:#111827; }
    .pagination .active { background:#111827; color:#fff; border-color:#111827; }
    .muted { color:#6b7280; font-size:12px; }
    .meta { display:flex; justify-content:space-between; align-items:center; margin-top:8px; }
    .main {
        flex: 1;
        padding: 20px;
        overflow-y: auto;
    }
</style>
<?php
include 'includes/sidebar.php';
?>
<div class="main">
    <?php
    include 'includes/header.php';
    ?>
    <div class="card">
        <h3 style="margin:0 0 12px;">Kullanıcılar</h3>

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

        <div class="meta">
            <div class="muted">Toplam: <?= $total ?> kullanıcı</div>
            <div class="muted">Sayfa: <?= $page ?> / <?= $pages ?></div>
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
                <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td>
                        <div class="user-cell">
                            <img src="<?= h($r['pp'] ?: 'uploads/default.png') ?>" alt="pp">
                            <div>
                                <div><strong><?= h($r['username']) ?></strong></div>
                                <div class="muted">#<?= (int)$r['id'] ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= roleBadge((int)$r['role']) ?></td>
                    <td><?= h(date('d.m.Y H:i', strtotime($r['created_at']))) ?></td>
                    <td class="actions">
                        <a href="user_view.php?id=<?= (int)$r['id'] ?>">Görüntüle</a>
                        <a href="user_edit.php?id=<?= (int)$r['id'] ?>">Düzenle</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <?php
        if ($pages > 1):
            parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
            ?>
            <div class="pagination">
                <?php
                $qs['page'] = max(1, $page-1);
                echo '<a href="?'.h(http_build_query($qs)).'">&laquo; Önceki</a>';

                $window = 2;
                for ($p = 1; $p <= $pages; $p++) {
                    if ($p==1 || $p==$pages || ($p >= $page-$window && $p <= $page+$window)) {
                        $qs['page'] = $p;
                        $cls = $p==$page ? 'class="active"' : '';
                        echo '<a '.$cls.' href="?'.h(http_build_query($qs)).'">'. $p .'</a>';
                    } elseif ($p == 2 && $page-$window > 3) {
                        echo '<span>…</span>'; // left ellipsis
                    } elseif ($p == $pages-1 && $page+$window < $pages-2) {
                        echo '<span>…</span>'; // right ellipsis
                    }
                }

                $qs['page'] = min($pages, $page+1);
                echo '<a href="?'.h(http_build_query($qs)).'">Sonraki &raquo;</a>';
                ?>
            </div>
        <?php endif; ?>
    </div>
</div>

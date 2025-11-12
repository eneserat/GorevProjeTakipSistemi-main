<?php
session_start();
if (!isset($_SESSION['username'])) { header("Location: login.php"); exit; }
require_once 'includes/db.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) { die("Geçersiz kullanıcı."); }

$q        = trim($_GET['q'] ?? '');
$status   = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = min(100, max(10, (int)($_GET['limit'] ?? 20)));
$offset   = ($page - 1) * $limit;

$where  = ["t.assigned_user_id = :me"];
$params = [':me' => $user_id];

if ($q !== '') {
    $where[] = "(t.title LIKE :q OR t.details LIKE :q)";
    $params[':q'] = "%{$q}%";
}
if ($status !== '' && in_array($status, ['todo','in_progress','on_hold','completed'], true)) {
    $where[] = "t.status = :status";
    $params[':status'] = $status;
}
if ($priority !== '' && in_array($priority, ['low','medium','high'], true)) {
    $where[] = "t.priority = :priority";
    $params[':priority'] = $priority;
}
$whereSql = "WHERE ".implode(" AND ", $where);

$sqlCount = "SELECT COUNT(*) FROM tasks t {$whereSql}";
$stc = $pdo->prepare($sqlCount);
$stc->execute($params);
$total = (int)$stc->fetchColumn();
$pages = (int)ceil($total / $limit);

$sql = "
SELECT
  t.id, t.title, t.priority, t.status, t.start_date, t.due_date, t.language,
  t.repo_url, t.assigned_user_id, t.created_by, t.created_at,
  cu.username AS creator_name
FROM tasks t
LEFT JOIN users cu ON cu.id = t.created_by
{$whereSql}
ORDER BY t.id DESC
LIMIT :limit OFFSET :offset
";
$st = $pdo->prepare($sql);
foreach ($params as $k=>$v) { $st->bindValue($k, $v); }
$st->bindValue(':limit', $limit, PDO::PARAM_INT);
$st->bindValue(':offset', $offset, PDO::PARAM_INT);
$st->execute();
$tasks = $st->fetchAll(PDO::FETCH_ASSOC);

function badgeStatus($s) {
    $map = [
        'todo' => '#e5e7eb',
        'in_progress' => '#fde68a',
        'on_hold' => '#fca5a5',
        'completed' => '#86efac'
    ];
    $bg = $map[$s] ?? '#e5e7eb';
    return "<span style='background:{$bg};padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600'>{$s}</span>";
}
function badgePriority($p) {
    $map = [
        'low' => '#bfdbfe',
        'medium' => '#fde68a',
        'high' => '#fca5a5'
    ];
    $bg = $map[$p] ?? '#e5e7eb';
    return "<span style='background:{$bg};padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600'>{$p}</span>";
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Görevlerim</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="css/stylesheet.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@600&display=swap" rel="stylesheet">
    <style>
        .container { padding:24px; }
        .card { background:#fff;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.07);padding:16px; }
        .filters { display:grid;grid-template-columns:1fr 1fr 1fr 120px;gap:8px;align-items:end;margin-bottom:12px; }
        .filters input, .filters select { width:100%;padding:10px;border:1px solid #e6e6eb;border-radius:10px; }
        .table { width:100%; border-collapse: collapse;}
        .table th, .table td { text-align:left; padding:10px; border-bottom:1px solid #eee; vertical-align:middle; }
        .table th { font-size:13px; text-transform:uppercase; letter-spacing:.03em; color:#6b7280; }
        .badge { padding:4px 10px; border-radius:999px; font-size:12px; font-weight:600; }
        .pager { display:flex; gap:8px; margin-top:12px; align-items:center; }
        .pager a, .pager span { padding:8px 12px; border-radius:10px; border:1px solid #e6e6eb; text-decoration:none; color:#111827; }
        .pager .active { background:#111827; color:#fff; }
        .actions a { text-decoration:none; font-weight:700; }
        .btnComment {
            padding: 8px 12px; border: 1px solid #e6e6eb; border-radius: 10px;
            background: #fff; cursor: pointer; font-weight: 700;
        }
        .btnComment:hover { background: #f5f5f7; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main">
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <h2 style="margin-bottom:12px;">Görevlerim</h2>

        <div class="card">
            <form class="filters" method="get" autocomplete="off">
                <input type="text" name="q" placeholder="Ara: başlık / detay" value="<?= htmlspecialchars($q) ?>">
                <select name="status">
                    <option value="">Durum (tümü)</option>
                    <?php foreach (['todo'=>'To Do','in_progress'=>'In Progress','on_hold'=>'On Hold','completed'=>'Completed'] as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= $status===$k?'selected':'' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="priority">
                    <option value="">Öncelik (tümü)</option>
                    <?php foreach (['low'=>'Low','medium'=>'Medium','high'=>'High'] as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= $priority===$k?'selected':'' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" style="padding:10px;border:none;border-radius:10px;background:#2f80ed;color:#fff;font-weight:700;cursor:pointer;">Filtre</button>
            </form>

            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Başlık</th>
                        <th>Öncelik</th>
                        <th>Durum</th>
                        <th>Başlangıç</th>
                        <th>Bitiş</th>
                        <th>Dil/Tech</th>
                        <th>Repo</th>
                        <th>Oluşturan</th>
                        <th>Yorum</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$tasks): ?>
                        <tr><td colspan="9">Kayıt bulunamadı.</td></tr>
                    <?php else: foreach ($tasks as $t): ?>
                        <tr>
                            <td>#<?= (int)$t['id'] ?></td>
                            <td><?= htmlspecialchars($t['title']) ?></td>
                            <td><?= badgePriority($t['priority']) ?></td>
                            <td><?= badgeStatus($t['status']) ?></td>
                            <td><?= $t['start_date'] ?: '—' ?></td>
                            <td><?= $t['due_date'] ?: '—' ?></td>
                            <td><?= htmlspecialchars($t['language'] ?: '-') ?></td>
                            <td>
                                <?php if ($t['repo_url']): ?>
                                    <a class="actions" href="<?= htmlspecialchars($t['repo_url']) ?>" target="_blank">Git</a>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($t['creator_name'] ?? '—') ?></td>
                            <td>
                                <button class="btnComment" type="button"
                                        data-task-id="<?= (int)$t['id'] ?>"
                                        data-task-title="<?= htmlspecialchars($t['title']) ?>">
                                    Yorum
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pages > 1): ?>
                <div class="pager">
                    <?php
                    if ($page > 1) {
                        $qstr = $_GET; $qstr['page'] = $page - 1;
                        echo '<a href="?'.http_build_query($qstr).'">&laquo; Önceki</a>';
                    } else {
                        echo '<span>&laquo; Önceki</span>';
                    }
                    for ($i=1; $i <= $pages; $i++) {
                        if ($i == 1 || $i == $pages || abs($i - $page) <= 2) {
                            $qstr = $_GET; $qstr['page'] = $i;
                            $cls = $i==$page?'active':'';
                            echo '<a class="'.$cls.'" href="?'.http_build_query($qstr).'">'.$i.'</a>';
                        } elseif ($i == 2 && $page > 4) {
                            echo '<span>...</span>';
                        } elseif ($i == $pages-1 && $page < $pages-3) {
                            echo '<span>...</span>';
                        }
                    }
                    if ($page < $pages) {
                        $qstr = $_GET; $qstr['page'] = $page + 1;
                        echo '<a href="?'.http_build_query($qstr).'">Sonraki &raquo;</a>';
                    } else {
                        echo '<span>Sonraki &raquo;</span>';
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<div id="commentModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:9999;">
    <div style="position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); width:min(640px,92vw);">
        <div style="background:#fff; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,.2); overflow:hidden;">
            <div style="padding:14px 16px; border-bottom:1px solid #eee; display:flex; align-items:center; justify-content:space-between;">
                <div id="cmTitle" style="font-weight:800;">Yorum</div>
                <button id="cmClose" type="button" style="background:none; border:none; font-size:20px; cursor:pointer;">×</button>
            </div>

            <div style="padding:14px 16px; display:grid; gap:12px;">
                <div id="cmList" style="max-height:280px; overflow:auto; border:1px solid #eee; border-radius:10px; padding:10px;"></div>


                <textarea id="cmText" rows="3" placeholder="Yorumunu yaz..."
                          style="width:100%; padding:10px; border:1px solid #e6e6eb; border-radius:10px;"></textarea>

                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button id="cmCancel" class="btnComment" type="button">İptal</button>
                    <button id="cmSend" class="btnComment" type="button"
                            style="background:#2f80ed; color:#fff; border-color:#2f80ed;">Gönder</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal    = document.getElementById('commentModal');
        const titleEl  = document.getElementById('cmTitle');
        const listEl   = document.getElementById('cmList');
        const textEl   = document.getElementById('cmText');
        const btnSend  = document.getElementById('cmSend');
        const btnClose = document.getElementById('cmClose');
        const btnCancel= document.getElementById('cmCancel');

        function openModal(taskId, title){
            modal.dataset.taskId = String(taskId);
            titleEl.textContent = 'Yorum – #' + taskId + ' · ' + (title || '');
            textEl.value = '';
            modal.style.display = 'block';
            loadComments();
            setTimeout(()=> textEl.focus(), 0);
        }

        function closeModal(){
            modal.style.display = 'none';
            listEl.innerHTML = '';
            delete modal.dataset.taskId;
        }

        function getTaskId(){
            const tid = parseInt(modal.dataset.taskId || '0', 10);
            return Number.isFinite(tid) ? tid : 0;
        }

        async function loadComments(){
            const taskId = getTaskId();
            if (!taskId) { listEl.textContent = 'Geçersiz task'; return; }
            listEl.textContent = 'Yükleniyor...';
            try {
                const res = await fetch('task_comments_list.php?task_id=' + encodeURIComponent(taskId));
                const json = await res.json();
                if (!json.ok) { listEl.textContent = json.message || 'Yüklenemedi'; return; }
                if (!json.items.length) { listEl.innerHTML = '<div style="opacity:.6">Henüz yorum yok.</div>'; return; }
                listEl.innerHTML = json.items.map(it => `
        <div style="padding:8px 10px; border-bottom:1px solid #f0f0f0;">
          <div style="font-weight:700;">${escapeHtml(it.username || 'Kullanıcı')}
            <span style="opacity:.6;font-weight:400;">· ${it.created_at}</span>
          </div>
          <div style="white-space:pre-wrap; margin-top:4px;">${escapeHtml(it.comment_text)}</div>
        </div>
      `).join('');
            } catch(e){
                listEl.textContent = 'Hata: ' + e.message;
            }
        }

        async function sendComment(){
            const taskId = getTaskId();
            const val = (textEl.value || '').trim();
            if (!taskId) { alert('Görev bulunamadı.'); return; }
            if (!val)    { alert('Yorum boş olamaz.'); return; }

            btnSend.disabled = true;
            try {
                const res = await fetch('task_comments_add.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({ task_id: taskId, comment_text: val })
                });
                const json = await res.json();
                if (!json.ok) { alert(json.message || 'Kaydedilemedi'); btnSend.disabled = false; return; }
                textEl.value = '';
                await loadComments();
            } catch(e){
                alert('Hata: ' + e.message);
            }
            btnSend.disabled = false;
        }

        function escapeHtml(s){
            return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
        }

        document.querySelectorAll('table .btnComment').forEach(b=>{
            b.addEventListener('click', () => openModal(b.dataset.taskId, b.dataset.taskTitle || ''));
        });

        btnSend.addEventListener('click', sendComment);
        btnClose.addEventListener('click', closeModal);
        btnCancel.addEventListener('click', closeModal);
        modal.addEventListener('click', (e)=>{ if (e.target === modal) closeModal(); });
    });
</script>
</body>
</html>

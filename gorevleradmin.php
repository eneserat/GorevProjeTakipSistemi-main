<?php
session_start();
if (!isset($_SESSION['username'])) { header("Location: login.php"); exit; }
require_once 'includes/db.php';

$rol      = (int)($_SESSION['role'] ?? 0);
$user_id  = (int)($_SESSION['user_id'] ?? 0);
if ($rol !== 1) { die("Bu sayfayı sadece admin görebilir."); }

$q        = trim($_GET['q'] ?? '');
$status   = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$assignee = $_GET['assignee'] ?? ''; // id
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = min(100, max(10, (int)($_GET['limit'] ?? 20)));
$offset   = ($page - 1) * $limit;

$where  = [];
$params = [];

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
if ($assignee !== '' && ctype_digit($assignee)) {
    $where[] = "t.assigned_user_id = :assignee";
    $params[':assignee'] = (int)$assignee;
}
$whereSql = $where ? ("WHERE ".implode(" AND ", $where)) : "";

$sqlCount = "SELECT COUNT(*) FROM tasks t {$whereSql}";
$stc = $pdo->prepare($sqlCount);
$stc->execute($params);
$total = (int)$stc->fetchColumn();
$pages = (int)ceil($total / $limit);

$sql = "
SELECT
  t.id, t.title, t.priority, t.status, t.start_date, t.due_date, t.language,
  t.repo_url, t.assigned_user_id, t.created_by, t.created_at,
  au.username AS assignee_name, au.pp AS assignee_pp,
  cu.username AS creator_name
FROM tasks t
LEFT JOIN users au ON au.id = t.assigned_user_id
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

$usersStmt = $pdo->query("SELECT id, username FROM users ORDER BY username ASC");
$usersAll = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

$columns = [
    'todo'        => [],
    'in_progress' => [],
    'on_hold'     => [],
    'completed'   => [],
];
foreach ($tasks as $t) {
    $k = $t['status'];
    if (!isset($columns[$k])) $k = 'todo';
    $columns[$k][] = $t;
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
    <title>Görevler</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="css/stylesheet.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@600&display=swap" rel="stylesheet">
    <style>
        .container { padding:24px; }
        .card { background:#fff;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.07);padding:16px; }

        .filters { display:grid;grid-template-columns:1fr 1fr 1fr 1fr 120px;gap:8px;align-items:end;margin-bottom:12px; }
        .filters input, .filters select { width:100%;padding:10px;border:1px solid #e6e6eb;border-radius:10px; }

        .kanban {
            display:grid;
            grid-template-columns: repeat(4, 1fr);
            gap:16px;
            align-items:start;
        }
        .kanban-col {
            background:#fafafa;
            border:1px solid #eee;
            border-radius:14px;
            padding:12px;
            min-height: 420px;
            display:flex;
            flex-direction:column;
        }
        .kanban-col-header {
            display:flex;align-items:center;gap:8px;justify-content:space-between;margin-bottom:10px;
        }
        .kanban-col-title { font-weight:800; font-size:14px; text-transform:uppercase; letter-spacing:.04em; color:#374151; }
        .kanban-col-count { background:#111827; color:#fff; padding:2px 8px; border-radius:999px; font-size:12px; font-weight:700; }

        .kanban-dropzone {
            flex:1;
            display:flex;
            flex-direction:column;
            gap:10px;
            min-height: 300px;
            border:2px dashed transparent;
            border-radius:12px;
            padding:4px;
            transition: border-color .15s, background .15s;
        }
        .kanban-dropzone.dragover {
            border-color:#c7d2fe; background:#eef2ff;
        }

        .task-card {
            background:#fff;
            border:1px solid #ececec;
            border-radius:12px;
            padding:10px;
            box-shadow:0 6px 18px rgba(0,0,0,.06);
            cursor:grab;
        }
        .task-card:active { cursor:grabbing; }
        .task-row-top {
            display:flex; align-items:center; gap:8px; justify-content:space-between; margin-bottom:6px;
        }
        .task-id { font-size:12px; font-weight:800; color:#6b7280; }
        .task-title { font-weight:800; font-size:14px; color:#111827; line-height:1.2; }
        .task-meta {
            display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; align-items:center;
            font-size:12px; color:#4b5563;
        }
        .pp {
            width:22px;height:22px;border-radius:999px;object-fit:cover;border:1px solid #e5e7eb;
        }
        .chip {
            border:1px solid #e5e7eb; padding:2px 8px; border-radius:999px; font-weight:700; font-size:11px; background:#fff;
        }
        .task-actions {
            display:flex; gap:8px; margin-top:8px; justify-content:flex-end;
        }
        .btnLink, .btnComment {
            padding:6px 10px; border:1px solid #e6e6eb; border-radius:10px; background:#fff; cursor:pointer; font-weight:700; font-size:12px; text-decoration:none; color:#111827;
        }
        .btnLink:hover, .btnComment:hover { background:#f5f5f7; }

        /* Pager */
        .pager { display:flex; gap:8px; margin-top:12px; align-items:center; flex-wrap:wrap; }
        .pager a, .pager span { padding:8px 12px; border-radius:10px; border:1px solid #e6e6eb; text-decoration:none; color:#111827; }
        .pager .active { background:#111827; color:#fff; }

        @media (max-width: 1100px){
            .kanban{ grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px){
            .filters{ grid-template-columns: 1fr; }
            .kanban{ grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main">
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <h2 style="margin-bottom:12px;">Görevler</h2>

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
                <select name="assignee">
                    <option value="">Atanan (tümü)</option>
                    <?php foreach ($usersAll as $uu): ?>
                        <option value="<?= (int)$uu['id'] ?>" <?= ($assignee!=='' && (int)$assignee===(int)$uu['id'])?'selected':'' ?>>
                            <?= htmlspecialchars($uu['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" style="padding:10px;border:none;border-radius:10px;background:#2f80ed;color:#fff;font-weight:700;cursor:pointer;">Filtre</button>
            </form>

            <div class="kanban" id="kanban">
                <?php
                $heads = [
                    'todo'        => ['To Do', '#e5e7eb'],
                    'in_progress' => ['In Progress', '#fde68a'],
                    'on_hold'     => ['On Hold', '#fca5a5'],
                    'completed'   => ['Completed', '#86efac'],
                ];
                foreach (['todo','in_progress','on_hold','completed'] as $colKey):
                    [$label,$clr] = $heads[$colKey];
                    $items = $columns[$colKey] ?? [];
                    ?>
                    <div class="kanban-col" data-status="<?= $colKey ?>">
                        <div class="kanban-col-header">
                            <div class="kanban-col-title"><?= $label ?></div>
                            <div class="kanban-col-count" id="count-<?= $colKey ?>"><?= count($items) ?></div>
                        </div>
                        <div class="kanban-dropzone" data-status="<?= $colKey ?>">
                            <?php foreach ($items as $t): ?>
                                <?php
                                $pp = $t['assignee_pp'] ?: 'uploads/default.png';
                                ?>
                                <div class="task-card" draggable="true"
                                     data-id="<?= (int)$t['id'] ?>"
                                     data-status="<?= htmlspecialchars($t['status']) ?>">
                                    <div class="task-row-top">
                                        <div class="task-id">#<?= (int)$t['id'] ?></div>
                                        <div><?= badgePriority($t['priority']) ?></div>
                                    </div>
                                    <div class="task-title"><?= htmlspecialchars($t['title']) ?></div>
                                    <div class="task-meta">
                                        <img class="pp" src="<?= htmlspecialchars($pp) ?>" alt="pp">
                                        <span class="chip"><?= htmlspecialchars($t['assignee_name'] ?? '—') ?></span>
                                        <?php if ($t['language']): ?>
                                            <span class="chip"><?= htmlspecialchars($t['language']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($t['start_date']): ?>
                                            <span class="chip">Baş: <?= htmlspecialchars($t['start_date']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($t['due_date']): ?>
                                            <span class="chip">Bitiş: <?= htmlspecialchars($t['due_date']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="task-actions">
                                        <?php if ($t['repo_url']): ?>
                                            <a class="btnLink" href="<?= htmlspecialchars($t['repo_url']) ?>" target="_blank">Repo</a>
                                        <?php endif; ?>
                                        <button class="btnComment" type="button"
                                                data-task-id="<?= (int)$t['id'] ?>"
                                                data-task-title="<?= htmlspecialchars($t['title']) ?>">
                                            Yorumlar
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
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

        document.querySelectorAll('.btnComment').forEach(b=>{
            b.addEventListener('click', () => openModal(b.dataset.taskId, b.dataset.taskTitle || ''));
        });
        btnSend.addEventListener('click', sendComment);
        btnClose.addEventListener('click', closeModal);
        btnCancel.addEventListener('click', closeModal);
        modal.addEventListener('click', (e)=>{ if (e.target === modal) closeModal(); });

        let draggingCard = null;
        document.querySelectorAll('.task-card').forEach(card=>{
            card.addEventListener('dragstart', (e)=>{
                draggingCard = card;
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', card.dataset.id);
                setTimeout(()=> card.style.opacity = '0.5', 0);
            });
            card.addEventListener('dragend', ()=>{
                if (draggingCard) draggingCard.style.opacity = '';
                draggingCard = null;
            });
        });

        document.querySelectorAll('.kanban-dropzone').forEach(zone=>{
            zone.addEventListener('dragover', (e)=>{ e.preventDefault(); zone.classList.add('dragover'); });
            zone.addEventListener('dragleave', ()=> zone.classList.remove('dragover'));
            zone.addEventListener('drop', async (e)=>{
                e.preventDefault();
                zone.classList.remove('dragover');
                if (!draggingCard) return;

                const taskId = draggingCard.dataset.id;
                const currentStatus = draggingCard.dataset.status;
                const nextStatus = zone.dataset.status;
                if (currentStatus === nextStatus) return;

                const prevParent = draggingCard.parentElement;
                zone.appendChild(draggingCard);
                draggingCard.dataset.status = nextStatus;

                updateCounts();

                try {
                    const res = await fetch('update_task_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ id: taskId, status: nextStatus })
                    });
                    const json = await res.json();
                    if (!json.ok) {
                        alert(json.message || 'Güncellenemedi');
                        prevParent.appendChild(draggingCard);
                        draggingCard.dataset.status = currentStatus;
                        updateCounts();
                    }
                } catch (err) {
                    alert('Hata: ' + err.message);
                    prevParent.appendChild(draggingCard);
                    draggingCard.dataset.status = currentStatus;
                    updateCounts();
                }
            });
        });

        function updateCounts(){
            const keys = ['todo','in_progress','on_hold','completed'];
            keys.forEach(k=>{
                const zone = document.querySelector('.kanban-dropzone[data-status="'+k+'"]');
                const count = zone ? zone.querySelectorAll('.task-card').length : 0;
                const el = document.getElementById('count-'+k);
                if (el) el.textContent = count;
            });
        }
        updateCounts();
    });
</script>
</body>
</html>

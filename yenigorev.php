<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

require_once 'includes/db.php';
$kullanici_adi = $_SESSION['username'];
$rol = $_SESSION['role'];
$user_id = $_SESSION['user_id'] ?? null;

if ((int)$rol !== 1) { die("Bu işlem için yetkin yok."); }

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['title'] ?? '');
    $details      = trim($_POST['details'] ?? '');
    $language     = trim($_POST['language'] ?? '');
    $priority     = $_POST['priority'] ?? 'medium';
    $status       = $_POST['status'] ?? 'todo';
    $start_date   = $_POST['start_date'] ?: null;
    $due_date     = $_POST['due_date'] ?: null;
    $repo_url     = trim($_POST['repo_url'] ?? '');
    $assignee_id  = isset($_POST['assignee_id']) && $_POST['assignee_id'] !== '' ? (int)$_POST['assignee_id'] : null;
    $assignee_role= $_POST['assignee_role'] ?? 'dev';

    if ($title === '') $errors[] = "İş ismi (görev başlığı) zorunlu.";
    if (!in_array($priority, ['low','medium','high'], true)) $errors[] = "Geçersiz öncelik.";
    if (!in_array($status, ['todo','in_progress','on_hold','completed'], true)) $errors[] = "Geçersiz durum.";
    if ($repo_url && !filter_var($repo_url, FILTER_VALIDATE_URL)) $errors[] = "Repo URL geçersiz.";
    if ($assignee_role && !in_array($assignee_role, ['owner','dev','qa','pm','designer','analyst'], true)) {
        $assignee_role = 'dev';
    }
    if ($start_date && $due_date && $start_date > $due_date) $errors[] = "Bitiş tarihi başlangıçtan önce olamaz.";

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
            INSERT INTO tasks
            (title, details, language, priority, status, start_date, due_date, repo_url, assigned_user_id, assigned_role, created_by)
            VALUES
            (:title, :details, :language, :priority, :status, :start_date, :due_date, :repo_url, :assigned_user_id, :assigned_role, :created_by)
        ");
            $stmt->execute([
                ':title'            => $title,
                ':details'          => $details,
                ':language'         => $language,
                ':priority'         => $priority,
                ':status'           => $status,
                ':start_date'       => $start_date,
                ':due_date'         => $due_date,
                ':repo_url'         => $repo_url,
                ':assigned_user_id' => $assignee_id,
                ':assigned_role'    => $assignee_role,
                ':created_by'       => $user_id ?? 0
            ]);
            $task_id = (int)$pdo->lastInsertId();
            if (!empty($assignee_id)) {
                $n = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, status, created_at)
                VALUES (:uid, :type, :title, 0, NOW())
            ");
                $n->execute([
                    ':uid'  => $assignee_id,
                    ':type' => 'Görev',
                    ':title'=> sprintf('Yeni görev atandı: %s (#%d)', $title, $task_id),
                ]);
            }

            $pdo->commit();
            $success = "Görev oluşturuldu (ID: {$task_id}).";
            $_POST = [];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = "Kayıt sırasında bir hata oluştu: " . $e->getMessage();
        }
    }

}

$users = [];
try {
    $q = $pdo->query("SELECT id, username, role, pp FROM users ORDER BY username ASC");
    $users = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { }

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yeni Görev</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="css/stylesheet.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@600&display=swap" rel="stylesheet">

    <style>
        .container { padding: 24px; }
        .wizard { max-width: 900px; margin: 0 auto; background:#fff; border-radius:16px; box-shadow:0 10px 30px rgba(0,0,0,.07); overflow:hidden; }
        .wizard-steps { display:flex; gap:8px; padding:16px; background:#f7f7f9; }
        .step { flex:1; text-align:center; padding:10px; border-radius:12px; font-weight:600; }
        .step.active { background:#2f80ed; color:#fff; }
        .step.done { background:#d9f99d; color:#222; }
        .wizard-body { padding:24px; }

        .grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .grid-1 { display:grid; grid-template-columns:1fr; gap:16px; }
        label { font-size:14px; font-weight:600; margin-bottom:6px; display:block; }
        input[type="text"], input[type="date"], input[type="url"], textarea, select {
            width:100%; padding:12px; border:1px solid #e6e6eb; border-radius:10px; outline:none;
        }
        textarea { min-height:120px; resize:vertical; }

        .actions { display:flex; gap:12px; justify-content:flex-end; margin-top:16px; }
        .btn { padding:10px 16px; border:none; border-radius:10px; cursor:pointer; font-weight:700; }
        .btn-primary { background:#2f80ed; color:#fff; }
        .btn-secondary { background:#e5e7eb; color:#111827; }
        .btn-success { background:#16a34a; color:#fff; }

        .user-list { max-height:360px; overflow:auto; border:1px solid #eee; border-radius:10px; padding:8px; }
        .user-row { display:flex; align-items:center; gap:10px; padding:8px; border-bottom:1px dashed #eee; }
        .user-row:last-child { border-bottom:none; }
        .user-row img { width:28px; height:28px; border-radius:50%; object-fit:cover; }
        .muted { color:#6b7280; font-size:12px; }
        .pill { display:inline-block; padding:4px 10px; border-radius:999px; background:#eef2ff; margin-right:6px; margin-bottom:6px; }
        .error { background:#fee2e2; color:#7f1d1d; padding:12px; border-radius:10px; margin-bottom:12px; }
        .success { background:#dcfce7; color:#14532d; padding:12px; border-radius:10px; margin-bottom:12px; }

        .assignee { display:flex; align-items:center; gap:10px; }
        .assignee > img { width:28px; height:28px; border-radius:50%; object-fit:cover; display:none; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main">
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <h2 style="margin-bottom:16px;">Yeni Görev Ekle</h2>

        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $e) echo "<div>• ".htmlspecialchars($e)."</div>"; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="wizard" id="wizard">
            <div class="wizard-steps">
                <div class="step active" data-step="1">1) Görev Bilgileri</div>
                <div class="step" data-step="2">2) Kullanıcı Seçimi</div>
                <div class="step" data-step="3">3) Önizleme & Kaydet</div>
            </div>

            <form method="post" id="taskForm" class="wizard-body" autocomplete="off">
                <div class="step-pane" data-step="1">
                    <div class="grid">
                        <div>
                            <label>İş İsmi *</label>
                            <input type="text" name="title" id="title" placeholder="Örn: Landing sayfası revizyonu" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                        </div>

                        <div>
                            <label>Öncelik</label>
                            <select name="priority" id="priority">
                                <option value="low"   <?= (($_POST['priority'] ?? '')==='low')?'selected':'' ?>>Düşük</option>
                                <option value="medium" <?= (!isset($_POST['priority']) || ($_POST['priority'] ?? '')==='medium')?'selected':'' ?>>Orta</option>
                                <option value="high"  <?= (($_POST['priority'] ?? '')==='high')?'selected':'' ?>>Yüksek</option>
                            </select>
                        </div>

                        <div class="grid-1">
                            <label>Yapılacak İş</label>
                            <textarea name="details" id="details" placeholder="Kısa açıklama..."><?= htmlspecialchars($_POST['details'] ?? '') ?></textarea>
                        </div>

                        <div>
                            <label>Repo URL (opsiyonel)</label>
                            <input type="url" name="repo_url" id="repo_url" placeholder="https://github.com/org/repo" value="<?= htmlspecialchars($_POST['repo_url'] ?? '') ?>">
                        </div>

                        <div>
                            <label>Başlangıç Tarihi</label>
                            <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>">
                        </div>
                        <div>
                            <label>Bitiş Tarihi</label>
                            <input type="date" name="due_date" id="due_date" value="<?= htmlspecialchars($_POST['due_date'] ?? '') ?>">
                        </div>

                        <div class="grid-1">
                            <label>Kullanılacak Dil / Teknoloji</label>
                            <input type="text" name="language" id="language" placeholder="php veya node.js veya react..." value="<?= htmlspecialchars($_POST['language'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="actions">
                        <button type="button" class="btn btn-primary" id="next1">Devam</button>
                    </div>
                </div>
                <div class="step-pane" data-step="2" style="display:none;">
                    <div class="grid">
                        <div class="grid-1">
                            <label>Atanacak Kullanıcı</label>
                            <div class="assignee">
                                <img id="assigneePP" src="" alt="pp">
                                <select name="assignee_id" id="assignee_id">
                                    <option value="">— Seç —</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?= (int)$u['id'] ?>" <?= ((string)($u['id'])===($_POST['assignee_id'] ?? ''))?'selected':'' ?>>
                                            <?= htmlspecialchars($u['username']) ?> <?= ((int)$u['role']===1?'(admin)':'') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="muted">Not: Bu ekranda yalnızca tek kişi seçilir.</div>
                        </div>

                        <div>
                            <label>Görev İçi Rol</label>
                            <select name="assignee_role" id="assignee_role">
                                <?php
                                $roles = ['dev'=>'Dev','pm'=>'PM','qa'=>'QA','designer'=>'Designer','analyst'=>'Analyst','owner'=>'Owner'];
                                $postedRole = $_POST['assignee_role'] ?? 'dev';
                                foreach ($roles as $val=>$label) {
                                    $sel = ($postedRole===$val)?'selected':'';
                                    echo "<option value=\"$val\" $sel>$label</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="actions">
                        <button type="button" class="btn btn-secondary" id="prev2">Geri</button>
                        <button type="button" class="btn btn-primary" id="next2">Devam</button>
                    </div>
                </div>
                <div class="step-pane" data-step="3" style="display:none;">
                    <div class="review" id="reviewBox"></div>
                    <div class="actions">
                        <button type="button" class="btn btn-secondary" id="prev3">Geri</button>
                        <button type="submit" class="btn btn-success">Görevi Oluştur</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const steps = Array.from(document.querySelectorAll('.step'));
    const panes = Array.from(document.querySelectorAll('.step-pane'));
    let current = 1;

    function gotoStep(n) {
        current = n;
        steps.forEach(s => {
            const idx = parseInt(s.dataset.step);
            s.classList.toggle('active', idx === current);
            s.classList.toggle('done', idx < current);
        });
        panes.forEach(p => p.style.display = (parseInt(p.dataset.step) === current) ? '' : 'none');
    }

    document.getElementById('next1').addEventListener('click', () => {
        const title = document.getElementById('title').value.trim();
        if (!title) { alert('İş ismi zorunlu.'); return; }
        gotoStep(2);
    });

    document.getElementById('prev2').addEventListener('click', () => gotoStep(1));
    document.getElementById('next2').addEventListener('click', () => {
        buildReview();
        gotoStep(3);
    });
    document.getElementById('prev3').addEventListener('click', () => gotoStep(2));

    const assigneeSelect = document.getElementById('assignee_id');
    const assigneePP = document.getElementById('assigneePP');
    const userMap = {
        <?php
        foreach ($users as $u) {
            $pp = $u['pp'] ? $u['pp'] : 'uploads/default.png';
            echo (int)$u['id'] . ":'" . htmlspecialchars($pp, ENT_QUOTES) . "',";
        }
        ?>
    };
    assigneeSelect?.addEventListener('change', () => {
        const val = assigneeSelect.value;
        if (val && userMap[val]) {
            assigneePP.src = userMap[val];
            assigneePP.style.display = 'block';
        } else {
            assigneePP.style.display = 'none';
        }
    });

    function buildReview() {
        const get = id => document.getElementById(id)?.value || '';
        const review = document.getElementById('reviewBox');

        const assigneeId = get('assignee_id');
        const assigneeText = assigneeId ? assigneeSelect.options[assigneeSelect.selectedIndex].text : 'Seçilmedi';

        review.innerHTML = `
        <h4 style="margin:0 0 10px;">Önizleme</h4>
        <div><b>İş İsmi:</b> ${escapeHtml(get('title'))}</div>
        <div><b>Öncelik:</b> ${escapeHtml(get('priority'))}</div>
        <div><b>Durum:</b> ${escapeHtml(get('status'))}</div>
        <div><b>Başlangıç:</b> ${escapeHtml(get('start_date')) || '-'}</div>
        <div><b>Bitiş:</b> ${escapeHtml(get('due_date')) || '-'}</div>
        <div><b>Repo:</b> ${escapeHtml(get('repo_url')) || '-'}</div>
        <div style="margin-top:8px;"><b>Detay:</b><br>${escapeHtml(get('details') || '-')}</div>
        <div style="margin-top:8px;"><b>Kullanılacak Dil:</b> ${escapeHtml(get('language') || '-')}</div>
        <div style="margin-top:8px;"><b>Atanan:</b> ${escapeHtml(assigneeText)} <span class="muted">(Rol: ${escapeHtml(get('assignee_role') || 'dev')})</span></div>
    `;
    }

    function escapeHtml(s) {
        return s.replace(/[&<>"']/g, c => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
        }[c]));
    }
</script>
</body>
</html>

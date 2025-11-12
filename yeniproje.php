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

if ($rol != 1) { die("Bu işlem için yetkin yok."); }

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = trim($_POST['name'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $technologies = trim($_POST['technologies'] ?? '');
    $priority     = $_POST['priority'] ?? 'medium';
    $status       = $_POST['status'] ?? 'planning';
    $start_date   = $_POST['start_date'] ?: null;
    $due_date     = $_POST['due_date'] ?: null;
    $repo_url     = trim($_POST['repo_url'] ?? '');

    $assigned_users = $_POST['assigned_users'] ?? [];
    $assigned_roles = $_POST['assigned_roles'] ?? [];

    if ($name === '') $errors[] = "Proje adı zorunlu.";
    if (!in_array($priority, ['low','medium','high'])) $errors[] = "Geçersiz öncelik.";
    if (!in_array($status, ['planning','in_progress','on_hold','completed'])) $errors[] = "Geçersiz durum.";
    if ($repo_url && !filter_var($repo_url, FILTER_VALIDATE_URL)) $errors[] = "Repo URL geçersiz.";

    if ($start_date && $due_date && $start_date > $due_date) $errors[] = "Bitiş tarihi başlangıçtan önce olamaz.";

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO projects
                (name, description, technologies, priority, status, start_date, due_date, repo_url, created_by)
                VALUES (:name, :description, :technologies, :priority, :status, :start_date, :due_date, :repo_url, :created_by)");
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':technologies' => $technologies,
                ':priority' => $priority,
                ':status' => $status,
                ':start_date' => $start_date,
                ':due_date' => $due_date,
                ':repo_url' => $repo_url,
                ':created_by' => $user_id ?? 0
            ]);
            $project_id = (int)$pdo->lastInsertId();
            if (!empty($assigned_users)) {
                $ins = $pdo->prepare("INSERT INTO project_users (project_id, user_id, project_role) VALUES (:pid, :uid, :role)");
                foreach ($assigned_users as $uid) {
                    $uid = (int)$uid;
                    $prole = $assigned_roles[$uid] ?? 'dev';
                    if (!in_array($prole, ['owner','dev','qa','pm','designer','analyst'])) {
                        $prole = 'dev';
                    }
                    $ins->execute([
                        ':pid' => $project_id,
                        ':uid' => $uid,
                        ':role' => $prole
                    ]);
                }
            }

            $pdo->commit();
            $success = "Proje oluşturuldu (ID: {$project_id}).";
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Kayıt sırasında bir hata oluştu: " . $e->getMessage();
        }
    }
}

$users = [];
try {
    $q = $pdo->query("SELECT id, username, role, pp FROM users ORDER BY username ASC");
    $users = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {

}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yeni Proje</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="css/stylesheet.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@600&display=swap" rel="stylesheet">

    <style>
        .container { padding: 24px; }
        .wizard { max-width: 1000px; margin: 0 auto; background:#fff; border-radius:16px; box-shadow:0 10px 30px rgba(0,0,0,.07); overflow:hidden; }
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
        .btn {
            padding:10px 16px; border:none; border-radius:10px; cursor:pointer; font-weight:700;
        }
        .btn-primary { background:#2f80ed; color:#fff; }
        .btn-secondary { background:#e5e7eb; color:#111827; }
        .btn-success { background:#16a34a; color:#fff; }

        .users-wrap { display:grid; grid-template-columns: 1fr; gap:16px; }
        .user-list { max-height:360px; overflow:auto; border:1px solid #eee; border-radius:10px; padding:8px; }
        .user-row { display:flex; align-items:center; gap:10px; padding:8px; border-bottom:1px dashed #eee; }
        .user-row:last-child { border-bottom:none; }
        .user-row img { width:28px; height:28px; border-radius:50%; object-fit:cover; }
        .user-role { margin-left:auto; }
        .muted { color:#6b7280; font-size:12px; }
        .tag-hint { font-size:12px; color:#6b7280; margin-top:6px; }

        .review { background:#f9fafb; padding:16px; border-radius:12px; }
        .pill { display:inline-block; padding:4px 10px; border-radius:999px; background:#eef2ff; margin-right:6px; margin-bottom:6px; }
        .error { background:#fee2e2; color:#7f1d1d; padding:12px; border-radius:10px; margin-bottom:12px; }
        .success { background:#dcfce7; color:#14532d; padding:12px; border-radius:10px; margin-bottom:12px; }
        .searchbox { display:flex; gap:8px; }
        .searchbox input { flex:1; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main">
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <h2 style="margin-bottom:16px;">Yeni Proje Ekle</h2>

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
                <div class="step active" data-step="1">1) Proje Bilgileri</div>
                <div class="step" data-step="2">2) Kullanıcı Atama</div>
                <div class="step" data-step="3">3) Önizleme & Kaydet</div>
            </div>

            <form method="post" id="projectForm" class="wizard-body" autocomplete="off">

                <div class="step-pane" data-step="1">
                    <div class="grid">
                        <div>
                            <label>Proje Adı *</label>
                            <input type="text" name="name" id="name" placeholder="Örn: CRM V2" required>
                        </div>
                        <div>
                            <label>Öncelik</label>
                            <select name="priority" id="priority">
                                <option value="low">Düşük</option>
                                <option value="medium" selected>Orta</option>
                                <option value="high">Yüksek</option>
                            </select>
                        </div>

                        <div class="grid-1">
                            <label>Açıklama</label>
                            <textarea name="description" id="description" placeholder="Kısa proje açıklaması..."></textarea>
                        </div>

                        <div>
                            <label>Durum</label>
                            <select name="status" id="status">
                                <option value="planning" selected>Planlama</option>
                                <option value="in_progress">Devam Ediyor</option>
                                <option value="on_hold">Beklemede</option>
                                <option value="completed">Tamamlandı</option>
                            </select>
                        </div>

                        <div>
                            <label>Repo URL</label>
                            <input type="url" name="repo_url" id="repo_url" placeholder="https://github.com/kadi/proje">
                        </div>

                        <div>
                            <label>Başlangıç Tarihi</label>
                            <input type="date" name="start_date" id="start_date">
                        </div>
                        <div>
                            <label>Bitiş Tarihi</label>
                            <input type="date" name="due_date" id="due_date">
                        </div>

                        <div class="grid-1">
                            <label>Teknolojiler (virgülle ayır)</label>
                            <input type="text" name="technologies" id="technologies" placeholder="php, mysql, node.js, react">
                        </div>
                    </div>

                    <div class="actions">
                        <button type="button" class="btn btn-primary" id="next1">Devam</button>
                    </div>
                </div>

                <div class="step-pane" data-step="2" style="display:none;">
                    <div class="users-wrap">
                        <div class="searchbox">
                            <input type="text" id="userSearch" placeholder="Kullanıcı ara (kısmi eşleşme)">
                            <button type="button" class="btn btn-secondary" id="clearSearch">Temizle</button>
                            <button type="button" class="btn btn-secondary" id="toggleAll">Tümünü Seç/Çıkar</button>
                        </div>

                        <div class="user-list" id="userList">
                            <?php foreach ($users as $u): ?>
                                <div class="user-row" data-username="<?= htmlspecialchars(mb_strtolower($u['username'])) ?>">
                                    <input type="checkbox" class="cb-user" name="assigned_users[]" value="<?= (int)$u['id'] ?>">
                                    <img src="<?= $u['pp'] ? htmlspecialchars($u['pp']) : 'uploads/default.png' ?>" alt="pp">
                                    <div>
                                        <div><strong><?= htmlspecialchars($u['username']) ?></strong></div>
                                        <div class="muted">role: <?= (int)$u['role'] === 1 ? 'admin' : 'user' ?></div>
                                    </div>
                                    <div class="user-role">
                                        <select name="assigned_roles[<?= (int)$u['id'] ?>]">
                                            <option value="dev">Dev</option>
                                            <option value="pm">PM</option>
                                            <option value="qa">QA</option>
                                            <option value="designer">Designer</option>
                                            <option value="analyst">Analyst</option>
                                            <option value="owner">Owner</option>
                                        </select>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($users)): ?>
                                <div class="muted">Kullanıcı bulunamadı.</div>
                            <?php endif; ?>
                        </div>
                        <div class="muted">Not: Kullanıcılara proje içi rol atayabilirsin; birden fazla kullanıcı seçilebilir.</div>
                    </div>

                    <div class="actions">
                        <button type="button" class="btn btn-secondary" id="prev2">Geri</button>
                        <button type="button" class="btn btn-primary" id="next2">Devam</button>
                    </div>
                </div>


                <div class="step-pane" data-step="3" style="display:none;">
                    <div class="review" id="reviewBox">

                    </div>
                    <div class="actions">
                        <button type="button" class="btn btn-secondary" id="prev3">Geri</button>
                        <button type="submit" class="btn btn-success">Projeyi Oluştur</button>
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

        const name = document.getElementById('name').value.trim();
        if (!name) { alert('Proje adı zorunlu.'); return; }
        gotoStep(2);
    });

    document.getElementById('prev2').addEventListener('click', () => gotoStep(1));
    document.getElementById('next2').addEventListener('click', () => {

        buildReview();
        gotoStep(3);
    });
    document.getElementById('prev3').addEventListener('click', () => gotoStep(2));


    const userSearch = document.getElementById('userSearch');
    const clearSearch = document.getElementById('clearSearch');
    const userList = document.getElementById('userList');
    userSearch?.addEventListener('input', () => {
        const q = userSearch.value.trim().toLowerCase();
        userList.querySelectorAll('.user-row').forEach(row => {
            const name = row.dataset.username;
            row.style.display = name.includes(q) ? 'flex' : 'none';
        });
    });
    clearSearch?.addEventListener('click', () => {
        userSearch.value = '';
        userSearch.dispatchEvent(new Event('input'));
    });

    document.getElementById('toggleAll')?.addEventListener('click', () => {
        const boxes = userList.querySelectorAll('.cb-user');
        const allChecked = Array.from(boxes).every(cb => cb.checked);
        boxes.forEach(cb => cb.checked = !allChecked);
    });

    function buildReview() {
        const get = id => document.getElementById(id).value;
        const techs = get('technologies').split(',').map(t => t.trim()).filter(Boolean);
        const review = document.getElementById('reviewBox');
        const selected = Array.from(document.querySelectorAll('.cb-user:checked')).map(cb => {
            const row = cb.closest('.user-row');
            const username = row.querySelector('strong').textContent;
            const roleSel = row.querySelector('select');
            return { id: cb.value, username, role: roleSel.value };
        });

        review.innerHTML = `
        <h4 style="margin:0 0 10px;">Önizleme</h4>
        <div><b>Ad:</b> ${escapeHtml(get('name'))}</div>
        <div><b>Öncelik:</b> ${escapeHtml(get('priority'))}</div>
        <div><b>Durum:</b> ${escapeHtml(get('status'))}</div>
        <div><b>Başlangıç:</b> ${escapeHtml(get('start_date')) || '-'}</div>
        <div><b>Bitiş:</b> ${escapeHtml(get('due_date')) || '-'}</div>
        <div><b>Repo:</b> ${escapeHtml(get('repo_url')) || '-'}</div>
        <div style="margin-top:8px;"><b>Açıklama:</b><br>${escapeHtml(get('description') || '-')}</div>
        <div style="margin-top:8px;"><b>Teknolojiler:</b><br>
            ${techs.length ? techs.map(t => `<span class="pill">${escapeHtml(t)}</span>`).join(' ') : '<span class="muted">-</span>'}
        </div>
        <div style="margin-top:8px;"><b>Atananlar:</b><br>
            ${selected.length ? selected.map(s => `<span class="pill">${escapeHtml(s.username)} • ${escapeHtml(s.role)}</span>`).join(' ') : '<span class="muted">Seçilmedi</span>'}
        </div>
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

<?php
require_once 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$user_id       = (int)($_SESSION['user_id'] ?? 0);
$kullanici_adi = $_SESSION['username'];
$rol           = $_SESSION['role'] ?? 0;

$st = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND status = 0");
$st->execute([':uid' => $user_id]);
$notif_count = (int)$st->fetchColumn();
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Topbar</title>

    <link href="css/header.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        .topbar { display:flex; align-items:center; justify-content:space-between; padding:12px 16px; background:#fff; border-bottom:1px solid #eee; }
        .profile { display:flex; align-items:center; gap:20px; position:relative; }
        .dropdown { position:relative; display:inline-block; }
        .dropdown-toggle { text-decoration:none; color:#222; display:inline-flex; align-items:center; gap:8px; }
        .dropdown .fa-bell { font-size:18px; }
        .badge {
            display:inline-block; min-width:18px; height:18px; line-height:18px; padding:0 6px;
            font-size:12px; border-radius:9px; background:#e74c3c; color:#fff; text-align:center;
        }
        .dropdownnn-menu {
            position:absolute; top:36px; right:0; width:320px; max-height:320px; overflow:auto;
            background:#fff; border:1px solid #eaeaea; border-radius:10px;
            box-shadow:0 8px 24px rgba(0,0,0,.08);
            padding:6px 0; display:none; z-index:1000; animation:fadeIn .15s ease;
        }
        .dropdownnn-menu.show { display:block; }
        .dropdownnn-menu ul { list-style:none; padding:0; margin:0; }
        .dropdownnn-menu li { padding:10px 14px; border-bottom:1px solid #f2f2f2; }
        .dropdownnn-menu li:last-child { border-bottom:none; }
        .dropdownnn-menu small { color:#888; }
        .dropdownnn-footer { text-align:center; padding:10px; }
        .dropdownnn-footer a { text-decoration:none; color:#1f6feb; }

        @keyframes fadeIn { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }
        .dropdownn { position:relative; }
        .dropdownn img { cursor:pointer; border-radius:50%; transition:.2s transform; }
        .dropdownn img:hover { transform:scale(1.05); }
        .dropdownn-menu {
            position:absolute; top:50px; right:0; background:#fff; border-radius:10px;
            box-shadow:0 8px 24px rgba(0,0,0,.08); padding:12px; display:none; min-width:240px; z-index:1000;
            animation:fadeIn .15s ease;
        }
        .dropdownn-menu.show { display:block; }
        .dropdownn-menu .username { font-weight:600; font-size:18px; margin-bottom:8px; color:#2a3f54; display:block; }
        .dropdownn-menu a { display:block; padding:8px 10px; color:#333; text-decoration:none; border-radius:8px; }
        .dropdownn-menu a:hover { background:#f6f8fa; }
        .logout-btn { color:#fff !important; background:#e74c3c; margin-top:6px; text-align:center; }
        .logout-btn:hover { background:#c0392b !important; }
    </style>
</head>
<body>

<div class="topbar">
    <span class="menu-btn" onclick="toggleSidebar()"></span>

    <div class="profile">
        <div class="dropdown" id="notifBox">
            <a href="#" class="dropdownnn-toggle" id="notifToggle">
                <i class="fa fa-bell"></i>
                <span id="notif-count" class="badge"><?= $notif_count ?></span>
            </a>

            <div id="notif-dropdown" class="dropdownnn-menu">
                <ul>
                    <?php
                    $st = $pdo->prepare("
                        SELECT id, title, type, created_at, status
                        FROM notifications
                        WHERE user_id = :uid
                        ORDER BY created_at DESC
                        LIMIT 10
                    ");
                    $st->execute([':uid' => $user_id]);

                    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                    if (!$rows) {
                        echo '<li style="text-align:center; color:#777;">Yeni bildirimin yok ✨</li>';
                    } else {
                        foreach ($rows as $n) {
                            $title = htmlspecialchars($n['title']);
                            $type  = htmlspecialchars($n['type']); // Görev / Proje
                            $date  = date("d.m.Y H:i", strtotime($n['created_at']));
                            $bold  = ((int)$n['status'] === 0) ? 'style="font-weight:600;"' : '';
                            echo "<li $bold>
                                    <strong>$type</strong>: $title
                                    <br><small>$date</small>
                                  </li>";
                        }
                    }
                    ?>
                </ul>
                <div class="dropdownnn-footer">
                    <a href="messages.php">Tümünü Gör</a>
                </div>
            </div>
        </div>
        <?php $profilResmi = !empty($_SESSION['pp']) ? $_SESSION['pp'] : 'uploads/profile/default.png'; ?>
        <div class="dropdownn" id="profileBox">
            <img src="<?= htmlspecialchars($profilResmi) ?>" alt="profil" width="40" height="40" id="profileToggle">
            <div id="dropdownMenuu" class="dropdownn-menu">
                <span class="username">👋 <?= htmlspecialchars($kullanici_adi) ?></span>
                <a href="profile_upload.php">📷 Fotoğraf Değiştir</a>
                <a href="/cikis.php" class="btn btn-outline-dark" data-logout>Çıkış</a>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/bootstrap.min.js"></script>
<script>
    const notifToggle   = document.getElementById('notifToggle');
    const notifDropdown = document.getElementById('notif-dropdown');
    const notifBox      = document.getElementById('notifBox');

    notifToggle.addEventListener('click', function(e){
        e.preventDefault();
        e.stopPropagation();
        notifDropdown.classList.toggle('show');
        document.getElementById('dropdownMenuu').classList.remove('show');
    });

    const profileToggle = document.getElementById('profileToggle');
    const profileMenu   = document.getElementById('dropdownMenuu');
    const profileBox    = document.getElementById('profileBox');

    profileToggle.addEventListener('click', function(e){
        e.preventDefault();
        e.stopPropagation();
        profileMenu.classList.toggle('show');
        notifDropdown.classList.remove('show');
    });

    document.addEventListener('click', function(e){
        if (!e.target.closest('#notifBox')) {
            notifDropdown.classList.remove('show');
        }
        if (!e.target.closest('#profileBox')) {
            profileMenu.classList.remove('show');
        }
    });

    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') {
            notifDropdown.classList.remove('show');
            profileMenu.classList.remove('show');
        }
    });
</script>
</body>
</html>

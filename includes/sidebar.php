<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link href="css/sidebar.css" rel="stylesheet" type="text/css">

</head>
<body>
<div class="sidebar" id="sidebarr">
    <?php if ($_SESSION['role'] == 1): ?>
   <a href="index.php" style="text-decoration: none;color: black"> <h2>ADMIN<br>PANELI</h2></a>
    <?php else:?>
    <h2>KULLANICI<br>PANELI</h2>
    <?php endif;?>
    <?php if ($_SESSION['role'] == 1): ?>
    <div class="dropdown" onclick="toggleDropdown(this)">
        <a  style="justify-content: left"><i class="fa fa-user"></i> Kullanıcı <i class="fa-solid fa-chevron-right"></i></a>
        <div class="dropdown-menu">
            <a href="kullanicilistele.php">Listele</a>
            <a href="kullaniciekle.php">Ekle</a>
        </div>
    </div>
    <?php endif;?>
    <?php if ($_SESSION['role'] == 1): ?>
    <div class="dropdown" onclick="toggleDropdown(this)">
        <a  style="justify-content: left"><i class="fa fa-file"></i> Proje <i class="fa-solid fa-chevron-right"></i></a>
        <div class="dropdown-menu">
            <a href="projeleradmin.php">Tüm Projeler</a>
            <a href="yeniproje.php">Yeni Proje</a>
        </div>
    </div>
    <?php endif;?>
    <?php if ($_SESSION['role'] == 0): ?>
        <div class="dropdown" onclick="toggleDropdown(this)">
            <a  style="justify-content: left"><i class="fa fa-file"></i> Proje <i class="fa-solid fa-chevron-right"></i></a>
            <div class="dropdown-menu">
                <a href="projeler.php">Projeler</a>
            </div>
        </div>
    <?php endif;?>
    <?php if ($_SESSION['role'] == 1): ?>
    <div class="dropdown" onclick="toggleDropdown(this)">
        <a  style="justify-content: left"><i class="fa fa-clock"></i> Görev <i class="fa-solid fa-chevron-right"></i></a>
        <div class="dropdown-menu">
            <a href="gorevleradmin.php">Aktif Görevler</a>
            <a href="yenigorev.php">Yeni Görev</a>
        </div>
    </div>
    <?php endif;?>
    <?php if ($_SESSION['role'] == 0): ?>
        <div class="dropdown" onclick="toggleDropdown(this)">
            <a  style="justify-content: left"><i class="fa fa-clock"></i> Görev <i class="fa-solid fa-chevron-right"></i></a>
            <div class="dropdown-menu">
                <a href="gorevler.php">Aktif Görevler</a>
            </div>
        </div>
    <?php endif;?>
    <div class="dropdown" onclick="toggleDropdown(this)">
        <a style="justify-content: left" href="messages.php"><i class="fa fa-comment"></i> Mesaj </a>
    </div>
    <?php if ($_SESSION['role'] == 1): ?>
    <div class="dropdown" onclick="toggleDropdown(this)">
        <a style="justify-content: left" href="gmail_list.php"><i class="fa fa-comment"></i> Gmail </a>
    </div>
    <?php endif;?>
    <div class="dropdown">
        <a  style="justify-content: left" href="pomodoro.php"><i class="fa fa-clock"></i> Pomodoro</a>
    </div>
</div>
<script src="js/sidebar.js"></script>
</body>
</html>
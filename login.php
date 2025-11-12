<?php
session_start();
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['user_id'];
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] == 1) {
            header("Location: index.php");
        } else {
            header("Location: index.php");
        }
        exit;
    } else {
        echo "Hatalı kullanıcı adı veya şifre.";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Giriş Yap</title>
    <link href="https://fonts.googleapis.com/css2?family=Rubik&display=swap" rel="stylesheet">
    <link href="css/login.css" rel="stylesheet" type="text/css">
</head>
<body>

<div class="login-box">
    <h2>CRM Sistemi</h2>
    <form action="login.php" method="POST">
        <input type="text" name="username" placeholder="Kullanıcı Adı" required>
        <input type="password" name="password" placeholder="Şifre" required>
        <button type="submit">Giriş</button>
    </form>
    <p class="info-text">Görev ve Proje Takip Sistemine Hoş Geldiniz Giriş Yapmak İçin Size Tahsis Edilen Kullanıcı Adınızı Ve Şifrenizi Giriniz</p>
</div>
<script>
    localStorage.setItem("id", "<?= $_SESSION['user_id'] ?>");
    localStorage.setItem("username", "<?= $_SESSION['username'] ?>");
    localStorage.setItem("role", "<?= $_SESSION['role'] ?>");
</script>
</body>
</html>

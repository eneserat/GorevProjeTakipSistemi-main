<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] != 1) {
    header("Location: login.php");
    exit;
}

$mesaj = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    if (!empty($username) && !empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        if ($stmt->execute([$username, $hashed_password, $role])) {
            $mesaj = '<div class="basarili">✅ Kullanıcı başarıyla eklendi.</div>';
        } else {
            $mesaj = '<div class="hata">❌ Hata oluştu. Tekrar deneyin.</div>';
        }
    } else {
        $mesaj = '<div class="hata">❌ Kullanıcı adı ve şifre zorunludur.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Paneli</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@600&display=swap" rel="stylesheet">
    <link href="css/stylesheet.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css" integrity="sha512-DxV+EoADOkOygM4IR9yXP8Sb2qwgidEmeqAEmDKIOfPRQZOWbXCzLC6vjbZyy0vPisbH2SyW27+ddLVCN+OMzQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .kullanici-ekle-container {
            padding: 30px 40px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            width: 400px;
            max-width: 90%;

        }

        .kullanici-ekle-container h2 {
            text-align: left;
            margin-bottom: 25px;
            margin-top: 20px;
            color: #2a3f54;
        }

        .kullanici-ekle-container label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            color: #333;
        }

        .kullanici-ekle-container input,
        .kullanici-ekle-container select {
            width: 50%;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 8px;
            border: 1px solid #ccc;
            transition: 0.3s;
        }

        .kullanici-ekle-container input:focus,
        .kullanici-ekle-container select:focus {
            border-color: #4e73df;
            outline: none;
        }

        .kullanici-ekle-container button {
            background-color: #4e73df;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            width: 10%;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
        }

        .kullanici-ekle-container button:hover {
            background-color: #2e59d9;
        }

        .basarili {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .hata {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
<?php
include 'includes/sidebar.php';
?>
<div class="main kullanici-ekle-container">
    <?php
    include "includes/header.php";
    ?>
    <h2>👤 Yeni Kullanıcı Ekle</h2>
    <?= $mesaj ?>
    <form method="post">
        <label for="username">👤 Kullanıcı Adı</label>
        <input type="text" name="username" required>

        <label for="password">🔒 Şifre</label>
        <input type="password" name="password" required>

        <label for="role">🛡️ Rol</label>
        <select name="role" required>
            <option value="0">Kullanıcı</option>
            <option value="1">Yönetici</option>
        </select>

        <button type="submit">➕ Kullanıcı Ekle</button>
    </form>
</div>

<script src="https://kit.fontawesome.com/a076d05399.js"></script>
</body>
</html>

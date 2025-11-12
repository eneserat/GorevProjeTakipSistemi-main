<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] != 1) {
    header("Location: login.php");
    exit;
}

$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: kullanici_listesi.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$kullanici = $stmt->fetch();

if (!$kullanici) {
    echo "Kullanıcı bulunamadı.";
    exit;
}

$mesaj = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $role = $_POST['role'];
    $new_password = $_POST['new_password'];

    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $guncelle = $pdo->prepare("UPDATE users SET username=?, password=?, role=? WHERE id=?");
        $guncelle->execute([$username, $hashed_password, $role, $id]);
    } else {
        $guncelle = $pdo->prepare("UPDATE users SET username=?, role=? WHERE id=?");
        $guncelle->execute([$username, $role, $id]);
    }

    $mesaj = '<div class="basarili">✅ Kullanıcı güncellendi.</div>';
    $stmt->execute([$id]);
    $kullanici = $stmt->fetch();
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
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <style>
        .kullanici-ekle-container {
            background: #fff;
            padding: 35px 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
            animation: fadeIn 0.7s ease;
            margin: 0 auto;
        }

        .kullanici-ekle-container h2 {
            text-align: center;
            color: #2a3f54;
            margin-bottom: 25px;
            font-size: 24px;
        }

        form label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 15px;
            color: #2a3f54;
        }

        form input[type="text"],
        form input[type="password"],
        form select {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 10px;
            border: 1px solid #ccc;
            font-size: 15px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        form input:focus,
        form select:focus {
            border-color: #4e73df;
            box-shadow: 0 0 8px rgba(78, 115, 223, 0.2);
            outline: none;
        }

        button[type="submit"] {
            width: 100%;
            background-color: #4e73df;
            color: #fff;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        button[type="submit"]:hover {
            background-color: #2e59d9;
        }

        .basarili {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
            border-left: 5px solid #28a745;
            animation: fadeIn 0.6s ease-in-out;
        }

        .hata {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
            border-left: 5px solid #e74c3c;
            animation: fadeIn 0.6s ease-in-out;
        }

        a {
            text-decoration: none;
            color: #4e73df;
            display: inline-block;
            margin-top: 15px;
            font-weight: 500;
            transition: 0.3s;
        }

        a:hover {
            text-decoration: underline;
            color: #2e59d9;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Mobil uyum */
        @media(max-width: 600px) {
            .kullanici-ekle-container {
                padding: 25px 20px;
            }

            button[type="submit"] {
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
<?php
include 'includes/sidebar.php';
?>
<div class="main">
    <?php
    include "includes/header.php";
    ?>
    <div class="kullanici-ekle-container">
        <h2>🛠️ Kullanıcı Güncelle</h2>
        <?= $mesaj ?>
        <form method="post">
            <label for="username">👤 Kullanıcı Adı</label>
            <input type="text" name="username" value="<?= htmlspecialchars($kullanici['username']) ?>" required>

            <label for="new_password">🆕 Yeni Şifre (İsteğe Bağlı)</label>
            <input type="password" name="new_password" placeholder="Boş bırakılırsa şifre değişmez.">

            <label for="role">🛡️ Rol</label>
            <select name="role" required>
                <option value="0" <?= $kullanici['role'] == 0 ? 'selected' : '' ?>>Kullanıcı</option>
                <option value="1" <?= $kullanici['role'] == 1 ? 'selected' : '' ?>>Yönetici</option>
            </select>

            <button type="submit">💾 Güncelle</button>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

</body>
</html>

<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pp'])) {
    $userId = $_SESSION['user_id'];
    $file = $_FILES['pp'];

    if ($file['error'] === 0) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = 'pp_' . $userId . '.' . $ext;
        $uploadPath = 'uploads/profile/' . $fileName;

        if (!is_dir('uploads/profile')) {
            mkdir('uploads/profile', 0777, true);
        }

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $stmt = $pdo->prepare("UPDATE users SET pp = ? WHERE id = ?");
            $stmt->execute([$uploadPath, $userId]);

            $_SESSION['pp'] = $uploadPath;
            header("Location: profile_upload.php?success=1");
            exit;
        } else {
            echo "❌ Yükleme hatası.";
        }
    } else {
        echo "❌ Dosya yüklenemedi.";
    }
}
?>

    <form action="" method="POST" enctype="multipart/form-data">
        <label>Profil Fotoğrafı Seç:</label>
        <input type="file" name="pp" accept="image/*" required>
        <button type="submit">Yükle</button>
    </form>

<?php if (isset($_GET['success'])) echo "✅ Fotoğraf yüklendi."; ?>
<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] != 1) {
    header("Location: login.php");
    exit;
}

$kullanicilar = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
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
        .kullanici-liste-container {
            max-width: 1300px;
            margin: auto;
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            animation: fadeInUp 1s ease;
        }

        .kullanici-liste-container h2 {
            margin-bottom: 20px;
            color: #2a3f54;
            text-align: center;
        }

        .kullanici-tablosu {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
        }

        .kullanici-tablosu th, .kullanici-tablosu td {
            padding: 14px 20px;
            text-align: left;
            border-bottom: 1px solid #eee;
            transition: background 0.3s;
        }

        .kullanici-tablosu tr:hover {
            background-color: #f9f9f9;
        }

        .kullanici-tablosu th {
            background: #4e73df;
            color: white;
            font-weight: 600;
        }

        .aksiyonlar .btn {
            padding: 6px 12px;
            margin-right: 8px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: 0.3s;
        }

        .btn-guncelle {
            background: #1cc88a;
            color: white;
            padding: 6px 12px;
            margin-right: 8px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: 0.3s;
        }

        .btn-guncelle:hover {
            background: #17a673;
        }

        .btn-sil {
            background: #e74a3b;
            color: white;
            padding: 6px 12px;
            margin-right: 8px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: 0.3s;
        }

        .btn-sil:hover {
            background: #c0392b;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        table.dataTable tbody tr {
            transition: all 0.2s ease-in-out;
        }

        table.dataTable tbody tr:hover {
            background-color: #f9f9f9;
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
    <div class="kullanici-liste-container">
        <h2>👥 Kullanıcı Listesi</h2>
        <table id="kullaniciTablosu" class="display responsive nowrap kullanici-tablosu" style="width:100%">
            <thead>
            <tr>
                <th>#</th>
                <th>Kullanıcı Adı</th>
                <th>Rol</th>
                <th>Kayıt Tarihi</th>
                <th>İşlemler</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($kullanicilar as $i => $k): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($k['username']) ?></td>
                    <td><?= $k['role'] == 1 ? 'Yönetici' : 'Kullanıcı' ?></td>
                    <td><?= date('d.m.Y H:i', strtotime($k['created_at'])) ?></td>
                    <td>
                        <a href="kullanici_guncelle.php?id=<?= $k['id'] ?>" class="btn-guncelle"><i class="fas fa-edit"></i> Güncelle</a>
                        <a href="kullanici_sil.php?id=<?= $k['id'] ?>" class="btn-sil" onclick="return confirm('Emin misin?')"><i class="fas fa-trash"></i> Sil</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

<script>
    $(document).ready(function () {
        $('#kullaniciTablosu').DataTable({
            responsive: true,
            language: {
                url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json"
            }
        });
    });
</script>
</body>
</html>

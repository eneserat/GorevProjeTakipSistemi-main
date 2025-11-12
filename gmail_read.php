<?php
session_start();
if (empty($_SESSION['user_id'])) { die('Auth required'); }

require_once __DIR__ . '/gmail_client.php';

$userId = (int)$_SESSION['user_id'];
$gmail = getGmailServiceFor($userId);
if ($gmail === false) { header("Location: gmail_list.php"); exit; }

$id = $_GET['id'] ?? '';
if ($id === '') { die('Mesaj id yok'); }

$msg = $gmail->users_messages->get('me', $id, ['format'=>'full']);
$payload = $msg->getPayload();
$headers = $payload->getHeaders();

$from    = headerValue($headers, 'From');
$to      = headerValue($headers, 'To');
$cc      = headerValue($headers, 'Cc');
$subject = headerValue($headers, 'Subject');
$date    = headerValue($headers, 'Date');

$contentHtml = '';
$contentText = '';

function extractBody($payload, &$contentHtml, &$contentText) {
    if ($payload->getParts()) {
        foreach ($payload->getParts() as $part) {
            $mime = $part->getMimeType();
            if ($mime === 'text/html') {
                $contentHtml .= b64url_decode($part->getBody()->getData() ?? '');
            } elseif ($mime === 'text/plain') {
                $contentText .= b64url_decode($part->getBody()->getData() ?? '');
            } elseif ($part->getParts()) {
                extractBody($part, $contentHtml, $contentText);
            }
        }
    } else {
        $mime = $payload->getMimeType();
        $data = $payload->getBody()->getData() ?? '';
        if ($mime === 'text/html') $contentHtml .= b64url_decode($data);
        if ($mime === 'text/plain') $contentText .= b64url_decode($data);
    }
}
extractBody($payload, $contentHtml, $contentText);

$body = $contentHtml ?: nl2br(htmlspecialchars($contentText ?: '(İçerik yok)'));

echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">';
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
</head>
<body>
<?php
include 'includes/sidebar.php';
?>
<div class="main">
    <?php
    include "includes/header.php";
    ?>
<div class="container py-4">
    <a class="btn btn-outline-secondary mb-3" href="gmail_list.php">&larr; Gelen Kutusuna Dön</a>
    <div class="card shadow-sm">
        <div class="card-header">
            <strong><?= htmlspecialchars($subject ?: '(No subject)') ?></strong>
        </div>
        <div class="card-body">
            <div class="mb-2"><strong>From:</strong> <?= htmlspecialchars($from) ?></div>
            <div class="mb-2"><strong>To:</strong> <?= htmlspecialchars($to) ?></div>
            <?php if ($cc): ?><div class="mb-2"><strong>Cc:</strong> <?= htmlspecialchars($cc) ?></div><?php endif; ?>
            <div class="mb-3"><strong>Date:</strong> <?= htmlspecialchars($date) ?></div>
            <hr>
            <div><?= $body ?></div>
        </div>
    </div>
</div>
</div>
</body>
</html>
<?php
session_start();
if (empty($_SESSION['user_id'])) { die('Auth required'); }

require_once __DIR__ . '/gmail_client.php';

$userId = (int)$_SESSION['user_id'];
$gmail = getGmailServiceFor($userId);

if ($gmail === false) {
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">';
    echo '<div class="container py-5">';
    echo '<h3>Gmail Bağlı Değil</h3>';
    echo '<p>Gmail hesabını bağlayarak gelen kutunu CRM içinde görebilirsin.</p>';
    echo '<a class="btn btn-dark" href="gmail_oauth_start.php">Gmail’i Bağla</a>';
    echo '</div>';
    exit;
}

$q = trim($_GET['q'] ?? '');
$pageToken = $_GET['pageToken'] ?? null;

$params = ['maxResults' => 20];
if ($q !== '') $params['q'] = $q;
if ($pageToken) $params['pageToken'] = $pageToken;

$list = $gmail->users_messages->listUsersMessages('me', $params);
$messages = $list->getMessages() ?: [];
$nextPageToken = $list->getNextPageToken();

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
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Gelen Kutusu</h3>
        <form class="d-flex" method="get" action="">
            <input name="q" class="form-control me-2" placeholder="from:, subject:, has:attachment ..." value="<?= htmlspecialchars($q) ?>">
            <button class="btn btn-dark">Ara</button>
        </form>
    </div>
    <div class="list-group">
        <?php
        if (!$messages) {
            echo '<div class="alert alert-secondary">Mail bulunamadı.</div>';
        } else {
            foreach ($messages as $m) {
                $msg = $gmail->users_messages->get('me', $m->getId(), ['format' => 'metadata', 'metadataHeaders' => ['From','Subject','Date']]);
                $headers = $msg->getPayload()->getHeaders();
                $from    = headerValue($headers, 'From');
                $subject = headerValue($headers, 'Subject');
                $date    = headerValue($headers, 'Date');
                $snippet = $msg->getSnippet();
                $threadId= $msg->getThreadId();

                $openInGmail = 'https://mail.google.com/mail/u/0/#inbox/' . urlencode($threadId);
                ?>
                <a class="list-group-item list-group-item-action" href="gmail_read.php?id=<?= urlencode($m->getId()) ?>">
                    <div class="d-flex w-100 justify-content-between">
                        <h5 class="mb-1"><?= htmlspecialchars($subject ?: '(No subject)') ?></h5>
                        <small><?= htmlspecialchars($date) ?></small>
                    </div>
                    <small class="text-muted d-block mb-1"><?= htmlspecialchars($from) ?></small>
                    <p class="mb-1" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($snippet) ?></p>
                    <small><u target="_blank" onclick="window.open('<?= $openInGmail ?>','_blank');return false;">Gmail’de aç</u></small>
                </a>
                <?php
            }
        }
        ?>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <a class="btn btn-outline-danger" href="gmail_disconnect.php" onclick="return confirm('Bağı kaldır?')">Bağlantıyı Kaldır</a>
        <div>
            <?php if ($pageToken):
                $backUrl = 'gmail_list.php?' . http_build_query(['q'=>$q]);
                ?>
                <a class="btn btn-outline-secondary me-2" href="<?= $backUrl ?>">Geri</a>
            <?php endif; ?>
            <?php if ($nextPageToken):
                $nextUrl = 'gmail_list.php?' . http_build_query(['q'=>$q, 'pageToken'=>$nextPageToken]);
                ?>
                <a class="btn btn-dark" href="<?= $nextUrl ?>">Sonraki</a>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
</body>
</html>
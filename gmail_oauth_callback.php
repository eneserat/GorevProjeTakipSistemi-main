<?php
session_start();
if (empty($_SESSION['user_id'])) { die('Auth required'); }

require_once __DIR__ . '/gmail_client.php';

$client = getGoogleClient();

if (!isset($_GET['code'])) {
    die('Auth code yok');
}
$post = [
    'code'          => $_GET['code'],
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'grant_type'    => 'authorization_code',
];

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_POST            => true,
    CURLOPT_POSTFIELDS      => http_build_query($post),
    CURLOPT_RETURNTRANSFER  => true,
    CURLOPT_TIMEOUT         => 20,
    CURLOPT_SSL_VERIFYPEER  => true,
]);

$resp = curl_exec($ch);
if ($resp === false) {
    die('cURL error: ' . curl_error($ch));
}
curl_close($ch);

$token = json_decode($resp, true);
if (!is_array($token) || isset($token['error'])) {
    header('Content-Type: text/plain; charset=UTF-8');
    die('TOKEN ERROR (raw): ' . $resp);
}

$client->setAccessToken($token);

try {
    $gmail   = new Google_Service_Gmail($client);
    $profile = $gmail->users->getProfile('me');
    $googleEmail = $profile->getEmailAddress();
} catch (Throwable $e) {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "GMAIL PROFILE ERROR:\n", $e;
    exit;
}

$token['email'] = $googleEmail;
saveTokenForUser((int)$_SESSION['user_id'], $googleEmail, $token);

$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
header('Location: ' . $base . '/gmail_list.php');
exit;

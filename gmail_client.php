<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config_gmail.php';

require_once __DIR__ . '/includes/db.php';

function getGoogleClient(): Google_Client {
    $client = new Google_Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->setRedirectUri(GOOGLE_REDIRECT_URI);
    $client->setAccessType('offline');
    $client->setPrompt('consent');
    $client->setIncludeGrantedScopes(true);
    $client->setScopes(GMAIL_SCOPES);
    $httpClient = new \GuzzleHttp\Client([
        'timeout'     => 20,
        'http_errors' => false,
        'verify'      => true,
    ]);
    $client->setHttpClient($httpClient);

    return $client;
}

function getStoredTokenForUser(int $userId): ?array {
    global $pdo;
    $st = $pdo->prepare("SELECT token_json FROM gmail_tokens WHERE user_id = ?");
    $st->execute([$userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $token = json_decode($row['token_json'], true);
    return is_array($token) ? $token : null;
}

function saveTokenForUser(int $userId, string $googleEmail, array $token): void {
    global $pdo;
    $json = json_encode($token, JSON_UNESCAPED_UNICODE);
    $st = $pdo->prepare("
        INSERT INTO gmail_tokens (user_id, google_user_email, token_json)
        VALUES (:uid, :email, :tok)
        ON DUPLICATE KEY UPDATE google_user_email = VALUES(google_user_email),
                                token_json = VALUES(token_json),
                                updated_at = CURRENT_TIMESTAMP
    ");
    $st->execute([':uid'=>$userId, ':email'=>$googleEmail, ':tok'=>$json]);
}

function deleteTokenForUser(int $userId): void {
    global $pdo;
    $pdo->prepare("DELETE FROM gmail_tokens WHERE user_id=?")->execute([$userId]);
}

function getGmailServiceFor(int $userId) {
    $client = getGoogleClient();
    $token = getStoredTokenForUser($userId);
    if (!$token) return false;

    $client->setAccessToken($token);

    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            if (isset($newToken['access_token'])) {
                $merged = array_merge($token, $newToken);
                saveTokenForUser($userId, $token['email'] ?? ($token['id_token_email'] ?? ''), $merged);
                $client->setAccessToken($merged);
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    return new Google_Service_Gmail($client);
}

function b64url_decode($data) {
    $data = strtr($data, '-_', '+/');
    return base64_decode($data);
}

function headerValue(array $headers, string $name): string {
    foreach ($headers as $h) {
        if (strcasecmp($h->getName(), $name) === 0) return $h->getValue();
    }
    return '';
}

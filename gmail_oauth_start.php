<?php
session_start();
if (empty($_SESSION['user_id'])) { die('Auth required'); }

require_once __DIR__ . '/gmail_client.php';
$client = getGoogleClient();
$authUrl = $client->createAuthUrl();
header("Location: " . $authUrl);
exit;

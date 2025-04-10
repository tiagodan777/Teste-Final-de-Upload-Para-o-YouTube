<?php
require_once 'vendor/autoload.php';
require_once 'db.php';

session_start();

$client = new Google\Client();
$client->setAuthConfig('client_secret.json');
$client->setRedirectUri('http://localhost:8888/Teste-Final-de-Upload-Para-o-YouTube/callback.php');
$client->addScope(Google\Service\YouTube::YOUTUBE_UPLOAD);

if (!isset($_GET['code'])) {
    die('Código não recebido');
}

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

if (isset($token['error'])) {
    die('Erro ao receber o token' . $token['error_description']);
}

$access_token = $token['access_token'];
$refresh_token = $token['refresh_token'];
$expires_in = $token['expires_in'];

$pdo->exec('DELETE FROM youtube_tokens');

$stmt = $pdo->prepare("INSERT INTO youtube_tokens (access_token, refresh_token, expires_in) VALUES (?, ?, ?)");
$stmt->execute([
    $token['access_token'],
    $token['refresh_token'],
    $token['expires_in']
]);

echo "✅ Token salvo com sucesso! Agora pode usar o upload.php sem autenticação.";
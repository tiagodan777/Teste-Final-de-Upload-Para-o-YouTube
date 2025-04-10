<?php
require_once 'vendor/autoload.php';

$client = new Google\Client();
$client->setAuthConfig('client_secret.json');
$client->setRedirectUri('http://localhost:8888/Teste-Final-de-Upload-Para-o-YouTube/callback.php'); // ou o domínio onde está rodando
$client->addScope(Google\Service\YouTube::YOUTUBE_UPLOAD);
$client->setAccessType('offline');
$client->setPrompt('consent'); // para garantir que o refresh_token seja enviado

$authUrl = $client->createAuthUrl();

echo "<a href='$authUrl'>Fazer login com conta do YouTube</a>";
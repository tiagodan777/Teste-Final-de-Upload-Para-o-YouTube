<?php
require_once 'vendor/autoload.php';
require_once 'db.php';

session_start();

// Mostrar formulário
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo <<<HTML
    <h1>Enviar vídeo para o YouTube</h1>
    <form method="post" enctype="multipart/form-data">
        <label>Título:<br><input type="text" name="title" required></label><br><br>
        <label>Descrição:<br><textarea name="description" required></textarea></label><br><br>
        <label>Tags (separadas por vírgula):<br><input type="text" name="tags"></label><br><br>
        <label>Arquivo de vídeo:<br><input type="file" name="video" accept="video/*" required></label><br><br>
        <label>Thumbnail (opcional):<br><input type="file" name="thumbnail" accept="image/*"></label><br><br>
        <button type="submit">Enviar</button>
    </form>
HTML;
    exit;
}

// Validar vídeo
if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
    die('Erro ao fazer upload do vídeo.');
}

// Salvar vídeo temporariamente
$tmpVideo = $_FILES['video']['tmp_name'];
$videoName = basename($_FILES['video']['name']);
$videoPath = __DIR__ . "/video_uploads/" . $videoName;

if (!move_uploaded_file($tmpVideo, $videoPath)) {
    die('Erro ao mover o arquivo de vídeo.');
}

// Thumbnail (se houver)
$thumbnailPath = null;
if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
    $tmpThumb = $_FILES['thumbnail']['tmp_name'];
    $thumbName = basename($_FILES['thumbnail']['name']);
    $thumbnailPath = __DIR__ . "/thumbnails/" . $thumbName;

    if (!move_uploaded_file($tmpThumb, $thumbnailPath)) {
        die('Erro ao mover a thumbnail.');
    }
}

// Preparar cliente
$client = new Google\Client();
$client->setAuthConfig('client_secret.json');
$client->addScope(Google\Service\YouTube::YOUTUBE_UPLOAD);
$client->setAccessType('offline');

// Buscar token
$stmt = $pdo->query("SELECT * FROM youtube_tokens ORDER BY id DESC LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    die("Token do YouTube não encontrado. Faça login como admin.");
}

$token = [
    'access_token' => $row['access_token'],
    'refresh_token' => $row['refresh_token'],
    'expires_in' => $row['expires_in'],
    'created' => strtotime($row['created_at']),
];

$client->setAccessToken($token);

// Renovar se necessário
if ($client->isAccessTokenExpired()) {
    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
    $newToken = $client->getAccessToken();
    $stmt = $pdo->prepare("UPDATE youtube_tokens SET access_token = ?, expires_in = ?, created_at = NOW() WHERE id = ?");
    $stmt->execute([
        $newToken['access_token'],
        $newToken['expires_in'],
        $row['id']
    ]);
}

$youtube = new Google\Service\YouTube($client);

// Preparar metadados
$title = $_POST['title'];
$description = $_POST['description'];
$tags = array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')));

$snippet = new Google\Service\YouTube\VideoSnippet();
$snippet->setTitle($title);
$snippet->setDescription($description);
if (!empty($tags)) {
    $snippet->setTags($tags);
}
$snippet->setCategoryId("22"); // Categoria: People & Blogs

$status = new Google\Service\YouTube\VideoStatus();
$status->privacyStatus = 'private'; // Pode mudar para 'public' ou 'unlisted'
$status->selfDeclaredMadeForKids = false; // Não é conteúdo infantil

$video = new Google\Service\YouTube\Video();
$video->setSnippet($snippet);
$video->setStatus($status);

// Upload do vídeo
$client->setDefer(true);
$request = $youtube->videos->insert('status,snippet', $video);

$media = new Google\Http\MediaFileUpload(
    $client,
    $request,
    mime_content_type($videoPath),
    null,
    true,
    1024 * 1024
);

$media->setFileSize(filesize($videoPath));

$handle = fopen($videoPath, "rb");
$status = false;

while (!$status && !feof($handle)) {
    $chunk = fread($handle, 1024 * 1024);
    $status = $media->nextChunk($chunk);
}
fclose($handle);
$client->setDefer(false);

unlink($videoPath);

// Upload da thumbnail
if ($thumbnailPath) {
    $youtube->thumbnails->set($status['id'], [
        'data' => file_get_contents($thumbnailPath),
        'mimeType' => mime_content_type($thumbnailPath),
        'uploadType' => 'media'
    ]);
    unlink($thumbnailPath);
}

echo "✅ Vídeo enviado com sucesso para o canal!";
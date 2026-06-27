<?php
require_once __DIR__ . '/security.php';

where2go_mobile_security_headers('POST, OPTIONS');

function where2go_mobile_photo_base_url(): string
{
    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $isHttps = $forwardedProto === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $projectPath = preg_replace('#/api/mobile$#', '', $scriptDir);

    return rtrim($scheme . '://' . $host . $projectPath, '/');
}

function where2go_mobile_photo_reply(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    where2go_mobile_photo_reply(405, ['ok' => false, 'message' => 'POST is required.']);
}

$customerId = (int) ($_POST['customer_id'] ?? 0);
$auth = where2go_mobile_security_require_customer($customerId > 0 ? $customerId : null);
$customerId = (int) $auth['customerId'];

if (empty($_FILES['photo']) || !is_uploaded_file($_FILES['photo']['tmp_name'] ?? '')) {
    where2go_mobile_photo_reply(422, ['ok' => false, 'message' => 'Choose a profile picture first.']);
}

$tmpName = (string) $_FILES['photo']['tmp_name'];
$size = (int) ($_FILES['photo']['size'] ?? 0);
$imageInfo = @getimagesize($tmpName);

if (!$imageInfo || $size <= 0 || $size > 5 * 1024 * 1024) {
    where2go_mobile_photo_reply(422, ['ok' => false, 'message' => 'Use a valid image under 5 MB.']);
}

$mime = (string) ($imageInfo['mime'] ?? '');
$extension = match ($mime) {
    'image/png' => 'png',
    'image/webp' => 'webp',
    default => 'jpg',
};
$uploadDir = realpath(__DIR__ . '/../../assets/images/uploads');

if (!$uploadDir) {
    $uploadDir = __DIR__ . '/../../assets/images/uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }
}

foreach (glob($uploadDir . DIRECTORY_SEPARATOR . 'profile-' . $customerId . '.*') ?: [] as $oldFile) {
    if (is_file($oldFile)) {
        @unlink($oldFile);
    }
}

$fileName = 'profile-' . $customerId . '.' . $extension;
$targetPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

if (!move_uploaded_file($tmpName, $targetPath)) {
    where2go_mobile_photo_reply(500, ['ok' => false, 'message' => 'Profile picture could not be saved.']);
}

$photoPath = 'assets/images/uploads/' . $fileName;

where2go_mobile_photo_reply(200, [
    'ok' => true,
    'message' => 'Profile picture uploaded.',
    'photoUrl' => where2go_mobile_photo_base_url() . '/' . $photoPath,
    'photoPath' => $photoPath,
]);

<?php
require_once __DIR__ . '/includes/functions.php';

start_session();

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'POST is required.'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Login required.'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = [];
$rawBody = file_get_contents('php://input');
$decoded = $rawBody !== '' ? json_decode($rawBody, true) : null;

if (is_array($decoded)) {
    $payload = $decoded;
}

$token = trim((string) ($_POST['token'] ?? ($payload['token'] ?? '')));
$result = claim_location_checkin_reward((int) ($_SESSION['customer_id'] ?? 0), $token);
$status = 200;
$code = trim((string) ($result['code'] ?? ''));

if (empty($result['ok'])) {
    if (in_array($code, ['rapid_repeat_blocked', 'daily_limit_reached', 'place_cooldown_active', 'same_day_repeat_limit'], true)) {
        $status = 429;
    } elseif ($code === 'no_pending_box') {
        $status = 404;
    } else {
        $status = 422;
    }
}

http_response_code($status);
echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);


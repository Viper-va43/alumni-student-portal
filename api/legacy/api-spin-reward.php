<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';

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

if (!verify_csrf_token($_POST['csrf_token'] ?? ($payload['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Your session expired. Please refresh and try again.'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$boxId = (int) ($_POST['box_id'] ?? ($payload['box_id'] ?? 0));
$result = spin_reward_box((int) ($_SESSION['customer_id'] ?? 0), $boxId);
$status = !empty($result['ok']) ? 200 : (($result['code'] ?? '') === 'no_pending_box' ? 404 : 422);

http_response_code($status);
echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/place_data.php';

start_session();

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Login required.']);
    exit;
}

$placeId = trim($_POST['place_id'] ?? '');
$action = trim($_POST['action'] ?? 'save');
$source = trim($_POST['source'] ?? 'catalog');
$payloadJson = $_POST['payload'] ?? '';
$payload = [];

if ($payloadJson !== '') {
    $decoded = json_decode($payloadJson, true);

    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

if ($placeId === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Unknown place.']);
    exit;
}

if ($action !== 'save' && $action !== 'remove') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Unknown action.']);
    exit;
}

if ($action === 'remove') {
    remove_place_visit($placeId);

    echo json_encode([
        'ok' => true,
        'saved' => false,
        'visited' => get_visited_place_ids(),
    ]);
    exit;
}

if ($source === 'catalog' && !get_place_by_id($placeId)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Unknown place.']);
    exit;
}

if ($source === 'google') {
    $hasName = trim((string) ($payload['name'] ?? '')) !== '';
    $hasAddress = trim((string) ($payload['address'] ?? $payload['formatted_address'] ?? '')) !== '';

    if (!$hasName || !$hasAddress) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'This Google place is missing the data needed to save it.']);
        exit;
    }
}

if ($source !== 'catalog' && $source !== 'google') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Unknown place.']);
    exit;
}

record_place_visit($placeId, $source, $payload);

echo json_encode([
    'ok' => true,
    'saved' => true,
    'visited' => get_visited_place_ids(),
]);

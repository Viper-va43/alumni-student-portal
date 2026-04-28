<?php
// Load the save/remove helpers used by the AJAX endpoint.
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/place_data.php';

start_session();

// Always answer with JSON because this file is called from fetch requests.
header('Content-Type: application/json');

// Block anonymous users before any saved-place action is processed.
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Login required.']);
    exit;
}

// Read the place details sent by the front-end save buttons.
$placeId = trim($_POST['place_id'] ?? '');
$businessId = (int) ($_POST['business_id'] ?? 0);
$locationId = (int) ($_POST['location_id'] ?? 0);
$action = trim($_POST['action'] ?? 'save');
$source = trim($_POST['source'] ?? 'catalog');
$payloadJson = $_POST['payload'] ?? '';
$payload = [];

// Decode any optional metadata that helps identify partner businesses and locations.
if ($payloadJson !== '') {
    $decoded = json_decode($payloadJson, true);

    if (is_array($decoded)) {
        $payload = $decoded;
    }
    if ($businessId <= 0) {
        $businessId = (int) ($payload['business_id'] ?? 0);
    }

    if ($locationId <= 0) {
        $locationId = (int) ($payload['location_id'] ?? 0);
    }
}

// Normalize the request into a consistent target record for catalog places or businesses.
$target = resolve_saved_place_target($placeId, array_merge($payload, [
    'business_id' => $businessId,
    'location_id' => $locationId,
]));
$isDatabaseTarget = $target['business_id'] > 0 || $target['location_id'] > 0 || in_array($source, ['business', 'business_location', 'location'], true);

// Reject requests that still do not identify any known place after normalization.
if ($placeId === '' && !$isDatabaseTarget) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Unknown place.']);
    exit;
}

// Only allow the two supported actions from the save button workflow.
if ($action !== 'save' && $action !== 'remove') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Unknown action.']);
    exit;
}

// Remove an existing saved place and return the refreshed saved list.
if ($action === 'remove') {
    remove_place_visit($placeId, [
        'business_id' => $target['business_id'],
        'location_id' => $target['location_id'],
    ]);

    echo json_encode([
        'ok' => true,
        'saved' => false,
        'visited' => get_visited_place_ids(),
    ]);
    exit;
}

// Validate partner business targets against the database before saving them.
if ($isDatabaseTarget) {
    $businessId = (int) $target['business_id'];
    $locationId = (int) $target['location_id'];

    if ($locationId > 0 && !get_location_by_id($locationId)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Unknown business location.']);
        exit;
    }

    if ($businessId > 0 && !get_business_by_id($businessId)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Unknown business.']);
        exit;
    }

    $source = $locationId > 0 ? 'business_location' : 'business';
    $payload['business_id'] = $businessId;
    $payload['location_id'] = $locationId;
} elseif ($source === 'catalog' && !get_place_by_id($placeId)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Unknown place.']);
    exit;
}

// Google-sourced places must include enough metadata to be rebuilt later.
if ($source === 'google') {
    $hasName = trim((string) ($payload['name'] ?? '')) !== '';
    $hasAddress = trim((string) ($payload['address'] ?? $payload['formatted_address'] ?? '')) !== '';

    if (!$hasName || !$hasAddress) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'This Google place is missing the data needed to save it.']);
        exit;
    }
}

// Any non-catalog source at this point must still resolve to a database-backed business target.
if ($source !== 'catalog' && $source !== 'google') {
    if (!$isDatabaseTarget) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Unknown place.']);
        exit;
    }
}

// Persist the save action and return the updated ids so the UI can stay in sync.
record_place_visit($placeId, $source, $payload);

echo json_encode([
    'ok' => true,
    'saved' => true,
    'visited' => get_visited_place_ids(),
]);

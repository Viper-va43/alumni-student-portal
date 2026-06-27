<?php
require_once __DIR__ . '/security.php';

where2go_mobile_security_headers('GET, POST, OPTIONS');

function where2go_mobile_saved_json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode((string) $raw, true);

    return is_array($data) ? $data : $_POST;
}

function where2go_mobile_saved_place_id(int $businessId, int $locationId): string
{
    return $businessId > 0 ? 'business-' . $businessId : ($locationId > 0 ? 'location-' . $locationId : '');
}

function where2go_mobile_saved_ids(int $customerId): array
{
    $ids = [];

    foreach (get_customer_saved_place_targets($customerId) as $target) {
        $businessId = (int) ($target['business_id'] ?? 0);
        $locationId = (int) ($target['location_id'] ?? 0);
        $id = where2go_mobile_saved_place_id($businessId, $locationId);

        if ($id !== '') {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $customerId = (int) ($_GET['customer_id'] ?? 0);

    if ($customerId <= 0) {
        echo json_encode(['ok' => true, 'savedPlaceIds' => []]);
        exit;
    }

    $auth = where2go_mobile_security_require_customer($customerId);
    $customerId = (int) $auth['customerId'];

    echo json_encode([
        'ok' => true,
        'savedPlaceIds' => where2go_mobile_saved_ids($customerId),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Unsupported request method.']);
    exit;
}

$data = where2go_mobile_saved_json_input();
$customerId = (int) ($data['customer_id'] ?? 0);
$businessId = (int) ($data['business_id'] ?? 0);
$locationId = (int) ($data['location_id'] ?? 0);
$action = trim((string) ($data['action'] ?? 'save'));
$auth = where2go_mobile_security_require_customer($customerId > 0 ? $customerId : null);
$customerId = (int) $auth['customerId'];

if ($businessId <= 0 && $locationId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'A valid place is required.']);
    exit;
}

$ok = $action === 'remove'
    ? remove_customer_saved_place_record($customerId, $businessId, $locationId)
    : save_customer_place_record($customerId, $businessId, $locationId);

if (!$ok) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Saved places could not be updated.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'savedPlaceIds' => where2go_mobile_saved_ids($customerId),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

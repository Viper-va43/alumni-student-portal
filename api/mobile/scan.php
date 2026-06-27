<?php
require_once __DIR__ . '/security.php';

where2go_mobile_security_headers('POST, OPTIONS');

function where2go_mobile_scan_json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode((string) $raw, true);

    return is_array($data) ? $data : $_POST;
}

function where2go_mobile_scan_token(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    $parts = parse_url($value);

    if (is_array($parts) && !empty($parts['query'])) {
        parse_str((string) $parts['query'], $query);
        $token = trim((string) ($query['token'] ?? ''));

        if ($token !== '') {
            return $token;
        }
    }

    if (preg_match('/token=([^&]+)/', $value, $matches)) {
        return rawurldecode((string) $matches[1]);
    }

    return $value;
}

function where2go_mobile_scan_status(array $result): int
{
    if (!empty($result['ok'])) {
        return 200;
    }

    $code = trim((string) ($result['code'] ?? ''));

    if (in_array($code, ['rapid_repeat_blocked', 'daily_limit_reached', 'place_cooldown_active', 'same_day_repeat_limit'], true)) {
        return 429;
    }

    return 422;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'POST is required.']);
    exit;
}

$data = where2go_mobile_scan_json_input();
$customerId = (int) ($data['customer_id'] ?? 0);
$qrData = trim((string) ($data['qr_data'] ?? ($data['token'] ?? '')));
$token = where2go_mobile_scan_token($qrData);
$auth = where2go_mobile_security_require_customer($customerId > 0 ? $customerId : null);
$customerId = (int) $auth['customerId'];

if ($token === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'This QR code does not contain a Where2Go check-in token.']);
    exit;
}

$result = claim_location_checkin_reward($customerId, $token);
$status = where2go_mobile_scan_status($result);

http_response_code($status);
echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

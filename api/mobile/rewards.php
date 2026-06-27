<?php
require_once __DIR__ . '/security.php';

where2go_mobile_security_headers('GET, OPTIONS');

function where2go_mobile_reward_box_payload(array $box): array
{
    return [
        'id' => (int) ($box['id'] ?? 0),
        'businessName' => (string) ($box['business_name'] ?? 'Where2Go place'),
        'locationName' => (string) ($box['location_name'] ?? ''),
        'unlockPoints' => (int) ($box['unlock_points'] ?? 0),
        'triggerLevel' => (int) ($box['trigger_level'] ?? 0),
        'createdAt' => (string) ($box['created_at'] ?? ''),
    ];
}

function where2go_mobile_checkin_payload(array $checkin): array
{
    return [
        'id' => (int) ($checkin['id'] ?? 0),
        'businessName' => (string) ($checkin['business_name'] ?? 'Where2Go place'),
        'locationName' => (string) ($checkin['location_name'] ?? ''),
        'points' => (int) ($checkin['points_awarded'] ?? 0),
        'scanType' => (string) ($checkin['scan_type'] ?? 'scan'),
        'checkedInAt' => (string) ($checkin['checked_in_at'] ?? ''),
    ];
}

function where2go_mobile_voucher_payload(array $voucher): array
{
    return [
        'id' => (int) ($voucher['id'] ?? 0),
        'businessName' => (string) ($voucher['business_name'] ?? 'Where2Go place'),
        'locationName' => (string) ($voucher['location_name'] ?? ''),
        'label' => (string) ($voucher['reward_label'] ?? 'Reward'),
        'value' => (int) ($voucher['reward_value'] ?? 0),
        'code' => (string) ($voucher['voucher_code'] ?? ''),
        'used' => (bool) ((int) ($voucher['used'] ?? 0)),
        'expiresAt' => (string) ($voucher['expires_at'] ?? ''),
    ];
}

$customerId = (int) ($_GET['customer_id'] ?? 0);

if ($customerId <= 0) {
    echo json_encode([
        'ok' => true,
        'summary' => get_customer_rewards_summary(0),
        'checkins' => [],
        'vouchers' => [],
        'pendingBoxes' => [],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$auth = where2go_mobile_security_require_customer($customerId);
$customerId = (int) $auth['customerId'];

echo json_encode([
    'ok' => true,
    'summary' => get_customer_rewards_summary($customerId),
    'checkins' => array_map('where2go_mobile_checkin_payload', get_customer_recent_checkins($customerId, 5)),
    'vouchers' => array_map('where2go_mobile_voucher_payload', get_customer_reward_vouchers($customerId, 5, true)),
    'pendingBoxes' => array_map('where2go_mobile_reward_box_payload', get_customer_pending_reward_boxes($customerId, 5)),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

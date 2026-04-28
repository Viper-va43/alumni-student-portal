<?php
require_once __DIR__ . '/includes/functions.php';

start_session();

header('Content-Type: application/json; charset=UTF-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Login required.'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$customerId = (int) ($_SESSION['customer_id'] ?? 0);
$customer = get_customer_by_id($customerId) ?: [];

echo json_encode([
    'ok' => true,
    'user' => [
        'id' => $customerId,
        'name' => trim((string) (($customer['First_N'] ?? '') . ' ' . ($customer['Last_N'] ?? ''))),
        'email' => trim((string) ($customer['Email'] ?? ($_SESSION['customer_email'] ?? ''))),
    ],
    'summary' => get_customer_rewards_summary($customerId),
    'pending_boxes' => get_customer_pending_reward_boxes($customerId, 5),
    'rewards' => get_customer_reward_vouchers($customerId, 20, true),
    'recent_checkins' => get_customer_recent_checkins($customerId, 10),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);


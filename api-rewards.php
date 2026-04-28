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
$includeUsedParam = strtolower(trim((string) ($_GET['include_used'] ?? '1')));
$includeUsed = !in_array($includeUsedParam, ['0', 'false', 'no'], true);

echo json_encode([
    'ok' => true,
    'summary' => get_customer_rewards_summary($customerId),
    'pending_boxes' => get_customer_pending_reward_boxes($customerId, 10),
    'rewards' => get_customer_reward_vouchers($customerId, 50, $includeUsed),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);


<?php
require_once __DIR__ . '/security.php';

where2go_mobile_security_headers('GET, OPTIONS');

function where2go_mobile_count(mysqli $conn, string $sql, int $customerId): int
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int) ($row['total'] ?? 0);
}

function where2go_mobile_count_all(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);
    $row = $result ? $result->fetch_assoc() : null;

    return (int) ($row['total'] ?? 0);
}

function where2go_mobile_profile_stats(mysqli $conn, int $customerId): array
{
    $stmt = $conn->prepare('
        SELECT
            (SELECT COUNT(*) FROM customer_saved_places WHERE customer_id = ?) AS savedPlaces,
            (SELECT COUNT(*) FROM bookings WHERE customer_id = ?) AS bookings,
            (SELECT COUNT(*) FROM customer_place_visits) AS visits,
            (SELECT COUNT(*) FROM user_rewards WHERE user_id = ?) AS rewards,
            (SELECT COUNT(*) FROM customer_checkins WHERE customer_id = ?) AS checkins
    ');

    if (!$stmt) {
        return [
            'savedPlaces' => where2go_mobile_count($conn, 'SELECT COUNT(*) AS total FROM customer_saved_places WHERE customer_id = ?', $customerId),
            'bookings' => where2go_mobile_count($conn, 'SELECT COUNT(*) AS total FROM bookings WHERE customer_id = ?', $customerId),
            'visits' => where2go_mobile_count_all($conn, 'SELECT COUNT(*) AS total FROM customer_place_visits'),
            'rewards' => where2go_mobile_count($conn, 'SELECT COUNT(*) AS total FROM user_rewards WHERE user_id = ?', $customerId),
            'checkins' => where2go_mobile_count($conn, 'SELECT COUNT(*) AS total FROM customer_checkins WHERE customer_id = ?', $customerId),
        ];
    }

    $stmt->bind_param('iiii', $customerId, $customerId, $customerId, $customerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    return [
        'savedPlaces' => (int) ($row['savedPlaces'] ?? 0),
        'bookings' => (int) ($row['bookings'] ?? 0),
        'visits' => (int) ($row['visits'] ?? 0),
        'rewards' => (int) ($row['rewards'] ?? 0),
        'checkins' => (int) ($row['checkins'] ?? 0),
    ];
}

function where2go_mobile_profile_base_url(): string
{
    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $isHttps = $forwardedProto === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $projectPath = preg_replace('#/api/mobile$#', '', $scriptDir);

    return rtrim($scheme . '://' . $host . $projectPath, '/');
}

function where2go_mobile_profile_photo_url(int $customerId): string
{
    $path = get_profile_photo_web_path($customerId);

    if (!$path) {
        return '';
    }

    return where2go_mobile_profile_base_url() . '/' . ltrim((string) $path, '/');
}

function where2go_mobile_profile_ensure_customer_schema(mysqli $conn): void
{
    ensure_table_column(
        $conn,
        'customers',
        'Age',
        'ALTER TABLE customers ADD COLUMN Age INT NULL AFTER Last_N'
    );
}

$conn = db_connect();
where2go_mobile_profile_ensure_customer_schema($conn);
$requestedCustomerId = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : 0;
$result = null;
$stmt = null;
$presentedToken = where2go_mobile_security_request_token() !== '';
$auth = where2go_mobile_security_auth_context();

if ($requestedCustomerId > 0 || $auth || $presentedToken) {
    $auth = where2go_mobile_security_require_customer($requestedCustomerId > 0 ? $requestedCustomerId : null);
    $requestedCustomerId = (int) $auth['customerId'];
}

if ($requestedCustomerId > 0) {
    $stmt = $conn->prepare("
        SELECT Customer_ID, First_N, Middle_N, Last_N, Age, Email, Customer_NUM, Date_Of_Birth, Physical_Address, Nationality, Created_At
        FROM customers
        WHERE Customer_ID = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $requestedCustomerId);
    $stmt->execute();
    $result = $stmt->get_result();
}

$customer = $result ? $result->fetch_assoc() : null;

if (isset($stmt) && $stmt) {
    $stmt->close();
}

if (!$customer) {
    echo json_encode([
        'ok' => true,
        'profile' => [
            'id' => null,
            'name' => 'Guest traveler',
            'email' => 'Sign in to sync your Where2Go profile',
            'memberSince' => '',
        ],
        'stats' => [
            'savedPlaces' => 0,
            'bookings' => 0,
            'visits' => 0,
            'rewards' => 0,
            'checkins' => 0,
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$customerId = (int) $customer['Customer_ID'];
$nameParts = array_filter([
    trim((string) $customer['First_N']),
    trim((string) $customer['Middle_N']),
    trim((string) $customer['Last_N']),
]);
$fullName = trim(implode(' ', $nameParts));

if ($fullName === '') {
    $fullName = 'Where2Go customer';
}

echo json_encode([
    'ok' => true,
    'profile' => [
        'id' => $customerId,
        'name' => $fullName,
        'email' => (string) $customer['Email'],
        'memberSince' => (string) $customer['Created_At'],
        'photoUrl' => where2go_mobile_profile_photo_url($customerId),
        'middleName' => (string) ($customer['Middle_N'] ?? ''),
        'lastName' => (string) ($customer['Last_N'] ?? ''),
        'age' => isset($customer['Age']) ? (int) $customer['Age'] : null,
        'phone' => (string) ($customer['Customer_NUM'] ?? ''),
        'dateOfBirth' => (string) ($customer['Date_Of_Birth'] ?? ''),
        'address' => (string) ($customer['Physical_Address'] ?? ''),
        'nationality' => (string) ($customer['Nationality'] ?? ''),
    ],
    'stats' => where2go_mobile_profile_stats($conn, $customerId),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

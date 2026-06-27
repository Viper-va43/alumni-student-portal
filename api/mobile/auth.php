<?php
require_once __DIR__ . '/security.php';

where2go_mobile_security_headers('POST, OPTIONS');

function where2go_mobile_auth_json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode((string) $raw, true);

    return is_array($data) ? $data : $_POST;
}

function where2go_mobile_auth_reply(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

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

function where2go_mobile_auth_stats(mysqli $conn, int $customerId): array
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

function where2go_mobile_auth_base_url(): string
{
    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $isHttps = $forwardedProto === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $projectPath = preg_replace('#/api/mobile$#', '', $scriptDir);

    return rtrim($scheme . '://' . $host . $projectPath, '/');
}

function where2go_mobile_auth_photo_url(int $customerId): string
{
    $path = get_profile_photo_web_path($customerId);

    if (!$path) {
        return '';
    }

    return where2go_mobile_auth_base_url() . '/' . ltrim((string) $path, '/');
}

function where2go_mobile_auth_ensure_customer_schema(mysqli $conn): void
{
    ensure_table_column(
        $conn,
        'customers',
        'Age',
        'ALTER TABLE customers ADD COLUMN Age INT NULL AFTER Last_N'
    );
}

function where2go_mobile_auth_nullable_string($value): ?string
{
    $value = trim((string) $value);

    return $value !== '' ? $value : null;
}

function where2go_mobile_auth_normalize_birth_date($value): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $value);
    $errors = DateTime::getLastErrors();

    if (!$date || ($errors && ((int) $errors['warning_count'] > 0 || (int) $errors['error_count'] > 0))) {
        where2go_mobile_auth_reply(422, ['ok' => false, 'message' => 'Date of birth must use YYYY-MM-DD.']);
    }

    if ($date > new DateTime('today')) {
        where2go_mobile_auth_reply(422, ['ok' => false, 'message' => 'Date of birth cannot be in the future.']);
    }

    return $date->format('Y-m-d');
}

function where2go_mobile_auth_profile(mysqli $conn, array $customer): array
{
    $customerId = (int) ($customer['Customer_ID'] ?? 0);
    $nameParts = array_filter([
        trim((string) ($customer['First_N'] ?? '')),
        trim((string) ($customer['Middle_N'] ?? '')),
        trim((string) ($customer['Last_N'] ?? '')),
    ]);
    $fullName = trim(implode(' ', $nameParts));

    if ($fullName === '') {
        $fullName = 'Where2Go customer';
    }

    return [
        'profile' => [
            'id' => $customerId,
            'name' => $fullName,
            'email' => (string) ($customer['Email'] ?? ''),
            'memberSince' => (string) ($customer['Created_At'] ?? ''),
            'photoUrl' => where2go_mobile_auth_photo_url($customerId),
            'middleName' => (string) ($customer['Middle_N'] ?? ''),
            'lastName' => (string) ($customer['Last_N'] ?? ''),
            'age' => isset($customer['Age']) ? (int) $customer['Age'] : null,
            'phone' => (string) ($customer['Customer_NUM'] ?? ''),
            'dateOfBirth' => (string) ($customer['Date_Of_Birth'] ?? ''),
            'address' => (string) ($customer['Physical_Address'] ?? ''),
            'nationality' => (string) ($customer['Nationality'] ?? ''),
        ],
        'stats' => where2go_mobile_auth_stats($conn, $customerId),
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    where2go_mobile_auth_reply(405, ['ok' => false, 'message' => 'Unsupported request method.']);
}

$data = where2go_mobile_auth_json_input();
$action = trim((string) ($data['action'] ?? 'login'));
$email = strtolower(trim((string) ($data['email'] ?? '')));
$password = (string) ($data['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    where2go_mobile_auth_reply(422, ['ok' => false, 'message' => 'Enter a valid email address.']);
}

if (strlen($password) < 6) {
    where2go_mobile_auth_reply(422, ['ok' => false, 'message' => 'Password must be at least 6 characters.']);
}

$conn = db_connect();
where2go_mobile_auth_ensure_customer_schema($conn);

if ($action === 'register') {
    $firstName = trim((string) ($data['first_name'] ?? ''));
    $middleName = where2go_mobile_auth_nullable_string($data['middle_name'] ?? '');
    $lastName = trim((string) ($data['last_name'] ?? ''));
    $ageInput = trim((string) ($data['age'] ?? ''));
    $phone = where2go_mobile_auth_nullable_string($data['phone'] ?? '');
    $dateOfBirth = where2go_mobile_auth_normalize_birth_date($data['date_of_birth'] ?? '');
    $address = where2go_mobile_auth_nullable_string($data['address'] ?? '');
    $nationality = where2go_mobile_auth_nullable_string($data['nationality'] ?? '');
    $age = $ageInput !== '' ? (int) $ageInput : null;

    if ($firstName === '') {
        where2go_mobile_auth_reply(422, ['ok' => false, 'message' => 'First name is required.']);
    }

    if ($lastName === '') {
        where2go_mobile_auth_reply(422, ['ok' => false, 'message' => 'Last name is required.']);
    }

    if ($age === null || !preg_match('/^\d{1,3}$/', $ageInput) || $age < 1 || $age > 120) {
        where2go_mobile_auth_reply(422, ['ok' => false, 'message' => 'Enter a valid age between 1 and 120.']);
    }

    if (!$phone || !preg_match('/^[0-9+\-\s()]{7,24}$/', $phone)) {
        where2go_mobile_auth_reply(422, ['ok' => false, 'message' => 'Enter a valid phone number.']);
    }

    if (!$dateOfBirth) {
        where2go_mobile_auth_reply(422, ['ok' => false, 'message' => 'Date of birth is required.']);
    }

    if (!$address) {
        where2go_mobile_auth_reply(422, ['ok' => false, 'message' => 'Address is required.']);
    }

    if (!$nationality) {
        where2go_mobile_auth_reply(422, ['ok' => false, 'message' => 'Nationality is required.']);
    }

    $checkStmt = $conn->prepare('SELECT Customer_ID FROM customers WHERE Email = ? LIMIT 1');

    if (!$checkStmt) {
        where2go_mobile_auth_reply(500, ['ok' => false, 'message' => 'Registration is temporarily unavailable.']);
    }

    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    $existing = $checkStmt->get_result();

    if ($existing && $existing->fetch_assoc()) {
        where2go_mobile_auth_reply(409, ['ok' => false, 'message' => 'This email already has an account. Try login instead.']);
    }

    $hash = hash_password($password);
    $insertStmt = $conn->prepare('
        INSERT INTO customers (First_N, Middle_N, Last_N, Age, Email, Password, Date_Of_Birth, Physical_Address, Customer_NUM, Verification_Status, Nationality, Created_At)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ');

    if (!$insertStmt) {
        where2go_mobile_auth_reply(500, ['ok' => false, 'message' => 'Registration is temporarily unavailable.']);
    }

    $verificationStatus = 'mobile';
    $insertStmt->bind_param(
        'sssisssssss',
        $firstName,
        $middleName,
        $lastName,
        $age,
        $email,
        $hash,
        $dateOfBirth,
        $address,
        $phone,
        $verificationStatus,
        $nationality
    );

    if (!$insertStmt->execute()) {
        where2go_mobile_auth_reply(500, ['ok' => false, 'message' => 'Registration could not be completed.']);
    }

    $customerId = (int) $insertStmt->insert_id;
    $stmt = $conn->prepare('
        SELECT Customer_ID, First_N, Middle_N, Last_N, Age, Email, Customer_NUM, Date_Of_Birth, Physical_Address, Nationality, Created_At
        FROM customers
        WHERE Customer_ID = ?
        LIMIT 1
    ');
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();

    if (!$customer) {
        where2go_mobile_auth_reply(500, ['ok' => false, 'message' => 'Account was created but profile could not load.']);
    }

    $profile = where2go_mobile_auth_profile($conn, $customer);
    $authToken = where2go_mobile_security_issue_token($customerId);

    where2go_mobile_auth_reply(201, [
        'ok' => true,
        'message' => 'Account created.',
        'auth' => $authToken,
        'profile' => $profile['profile'],
        'stats' => $profile['stats'],
        'bookings' => [],
    ]);
}

if ($action !== 'login') {
    where2go_mobile_auth_reply(400, ['ok' => false, 'message' => 'Choose login or register.']);
}

$rateLimit = get_login_rate_limit_status('mobile_customer', $email);

if (!empty($rateLimit['limited'])) {
    $waitMinutes = max(1, (int) ceil(((int) $rateLimit['retry_after_seconds']) / 60));
    where2go_mobile_auth_reply(429, [
        'ok' => false,
        'message' => 'Too many failed login attempts. Try again in about ' . $waitMinutes . ' minutes.',
    ]);
}

$stmt = $conn->prepare('
    SELECT Customer_ID, First_N, Middle_N, Last_N, Age, Email, Customer_NUM, Date_Of_Birth, Physical_Address, Nationality, Password, Created_At
    FROM customers
    WHERE Email = ?
    LIMIT 1
');

if (!$stmt) {
    where2go_mobile_auth_reply(500, ['ok' => false, 'message' => 'Login is temporarily unavailable.']);
}

$stmt->bind_param('s', $email);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

if (!$customer || !verify_password($password, (string) ($customer['Password'] ?? ''))) {
    record_login_attempt('mobile_customer', $email, false);
    where2go_mobile_auth_reply(401, ['ok' => false, 'message' => 'Email or password is incorrect.']);
}

$customerId = (int) ($customer['Customer_ID'] ?? 0);
clear_login_attempts('mobile_customer', $email);
record_login_attempt('mobile_customer', $email, true);
$profile = where2go_mobile_auth_profile($conn, $customer);
$authToken = where2go_mobile_security_issue_token($customerId);

where2go_mobile_auth_reply(200, [
    'ok' => true,
    'message' => 'Logged in.',
    'auth' => $authToken,
    'profile' => $profile['profile'],
    'stats' => $profile['stats'],
    'bookings' => [],
]);

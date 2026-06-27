<?php

/* -------------------------
   DATABASE CONNECTION
------------------------- */
function db_connect() {

    $host = getenv('WHERE2GO_DB_HOST') ?: "localhost";
    $user = getenv('WHERE2GO_DB_USER') ?: "root";
    $password = getenv('WHERE2GO_DB_PASS') ?: "";
    $database = getenv('WHERE2GO_DB_NAME') ?: "where2go";
    $charset = getenv('WHERE2GO_DB_CHARSET') ?: "utf8mb4";

    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = new mysqli($host, $user, $password, $database);

    if ($conn->connect_error) {
        error_log('Where2Go database connection failed: ' . $conn->connect_error);
        http_response_code(500);
        exit("Database connection is temporarily unavailable.");
    }

    if (!$conn->set_charset($charset)) {
        error_log('Where2Go database charset setup failed: ' . $conn->error);
    }

    return $conn;
}


/* -------------------------
   SANITIZE INPUT
------------------------- */
function clean_input($data) {

    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

    return $data;
}


/* -------------------------
   PASSWORD HASH
------------------------- */
function hash_password($password) {

    return password_hash($password, PASSWORD_DEFAULT);

}


/* -------------------------
   VERIFY PASSWORD
------------------------- */
function verify_password($input, $stored) {

    return password_verify($input, $stored);

}


/* -------------------------
   START SESSION
------------------------- */
function start_session() {

    if(session_status() === PHP_SESSION_NONE) {
        $secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secureCookie,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

}


/* -------------------------
   CSRF PROTECTION
------------------------- */
function get_csrf_token() {

    start_session();

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];

}


function csrf_field() {

    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';

}


function verify_csrf_token($token) {

    start_session();
    $token = is_string($token) ? $token : '';
    $sessionToken = is_string($_SESSION['csrf_token'] ?? null) ? $_SESSION['csrf_token'] : '';

    return $token !== '' && $sessionToken !== '' && hash_equals($sessionToken, $token);

}


/* -------------------------
   LOGIN RATE LIMITING
------------------------- */
function get_client_ip_address() {

    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

    return $ip !== '' ? substr($ip, 0, 45) : 'unknown';

}


function normalize_login_identifier($identifier) {

    $identifier = strtolower(trim((string) $identifier));

    return $identifier !== '' ? $identifier : 'unknown';

}


function get_login_attempt_hash($scope, $identifier) {

    return hash('sha256', strtolower(trim((string) $scope)) . '|' . normalize_login_identifier($identifier) . '|' . get_client_ip_address());

}


function ensure_login_attempts_table($conn = null) {

    static $ready = false;

    if ($ready) {
        return true;
    }

    $conn = $conn instanceof mysqli ? $conn : db_connect();
    $sql = "CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        scope VARCHAR(40) NOT NULL,
        identifier_hash CHAR(64) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        success TINYINT(1) NOT NULL DEFAULT 0,
        attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_login_attempts_scope_hash_time (scope, identifier_hash, attempted_at),
        KEY idx_login_attempts_time (attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($sql)) {
        error_log('Where2Go login attempt table setup failed: ' . $conn->error);
        return false;
    }

    $ready = true;

    return true;

}


function get_login_rate_limit_status($scope, $identifier, $max_attempts = 5, $window_minutes = 15) {

    $scope = substr(trim((string) $scope), 0, 40);
    $identifierHash = get_login_attempt_hash($scope, $identifier);
    $max_attempts = max(1, (int) $max_attempts);
    $window_minutes = max(1, (int) $window_minutes);
    $conn = db_connect();

    if (!ensure_login_attempts_table($conn)) {
        return ['limited' => false, 'failed_count' => 0, 'retry_after_seconds' => 0];
    }

    $sql = "SELECT COUNT(*) AS failed_count,
                   TIMESTAMPDIFF(SECOND, MAX(attempted_at), NOW()) AS seconds_since_last
            FROM login_attempts
            WHERE scope = ?
              AND identifier_hash = ?
              AND success = 0
              AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return ['limited' => false, 'failed_count' => 0, 'retry_after_seconds' => 0];
    }

    $stmt->bind_param("ssi", $scope, $identifierHash, $window_minutes);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $failedCount = (int) ($row['failed_count'] ?? 0);
    $secondsSinceLast = max(0, (int) ($row['seconds_since_last'] ?? 0));
    $limited = $failedCount >= $max_attempts;
    $retryAfter = $limited ? max(60, ($window_minutes * 60) - $secondsSinceLast) : 0;

    return [
        'limited' => $limited,
        'failed_count' => $failedCount,
        'retry_after_seconds' => $retryAfter,
    ];

}


function record_login_attempt($scope, $identifier, $success) {

    $scope = substr(trim((string) $scope), 0, 40);
    $identifierHash = get_login_attempt_hash($scope, $identifier);
    $ipAddress = get_client_ip_address();
    $successValue = !empty($success) ? 1 : 0;
    $conn = db_connect();

    if (!ensure_login_attempts_table($conn)) {
        return false;
    }

    $stmt = $conn->prepare("INSERT INTO login_attempts (scope, identifier_hash, ip_address, success) VALUES (?, ?, ?, ?)");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("sssi", $scope, $identifierHash, $ipAddress, $successValue);
    $ok = $stmt->execute();

    if (!$successValue) {
        $status = get_login_rate_limit_status($scope, $identifier);

        if ((int) ($status['failed_count'] ?? 0) >= 3) {
            error_log('Where2Go suspicious failed login attempts: scope=' . $scope . ' ip=' . $ipAddress . ' failed_count=' . (int) $status['failed_count']);
        }
    }

    return $ok;

}


function clear_login_attempts($scope, $identifier) {

    $scope = substr(trim((string) $scope), 0, 40);
    $identifierHash = get_login_attempt_hash($scope, $identifier);
    $conn = db_connect();

    if (!ensure_login_attempts_table($conn)) {
        return false;
    }

    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE scope = ? AND identifier_hash = ? AND success = 0");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("ss", $scope, $identifierHash);

    return $stmt->execute();

}


/* -------------------------
   LOGIN USER
------------------------- */
function login_user($customer) {

    start_session();
    session_regenerate_id(true);

    $_SESSION['customer_id'] = $customer['Customer_ID'];
    $_SESSION['customer_name'] = $customer['First_N'];
    $_SESSION['customer_email'] = $customer['Email'];

}


/* -------------------------
   CHECK LOGIN
------------------------- */
function is_logged_in() {

    start_session();

    return isset($_SESSION['customer_id']);

}


/* -------------------------
   REQUIRE LOGIN
------------------------- */
function require_login() {

    if(!is_logged_in()) {

        header("Location: login.php");
        exit();

    }

}


/* -------------------------
   LOGOUT USER
------------------------- */
function logout_user() {

    start_session();

    session_unset();
    session_destroy();

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        $cookieOptions = [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => true,
            'samesite' => $params['samesite'] ?? 'Lax',
        ];

        if (!empty($params['domain'])) {
            $cookieOptions['domain'] = $params['domain'];
        }

        setcookie(session_name(), '', $cookieOptions);
    }

}


/* -------------------------
   LOGIN PARTNER
------------------------- */
function login_partner_user($partner) {

    start_session();
    session_regenerate_id(true);

    $_SESSION['partner_id'] = (int) ($partner['partner_id'] ?? 0);
    $_SESSION['partner_name'] = (string) ($partner['owner_name'] ?? '');
    $_SESSION['partner_email'] = (string) ($partner['email'] ?? '');

}


/* -------------------------
   PARTNER LOGIN STATUS
------------------------- */
function is_partner_logged_in() {

    start_session();

    return isset($_SESSION['partner_id']);

}


/* -------------------------
   REQUIRE PARTNER LOGIN
------------------------- */
function require_partner_login() {

    if (!is_partner_logged_in()) {
        header("Location: partner-login.php");
        exit();
    }

}


/* -------------------------
   LOGOUT PARTNER
------------------------- */
function logout_partner_user() {

    start_session();

    unset($_SESSION['partner_id'], $_SESSION['partner_name'], $_SESSION['partner_email']);

}


/* -------------------------
   ADMIN EMAILS
------------------------- */
function get_admin_user_emails() {

    static $emails = null;

    if ($emails !== null) {
        return $emails;
    }

    $emails = [];
    $envValue = getenv('WHERE2GO_ADMIN_EMAILS');

    if (is_string($envValue) && trim($envValue) !== '') {
        $emails = array_merge($emails, array_map('trim', explode(',', $envValue)));
    }

    $localConfigPath = __DIR__ . '/../config/admin.local.php';

    if (is_file($localConfigPath)) {
        $config = require $localConfigPath;

        if (is_array($config) && !empty($config['admin_emails']) && is_array($config['admin_emails'])) {
            $emails = array_merge($emails, $config['admin_emails']);
        }
    }

    $emails = array_values(array_filter(array_map(function ($email) {
        return strtolower(trim((string) $email));
    }, $emails)));

    return $emails ? array_values(array_unique($emails)) : [];

}


/* -------------------------
   ADMIN STATUS
------------------------- */
function is_admin_user() {

    if (!is_logged_in()) {
        return false;
    }

    $currentEmail = strtolower(trim((string) ($_SESSION['customer_email'] ?? '')));

    return $currentEmail !== '' && in_array($currentEmail, get_admin_user_emails(), true);

}


/* -------------------------
   REQUIRE ADMIN
------------------------- */
function require_admin_user() {

    if (!is_logged_in()) {
        $loginPath = rtrim((string) get_where2go_base_url(), '/');
        $loginPath = $loginPath !== '' ? $loginPath . '/login.php' : '../login.php';
        header("Location: " . $loginPath);
        exit();
    }

    if (!is_admin_user()) {
        http_response_code(403);
        exit('Admin access is required for this page.');
    }

}


/* -------------------------
   PROFILE PHOTO
------------------------- */
function get_profile_photo_web_path($customer_id) {

    $customer_id = (int) $customer_id;

    if ($customer_id <= 0) {
        return null;
    }

    $matches = glob(__DIR__ . '/../assets/images/uploads/profile-' . $customer_id . '.*');

    if (!$matches) {
        return null;
    }

    return 'assets/images/uploads/' . basename($matches[0]);

}


/* -------------------------
   VISITED PLACES
------------------------- */
function get_visited_place_ids($limit = null) {

    start_session();

    $limit = $limit !== null ? (int) $limit : null;
    $visited = [];

    if (is_logged_in()) {
        $customer_id = (int) ($_SESSION['customer_id'] ?? 0);

        if ($customer_id > 0) {
            foreach (get_customer_saved_place_targets($customer_id) as $savedTarget) {
                $visited[] = normalize_saved_target_identifier(
                    (int) ($savedTarget['business_id'] ?? 0),
                    (int) ($savedTarget['location_id'] ?? 0)
                );
            }
        }
    }

    $visited = array_values(array_unique(array_merge($visited, get_legacy_saved_place_ids())));

    if ($limit !== null && $limit > 0) {
        return array_slice($visited, 0, $limit);
    }

    return $visited;

}


/* -------------------------
   VISITED PLACE ENTRIES
------------------------- */
function get_visited_places($limit = 12) {

    start_session();

    $places = [];
    $limit = $limit !== null ? (int) $limit : null;
    $customer_id = (int) ($_SESSION['customer_id'] ?? 0);

    if ($customer_id > 0) {
        foreach (get_customer_saved_places_from_database($customer_id, $limit) as $place) {
            $places[] = $place;
        }
    }

    foreach (get_legacy_saved_places($limit) as $place) {
        $places[] = $place;
    }

    $places = array_values(array_slice($places, 0, $limit !== null && $limit > 0 ? $limit : count($places)));

    return $places;

}


/* -------------------------
   RECORD PLACE VISIT
------------------------- */
function record_place_visit($place_id, $source = 'catalog', $payload = []) {

    start_session();

    $place_id = trim($place_id);
    $source = trim($source) !== '' ? trim($source) : 'catalog';
    $payload = is_array($payload) ? $payload : [];

    $target = resolve_saved_place_target($place_id, $payload);
    $customer_id = (int) ($_SESSION['customer_id'] ?? 0);

    if (($target['business_id'] > 0 || $target['location_id'] > 0) && $customer_id > 0) {
        return save_customer_place_record($customer_id, $target['business_id'], $target['location_id']);
    }

    if ($place_id === '') {
        return false;
    }

    $normalizedPayload = normalize_saved_place_payload($place_id, $source, is_array($payload) ? $payload : []);
    $visited = get_legacy_saved_place_ids();
    $visited = array_values(array_unique(array_merge([$place_id], $visited)));
    set_legacy_saved_place_ids(array_slice($visited, 0, 24));
    set_legacy_saved_place_payload($place_id, $normalizedPayload);

    return true;

}


/* -------------------------
   REMOVE PLACE VISIT
------------------------- */
function remove_place_visit($place_id, $payload = []) {

    start_session();

    $place_id = trim($place_id);
    $payload = is_array($payload) ? $payload : [];

    $target = resolve_saved_place_target($place_id, $payload);
    $customer_id = (int) ($_SESSION['customer_id'] ?? 0);

    if (($target['business_id'] > 0 || $target['location_id'] > 0) && $customer_id > 0) {
        return remove_customer_saved_place_record($customer_id, $target['business_id'], $target['location_id']);
    }

    if ($place_id === '') {
        return false;
    }

    $visited = get_legacy_saved_place_ids();
    $visited = array_values(array_filter($visited, function ($visited_place_id) use ($place_id) {
        return $visited_place_id !== $place_id;
    }));

    set_legacy_saved_place_ids($visited);
    remove_legacy_saved_place_payload($place_id);

    return true;

}


/* -------------------------
   ENSURE VISIT TABLE
------------------------- */
function ensure_customer_place_visits_table() {

    static $ensured = false;

    if ($ensured) {
        return;
    }

    $conn = db_connect();

    $sql = "CREATE TABLE IF NOT EXISTS customer_place_visits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                business_id INT NULL,
                viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_visits_business (business_id),
                CONSTRAINT fk_customer_place_visits_business
                    FOREIGN KEY (business_id) REFERENCES businesses (business_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql);
    $ensured = true;

}


/* -------------------------
   ENSURE VISIT COLUMN
------------------------- */
function ensure_customer_place_visits_column($conn, $column_name, $alter_sql) {

    $column_name = trim($column_name);

    if ($column_name === '') {
        return;
    }

    $escaped = $conn->real_escape_string($column_name);
    $result = $conn->query("SHOW COLUMNS FROM customer_place_visits LIKE '{$escaped}'");

    if ($result && $result->num_rows === 0) {
        $conn->query($alter_sql);
    }

}


/* -------------------------
   ENSURE TABLE COLUMN
------------------------- */
function ensure_table_column($conn, $table_name, $column_name, $alter_sql) {

    $table_name = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table_name);
    $column_name = trim((string) $column_name);

    if (!$conn || $table_name === '' || $column_name === '') {
        return;
    }

    $escaped = $conn->real_escape_string($column_name);
    $result = $conn->query("SHOW COLUMNS FROM `{$table_name}` LIKE '{$escaped}'");

    if ($result && $result->num_rows === 0) {
        $conn->query($alter_sql);
    }

}


/* -------------------------
   ENSURE TABLE INDEX
------------------------- */
function ensure_table_index($conn, $table_name, $index_name, $alter_sql) {

    $table_name = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table_name);
    $index_name = trim((string) $index_name);

    if (!$conn || $table_name === '' || $index_name === '') {
        return;
    }

    $escaped = $conn->real_escape_string($index_name);
    $result = $conn->query("SHOW INDEX FROM `{$table_name}` WHERE Key_name = '{$escaped}'");

    if ($result && $result->num_rows === 0) {
        $conn->query($alter_sql);
    }

}


/* -------------------------
   BUSINESS SEARCH TAGS
------------------------- */
function ensure_business_search_tags_schema($conn = null) {

    $conn = $conn ?: db_connect();

    ensure_table_column(
        $conn,
        'businesses',
        'search_tags',
        'ALTER TABLE businesses ADD COLUMN search_tags TEXT NULL AFTER custom_type'
    );

}

/* -------------------------
   PARTNER RESERVATION SETTINGS
------------------------- */
function ensure_partner_reservation_settings_schema($conn = null) {

    $conn = $conn ?: db_connect();

    ensure_table_column($conn, 'business_locations', 'min_party_size', "ALTER TABLE business_locations ADD COLUMN min_party_size INT NOT NULL DEFAULT 1 AFTER has_reservations");
    ensure_table_column($conn, 'business_locations', 'max_party_size', "ALTER TABLE business_locations ADD COLUMN max_party_size INT NOT NULL DEFAULT 40 AFTER min_party_size");
    ensure_table_column($conn, 'business_locations', 'reservation_duration_minutes', "ALTER TABLE business_locations ADD COLUMN reservation_duration_minutes INT NOT NULL DEFAULT 60 AFTER max_party_size");
    ensure_table_column($conn, 'business_locations', 'reservation_buffer_minutes', "ALTER TABLE business_locations ADD COLUMN reservation_buffer_minutes INT NOT NULL DEFAULT 0 AFTER reservation_duration_minutes");
    ensure_table_column($conn, 'business_locations', 'auto_approve_reservations', "ALTER TABLE business_locations ADD COLUMN auto_approve_reservations TINYINT(1) NOT NULL DEFAULT 0 AFTER reservation_buffer_minutes");
    ensure_table_column($conn, 'business_locations', 'same_day_cutoff_time', "ALTER TABLE business_locations ADD COLUMN same_day_cutoff_time TIME NULL AFTER auto_approve_reservations");
    ensure_table_column($conn, 'business_locations', 'blocked_dates', "ALTER TABLE business_locations ADD COLUMN blocked_dates TEXT NULL AFTER same_day_cutoff_time");

}

function normalize_partner_cutoff_time($value) {

    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value) !== 1) {
        return '';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('H:i:s', $timestamp) : '';

}

function normalize_partner_blocked_dates($value) {

    if (is_array($value)) {
        $value = implode("\n", $value);
    }

    $parts = preg_split('/[,;\s]+/', (string) $value) ?: [];
    $dates = [];
    $seen = [];

    foreach ($parts as $part) {
        $part = trim((string) $part);

        if ($part === '') {
            continue;
        }

        $date = DateTime::createFromFormat('Y-m-d', $part);

        if (!$date || $date->format('Y-m-d') !== $part || isset($seen[$part])) {
            continue;
        }

        $seen[$part] = true;
        $dates[] = $part;
    }

    sort($dates);

    return implode("\n", array_slice($dates, 0, 180));

}

function get_location_blocked_date_list($value) {

    $normalized = normalize_partner_blocked_dates($value);

    return $normalized !== '' ? explode("\n", $normalized) : [];

}

function get_location_capacity_guest_limit($location) {

    $location = is_array($location) ? $location : [];
    $tablesPerHour = max(1, (int) ($location['capacity_per_hour'] ?? 1));

    return max(4, $tablesPerHour * 4);

}

function get_location_guest_limit($location) {

    $location = is_array($location) ? $location : [];
    $capacityLimit = get_location_capacity_guest_limit($location);
    $configuredMax = (int) ($location['max_party_size'] ?? 0);

    if ($configuredMax <= 0) {
        return $capacityLimit;
    }

    return max(1, min($configuredMax, $capacityLimit));

}

function get_location_min_party_size($location) {

    $location = is_array($location) ? $location : [];
    $configuredMin = max(1, (int) ($location['min_party_size'] ?? 1));

    return min($configuredMin, get_location_guest_limit($location));

}

function get_location_booking_slot_minutes($location, $fallback_minutes = 60) {

    $location = is_array($location) ? $location : [];
    $duration = (int) ($location['reservation_duration_minutes'] ?? 0);
    $buffer = max(0, (int) ($location['reservation_buffer_minutes'] ?? 0));

    if ($duration <= 0) {
        $duration = (int) $fallback_minutes;
    }

    return max(15, min(480, $duration + $buffer));

}

function is_location_reservation_request_allowed($location, $date, $guests = 1) {

    $location = is_array($location) ? $location : [];
    $guests = max(1, (int) $guests);
    $dateTimestamp = strtotime((string) $date);

    if (!$dateTimestamp) {
        return false;
    }

    $bookingDate = date('Y-m-d', $dateTimestamp);

    if ($guests < get_location_min_party_size($location) || $guests > get_location_guest_limit($location)) {
        return false;
    }

    if (in_array($bookingDate, get_location_blocked_date_list($location['blocked_dates'] ?? ''), true)) {
        return false;
    }

    $cutoffTime = normalize_partner_cutoff_time($location['same_day_cutoff_time'] ?? '');

    if ($cutoffTime !== '' && $bookingDate === date('Y-m-d')) {
        $cutoffTimestamp = strtotime($bookingDate . ' ' . $cutoffTime);

        if ($cutoffTimestamp && time() > $cutoffTimestamp) {
            return false;
        }
    }

    return true;

}

function normalize_business_search_tags($value) {

    if (is_array($value)) {
        $value = implode(',', $value);
    }

    $parts = preg_split('/[,;#\r\n]+/', (string) $value) ?: [];
    $tags = [];
    $seen = [];

    foreach ($parts as $part) {
        $tag = trim(preg_replace('/\s+/', ' ', (string) $part));
        $tag = trim($tag, " \t\n\r\0\x0B-");

        if ($tag === '') {
            continue;
        }

        $tag = substr($tag, 0, 40);
        $key = strtolower($tag);

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $tags[] = $tag;

        if (count($tags) >= 20) {
            break;
        }
    }

    return implode(', ', $tags);

}

function get_business_search_tag_list($value) {

    $normalized = normalize_business_search_tags($value);

    if ($normalized === '') {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', $normalized)), 'strlen'));

}


/* -------------------------
   BUSINESS THEME SETTINGS
------------------------- */
function ensure_business_theme_schema($conn = null) {

    $conn = $conn ?: db_connect();

    ensure_table_column($conn, 'businesses', 'theme_preset', "ALTER TABLE businesses ADD COLUMN theme_preset VARCHAR(40) NOT NULL DEFAULT 'where2go' AFTER website");
    ensure_table_column($conn, 'businesses', 'theme_accent_color', "ALTER TABLE businesses ADD COLUMN theme_accent_color VARCHAR(7) NULL AFTER theme_preset");
    ensure_table_column($conn, 'businesses', 'theme_cover_url', "ALTER TABLE businesses ADD COLUMN theme_cover_url VARCHAR(500) NULL AFTER theme_accent_color");
    ensure_table_column($conn, 'businesses', 'brand_tagline', "ALTER TABLE businesses ADD COLUMN brand_tagline VARCHAR(140) NULL AFTER theme_cover_url");

}

function get_business_theme_presets() {

    return [
        'where2go' => 'Where2Go default',
        'minimal' => 'Minimal',
        'luxury' => 'Luxury',
        'nightlife' => 'Nightlife',
        'family' => 'Family',
        'cafe' => 'Cafe',
    ];

}

function normalize_business_theme_preset($value) {

    $value = strtolower(trim((string) $value));

    return array_key_exists($value, get_business_theme_presets()) ? $value : 'where2go';

}

function normalize_business_theme_accent_color($value) {

    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if (preg_match('/^#?[0-9a-fA-F]{6}$/', $value) !== 1) {
        return '';
    }

    return '#' . strtoupper(ltrim($value, '#'));

}

function get_business_theme_payload($business) {

    $business = is_array($business) ? $business : [];
    $preset = normalize_business_theme_preset($business['theme_preset'] ?? 'where2go');
    $accentColor = normalize_business_theme_accent_color($business['theme_accent_color'] ?? '');

    return [
        'preset' => $preset,
        'label' => get_business_theme_presets()[$preset] ?? 'Where2Go default',
        'accentColor' => $accentColor !== '' ? $accentColor : '#F26C1C',
        'coverImageUrl' => trim((string) ($business['theme_cover_url'] ?? '')),
        'tagline' => trim((string) ($business['brand_tagline'] ?? '')),
    ];

}


/* -------------------------
   DAILY TOP PICKS
------------------------- */
function normalize_top_pick_date($date = '') {

    $date = trim((string) $date);

    if ($date !== '') {
        $parsed = DateTime::createFromFormat('!Y-m-d', $date);
        $errors = DateTime::getLastErrors();

        if ($parsed && (!$errors || ((int) $errors['warning_count'] === 0 && (int) $errors['error_count'] === 0))) {
            return $parsed->format('Y-m-d');
        }
    }

    return date('Y-m-d');

}

function ensure_daily_top_picks_schema($conn = null) {

    $conn = $conn ?: db_connect();
    ensure_business_search_tags_schema($conn);
    ensure_business_photo_order_schema($conn);

    $conn->query("CREATE TABLE IF NOT EXISTS daily_top_picks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pick_date DATE NOT NULL,
        business_id INT NOT NULL,
        position TINYINT UNSIGNED NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_daily_top_pick (pick_date, business_id),
        KEY idx_daily_top_picks_date_position (pick_date, position),
        KEY idx_daily_top_picks_business (business_id),
        CONSTRAINT fk_daily_top_picks_business
            FOREIGN KEY (business_id) REFERENCES businesses(business_id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    ensure_table_column(
        $conn,
        'daily_top_picks',
        'position',
        'ALTER TABLE daily_top_picks ADD COLUMN position TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER business_id'
    );
    ensure_table_index(
        $conn,
        'daily_top_picks',
        'idx_daily_top_picks_date_position',
        'ALTER TABLE daily_top_picks ADD INDEX idx_daily_top_picks_date_position (pick_date, position)'
    );
    ensure_table_index(
        $conn,
        'daily_top_picks',
        'idx_daily_top_picks_business',
        'ALTER TABLE daily_top_picks ADD INDEX idx_daily_top_picks_business (business_id)'
    );

}

function top_pick_business_select_columns() {

    return "b.business_id,
        b.name,
        b.description,
        b.type,
        b.custom_type,
        b.search_tags,
        b.logo_url,
        b.website,
        b.theme_preset,
        b.theme_accent_color,
        b.theme_cover_url,
        b.brand_tagline,
        COALESCE(p.image_url, '') AS photo_url,
        COALESCE(l.location_id, 0) AS location_id,
        COALESCE(l.location_id, 0) AS primary_location_id,
        COALESCE(l.location_name, '') AS location_name,
        COALESCE(l.address, '') AS address,
        COALESCE(l.address, '') AS primary_address,
        COALESCE(l.phone, '') AS phone,
        COALESCE(l.promo_code, '') AS promo_code,
        COALESCE(l.promo_details, '') AS promo_details,
        COALESCE(l.capacity_per_hour, 0) AS capacity_per_hour,
        COALESCE(l.has_reservations, 0) AS has_reservations,
        COALESCE(l.checkin_enabled, 0) AS checkin_enabled,
        (SELECT AVG(br.rating)
         FROM business_reviews br
         WHERE br.business_id = b.business_id) AS average_rating,
        (SELECT COUNT(*)
         FROM business_reviews br
         WHERE br.business_id = b.business_id) AS review_count,
        (SELECT bo.title
         FROM business_offers bo
         WHERE bo.business_id = b.business_id
           AND bo.is_active = 1
           AND (bo.start_date IS NULL OR bo.start_date <= CURDATE())
           AND (bo.end_date IS NULL OR bo.end_date >= CURDATE())
         ORDER BY bo.start_date DESC, bo.id DESC
         LIMIT 1) AS active_offer_title";

}

function top_pick_business_joins_sql() {

    return "LEFT JOIN (
            SELECT business_id, MIN(location_id) AS location_id
            FROM business_locations
            GROUP BY business_id
        ) first_location ON first_location.business_id = b.business_id
        LEFT JOIN business_locations l ON l.location_id = first_location.location_id
        LEFT JOIN business_photos p ON p.id = (
            SELECT bp.id
            FROM business_photos bp
            WHERE bp.business_id = b.business_id
            ORDER BY bp.display_order ASC, bp.id ASC
            LIMIT 1
        )";

}

function hydrate_top_pick_business_rows($result, $source = '') {

    $rows = [];

    if (!$result) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $row['icon'] = map_business_type_icon($row['type'] ?? 'other');
        $row['type_label'] = format_business_type_label($row['type'] ?? 'other', $row['custom_type'] ?? '');

        if ($source !== '') {
            $row['top_pick_source'] = $source;
        }

        $rows[] = $row;
    }

    return $rows;

}

function get_daily_top_pick_rows($date = '', $limit = 6) {

    $date = normalize_top_pick_date($date);
    $limit = max(1, min(6, (int) $limit));
    $conn = db_connect();
    ensure_daily_top_picks_schema($conn);
    ensure_business_theme_schema($conn);
    $sql = "SELECT dtp.id AS top_pick_id,
                   dtp.pick_date,
                   dtp.position AS top_pick_position,
                   " . top_pick_business_select_columns() . "
            FROM daily_top_picks dtp
            INNER JOIN businesses b ON b.business_id = dtp.business_id
            " . top_pick_business_joins_sql() . "
            WHERE dtp.pick_date = ?
              AND b.approval_status = 'approved'
            ORDER BY dtp.position ASC, dtp.id ASC
            LIMIT ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("si", $date, $limit);
    $stmt->execute();

    return hydrate_top_pick_business_rows($stmt->get_result(), 'manual');

}

function get_automatic_top_pick_rows($limit = 6, $exclude_business_ids = []) {

    $limit = max(0, min(6, (int) $limit));

    if ($limit <= 0) {
        return [];
    }

    $conn = db_connect();
    ensure_daily_top_picks_schema($conn);
    ensure_business_theme_schema($conn);
    $exclude_business_ids = array_values(array_unique(array_filter(array_map('intval', (array) $exclude_business_ids), function ($id) {
        return $id > 0;
    })));
    $rows = [];

    $fetchAutomaticRows = function ($whereSql, $remaining) use ($conn, &$exclude_business_ids) {
        $excludeSql = '';

        if ($exclude_business_ids) {
            $excludeSql = ' AND b.business_id NOT IN (' . implode(',', $exclude_business_ids) . ')';
        }

        $sql = "SELECT 0 AS top_pick_id,
                       CURDATE() AS pick_date,
                       0 AS top_pick_position,
                       " . top_pick_business_select_columns() . "
                FROM businesses b
                " . top_pick_business_joins_sql() . "
                WHERE b.approval_status = 'approved'
                  {$excludeSql}
                  {$whereSql}
                ORDER BY b.created_at DESC, b.business_id DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("i", $remaining);
        $stmt->execute();

        return hydrate_top_pick_business_rows($stmt->get_result(), 'automatic');
    };

    $nightlifeSql = "AND (
        LOWER(COALESCE(b.type, '')) = 'nightlife'
        OR LOWER(COALESCE(b.custom_type, '')) LIKE '%night%'
        OR LOWER(COALESCE(b.search_tags, '')) LIKE '%night%'
    )";
    $rows = $fetchAutomaticRows($nightlifeSql, $limit);

    foreach ($rows as $row) {
        $exclude_business_ids[] = (int) ($row['business_id'] ?? 0);
    }

    $remaining = $limit - count($rows);

    if ($remaining > 0) {
        $rows = array_merge($rows, $fetchAutomaticRows('', $remaining));
    }

    foreach ($rows as $index => $row) {
        $rows[$index]['top_pick_position'] = $index + 1;
    }

    return array_slice($rows, 0, $limit);

}

function get_top_pick_business_rows_for_app($date = '', $limit = 6) {

    $limit = max(1, min(6, (int) $limit));
    $manualRows = get_daily_top_pick_rows($date, $limit);

    if (count($manualRows) >= $limit) {
        return array_slice($manualRows, 0, $limit);
    }

    $excludeIds = array_map(function ($row) {
        return (int) ($row['business_id'] ?? 0);
    }, $manualRows);
    $automaticRows = get_automatic_top_pick_rows($limit - count($manualRows), $excludeIds);
    $rows = array_merge($manualRows, $automaticRows);

    foreach ($rows as $index => $row) {
        $rows[$index]['top_pick_position'] = $index + 1;
    }

    return array_slice($rows, 0, $limit);

}

function search_daily_top_pick_candidates($query = '', $date = '', $limit = 20) {

    $query = trim((string) $query);
    $date = normalize_top_pick_date($date);
    $limit = max(1, min(40, (int) $limit));
    $conn = db_connect();
    ensure_daily_top_picks_schema($conn);
    ensure_business_theme_schema($conn);
    $whereSql = '';
    $columns = top_pick_business_select_columns();
    $joins = top_pick_business_joins_sql();

    if ($query !== '') {
        $whereSql = "AND (
            b.name LIKE ?
            OR COALESCE(b.description, '') LIKE ?
            OR COALESCE(b.search_tags, '') LIKE ?
            OR COALESCE(l.location_name, '') LIKE ?
            OR COALESCE(l.address, '') LIKE ?
            OR COALESCE(b.custom_type, b.type, '') LIKE ?
        )";
    }

    $sql = "SELECT dtp.id AS top_pick_id,
                   dtp.position AS top_pick_position,
                   {$columns}
            FROM businesses b
            {$joins}
            LEFT JOIN daily_top_picks dtp
                ON dtp.business_id = b.business_id
               AND dtp.pick_date = ?
            WHERE b.approval_status = 'approved'
              {$whereSql}
            ORDER BY CASE WHEN dtp.id IS NULL THEN 1 ELSE 0 END,
                     dtp.position ASC,
                     b.name ASC
            LIMIT ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    if ($query !== '') {
        $needle = '%' . $query . '%';
        $stmt->bind_param("sssssssi", $date, $needle, $needle, $needle, $needle, $needle, $needle, $limit);
    } else {
        $stmt->bind_param("si", $date, $limit);
    }

    $stmt->execute();

    return hydrate_top_pick_business_rows($stmt->get_result());

}

function add_daily_top_pick($business_id, $date = '') {

    $business_id = (int) $business_id;
    $date = normalize_top_pick_date($date);

    if ($business_id <= 0) {
        return ['ok' => false, 'message' => 'Choose an approved place first.'];
    }

    $conn = db_connect();
    ensure_daily_top_picks_schema($conn);
    $stmt = $conn->prepare("SELECT business_id FROM businesses WHERE business_id = ? AND approval_status = 'approved' LIMIT 1");

    if (!$stmt) {
        return ['ok' => false, 'message' => 'The place could not be checked right now.'];
    }

    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $business = $stmt->get_result()->fetch_assoc();

    if (!$business) {
        return ['ok' => false, 'message' => 'Only approved places can be used as top picks.'];
    }

    $stmt = $conn->prepare("SELECT business_id FROM daily_top_picks WHERE pick_date = ? ORDER BY position ASC, id ASC");

    if (!$stmt) {
        return ['ok' => false, 'message' => 'Top picks could not be checked right now.'];
    }

    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentCount = 0;

    while ($row = $result->fetch_assoc()) {
        $currentCount++;

        if ((int) ($row['business_id'] ?? 0) === $business_id) {
            return ['ok' => false, 'message' => 'That place is already in the top picks for this day.'];
        }
    }

    if ($currentCount >= 6) {
        return ['ok' => false, 'message' => 'Top picks are limited to 6 places per day. Remove one before adding another.'];
    }

    $position = $currentCount + 1;
    $stmt = $conn->prepare("INSERT INTO daily_top_picks (pick_date, business_id, position) VALUES (?, ?, ?)");

    if (!$stmt) {
        return ['ok' => false, 'message' => 'The top pick could not be saved right now.'];
    }

    $stmt->bind_param("sii", $date, $business_id, $position);

    if (!$stmt->execute()) {
        return ['ok' => false, 'message' => 'That place could not be added. It may already be selected.'];
    }

    clear_where2go_mobile_cache('places');

    return ['ok' => true, 'message' => 'Top pick added for ' . $date . '.'];

}

function reorder_daily_top_picks($conn, $date) {

    $date = normalize_top_pick_date($date);
    $stmt = $conn->prepare("SELECT id FROM daily_top_picks WHERE pick_date = ? ORDER BY position ASC, id ASC");

    if (!$stmt) {
        return;
    }

    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $position = 1;
    $updateStmt = $conn->prepare("UPDATE daily_top_picks SET position = ? WHERE id = ?");

    if (!$updateStmt) {
        return;
    }

    while ($row = $result->fetch_assoc()) {
        $id = (int) ($row['id'] ?? 0);

        if ($id <= 0) {
            continue;
        }

        $updateStmt->bind_param("ii", $position, $id);
        $updateStmt->execute();
        $position++;
    }

}

function remove_daily_top_pick($pick_id, $date = '') {

    $pick_id = (int) $pick_id;
    $date = normalize_top_pick_date($date);

    if ($pick_id <= 0) {
        return ['ok' => false, 'message' => 'Choose a top pick to remove.'];
    }

    $conn = db_connect();
    ensure_daily_top_picks_schema($conn);
    $stmt = $conn->prepare("DELETE FROM daily_top_picks WHERE id = ? AND pick_date = ?");

    if (!$stmt) {
        return ['ok' => false, 'message' => 'The top pick could not be removed right now.'];
    }

    $stmt->bind_param("is", $pick_id, $date);
    $stmt->execute();

    if ($stmt->affected_rows < 1) {
        return ['ok' => false, 'message' => 'That top pick was not found for the selected day.'];
    }

    reorder_daily_top_picks($conn, $date);
    clear_where2go_mobile_cache('places');

    return ['ok' => true, 'message' => 'Top pick removed.'];

}

function clear_daily_top_picks($date = '') {

    $date = normalize_top_pick_date($date);
    $conn = db_connect();
    ensure_daily_top_picks_schema($conn);
    $stmt = $conn->prepare("DELETE FROM daily_top_picks WHERE pick_date = ?");

    if (!$stmt) {
        return ['ok' => false, 'message' => 'Top picks could not be cleared right now.'];
    }

    $stmt->bind_param("s", $date);
    $stmt->execute();
    clear_where2go_mobile_cache('places');

    return ['ok' => true, 'message' => 'Top picks cleared for ' . $date . '. Automatic picks will fill the app until you add manual picks.'];

}

function clear_where2go_mobile_cache($namespace = '') {

    $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'where2go-mobile-cache';

    if (!is_dir($directory)) {
        return;
    }

    $namespace = preg_replace('/[^a-z0-9_-]/i', '-', (string) $namespace);
    $pattern = $namespace !== '' ? $namespace . '-*.json' : '*.json';

    foreach (glob($directory . DIRECTORY_SEPARATOR . $pattern) ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }

}


/* -------------------------
   REWARDS LAYER
------------------------- */
require_once __DIR__ . '/rewards.php';


/* -------------------------
   NORMALIZE SAVED PLACE
------------------------- */
function normalize_saved_place_payload($place_id, $source = 'catalog', $payload = []) {

    $payload = is_array($payload) ? $payload : [];
    $source = trim($source) !== '' ? trim($source) : 'catalog';

    if ($source === 'business' || $source === 'business_location') {
        $businessId = (int) ($payload['business_id'] ?? 0);
        $locationId = (int) ($payload['location_id'] ?? 0);

        return normalize_saved_business_payload([
            'business_id' => $businessId,
            'location_id' => $locationId,
            'business_name' => trim((string) ($payload['name'] ?? $payload['business_name'] ?? 'Where2Go business')),
            'business_description' => trim((string) ($payload['description'] ?? 'Saved from Where2Go.')),
            'type' => trim((string) ($payload['type'] ?? 'other')),
            'custom_type' => trim((string) ($payload['custom_type'] ?? '')),
            'website' => trim((string) ($payload['website_url'] ?? $payload['website'] ?? '')),
            'location_address' => trim((string) ($payload['address'] ?? '')),
            'location_phone' => trim((string) ($payload['phone'] ?? '')),
            'primary_photo_url' => trim((string) ($payload['photo_url'] ?? '')),
            'average_rating' => is_numeric($payload['rating'] ?? null) ? (float) $payload['rating'] : null,
            'review_count' => (int) ($payload['reviews'] ?? 0),
            'active_offer_title' => trim((string) ($payload['offer_title'] ?? '')),
        ]);
    }

    if ($source === 'google') {
        $name = trim((string) ($payload['name'] ?? $payload['display_name'] ?? 'Google place'));
        $address = trim((string) ($payload['address'] ?? $payload['formatted_address'] ?? 'Cairo, Egypt'));

        return [
            'id' => (string) $place_id,
            'source' => 'google',
            'place_id' => (string) $place_id,
            'name' => $name,
            'category' => trim((string) ($payload['category'] ?? $payload['primary_type'] ?? 'Discovered on Google Maps')),
            'area' => trim((string) ($payload['area'] ?? $address)),
            'city' => trim((string) ($payload['city'] ?? 'Cairo')),
            'address' => $address,
            'description' => trim((string) ($payload['description'] ?? ('Google Maps result for ' . $name))),
            'price_range' => trim((string) ($payload['price_range'] ?? '$$')),
            'rating' => trim((string) ($payload['rating'] ?? '')),
            'reviews' => (int) ($payload['reviews'] ?? $payload['user_ratings_total'] ?? 0),
            'icon' => trim((string) ($payload['icon'] ?? 'map-pinned')),
            'photo_url' => trim((string) ($payload['photo_url'] ?? '')),
            'photo_attribution' => trim((string) ($payload['photo_attribution'] ?? '')),
            'google_maps_url' => trim((string) ($payload['google_maps_url'] ?? '')),
            'website_url' => trim((string) ($payload['website_url'] ?? '')),
        ];
    }

    $catalogPlace = get_place_by_id($place_id);
    $base = is_array($catalogPlace) ? $catalogPlace : [];

    return array_merge($base, $payload, [
        'id' => (string) $place_id,
        'source' => 'catalog',
        'place_id' => (string) $place_id,
        'photo_url' => trim((string) ($payload['photo_url'] ?? '')),
        'photo_attribution' => trim((string) ($payload['photo_attribution'] ?? '')),
        'google_maps_url' => trim((string) ($payload['google_maps_url'] ?? '')),
        'website_url' => trim((string) ($payload['website_url'] ?? '')),
    ]);

}


/* -------------------------
   LEGACY SAVE SESSION IDS
------------------------- */
function get_legacy_saved_place_ids() {

    start_session();

    $visited = $_SESSION['legacy_saved_place_ids'] ?? ($_SESSION['visited_places'] ?? []);

    return is_array($visited) ? array_values($visited) : [];

}


/* -------------------------
   SET LEGACY SAVE IDS
------------------------- */
function set_legacy_saved_place_ids($visited) {

    start_session();

    $visited = is_array($visited) ? array_values($visited) : [];
    $_SESSION['legacy_saved_place_ids'] = $visited;
    $_SESSION['visited_places'] = $visited;

}


/* -------------------------
   LEGACY SAVE PAYLOADS
------------------------- */
function get_legacy_saved_place_payloads() {

    start_session();

    $payloads = $_SESSION['legacy_saved_place_payloads'] ?? ($_SESSION['visited_place_payloads'] ?? []);

    return is_array($payloads) ? $payloads : [];

}


/* -------------------------
   SET LEGACY SAVE PAYLOAD
------------------------- */
function set_legacy_saved_place_payload($place_id, $payload) {

    start_session();

    $payloads = get_legacy_saved_place_payloads();
    $payloads[$place_id] = is_array($payload) ? $payload : [];
    $_SESSION['legacy_saved_place_payloads'] = $payloads;
    $_SESSION['visited_place_payloads'] = $payloads;

}


/* -------------------------
   REMOVE LEGACY SAVE PAYLOAD
------------------------- */
function remove_legacy_saved_place_payload($place_id) {

    start_session();

    $payloads = get_legacy_saved_place_payloads();
    unset($payloads[$place_id]);
    $_SESSION['legacy_saved_place_payloads'] = $payloads;
    $_SESSION['visited_place_payloads'] = $payloads;

}


/* -------------------------
   LEGACY SAVED PLACES
------------------------- */
function get_legacy_saved_places($limit = 12) {

    $places = [];
    $payloads = get_legacy_saved_place_payloads();

    foreach (get_legacy_saved_place_ids() as $place_id) {
        $payload = $payloads[$place_id] ?? [];
        $source = trim((string) ($payload['source'] ?? 'catalog'));

        if ($source === 'google') {
            $places[] = normalize_saved_place_payload($place_id, 'google', $payload);
        } elseif (function_exists('get_place_by_id')) {
            $catalogPlace = get_place_by_id($place_id);

            if ($catalogPlace) {
                $places[] = normalize_saved_place_payload($place_id, 'catalog', array_merge($catalogPlace, $payload));
            }
        }

        if ($limit !== null && $limit > 0 && count($places) >= $limit) {
            break;
        }
    }

    return $places;

}


/* -------------------------
   RESOLVE SAVE TARGET
------------------------- */
function resolve_saved_place_target($identifier = '', $payload = []) {

    $identifier = trim((string) $identifier);
    $payload = is_array($payload) ? $payload : [];
    $businessId = (int) ($payload['business_id'] ?? 0);
    $locationId = (int) ($payload['location_id'] ?? 0);

    if ($identifier !== '' && preg_match('/^location:(\d+)$/', $identifier, $matches)) {
        $locationId = (int) $matches[1];
    } elseif ($identifier !== '' && preg_match('/^\d+$/', $identifier)) {
        $businessId = (int) $identifier;
    }

    if ($locationId > 0 && $businessId <= 0) {
        $businessId = get_business_id_by_location_id($locationId);
    }

    return [
        'business_id' => $businessId,
        'location_id' => $locationId,
    ];

}


/* -------------------------
   NORMALIZE SAVE TARGET ID
------------------------- */
function normalize_saved_target_identifier($business_id = 0, $location_id = 0) {

    $business_id = (int) $business_id;
    $location_id = (int) $location_id;

    if ($location_id > 0) {
        return 'location:' . $location_id;
    }

    return $business_id > 0 ? (string) $business_id : '';

}


/* -------------------------
   ENSURE CUSTOMER SAVES TABLE
------------------------- */
function ensure_customer_saved_places_table() {

    static $ensured = false;

    if ($ensured) {
        return;
    }

    $conn = db_connect();
    $sql = "CREATE TABLE IF NOT EXISTS customer_saved_places (
                id INT AUTO_INCREMENT PRIMARY KEY,
                customer_id INT NOT NULL,
                business_id INT NULL,
                location_id INT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_customer_saved_places_customer (customer_id),
                KEY idx_customer_saved_places_business (business_id),
                KEY idx_customer_saved_places_location (location_id),
                CONSTRAINT fk_customer_saved_places_customer
                    FOREIGN KEY (customer_id) REFERENCES customers (Customer_ID) ON DELETE CASCADE,
                CONSTRAINT fk_customer_saved_places_business
                    FOREIGN KEY (business_id) REFERENCES businesses (business_id) ON DELETE CASCADE,
                CONSTRAINT fk_customer_saved_places_location
                    FOREIGN KEY (location_id) REFERENCES business_locations (location_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql);
    $ensured = true;

}


/* -------------------------
   SAVE CUSTOMER PLACE
------------------------- */
function save_customer_place_record($customer_id, $business_id = 0, $location_id = 0) {

    $customer_id = (int) $customer_id;
    $business_id = (int) $business_id;
    $location_id = (int) $location_id;

    if ($customer_id <= 0 || ($business_id <= 0 && $location_id <= 0)) {
        return false;
    }

    ensure_customer_saved_places_table();

    if ($location_id > 0 && $business_id <= 0) {
        $business_id = get_business_id_by_location_id($location_id);
    }

    if ($business_id <= 0) {
        return false;
    }

    $conn = db_connect();

    if ($location_id > 0) {
        $checkSql = "SELECT id FROM customer_saved_places WHERE customer_id = ? AND location_id = ? LIMIT 1";
        $checkStmt = $conn->prepare($checkSql);

        if ($checkStmt) {
            $checkStmt->bind_param("ii", $customer_id, $location_id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();

            if ($result && $result->fetch_assoc()) {
                return true;
            }
        }
    } else {
        $checkSql = "SELECT id FROM customer_saved_places WHERE customer_id = ? AND business_id = ? AND location_id IS NULL LIMIT 1";
        $checkStmt = $conn->prepare($checkSql);

        if ($checkStmt) {
            $checkStmt->bind_param("ii", $customer_id, $business_id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();

            if ($result && $result->fetch_assoc()) {
                return true;
            }
        }
    }

    if ($location_id > 0) {
        $insertSql = "INSERT INTO customer_saved_places (customer_id, business_id, location_id, created_at) VALUES (?, ?, ?, NOW())";
        $insertStmt = $conn->prepare($insertSql);

        if (!$insertStmt) {
            return false;
        }

        $insertStmt->bind_param("iii", $customer_id, $business_id, $location_id);

        return $insertStmt->execute();
    }

    $insertSql = "INSERT INTO customer_saved_places (customer_id, business_id, location_id, created_at) VALUES (?, ?, NULL, NOW())";
    $insertStmt = $conn->prepare($insertSql);

    if (!$insertStmt) {
        return false;
    }

    $insertStmt->bind_param("ii", $customer_id, $business_id);

    return $insertStmt->execute();

}


/* -------------------------
   REMOVE CUSTOMER SAVE
------------------------- */
function remove_customer_saved_place_record($customer_id, $business_id = 0, $location_id = 0) {

    $customer_id = (int) $customer_id;
    $business_id = (int) $business_id;
    $location_id = (int) $location_id;

    if ($customer_id <= 0) {
        return false;
    }

    ensure_customer_saved_places_table();

    if ($location_id > 0) {
        $conn = db_connect();
        $sql = "DELETE FROM customer_saved_places WHERE customer_id = ? AND location_id = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ii", $customer_id, $location_id);

        return $stmt->execute();
    }

    if ($business_id <= 0) {
        return false;
    }

    $conn = db_connect();
    $sql = "DELETE FROM customer_saved_places WHERE customer_id = ? AND business_id = ? AND location_id IS NULL";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("ii", $customer_id, $business_id);

    return $stmt->execute();

}


/* -------------------------
   CUSTOMER SAVED TARGETS
------------------------- */
function get_customer_saved_place_targets($customer_id) {

    $customer_id = (int) $customer_id;

    if ($customer_id <= 0) {
        return [];
    }

    ensure_customer_saved_places_table();

    $conn = db_connect();
    $sql = "SELECT business_id, location_id
            FROM customer_saved_places
            WHERE customer_id = ?
            ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $targets = [];

    while ($row = $result->fetch_assoc()) {
        $targets[] = $row;
    }

    return $targets;

}


/* -------------------------
   DB SAVED PLACE ENTRIES
------------------------- */
function get_customer_saved_places_from_database($customer_id, $limit = 12) {

    $customer_id = (int) $customer_id;
    $limit = $limit !== null ? (int) $limit : null;

    if ($customer_id <= 0) {
        return [];
    }

    ensure_customer_saved_places_table();

    $conn = db_connect();
    ensure_business_photo_order_schema($conn);
    $sql = "SELECT sp.business_id,
                   sp.location_id,
                   sp.created_at AS saved_at,
                   b.name AS business_name,
                   b.description AS business_description,
                   b.rules AS business_rules,
                   b.type,
                   b.custom_type,
                   b.logo_url,
                   b.website,
                   l.address AS location_address,
                   l.phone AS location_phone,
                   COALESCE(
                       (SELECT bp.image_url
                        FROM business_photos bp
                        WHERE bp.business_id = b.business_id
                        ORDER BY bp.display_order ASC, bp.id ASC
                        LIMIT 1),
                       b.logo_url
                   ) AS primary_photo_url,
                   (SELECT AVG(br.rating)
                    FROM business_reviews br
                    WHERE br.business_id = b.business_id) AS average_rating,
                   (SELECT COUNT(*)
                    FROM business_reviews br
                    WHERE br.business_id = b.business_id) AS review_count,
                   (SELECT bo.title
                    FROM business_offers bo
                    WHERE bo.business_id = b.business_id
                      AND bo.is_active = 1
                      AND (bo.start_date IS NULL OR bo.start_date <= CURDATE())
                      AND (bo.end_date IS NULL OR bo.end_date >= CURDATE())
                    ORDER BY bo.start_date DESC, bo.id DESC
                    LIMIT 1) AS active_offer_title
            FROM customer_saved_places sp
            LEFT JOIN business_locations l ON l.location_id = sp.location_id
            INNER JOIN businesses b ON b.business_id = COALESCE(sp.business_id, l.business_id)
            WHERE sp.customer_id = ?
            ORDER BY sp.created_at DESC";

    if ($limit !== null && $limit > 0) {
        $sql .= " LIMIT " . $limit;
    }

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $places = [];

    while ($row = $result->fetch_assoc()) {
        $places[] = normalize_saved_business_payload($row);
    }

    return $places;

}


/* -------------------------
   NORMALIZE SAVED BUSINESS
------------------------- */
function normalize_saved_business_payload($row) {

    $row = is_array($row) ? $row : [];
    $businessId = (int) ($row['business_id'] ?? 0);
    $locationId = (int) ($row['location_id'] ?? 0);
    $identifier = normalize_saved_target_identifier($businessId, $locationId);
    $address = trim((string) ($row['location_address'] ?? ''));
    $rating = $row['average_rating'] !== null ? number_format((float) $row['average_rating'], 1) : 'N/A';
    $descriptionParts = [];

    if (trim((string) ($row['active_offer_title'] ?? '')) !== '') {
        $descriptionParts[] = 'Offer: ' . trim((string) $row['active_offer_title']) . '.';
    }

    if (trim((string) ($row['business_description'] ?? '')) !== '') {
        $descriptionParts[] = trim((string) $row['business_description']);
    }

    return [
        'id' => $identifier,
        'source' => 'business',
        'place_id' => $identifier,
        'business_id' => $businessId,
        'location_id' => $locationId > 0 ? $locationId : null,
        'name' => trim((string) ($row['business_name'] ?? 'Where2Go business')),
        'category' => format_business_type_label(
            trim((string) ($row['type'] ?? 'other')),
            trim((string) ($row['custom_type'] ?? ''))
        ),
        'area' => $address,
        'city' => '',
        'address' => $address,
        'description' => trim(implode(' ', $descriptionParts)) !== '' ? trim(implode(' ', $descriptionParts)) : 'Saved from Where2Go.',
        'price_range' => trim((string) ($row['active_offer_title'] ?? '')) !== '' ? 'Offer live' : 'See details',
        'rating' => $rating,
        'reviews' => (int) ($row['review_count'] ?? 0),
        'icon' => map_business_type_icon(trim((string) ($row['type'] ?? 'other'))),
        'photo_url' => trim((string) ($row['primary_photo_url'] ?? '')),
        'photo_attribution' => '',
        'google_maps_url' => '',
        'website_url' => trim((string) ($row['website'] ?? '')),
        'offer_title' => trim((string) ($row['active_offer_title'] ?? '')),
        'detail_url' => $businessId > 0 ? 'place.php?business_id=' . rawurlencode((string) $businessId) : '',
    ];

}


/* -------------------------
   BUSINESS TYPE LABEL
------------------------- */
function format_business_type_label($type, $custom_type = '') {

    $type = trim((string) $type);
    $custom_type = trim((string) $custom_type);

    if ($type === 'other' && $custom_type !== '') {
        return $custom_type;
    }

    $labels = [
        'restaurant' => 'Restaurant',
        'cafe' => 'Cafe',
        'activity' => 'Activity',
        'entertainment' => 'Entertainment',
        'nightlife' => 'Nightlife',
        'heritage' => 'Heritage & Culture',
        'other' => 'Other',
    ];

    return $labels[$type] ?? 'Business';

}


/* -------------------------
   BUSINESS TYPE ICON
------------------------- */
function map_business_type_icon($type) {

    $type = trim((string) $type);

    $icons = [
        'restaurant' => 'utensils-crossed',
        'cafe' => 'coffee',
        'activity' => 'mountain-snow',
        'entertainment' => 'star',
        'nightlife' => 'music-4',
        'heritage' => 'landmark',
        'other' => 'building-2',
    ];

    return $icons[$type] ?? 'building-2';

}


/* -------------------------
   BUSINESS TYPE SCHEMA
------------------------- */
function ensure_business_type_catalog_values($conn = null) {

    static $checked = false;

    if ($checked) {
        return;
    }

    $conn = $conn ?: db_connect();

    if (!$conn) {
        return;
    }

    $result = $conn->query("SHOW COLUMNS FROM businesses LIKE 'type'");
    $row = $result ? $result->fetch_assoc() : null;
    $columnType = strtolower((string) ($row['Type'] ?? ''));

    if ($columnType !== '' && strpos($columnType, "'heritage'") === false) {
        $conn->query("ALTER TABLE businesses MODIFY type ENUM('restaurant','cafe','activity','entertainment','nightlife','heritage','other') NOT NULL DEFAULT 'restaurant'");
    }

    $checked = true;

}


/* -------------------------
   BUSINESS ID BY LOCATION
------------------------- */
function get_business_id_by_location_id($location_id) {

    $location_id = (int) $location_id;

    if ($location_id <= 0) {
        return 0;
    }

    $conn = db_connect();
    $sql = "SELECT business_id FROM business_locations WHERE location_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param("i", $location_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    return (int) ($row['business_id'] ?? 0);

}


/* -------------------------
   BUSINESS PRIMARY PHOTO
------------------------- */
function get_business_primary_photo_url($business_id) {

    $business_id = (int) $business_id;

    if ($business_id <= 0) {
        return '';
    }

    $conn = db_connect();
    ensure_business_photo_order_schema($conn);
    $sql = "SELECT image_url FROM business_photos WHERE business_id = ? ORDER BY display_order ASC, id ASC LIMIT 1";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $business_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        if ($row && trim((string) ($row['image_url'] ?? '')) !== '') {
            return trim((string) $row['image_url']);
        }
    }

    $sql = "SELECT logo_url FROM businesses WHERE business_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return '';
    }

    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    return trim((string) ($row['logo_url'] ?? ''));

}


/* -------------------------
   BUSINESS LOCATIONS
------------------------- */
function get_business_locations($business_id) {

    $business_id = (int) $business_id;

    if ($business_id <= 0) {
        return [];
    }

    ensure_where2go_rewards_schema();

    $conn = db_connect();
    ensure_partner_reservation_settings_schema($conn);
    $sql = "SELECT location_id, business_id, location_name, address, phone, promo_code, promo_details, qr_token,
                   capacity_per_hour, has_reservations, min_party_size, max_party_size,
                   reservation_duration_minutes, reservation_buffer_minutes, auto_approve_reservations,
                   same_day_cutoff_time, blocked_dates, checkin_enabled
            FROM business_locations
            WHERE business_id = ?
            ORDER BY location_id ASC";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $locations = [];

    while ($row = $result->fetch_assoc()) {
        $row['checkin_enabled'] = (int) ($row['checkin_enabled'] ?? 1);
        $row['qr_token'] = ensure_location_qr_token((int) ($row['location_id'] ?? 0), (string) ($row['qr_token'] ?? ''), $conn);
        $row['checkin_url'] = build_location_checkin_url((string) ($row['qr_token'] ?? ''));
        $locations[] = $row;
    }

    return $locations;

}


/* -------------------------
   LOCATION HOURS MAP
------------------------- */
function get_location_hours_map($location_id) {

    $location_id = (int) $location_id;
    static $cache = [];

    if ($location_id <= 0) {
        return [];
    }

    if (isset($cache[$location_id])) {
        return $cache[$location_id];
    }

    $conn = db_connect();
    $sql = "SELECT day_of_week, is_closed, open_time, close_time
            FROM business_hours
            WHERE location_id = ?
            ORDER BY day_of_week ASC";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $location_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $hours = [];

    while ($row = $result->fetch_assoc()) {
        $hours[(int) $row['day_of_week']] = $row;
    }

    $cache[$location_id] = $hours;

    return $cache[$location_id];

}


/* -------------------------
   LOCATION HOURS BY DATE
------------------------- */
function get_location_hours_for_date($location_id, $date) {

    $location_id = (int) $location_id;
    $timestamp = strtotime((string) $date);

    if ($location_id <= 0 || !$timestamp) {
        return null;
    }

    $dayOfWeek = (int) date('w', $timestamp);
    $hoursMap = get_location_hours_map($location_id);
    $defaultRows = get_default_hours_rows();

    return $hoursMap[$dayOfWeek] ?? ($defaultRows[$dayOfWeek] ?? null);

}


/* -------------------------
   ACTIVE BUSINESS OFFERS
------------------------- */
function get_active_business_offers($business_id) {

    $business_id = (int) $business_id;

    if ($business_id <= 0) {
        return [];
    }

    $conn = db_connect();
    $sql = "SELECT id, business_id, title, description, discount, start_date, end_date, is_active
            FROM business_offers
            WHERE business_id = ?
              AND is_active = 1
              AND (start_date IS NULL OR start_date <= CURDATE())
              AND (end_date IS NULL OR end_date >= CURDATE())
            ORDER BY start_date DESC, id DESC";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $offers = [];

    while ($row = $result->fetch_assoc()) {
        $offers[] = $row;
    }

    return $offers;

}


/* -------------------------
   BUSINESS REVIEWS
------------------------- */
function get_business_reviews($business_id, $limit = 3) {

    $business_id = (int) $business_id;
    $limit = max(1, (int) $limit);

    if ($business_id <= 0) {
        return [];
    }

    $conn = db_connect();
    $sql = "SELECT br.review_id, br.business_id, br.location_id, br.customer_id, br.rating, br.comment, br.created_at,
                   c.First_N, c.Last_N
            FROM business_reviews br
            LEFT JOIN customers c ON c.Customer_ID = br.customer_id
            WHERE br.business_id = ?
            ORDER BY br.created_at DESC
            LIMIT " . $limit;
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $reviews = [];

    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }

    return $reviews;

}


/* -------------------------
   PUBLIC BUSINESS LIST
------------------------- */
function get_public_businesses($limit = null) {

    $limit = $limit !== null ? (int) $limit : null;
    $conn = db_connect();
    ensure_business_search_tags_schema($conn);
    ensure_business_theme_schema($conn);
    ensure_business_photo_order_schema($conn);
    $sql = "SELECT b.business_id,
                   b.partner_id,
                   b.name,
                   b.description,
                   b.rules,
                   b.type,
                   b.custom_type,
                   b.search_tags,
                   b.logo_url,
                   b.website,
                   b.approval_status,
                   b.created_at,
                   (SELECT bl.location_id
                    FROM business_locations bl
                    WHERE bl.business_id = b.business_id
                    ORDER BY bl.location_id ASC
                    LIMIT 1) AS primary_location_id,
                   (SELECT bl.address
                    FROM business_locations bl
                    WHERE bl.business_id = b.business_id
                    ORDER BY bl.location_id ASC
                    LIMIT 1) AS primary_address,
                   COALESCE(
                        (SELECT bp.image_url
                         FROM business_photos bp
                         WHERE bp.business_id = b.business_id
                         ORDER BY bp.display_order ASC, bp.id ASC
                         LIMIT 1),
                       b.logo_url
                   ) AS photo_url,
                   (SELECT AVG(br.rating)
                    FROM business_reviews br
                    WHERE br.business_id = b.business_id) AS average_rating,
                   (SELECT COUNT(*)
                    FROM business_reviews br
                    WHERE br.business_id = b.business_id) AS review_count,
                   (SELECT bo.title
                    FROM business_offers bo
                    WHERE bo.business_id = b.business_id
                      AND bo.is_active = 1
                      AND (bo.start_date IS NULL OR bo.start_date <= CURDATE())
                      AND (bo.end_date IS NULL OR bo.end_date >= CURDATE())
                    ORDER BY bo.start_date DESC, bo.id DESC
                    LIMIT 1) AS active_offer_title
            FROM businesses b
            WHERE b.approval_status = 'approved'
            ORDER BY b.created_at DESC";

    if ($limit !== null && $limit > 0) {
        $sql .= " LIMIT " . $limit;
    }

    $result = $conn->query($sql);

    if (!$result) {
        return [];
    }

    $businesses = [];

    while ($row = $result->fetch_assoc()) {
        $row['icon'] = map_business_type_icon($row['type'] ?? 'other');
        $row['type_label'] = format_business_type_label($row['type'] ?? 'other', $row['custom_type'] ?? '');
        $businesses[] = $row;
    }

    return $businesses;

}


/* -------------------------
   BUSINESS DETAILS
------------------------- */
function get_business_by_id($business_id) {

    $business_id = (int) $business_id;

    if ($business_id <= 0) {
        return null;
    }

    $conn = db_connect();
    ensure_business_search_tags_schema($conn);
    ensure_business_theme_schema($conn);
    ensure_business_photo_order_schema($conn);
    $sql = "SELECT b.business_id, b.partner_id, b.name, b.description, b.rules, b.type, b.custom_type, b.search_tags,
                   b.logo_url, b.website, b.theme_preset, b.theme_accent_color, b.theme_cover_url, b.brand_tagline,
                   b.approval_status, b.review_note, b.reviewed_at, b.created_at,
                   COALESCE(
                        (SELECT bp.image_url
                         FROM business_photos bp
                         WHERE bp.business_id = b.business_id
                         ORDER BY bp.display_order ASC, bp.id ASC
                         LIMIT 1),
                       b.logo_url
                   ) AS photo_url,
                   (SELECT AVG(br.rating)
                    FROM business_reviews br
                    WHERE br.business_id = b.business_id) AS average_rating,
                   (SELECT COUNT(*)
                    FROM business_reviews br
                    WHERE br.business_id = b.business_id) AS review_count
            FROM businesses b
            WHERE b.business_id = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $business = $result ? $result->fetch_assoc() : null;

    if (!$business) {
        return null;
    }

    $business['icon'] = map_business_type_icon($business['type'] ?? 'other');
    $business['type_label'] = format_business_type_label($business['type'] ?? 'other', $business['custom_type'] ?? '');
    $business['locations'] = get_business_locations($business_id);
    $business['photos'] = get_business_photos($business_id);
    $business['menus'] = get_business_menus($business_id);
    $business['offers'] = get_all_business_offers($business_id);
    $business['active_offers'] = get_active_business_offers($business_id);
    $business['reviews'] = get_business_reviews($business_id, 5);

    foreach ($business['locations'] as $index => $location) {
        $locationId = (int) ($location['location_id'] ?? 0);
        $business['locations'][$index]['hours'] = get_location_hours_rows($locationId);
    }

    $business['primary_location'] = $business['locations'][0] ?? null;

    return $business;

}


/* -------------------------
   BUSINESS ACCESS
------------------------- */
function can_current_user_access_business($business) {

    $business = is_array($business) ? $business : [];
    $status = trim((string) ($business['approval_status'] ?? ''));
    $partnerId = (int) ($business['partner_id'] ?? 0);

    if ($status === 'approved') {
        return true;
    }

    if (is_admin_user()) {
        return true;
    }

    return is_partner_logged_in() && (int) ($_SESSION['partner_id'] ?? 0) === $partnerId;

}


/* -------------------------
   RECORD BUSINESS VIEW
------------------------- */
function record_business_view($business_id) {

    $business_id = (int) $business_id;

    if ($business_id <= 0) {
        return false;
    }

    ensure_customer_place_visits_table();

    $conn = db_connect();
    $sql = "INSERT INTO customer_place_visits (business_id, viewed_at) VALUES (?, NOW())";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("i", $business_id);

    return $stmt->execute();

}


/* -------------------------
   BUSINESS VIEW COUNT
------------------------- */
function get_business_view_count($business_id) {

    $business_id = (int) $business_id;

    if ($business_id <= 0) {
        return 0;
    }

    ensure_customer_place_visits_table();

    $conn = db_connect();
    $sql = "SELECT COUNT(*) AS total_views FROM customer_place_visits WHERE business_id = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    return (int) ($row['total_views'] ?? 0);

}


/* -------------------------
   PARTNER DASHBOARD SUMMARY
------------------------- */
function get_partner_dashboard_summary($partner_id) {

    $partner_id = (int) $partner_id;

    if ($partner_id <= 0) {
        return [
            'business_count' => 0,
            'view_count' => 0,
            'reservation_count' => 0,
            'upcoming_reservation_count' => 0,
            'active_offer_count' => 0,
            'checkin_count' => 0,
            'points_issued' => 0,
        ];
    }

    ensure_where2go_rewards_schema();

    $conn = db_connect();
    $summary = [
        'business_count' => 0,
        'view_count' => 0,
        'reservation_count' => 0,
        'upcoming_reservation_count' => 0,
        'active_offer_count' => 0,
        'checkin_count' => 0,
        'points_issued' => 0,
    ];

    $queries = [
        'business_count' => "SELECT COUNT(*) AS value FROM businesses WHERE partner_id = ?",
        'view_count' => "SELECT COUNT(*) AS value
                         FROM customer_place_visits cpv
                         INNER JOIN businesses b ON b.business_id = cpv.business_id
                         WHERE b.partner_id = ?",
        'reservation_count' => "SELECT COUNT(*) AS value
                                FROM bookings bk
                                INNER JOIN business_locations bl ON bl.location_id = bk.location_id
                                INNER JOIN businesses b ON b.business_id = bl.business_id
                                WHERE b.partner_id = ?",
        'upcoming_reservation_count' => "SELECT COUNT(*) AS value
                                         FROM bookings bk
                                         INNER JOIN business_locations bl ON bl.location_id = bk.location_id
                                         INNER JOIN businesses b ON b.business_id = bl.business_id
                                         WHERE b.partner_id = ?
                                           AND (
                                               bk.status = 'pending'
                                               OR (bk.status = 'confirmed' AND bk.date >= CURDATE())
                                           )",
        'active_offer_count' => "SELECT COUNT(*) AS value
                                 FROM business_offers bo
                                 INNER JOIN businesses b ON b.business_id = bo.business_id
                                 WHERE b.partner_id = ?
                                   AND bo.is_active = 1
                                   AND (bo.start_date IS NULL OR bo.start_date <= CURDATE())
                                   AND (bo.end_date IS NULL OR bo.end_date >= CURDATE())",
        'checkin_count' => "SELECT COUNT(*) AS value
                            FROM customer_checkins cc
                            INNER JOIN businesses b ON b.business_id = cc.business_id
                            WHERE b.partner_id = ?",
        'points_issued' => "SELECT COALESCE(SUM(cc.points_awarded), 0) AS value
                            FROM customer_checkins cc
                            INNER JOIN businesses b ON b.business_id = cc.business_id
                            WHERE b.partner_id = ?",
    ];

    foreach ($queries as $key => $sql) {
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            continue;
        }

        $stmt->bind_param("i", $partner_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $summary[$key] = (int) ($row['value'] ?? 0);
    }

    return $summary;

}


/* -------------------------
   PARTNER UPCOMING BOOKINGS
------------------------- */
function get_partner_upcoming_reservations($partner_id, $limit = 8) {

    $partner_id = (int) $partner_id;
    $limit = max(1, (int) $limit);

    if ($partner_id <= 0) {
        return [];
    }

    $conn = db_connect();
    $sql = "SELECT bk.id,
                   bk.location_id,
                   bk.user_name,
                   bk.user_email,
                   bk.date,
                   bk.time_slot,
                   bk.guests,
                   bk.status,
                   b.business_id,
                   b.name AS business_name,
                   bl.address AS location_address
            FROM bookings bk
            INNER JOIN business_locations bl ON bl.location_id = bk.location_id
            INNER JOIN businesses b ON b.business_id = bl.business_id
            WHERE b.partner_id = ?
              AND (
                  bk.status = 'pending'
                  OR (bk.status = 'confirmed' AND bk.date >= CURDATE())
              )
            ORDER BY CASE WHEN bk.status = 'pending' THEN 0 ELSE 1 END, bk.date ASC, bk.time_slot ASC
            LIMIT " . $limit;
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $partner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $reservations = [];

    while ($row = $result->fetch_assoc()) {
        $reservations[] = $row;
    }

    return $reservations;

}


/* -------------------------
   UPDATE PARTNER BOOKING STATUS
------------------------- */
function update_partner_booking_status($partner_id, $booking_id, $status) {

    $partner_id = (int) $partner_id;
    $booking_id = (int) $booking_id;
    $status = trim((string) $status);
    $allowedStatuses = ['confirmed', 'canceled', 'completed'];

    if ($partner_id <= 0 || $booking_id <= 0 || !in_array($status, $allowedStatuses, true)) {
        return ['ok' => false, 'message' => 'Choose a valid reservation action.'];
    }

    $conn = db_connect();
    $lookupSql = "SELECT bk.id, bk.status
            FROM bookings bk
            INNER JOIN business_locations bl ON bl.location_id = bk.location_id
            INNER JOIN businesses b ON b.business_id = bl.business_id
            WHERE bk.id = ?
              AND b.partner_id = ?
            LIMIT 1";
    $lookupStmt = $conn->prepare($lookupSql);

    if (!$lookupStmt) {
        return ['ok' => false, 'message' => 'The reservation could not be checked right now.'];
    }

    $lookupStmt->bind_param("ii", $booking_id, $partner_id);
    $lookupStmt->execute();
    $result = $lookupStmt->get_result();
    $booking = $result ? $result->fetch_assoc() : null;

    if (!$booking) {
        return ['ok' => false, 'message' => 'This reservation does not belong to your partner account.'];
    }

    if ((string) ($booking['status'] ?? '') === $status) {
        return ['ok' => true, 'message' => 'The reservation is already ' . $status . '.'];
    }

    $updateStmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");

    if (!$updateStmt) {
        return ['ok' => false, 'message' => 'The reservation update could not be prepared right now.'];
    }

    $updateStmt->bind_param("si", $status, $booking_id);

    if (!$updateStmt->execute()) {
        return ['ok' => false, 'message' => 'The reservation could not be updated right now.'];
    }

    return [
        'ok' => true,
        'message' => $status === 'confirmed'
            ? 'Reservation approved.'
            : ($status === 'canceled' ? 'Reservation canceled.' : 'Reservation marked as completed.'),
    ];

}


/* -------------------------
   PARTNER RECENT CHECK-INS
------------------------- */
function get_partner_recent_checkins($partner_id, $limit = 8) {

    $partner_id = (int) $partner_id;
    $limit = max(1, (int) $limit);

    if ($partner_id <= 0) {
        return [];
    }

    ensure_where2go_rewards_schema();

    $conn = db_connect();
    $sql = "SELECT cc.id, cc.customer_id, cc.business_id, cc.location_id, cc.promo_code_snapshot,
                   cc.points_awarded, cc.xp_awarded, cc.checkin_date, cc.checked_in_at,
                   b.name AS business_name,
                   bl.location_name, bl.address AS location_address,
                   c.First_N, c.Last_N
            FROM customer_checkins cc
            INNER JOIN businesses b ON b.business_id = cc.business_id
            INNER JOIN business_locations bl ON bl.location_id = cc.location_id
            LEFT JOIN customers c ON c.Customer_ID = cc.customer_id
            WHERE b.partner_id = ?
            ORDER BY cc.checked_in_at DESC
            LIMIT " . $limit;
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $partner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $checkins = [];

    while ($row = $result->fetch_assoc()) {
        $checkins[] = $row;
    }

    return $checkins;

}


/* -------------------------
   PARTNER BY EMAIL
------------------------- */
function get_partner_by_email($email) {

    $email = trim((string) $email);

    if ($email === '') {
        return null;
    }

    $conn = db_connect();
    $sql = "SELECT * FROM partners WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result ? $result->fetch_assoc() : null;

}


/* -------------------------
   PARTNER BY ID
------------------------- */
function get_partner_by_id($partner_id) {

    $partner_id = (int) $partner_id;

    if ($partner_id <= 0) {
        return null;
    }

    $conn = db_connect();
    $sql = "SELECT * FROM partners WHERE partner_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $partner_id);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result ? $result->fetch_assoc() : null;

}


/* -------------------------
   PARTNER BUSINESSES
------------------------- */
function get_partner_businesses($partner_id, $approval_status = null) {

    $partner_id = (int) $partner_id;

    if ($partner_id <= 0) {
        return [];
    }

    $conn = db_connect();
    ensure_business_search_tags_schema($conn);
    ensure_business_theme_schema($conn);
    $sql = "SELECT b.business_id,
                   b.partner_id,
                   b.name,
                   b.description,
                   b.rules,
                   b.type,
                   b.custom_type,
                   b.search_tags,
                   b.logo_url,
                   b.website,
                   b.theme_preset,
                   b.theme_accent_color,
                   b.theme_cover_url,
                   b.brand_tagline,
                   b.approval_status,
                   b.review_note,
                   b.reviewed_at,
                   b.created_at,
                   (SELECT bl.location_id
                    FROM business_locations bl
                    WHERE bl.business_id = b.business_id
                    ORDER BY bl.location_id ASC
                    LIMIT 1) AS primary_location_id,
                   (SELECT bl.address
                    FROM business_locations bl
                    WHERE bl.business_id = b.business_id
                    ORDER BY bl.location_id ASC
                    LIMIT 1) AS primary_address,
                   (SELECT COUNT(*)
                    FROM customer_place_visits cpv
                    WHERE cpv.business_id = b.business_id) AS total_views,
                   (SELECT COUNT(*)
                    FROM bookings bk
                    INNER JOIN business_locations bl ON bl.location_id = bk.location_id
                    WHERE bl.business_id = b.business_id) AS total_bookings,
                   (SELECT COUNT(*)
                    FROM business_offers bo
                    WHERE bo.business_id = b.business_id
                      AND bo.is_active = 1
                      AND (bo.start_date IS NULL OR bo.start_date <= CURDATE())
                      AND (bo.end_date IS NULL OR bo.end_date >= CURDATE())) AS active_offers
            FROM businesses b
            WHERE b.partner_id = ?";

    if ($approval_status !== null) {
        $sql .= " AND b.approval_status = ?";
    }

    $sql .= " ORDER BY b.created_at DESC";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    if ($approval_status !== null) {
        $status = trim((string) $approval_status);
        $stmt->bind_param("is", $partner_id, $status);
    } else {
        $stmt->bind_param("i", $partner_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $businesses = [];

    while ($row = $result->fetch_assoc()) {
        $row['type_label'] = format_business_type_label($row['type'] ?? 'other', $row['custom_type'] ?? '');
        $row['icon'] = map_business_type_icon($row['type'] ?? 'other');
        $businesses[] = $row;
    }

    return $businesses;

}


function get_partner_business_profile_completion(array $business) {

    $businessId = (int) ($business['business_id'] ?? 0);
    $locations = is_array($business['locations'] ?? null) ? $business['locations'] : get_business_locations($businessId);
    $photos = get_business_photos($businessId);
    $menus = get_business_menus($businessId);
    $offers = get_all_business_offers($businessId);
    $searchTags = get_business_search_tag_list($business['search_tags'] ?? '');
    $nowDate = date('Y-m-d');
    $hasActiveOffer = false;
    $hasContactReadyLocation = false;
    $hasReservableLocation = false;
    $hasWorkingHours = false;

    foreach ($offers as $offer) {
        $startsOk = trim((string) ($offer['start_date'] ?? '')) === '' || (string) $offer['start_date'] <= $nowDate;
        $endsOk = trim((string) ($offer['end_date'] ?? '')) === '' || (string) $offer['end_date'] >= $nowDate;

        if (!empty($offer['is_active']) && $startsOk && $endsOk) {
            $hasActiveOffer = true;
            break;
        }
    }

    foreach ($locations as $location) {
        if (trim((string) ($location['address'] ?? '')) !== '' && trim((string) ($location['phone'] ?? '')) !== '') {
            $hasContactReadyLocation = true;
        }

        if (!empty($location['has_reservations'])) {
            $hasReservableLocation = true;
        }

        foreach (get_location_hours_rows((int) ($location['location_id'] ?? 0)) as $hourRow) {
            if (empty($hourRow['is_closed']) && trim((string) ($hourRow['open_time'] ?? '')) !== '' && trim((string) ($hourRow['close_time'] ?? '')) !== '') {
                $hasWorkingHours = true;
                break 2;
            }
        }
    }

    $checks = [
        'Business name' => trim((string) ($business['name'] ?? '')) !== '',
        'Public description' => trim((string) ($business['description'] ?? '')) !== '',
        'Logo' => trim((string) ($business['logo_url'] ?? '')) !== '',
        'Search tags' => !empty($searchTags),
        'At least 3 photos' => count($photos) >= 3,
        'Menu' => count($menus) > 0,
        'Contact-ready location' => $hasContactReadyLocation,
        'Working hours' => $hasWorkingHours,
        'Reservation enabled' => $hasReservableLocation,
        'Live offer' => $hasActiveOffer,
    ];

    $completed = count(array_filter($checks));
    $total = count($checks);
    $missing = [];

    foreach ($checks as $label => $ok) {
        if (!$ok) {
            $missing[] = $label;
        }
    }

    return [
        'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
        'completed' => $completed,
        'total' => $total,
        'missing' => $missing,
    ];

}


function get_partner_reservation_calendar($partner_id, $start_date = '', $days = 14) {

    $partner_id = (int) $partner_id;
    $days = max(1, min(31, (int) $days));
    $startTimestamp = strtotime((string) $start_date);

    if ($partner_id <= 0) {
        return [];
    }

    if (!$startTimestamp) {
        $startTimestamp = strtotime(date('Y-m-d'));
    }

    $startDate = date('Y-m-d', $startTimestamp);
    $endDate = date('Y-m-d', strtotime('+' . ($days - 1) . ' day', $startTimestamp));
    $calendar = [];

    for ($offset = 0; $offset < $days; $offset++) {
        $date = date('Y-m-d', strtotime('+' . $offset . ' day', $startTimestamp));
        $calendar[$date] = [
            'date' => $date,
            'items' => [],
        ];
    }

    $conn = db_connect();
    $sql = "SELECT bk.id,
                   bk.location_id,
                   bk.user_name,
                   bk.user_email,
                   bk.date,
                   bk.time_slot,
                   bk.guests,
                   bk.status,
                   b.business_id,
                   b.name AS business_name,
                   bl.location_name,
                   bl.address AS location_address
            FROM bookings bk
            INNER JOIN business_locations bl ON bl.location_id = bk.location_id
            INNER JOIN businesses b ON b.business_id = bl.business_id
            WHERE b.partner_id = ?
              AND bk.date BETWEEN ? AND ?
            ORDER BY bk.date ASC, bk.time_slot ASC, bk.id ASC";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return array_values($calendar);
    }

    $stmt->bind_param("iss", $partner_id, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $date = (string) ($row['date'] ?? '');

        if (isset($calendar[$date])) {
            $calendar[$date]['items'][] = $row;
        }
    }

    return array_values($calendar);

}


/* -------------------------
   PARTNER OWNS BUSINESS
------------------------- */
function current_partner_owns_business($business_id) {

    if (!is_partner_logged_in()) {
        return false;
    }

    $business_id = (int) $business_id;
    $partner_id = (int) ($_SESSION['partner_id'] ?? 0);

    if ($business_id <= 0 || $partner_id <= 0) {
        return false;
    }

    $conn = db_connect();
    $sql = "SELECT business_id FROM businesses WHERE business_id = ? AND partner_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("ii", $business_id, $partner_id);
    $stmt->execute();
    $result = $stmt->get_result();

    return (bool) ($result && $result->fetch_assoc());

}


/* -------------------------
   PARTNER OWNS LOCATION
------------------------- */
function partner_owns_location($partner_id, $location_id) {

    $partner_id = (int) $partner_id;
    $location_id = (int) $location_id;

    if ($partner_id <= 0 || $location_id <= 0) {
        return false;
    }

    $conn = db_connect();
    $sql = "SELECT bl.location_id
            FROM business_locations bl
            INNER JOIN businesses b ON b.business_id = bl.business_id
            WHERE bl.location_id = ?
              AND b.partner_id = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("ii", $location_id, $partner_id);
    $stmt->execute();
    $result = $stmt->get_result();

    return (bool) ($result && $result->fetch_assoc());

}


/* -------------------------
   REFRESH LOCATION QR
------------------------- */
function refresh_partner_location_qr_token($partner_id, $location_id) {

    $partner_id = (int) $partner_id;
    $location_id = (int) $location_id;

    if (!partner_owns_location($partner_id, $location_id)) {
        return [
            'ok' => false,
            'message' => 'You can only refresh QR codes for locations on your own partner account.',
        ];
    }

    ensure_where2go_rewards_schema();

    $conn = db_connect();
    $token = generate_unique_location_qr_token($conn);

    if ($token === '') {
        return [
            'ok' => false,
            'message' => 'A fresh QR token could not be generated right now.',
        ];
    }

    $stmt = $conn->prepare("UPDATE business_locations SET qr_token = ? WHERE location_id = ?");

    if (!$stmt) {
        return [
            'ok' => false,
            'message' => 'The QR update could not be prepared right now.',
        ];
    }

    $stmt->bind_param("si", $token, $location_id);

    if (!$stmt->execute()) {
        return [
            'ok' => false,
            'message' => 'The QR code could not be refreshed right now.',
        ];
    }

    return [
        'ok' => true,
        'message' => 'A fresh QR code was generated for this location.',
        'token' => $token,
        'url' => build_location_checkin_url($token),
    ];

}


/* -------------------------
   BUSINESS PHOTOS
------------------------- */
function ensure_business_photo_order_schema($conn = null) {

    static $ready = false;

    if ($ready) {
        return;
    }

    $conn = $conn ?: db_connect();
    ensure_table_column(
        $conn,
        'business_photos',
        'display_order',
        'ALTER TABLE business_photos ADD COLUMN display_order INT NOT NULL DEFAULT 0 AFTER image_url'
    );
    ensure_table_index(
        $conn,
        'business_photos',
        'idx_business_photos_display_order',
        'ALTER TABLE business_photos ADD INDEX idx_business_photos_display_order (business_id, display_order, id)'
    );

    $ready = true;

}


function get_business_photos($business_id) {

    $business_id = (int) $business_id;

    if ($business_id <= 0) {
        return [];
    }

    $conn = db_connect();
    ensure_business_photo_order_schema($conn);
    $sql = "SELECT id, business_id, image_url, display_order FROM business_photos WHERE business_id = ? ORDER BY display_order ASC, id ASC";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $photos = [];

    while ($row = $result->fetch_assoc()) {
        $photos[] = $row;
    }

    return $photos;

}


/* -------------------------
   BUSINESS MENUS
------------------------- */
function get_business_menus($business_id) {

    $business_id = (int) $business_id;

    if ($business_id <= 0) {
        return [];
    }

    $conn = db_connect();
    $sql = "SELECT id, business_id, title, file_url FROM business_menus WHERE business_id = ? ORDER BY id ASC";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $menus = [];

    while ($row = $result->fetch_assoc()) {
        $menus[] = $row;
    }

    return $menus;

}


/* -------------------------
   ALL BUSINESS OFFERS
------------------------- */
function get_all_business_offers($business_id) {

    $business_id = (int) $business_id;

    if ($business_id <= 0) {
        return [];
    }

    $conn = db_connect();
    $sql = "SELECT id, business_id, title, description, discount, start_date, end_date, is_active
            FROM business_offers
            WHERE business_id = ?
            ORDER BY id ASC";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $offers = [];

    while ($row = $result->fetch_assoc()) {
        $offers[] = $row;
    }

    return $offers;

}


/* -------------------------
   LOCATION HOURS ROWS
------------------------- */
function get_default_hours_rows() {

    $rows = [];

    for ($day = 0; $day <= 6; $day++) {
        $rows[$day] = [
            'day_of_week' => $day,
            'is_closed' => 0,
            'open_time' => '10:00',
            'close_time' => '23:00',
        ];
    }

    return $rows;

}

function normalize_hours_input_rows($hours) {

    $normalized = get_default_hours_rows();
    $hours = is_array($hours) ? $hours : [];

    for ($day = 0; $day <= 6; $day++) {
        $row = is_array($hours[$day] ?? null) ? $hours[$day] : [];
        $normalized[$day] = [
            'day_of_week' => $day,
            'is_closed' => !empty($row['is_closed']) ? 1 : 0,
            'open_time' => trim((string) ($row['open_time'] ?? '')),
            'close_time' => trim((string) ($row['close_time'] ?? '')),
        ];
    }

    return $normalized;

}

function get_location_hours_rows($location_id) {

    $location_id = (int) $location_id;

    if ($location_id <= 0) {
        return get_default_hours_rows();
    }

    $hoursMap = get_location_hours_map($location_id);
    $rows = get_default_hours_rows();

    for ($day = 0; $day <= 6; $day++) {
        $rows[$day] = [
            'day_of_week' => $day,
            'is_closed' => (int) ($hoursMap[$day]['is_closed'] ?? 0),
            'open_time' => (string) ($hoursMap[$day]['open_time'] ?? ''),
            'close_time' => (string) ($hoursMap[$day]['close_time'] ?? ''),
        ];
    }

    return $rows;

}


/* -------------------------
   HOURS DAY LABEL
------------------------- */
function get_day_name_from_index($day) {

    $labels = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    return $labels[(int) $day] ?? 'Day';

}


/* -------------------------
   NORMALIZE LOCATION INPUTS
------------------------- */
function normalize_partner_locations_input($locations, $legacyData = []) {

    $locations = is_array($locations) ? array_values($locations) : [];
    $legacyData = is_array($legacyData) ? $legacyData : [];

    if (!$locations) {
        $locations = [[
            'location_id' => (int) ($legacyData['location_id'] ?? 0),
            'location_name' => trim((string) ($legacyData['location_name'] ?? '')),
            'address' => trim((string) ($legacyData['address'] ?? '')),
            'phone' => trim((string) ($legacyData['phone'] ?? '')),
            'promo_code' => trim((string) ($legacyData['promo_code'] ?? '')),
            'promo_details' => trim((string) ($legacyData['promo_details'] ?? '')),
            'capacity_per_hour' => (int) ($legacyData['capacity_per_hour'] ?? 10),
            'has_reservations' => !empty($legacyData['has_reservations']) ? 1 : 0,
            'min_party_size' => (int) ($legacyData['min_party_size'] ?? 1),
            'max_party_size' => (int) ($legacyData['max_party_size'] ?? 40),
            'reservation_duration_minutes' => (int) ($legacyData['reservation_duration_minutes'] ?? 60),
            'reservation_buffer_minutes' => (int) ($legacyData['reservation_buffer_minutes'] ?? 0),
            'auto_approve_reservations' => !empty($legacyData['auto_approve_reservations']) ? 1 : 0,
            'same_day_cutoff_time' => trim((string) ($legacyData['same_day_cutoff_time'] ?? '')),
            'blocked_dates' => trim((string) ($legacyData['blocked_dates'] ?? '')),
            'hours' => $legacyData['hours'] ?? [],
        ]];
    }

    $normalized = [];

    foreach ($locations as $location) {
        $location = is_array($location) ? $location : [];
        $locationName = trim((string) ($location['location_name'] ?? ''));
        $address = trim((string) ($location['address'] ?? ''));
        $phone = trim((string) ($location['phone'] ?? ''));
        $promoCode = strtoupper(trim((string) ($location['promo_code'] ?? '')));
        $promoDetails = trim((string) ($location['promo_details'] ?? ''));
        $capacityPerHour = max(1, (int) ($location['capacity_per_hour'] ?? 10));
        $capacityGuestLimit = get_location_capacity_guest_limit(['capacity_per_hour' => $capacityPerHour]);
        $minPartySize = max(1, (int) ($location['min_party_size'] ?? 1));
        $maxPartySize = max($minPartySize, (int) ($location['max_party_size'] ?? $capacityGuestLimit));
        $maxPartySize = min(max($minPartySize, $maxPartySize), max($minPartySize, $capacityGuestLimit));
        $durationMinutes = max(15, min(360, (int) ($location['reservation_duration_minutes'] ?? 60)));
        $bufferMinutes = max(0, min(180, (int) ($location['reservation_buffer_minutes'] ?? 0)));
        $sameDayCutoffTime = normalize_partner_cutoff_time($location['same_day_cutoff_time'] ?? '');
        $blockedDates = normalize_partner_blocked_dates($location['blocked_dates'] ?? '');

        if ($locationName === '' && $address === '' && $phone === '') {
            continue;
        }

        $normalized[] = [
            'location_id' => (int) ($location['location_id'] ?? 0),
            'location_name' => $locationName,
            'address' => $address,
            'phone' => $phone,
            'promo_code' => $promoCode,
            'promo_details' => $promoDetails,
            'capacity_per_hour' => $capacityPerHour,
            'has_reservations' => !empty($location['has_reservations']) ? 1 : 0,
            'min_party_size' => $minPartySize,
            'max_party_size' => $maxPartySize,
            'reservation_duration_minutes' => $durationMinutes,
            'reservation_buffer_minutes' => $bufferMinutes,
            'auto_approve_reservations' => !empty($location['auto_approve_reservations']) ? 1 : 0,
            'same_day_cutoff_time' => $sameDayCutoffTime,
            'blocked_dates' => $blockedDates,
            'checkin_enabled' => array_key_exists('checkin_enabled', $location) ? (!empty($location['checkin_enabled']) ? 1 : 0) : 1,
            'hours' => normalize_hours_input_rows($location['hours'] ?? []),
        ];
    }

    return $normalized;

}


/* -------------------------
   PARTNER BUSINESS FORM DATA
------------------------- */
function get_partner_business_form_data($partner_id, $business_id = 0) {

    $partner_id = (int) $partner_id;
    $business_id = (int) $business_id;

    $defaults = [
        'business' => [
            'business_id' => 0,
            'name' => '',
            'description' => '',
            'rules' => '',
            'type' => 'restaurant',
            'custom_type' => '',
            'search_tags' => '',
            'logo_url' => '',
            'website' => '',
            'theme_preset' => 'where2go',
            'theme_accent_color' => '',
            'theme_cover_url' => '',
            'brand_tagline' => '',
            'approval_status' => 'pending',
            'review_note' => '',
            'reviewed_at' => null,
        ],
        'locations' => [[
            'location_id' => 0,
            'location_name' => '',
            'address' => '',
            'phone' => '',
            'promo_code' => '',
            'promo_details' => '',
            'capacity_per_hour' => 10,
            'has_reservations' => 1,
            'min_party_size' => 1,
            'max_party_size' => 40,
            'reservation_duration_minutes' => 60,
            'reservation_buffer_minutes' => 0,
            'auto_approve_reservations' => 0,
            'same_day_cutoff_time' => '',
            'blocked_dates' => '',
            'checkin_enabled' => 1,
            'hours' => get_default_hours_rows(),
        ]],
        'photos' => [],
        'menus' => [],
        'offers' => [],
    ];

    if ($partner_id <= 0 || $business_id <= 0) {
        return $defaults;
    }

    $business = get_business_by_id($business_id);

    if (!$business || (int) ($business['partner_id'] ?? 0) !== $partner_id) {
        return $defaults;
    }

    $locations = [];

    foreach (($business['locations'] ?? []) as $location) {
        $location['hours'] = get_location_hours_rows((int) ($location['location_id'] ?? 0));
        $locations[] = $location;
    }

    if (!$locations) {
        $locations = $defaults['locations'];
    }

    return [
        'business' => $business,
        'locations' => $locations,
        'photos' => get_business_photos($business_id),
        'menus' => get_business_menus($business_id),
        'offers' => get_all_business_offers($business_id),
    ];

}


/* -------------------------
   NORMALIZE URL LIST
------------------------- */
function normalize_safe_url_input($value, $allow_relative = true) {

    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if (preg_match('/[\x00-\x1F\x7F\s]/', $value) || strpos($value, '\\') !== false) {
        return '';
    }

    if (filter_var($value, FILTER_VALIDATE_URL)) {
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $value : '';
    }

    if ($allow_relative && !preg_match('/^[a-z][a-z0-9+.-]*:/i', $value) && strpos($value, '//') !== 0) {
        return $value;
    }

    return '';

}


function is_valid_iso_date_input($value) {

    $value = trim((string) $value);

    if ($value === '') {
        return true;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);

    return $date && $date->format('Y-m-d') === $value;

}


function normalize_url_input_list($values) {

    $values = is_array($values) ? $values : [];
    $normalized = [];

    foreach ($values as $value) {
        $value = normalize_safe_url_input($value);

        if ($value !== '') {
            $normalized[] = $value;
        }
    }

    return $normalized;

}


/* -------------------------
   SAVE PARTNER BUSINESS
------------------------- */
function save_partner_business_submission($partner_id, $data, $business_id = 0) {

    $partner_id = (int) $partner_id;
    $business_id = (int) $business_id;
    $data = is_array($data) ? $data : [];

    if ($partner_id <= 0) {
        return ['ok' => false, 'message' => 'Partner account is missing.'];
    }

    $name = trim((string) ($data['name'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $rules = trim((string) ($data['rules'] ?? ''));
    $type = trim((string) ($data['type'] ?? 'restaurant'));
    $customType = trim((string) ($data['custom_type'] ?? ''));
    $searchTags = normalize_business_search_tags($data['search_tags'] ?? '');
    $rawLogoUrl = trim((string) ($data['logo_url'] ?? ''));
    $rawWebsite = trim((string) ($data['website'] ?? ''));
    $themePreset = normalize_business_theme_preset($data['theme_preset'] ?? 'where2go');
    $themeAccentColor = normalize_business_theme_accent_color($data['theme_accent_color'] ?? '');
    $rawThemeCoverUrl = trim((string) ($data['theme_cover_url'] ?? ''));
    $themeCoverUrl = normalize_safe_url_input($rawThemeCoverUrl);
    $brandTagline = substr(trim(preg_replace('/\s+/', ' ', (string) ($data['brand_tagline'] ?? ''))), 0, 140);
    $logoUrl = normalize_safe_url_input($rawLogoUrl);
    $website = normalize_safe_url_input($rawWebsite, false);
    $settings = get_where2go_rewards_program_settings();
    $locations = normalize_partner_locations_input($data['locations'] ?? [], $data);
    $photoUrls = array_slice(normalize_url_input_list($data['photo_urls'] ?? []), 0, max(1, (int) ($settings['max_business_photos'] ?? 6)));
    $menuItems = is_array($data['menus'] ?? null) ? $data['menus'] : [];
    $offerItems = is_array($data['offers'] ?? null) ? $data['offers'] : [];
    $allowedTypes = ['restaurant', 'cafe', 'activity', 'entertainment', 'nightlife', 'heritage', 'other'];

    if ($name === '' || !$locations) {
        return ['ok' => false, 'message' => 'Business name and at least one location are required.'];
    }

    if (($rawLogoUrl !== '' && $logoUrl === '') || ($rawWebsite !== '' && $website === '')) {
        return ['ok' => false, 'message' => 'Website links must use http or https. Logo links may also use an existing site-relative asset path.'];
    }

    if ($rawThemeCoverUrl !== '' && $themeCoverUrl === '') {
        return ['ok' => false, 'message' => 'Cover image links must use http, https, or an existing site-relative asset path.'];
    }

    foreach ((is_array($data['photo_urls'] ?? null) ? $data['photo_urls'] : []) as $photoUrl) {
        if (trim((string) $photoUrl) !== '' && normalize_safe_url_input($photoUrl) === '') {
            return ['ok' => false, 'message' => 'Photo links must use http, https, or an existing site-relative asset path.'];
        }
    }

    foreach ($menuItems as $menuItem) {
        $menuUrl = trim((string) ($menuItem['file_url'] ?? ''));

        if ($menuUrl !== '' && normalize_safe_url_input($menuUrl) === '') {
            return ['ok' => false, 'message' => 'Menu links must use http, https, or an existing site-relative asset path.'];
        }
    }

    foreach ($offerItems as $offerItem) {
        $offerDiscount = trim((string) ($offerItem['discount'] ?? ''));
        $offerStart = trim((string) ($offerItem['start_date'] ?? ''));
        $offerEnd = trim((string) ($offerItem['end_date'] ?? ''));

        if ($offerDiscount !== '' && (!is_numeric($offerDiscount) || (float) $offerDiscount < 0 || (float) $offerDiscount > 100)) {
            return ['ok' => false, 'message' => 'Offer discounts must be between 0 and 100 percent.'];
        }

        if (!is_valid_iso_date_input($offerStart) || !is_valid_iso_date_input($offerEnd)) {
            return ['ok' => false, 'message' => 'Offer dates must use valid calendar dates.'];
        }

        if ($offerStart !== '' && $offerEnd !== '' && strtotime($offerEnd) < strtotime($offerStart)) {
            return ['ok' => false, 'message' => 'Offer end date cannot be before the start date.'];
        }
    }

    if (!in_array($type, $allowedTypes, true)) {
        $type = 'other';
    }

    if ($type !== 'other') {
        $customType = '';
    }

    if ($business_id > 0 && !current_partner_owns_business($business_id)) {
        return ['ok' => false, 'message' => 'You can only edit businesses on your own account.'];
    }

    ensure_where2go_rewards_schema();

    $conn = db_connect();
    ensure_business_search_tags_schema($conn);
    ensure_business_theme_schema($conn);
    ensure_partner_reservation_settings_schema($conn);
    ensure_business_photo_order_schema($conn);
    ensure_business_type_catalog_values($conn);
    $currentBusiness = $business_id > 0 ? get_business_by_id($business_id) : null;
    $preserveApproval = $currentBusiness && trim((string) ($currentBusiness['approval_status'] ?? '')) === 'approved';

    try {
        $conn->begin_transaction();

        if ($business_id > 0) {
            $sql = "UPDATE businesses
                    SET name = ?, description = ?, rules = ?, type = ?, custom_type = ?, search_tags = ?, logo_url = ?, website = ?,
                        theme_preset = ?, theme_accent_color = NULLIF(?, ''), theme_cover_url = NULLIF(?, ''), brand_tagline = NULLIF(?, ''),
                        approval_status = " . ($preserveApproval ? "'approved'" : "'pending'") . ",
                        review_note = " . ($preserveApproval ? "review_note" : "NULL") . ",
                        reviewed_at = " . ($preserveApproval ? "reviewed_at" : "NULL") . "
                    WHERE business_id = ? AND partner_id = ?";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                throw new Exception('Business update could not be prepared.');
            }

            $stmt->bind_param("ssssssssssssii", $name, $description, $rules, $type, $customType, $searchTags, $logoUrl, $website, $themePreset, $themeAccentColor, $themeCoverUrl, $brandTagline, $business_id, $partner_id);

            if (!$stmt->execute()) {
                throw new Exception('Business update failed.');
            }
        } else {
            $sql = "INSERT INTO businesses
                    (partner_id, name, description, rules, type, custom_type, search_tags, logo_url, website,
                     theme_preset, theme_accent_color, theme_cover_url, brand_tagline, approval_status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?, NOW())";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                throw new Exception('Business insert could not be prepared.');
            }

            $approvalStatus = 'pending';
            $stmt->bind_param("isssssssssssss", $partner_id, $name, $description, $rules, $type, $customType, $searchTags, $logoUrl, $website, $themePreset, $themeAccentColor, $themeCoverUrl, $brandTagline, $approvalStatus);

            if (!$stmt->execute()) {
                throw new Exception('Business insert failed.');
            }

            $business_id = (int) $conn->insert_id;
        }

        $existingLocationIds = [];
        $existingLocationResult = $conn->query("SELECT location_id FROM business_locations WHERE business_id = " . (int) $business_id);

        if ($existingLocationResult) {
            while ($row = $existingLocationResult->fetch_assoc()) {
                $existingLocationIds[] = (int) ($row['location_id'] ?? 0);
            }
        }

        $deleteHoursStmt = $conn->prepare("DELETE FROM business_hours WHERE location_id = ?");

        if (!$deleteHoursStmt) {
            throw new Exception('Business hours cleanup could not be prepared.');
        }

        $insertHoursStmt = $conn->prepare("INSERT INTO business_hours (location_id, day_of_week, is_closed, open_time, close_time) VALUES (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''))");

        if (!$insertHoursStmt) {
            throw new Exception('Business hours insert could not be prepared.');
        }

        $savedLocationIds = [];

        foreach ($locations as $location) {
            $locationId = (int) ($location['location_id'] ?? 0);
            $locationName = trim((string) ($location['location_name'] ?? ''));
            $address = trim((string) ($location['address'] ?? ''));
            $phone = trim((string) ($location['phone'] ?? ''));
            $promoCode = strtoupper(trim((string) ($location['promo_code'] ?? '')));
            $promoDetails = trim((string) ($location['promo_details'] ?? ''));
            $capacityPerHour = max(1, (int) ($location['capacity_per_hour'] ?? 10));
            $hasReservations = !empty($location['has_reservations']) ? 1 : 0;
            $minPartySize = max(1, (int) ($location['min_party_size'] ?? 1));
            $maxPartySize = max($minPartySize, (int) ($location['max_party_size'] ?? get_location_capacity_guest_limit(['capacity_per_hour' => $capacityPerHour])));
            $durationMinutes = max(15, min(360, (int) ($location['reservation_duration_minutes'] ?? 60)));
            $bufferMinutes = max(0, min(180, (int) ($location['reservation_buffer_minutes'] ?? 0)));
            $autoApproveReservations = !empty($location['auto_approve_reservations']) ? 1 : 0;
            $sameDayCutoffTime = normalize_partner_cutoff_time($location['same_day_cutoff_time'] ?? '');
            $blockedDates = normalize_partner_blocked_dates($location['blocked_dates'] ?? '');
            $checkinEnabled = array_key_exists('checkin_enabled', $location) ? (!empty($location['checkin_enabled']) ? 1 : 0) : 1;

            if ($locationId > 0 && in_array($locationId, $existingLocationIds, true)) {
                $sql = "UPDATE business_locations
                        SET location_name = ?, address = ?, phone = ?, promo_code = ?, promo_details = ?,
                            capacity_per_hour = ?, has_reservations = ?, min_party_size = ?, max_party_size = ?,
                            reservation_duration_minutes = ?, reservation_buffer_minutes = ?, auto_approve_reservations = ?,
                            same_day_cutoff_time = NULLIF(?, ''), blocked_dates = NULLIF(?, ''), checkin_enabled = ?
                        WHERE location_id = ? AND business_id = ?";
                $stmt = $conn->prepare($sql);

                if (!$stmt) {
                    throw new Exception('Location update could not be prepared.');
                }

                $stmt->bind_param(
                    "sssssiiiiiiissiii",
                    $locationName,
                    $address,
                    $phone,
                    $promoCode,
                    $promoDetails,
                    $capacityPerHour,
                    $hasReservations,
                    $minPartySize,
                    $maxPartySize,
                    $durationMinutes,
                    $bufferMinutes,
                    $autoApproveReservations,
                    $sameDayCutoffTime,
                    $blockedDates,
                    $checkinEnabled,
                    $locationId,
                    $business_id
                );

                if (!$stmt->execute()) {
                    throw new Exception('Location update failed.');
                }

                ensure_location_qr_token($locationId, '', $conn);
            } else {
                $qrToken = generate_unique_location_qr_token($conn);
                $sql = "INSERT INTO business_locations
                        (business_id, location_name, address, phone, promo_code, promo_details, qr_token,
                         capacity_per_hour, has_reservations, min_party_size, max_party_size,
                         reservation_duration_minutes, reservation_buffer_minutes, auto_approve_reservations,
                         same_day_cutoff_time, blocked_dates, checkin_enabled)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?)";
                $stmt = $conn->prepare($sql);

                if (!$stmt) {
                    throw new Exception('Location insert could not be prepared.');
                }

                $stmt->bind_param(
                    "issssssiiiiiiissi",
                    $business_id,
                    $locationName,
                    $address,
                    $phone,
                    $promoCode,
                    $promoDetails,
                    $qrToken,
                    $capacityPerHour,
                    $hasReservations,
                    $minPartySize,
                    $maxPartySize,
                    $durationMinutes,
                    $bufferMinutes,
                    $autoApproveReservations,
                    $sameDayCutoffTime,
                    $blockedDates,
                    $checkinEnabled
                );

                if (!$stmt->execute()) {
                    throw new Exception('Location insert failed.');
                }

                $locationId = (int) $conn->insert_id;
            }

            $savedLocationIds[] = $locationId;
            $deleteHoursStmt->bind_param("i", $locationId);
            $deleteHoursStmt->execute();

            foreach (normalize_hours_input_rows($location['hours'] ?? []) as $day => $dayHours) {
                $isClosed = !empty($dayHours['is_closed']) ? 1 : 0;
                $openTime = trim((string) ($dayHours['open_time'] ?? ''));
                $closeTime = trim((string) ($dayHours['close_time'] ?? ''));

                if ($isClosed) {
                    $openTime = '';
                    $closeTime = '';
                } else {
                    $openTime = $openTime !== '' ? $openTime . ':00' : '';
                    $closeTime = $closeTime !== '' ? $closeTime . ':00' : '';
                }

                $insertHoursStmt->bind_param("iiiss", $locationId, $day, $isClosed, $openTime, $closeTime);

                if (!$insertHoursStmt->execute()) {
                    throw new Exception('Business hours insert failed.');
                }
            }
        }

        foreach (array_diff($existingLocationIds, $savedLocationIds) as $deletedLocationId) {
            $deletedLocationId = (int) $deletedLocationId;
            $bookingCountResult = $conn->query("SELECT COUNT(*) AS total FROM bookings WHERE location_id = {$deletedLocationId}");
            $bookingCountRow = $bookingCountResult ? $bookingCountResult->fetch_assoc() : ['total' => 0];

            if ((int) ($bookingCountRow['total'] ?? 0) > 0) {
                continue;
            }

            $deleteHoursStmt->bind_param("i", $deletedLocationId);
            $deleteHoursStmt->execute();
            $conn->query("DELETE FROM business_locations WHERE location_id = {$deletedLocationId} AND business_id = " . (int) $business_id);
        }

        foreach (['business_photos', 'business_menus', 'business_offers'] as $tableName) {
            $deleteStmt = $conn->prepare("DELETE FROM {$tableName} WHERE business_id = ?");

            if ($deleteStmt) {
                $deleteStmt->bind_param("i", $business_id);
                $deleteStmt->execute();
            }
        }

        if ($photoUrls) {
            $insertPhotoStmt = $conn->prepare("INSERT INTO business_photos (business_id, image_url, display_order) VALUES (?, ?, ?)");

            if (!$insertPhotoStmt) {
                throw new Exception('Business photo insert could not be prepared.');
            }

            foreach ($photoUrls as $photoIndex => $photoUrl) {
                $displayOrder = (int) $photoIndex;
                $insertPhotoStmt->bind_param("isi", $business_id, $photoUrl, $displayOrder);

                if (!$insertPhotoStmt->execute()) {
                    throw new Exception('Business photo insert failed.');
                }
            }
        }

        $insertMenuStmt = $conn->prepare("INSERT INTO business_menus (business_id, title, file_url) VALUES (?, ?, ?)");

        if (!$insertMenuStmt) {
            throw new Exception('Business menu insert could not be prepared.');
        }

        foreach ($menuItems as $menuItem) {
            $menuTitle = trim((string) ($menuItem['title'] ?? ''));
            $menuUrl = normalize_safe_url_input($menuItem['file_url'] ?? '');

            if ($menuTitle === '' && $menuUrl === '') {
                continue;
            }

            $insertMenuStmt->bind_param("iss", $business_id, $menuTitle, $menuUrl);

            if (!$insertMenuStmt->execute()) {
                throw new Exception('Business menu insert failed.');
            }
        }

        $insertOfferStmt = $conn->prepare("INSERT INTO business_offers (business_id, title, description, discount, start_date, end_date, is_active) VALUES (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?)");

        if (!$insertOfferStmt) {
            throw new Exception('Business offer insert could not be prepared.');
        }

        foreach ($offerItems as $offerItem) {
            $offerTitle = trim((string) ($offerItem['title'] ?? ''));
            $offerDescription = trim((string) ($offerItem['description'] ?? ''));
            $offerDiscount = trim((string) ($offerItem['discount'] ?? ''));
            $offerStart = trim((string) ($offerItem['start_date'] ?? ''));
            $offerEnd = trim((string) ($offerItem['end_date'] ?? ''));
            $offerActive = !empty($offerItem['is_active']) ? 1 : 0;

            if ($offerTitle === '' && $offerDescription === '') {
                continue;
            }

            $discountValue = $offerDiscount !== '' ? $offerDiscount : '';
            $startValue = $offerStart !== '' ? $offerStart : '';
            $endValue = $offerEnd !== '' ? $offerEnd : '';
            $insertOfferStmt->bind_param("isssssi", $business_id, $offerTitle, $offerDescription, $discountValue, $startValue, $endValue, $offerActive);

            if (!$insertOfferStmt->execute()) {
                throw new Exception('Business offer insert failed.');
            }
        }

        $conn->commit();
        clear_where2go_mobile_cache('places');
        clear_where2go_mobile_cache('availability');

        return [
            'ok' => true,
            'business_id' => $business_id,
            'message' => $preserveApproval
                ? 'Your approved business was updated successfully.'
                : 'Your business was saved and is now waiting for admin approval.',
        ];
    } catch (Throwable $error) {
        $conn->rollback();

        return [
            'ok' => false,
            'message' => 'The business could not be saved right now.',
            'error' => $error->getMessage(),
        ];
    }

}


/* -------------------------
   BUSINESS APPROVAL
------------------------- */
function set_business_approval_status($business_id, $status, $review_note = '') {

    $business_id = (int) $business_id;
    $status = trim((string) $status);
    $review_note = trim((string) $review_note);
    $allowed = ['pending', 'approved', 'rejected'];

    if ($business_id <= 0 || !in_array($status, $allowed, true)) {
        return false;
    }

    $conn = db_connect();
    $sql = "UPDATE businesses
            SET approval_status = ?,
                review_note = ?,
                reviewed_at = ?
            WHERE business_id = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $noteValue = $status === 'rejected' ? ($review_note !== '' ? $review_note : 'No rejection note was added.') : null;
    $reviewedAt = $status === 'pending' ? null : date('Y-m-d H:i:s');
    $stmt->bind_param("sssi", $status, $noteValue, $reviewedAt, $business_id);

    $ok = $stmt->execute();

    if ($ok) {
        clear_where2go_mobile_cache('places');
    }

    return $ok;

}


/* -------------------------
   PENDING BUSINESSES
------------------------- */
function get_pending_businesses($status = 'pending') {

    $status = trim((string) $status);
    $conn = db_connect();
    ensure_business_search_tags_schema($conn);
    $sql = "SELECT b.business_id,
                   b.name,
                   b.description,
                   b.type,
                   b.custom_type,
                   b.search_tags,
                   b.website,
                   b.approval_status,
                   b.review_note,
                   b.reviewed_at,
                   b.created_at,
                   p.partner_id,
                   p.owner_name,
                   p.email AS partner_email,
                   (SELECT bl.address
                    FROM business_locations bl
                    WHERE bl.business_id = b.business_id
                    ORDER BY bl.location_id ASC
                    LIMIT 1) AS primary_address
            FROM businesses b
            INNER JOIN partners p ON p.partner_id = b.partner_id
            WHERE b.approval_status = ?
            ORDER BY " . ($status === 'pending' ? "b.created_at ASC" : "COALESCE(b.reviewed_at, b.created_at) DESC");
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("s", $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $businesses = [];

    while ($row = $result->fetch_assoc()) {
        $row['type_label'] = format_business_type_label($row['type'] ?? 'other', $row['custom_type'] ?? '');
        $row['icon'] = map_business_type_icon($row['type'] ?? 'other');
        $businesses[] = $row;
    }

    return $businesses;

}


/* -------------------------
   GET CUSTOMER BY EMAIL
------------------------- */
function get_customer_by_email($email) {

    $conn = db_connect();

    $sql = "SELECT * FROM customers WHERE Email = ?";
    $stmt = $conn->prepare($sql);

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    return $result->fetch_assoc();

}


/* -------------------------
   GET CUSTOMER BY ID
------------------------- */
function get_customer_by_id($id) {

    $conn = db_connect();

    $sql = "SELECT * FROM customers WHERE Customer_ID = ?";
    $stmt = $conn->prepare($sql);

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();

    return $result->fetch_assoc();

}


/* -------------------------
   GET LOCATION BY ID
------------------------- */
function get_location_by_id($location_id) {

    $location_id = (int) $location_id;
    static $cache = [];

    if ($location_id <= 0) {
        return null;
    }

    if (array_key_exists($location_id, $cache)) {
        return $cache[$location_id];
    }

    ensure_where2go_rewards_schema();

    $conn = db_connect();
    ensure_partner_reservation_settings_schema($conn);
    $sql = "SELECT bl.location_id, bl.business_id, bl.location_name, bl.address, bl.phone, bl.promo_code, bl.promo_details,
                   bl.qr_token, bl.capacity_per_hour, bl.has_reservations, bl.min_party_size, bl.max_party_size,
                   bl.reservation_duration_minutes, bl.reservation_buffer_minutes, bl.auto_approve_reservations,
                   bl.same_day_cutoff_time, bl.blocked_dates, bl.checkin_enabled,
                   b.name AS business_name, b.type AS business_type, b.custom_type, b.website, b.approval_status
            FROM business_locations bl
            INNER JOIN businesses b ON b.business_id = bl.business_id
            WHERE bl.location_id = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $location_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $location = $result ? $result->fetch_assoc() : null;

    if (!$location) {
        return null;
    }

    $location['checkin_enabled'] = (int) ($location['checkin_enabled'] ?? 1);
    $location['qr_token'] = ensure_location_qr_token((int) ($location['location_id'] ?? 0), (string) ($location['qr_token'] ?? ''), $conn);
    $location['checkin_url'] = build_location_checkin_url((string) ($location['qr_token'] ?? ''));
    $location['type_label'] = format_business_type_label($location['business_type'] ?? 'other', $location['custom_type'] ?? '');

    $cache[$location_id] = $location;

    return $cache[$location_id];

}


/* -------------------------
   NORMALIZE BOOKING TIME
------------------------- */
function normalize_booking_time_slot($time) {

    $timestamp = strtotime((string) $time);

    if (!$timestamp) {
        return '';
    }

    return date('H:i:s', $timestamp);

}


/* -------------------------
   BOOKING SLOT FUTURE CHECK
------------------------- */
function is_booking_slot_in_future($date, $time, $location_id = 0) {

    $time = normalize_booking_time_slot($time);
    $slotTimestamp = $time !== '' ? strtotime((string) $date . ' ' . $time) : false;

    if ($slotTimestamp === false) {
        return false;
    }

    $location_id = (int) $location_id;

    if ($location_id > 0) {
        $hours = get_location_hours_for_date($location_id, $date);
        $openTime = trim((string) ($hours['open_time'] ?? ''));
        $closeTime = trim((string) ($hours['close_time'] ?? ''));
        $openTimestamp = $openTime !== '' ? strtotime((string) $date . ' ' . $openTime) : false;
        $closeTimestamp = $closeTime !== '' ? strtotime((string) $date . ' ' . $closeTime) : false;

        if ($openTimestamp && $closeTimestamp && $closeTimestamp <= $openTimestamp && $slotTimestamp < $openTimestamp) {
            $slotTimestamp = strtotime('+1 day', $slotTimestamp);
        }
    }

    return $slotTimestamp > time();

}


/* -------------------------
   TABLES NEEDED
------------------------- */
function get_required_table_count($guests = 1) {

    $guests = max(1, (int) $guests);

    return (int) ceil($guests / 4);

}


/* -------------------------
   LOCATION OPEN FOR SLOT
------------------------- */
function is_location_open_for_booking($location_id, $date, $time) {

    $hours = get_location_hours_for_date($location_id, $date);
    $time = normalize_booking_time_slot($time);

    if (!$hours || $time === '') {
        return false;
    }

    if ((int) ($hours['is_closed'] ?? 0) === 1) {
        return false;
    }

    $openTime = trim((string) ($hours['open_time'] ?? ''));
    $closeTime = trim((string) ($hours['close_time'] ?? ''));

    if ($openTime === '' || $closeTime === '') {
        return false;
    }

    $openTimestamp = strtotime($date . ' ' . $openTime);
    $closeTimestamp = strtotime($date . ' ' . $closeTime);
    $slotTimestamp = strtotime($date . ' ' . $time);

    if (!$openTimestamp || !$closeTimestamp || !$slotTimestamp) {
        return false;
    }

    if ($closeTimestamp <= $openTimestamp) {
        $closeTimestamp = strtotime('+1 day', $closeTimestamp);

        if ($slotTimestamp < $openTimestamp) {
            $slotTimestamp = strtotime('+1 day', $slotTimestamp);
        }
    }

    return $slotTimestamp >= $openTimestamp && $slotTimestamp < $closeTimestamp;

}


function is_booking_time_on_location_slot_interval(array $location, $date, $time) {

    $locationId = (int) ($location['location_id'] ?? 0);
    $hours = get_location_hours_for_date($locationId, $date);
    $time = normalize_booking_time_slot($time);

    if (!$hours || $time === '') {
        return false;
    }

    $openTime = trim((string) ($hours['open_time'] ?? ''));
    $closeTime = trim((string) ($hours['close_time'] ?? ''));

    if ($openTime === '' || $closeTime === '') {
        return false;
    }

    $openTimestamp = strtotime((string) $date . ' ' . $openTime);
    $closeTimestamp = strtotime((string) $date . ' ' . $closeTime);
    $slotTimestamp = strtotime((string) $date . ' ' . $time);

    if (!$openTimestamp || !$closeTimestamp || !$slotTimestamp) {
        return false;
    }

    if ($closeTimestamp <= $openTimestamp && $slotTimestamp < $openTimestamp) {
        $slotTimestamp = strtotime('+1 day', $slotTimestamp);
    }

    $minutesFromOpen = (int) (($slotTimestamp - $openTimestamp) / 60);
    $slotMinutes = get_location_booking_slot_minutes($location, 60);

    return $minutesFromOpen >= 0 && $slotMinutes > 0 && $minutesFromOpen % $slotMinutes === 0;

}


/* -------------------------
   SLOT BOOKING USAGE
------------------------- */
function get_location_booking_slot_usage($location_id, $date, $time) {

    $location_id = (int) $location_id;
    $time = normalize_booking_time_slot($time);

    if ($location_id <= 0 || $time === '' || !strtotime((string) $date)) {
        return 0;
    }

    $conn = db_connect();
    $sql = "SELECT COALESCE(SUM(CASE
                        WHEN guests IS NULL OR guests < 1 THEN 1
                        ELSE CEIL(guests / 4)
                    END), 0) AS reserved_units
            FROM bookings
            WHERE location_id = ?
              AND date = ?
              AND time_slot = ?
              AND status IN ('pending', 'confirmed')";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param("iss", $location_id, $date, $time);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    return (int) ($row['reserved_units'] ?? 0);

}


/* -------------------------
   SLOT BOOKING USAGE MAP
------------------------- */
function get_location_booking_slot_usage_map($location_id, $date) {

    $location_id = (int) $location_id;

    if ($location_id <= 0 || !strtotime((string) $date)) {
        return [];
    }

    $conn = db_connect();
    $sql = "SELECT time_slot,
                   COALESCE(SUM(CASE
                        WHEN guests IS NULL OR guests < 1 THEN 1
                        ELSE CEIL(guests / 4)
                    END), 0) AS reserved_units
            FROM bookings
            WHERE location_id = ?
              AND date = ?
              AND status IN ('pending', 'confirmed')
            GROUP BY time_slot";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("is", $location_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $usage = [];

    while ($row = $result->fetch_assoc()) {
        $time = normalize_booking_time_slot($row['time_slot'] ?? '');

        if ($time !== '') {
            $usage[$time] = (int) ($row['reserved_units'] ?? 0);
        }
    }

    return $usage;

}


/* -------------------------
   SLOT BOOKING USAGE MAPS
------------------------- */
function get_location_booking_slot_usage_maps($location_id, $start_date, $days = 14) {

    $location_id = (int) $location_id;
    $days = max(1, (int) $days);
    $startTimestamp = strtotime((string) $start_date);

    if ($location_id <= 0 || !$startTimestamp) {
        return [];
    }

    $startDate = date('Y-m-d', $startTimestamp);
    $endDate = date('Y-m-d', strtotime('+' . ($days - 1) . ' day', $startTimestamp));
    $conn = db_connect();
    $sql = "SELECT date,
                   time_slot,
                   COALESCE(SUM(CASE
                        WHEN guests IS NULL OR guests < 1 THEN 1
                        ELSE CEIL(guests / 4)
                    END), 0) AS reserved_units
            FROM bookings
            WHERE location_id = ?
              AND date BETWEEN ? AND ?
              AND status IN ('pending', 'confirmed')
            GROUP BY date, time_slot";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("iss", $location_id, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $usage = [];

    while ($row = $result->fetch_assoc()) {
        $date = (string) ($row['date'] ?? '');
        $time = normalize_booking_time_slot($row['time_slot'] ?? '');

        if ($date !== '' && $time !== '') {
            $usage[$date][$time] = (int) ($row['reserved_units'] ?? 0);
        }
    }

    return $usage;

}


/* -------------------------
   BUILD BOOKING SLOTS
------------------------- */
function build_available_booking_slots_from_usage(array $location, ?array $hours, $date, $slot_minutes = 60, $guests = 1, array $usage_map = []) {

    $slot_minutes = get_location_booking_slot_minutes($location, $slot_minutes);
    $guests = max(1, (int) $guests);

    if ((int) ($location['has_reservations'] ?? 0) !== 1 || !$hours || (int) ($hours['is_closed'] ?? 0) === 1) {
        return [];
    }

    if (!is_location_reservation_request_allowed($location, $date, $guests)) {
        return [];
    }

    $openTime = trim((string) ($hours['open_time'] ?? ''));
    $closeTime = trim((string) ($hours['close_time'] ?? ''));

    if ($openTime === '' || $closeTime === '') {
        return [];
    }

    $current = strtotime((string) $date . ' ' . $openTime);
    $end = strtotime((string) $date . ' ' . $closeTime);

    if (!$current || !$end) {
        return [];
    }

    if ($end <= $current) {
        $end = strtotime('+1 day', $end);
    }

    $capacity = max(0, (int) ($location['capacity_per_hour'] ?? 0));
    $requiredTables = get_required_table_count($guests);

    if ($capacity <= 0) {
        return [];
    }

    $slots = [];
    $now = time();

    while ($current < $end) {
        $time = date('H:i:s', $current);
        $reservedUnits = (int) ($usage_map[$time] ?? 0);
        $slots[] = [
            'time' => $time,
            'available' => $current > $now && ($reservedUnits + $requiredTables) <= $capacity,
        ];
        $current = strtotime('+' . $slot_minutes . ' minutes', $current);
    }

    return $slots;

}


/* -------------------------
   SLOT AVAILABILITY
------------------------- */
function is_booking_slot_available($location_id, $date, $time, $guests = 1) {

    $location = get_location_by_id($location_id);
    $guests = max(1, (int) $guests);
    $requiredTables = get_required_table_count($guests);

    if (!$location || (int) ($location['has_reservations'] ?? 0) !== 1) {
        return false;
    }

    if (!is_location_reservation_request_allowed($location, $date, $guests)) {
        return false;
    }

    if (!is_location_open_for_booking($location_id, $date, $time)) {
        return false;
    }

    if (!is_booking_slot_in_future($date, $time, $location_id)) {
        return false;
    }

    if (!is_booking_time_on_location_slot_interval($location, $date, $time)) {
        return false;
    }

    $capacity = max(0, (int) ($location['capacity_per_hour'] ?? 0));

    if ($capacity <= 0) {
        return false;
    }

    $reservedUnits = get_location_booking_slot_usage($location_id, $date, $time);

    return ($reservedUnits + $requiredTables) <= $capacity;

}


/* -------------------------
   AVAILABLE BOOKING SLOTS
------------------------- */
function get_available_booking_slots($location_id, $date, $slot_minutes = 60, $guests = 1) {

    $location_id = (int) $location_id;
    $slot_minutes = max(15, (int) $slot_minutes);
    $guests = max(1, (int) $guests);
    $location = get_location_by_id($location_id);
    $hours = get_location_hours_for_date($location_id, $date);

    if (!$location) {
        return [];
    }

    $usageMap = get_location_booking_slot_usage_map($location_id, $date);

    return build_available_booking_slots_from_usage($location, $hours, $date, $slot_minutes, $guests, $usageMap);

}


/* -------------------------
   BOOKING CALENDAR DAYS
------------------------- */
function get_location_booking_calendar_days($location_id, $start_date, $days = 21, $guests = 1) {

    $location_id = (int) $location_id;
    $days = max(1, (int) $days);
    $guests = max(1, (int) $guests);
    $startTimestamp = strtotime((string) $start_date);

    if ($location_id <= 0 || !$startTimestamp) {
        return [];
    }

    $location = get_location_by_id($location_id);

    if (!$location || (int) ($location['has_reservations'] ?? 0) !== 1) {
        return [];
    }

    $calendar = [];
    $usageMaps = get_location_booking_slot_usage_maps($location_id, date('Y-m-d', $startTimestamp), $days);

    for ($offset = 0; $offset < $days; $offset++) {
        $date = date('Y-m-d', strtotime('+' . $offset . ' day', $startTimestamp));
        $hours = get_location_hours_for_date($location_id, $date);
        $status = 'closed';
        $slots = [];

        if ($hours && (int) ($hours['is_closed'] ?? 0) !== 1) {
            $slots = build_available_booking_slots_from_usage($location, $hours, $date, 60, $guests, $usageMaps[$date] ?? []);
            $availableSlots = array_values(array_filter($slots, function ($slot) {
                return !empty($slot['available']);
            }));
            $status = $availableSlots ? 'available' : 'full';
        }

        $calendar[] = [
            'date' => $date,
            'status' => $status,
            'slots' => $slots,
        ];
    }

    return $calendar;

}


/* -------------------------
   CREATE BOOKING
------------------------- */
function create_booking($customer_id, $location_id, $date, $time, $guests = 1, $user_name = null, $user_email = null) {

    $customer_id = (int) $customer_id;
    $location_id = (int) $location_id;
    $guests = max(1, (int) $guests);
    $time = normalize_booking_time_slot($time);

    if ($customer_id <= 0 || $location_id <= 0 || !strtotime((string) $date) || $time === '') {
        return false;
    }

    $location = get_location_by_id($location_id);

    if (!$location) {
        return false;
    }

    if (!is_booking_slot_available($location_id, $date, $time, $guests)) {
        return false;
    }

    $customer = get_customer_by_id($customer_id);
    $fallbackName = trim(($customer['First_N'] ?? '') . ' ' . ($customer['Last_N'] ?? ''));
    $user_name = trim((string) ($user_name !== null ? $user_name : $fallbackName));
    $user_email = trim((string) ($user_email !== null ? $user_email : ($customer['Email'] ?? '')));
    $status = !empty($location['auto_approve_reservations']) ? "confirmed" : "pending";
    $conn = db_connect();
    $sql = "INSERT INTO bookings
            (location_id, user_name, user_email, date, time_slot, guests, status, created_at, customer_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("issssisi", $location_id, $user_name, $user_email, $date, $time, $guests, $status, $customer_id);

    return $stmt->execute();

}


/* -------------------------
   GET CUSTOMER BOOKINGS
------------------------- */
function get_customer_bookings($customer_id) {

    $customer_id = (int) $customer_id;

    if ($customer_id <= 0) {
        return [];
    }

    $conn = db_connect();

    $sql = "SELECT bk.id,
                   bk.location_id,
                   bk.user_name,
                   bk.user_email,
                   bk.date,
                   bk.time_slot,
                   bk.guests,
                   bk.status,
                   bk.created_at,
                   bk.customer_id,
                   bl.address AS location_address,
                   bl.phone AS location_phone,
                   b.business_id,
                   b.name AS business_name,
                   b.type AS business_type,
                   b.custom_type
            FROM bookings bk
            INNER JOIN business_locations bl ON bl.location_id = bk.location_id
            INNER JOIN businesses b ON b.business_id = bl.business_id
            WHERE bk.customer_id = ?
            ORDER BY bk.date DESC, bk.time_slot DESC";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $customer_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $bookings = [];

    while ($row = $result->fetch_assoc()) {
        $row['business_type_label'] = format_business_type_label($row['business_type'] ?? 'other', $row['custom_type'] ?? '');
        $bookings[] = $row;
    }

    return $bookings;

}


/* -------------------------
   SAFE REDIRECT
------------------------- */
function redirect($url) {

    header("Location: $url");
    exit();

}

?>

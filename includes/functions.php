<?php

/* -------------------------
   DATABASE CONNECTION
------------------------- */
function db_connect() {

    $host = "localhost";
    $user = "root";
    $password = "";
    $database = "where2go";

    $conn = new mysqli($host, $user, $password, $database);

    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }

    return $conn;
}


/* -------------------------
   SANITIZE INPUT
------------------------- */
function clean_input($data) {

    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);

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
        session_start();
    }

}


/* -------------------------
   LOGIN USER
------------------------- */
function login_user($customer) {

    start_session();

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

}


/* -------------------------
   LOGIN PARTNER
------------------------- */
function login_partner_user($partner) {

    start_session();

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

    if ($emails) {
        $emails = array_values(array_unique($emails));
        return $emails;
    }

    $conn = db_connect();
    $result = $conn->query("SELECT Email FROM customers ORDER BY Customer_ID ASC LIMIT 1");
    $row = $result ? $result->fetch_assoc() : null;

    if ($row && !empty($row['Email'])) {
        $emails[] = strtolower(trim((string) $row['Email']));
    }

    return $emails;

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
        header("Location: login.php");
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
                        ORDER BY bp.id ASC
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
        'other' => 'building-2',
    ];

    return $icons[$type] ?? 'building-2';

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
    $sql = "SELECT image_url FROM business_photos WHERE business_id = ? ORDER BY id ASC LIMIT 1";
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

    $conn = db_connect();
    $sql = "SELECT location_id, business_id, location_name, address, phone, capacity_per_hour, has_reservations
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
        $locations[] = $row;
    }

    return $locations;

}


/* -------------------------
   LOCATION HOURS MAP
------------------------- */
function get_location_hours_map($location_id) {

    $location_id = (int) $location_id;

    if ($location_id <= 0) {
        return [];
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

    return $hours;

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

    return $hoursMap[$dayOfWeek] ?? null;

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
    $sql = "SELECT b.business_id,
                   b.partner_id,
                   b.name,
                   b.description,
                   b.rules,
                   b.type,
                   b.custom_type,
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
                        ORDER BY bp.id ASC
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
    $sql = "SELECT b.business_id, b.partner_id, b.name, b.description, b.rules, b.type, b.custom_type,
                   b.logo_url, b.website, b.approval_status, b.review_note, b.reviewed_at, b.created_at,
                   COALESCE(
                       (SELECT bp.image_url
                        FROM business_photos bp
                        WHERE bp.business_id = b.business_id
                        ORDER BY bp.id ASC
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
        ];
    }

    $conn = db_connect();
    $summary = [
        'business_count' => 0,
        'view_count' => 0,
        'reservation_count' => 0,
        'upcoming_reservation_count' => 0,
        'active_offer_count' => 0,
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
                                           AND bk.status IN ('pending', 'confirmed')
                                           AND bk.date >= CURDATE()",
        'active_offer_count' => "SELECT COUNT(*) AS value
                                 FROM business_offers bo
                                 INNER JOIN businesses b ON b.business_id = bo.business_id
                                 WHERE b.partner_id = ?
                                   AND bo.is_active = 1
                                   AND (bo.start_date IS NULL OR bo.start_date <= CURDATE())
                                   AND (bo.end_date IS NULL OR bo.end_date >= CURDATE())",
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
              AND bk.status IN ('pending', 'confirmed')
              AND bk.date >= CURDATE()
            ORDER BY bk.date ASC, bk.time_slot ASC
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
    $sql = "SELECT b.business_id,
                   b.partner_id,
                   b.name,
                   b.description,
                   b.type,
                   b.custom_type,
                   b.website,
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
   BUSINESS PHOTOS
------------------------- */
function get_business_photos($business_id) {

    $business_id = (int) $business_id;

    if ($business_id <= 0) {
        return [];
    }

    $conn = db_connect();
    $sql = "SELECT id, business_id, image_url FROM business_photos WHERE business_id = ? ORDER BY id ASC";
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
            'open_time' => '',
            'close_time' => '',
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
            'capacity_per_hour' => (int) ($legacyData['capacity_per_hour'] ?? 10),
            'has_reservations' => !empty($legacyData['has_reservations']) ? 1 : 0,
            'hours' => $legacyData['hours'] ?? [],
        ]];
    }

    $normalized = [];

    foreach ($locations as $location) {
        $location = is_array($location) ? $location : [];
        $locationName = trim((string) ($location['location_name'] ?? ''));
        $address = trim((string) ($location['address'] ?? ''));
        $phone = trim((string) ($location['phone'] ?? ''));

        if ($locationName === '' && $address === '' && $phone === '') {
            continue;
        }

        $normalized[] = [
            'location_id' => (int) ($location['location_id'] ?? 0),
            'location_name' => $locationName,
            'address' => $address,
            'phone' => $phone,
            'capacity_per_hour' => max(1, (int) ($location['capacity_per_hour'] ?? 10)),
            'has_reservations' => !empty($location['has_reservations']) ? 1 : 0,
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
            'logo_url' => '',
            'website' => '',
            'approval_status' => 'pending',
            'review_note' => '',
            'reviewed_at' => null,
        ],
        'locations' => [[
            'location_id' => 0,
            'location_name' => '',
            'address' => '',
            'phone' => '',
            'capacity_per_hour' => 10,
            'has_reservations' => 1,
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
function normalize_url_input_list($values) {

    $values = is_array($values) ? $values : [];
    $normalized = [];

    foreach ($values as $value) {
        $value = trim((string) $value);

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
    $logoUrl = trim((string) ($data['logo_url'] ?? ''));
    $website = trim((string) ($data['website'] ?? ''));
    $locations = normalize_partner_locations_input($data['locations'] ?? [], $data);
    $photoUrls = normalize_url_input_list($data['photo_urls'] ?? []);
    $menuItems = is_array($data['menus'] ?? null) ? $data['menus'] : [];
    $offerItems = is_array($data['offers'] ?? null) ? $data['offers'] : [];
    $allowedTypes = ['restaurant', 'cafe', 'activity', 'entertainment', 'nightlife', 'other'];

    if ($name === '' || !$locations) {
        return ['ok' => false, 'message' => 'Business name and at least one location are required.'];
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

    $conn = db_connect();
    $currentBusiness = $business_id > 0 ? get_business_by_id($business_id) : null;
    $preserveApproval = $currentBusiness && trim((string) ($currentBusiness['approval_status'] ?? '')) === 'approved';

    try {
        $conn->begin_transaction();

        if ($business_id > 0) {
            $sql = "UPDATE businesses
                    SET name = ?, description = ?, rules = ?, type = ?, custom_type = ?, logo_url = ?, website = ?,
                        approval_status = " . ($preserveApproval ? "'approved'" : "'pending'") . ",
                        review_note = " . ($preserveApproval ? "review_note" : "NULL") . ",
                        reviewed_at = " . ($preserveApproval ? "reviewed_at" : "NULL") . "
                    WHERE business_id = ? AND partner_id = ?";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                throw new Exception('Business update could not be prepared.');
            }

            $stmt->bind_param("sssssssii", $name, $description, $rules, $type, $customType, $logoUrl, $website, $business_id, $partner_id);

            if (!$stmt->execute()) {
                throw new Exception('Business update failed.');
            }
        } else {
            $sql = "INSERT INTO businesses (partner_id, name, description, rules, type, custom_type, logo_url, website, approval_status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                throw new Exception('Business insert could not be prepared.');
            }

            $approvalStatus = 'pending';
            $stmt->bind_param("issssssss", $partner_id, $name, $description, $rules, $type, $customType, $logoUrl, $website, $approvalStatus);

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
            $capacityPerHour = max(1, (int) ($location['capacity_per_hour'] ?? 10));
            $hasReservations = !empty($location['has_reservations']) ? 1 : 0;

            if ($locationId > 0 && in_array($locationId, $existingLocationIds, true)) {
                $sql = "UPDATE business_locations
                        SET location_name = ?, address = ?, phone = ?, capacity_per_hour = ?, has_reservations = ?
                        WHERE location_id = ? AND business_id = ?";
                $stmt = $conn->prepare($sql);

                if (!$stmt) {
                    throw new Exception('Location update could not be prepared.');
                }

                $stmt->bind_param("sssiiii", $locationName, $address, $phone, $capacityPerHour, $hasReservations, $locationId, $business_id);

                if (!$stmt->execute()) {
                    throw new Exception('Location update failed.');
                }
            } else {
                $sql = "INSERT INTO business_locations (business_id, location_name, address, phone, capacity_per_hour, has_reservations)
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);

                if (!$stmt) {
                    throw new Exception('Location insert could not be prepared.');
                }

                $stmt->bind_param("isssii", $business_id, $locationName, $address, $phone, $capacityPerHour, $hasReservations);

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
            $insertPhotoStmt = $conn->prepare("INSERT INTO business_photos (business_id, image_url) VALUES (?, ?)");

            if (!$insertPhotoStmt) {
                throw new Exception('Business photo insert could not be prepared.');
            }

            foreach ($photoUrls as $photoUrl) {
                $insertPhotoStmt->bind_param("is", $business_id, $photoUrl);

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
            $menuUrl = trim((string) ($menuItem['file_url'] ?? ''));

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

    return $stmt->execute();

}


/* -------------------------
   PENDING BUSINESSES
------------------------- */
function get_pending_businesses($status = 'pending') {

    $status = trim((string) $status);
    $conn = db_connect();
    $sql = "SELECT b.business_id,
                   b.name,
                   b.description,
                   b.type,
                   b.custom_type,
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

    if ($location_id <= 0) {
        return null;
    }

    $conn = db_connect();
    $sql = "SELECT bl.location_id, bl.business_id, bl.location_name, bl.address, bl.phone, bl.capacity_per_hour, bl.has_reservations,
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

    return $result ? $result->fetch_assoc() : null;

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

    return $time >= $openTime && $time < $closeTime;

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
   SLOT AVAILABILITY
------------------------- */
function is_booking_slot_available($location_id, $date, $time, $guests = 1) {

    $location = get_location_by_id($location_id);
    $guests = max(1, (int) $guests);
    $requiredTables = get_required_table_count($guests);

    if (!$location || (int) ($location['has_reservations'] ?? 0) !== 1) {
        return false;
    }

    if (!is_location_open_for_booking($location_id, $date, $time)) {
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
    $hours = get_location_hours_for_date($location_id, $date);

    if (!$hours || (int) ($hours['is_closed'] ?? 0) === 1) {
        return [];
    }

    $openTime = trim((string) ($hours['open_time'] ?? ''));
    $closeTime = trim((string) ($hours['close_time'] ?? ''));

    if ($openTime === '' || $closeTime === '') {
        return [];
    }

    $slots = [];
    $current = strtotime($date . ' ' . $openTime);
    $end = strtotime($date . ' ' . $closeTime);

    if (!$current || !$end || $current >= $end) {
        return [];
    }

    while ($current < $end) {
        $time = date('H:i:s', $current);
        $slots[] = [
            'time' => $time,
            'available' => is_booking_slot_available($location_id, $date, $time, $guests),
        ];
        $current = strtotime('+' . $slot_minutes . ' minutes', $current);
    }

    return $slots;

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

    $calendar = [];

    for ($offset = 0; $offset < $days; $offset++) {
        $date = date('Y-m-d', strtotime('+' . $offset . ' day', $startTimestamp));
        $hours = get_location_hours_for_date($location_id, $date);
        $status = 'closed';
        $slots = [];

        if ($hours && (int) ($hours['is_closed'] ?? 0) !== 1) {
            $slots = get_available_booking_slots($location_id, $date, 60, $guests);
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

    if (!is_booking_slot_available($location_id, $date, $time, $guests)) {
        return false;
    }

    $customer = get_customer_by_id($customer_id);
    $fallbackName = trim(($customer['First_N'] ?? '') . ' ' . ($customer['Last_N'] ?? ''));
    $user_name = trim((string) ($user_name !== null ? $user_name : $fallbackName));
    $user_email = trim((string) ($user_email !== null ? $user_email : ($customer['Email'] ?? '')));
    $status = "pending";
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

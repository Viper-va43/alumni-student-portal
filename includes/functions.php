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
function get_visited_place_ids() {

    start_session();

    if (is_logged_in()) {
        $customer_id = (int) ($_SESSION['customer_id'] ?? 0);

        if ($customer_id > 0) {
            ensure_customer_place_visits_table();

            $conn = db_connect();
            $sql = "SELECT Place_ID FROM customer_place_visits WHERE Customer_ID = ? ORDER BY Last_Visited_At DESC LIMIT 12";
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param("i", $customer_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $visited = [];

                while ($row = $result->fetch_assoc()) {
                    $visited[] = $row['Place_ID'];
                }

                $_SESSION['visited_places'] = $visited;

                return $visited;
            }
        }
    }

    $visited = $_SESSION['visited_places'] ?? [];

    return is_array($visited) ? array_values($visited) : [];

}


/* -------------------------
   VISITED PLACE ENTRIES
------------------------- */
function get_visited_places() {

    start_session();

    $places = [];

    if (!is_logged_in()) {
        return $places;
    }

    $customer_id = (int) ($_SESSION['customer_id'] ?? 0);

    if ($customer_id <= 0) {
        return $places;
    }

    ensure_customer_place_visits_table();

    $conn = db_connect();
    $sql = "SELECT Place_ID, Place_Source, Place_Payload, Last_Visited_At
            FROM customer_place_visits
            WHERE Customer_ID = ?
            ORDER BY Last_Visited_At DESC
            LIMIT 12";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return $places;
    }

    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $source = $row['Place_Source'] ?: 'catalog';
        $payload = [];

        if (!empty($row['Place_Payload'])) {
            $decoded = json_decode($row['Place_Payload'], true);

            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        if ($source === 'google') {
            $places[] = normalize_saved_place_payload($row['Place_ID'], $source, $payload);
            continue;
        }

        $catalogPlace = get_place_by_id($row['Place_ID']);

        if ($catalogPlace) {
            $places[] = normalize_saved_place_payload($row['Place_ID'], 'catalog', array_merge($catalogPlace, $payload));
        }
    }

    return $places;

}


/* -------------------------
   RECORD PLACE VISIT
------------------------- */
function record_place_visit($place_id, $source = 'catalog', $payload = []) {

    start_session();

    $place_id = trim($place_id);
    $source = trim($source) !== '' ? trim($source) : 'catalog';

    if ($place_id === '') {
        return;
    }

    $normalizedPayload = normalize_saved_place_payload($place_id, $source, is_array($payload) ? $payload : []);
    $payloadJson = json_encode($normalizedPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (is_logged_in()) {
        $customer_id = (int) ($_SESSION['customer_id'] ?? 0);

        if ($customer_id > 0) {
            ensure_customer_place_visits_table();

            $conn = db_connect();
            $sql = "INSERT INTO customer_place_visits (Customer_ID, Place_ID, Place_Source, Place_Payload, Last_Visited_At, Created_At)
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE Place_Source = VALUES(Place_Source), Place_Payload = VALUES(Place_Payload), Last_Visited_At = NOW()";
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param("isss", $customer_id, $place_id, $source, $payloadJson);
                $stmt->execute();
            }
        }
    }

    $visited = $_SESSION['visited_places'] ?? [];
    $visited = is_array($visited) ? $visited : [];
    $visited = array_values(array_unique(array_merge([$place_id], $visited)));
    $_SESSION['visited_places'] = array_slice($visited, 0, 12);
    $_SESSION['visited_place_payloads'][$place_id] = $normalizedPayload;

}


/* -------------------------
   REMOVE PLACE VISIT
------------------------- */
function remove_place_visit($place_id) {

    start_session();

    $place_id = trim($place_id);

    if ($place_id === '') {
        return;
    }

    if (is_logged_in()) {
        $customer_id = (int) ($_SESSION['customer_id'] ?? 0);

        if ($customer_id > 0) {
            ensure_customer_place_visits_table();

            $conn = db_connect();
            $sql = "DELETE FROM customer_place_visits WHERE Customer_ID = ? AND Place_ID = ?";
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param("is", $customer_id, $place_id);
                $stmt->execute();
            }
        }
    }

    $visited = $_SESSION['visited_places'] ?? [];
    $visited = is_array($visited) ? $visited : [];
    $visited = array_values(array_filter($visited, function ($visited_place_id) use ($place_id) {
        return $visited_place_id !== $place_id;
    }));

    $_SESSION['visited_places'] = $visited;

    if (isset($_SESSION['visited_place_payloads']) && is_array($_SESSION['visited_place_payloads'])) {
        unset($_SESSION['visited_place_payloads'][$place_id]);
    }

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
                Visit_ID INT AUTO_INCREMENT PRIMARY KEY,
                Customer_ID INT NOT NULL,
                Place_ID VARCHAR(120) NOT NULL,
                Place_Source VARCHAR(20) NOT NULL DEFAULT 'catalog',
                Place_Payload LONGTEXT NULL,
                Last_Visited_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                Created_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_customer_place (Customer_ID, Place_ID),
                KEY idx_customer_recent (Customer_ID, Last_Visited_At)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql);
    ensure_customer_place_visits_column($conn, 'Place_Source', "ALTER TABLE customer_place_visits ADD COLUMN Place_Source VARCHAR(20) NOT NULL DEFAULT 'catalog' AFTER Place_ID");
    ensure_customer_place_visits_column($conn, 'Place_Payload', "ALTER TABLE customer_place_visits ADD COLUMN Place_Payload LONGTEXT NULL AFTER Place_Source");
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
   CREATE BOOKING
------------------------- */
function create_booking($customer_id, $business_id, $date, $time) {

    $conn = db_connect();

    $status = "Pending";

    $sql = "INSERT INTO bookings 
            (Booking_Date, Booking_Time, Status, Customer_ID, Business_ID)
            VALUES (?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param("sssii", $date, $time, $status, $customer_id, $business_id);

    return $stmt->execute();

}


/* -------------------------
   GET CUSTOMER BOOKINGS
------------------------- */
function get_customer_bookings($customer_id) {

    $conn = db_connect();

    $sql = "SELECT * FROM bookings WHERE Customer_ID = ?";
    $stmt = $conn->prepare($sql);

    $stmt->bind_param("i", $customer_id);
    $stmt->execute();

    return $stmt->get_result();

}


/* -------------------------
   SAFE REDIRECT
------------------------- */
function redirect($url) {

    header("Location: $url");
    exit();

}

?>

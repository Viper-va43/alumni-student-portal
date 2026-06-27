<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/cache.php';

date_default_timezone_set(getenv('WHERE2GO_TIMEZONE') ?: 'Africa/Cairo');

where2go_mobile_security_headers('GET, POST, OPTIONS');

function where2go_mobile_json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode((string) $raw, true);

    return is_array($data) ? $data : $_POST;
}

function where2go_mobile_customer_id(array $data = []): int
{
    $customerId = (int) ($data['customer_id'] ?? ($_GET['customer_id'] ?? 0));

    return $customerId > 0 ? $customerId : 0;
}

function where2go_mobile_time_label(string $time): string
{
    $timestamp = strtotime($time);

    return $timestamp ? date('g:i A', $timestamp) : $time;
}

function where2go_mobile_guest_limit(array $location): int
{
    return function_exists('get_location_guest_limit') ? get_location_guest_limit($location) : max(4, max(1, (int) ($location['capacity_per_hour'] ?? 1)) * 4);
}

function where2go_mobile_booking_payload(array $booking): array
{
    return [
        'id' => (int) ($booking['id'] ?? 0),
        'locationId' => (int) ($booking['location_id'] ?? 0),
        'businessId' => (int) ($booking['business_id'] ?? 0),
        'businessName' => (string) ($booking['business_name'] ?? 'Where2Go place'),
        'category' => (string) ($booking['business_type_label'] ?? 'Place'),
        'address' => (string) ($booking['location_address'] ?? ''),
        'phone' => (string) ($booking['location_phone'] ?? ''),
        'date' => (string) ($booking['date'] ?? ''),
        'time' => (string) ($booking['time_slot'] ?? ''),
        'timeLabel' => where2go_mobile_time_label((string) ($booking['time_slot'] ?? '')),
        'guests' => (int) ($booking['guests'] ?? 1),
        'status' => (string) ($booking['status'] ?? 'pending'),
        'createdAt' => (string) ($booking['created_at'] ?? ''),
    ];
}

function where2go_mobile_booking_delivery(int $customerId, int $locationId, string $date, string $time): ?array
{
    $conn = db_connect();
    $time = normalize_booking_time_slot($time);
    $sql = "SELECT bk.id,
                   bk.status,
                   b.business_id,
                   b.name AS business_name,
                   b.partner_id,
                   p.email AS partner_email
            FROM bookings bk
            INNER JOIN business_locations bl ON bl.location_id = bk.location_id
            INNER JOIN businesses b ON b.business_id = bl.business_id
            INNER JOIN partners p ON p.partner_id = b.partner_id
            WHERE bk.customer_id = ?
              AND bk.location_id = ?
              AND bk.date = ?
              AND bk.time_slot = ?
            ORDER BY bk.id DESC
            LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('iiss', $customerId, $locationId, $date, $time);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'bookingId' => (int) ($row['id'] ?? 0),
        'status' => (string) ($row['status'] ?? 'pending'),
        'businessId' => (int) ($row['business_id'] ?? 0),
        'businessName' => (string) ($row['business_name'] ?? 'Where2Go business'),
        'partnerId' => (int) ($row['partner_id'] ?? 0),
        'partnerEmail' => (string) ($row['partner_email'] ?? ''),
        'visibleToBusiness' => true,
    ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = trim((string) ($_GET['action'] ?? 'availability'));

if ($method === 'GET' && $action === 'bookings') {
    $customerId = where2go_mobile_customer_id();
    $auth = where2go_mobile_security_require_customer($customerId > 0 ? $customerId : null);
    $customerId = (int) $auth['customerId'];
    $bookings = array_map('where2go_mobile_booking_payload', get_customer_bookings($customerId));

    echo json_encode([
        'ok' => true,
        'bookings' => $bookings,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'GET') {
    $locationId = (int) ($_GET['location_id'] ?? 0);
    $guests = max(1, min(80, (int) ($_GET['guests'] ?? 1)));
    $selectedDate = trim((string) ($_GET['date'] ?? ''));
    $cacheKey = where2go_mobile_cache_key('availability', [
        'locationId' => $locationId,
        'guests' => $guests,
        'selectedDate' => $selectedDate,
        'today' => date('Y-m-d'),
    ]);
    $cachedPayload = where2go_mobile_cache_get($cacheKey, 20);

    if ($cachedPayload) {
        where2go_mobile_cache_reply($cachedPayload);
    }

    $location = get_location_by_id($locationId);

    if (!$location || (int) ($location['has_reservations'] ?? 0) !== 1) {
        http_response_code(404);
        echo json_encode([
            'ok' => false,
            'message' => 'Reservations are not available for this location.',
        ]);
        exit;
    }

    $guestLimit = where2go_mobile_guest_limit($location);
    $guestMinimum = function_exists('get_location_min_party_size') ? get_location_min_party_size($location) : 1;
    $guests = min($guests, $guestLimit);
    $guests = max($guests, $guestMinimum);
    $calendar = get_location_booking_calendar_days($locationId, date('Y-m-d'), 14, $guests);

    if ($selectedDate === '') {
        foreach ($calendar as $day) {
            if (($day['status'] ?? '') === 'available') {
                $selectedDate = (string) $day['date'];
                break;
            }
        }
    }

    if ($selectedDate === '') {
        $selectedDate = date('Y-m-d');
    }

    $slots = array_map(function ($slot) {
        $time = (string) ($slot['time'] ?? '');

        return [
            'time' => $time,
            'label' => where2go_mobile_time_label($time),
            'available' => !empty($slot['available']),
        ];
    }, get_available_booking_slots($locationId, $selectedDate, 60, $guests));

    $calendarPayload = array_map(function ($day) {
        return [
            'date' => (string) ($day['date'] ?? ''),
            'status' => (string) ($day['status'] ?? 'closed'),
        ];
    }, $calendar);

    $payload = [
        'ok' => true,
        'location' => [
            'id' => (int) ($location['location_id'] ?? 0),
            'businessId' => (int) ($location['business_id'] ?? 0),
            'name' => trim((string) ($location['location_name'] ?: $location['business_name'])),
            'address' => (string) ($location['address'] ?? ''),
            'phone' => (string) ($location['phone'] ?? ''),
            'guestMinimum' => $guestMinimum,
            'guestLimit' => $guestLimit,
        ],
        'selectedDate' => $selectedDate,
        'guests' => $guests,
        'calendar' => $calendarPayload,
        'slots' => $slots,
    ];

    where2go_mobile_cache_set($cacheKey, $payload);
    where2go_mobile_cache_reply($payload, 'MISS');
    exit;
}

if ($method === 'POST') {
    $data = where2go_mobile_json_input();
    $postAction = trim((string) ($data['action'] ?? 'create'));
    $customerId = where2go_mobile_customer_id($data);
    $locationId = (int) ($data['location_id'] ?? 0);
    $date = trim((string) ($data['date'] ?? ''));
    $time = trim((string) ($data['time_slot'] ?? ''));
    $guests = max(1, min(80, (int) ($data['guests'] ?? 1)));
    $auth = where2go_mobile_security_require_customer($customerId > 0 ? $customerId : null);
    $customerId = (int) $auth['customerId'];

    if ($postAction === 'cancel') {
        $bookingId = (int) ($data['booking_id'] ?? 0);

        if ($bookingId <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'A valid reservation is required.']);
            exit;
        }

        $conn = db_connect();
        $stmt = $conn->prepare("UPDATE bookings
                SET status = 'canceled'
                WHERE id = ?
                  AND customer_id = ?
                  AND status IN ('pending', 'confirmed')");

        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Reservation could not be canceled right now.']);
            exit;
        }

        $stmt->bind_param('ii', $bookingId, $customerId);
        $stmt->execute();

        if ($stmt->affected_rows < 1) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'message' => 'This reservation cannot be canceled.']);
            exit;
        }

        where2go_mobile_cache_clear_namespace('availability');
        $bookings = get_customer_bookings($customerId);

        echo json_encode([
            'ok' => true,
            'message' => 'Reservation canceled.',
            'bookings' => array_map('where2go_mobile_booking_payload', $bookings),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $location = get_location_by_id($locationId);

    if ($location) {
        $guests = min($guests, where2go_mobile_guest_limit($location));
        $guests = max($guests, function_exists('get_location_min_party_size') ? get_location_min_party_size($location) : 1);
    }

    if (!create_booking($customerId, $locationId, $date, $time, $guests)) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => 'That time is no longer available. Pick another slot.']);
        exit;
    }

    where2go_mobile_cache_clear_namespace('availability');
    $delivery = where2go_mobile_booking_delivery($customerId, $locationId, $date, $time);

    if (!$delivery || empty($delivery['visibleToBusiness'])) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'The reservation was created but could not be linked to the business dashboard.']);
        exit;
    }

    $bookings = get_customer_bookings($customerId);
    $created = $bookings[0] ?? [];

    echo json_encode([
        'ok' => true,
        'message' => 'Reservation pending. The business can now see it in their dashboard.',
        'delivery' => $delivery,
        'booking' => where2go_mobile_booking_payload($created),
        'bookings' => array_map('where2go_mobile_booking_payload', $bookings),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'message' => 'Unsupported request method.']);

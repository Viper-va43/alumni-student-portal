<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/place_data.php';

$conn = db_connect();
ensure_where2go_rewards_schema();

$password = trim((string) getenv('WHERE2GO_SEED_PARTNER_PASSWORD'));

if ($password === '') {
    $password = 'W2G-' . bin2hex(random_bytes(6)) . '-Aa!';
}

$passwordHash = hash_password($password);
$credentials = [];
$places = get_place_catalog();

function seed_partner_email_for_place($placeId) {
    $slug = strtolower(trim((string) $placeId));
    $slug = preg_replace('/[^a-z0-9]+/', '.', $slug);
    $slug = trim((string) $slug, '.');

    return 'partner.' . $slug . '@where2go-partners.com';
}

function seed_business_type_for_category($category, $catalog = '') {
    $catalog = get_place_catalog_slug($catalog);

    if (in_array($catalog, ['restaurant', 'cafe', 'activity', 'entertainment', 'nightlife', 'heritage'], true)) {
        return $catalog;
    }

    $category = strtolower(trim((string) $category));

    if (str_contains($category, 'restaurant')) {
        return 'restaurant';
    }

    if (str_contains($category, 'cafe') || str_contains($category, 'relaxed')) {
        return 'cafe';
    }

    if (str_contains($category, 'night')) {
        return 'nightlife';
    }

    if (str_contains($category, 'activity') || str_contains($category, 'fun')) {
        return 'activity';
    }

    if (str_contains($category, 'heritage') || str_contains($category, 'museum') || str_contains($category, 'market') || str_contains($category, 'viewpoint')) {
        return 'heritage';
    }

    if (str_contains($category, 'entertainment')) {
        return 'entertainment';
    }

    return 'other';
}

function seed_get_partner_id($conn, $ownerName, $email, $passwordHash) {
    $stmt = $conn->prepare("SELECT partner_id FROM partners WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    if ($row) {
        $partnerId = (int) $row['partner_id'];
        $update = $conn->prepare("UPDATE partners SET owner_name = ?, password = ? WHERE partner_id = ?");
        $update->bind_param("ssi", $ownerName, $passwordHash, $partnerId);
        $update->execute();

        return $partnerId;
    }

    $insert = $conn->prepare("INSERT INTO partners (owner_name, email, password, created_at) VALUES (?, ?, ?, NOW())");
    $insert->bind_param("sss", $ownerName, $email, $passwordHash);
    $insert->execute();

    return (int) $conn->insert_id;
}

function seed_get_business_id($conn, $partnerId, $place, $logoUrl) {
    $name = trim((string) ($place['name'] ?? 'Where2Go place'));
    $description = trim((string) ($place['description'] ?? 'Listed on Where2Go.'));
    $type = seed_business_type_for_category($place['category'] ?? '', $place['catalog'] ?? '');
    $customType = $type === 'other' ? trim((string) ($place['category'] ?? 'Place')) : '';

    $stmt = $conn->prepare("SELECT business_id FROM businesses WHERE partner_id = ? AND name = ? LIMIT 1");
    $stmt->bind_param("is", $partnerId, $name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    if ($row) {
        $businessId = (int) $row['business_id'];
        $update = $conn->prepare("UPDATE businesses
                SET description = ?, rules = '', type = ?, custom_type = ?, logo_url = ?, website = '',
                    approval_status = 'approved', review_note = NULL, reviewed_at = NOW()
                WHERE business_id = ?");
        $update->bind_param("ssssi", $description, $type, $customType, $logoUrl, $businessId);
        $update->execute();

        return $businessId;
    }

    $insert = $conn->prepare("INSERT INTO businesses
            (partner_id, name, description, rules, type, custom_type, logo_url, website, approval_status, review_note, reviewed_at, created_at)
            VALUES (?, ?, ?, '', ?, ?, ?, '', 'approved', NULL, NOW(), NOW())");
    $insert->bind_param("isssss", $partnerId, $name, $description, $type, $customType, $logoUrl);
    $insert->execute();

    return (int) $conn->insert_id;
}

function seed_location_for_business($conn, $businessId, $place, $phone, $index) {
    $locationName = trim((string) ($place['name'] ?? 'Main location'));
    $address = trim(implode(', ', array_filter([
        trim((string) ($place['area'] ?? '')),
        trim((string) ($place['city'] ?? '')),
        'Egypt',
    ])));
    $capacity = max(10, 12 + ($index % 5));

    $stmt = $conn->prepare("SELECT location_id FROM business_locations WHERE business_id = ? ORDER BY location_id ASC LIMIT 1");
    $stmt->bind_param("i", $businessId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    if ($row) {
        $locationId = (int) $row['location_id'];
        $update = $conn->prepare("UPDATE business_locations
                SET location_name = ?, address = ?, phone = ?, capacity_per_hour = ?, has_reservations = 1, checkin_enabled = 0
                WHERE location_id = ?");
        $update->bind_param("sssii", $locationName, $address, $phone, $capacity, $locationId);
        $update->execute();
    } else {
        $locationId = 0;
        $qrToken = generate_unique_location_qr_token($conn);
        $insert = $conn->prepare("INSERT INTO business_locations
                (business_id, location_name, address, phone, promo_code, promo_details, qr_token, capacity_per_hour, has_reservations, checkin_enabled)
                VALUES (?, ?, ?, ?, NULL, NULL, ?, ?, 1, 0)");
        $insert->bind_param("issssi", $businessId, $locationName, $address, $phone, $qrToken, $capacity);
        $insert->execute();
        $locationId = (int) $conn->insert_id;
    }

    $deleteHours = $conn->prepare("DELETE FROM business_hours WHERE location_id = ?");
    $deleteHours->bind_param("i", $locationId);
    $deleteHours->execute();

    $openTime = '10:00:00';
    $closeTime = '23:00:00';
    $isClosed = 0;
    $insertHours = $conn->prepare("INSERT INTO business_hours (location_id, day_of_week, is_closed, open_time, close_time) VALUES (?, ?, ?, ?, ?)");

    for ($day = 0; $day <= 6; $day++) {
        $insertHours->bind_param("iiiss", $locationId, $day, $isClosed, $openTime, $closeTime);
        $insertHours->execute();
    }

    return $locationId;
}

function seed_media_for_business($conn, $businessId, $mediaItems) {
    $delete = $conn->prepare("DELETE FROM business_photos WHERE business_id = ?");
    $delete->bind_param("i", $businessId);
    $delete->execute();

    $insert = $conn->prepare("INSERT INTO business_photos (business_id, image_url) VALUES (?, ?)");

    foreach ($mediaItems as $mediaItem) {
        $url = trim((string) ($mediaItem['url'] ?? ''));

        if ($url === '') {
            continue;
        }

        $insert->bind_param("is", $businessId, $url);
        $insert->execute();
    }
}

$conn->begin_transaction();

try {
    foreach ($places as $index => $place) {
        $placeId = trim((string) ($place['id'] ?? ''));

        if ($placeId === '') {
            continue;
        }

        $normalized = normalize_catalog_place_for_discovery($place);
        $mediaItems = is_array($normalized['media_items'] ?? null) ? $normalized['media_items'] : [];
        $logoUrl = get_first_place_image_url($mediaItems, $normalized['photo_url'] ?? '');
        $email = seed_partner_email_for_place($placeId);
        $ownerName = trim((string) ($place['name'] ?? 'Where2Go')) . ' Partner';
        $phone = '010' . str_pad((string) (70000000 + $index), 8, '0', STR_PAD_LEFT);

        $partnerId = seed_get_partner_id($conn, $ownerName, $email, $passwordHash);
        $businessId = seed_get_business_id($conn, $partnerId, $place, $logoUrl);
        $locationId = seed_location_for_business($conn, $businessId, $place, $phone, $index);
        seed_media_for_business($conn, $businessId, $mediaItems);

        $credentials[] = [
            'place' => trim((string) ($place['name'] ?? $placeId)),
            'email' => $email,
            'password' => $password,
            'phone' => $phone,
            'business_id' => $businessId,
            'location_id' => $locationId,
        ];
    }

    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    fwrite(STDERR, "Seeding failed: " . $error->getMessage() . PHP_EOL);
    exit(1);
}

echo "Seeded " . count($credentials) . " partner-style businesses." . PHP_EOL;
echo "Place | Email | Password | Phone | Business ID | Location ID" . PHP_EOL;

foreach ($credentials as $row) {
    echo $row['place'] . ' | ' . $row['email'] . ' | ' . $row['password'] . ' | ' . $row['phone'] . ' | ' . $row['business_id'] . ' | ' . $row['location_id'] . PHP_EOL;
}

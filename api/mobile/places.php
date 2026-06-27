<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/../../includes/place_data.php';

where2go_mobile_security_headers('GET, OPTIONS');

function where2go_mobile_base_url(): string
{
    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $isHttps = $forwardedProto === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $projectPath = preg_replace('#/api/mobile$#', '', $scriptDir);

    return rtrim($scheme . '://' . $host . $projectPath, '/');
}

function where2go_mobile_media_url(?string $path, string $baseUrl): string
{
    $path = trim((string) $path);

    if ($path === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    return $baseUrl . '/' . str_replace(' ', '%20', ltrim($path, '/'));
}

function where2go_mobile_place_payload(array $row, string $baseUrl): array
{
    $rawCategory = trim((string) (($row['custom_type'] ?? '') ?: ($row['type'] ?? '')));
    $category = $rawCategory !== '' ? ucfirst($rawCategory) : 'Place';
    $address = trim((string) ($row['address'] ?? ''));
    $area = trim((string) ($row['location_name'] ?? ''));

    if ($area === '' && $address !== '') {
        $addressParts = array_values(array_filter(array_map('trim', explode(',', $address))));
        $area = $addressParts[0] ?? '';
    }

    $theme = function_exists('get_business_theme_payload') ? get_business_theme_payload($row) : [];
    $coverImagePath = trim((string) ($theme['coverImageUrl'] ?? ''));
    $imagePath = trim((string) ($coverImagePath ?: (($row['photo_url'] ?? '') ?: ($row['logo_url'] ?? ''))));
    $searchTags = normalize_business_search_tags($row['search_tags'] ?? '');

    return [
        'id' => 'business-' . (int) ($row['business_id'] ?? 0),
        'source' => 'business',
        'businessId' => (int) ($row['business_id'] ?? 0),
        'locationId' => (int) ($row['location_id'] ?? 0),
        'name' => (string) ($row['name'] ?? 'Where2Go place'),
        'category' => $category,
        'area' => $area,
        'city' => 'Cairo',
        'description' => (string) (($row['description'] ?? '') ?: 'Approved place on Where2Go.'),
        'tags' => get_business_search_tag_list($searchTags),
        'searchTags' => $searchTags,
        'imageUrl' => where2go_mobile_media_url($imagePath, $baseUrl),
        'theme' => [
            'preset' => (string) ($theme['preset'] ?? 'where2go'),
            'label' => (string) ($theme['label'] ?? 'Where2Go default'),
            'accentColor' => (string) ($theme['accentColor'] ?? '#F26C1C'),
            'coverImageUrl' => where2go_mobile_media_url($coverImagePath, $baseUrl),
            'tagline' => (string) ($theme['tagline'] ?? ''),
        ],
        'address' => $address,
        'phone' => (string) ($row['phone'] ?? ''),
        'promoCode' => (string) ($row['promo_code'] ?? ''),
        'promoDetails' => (string) ($row['promo_details'] ?? ''),
        'websiteUrl' => (string) ($row['website'] ?? ''),
        'capacityPerHour' => (int) ($row['capacity_per_hour'] ?? 0),
        'priceRange' => '$$',
        'rating' => 'Live',
        'reservations' => (bool) ($row['has_reservations'] ?? false),
        'checkins' => (bool) ($row['checkin_enabled'] ?? false),
    ];
}

function where2go_mobile_hidden_place_name(string $name): bool
{
    return normalize_place_catalog_token($name) === 'adham';
}

function where2go_mobile_discovery_place_payload(array $place, string $baseUrl): array
{
    $source = trim((string) ($place['source'] ?? 'catalog'));
    $businessId = (int) ($place['business_id'] ?? 0);
    $locationId = (int) ($place['location_id'] ?? 0);
    $rawId = trim((string) ($place['id'] ?? ($place['place_id'] ?? '')));
    $id = $source === 'business' && $businessId > 0
        ? 'business-' . $businessId
        : 'catalog-' . ($rawId !== '' ? $rawId : normalize_place_catalog_token($place['name'] ?? 'place'));
    $theme = is_array($place['theme'] ?? null) ? $place['theme'] : [];
    $coverImagePath = trim((string) ($theme['coverImageUrl'] ?? ''));
    $imagePath = trim((string) (($place['hero_media_url'] ?? '') ?: (($place['photo_url'] ?? '') ?: $coverImagePath)));
    $searchTags = trim((string) (($place['search_tags'] ?? '') ?: ($place['search_blob'] ?? '')));
    $tagValues = array_merge(
        is_array($place['tags'] ?? null) ? $place['tags'] : [],
        [
            (string) ($place['catalog'] ?? ''),
            (string) ($place['catalog_label'] ?? ''),
            (string) ($place['category'] ?? ''),
        ]
    );
    $tags = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $tagValues)))));

    return [
        'id' => $id,
        'source' => $source,
        'businessId' => $businessId,
        'locationId' => $locationId,
        'name' => (string) ($place['name'] ?? 'Where2Go place'),
        'category' => (string) ($place['category'] ?? 'Place'),
        'area' => (string) (($place['area'] ?? '') ?: ($place['address'] ?? '')),
        'city' => (string) ($place['city'] ?? ''),
        'description' => (string) (($place['description'] ?? '') ?: 'Curated by Where2Go.'),
        'tags' => $tags,
        'searchTags' => $searchTags,
        'imageUrl' => where2go_mobile_media_url($imagePath, $baseUrl),
        'theme' => [
            'preset' => (string) ($theme['preset'] ?? 'where2go'),
            'label' => (string) ($theme['label'] ?? 'Where2Go default'),
            'accentColor' => (string) ($theme['accentColor'] ?? '#F26C1C'),
            'coverImageUrl' => where2go_mobile_media_url($coverImagePath, $baseUrl),
            'tagline' => (string) ($theme['tagline'] ?? ''),
        ],
        'address' => (string) ($place['address'] ?? ''),
        'phone' => '',
        'promoCode' => '',
        'promoDetails' => '',
        'websiteUrl' => (string) ($place['website_url'] ?? ''),
        'capacityPerHour' => 0,
        'priceRange' => (string) (($place['price_range'] ?? '') ?: '$$'),
        'rating' => (string) (($place['rating'] ?? '') ?: 'Featured'),
        'reservations' => false,
        'checkins' => false,
    ];
}

$conn = db_connect();
ensure_business_search_tags_schema($conn);
ensure_business_theme_schema($conn);
$baseUrl = where2go_mobile_base_url();
$today = normalize_top_pick_date();
$cacheKey = where2go_mobile_cache_key('places', ['baseUrl' => $baseUrl, 'date' => $today]);
$cachedPayload = where2go_mobile_cache_get($cacheKey, 60);

if ($cachedPayload) {
    where2go_mobile_cache_reply($cachedPayload);
}

ensure_business_photo_order_schema($conn);

$sql = "
    SELECT
        b.business_id,
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
        COALESCE(l.location_name, '') AS location_name,
        COALESCE(l.address, '') AS address,
        COALESCE(l.phone, '') AS phone,
        COALESCE(l.promo_code, '') AS promo_code,
        COALESCE(l.promo_details, '') AS promo_details,
        COALESCE(l.capacity_per_hour, 0) AS capacity_per_hour,
        COALESCE(l.has_reservations, 0) AS has_reservations,
        COALESCE(l.checkin_enabled, 0) AS checkin_enabled
    FROM businesses b
    LEFT JOIN (
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
    )
    WHERE b.approval_status = 'approved'
    ORDER BY b.business_id ASC
";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Unable to load places.',
    ]);
    exit;
}

$places = [];
$businessRowsById = [];

while ($row = $result->fetch_assoc()) {
    $businessRowsById[(int) ($row['business_id'] ?? 0)] = $row;
}

foreach (get_discovery_places('', null, '') as $place) {
    if (where2go_mobile_hidden_place_name((string) ($place['name'] ?? ''))) {
        continue;
    }

    $businessId = (int) ($place['business_id'] ?? 0);

    if (($place['source'] ?? '') === 'business' && $businessId > 0 && isset($businessRowsById[$businessId])) {
        $places[] = where2go_mobile_place_payload($businessRowsById[$businessId], $baseUrl);
        continue;
    }

    $places[] = where2go_mobile_discovery_place_payload($place, $baseUrl);
}

$topPicks = [];
$topPickBusinessIds = [];

foreach (get_top_pick_business_rows_for_app($today, 12) as $row) {
    if (where2go_mobile_hidden_place_name((string) ($row['name'] ?? ''))) {
        continue;
    }

    $topPick = where2go_mobile_place_payload($row, $baseUrl);
    $topPick['topPick'] = true;
    $topPick['topPickSource'] = (string) ($row['top_pick_source'] ?? 'automatic');
    $topPick['topPickPosition'] = (int) ($row['top_pick_position'] ?? 0);
    $topPicks[] = $topPick;
    $topPickBusinessIds[] = (int) ($topPick['businessId'] ?? 0);

    if (count($topPicks) >= 6) {
        break;
    }
}

if (count($topPicks) < 6) {
    foreach ($places as $place) {
        $businessId = (int) ($place['businessId'] ?? 0);

        if ($businessId > 0 && in_array($businessId, $topPickBusinessIds, true)) {
            continue;
        }

        $fallbackTopPick = $place;
        $fallbackTopPick['topPick'] = true;
        $fallbackTopPick['topPickSource'] = 'fallback';
        $fallbackTopPick['topPickPosition'] = count($topPicks) + 1;
        $topPicks[] = $fallbackTopPick;

        if ($businessId > 0) {
            $topPickBusinessIds[] = $businessId;
        }

        if (count($topPicks) >= 6) {
            break;
        }
    }
}

$payload = [
    'ok' => true,
    'places' => $places,
    'topPicks' => $topPicks,
    'meta' => [
        'count' => count($places),
        'topPickCount' => count($topPicks),
        'topPickDate' => $today,
        'baseUrl' => $baseUrl,
    ],
];

where2go_mobile_cache_set($cacheKey, $payload);
where2go_mobile_cache_reply($payload, 'MISS');

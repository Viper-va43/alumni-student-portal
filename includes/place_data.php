<?php

function get_place_catalog() {
    return [
        [
            'id' => 'hadramout-antar',
            'name' => 'Hadramout Antar',
            'category' => 'Restaurants',
            'area' => 'Banks Complex, Fifth Settlement',
            'city' => 'New Cairo',
            'description' => 'A well-known mandi and grill stop in Fifth Settlement for hearty Arabic meals and group dinners.',
            'price_range' => '$$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'utensils-crossed',
            'query' => 'Hadramout Antar Banks Complex Fifth Settlement New Cairo Egypt',
            'lat' => 30.025,
            'lng' => 31.491,
        ],
        [
            'id' => 'garden-8',
            'name' => 'Garden 8',
            'category' => 'Entertainment',
            'area' => 'La Nuova Vista, First Settlement',
            'city' => 'New Cairo',
            'description' => 'A polished community mall with restaurants, cafes, and open-air hangout energy in the heart of New Cairo.',
            'price_range' => '$$$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'tree-pine',
            'query' => 'Garden 8 La Nuova Vista New Cairo Egypt',
            'lat' => 30.072,
            'lng' => 31.476,
        ],
        [
            'id' => '5a-waterway',
            'name' => '5A by The Waterway',
            'category' => 'Nightlife',
            'area' => 'Fifth Settlement',
            'city' => 'New Cairo',
            'description' => 'A sleek dining and commercial destination with upscale restaurants, lifestyle brands, and evening plans in one cluster.',
            'price_range' => '$$$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'sparkles',
            'query' => '5A by The Waterway Fifth Settlement New Cairo Egypt',
            'lat' => 30.013,
            'lng' => 31.431,
        ],
        [
            'id' => 'point-90-mall',
            'name' => 'Point 90 Mall',
            'category' => 'Entertainment',
            'area' => 'In front of AUC, Fifth Settlement',
            'city' => 'New Cairo',
            'description' => 'A major New Cairo mall known for shopping, dining, cinema, and easy meet-up plans right by AUC.',
            'price_range' => '$$$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'shopping-bag',
            'query' => 'Point 90 Mall American University Fifth Settlement New Cairo Egypt',
            'lat' => 30.028,
            'lng' => 31.492,
        ],
        [
            'id' => 'o1-mall',
            'name' => 'O1 Mall',
            'category' => 'Restaurants',
            'area' => 'Mohammed Naguib Axis',
            'city' => 'New Cairo',
            'description' => 'An upscale New Cairo stop built around restaurants, cafes, and polished everyday hangout options.',
            'price_range' => '$$$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'store',
            'query' => 'O1 Mall Mohammed Naguib Axis New Cairo Egypt',
            'lat' => 30.048,
            'lng' => 31.492,
        ],
        [
            'id' => 'lake-town',
            'name' => 'Lake Town Mall',
            'category' => 'Fun Spots',
            'area' => 'New Cairo',
            'city' => 'Cairo',
            'description' => 'A large mixed-use mall in New Cairo with a more spacious plaza feel for casual outings and multiple stops in one trip.',
            'price_range' => '$$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'landmark',
            'query' => 'Lake Town Mall New Cairo Egypt',
            'lat' => 30.041,
            'lng' => 31.508,
        ],
        [
            'id' => 'the-drive',
            'name' => 'The Drive',
            'category' => 'Nightlife',
            'area' => 'North 90 Street',
            'city' => 'New Cairo',
            'description' => 'A high-energy lifestyle destination by Waterway Developments with dining, retail, and a more polished night-out atmosphere.',
            'price_range' => '$$$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'car-front',
            'query' => 'The Drive by Waterway Developments North 90 Street New Cairo Egypt',
            'lat' => 30.043,
            'lng' => 31.506,
        ],
        [
            'id' => '354-club',
            'name' => 'The 354 Club',
            'category' => 'Fun Spots',
            'area' => 'New Cairo 1',
            'city' => 'Cairo',
            'description' => 'A gaming lounge pick for competitive hangouts, console sessions, and a more casual indoor social plan.',
            'price_range' => '$$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'gamepad-2',
            'query' => 'The 354 Club Gaming Lounge New Cairo Egypt',
            'lat' => 30.033,
            'lng' => 31.471,
        ],
        [
            'id' => 'the-waterway',
            'name' => 'The Waterway',
            'category' => 'Relaxed',
            'area' => 'North Teseen',
            'city' => 'New Cairo',
            'description' => 'A stylish dining and leisure strip around The Waterway area, good for slower plans, coffee, and evening walks.',
            'price_range' => '$$$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'waves',
            'query' => 'The Waterway 2 North Teseen New Cairo Egypt',
            'lat' => 30.030,
            'lng' => 31.503,
        ],
        [
            'id' => 'fuel-up',
            'name' => 'Fuel Up',
            'category' => 'Relaxed',
            'area' => 'Next to Police Academy, First Settlement',
            'city' => 'New Cairo',
            'description' => 'A quick-stop New Cairo spot that mixes daily convenience with cafe-style stops and easy casual breaks.',
            'price_range' => '$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'fuel',
            'query' => 'Fuel Up next to Police Academy First Settlement New Cairo Egypt',
            'lat' => 30.063,
            'lng' => 31.443,
        ],
    ];
}

function get_place_by_id($placeId) {
    foreach (get_place_catalog() as $place) {
        if ($place['id'] === $placeId) {
            return $place;
        }
    }

    return null;
}

function get_places_by_ids($placeIds) {
    $places = [];

    foreach ($placeIds as $placeId) {
        $place = get_place_by_id($placeId);

        if ($place) {
            $places[] = $place;
        }
    }

    return $places;
}

function get_suggested_places($visitedIds = [], $limit = 4) {
    $visitedLookup = array_flip($visitedIds);
    $suggestions = [];

    foreach (get_discovery_places() as $place) {
        if (!isset($visitedLookup[$place['id']])) {
            $suggestions[] = $place;
        }
    }

    return array_slice($suggestions, 0, $limit);
}

function normalize_catalog_place_for_discovery($place) {
    $place = is_array($place) ? $place : [];
    $address = trim(implode(', ', array_filter([
        trim((string) ($place['area'] ?? '')),
        trim((string) ($place['city'] ?? '')),
    ])));

    return [
        'id' => (string) ($place['id'] ?? ''),
        'source' => 'catalog',
        'place_id' => (string) ($place['id'] ?? ''),
        'business_id' => null,
        'location_id' => null,
        'name' => trim((string) ($place['name'] ?? 'Where2Go place')),
        'category' => trim((string) ($place['category'] ?? 'Featured place')),
        'area' => trim((string) ($place['area'] ?? '')),
        'city' => trim((string) ($place['city'] ?? '')),
        'address' => $address,
        'description' => trim((string) ($place['description'] ?? 'Curated by Where2Go.')),
        'price_range' => trim((string) ($place['price_range'] ?? '$$')),
        'rating' => trim((string) ($place['rating'] ?? 'Featured')),
        'reviews' => (int) ($place['reviews'] ?? 0),
        'icon' => trim((string) ($place['icon'] ?? 'map-pinned')),
        'photo_url' => trim((string) ($place['photo_url'] ?? '')),
        'photo_attribution' => trim((string) ($place['photo_attribution'] ?? '')),
        'website_url' => trim((string) ($place['website_url'] ?? '')),
        'offer_title' => '',
        'has_offer' => false,
        'detail_url' => 'place.php?catalog_id=' . rawurlencode((string) ($place['id'] ?? '')),
        'search_blob' => strtolower(trim(implode(' ', array_filter([
            $place['name'] ?? '',
            $place['category'] ?? '',
            $place['area'] ?? '',
            $place['city'] ?? '',
            $place['description'] ?? '',
        ])))),
    ];
}

function normalize_public_business_for_discovery($business) {
    $business = is_array($business) ? $business : [];
    $businessId = (int) ($business['business_id'] ?? 0);
    $address = trim((string) ($business['primary_address'] ?? ''));
    $offerTitle = trim((string) ($business['active_offer_title'] ?? ''));
    $descriptionParts = [];

    if ($offerTitle !== '') {
        $descriptionParts[] = 'Offer live: ' . $offerTitle . '.';
    }

    if (trim((string) ($business['description'] ?? '')) !== '') {
        $descriptionParts[] = trim((string) $business['description']);
    }

    $ratingValue = $business['average_rating'] !== null
        ? number_format((float) $business['average_rating'], 1)
        : 'New';

    return [
        'id' => $businessId > 0 ? (string) $businessId : '',
        'source' => 'business',
        'place_id' => $businessId > 0 ? (string) $businessId : '',
        'business_id' => $businessId > 0 ? $businessId : null,
        'location_id' => !empty($business['primary_location_id']) ? (int) $business['primary_location_id'] : null,
        'name' => trim((string) ($business['name'] ?? 'Where2Go business')),
        'category' => trim((string) ($business['type_label'] ?? 'Business')),
        'area' => $address,
        'city' => '',
        'address' => $address,
        'description' => trim(implode(' ', $descriptionParts)) !== '' ? trim(implode(' ', $descriptionParts)) : 'Approved business on Where2Go.',
        'price_range' => $offerTitle !== '' ? 'Offer live' : 'See details',
        'rating' => $ratingValue,
        'reviews' => (int) ($business['review_count'] ?? 0),
        'icon' => trim((string) ($business['icon'] ?? 'building-2')),
        'photo_url' => trim((string) ($business['photo_url'] ?? '')),
        'photo_attribution' => '',
        'website_url' => trim((string) ($business['website'] ?? '')),
        'offer_title' => $offerTitle,
        'has_offer' => $offerTitle !== '',
        'detail_url' => $businessId > 0 ? 'place.php?business_id=' . rawurlencode((string) $businessId) : '',
        'search_blob' => strtolower(trim(implode(' ', array_filter([
            $business['name'] ?? '',
            $business['type_label'] ?? '',
            $business['primary_address'] ?? '',
            $business['description'] ?? '',
            $offerTitle,
        ])))),
    ];
}

function get_discovery_places($query = '', $limit = null) {
    $places = [];

    foreach (get_place_catalog() as $place) {
        $normalized = normalize_catalog_place_for_discovery($place);

        if ($normalized['id'] !== '') {
            $places[] = $normalized;
        }
    }

    if (function_exists('get_public_businesses')) {
        foreach (get_public_businesses() as $business) {
            $normalized = normalize_public_business_for_discovery($business);

            if ($normalized['id'] !== '') {
                $places[] = $normalized;
            }
        }
    }

    $query = strtolower(trim((string) $query));

    if ($query !== '') {
        $places = array_values(array_filter($places, function ($place) use ($query) {
            return strpos((string) ($place['search_blob'] ?? ''), $query) !== false;
        }));
    }

    usort($places, function ($left, $right) {
        $leftHasOffer = !empty($left['has_offer']) ? 1 : 0;
        $rightHasOffer = !empty($right['has_offer']) ? 1 : 0;

        if ($leftHasOffer !== $rightHasOffer) {
            return $rightHasOffer <=> $leftHasOffer;
        }

        $leftSource = (string) ($left['source'] ?? '');
        $rightSource = (string) ($right['source'] ?? '');

        if ($leftSource !== $rightSource) {
            return $leftSource === 'business' ? 1 : -1;
        }

        return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
    });

    if ($limit !== null && (int) $limit > 0) {
        return array_slice($places, 0, (int) $limit);
    }

    return $places;
}

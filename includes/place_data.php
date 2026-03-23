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
    $catalog = get_place_catalog();
    $visitedLookup = array_flip($visitedIds);
    $suggestions = [];

    foreach ($catalog as $place) {
        if (!isset($visitedLookup[$place['id']])) {
            $suggestions[] = $place;
        }
    }

    return array_slice($suggestions, 0, $limit);
}

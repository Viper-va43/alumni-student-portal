<?php

function where2go_media_type_from_url($url) {
    $path = parse_url((string) $url, PHP_URL_PATH);
    $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));

    if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'jfif', 'avif'], true)) {
        return 'image';
    }

    if (in_array($extension, ['mp4', 'webm', 'ogg', 'mov', 'm4v'], true)) {
        return 'video';
    }

    return '';
}

function where2go_encode_media_web_path($relativePath) {
    $parts = array_filter(explode('/', str_replace('\\', '/', (string) $relativePath)), 'strlen');

    return implode('/', array_map('rawurlencode', $parts));
}

function get_where2go_media_root_info() {
    $root = dirname(__DIR__);

    foreach (['images', 'Images'] as $folderName) {
        $path = $root . DIRECTORY_SEPARATOR . $folderName;

        if (is_dir($path)) {
            return [
                'path' => $path,
                'web' => $folderName,
            ];
        }
    }

    return [
        'path' => $root . DIRECTORY_SEPARATOR . 'images',
        'web' => 'images',
    ];
}

function get_catalog_media_folder_map() {
    return [
        'hadramout-antar' => ['Hardra mouta anter'],
        'garden-8' => ['Garden 8'],
        '5a-waterway' => ['5A'],
        'point-90-mall' => ['Point 90'],
        'o1-mall' => ['O1 Mall'],
        'lake-town' => ['Lake town'],
        'the-drive' => ['The drive', 'The drive 2'],
        '354-club' => ['354 Club'],
        'the-waterway' => ['Waterway', 'Waterway 2'],
        'fuel-up' => ['FuleUp'],
        'pyramids-giza-sphinx' => ['Pyramids of giza'],
        'grand-egyptian-museum' => ['Grand Egyption museum'],
        'khan-khalili-moez' => ['Khan el-Khalili &Elmoez street'],
        'cairo-citadel' => ['Cairo Citadel'],
        'azhar-park' => ['Azhar park'],
        'cairo-tower' => ['Cairo tower'],
        'egyptian-museum-tahrir' => ['Egyption museum'],
        'coptic-cairo' => ['Coptic Cairo'],
        'manial-palace' => ['Manial Palace'],
        'nile-kayak-maadi' => ['Nile Kayak'],
        'paintball-archery-new-cairo' => ['Adrinalin park'],
        'crimson-zamalek' => ['Crimson Bar and grill'],
        'opia-lounge-ramses-hilton' => ['OPIA Lounge & Bar'],
    ];
}

function get_catalog_place_media($place) {
    $place = is_array($place) ? $place : [];
    $placeId = trim((string) ($place['id'] ?? ''));
    $folderMap = get_catalog_media_folder_map();
    $folders = $folderMap[$placeId] ?? [];
    $mediaRoot = get_where2go_media_root_info();
    $baseDir = $mediaRoot['path'];
    $webRoot = $mediaRoot['web'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'jfif', 'avif', 'mp4', 'webm', 'ogg', 'mov', 'm4v'];
    $media = [];
    $seen = [];

    if (!is_dir($baseDir)) {
        return [];
    }

    foreach ($folders as $folder) {
        $folder = trim((string) $folder);
        $folderPath = $baseDir . DIRECTORY_SEPARATOR . $folder;

        if ($folder === '' || !is_dir($folderPath)) {
            continue;
        }

        $files = array_values(array_filter(scandir($folderPath) ?: [], function ($file) use ($folderPath, $allowedExtensions) {
            $fullPath = $folderPath . DIRECTORY_SEPARATOR . $file;
            $extension = strtolower(pathinfo((string) $file, PATHINFO_EXTENSION));

            return is_file($fullPath) && in_array($extension, $allowedExtensions, true);
        }));

        natsort($files);

        foreach ($files as $file) {
            $relativePath = $webRoot . '/' . $folder . '/' . $file;
            $url = where2go_encode_media_web_path($relativePath);

            if (isset($seen[$url])) {
                continue;
            }

            $type = where2go_media_type_from_url($url);

            if ($type === '') {
                continue;
            }

            $seen[$url] = true;
            $media[] = [
                'url' => $url,
                'type' => $type,
                'name' => pathinfo((string) $file, PATHINFO_FILENAME),
            ];
        }
    }

    return array_slice($media, 0, 16);
}

function get_place_hero_media($mediaItems, $fallbackUrl = '') {
    $mediaItems = is_array($mediaItems) ? $mediaItems : [];

    foreach ($mediaItems as $item) {
        if (!empty($item['url']) && !empty($item['type'])) {
            return [
                'url' => (string) $item['url'],
                'type' => (string) $item['type'],
            ];
        }
    }

    $fallbackUrl = trim((string) $fallbackUrl);
    $fallbackType = where2go_media_type_from_url($fallbackUrl);

    if ($fallbackUrl !== '') {
        return [
            'url' => $fallbackUrl,
            'type' => $fallbackType !== '' ? $fallbackType : 'image',
        ];
    }

    return null;
}

function get_first_place_image_url($mediaItems, $fallbackUrl = '') {
    $mediaItems = is_array($mediaItems) ? $mediaItems : [];

    foreach ($mediaItems as $item) {
        if (($item['type'] ?? '') === 'image' && trim((string) ($item['url'] ?? '')) !== '') {
            return trim((string) $item['url']);
        }
    }

    return where2go_media_type_from_url($fallbackUrl) !== 'video' ? trim((string) $fallbackUrl) : '';
}

function get_place_catalog_definitions() {
    return [
        'restaurant' => [
            'label' => 'Restaurants',
            'icon' => 'utensils-crossed',
            'aliases' => ['restaurant', 'restaurants', 'food', 'food plan', 'dining', 'grill', 'meal', 'meals'],
        ],
        'cafe' => [
            'label' => 'Cafes',
            'icon' => 'coffee',
            'aliases' => ['cafe', 'cafes', 'coffee', 'relaxed'],
        ],
        'activity' => [
            'label' => 'Activities',
            'icon' => 'mountain-snow',
            'aliases' => ['activity', 'activities', 'active', 'active day', 'fun', 'fun spot', 'fun spots', 'gaming', 'game', 'games', 'outdoor', 'outdoors', 'park'],
        ],
        'entertainment' => [
            'label' => 'Entertainment',
            'icon' => 'star',
            'aliases' => ['entertainment', 'mall', 'malls', 'cinema', 'shopping', 'hangout', 'hangouts'],
        ],
        'nightlife' => [
            'label' => 'Nightlife',
            'icon' => 'music-4',
            'aliases' => ['nightlife', 'night life', 'night', 'bar', 'bars', 'lounge', 'rooftop', 'club'],
        ],
        'heritage' => [
            'label' => 'Heritage & Culture',
            'icon' => 'landmark',
            'aliases' => ['heritage', 'culture', 'cultural', 'culture walk', 'museum', 'museums', 'market', 'markets', 'history', 'historic', 'historical', 'viewpoint', 'views', 'view', 'landmark', 'landmarks', 'sightseeing'],
        ],
        'other' => [
            'label' => 'Other',
            'icon' => 'building-2',
            'aliases' => ['other'],
        ],
    ];
}

function normalize_place_catalog_token($value) {
    $normalized = strtolower(str_replace('&', ' and ', (string) $value));
    $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized);
    $normalized = preg_replace('/\s+/', ' ', (string) $normalized);

    return trim((string) $normalized);
}

function get_place_catalog_slug($value) {
    static $lookup = null;

    $normalized = normalize_place_catalog_token($value);

    if ($normalized === '') {
        return '';
    }

    if ($lookup === null) {
        $lookup = [];

        foreach (get_place_catalog_definitions() as $slug => $definition) {
            $lookup[normalize_place_catalog_token($slug)] = $slug;
            $lookup[normalize_place_catalog_token($definition['label'] ?? $slug)] = $slug;

            foreach (($definition['aliases'] ?? []) as $alias) {
                $lookup[normalize_place_catalog_token($alias)] = $slug;
            }
        }
    }

    return $lookup[$normalized] ?? '';
}

function get_primary_place_catalog_slug($value) {
    static $lookup = null;

    $normalized = normalize_place_catalog_token($value);

    if ($normalized === '') {
        return '';
    }

    if ($lookup === null) {
        $lookup = [];

        foreach (get_place_catalog_definitions() as $slug => $definition) {
            $lookup[normalize_place_catalog_token($slug)] = $slug;
            $lookup[normalize_place_catalog_token($definition['label'] ?? $slug)] = $slug;
        }
    }

    return $lookup[$normalized] ?? '';
}

function get_place_catalog_label($catalog) {
    $catalog = get_place_catalog_slug($catalog) ?: 'other';
    $definitions = get_place_catalog_definitions();

    return (string) ($definitions[$catalog]['label'] ?? 'Other');
}

function get_place_catalog_icon($catalog) {
    $catalog = get_place_catalog_slug($catalog) ?: 'other';
    $definitions = get_place_catalog_definitions();

    return (string) ($definitions[$catalog]['icon'] ?? 'building-2');
}

function get_builtin_place_catalog_assignments() {
    return [
        'hadramout-antar' => 'restaurant',
        'garden-8' => 'entertainment',
        '5a-waterway' => 'entertainment',
        'point-90-mall' => 'entertainment',
        'o1-mall' => 'restaurant',
        'lake-town' => 'entertainment',
        'the-drive' => 'entertainment',
        '354-club' => 'activity',
        'the-waterway' => 'cafe',
        'fuel-up' => 'cafe',
        'pyramids-giza-sphinx' => 'heritage',
        'grand-egyptian-museum' => 'heritage',
        'khan-khalili-moez' => 'heritage',
        'cairo-citadel' => 'heritage',
        'azhar-park' => 'activity',
        'cairo-tower' => 'heritage',
        'egyptian-museum-tahrir' => 'heritage',
        'coptic-cairo' => 'heritage',
        'manial-palace' => 'heritage',
        'nile-kayak-maadi' => 'activity',
        'paintball-archery-new-cairo' => 'activity',
        'crimson-zamalek' => 'nightlife',
        'opia-lounge-ramses-hilton' => 'nightlife',
    ];
}

function get_builtin_place_category_assignments() {
    return [
        'hadramout-antar' => 'Restaurant',
        'garden-8' => 'Entertainment Hub',
        '5a-waterway' => 'Entertainment Hub',
        'point-90-mall' => 'Mall',
        'o1-mall' => 'Restaurant',
        'lake-town' => 'Mall',
        'the-drive' => 'Entertainment Hub',
        '354-club' => 'Fun Spot',
        'the-waterway' => 'Relaxed Hangout',
        'fuel-up' => 'Late-night Cafe',
        'pyramids-giza-sphinx' => 'Heritage Site',
        'grand-egyptian-museum' => 'Museum',
        'khan-khalili-moez' => 'Market',
        'cairo-citadel' => 'Heritage Site',
        'azhar-park' => 'Outdoor',
        'cairo-tower' => 'Viewpoint',
        'egyptian-museum-tahrir' => 'Museum',
        'coptic-cairo' => 'Heritage Site',
        'manial-palace' => 'Museum',
        'nile-kayak-maadi' => 'Activity',
        'paintball-archery-new-cairo' => 'Activity',
        'crimson-zamalek' => 'Rooftop',
        'opia-lounge-ramses-hilton' => 'Lounge',
    ];
}

function normalize_place_category_label($category) {
    $category = trim((string) $category);

    if ($category === '') {
        return 'Other';
    }

    $lookup = [
        'restaurant' => 'Restaurant',
        'restaurants' => 'Restaurant',
        'cafe' => 'Cafe',
        'cafes' => 'Cafe',
        'late night cafe' => 'Late-night Cafe',
        'late night cafes' => 'Late-night Cafe',
        'outdoor' => 'Outdoor',
        'outdoors' => 'Outdoor',
        'museum' => 'Museum',
        'museums' => 'Museum',
        'heritage' => 'Heritage Site',
        'heritage site' => 'Heritage Site',
        'heritage and culture' => 'Heritage Site',
        'markets' => 'Market',
        'market' => 'Market',
        'viewpoint' => 'Viewpoint',
        'view point' => 'Viewpoint',
        'activity' => 'Activity',
        'activities' => 'Activity',
        'entertainment' => 'Entertainment Hub',
        'entertainment hub' => 'Entertainment Hub',
        'fun spots' => 'Fun Spot',
        'fun spot' => 'Fun Spot',
        'rooftop' => 'Rooftop',
        'lounge' => 'Lounge',
        'mall' => 'Mall',
        'relaxed' => 'Relaxed Hangout',
        'relaxed hangout' => 'Relaxed Hangout',
        'nightlife' => 'Lounge',
        'nightlife spot' => 'Lounge',
        'other' => 'Other',
    ];

    $normalized = normalize_place_catalog_token($category);

    return $lookup[$normalized] ?? $category;
}

function resolve_catalog_place_category($place) {
    $place = is_array($place) ? $place : [];
    $placeId = trim((string) ($place['id'] ?? ''));
    $assignments = get_builtin_place_category_assignments();

    if ($placeId !== '' && isset($assignments[$placeId])) {
        return $assignments[$placeId];
    }

    return normalize_place_category_label($place['category'] ?? 'Other');
}

function infer_place_catalog_slug($place) {
    $place = is_array($place) ? $place : [];
    $catalog = get_place_catalog_slug($place['catalog'] ?? '');

    if ($catalog !== '') {
        return $catalog;
    }

    $placeId = trim((string) ($place['id'] ?? ''));
    $assignments = get_builtin_place_catalog_assignments();

    if ($placeId !== '' && isset($assignments[$placeId])) {
        return $assignments[$placeId];
    }

    $categoryCatalog = get_place_catalog_slug($place['category'] ?? '');

    if ($categoryCatalog !== '') {
        return $categoryCatalog;
    }

    $searchable = normalize_place_catalog_token(implode(' ', array_filter([
        $place['name'] ?? '',
        $place['description'] ?? '',
        $place['query'] ?? '',
    ])));

    foreach (get_place_catalog_definitions() as $slug => $definition) {
        if ($slug === 'other') {
            continue;
        }

        foreach (($definition['aliases'] ?? []) as $alias) {
            $alias = normalize_place_catalog_token($alias);

            if ($alias !== '' && strpos($searchable, $alias) !== false) {
                return $slug;
            }
        }
    }

    return 'other';
}

function get_builtin_place_catalog_slug_for_name($name) {
    static $lookup = null;

    $normalizedName = normalize_place_catalog_token($name);

    if ($normalizedName === '') {
        return '';
    }

    if ($lookup === null) {
        $lookup = [];

        foreach (get_builtin_place_catalog() as $place) {
            $placeName = normalize_place_catalog_token($place['name'] ?? '');

            if ($placeName !== '') {
                $lookup[$placeName] = infer_place_catalog_slug($place);
            }
        }
    }

    return $lookup[$normalizedName] ?? '';
}

function get_builtin_place_category_for_name($name) {
    static $lookup = null;

    $normalizedName = normalize_place_catalog_token($name);

    if ($normalizedName === '') {
        return '';
    }

    if ($lookup === null) {
        $lookup = [];

        foreach (get_builtin_place_catalog() as $place) {
            $placeName = normalize_place_catalog_token($place['name'] ?? '');

            if ($placeName !== '') {
                $lookup[$placeName] = resolve_catalog_place_category($place);
            }
        }
    }

    return $lookup[$normalizedName] ?? '';
}

function catalog_place_matches_search($place, $query) {
    $needle = normalize_place_catalog_token($query);

    if ($needle === '') {
        return true;
    }

    $haystack = normalize_place_catalog_token($place['search_blob'] ?? '');

    return strpos($haystack, $needle) !== false;
}

// Return the built-in starter catalog that seeds discovery before partner data is added.
function get_builtin_place_catalog() {
    return [
        [
            'id' => 'hadramout-antar',
            'name' => 'Hadramout Antar',
            'category' => 'Restaurant',
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
            'category' => 'Entertainment Hub',
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
            'category' => 'Entertainment Hub',
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
            'category' => 'Mall',
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
            'category' => 'Restaurant',
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
            'category' => 'Mall',
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
            'category' => 'Entertainment Hub',
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
            'category' => 'Fun Spot',
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
            'category' => 'Relaxed Hangout',
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
            'category' => 'Late-night Cafe',
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
        [
            'id' => 'pyramids-giza-sphinx',
            'name' => 'Pyramids of Giza & Sphinx',
            'category' => 'Heritage Site',
            'area' => 'Giza Plateau',
            'city' => 'Giza',
            'description' => 'A must-see ancient wonder with the Great Pyramids and Sphinx. Approx. 700 EGP per ticket.',
            'price_range' => '$$$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'landmark',
            'query' => 'Pyramids of Giza Sphinx Giza Plateau Egypt',
            'lat' => 29.9792,
            'lng' => 31.1342,
        ],
        [
            'id' => 'grand-egyptian-museum',
            'name' => 'Grand Egyptian Museum (GEM)',
            'category' => 'Museum',
            'area' => 'Giza',
            'city' => 'Giza',
            'description' => 'A major museum near the pyramids with large ancient Egyptian collections and high-range ticket expectations.',
            'price_range' => '$$$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'museum',
            'query' => 'Grand Egyptian Museum GEM Giza Egypt',
            'lat' => 29.9936,
            'lng' => 31.1194,
        ],
        [
            'id' => 'khan-khalili-moez',
            'name' => 'Khan el-Khalili & El Moez Street',
            'category' => 'Market',
            'area' => 'Islamic Cairo',
            'city' => 'Cairo',
            'description' => 'A classic walk through historic streets, markets, shops, cafes, and old Cairo atmosphere. Free to walk; spending varies.',
            'price_range' => '$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'shopping-bag',
            'query' => 'Khan el Khalili El Moez Street Islamic Cairo Egypt',
            'lat' => 30.0477,
            'lng' => 31.2625,
        ],
        [
            'id' => 'cairo-citadel',
            'name' => 'Cairo Citadel (Salah El-Din)',
            'category' => 'Heritage Site',
            'area' => 'Islamic Cairo',
            'city' => 'Cairo',
            'description' => 'A historic fortress with mosque courtyards and city views. Approx. 450 EGP per ticket.',
            'price_range' => '$$$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'landmark',
            'query' => 'Cairo Citadel Salah El Din Islamic Cairo Egypt',
            'lat' => 30.0299,
            'lng' => 31.2613,
        ],
        [
            'id' => 'azhar-park',
            'name' => 'Azhar Park',
            'category' => 'Outdoor',
            'area' => 'Darb al-Ahmar',
            'city' => 'Cairo',
            'description' => 'A green city escape for walking, skyline views, and relaxed daytime plans. Approx. 40 EGP.',
            'price_range' => '$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'trees',
            'query' => 'Azhar Park Darb al Ahmar Cairo Egypt',
            'lat' => 30.0400,
            'lng' => 31.2653,
        ],
        [
            'id' => 'cairo-tower',
            'name' => 'Cairo Tower',
            'category' => 'Viewpoint',
            'area' => 'Zamalek',
            'city' => 'Cairo',
            'description' => 'A Zamalek viewpoint with panoramic city and Nile views. Approx. 200 EGP.',
            'price_range' => '$$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'tower-control',
            'query' => 'Cairo Tower Zamalek Egypt',
            'lat' => 30.0459,
            'lng' => 31.2243,
        ],
        [
            'id' => 'egyptian-museum-tahrir',
            'name' => 'Egyptian Museum',
            'category' => 'Museum',
            'area' => 'Tahrir Square',
            'city' => 'Cairo',
            'description' => 'A central museum with ancient Egyptian artifacts and classic Cairo history. Approx. 450 EGP.',
            'price_range' => '$$$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'museum',
            'query' => 'Egyptian Museum Tahrir Square Cairo Egypt',
            'lat' => 30.0478,
            'lng' => 31.2336,
        ],
        [
            'id' => 'coptic-cairo',
            'name' => 'Coptic Cairo',
            'category' => 'Heritage Site',
            'area' => 'Old Cairo',
            'city' => 'Cairo',
            'description' => 'A historic district with the Hanging Church, Babylon Fortress, and quiet heritage walks. Free to enter many areas.',
            'price_range' => '$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'church',
            'query' => 'Coptic Cairo Old Cairo Hanging Church Babylon Fortress Egypt',
            'lat' => 30.0061,
            'lng' => 31.2302,
        ],
        [
            'id' => 'manial-palace',
            'name' => 'Manial Palace',
            'category' => 'Museum',
            'area' => 'Rhoda Island',
            'city' => 'Cairo',
            'description' => 'A historical palace museum with distinctive architecture, gardens, and Nile island character. Approx. 180 EGP.',
            'price_range' => '$$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'castle',
            'query' => 'Manial Palace Rhoda Island Cairo Egypt',
            'lat' => 30.0275,
            'lng' => 31.2292,
        ],
        [
            'id' => 'nile-kayak-maadi',
            'name' => 'Nile Kayak',
            'category' => 'Activity',
            'area' => 'Maadi / Corniche',
            'city' => 'Cairo',
            'description' => 'A Nile activity plan for paddling sessions around Maadi or the Corniche. Approx. 200 EGP per session.',
            'price_range' => '$$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'waves',
            'query' => 'Nile Kayak Maadi Corniche Cairo Egypt',
            'lat' => 29.9602,
            'lng' => 31.2508,
        ],
        [
            'id' => 'paintball-archery-new-cairo',
            'name' => 'Paintball / Archery',
            'category' => 'Activity',
            'area' => 'New Cairo',
            'city' => 'Cairo',
            'description' => 'Group-friendly paintball and archery options for active plans. Approx. 140-220 EGP per person.',
            'price_range' => '$$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'target',
            'query' => 'Paintball Archery New Cairo Egypt',
            'lat' => 30.0131,
            'lng' => 31.4913,
        ],
        [
            'id' => 'crimson-zamalek',
            'name' => 'Crimson Bar & Grill',
            'category' => 'Rooftop',
            'area' => '16 Kamal Al Tawil St, Zamalek',
            'city' => 'Cairo',
            'description' => 'An upscale Nile-side rooftop in Zamalek known for sunset views, refined dining, and a polished evening atmosphere. Meals often cost 500+ EGP.',
            'price_range' => '$$$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'utensils',
            'query' => 'Crimson Bar Grill 16 Kamal Al Tawil Zamalek Cairo Egypt',
            'lat' => 30.0629,
            'lng' => 31.2168,
        ],
        [
            'id' => 'opia-lounge-ramses-hilton',
            'name' => 'OPIA Lounge & Bar',
            'category' => 'Lounge',
            'area' => 'Ramses Hilton, 36th Floor, Downtown',
            'city' => 'Cairo',
            'description' => 'A high-floor lounge with panoramic downtown and Nile skyline views. Minimum charge often around 500-1000 EGP.',
            'price_range' => '$$$',
            'rating' => 'Live',
            'reviews' => 0,
            'icon' => 'building-2',
            'query' => 'OPIA Lounge Bar Ramses Hilton 36th floor Downtown Cairo Egypt',
            'lat' => 30.0504,
            'lng' => 31.2327,
        ],
    ];
}

// Keep the built-in catalog mirrored in MySQL so it exists in the database as well as the website.
function ensure_catalog_places_table($conn) {
    if (!$conn) {
        return false;
    }

    $created = (bool) $conn->query("CREATE TABLE IF NOT EXISTS catalog_places (
        id VARCHAR(120) PRIMARY KEY,
        name VARCHAR(180) NOT NULL,
        category VARCHAR(80) NOT NULL DEFAULT 'Featured place',
        catalog VARCHAR(40) NOT NULL DEFAULT 'other',
        area VARCHAR(180) NOT NULL DEFAULT '',
        city VARCHAR(100) NOT NULL DEFAULT '',
        description TEXT NULL,
        price_range VARCHAR(20) NOT NULL DEFAULT '$$',
        rating VARCHAR(40) NOT NULL DEFAULT 'Live',
        reviews INT NOT NULL DEFAULT 0,
        icon VARCHAR(80) NOT NULL DEFAULT 'map-pinned',
        query_text VARCHAR(255) NOT NULL DEFAULT '',
        lat DECIMAL(10,7) NULL,
        lng DECIMAL(10,7) NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if ($created) {
        if (function_exists('ensure_table_column')) {
            ensure_table_column($conn, 'catalog_places', 'catalog', "ALTER TABLE catalog_places ADD COLUMN catalog VARCHAR(40) NOT NULL DEFAULT 'other' AFTER category");
        } else {
            $result = $conn->query("SHOW COLUMNS FROM catalog_places LIKE 'catalog'");

            if ($result && $result->num_rows === 0) {
                $conn->query("ALTER TABLE catalog_places ADD COLUMN catalog VARCHAR(40) NOT NULL DEFAULT 'other' AFTER category");
            }
        }
    }

    return $created;
}

function seed_catalog_places_table($conn, $places) {
    if (!$conn || !is_array($places)) {
        return false;
    }

    $sql = "INSERT INTO catalog_places
            (id, name, category, catalog, area, city, description, price_range, rating, reviews, icon, query_text, lat, lng, sort_order, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                category = VALUES(category),
                catalog = VALUES(catalog),
                area = VALUES(area),
                city = VALUES(city),
                description = VALUES(description),
                price_range = VALUES(price_range),
                rating = VALUES(rating),
                reviews = VALUES(reviews),
                icon = VALUES(icon),
                query_text = VALUES(query_text),
                lat = VALUES(lat),
                lng = VALUES(lng),
                sort_order = VALUES(sort_order),
                is_active = VALUES(is_active)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    foreach (array_values($places) as $index => $place) {
        $id = (string) ($place['id'] ?? '');

        if ($id === '') {
            continue;
        }

        $name = (string) ($place['name'] ?? 'Where2Go place');
        $category = resolve_catalog_place_category($place);
        $catalog = infer_place_catalog_slug($place);
        $area = (string) ($place['area'] ?? '');
        $city = (string) ($place['city'] ?? '');
        $description = (string) ($place['description'] ?? '');
        $priceRange = (string) ($place['price_range'] ?? '$$');
        $rating = (string) ($place['rating'] ?? 'Live');
        $reviews = (int) ($place['reviews'] ?? 0);
        $icon = (string) ($place['icon'] ?? 'map-pinned');
        $queryText = (string) ($place['query'] ?? '');
        $lat = isset($place['lat']) ? (float) $place['lat'] : null;
        $lng = isset($place['lng']) ? (float) $place['lng'] : null;
        $sortOrder = $index + 1;
        $isActive = 1;

        $stmt->bind_param(
            "sssssssssissddii",
            $id,
            $name,
            $category,
            $catalog,
            $area,
            $city,
            $description,
            $priceRange,
            $rating,
            $reviews,
            $icon,
            $queryText,
            $lat,
            $lng,
            $sortOrder,
            $isActive
        );
        $stmt->execute();
    }

    return true;
}

function get_database_place_catalog($conn) {
    if (!$conn) {
        return [];
    }

    $result = $conn->query("SELECT id, name, category, catalog, area, city, description, price_range, rating, reviews, icon, query_text, lat, lng
                            FROM catalog_places
                            WHERE is_active = 1
                            ORDER BY sort_order ASC, name ASC");

    if (!$result) {
        return [];
    }

    $places = [];

    while ($row = $result->fetch_assoc()) {
        $places[] = [
            'id' => (string) ($row['id'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'category' => (string) ($row['category'] ?? ''),
            'catalog' => (string) ($row['catalog'] ?? ''),
            'area' => (string) ($row['area'] ?? ''),
            'city' => (string) ($row['city'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'price_range' => (string) ($row['price_range'] ?? '$$'),
            'rating' => (string) ($row['rating'] ?? 'Live'),
            'reviews' => (int) ($row['reviews'] ?? 0),
            'icon' => (string) ($row['icon'] ?? 'map-pinned'),
            'query' => (string) ($row['query_text'] ?? ''),
            'lat' => $row['lat'] !== null ? (float) $row['lat'] : null,
            'lng' => $row['lng'] !== null ? (float) $row['lng'] : null,
        ];
    }

    return $places;
}

function get_place_catalog() {
    static $catalog = null;

    if ($catalog !== null) {
        return $catalog;
    }

    $fallback = get_builtin_place_catalog();
    $catalog = $fallback;

    if (function_exists('db_connect')) {
        try {
            $conn = db_connect();

            if (ensure_catalog_places_table($conn)) {
                seed_catalog_places_table($conn, $fallback);
                $databasePlaces = get_database_place_catalog($conn);

                if ($databasePlaces) {
                    $catalog = $databasePlaces;
                }
            }
        } catch (Throwable $error) {
            $catalog = $fallback;
        }
    }

    return $catalog;
}

function ensure_catalog_place_reviews_table($conn = null) {
    if (!$conn && function_exists('db_connect')) {
        $conn = db_connect();
    }

    if (!$conn) {
        return false;
    }

    ensure_catalog_places_table($conn);

    return (bool) $conn->query("CREATE TABLE IF NOT EXISTS catalog_place_reviews (
        review_id INT AUTO_INCREMENT PRIMARY KEY,
        catalog_id VARCHAR(120) NOT NULL,
        customer_id INT NOT NULL,
        rating TINYINT NOT NULL DEFAULT 5,
        comment TEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_catalog_review_customer (catalog_id, customer_id),
        KEY idx_catalog_reviews_catalog (catalog_id),
        KEY idx_catalog_reviews_customer (customer_id),
        CONSTRAINT fk_catalog_reviews_place
            FOREIGN KEY (catalog_id) REFERENCES catalog_places (id) ON DELETE CASCADE,
        CONSTRAINT fk_catalog_reviews_customer
            FOREIGN KEY (customer_id) REFERENCES customers (Customer_ID) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function update_catalog_place_review_count($conn, $catalog_id) {
    $catalog_id = trim((string) $catalog_id);

    if (!$conn || $catalog_id === '') {
        return false;
    }

    $stmt = $conn->prepare("UPDATE catalog_places
            SET reviews = (
                SELECT COUNT(*)
                FROM catalog_place_reviews
                WHERE catalog_id = ?
            )
            WHERE id = ?");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("ss", $catalog_id, $catalog_id);

    return $stmt->execute();
}

function get_catalog_place_review_summary($catalog_id) {
    $catalog_id = trim((string) $catalog_id);

    if ($catalog_id === '') {
        return ['average_rating' => null, 'review_count' => 0];
    }

    $conn = db_connect();
    ensure_catalog_place_reviews_table($conn);

    $stmt = $conn->prepare("SELECT AVG(rating) AS average_rating, COUNT(*) AS review_count
            FROM catalog_place_reviews
            WHERE catalog_id = ?");

    if (!$stmt) {
        return ['average_rating' => null, 'review_count' => 0];
    }

    $stmt->bind_param("s", $catalog_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $count = (int) ($row['review_count'] ?? 0);

    return [
        'average_rating' => $count > 0 ? (float) ($row['average_rating'] ?? 0) : null,
        'review_count' => $count,
    ];
}

function get_catalog_place_reviews($catalog_id, $limit = 5) {
    $catalog_id = trim((string) $catalog_id);
    $limit = max(1, (int) $limit);

    if ($catalog_id === '') {
        return [];
    }

    $conn = db_connect();
    ensure_catalog_place_reviews_table($conn);

    $sql = "SELECT cpr.review_id, cpr.catalog_id, cpr.customer_id, cpr.rating, cpr.comment, cpr.created_at, cpr.updated_at,
                   c.First_N, c.Last_N
            FROM catalog_place_reviews cpr
            LEFT JOIN customers c ON c.Customer_ID = cpr.customer_id
            WHERE cpr.catalog_id = ?
            ORDER BY cpr.created_at DESC
            LIMIT " . $limit;
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("s", $catalog_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $reviews = [];

    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }

    return $reviews;
}

function get_customer_review_for_catalog_place($customer_id, $catalog_id) {
    $customer_id = (int) $customer_id;
    $catalog_id = trim((string) $catalog_id);

    if ($customer_id <= 0 || $catalog_id === '') {
        return null;
    }

    $conn = db_connect();
    ensure_catalog_place_reviews_table($conn);

    $stmt = $conn->prepare("SELECT review_id, catalog_id, customer_id, rating, comment, created_at, updated_at
            FROM catalog_place_reviews
            WHERE catalog_id = ?
              AND customer_id = ?
            LIMIT 1");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("si", $catalog_id, $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result ? $result->fetch_assoc() : null;
}

function submit_catalog_place_review($customer_id, $catalog_id, $rating, $comment) {
    $customer_id = (int) $customer_id;
    $catalog_id = trim((string) $catalog_id);
    $rating = max(1, min(5, (int) $rating));
    $comment = trim((string) $comment);

    if ($customer_id <= 0 || $catalog_id === '') {
        return ['ok' => false, 'message' => 'A logged-in customer and valid place are required.'];
    }

    if (!get_place_by_id($catalog_id)) {
        return ['ok' => false, 'message' => 'This original place could not be found.'];
    }

    if ($comment === '') {
        return ['ok' => false, 'message' => 'Share a short review before submitting.'];
    }

    $conn = db_connect();
    ensure_catalog_place_reviews_table($conn);

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare("SELECT review_id
                FROM catalog_place_reviews
                WHERE catalog_id = ?
                  AND customer_id = ?
                LIMIT 1
                FOR UPDATE");

        if (!$stmt) {
            throw new Exception('The review record could not be prepared.');
        }

        $stmt->bind_param("si", $catalog_id, $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existingReview = $result ? $result->fetch_assoc() : null;

        if ($existingReview) {
            $reviewId = (int) ($existingReview['review_id'] ?? 0);
            $updateStmt = $conn->prepare("UPDATE catalog_place_reviews
                    SET rating = ?, comment = ?, updated_at = NOW()
                    WHERE review_id = ?");

            if (!$updateStmt) {
                throw new Exception('The review update could not be prepared.');
            }

            $updateStmt->bind_param("isi", $rating, $comment, $reviewId);

            if (!$updateStmt->execute()) {
                throw new Exception('The review could not be updated.');
            }
        } else {
            $insertStmt = $conn->prepare("INSERT INTO catalog_place_reviews
                    (catalog_id, customer_id, rating, comment, created_at, updated_at)
                    VALUES (?, ?, ?, ?, NOW(), NOW())");

            if (!$insertStmt) {
                throw new Exception('The review insert could not be prepared.');
            }

            $insertStmt->bind_param("siis", $catalog_id, $customer_id, $rating, $comment);

            if (!$insertStmt->execute()) {
                throw new Exception('The review could not be saved.');
            }
        }

        update_catalog_place_review_count($conn, $catalog_id);
        $conn->commit();

        return [
            'ok' => true,
            'message' => $existingReview ? 'Your review was updated.' : 'Review posted. Thanks for sharing your feedback.',
        ];
    } catch (Throwable $error) {
        $conn->rollback();

        return [
            'ok' => false,
            'message' => 'The review could not be saved right now.',
            'error' => $error->getMessage(),
        ];
    }
}

function delete_catalog_place_review($customer_id, $catalog_id) {
    $customer_id = (int) $customer_id;
    $catalog_id = trim((string) $catalog_id);

    if ($customer_id <= 0 || $catalog_id === '') {
        return ['ok' => false, 'message' => 'A logged-in customer and valid place are required.'];
    }

    $conn = db_connect();
    ensure_catalog_place_reviews_table($conn);

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare("DELETE FROM catalog_place_reviews
                WHERE catalog_id = ?
                  AND customer_id = ?
                LIMIT 1");

        if (!$stmt) {
            throw new Exception('The review delete could not be prepared.');
        }

        $stmt->bind_param("si", $catalog_id, $customer_id);

        if (!$stmt->execute()) {
            throw new Exception('The review could not be deleted.');
        }

        if ($stmt->affected_rows < 1) {
            $conn->rollback();
            return ['ok' => false, 'message' => 'No review was found to delete.'];
        }

        update_catalog_place_review_count($conn, $catalog_id);
        $conn->commit();

        return ['ok' => true, 'message' => 'Your review was deleted.'];
    } catch (Throwable $error) {
        $conn->rollback();

        return [
            'ok' => false,
            'message' => 'The review could not be deleted right now.',
            'error' => $error->getMessage(),
        ];
    }
}

// Find one catalog place by its stable identifier.
function get_place_by_id($placeId) {
    foreach (get_place_catalog() as $place) {
        if ($place['id'] === $placeId) {
            return $place;
        }
    }

    return null;
}

// Resolve a saved list of place ids back into catalog records.
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

// Suggest new places by excluding anything the customer already saved.
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

// Shape a built-in catalog record so it matches the discovery and search UI contract.
function normalize_catalog_place_for_discovery($place) {
    $place = is_array($place) ? $place : [];
    $catalog = infer_place_catalog_slug($place);
    $catalogLabel = get_place_catalog_label($catalog);
    $address = trim(implode(', ', array_filter([
        trim((string) ($place['area'] ?? '')),
        trim((string) ($place['city'] ?? '')),
    ])));
    $mediaItems = get_catalog_place_media($place);
    $heroMedia = get_place_hero_media($mediaItems, $place['photo_url'] ?? '');
    $photoUrl = get_first_place_image_url($mediaItems, $place['photo_url'] ?? '');
    $category = resolve_catalog_place_category($place);

    return [
        'id' => (string) ($place['id'] ?? ''),
        'source' => 'catalog',
        'place_id' => (string) ($place['id'] ?? ''),
        'business_id' => null,
        'location_id' => null,
        'name' => trim((string) ($place['name'] ?? 'Where2Go place')),
        'category' => $category,
        'catalog' => $catalog,
        'catalog_label' => $catalogLabel,
        'catalog_icon' => get_place_catalog_icon($catalog),
        'area' => trim((string) ($place['area'] ?? '')),
        'city' => trim((string) ($place['city'] ?? '')),
        'address' => $address,
        'description' => trim((string) ($place['description'] ?? 'Curated by Where2Go.')),
        'price_range' => trim((string) ($place['price_range'] ?? '$$')),
        'rating' => trim((string) ($place['rating'] ?? 'Featured')),
        'reviews' => (int) ($place['reviews'] ?? 0),
        'icon' => trim((string) ($place['icon'] ?? 'map-pinned')),
        'photo_url' => $photoUrl,
        'media_items' => $mediaItems,
        'hero_media' => $heroMedia,
        'hero_media_url' => $heroMedia['url'] ?? '',
        'hero_media_type' => $heroMedia['type'] ?? '',
        'photo_attribution' => trim((string) ($place['photo_attribution'] ?? '')),
        'website_url' => trim((string) ($place['website_url'] ?? '')),
        'offer_title' => '',
        'has_offer' => false,
        'detail_url' => 'place.php?catalog_id=' . rawurlencode((string) ($place['id'] ?? '')),
        'search_blob' => strtolower(trim(implode(' ', array_filter([
            $place['name'] ?? '',
            $category,
            $catalog,
            $catalogLabel,
            $place['area'] ?? '',
            $place['city'] ?? '',
            $place['description'] ?? '',
            $place['query'] ?? '',
        ])))),
    ];
}

// Shape an approved partner business so it can live beside the built-in catalog.
function normalize_public_business_for_discovery($business) {
    $business = is_array($business) ? $business : [];
    $businessId = (int) ($business['business_id'] ?? 0);
    $address = trim((string) ($business['primary_address'] ?? ''));
    $offerTitle = trim((string) ($business['active_offer_title'] ?? ''));
    $photoUrl = trim((string) ($business['photo_url'] ?? ''));
    $heroMedia = get_place_hero_media([], $photoUrl);
    $catalog = get_builtin_place_catalog_slug_for_name($business['name'] ?? '');
    $category = get_builtin_place_category_for_name($business['name'] ?? '');

    if ($catalog === '') {
        $catalog = get_place_catalog_slug($business['type'] ?? '');
    }

    if ($catalog === '' || $catalog === 'other') {
        $customCatalog = get_place_catalog_slug($business['custom_type'] ?? ($business['type_label'] ?? ''));

        if ($customCatalog !== '') {
            $catalog = $customCatalog;
        }
    }

    if ($catalog === '') {
        $catalog = 'other';
    }

    if ($category === '') {
        $customCategory = trim((string) ($business['custom_type'] ?? ''));
        $category = normalize_place_category_label($customCategory !== '' ? $customCategory : ($business['type_label'] ?? 'Business'));
    }

    $catalogLabel = get_place_catalog_label($catalog);
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
        'category' => $category,
        'catalog' => $catalog,
        'catalog_label' => $catalogLabel,
        'catalog_icon' => get_place_catalog_icon($catalog),
        'area' => $address,
        'city' => '',
        'address' => $address,
        'description' => trim(implode(' ', $descriptionParts)) !== '' ? trim(implode(' ', $descriptionParts)) : 'Approved business on Where2Go.',
        'price_range' => $offerTitle !== '' ? 'Offer live' : 'See details',
        'rating' => $ratingValue,
        'reviews' => (int) ($business['review_count'] ?? 0),
        'icon' => trim((string) ($business['icon'] ?? 'building-2')),
        'photo_url' => $photoUrl,
        'media_items' => [],
        'hero_media' => $heroMedia,
        'hero_media_url' => $heroMedia['url'] ?? '',
        'hero_media_type' => $heroMedia['type'] ?? '',
        'photo_attribution' => '',
        'website_url' => trim((string) ($business['website'] ?? '')),
        'offer_title' => $offerTitle,
        'has_offer' => $offerTitle !== '',
        'detail_url' => $businessId > 0 ? 'place.php?business_id=' . rawurlencode((string) $businessId) : '',
        'search_blob' => strtolower(trim(implode(' ', array_filter([
            $business['name'] ?? '',
            $category,
            $catalog,
            $catalogLabel,
            $business['primary_address'] ?? '',
            $business['description'] ?? '',
            $offerTitle,
        ])))),
    ];
}

function get_discovery_place_dedupe_key($place) {
    $place = is_array($place) ? $place : [];
    $name = normalize_place_catalog_token($place['name'] ?? '');

    if ($name === '') {
        return '';
    }

    return 'name:' . $name;
}

function dedupe_discovery_places($places) {
    $places = is_array($places) ? $places : [];
    $deduped = [];
    $positions = [];

    foreach ($places as $place) {
        $place = is_array($place) ? $place : [];
        $key = get_discovery_place_dedupe_key($place);

        if ($key === '') {
            $deduped[] = $place;
            continue;
        }

        if (!array_key_exists($key, $positions)) {
            $positions[$key] = count($deduped);
            $deduped[] = $place;
            continue;
        }

        $position = $positions[$key];
        $currentSource = (string) ($place['source'] ?? '');
        $existingSource = (string) ($deduped[$position]['source'] ?? '');

        if ($currentSource === 'business' && $existingSource !== 'business') {
            $deduped[$position] = $place;
        }
    }

    return array_values($deduped);
}

// Merge catalog places and approved partner businesses into one searchable discovery list.
function get_discovery_places($query = '', $limit = null, $catalog = '') {
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

    $places = dedupe_discovery_places($places);

    $query = trim((string) $query);
    $catalogFilter = get_place_catalog_slug($catalog);

    if ($catalogFilter === '') {
        $queryCatalog = get_primary_place_catalog_slug($query);

        if ($queryCatalog !== '') {
            $catalogFilter = $queryCatalog;
            $query = '';
        }
    }

    if ($catalogFilter !== '') {
        $places = array_values(array_filter($places, function ($place) use ($catalogFilter) {
            return ($place['catalog'] ?? '') === $catalogFilter;
        }));
    }

    if ($query !== '') {
        $places = array_values(array_filter($places, function ($place) use ($query) {
            return catalog_place_matches_search($place, $query);
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

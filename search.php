<?php
// Load discovery helpers and build the search results using the current session state.
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/place_data.php';

start_session();

$loggedIn = is_logged_in();
$partnerLoggedIn = is_partner_logged_in();
$adminLoggedIn = is_admin_user();
$customerName = trim($_SESSION['customer_name'] ?? '');
$partnerName = trim($_SESSION['partner_name'] ?? '');
$customerId = (int) ($_SESSION['customer_id'] ?? 0);
$profilePhoto = $loggedIn ? get_profile_photo_web_path($customerId) : null;
$visitedPlaceIds = get_visited_place_ids();
$visitedLookup = array_flip($visitedPlaceIds);
$query = trim($_GET['q'] ?? ($_GET['query'] ?? ''));
$activeCatalog = get_place_catalog_slug($_GET['catalog'] ?? '');
$activeEvent = normalize_search_filter_choice($_GET['event'] ?? '', ['food', 'friends', 'family', 'date', 'active', 'culture', 'views']);
$activeLocation = trim((string) ($_GET['location'] ?? ''));
$activePrice = normalize_search_filter_choice($_GET['price'] ?? '', ['100', '200', '300']);
$queryCatalog = get_primary_place_catalog_slug($query);

if ($activeCatalog === '' && $queryCatalog !== '') {
    $activeCatalog = $queryCatalog;
}

$effectiveQuery = $queryCatalog !== '' && $activeCatalog === $queryCatalog ? '' : $query;
$searchResults = get_discovery_places($effectiveQuery, null, $activeCatalog);
$searchResults = array_values(array_filter($searchResults, function ($place) use ($activeEvent, $activeLocation, $activePrice) {
    return search_place_matches_extra_filters($place, $activeEvent, $activeLocation, $activePrice);
}));
$activeCatalogLabel = $activeCatalog !== '' ? get_place_catalog_label($activeCatalog) : '';
$activeFilterLabels = build_active_search_filter_labels($activeEvent, $activeLocation, $activePrice);
$catalogCount = count(array_filter($searchResults, function ($place) {
    return ($place['source'] ?? '') === 'catalog';
}));
$businessCount = count(array_filter($searchResults, function ($place) {
    return ($place['source'] ?? '') === 'business';
}));

function normalize_search_filter_choice($value, $allowedValues) {
    $value = strtolower(trim((string) $value));

    return in_array($value, $allowedValues, true) ? $value : '';
}

function build_search_filter_text($place) {
    $place = is_array($place) ? $place : [];

    return normalize_place_catalog_token(implode(' ', array_filter([
        $place['name'] ?? '',
        $place['category'] ?? '',
        $place['catalog'] ?? '',
        $place['catalog_label'] ?? '',
        $place['area'] ?? '',
        $place['city'] ?? '',
        $place['address'] ?? '',
        $place['description'] ?? '',
        $place['search_blob'] ?? '',
    ])));
}

function search_place_matches_location_filter($place, $location) {
    $location = normalize_place_catalog_token($location);

    if ($location === '') {
        return true;
    }

    $aliases = [
        '5th settlement' => ['5th settlement', 'fifth settlement', 'new cairo'],
        '1st settlement' => ['1st settlement', 'first settlement', 'new cairo'],
        'al rehab' => ['al rehab', 'rehab', 'new cairo'],
        'el shorouk' => ['el shorouk', 'shorouk'],
        'new cairo' => ['new cairo', 'fifth settlement', '5th settlement', 'first settlement', '1st settlement'],
        'downtown' => ['downtown', 'abdeen', 'azbakeya', 'cairo'],
        'islamic cairo' => ['islamic cairo', 'el mosky', 'al wayli', 'cairo'],
        'coptic cairo' => ['coptic cairo', 'old cairo', 'cairo'],
        'cairo' => ['cairo'],
    ];

    $haystack = build_search_filter_text($place);
    $needles = $aliases[$location] ?? [$location];

    foreach ($needles as $needle) {
        $needle = normalize_place_catalog_token($needle);

        if ($needle !== '' && strpos($haystack, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function search_place_matches_event_filter($place, $event) {
    $event = normalize_search_filter_choice($event, ['food', 'friends', 'family', 'date', 'active', 'culture', 'views']);

    if ($event === '') {
        return true;
    }

    $patterns = [
        'food' => '/restaurant|dining|food|grill|cafe|meal/i',
        'friends' => '/group|mall|entertainment|activity|nightlife|kayak|paintball|archery|club|gaming|hangout/i',
        'family' => '/park|museum|heritage|mall|citadel|pyramids?|sphinx|coptic|palace|outdoor/i',
        'date' => '/rooftop|nile|view|sunset|fine dining|zamalek|palace|tower|garden|polished/i',
        'active' => '/kayak|paintball|archery|activity|outdoor|park|walking|active/i',
        'culture' => '/museum|heritage|market|citadel|coptic|pyramids?|sphinx|palace|old cairo|historic|artifact/i',
        'views' => '/tower|view|views|rooftop|nile|park|skyline|panoramic/i',
    ];

    return preg_match($patterns[$event], build_search_filter_text($place)) === 1;
}

function search_place_matches_price_filter($place, $price) {
    $price = normalize_search_filter_choice($price, ['100', '200', '300']);

    if ($price === '') {
        return true;
    }

    if ($price === '300') {
        return true;
    }

    $priceRange = strtolower((string) ($place['price_range'] ?? ''));
    $dollarCount = substr_count($priceRange, '$');

    if ($dollarCount <= 0) {
        return false;
    }

    if ($price === '100') {
        return $dollarCount === 1;
    }

    return $dollarCount === 2;
}

function search_place_matches_extra_filters($place, $event, $location, $price) {
    return search_place_matches_location_filter($place, $location)
        && search_place_matches_event_filter($place, $event)
        && search_place_matches_price_filter($place, $price);
}

function build_active_search_filter_labels($event, $location, $price) {
    $labels = [];
    $eventLabels = [
        'food' => 'Food plan',
        'friends' => 'Friends hangout',
        'family' => 'Family outing',
        'date' => 'Date plan',
        'active' => 'Active day',
        'culture' => 'Culture walk',
        'views' => 'Views',
    ];
    $priceLabels = [
        '100' => '50-100 EGP',
        '200' => '100-200 EGP',
        '300' => '200+ EGP',
    ];

    if (isset($eventLabels[$event])) {
        $labels[] = $eventLabels[$event];
    }

    if (trim((string) $location) !== '') {
        $labels[] = trim((string) $location);
    }

    if (isset($priceLabels[$price])) {
        $labels[] = $priceLabels[$price];
    }

    return $labels;
}

function build_search_page_url($query = '', $catalog = '', $event = '', $location = '', $price = '') {
    $params = [];
    $query = trim((string) $query);
    $catalog = get_place_catalog_slug($catalog);
    $event = normalize_search_filter_choice($event, ['food', 'friends', 'family', 'date', 'active', 'culture', 'views']);
    $location = trim((string) $location);
    $price = normalize_search_filter_choice($price, ['100', '200', '300']);

    if ($query !== '') {
        $params['q'] = $query;
    }

    if ($catalog !== '') {
        $params['catalog'] = $catalog;
    }

    if ($event !== '') {
        $params['event'] = $event;
    }

    if ($location !== '') {
        $params['location'] = $location;
    }

    if ($price !== '') {
        $params['price'] = $price;
    }

    return 'search.php' . ($params ? '?' . http_build_query($params) : '');
}

// Render one search result card with save metadata and the destination detail URL.
function render_search_result_card($place, $loggedIn, $visitedLookup) {
    $place = is_array($place) ? $place : [];
    $placeId = (string) ($place['id'] ?? '');
    $source = (string) ($place['source'] ?? 'catalog');
    $isSaved = $placeId !== '' && isset($visitedLookup[$placeId]);
    $detailUrl = (string) ($place['detail_url'] ?? 'search.php');
    $catalogLabel = trim((string) ($place['catalog_label'] ?? ($place['category'] ?? 'Place')));
    $catalogIcon = trim((string) ($place['catalog_icon'] ?? 'layers-3'));
    $categoryLabel = trim((string) ($place['category'] ?? ''));
    $payload = [];

    if ($source === 'business') {
        $payload = [
            'business_id' => (int) ($place['business_id'] ?? 0),
            'location_id' => (int) ($place['location_id'] ?? 0),
            'name' => (string) ($place['name'] ?? ''),
            'description' => (string) ($place['description'] ?? ''),
            'address' => (string) ($place['address'] ?? ''),
            'website' => (string) ($place['website_url'] ?? ''),
            'photo_url' => (string) ($place['photo_url'] ?? ''),
            'offer_title' => (string) ($place['offer_title'] ?? ''),
        ];
    }

    $payloadJson = htmlspecialchars(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    $mediaStyle = !empty($place['photo_url'])
        ? " style=\"background-image:url('" . htmlspecialchars((string) $place['photo_url'], ENT_QUOTES, 'UTF-8') . "')\""
        : '';
    ?>
    <article class="result-card" data-result-href="<?php echo htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="result-media"<?php echo $mediaStyle; ?>>
            <?php if (empty($place['photo_url'])): ?>
            <i data-lucide="<?php echo htmlspecialchars((string) ($place['icon'] ?? 'map-pinned'), ENT_QUOTES, 'UTF-8'); ?>" style="width:54px;height:54px;"></i>
            <?php endif; ?>
        </div>
        <div>
            <h3 class="result-title"><?php echo htmlspecialchars((string) ($place['name'] ?? 'Where2Go place'), ENT_QUOTES, 'UTF-8'); ?></h3>
            <div class="result-subtitle"><?php echo htmlspecialchars((string) ($place['address'] ?? 'Cairo, Egypt'), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="result-tags">
            <span class="tag"><i data-lucide="<?php echo htmlspecialchars($catalogIcon, ENT_QUOTES, 'UTF-8'); ?>" style="width:14px;height:14px;"></i><?php echo htmlspecialchars($catalogLabel !== '' ? $catalogLabel : 'Place', ENT_QUOTES, 'UTF-8'); ?></span>
            <?php if ($categoryLabel !== '' && strcasecmp($categoryLabel, $catalogLabel) !== 0): ?>
            <span class="tag"><i data-lucide="layers-3" style="width:14px;height:14px;"></i><?php echo htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
            <span class="tag"><i data-lucide="<?php echo $source === 'business' ? 'badge-check' : 'sparkles'; ?>" style="width:14px;height:14px;"></i><?php echo htmlspecialchars($source === 'business' ? 'Approved partner' : 'Original pick', ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="tag"><i data-lucide="star" style="width:14px;height:14px;"></i><?php echo htmlspecialchars((string) ($place['rating'] ?? 'Featured'), ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($place['reviews']) ? ' (' . (int) $place['reviews'] . ')' : ''; ?></span>
            <span class="tag"><i data-lucide="wallet" style="width:14px;height:14px;"></i><?php echo htmlspecialchars((string) ($place['price_range'] ?? 'See details'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <p class="result-copy" style="margin:0;"><?php echo htmlspecialchars((string) ($place['description'] ?? 'Discover more on Where2Go.'), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if (!empty($place['offer_title'])): ?>
        <div class="result-tags">
            <span class="tag"><i data-lucide="ticket-percent" style="width:14px;height:14px;"></i><?php echo htmlspecialchars((string) $place['offer_title'], ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php endif; ?>
        <div class="result-actions">
            <a class="secondary-btn" href="<?php echo htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8'); ?>"><i data-lucide="arrow-up-right"></i>Open details</a>
            <button
                class="primary-btn<?php echo $isSaved ? ' is-saved' : ''; ?>"
                type="button"
                data-save-place="<?php echo htmlspecialchars($placeId, ENT_QUOTES, 'UTF-8'); ?>"
                data-track-source="<?php echo htmlspecialchars($source, ENT_QUOTES, 'UTF-8'); ?>"
                data-track-payload="<?php echo $payloadJson; ?>"
            >
                <i data-lucide="<?php echo $isSaved ? 'bookmark-check' : 'bookmark-plus'; ?>"></i>
                <?php echo $loggedIn ? ($isSaved ? 'Remove from profile' : 'Save to profile') : 'Login to save'; ?>
            </button>
        </div>
    </article>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Search Where2Go for Cairo restaurants, cafes, nightlife, entertainment, activities, and partner places.">
<title>Where2Go | Search</title>
<link rel="icon" type="image/png" href="assets/images/where2go_transparent_clean.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="assets/css/discover.css?v=20260513-catalog-filters-1">
</head>
<body class="dark-mode">
<!-- Search page header with navigation, account access, and the theme toggle. -->
<header class="topbar">
    <div class="topbar-inner">
        <div class="topbar-left">
            <a class="brand-link" href="Home.php" aria-label="Where2Go home">
                <img src="assets/images/where2go_transparent_clean.png" alt="Where2Go logo" class="logo">
            </a>
            <button class="theme-toggle" id="theme-toggle" type="button">
                <i data-lucide="moon-star" id="theme-icon"></i>
                <span id="theme-label">Dark mode</span>
            </button>
        </div>

        <nav class="topbar-right" aria-label="Search navigation">
            <a class="nav-link" href="Home.php">Home</a>
            <a class="nav-link is-active" href="<?php echo htmlspecialchars(build_search_page_url($effectiveQuery, $activeCatalog, $activeEvent, $activeLocation, $activePrice), ENT_QUOTES, 'UTF-8'); ?>">Search</a>
            <a class="nav-link" href="about.php">About</a>
            <?php if ($adminLoggedIn): ?>
            <a class="nav-link" href="Home.php#partners">Partners</a>
            <a class="nav-link" href="admin/dashboard.php">Admin</a>
            <a class="nav-link" href="admin/business-approvals.php">Approvals</a>
            <a class="nav-link" href="partner-login.php">Partner portal</a>
            <?php endif; ?>
            <?php if ($loggedIn): ?>
            <div class="profile-menu" data-profile-menu>
                <button class="profile-toggle" type="button" data-profile-toggle>
                    <span class="profile-avatar">
                        <?php if ($profilePhoto): ?>
                        <img src="<?php echo htmlspecialchars($profilePhoto, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile picture">
                        <?php else: ?>
                        <?php echo htmlspecialchars(strtoupper(substr($customerName !== '' ? $customerName : 'W', 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
                    </span>
                    <span><?php echo htmlspecialchars($customerName !== '' ? $customerName : 'My account', ENT_QUOTES, 'UTF-8'); ?></span>
                    <i data-lucide="chevrons-up-down"></i>
                </button>
                <div class="profile-dropdown" data-profile-dropdown>
                    <a class="profile-link" href="profile.php"><i data-lucide="user-round"></i><span>Profile</span></a>
                    <a class="profile-link" href="suggestions.php"><i data-lucide="sparkles"></i><span>Suggestions</span></a>
                    <a class="profile-link" href="logout.php"><i data-lucide="log-out"></i><span>Logout</span></a>
                </div>
            </div>
            <?php else: ?>
            <a class="nav-link" href="login.php">Login</a>
            <a class="nav-cta" href="register.php">Create account</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="main-inner">
    <!-- Search hero that explains the local-only catalog and exposes quick filters. -->
    <section class="hero-panel">
        <span class="hero-chip"><i data-lucide="search"></i>Local-only search</span>
        <h1 class="hero-title">Search places around Cairo</h1>
        <p class="hero-copy">Find restaurants, cafes, activities, entertainment, and local picks in one place.</p>
        <form class="search-form" action="search.php" method="GET">
            <i data-lucide="search" style="color:#8b6b57;"></i>
            <input id="search-input" type="text" name="q" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Try restaurant, cafe, nightlife, entertainment, or a place name">
            <?php if ($activeCatalog !== ''): ?>
            <input type="hidden" name="catalog" value="<?php echo htmlspecialchars($activeCatalog, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>
            <?php if ($activeEvent !== ''): ?>
            <input type="hidden" name="event" value="<?php echo htmlspecialchars($activeEvent, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>
            <?php if ($activeLocation !== ''): ?>
            <input type="hidden" name="location" value="<?php echo htmlspecialchars($activeLocation, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>
            <?php if ($activePrice !== ''): ?>
            <input type="hidden" name="price" value="<?php echo htmlspecialchars($activePrice, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>
            <button type="submit" aria-label="Search places"><i data-lucide="arrow-right"></i></button>
        </form>
        <div class="quick-pills" style="margin-top:16px;">
            <a class="quick-pill<?php echo $activeCatalog === 'restaurant' ? ' is-active' : ''; ?>" href="search.php?catalog=restaurant"><i data-lucide="utensils-crossed"></i>Restaurants</a>
            <a class="quick-pill<?php echo $activeCatalog === 'cafe' ? ' is-active' : ''; ?>" href="search.php?catalog=cafe"><i data-lucide="coffee"></i>Cafes</a>
            <a class="quick-pill<?php echo $activeCatalog === 'activity' ? ' is-active' : ''; ?>" href="search.php?catalog=activity"><i data-lucide="mountain-snow"></i>Activities</a>
            <a class="quick-pill<?php echo $activeCatalog === 'entertainment' ? ' is-active' : ''; ?>" href="search.php?catalog=entertainment"><i data-lucide="star"></i>Entertainment</a>
            <a class="quick-pill<?php echo $activeCatalog === 'nightlife' ? ' is-active' : ''; ?>" href="search.php?catalog=nightlife"><i data-lucide="music-4"></i>Nightlife</a>
            <a class="quick-pill<?php echo $activeCatalog === 'heritage' ? ' is-active' : ''; ?>" href="search.php?catalog=heritage"><i data-lucide="landmark"></i>Heritage</a>
        </div>
    </section>

    <div class="status-row">
        <div>
            <h2 class="section-title" style="margin:0 0 6px;">
                <?php if ($activeCatalogLabel !== '' && $effectiveQuery !== ''): ?>
                <?php echo htmlspecialchars('Results for "' . $effectiveQuery . '" in ' . $activeCatalogLabel, ENT_QUOTES, 'UTF-8'); ?>
                <?php elseif ($activeCatalogLabel !== ''): ?>
                <?php echo htmlspecialchars($activeCatalogLabel . ' places', ENT_QUOTES, 'UTF-8'); ?>
                <?php elseif ($query !== ''): ?>
                <?php echo htmlspecialchars('Results for "' . $query . '"', ENT_QUOTES, 'UTF-8'); ?>
                <?php else: ?>
                Browse all local places
                <?php endif; ?>
            </h2>
            <p class="section-copy" style="margin:0;">
                <?php echo count($searchResults); ?> results found<?php echo $activeFilterLabels ? ' for ' . htmlspecialchars(implode(' / ', $activeFilterLabels), ENT_QUOTES, 'UTF-8') : ''; ?>.
            </p>
        </div>
        <span class="status-badge" id="results-status"><i data-lucide="badge-check"></i><?php echo count($searchResults); ?> places ready</span>
    </div>

    <!-- Result grid that shows matching places or the empty-state fallback. -->
    <section class="results-shell">
        <?php if ($searchResults): ?>
        <div class="results-grid" id="results-grid">
            <?php foreach ($searchResults as $place): ?>
            <?php render_search_result_card($place, $loggedIn, $visitedLookup); ?>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-card" id="results-empty">
            <h3 style="margin-top:0;">No places matched that search</h3>
            <p>Try a broader catalog like restaurant, cafe, activity, entertainment, nightlife, heritage, or search for part of the business name or address.</p>
        </div>
        <?php endif; ?>
    </section>
</main>

<script>
// Share login and saved-place ids with the dedicated search page script.
window.where2goSearchData = <?php echo json_encode([
    'isLoggedIn' => $loggedIn,
    'visitedPlaceIds' => array_values($visitedPlaceIds),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/js/discover-search.js"></script>
</body>
</html>

<?php
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
$query = trim($_GET['q'] ?? '');
$searchResults = get_discovery_places($query);
$catalogCount = count(array_filter($searchResults, function ($place) {
    return ($place['source'] ?? '') === 'catalog';
}));
$businessCount = count(array_filter($searchResults, function ($place) {
    return ($place['source'] ?? '') === 'business';
}));

function render_search_result_card($place, $loggedIn, $visitedLookup) {
    $place = is_array($place) ? $place : [];
    $placeId = (string) ($place['id'] ?? '');
    $source = (string) ($place['source'] ?? 'catalog');
    $isSaved = $placeId !== '' && isset($visitedLookup[$placeId]);
    $detailUrl = (string) ($place['detail_url'] ?? 'search.php');
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
            <span class="tag"><i data-lucide="layers-3" style="width:14px;height:14px;"></i><?php echo htmlspecialchars((string) ($place['category'] ?? 'Place'), ENT_QUOTES, 'UTF-8'); ?></span>
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
<title>Where2Go | Search</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="assets/css/discover.css">
</head>
<body class="light-mode">
<header class="topbar">
    <div class="topbar-inner">
        <div class="topbar-left">
            <a class="brand-link" href="Home.php" aria-label="Where2Go home">
                <img src="assets/images/where2go_transparent.png" alt="Where2Go logo" class="logo">
            </a>
            <button class="theme-toggle" id="theme-toggle" type="button">
                <i data-lucide="sun-medium" id="theme-icon"></i>
                <span id="theme-label">Light mode</span>
            </button>
        </div>

        <nav class="topbar-right" aria-label="Search navigation">
            <a class="nav-link" href="Home.php">Home</a>
            <a class="nav-link is-active" href="search.php<?php echo $query !== '' ? '?q=' . rawurlencode($query) : ''; ?>">Search</a>
            <a class="nav-link" href="about.php">About</a>
            <?php if ($adminLoggedIn): ?>
            <a class="nav-link" href="admin/business-approvals.php">Approvals</a>
            <?php endif; ?>
            <?php if ($partnerLoggedIn): ?>
            <a class="nav-link" href="partner-dashboard.php"><?php echo htmlspecialchars($partnerName !== '' ? $partnerName : 'Partner dashboard', ENT_QUOTES, 'UTF-8'); ?></a>
            <?php else: ?>
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
    <section class="hero-panel">
        <span class="hero-chip"><i data-lucide="search"></i>Local-only search</span>
        <h1 class="hero-title">Search the original picks and approved partner businesses</h1>
        <p class="hero-copy">This page now searches only the places stored inside Where2Go. Partners appear here after approval, while the original 10 places remain part of every result set.</p>
        <form class="search-form" action="search.php" method="GET">
            <i data-lucide="search" style="color:#8b6b57;"></i>
            <input id="search-input" type="text" name="q" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Try restaurant, cafe, nightlife, entertainment, or a place name">
            <button type="submit" aria-label="Search places"><i data-lucide="arrow-right"></i></button>
        </form>
        <div class="quick-pills" style="margin-top:16px;">
            <a class="quick-pill" href="search.php?q=restaurant"><i data-lucide="utensils-crossed"></i>Restaurants</a>
            <a class="quick-pill" href="search.php?q=cafe"><i data-lucide="coffee"></i>Cafes</a>
            <a class="quick-pill" href="search.php?q=nightlife"><i data-lucide="music-4"></i>Nightlife</a>
            <a class="quick-pill" href="search.php?q=entertainment"><i data-lucide="star"></i>Entertainment</a>
            <a class="quick-pill" href="search.php?q=activity"><i data-lucide="mountain-snow"></i>Activities</a>
        </div>
    </section>

    <div class="status-row">
        <div>
            <h2 class="section-title" style="margin:0 0 6px;"><?php echo $query !== '' ? 'Results for "' . htmlspecialchars($query, ENT_QUOTES, 'UTF-8') . '"' : 'Browse all local places'; ?></h2>
            <p class="section-copy" style="margin:0;"><?php echo count($searchResults); ?> results found. <?php echo $catalogCount; ?> original picks and <?php echo $businessCount; ?> approved partner businesses matched.</p>
        </div>
        <span class="status-badge" id="results-status"><i data-lucide="badge-check"></i><?php echo count($searchResults); ?> places ready</span>
    </div>

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
            <p>Try a broader term like restaurant, cafe, nightlife, entertainment, or search for part of the business name or address.</p>
        </div>
        <?php endif; ?>
    </section>
</main>

<script>
window.where2goSearchData = <?php echo json_encode([
    'isLoggedIn' => $loggedIn,
    'visitedPlaceIds' => array_values($visitedPlaceIds),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/js/discover-search.js"></script>
</body>
</html>

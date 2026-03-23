<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/place_data.php';
require_once __DIR__ . '/config/maps.php';

start_session();

$placeId = trim($_GET['place_id'] ?? '');
$catalogId = trim($_GET['catalog_id'] ?? '');
$catalogPlace = $catalogId !== '' ? get_place_by_id($catalogId) : null;

if ($placeId === '' && !$catalogPlace) {
    header('Location: search.php');
    exit;
}

$loggedIn = is_logged_in();
$customerName = trim($_SESSION['customer_name'] ?? '');
$customerId = (int) ($_SESSION['customer_id'] ?? 0);
$profilePhoto = $loggedIn ? get_profile_photo_web_path($customerId) : null;
$visitedPlaceIds = get_visited_place_ids();
$visitedLookup = array_flip($visitedPlaceIds);
$mapsApiKey = get_google_maps_api_key();
$pageTitle = $catalogPlace['name'] ?? 'Place details';
$seedDescription = $catalogPlace['description'] ?? 'Live place details from Google Maps.';
$activePlaceId = $catalogId !== '' ? $catalogId : $placeId;
$isSaved = $activePlaceId !== '' && isset($visitedLookup[$activePlaceId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Where2Go | <?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
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

        <nav class="topbar-right" aria-label="Place navigation">
            <a class="nav-link" href="Home.php">Home</a>
            <a class="nav-link" href="search.php">Search</a>
            <a class="nav-link is-active" href="#">Place</a>
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
        <span class="hero-chip"><i data-lucide="map-pinned"></i>Place details</span>
        <h1 class="detail-title" id="detail-title"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="hero-copy" id="detail-summary"><?php echo htmlspecialchars($seedDescription, ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="hero-actions">
            <a class="secondary-btn" href="search.php"><i data-lucide="arrow-left"></i>Back to search</a>
            <button class="primary-btn<?php echo $isSaved ? ' is-saved' : ''; ?>" type="button" id="save-place-button"><i data-lucide="<?php echo $isSaved ? 'bookmark-check' : 'bookmark-plus'; ?>"></i><?php echo $loggedIn ? ($isSaved ? 'Remove from profile' : 'Save to profile') : 'Login to save'; ?></button>
            <button class="secondary-btn" type="button" id="toggle-map-button"><i data-lucide="map"></i>Show location</button>
        </div>
    </section>

    <section class="detail-panel">
        <div class="detail-layout">
            <div>
                <div class="gallery-main" id="gallery-main">
                    <i data-lucide="<?php echo htmlspecialchars($catalogPlace['icon'] ?? 'map-pinned', ENT_QUOTES, 'UTF-8'); ?>" style="width:64px;height:64px;"></i>
                </div>
                <div class="photo-credit" id="photo-credit" style="margin-top:10px;"></div>
                <div class="gallery-strip" id="gallery-strip"></div>
                <div class="detail-panel" style="margin-top:18px;">
                    <h2 class="section-title" style="margin-top:0;">What to expect</h2>
                    <p class="detail-copy" id="detail-copy"><?php echo htmlspecialchars($seedDescription, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="detail-panel" style="margin-top:18px;">
                    <h2 class="section-title" style="margin-top:0;">Recent reviews</h2>
                    <div class="review-grid" id="reviews-grid">
                        <div class="review-card"><p>Loading live details for this place.</p></div>
                    </div>
                </div>
            </div>

            <aside class="detail-side">
                <div class="detail-panel">
                    <h2 class="section-title" style="margin-top:0;">Live Google info</h2>
                    <div class="detail-meta" id="detail-meta"></div>
                    <div class="contact-list" id="contact-list" style="margin-top:14px;"></div>
                </div>
                <div class="map-shell hidden" id="detail-map-panel" style="position:static;min-height:auto;">
                    <div class="location-summary" id="detail-location-summary"></div>
                    <div class="contact-list" id="detail-location-links"></div>
                    <div id="detail-map" class="hidden"></div>
                    <div class="map-status" id="detail-map-status">Press "Show location" to open the location section.</div>
                </div>
            </aside>
        </div>
    </section>
</main>

<script>
window.where2goPlaceData = <?php echo json_encode([
    'placeId' => $placeId,
    'catalogId' => $catalogId,
    'catalogPlace' => $catalogPlace,
    'mapsApiKey' => $mapsApiKey,
    'isLoggedIn' => $loggedIn,
    'visitedPlaceIds' => array_values($visitedPlaceIds),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/js/place-detail.js"></script>
</body>
</html>

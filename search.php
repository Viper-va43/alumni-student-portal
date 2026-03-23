<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/maps.php';

start_session();

$loggedIn = is_logged_in();
$customerName = trim($_SESSION['customer_name'] ?? '');
$customerId = (int) ($_SESSION['customer_id'] ?? 0);
$profilePhoto = $loggedIn ? get_profile_photo_web_path($customerId) : null;
$visitedPlaceIds = get_visited_place_ids();
$mapsApiKey = get_google_maps_api_key();
$query = trim($_GET['q'] ?? '');

if ($query === '') {
    $query = 'nightlife';
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
            <a class="nav-link is-active" href="search.php?q=<?php echo rawurlencode($query); ?>">Search</a>
            <a class="nav-link" href="about.php">About</a>
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
        <span class="hero-chip"><i data-lucide="search"></i>Live Google results</span>
        <h1 class="hero-title">Search across more than the homepage cards</h1>
        <p class="hero-copy">This page turns the search bar into a real discovery tool. Type nightlife, cafes, live music, bowling, or a specific place and Where2Go will pull a broader set of options from Google Maps around Cairo.</p>
        <form class="search-form" action="search.php" method="GET">
            <i data-lucide="search" style="color:#8b6b57;"></i>
            <input id="search-input" type="text" name="q" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Try nightlife, restaurants, gaming, coffee, or a place name">
            <button type="submit" aria-label="Search places"><i data-lucide="arrow-right"></i></button>
        </form>
        <div class="quick-pills" style="margin-top:16px;">
            <a class="quick-pill" href="search.php?q=restaurants"><i data-lucide="utensils-crossed"></i>Restaurants</a>
            <a class="quick-pill" href="search.php?q=nightlife"><i data-lucide="music-4"></i>Nightlife</a>
            <a class="quick-pill" href="search.php?q=entertainment"><i data-lucide="star"></i>Entertainment</a>
            <a class="quick-pill" href="search.php?q=activities"><i data-lucide="mountain-snow"></i>Activities</a>
            <a class="quick-pill" href="search.php?q=coffee"><i data-lucide="coffee"></i>Cafes</a>
        </div>
    </section>

    <div class="status-row">
        <div>
            <h2 class="section-title" style="margin:0 0 6px;">Results for "<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>"</h2>
            <p class="section-copy" style="margin:0;">Ratings, photos, and addresses are pulled from Google Maps. Click any result to open the full place page.</p>
        </div>
        <span class="status-badge" id="results-status"><i data-lucide="map-pinned"></i>Preparing results</span>
    </div>

    <section class="results-shell">
        <div class="results-layout">
            <aside class="map-shell">
                <div id="search-map"></div>
                <div class="map-status" id="map-status">Connecting to Google Maps.</div>
            </aside>

            <div>
                <div class="results-grid" id="results-grid"></div>
                <div class="empty-card hidden" id="results-empty">
                    <h3 style="margin-top:0;">No places matched that search</h3>
                    <p>Try a broader search like nightlife, restaurants, or entertainment, or include an area such as Zamalek or New Cairo.</p>
                </div>
                <div class="load-more-wrap">
                    <button class="secondary-btn hidden" id="load-more-button" type="button"><i data-lucide="plus"></i>Load more results</button>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
window.where2goSearchData = <?php echo json_encode([
    'query' => $query,
    'mapsApiKey' => $mapsApiKey,
    'isLoggedIn' => $loggedIn,
    'visitedPlaceIds' => array_values($visitedPlaceIds),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/js/discover-search.js"></script>
</body>
</html>

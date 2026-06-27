<?php
// Load the logged-in customer's saved history so the page can recommend new places.
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/place_data.php';

start_session();
require_login();

$customerId = (int) ($_SESSION['customer_id'] ?? 0);
$customerName = trim($_SESSION['customer_name'] ?? 'Traveler');
$profilePhoto = get_profile_photo_web_path($customerId);
$visitedPlaceIds = get_visited_place_ids();
$suggestedPlaces = get_suggested_places($visitedPlaceIds, 6);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Get Where2Go suggestions based on the places you saved and visited around Cairo.">
<title>Where2Go | Suggestions</title>
<link rel="icon" type="image/png" href="assets/images/where2go_transparent_clean.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="assets/css/account.css?v=20260502-alignment-1">
</head>
<body class="dark-mode">
<!-- Suggestions header with profile access, navigation, and theme controls. -->
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

        <nav class="topbar-right" aria-label="Suggestions navigation">
            <a class="nav-link" href="Home.php">Home</a>
            <a class="nav-link" href="profile.php">Profile</a>
            <a class="nav-link" href="about.php">About</a>
            <div class="profile-menu" data-profile-menu>
                <button class="profile-toggle" type="button" data-profile-toggle>
                    <span class="profile-avatar">
                        <?php if ($profilePhoto): ?>
                        <img src="<?php echo htmlspecialchars($profilePhoto, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile picture">
                        <?php else: ?>
                        <?php echo htmlspecialchars(strtoupper(substr($customerName !== '' ? $customerName : 'W', 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
                    </span>
                    <span><?php echo htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'); ?></span>
                    <i data-lucide="chevrons-up-down"></i>
                </button>
                <div class="profile-dropdown" data-profile-dropdown>
                    <a class="profile-link" href="profile.php"><i data-lucide="user-round"></i><span>Profile</span></a>
                    <a class="profile-link" href="suggestions.php"><i data-lucide="sparkles"></i><span>Suggestions</span></a>
                    <a class="profile-link" href="logout.php"><i data-lucide="log-out"></i><span>Logout</span></a>
                </div>
            </div>
        </nav>
    </div>
</header>

<main class="main-inner">
    <!-- Hero section explaining why these recommendations were generated. -->
    <section class="hero-panel">
        <span class="eyebrow"><i data-lucide="sparkles"></i>Picked for you</span>
        <h1>Suggestions based on what you already saved</h1>
        <p>Find fresh ideas based on the places you already saved.</p>
        <div class="profile-stats">
            <span class="status-badge is-success"><i data-lucide="bookmark-check"></i><?php echo count($visitedPlaceIds); ?> places already in profile</span>
            <span class="status-badge"><i data-lucide="sparkles"></i><?php echo count($suggestedPlaces); ?> suggestions right now</span>
        </div>
        <div class="hero-actions">
            <a class="primary-btn" href="profile.php"><i data-lucide="user-round"></i>Back to profile</a>
            <a class="secondary-btn" href="search.php"><i data-lucide="search"></i>Search local places</a>
        </div>
    </section>

    <!-- Recommendation panel that lists suggested places or explains why none remain. -->
    <section class="panel-card" style="margin-top:24px;">
        <div class="section-row">
            <div>
                <h2 style="margin-bottom:8px;">Recommended next spots</h2>
                <p class="section-copy">New ideas appear here when they are not already in your saved list.</p>
            </div>
        </div>

        <?php if ($suggestedPlaces): ?>
        <div class="section-grid">
            <?php foreach ($suggestedPlaces as $place): ?>
            <article class="place-card" style="min-width:0;">
                <div class="place-media"><i data-lucide="<?php echo htmlspecialchars($place['icon'], ENT_QUOTES, 'UTF-8'); ?>" style="width:56px;height:56px;"></i></div>
                <div class="meta-row">
                    <span class="pill"><i data-lucide="layers-3"></i><?php echo htmlspecialchars($place['category'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="pill"><i data-lucide="map-pin"></i><?php echo htmlspecialchars($place['area'] . ', ' . $place['city'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div>
                    <h3 class="place-name"><?php echo htmlspecialchars($place['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="place-description"><?php echo htmlspecialchars($place['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="meta-row">
                    <span class="pill"><i data-lucide="wallet"></i><?php echo htmlspecialchars($place['price_range'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="pill"><i data-lucide="star"></i><?php echo htmlspecialchars($place['rating'], ENT_QUOTES, 'UTF-8'); ?> rating</span>
                </div>
                <div class="card-actions">
                    <button class="primary-btn" type="button" data-track-place="<?php echo htmlspecialchars($place['id'], ENT_QUOTES, 'UTF-8'); ?>"><i data-lucide="bookmark-plus"></i>Save to profile</button>
                    <a class="secondary-btn" href="place.php?catalog_id=<?php echo rawurlencode($place['id']); ?>"><i data-lucide="map"></i>Open details</a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="empty-card">
                <h3 style="margin-top:0;">You already covered every featured spot</h3>
                <p>Your profile already contains the available featured spots. Check back later for more ideas.</p>
                <div class="card-actions">
                    <a class="primary-btn" href="Home.php#places"><i data-lucide="compass"></i>Browse homepage places</a>
                </div>
        </div>
        <?php endif; ?>
    </section>
</main>

<script>
// Reuse the shared account script by exposing the current saved-place ids.
window.where2goPageData = <?php echo json_encode([
    'visitedPlaceIds' => array_values($visitedPlaceIds),
    'csrfToken' => get_csrf_token(),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/js/account.js"></script>
</body>
</html>

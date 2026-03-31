<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/place_data.php';

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
<title>Where2Go | Suggestions</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="assets/css/account.css">
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
    <section class="hero-panel">
        <span class="eyebrow"><i data-lucide="sparkles"></i>Picked for you</span>
        <h1>Suggestions based on what you already saved</h1>
        <p>Use this page through the account menu whenever you want fresh ideas. It stays close to your saved history and lets you add more places back into your profile in one click.</p>
        <div class="profile-stats">
            <span class="status-badge is-success"><i data-lucide="bookmark-check"></i><?php echo count($visitedPlaceIds); ?> places already in profile</span>
            <span class="status-badge"><i data-lucide="sparkles"></i><?php echo count($suggestedPlaces); ?> suggestions right now</span>
        </div>
        <div class="hero-actions">
            <a class="primary-btn" href="profile.php"><i data-lucide="user-round"></i>Back to profile</a>
            <a class="secondary-btn" href="search.php"><i data-lucide="search"></i>Search local places</a>
        </div>
    </section>

    <section class="panel-card" style="margin-top:24px;">
        <div class="section-row">
            <div>
                <h2 style="margin-bottom:8px;">Recommended next spots</h2>
                <p class="section-copy">These suggestions stay out of the places you already saved, so the page remains focused on new ideas instead of duplicates.</p>
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
                <p>The current suggestion list is empty because your profile already contains all of the places available right now. More approved partner businesses will show up here as the catalog grows.</p>
                <div class="card-actions">
                    <a class="primary-btn" href="Home.php#places"><i data-lucide="compass"></i>Browse homepage places</a>
                </div>
        </div>
        <?php endif; ?>
    </section>
</main>

<script>
window.where2goPageData = <?php echo json_encode([
    'visitedPlaceIds' => array_values($visitedPlaceIds),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/js/account.js"></script>
</body>
</html>

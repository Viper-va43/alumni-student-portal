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
$originalPlaces = array_map('normalize_catalog_place_for_discovery', get_place_catalog());
$approvedPartnerPlaces = array_map('normalize_public_business_for_discovery', get_public_businesses());
$partnerHighlights = array_slice($approvedPartnerPlaces, 0, 6);
$visitedPlaceIds = get_visited_place_ids();
$visitedLookup = array_flip($visitedPlaceIds);
$offerCount = count(array_filter($approvedPartnerPlaces, function ($place) {
    return !empty($place['has_offer']);
}));

function render_home_place_card($place, $loggedIn, $visitedLookup) {
    $place = is_array($place) ? $place : [];
    $placeId = (string) ($place['id'] ?? '');
    $isSaved = $placeId !== '' && isset($visitedLookup[$placeId]);
    $detailUrl = (string) ($place['detail_url'] ?? 'search.php');
    $trackSource = (string) ($place['source'] ?? 'catalog');
    $trackPayload = [];

    if ($trackSource === 'business') {
        $trackPayload = [
            'business_id' => (int) ($place['business_id'] ?? 0),
            'location_id' => (int) ($place['location_id'] ?? 0),
            'name' => (string) ($place['name'] ?? ''),
            'description' => (string) ($place['description'] ?? ''),
            'type' => (string) ($place['category'] ?? ''),
            'address' => (string) ($place['address'] ?? ''),
            'photo_url' => (string) ($place['photo_url'] ?? ''),
            'website' => (string) ($place['website_url'] ?? ''),
            'offer_title' => (string) ($place['offer_title'] ?? ''),
            'rating' => (string) ($place['rating'] ?? ''),
            'reviews' => (int) ($place['reviews'] ?? 0),
        ];
    }

    $payloadJson = htmlspecialchars(json_encode($trackPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    $photoStyle = !empty($place['photo_url'])
        ? " style=\"background-image:url('" . htmlspecialchars((string) $place['photo_url'], ENT_QUOTES, 'UTF-8') . "');background-size:cover;background-position:center;\""
        : '';
    $addressText = trim((string) ($place['address'] ?? ''));
    $priceText = trim((string) ($place['price_range'] ?? 'See details'));
    $ratingText = trim((string) ($place['rating'] ?? 'Featured'));
    ?>
    <article class="place-card" data-place-card data-place-id="<?php echo htmlspecialchars($placeId, ENT_QUOTES, 'UTF-8'); ?>" data-search="<?php echo htmlspecialchars((string) ($place['search_blob'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="place-media"<?php echo $photoStyle; ?>>
            <?php if (empty($place['photo_url'])): ?>
            <i data-lucide="<?php echo htmlspecialchars((string) ($place['icon'] ?? 'map-pinned'), ENT_QUOTES, 'UTF-8'); ?>" class="place-media-icon"></i>
            <?php endif; ?>
        </div>
        <div class="place-chip-row">
            <span class="place-chip"><i data-lucide="layers-3" class="tiny-icon"></i><?php echo htmlspecialchars((string) ($place['category'] ?? 'Place'), ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="place-chip"><i data-lucide="<?php echo ($trackSource === 'business' ? 'badge-check' : 'sparkles'); ?>" class="tiny-icon"></i><?php echo htmlspecialchars($trackSource === 'business' ? 'Approved partner' : 'Original pick', ENT_QUOTES, 'UTF-8'); ?></span>
            <?php if ($addressText !== ''): ?>
            <span class="place-chip"><i data-lucide="map-pin" class="tiny-icon"></i><?php echo htmlspecialchars($addressText, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </div>
        <div>
            <h3 class="place-name"><a href="<?php echo htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8'); ?>" style="text-decoration:none;color:inherit;"><?php echo htmlspecialchars((string) ($place['name'] ?? 'Where2Go place'), ENT_QUOTES, 'UTF-8'); ?></a></h3>
            <p class="place-description"><?php echo htmlspecialchars((string) ($place['description'] ?? 'Discover more on Where2Go.'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <div class="place-meta-row">
            <span class="price-pill"><i data-lucide="wallet" class="tiny-icon"></i><?php echo htmlspecialchars($priceText, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="rating-pill"><i data-lucide="star" class="tiny-icon"></i><?php echo htmlspecialchars($ratingText, ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($place['reviews']) ? ' (' . (int) $place['reviews'] . ')' : ''; ?></span>
        </div>
        <?php if (!empty($place['offer_title'])): ?>
        <div class="place-chip-row">
            <span class="place-chip"><i data-lucide="ticket-percent" class="tiny-icon"></i><?php echo htmlspecialchars((string) $place['offer_title'], ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php endif; ?>
        <div class="place-actions">
            <button
                class="view-btn<?php echo $isSaved ? ' is-saved' : ''; ?>"
                type="button"
                data-track-place="<?php echo htmlspecialchars($placeId, ENT_QUOTES, 'UTF-8'); ?>"
                data-track-source="<?php echo htmlspecialchars($trackSource, ENT_QUOTES, 'UTF-8'); ?>"
                data-track-payload="<?php echo $payloadJson; ?>"
            >
                <?php echo $loggedIn ? ($isSaved ? 'Remove from profile' : 'Save to profile') : 'Login to save'; ?>
            </button>
            <a class="ghost-btn" href="<?php echo htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8'); ?>"><i data-lucide="arrow-up-right"></i>Open details</a>
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
<title>Where2Go | Discover your next hangout</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="assets/css/home.css">
</head>
<body class="light-mode">
<div class="intro-screen" id="intro-screen" aria-hidden="true">
    <div class="intro-card">
        <img src="assets/images/where2go_transparent.png" alt="Where2Go logo" class="intro-logo">
        <div class="intro-copy">Browse the original picks, discover approved local businesses, and keep everything inside Where2Go.</div>
    </div>
</div>

<div class="page-shell">
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

            <nav class="topbar-right" aria-label="Main navigation">
                <a class="nav-link is-active" href="Home.php#home">Home</a>
                <a class="nav-link" href="Home.php#partners">Partners</a>
                <a class="nav-link" href="Home.php#places">Places</a>
                <a class="nav-link" href="search.php">Search</a>
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
                        <i data-lucide="chevrons-up-down" class="menu-chevron"></i>
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

    <main>
        <section class="hero-section" id="home">
            <div class="section-inner">
                <div class="hero-card">
                    <div class="hero-grid">
                        <div>
                            <span class="eyebrow"><i data-lucide="badge-check"></i>Local-first discovery platform</span>
                            <h1 class="hero-title">Find the place faster, then let approved partners bring the new options in.</h1>
                            <p class="hero-text">Where2Go now runs on the original 10 places we built together plus approved business submissions from real partners. No Google dependency, no outside search feed, and no business goes public until you approve it.</p>
                            <div class="hero-actions">
                                <a class="hero-button primary" href="#places"><i data-lucide="compass"></i>Browse original picks</a>
                                <a class="hero-button secondary" href="search.php"><i data-lucide="search"></i>Search local places</a>
                                <a class="hero-button secondary" href="<?php echo htmlspecialchars($partnerLoggedIn ? 'partner-dashboard.php' : 'partner-register.php', ENT_QUOTES, 'UTF-8'); ?>"><i data-lucide="store"></i><?php echo htmlspecialchars($partnerLoggedIn ? 'Open partner dashboard' : 'Become a partner', ENT_QUOTES, 'UTF-8'); ?></a>
                            </div>
                            <div class="hero-search">
                                <i data-lucide="search" class="hero-search-icon"></i>
                                <input id="search-input" type="text" placeholder="Search the original picks or approved partner businesses">
                                <button id="search-button" type="button" aria-label="Search places"><i data-lucide="arrow-right"></i></button>
                            </div>
                        </div>

                        <div class="hero-side">
                            <div class="hero-logo-card">
                                <img src="assets/images/where2go_transparent.png" alt="Where2Go logo" class="hero-logo">
                                <p>Partner businesses stay in a pending queue until you approve them, so the public experience stays clean and trusted.</p>
                            </div>
                            <div class="hero-stat-card">
                                <p>The homepage is now fully local: curated originals, approved partner businesses, and offers managed directly from your own dashboard flow.</p>
                                <div class="hero-stat-grid">
                                    <div class="hero-stat">
                                        <strong><?php echo count($originalPlaces); ?></strong>
                                        <span>Original picks</span>
                                    </div>
                                    <div class="hero-stat">
                                        <strong><?php echo count($approvedPartnerPlaces); ?></strong>
                                        <span>Approved partners</span>
                                    </div>
                                    <div class="hero-stat">
                                        <strong><?php echo $offerCount; ?></strong>
                                        <span>Live offers</span>
                                    </div>
                                    <div class="hero-stat">
                                        <strong id="visited-count"><?php echo count($visitedPlaceIds); ?></strong>
                                        <span>Saved to profile</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section" id="explore">
            <div class="section-inner">
                <div class="section-head">
                    <div>
                        <span class="section-kicker"><i data-lucide="layers-3"></i>Explore by category</span>
                        <h2 class="section-title">Start with the mood, then narrow down.</h2>
                        <p class="section-desc">These category shortcuts now search only the places stored inside Where2Go: the original 10 plus any approved partner business that belongs there.</p>
                    </div>
                </div>

                <div class="category-grid">
                    <article class="category-card" data-category-query="restaurant"><div class="category-icon"><i data-lucide="utensils-crossed"></i></div><div class="category-name">Restaurants</div></article>
                    <article class="category-card" data-category-query="cafe"><div class="category-icon"><i data-lucide="coffee"></i></div><div class="category-name">Cafes</div></article>
                    <article class="category-card" data-category-query="activity"><div class="category-icon"><i data-lucide="mountain-snow"></i></div><div class="category-name">Activities</div></article>
                    <article class="category-card" data-category-query="entertainment"><div class="category-icon"><i data-lucide="star"></i></div><div class="category-name">Entertainment</div></article>
                    <article class="category-card" data-category-query="nightlife"><div class="category-icon"><i data-lucide="music-4"></i></div><div class="category-name">Nightlife</div></article>
                </div>
            </div>
        </section>

        <section class="section" id="partners">
            <div class="section-inner">
                <div class="section-head">
                    <div>
                        <span class="section-kicker"><i data-lucide="store"></i>Approved partner businesses</span>
                        <h2 class="section-title">Businesses only appear here after admin approval.</h2>
                        <p class="section-desc">Partners can submit their place, menus, offers, hours, and reservation settings, but customers only see the listing after you validate it.</p>
                    </div>
                </div>

                <?php if ($partnerHighlights): ?>
                <div class="places-grid">
                    <?php foreach ($partnerHighlights as $place): ?>
                    <?php render_home_place_card($place, $loggedIn, $visitedLookup); ?>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="hero-stat-card">
                    <p>No partner business is approved yet. The original 10 places still power discovery while you test the partner portal and approval workflow.</p>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="section" id="places">
            <div class="section-inner">
                <div class="section-head">
                    <div>
                        <span class="section-kicker"><i data-lucide="sparkles"></i>The original 10 places</span>
                        <h2 class="section-title">Your starting list stays right here.</h2>
                        <p class="section-desc">These are the original places we added together. They remain the foundation of the homepage while approved partner businesses grow the catalog over time.</p>
                    </div>
                </div>

                <div class="places-grid" id="place-grid">
                    <?php foreach ($originalPlaces as $place): ?>
                    <?php render_home_place_card($place, $loggedIn, $visitedLookup); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="footer-inner">
            <div class="footer-grid">
                <div class="footer-card">
                    <img src="assets/images/where2go_transparent.png" alt="Where2Go logo" class="logo footer-logo">
                    <p>Where2Go now keeps discovery inside your own platform: curated original places, approved partner businesses, and customer saves that stay connected to the profile flow.</p>
                </div>
                <div class="footer-card">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="Home.php#home">Home</a></li>
                        <li><a href="Home.php#partners">Partners</a></li>
                        <li><a href="Home.php#places">Original picks</a></li>
                        <li><a href="search.php">Search</a></li>
                        <li><a href="about.php">About</a></li>
                    </ul>
                </div>
                <div class="footer-card">
                    <h4>Partner Portal</h4>
                    <ul>
                        <li><a href="partner-register.php">Register business account</a></li>
                        <li><a href="partner-login.php">Partner login</a></li>
                        <?php if ($partnerLoggedIn): ?>
                        <li><a href="partner-dashboard.php">Dashboard</a></li>
                        <?php endif; ?>
                        <?php if ($adminLoggedIn): ?>
                        <li><a href="admin/business-approvals.php">Admin approvals</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="footer-card">
                    <h4>Contact</h4>
                    <span>support@where2go.com</span><br>
                    <span>+20 XXX XXX XXXX</span><br>
                    <span>Cairo, Egypt</span>
                </div>
            </div>
            <div class="footer-bottom">Only approved partner businesses are public. Everything else stays pending until you review it.</div>
        </div>
    </footer>
</div>

<script>
window.where2goHomeData = <?php echo json_encode([
    'isLoggedIn' => $loggedIn,
    'visitedPlaceIds' => array_values($visitedPlaceIds),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/js/home.js"></script>
</body>
</html>

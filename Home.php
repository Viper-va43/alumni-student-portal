<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/place_data.php';
require_once __DIR__ . '/config/maps.php';

start_session();

$loggedIn = is_logged_in();
$customerName = trim($_SESSION['customer_name'] ?? '');
$customerId = (int) ($_SESSION['customer_id'] ?? 0);
$profilePhoto = $loggedIn ? get_profile_photo_web_path($customerId) : null;
$featuredPlaces = get_place_catalog();
$visitedPlaceIds = get_visited_place_ids();
$visitedLookup = array_flip($visitedPlaceIds);
$mapsApiKey = get_google_maps_api_key();
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
        <div class="intro-copy">Find where to go faster, then spend your time enjoying it.</div>
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
                <a class="nav-link" href="Home.php#explore">Explore</a>
                <a class="nav-link" href="Home.php#places">Places</a>
                <a class="nav-link" href="search.php?q=nightlife">Search</a>
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
                            <span class="eyebrow"><i data-lucide="map-pinned"></i>Cairo-ready discovery platform</span>
                            <h1 class="hero-title">Stop overthinking the plan. Find the right place in minutes.</h1>
                            <p class="hero-text">Where2Go helps you discover restaurants, activities, fun spots, and easy hangouts without bouncing between apps. Search, compare, save the places you like, and keep your next outing organized in one place.</p>
                            <div class="hero-actions">
                                <a class="hero-button primary" href="#places"><i data-lucide="compass"></i>Explore places</a>
                                <a class="hero-button secondary" href="search.php?q=nightlife"><i data-lucide="search"></i>Search live places</a>
                            </div>
                            <div class="hero-search">
                                <i data-lucide="search" class="hero-search-icon"></i>
                                <input id="search-input" type="text" placeholder="Search by place, category, city, or mood">
                                <button id="search-button" type="button" aria-label="Search places"><i data-lucide="arrow-right"></i></button>
                            </div>
                        </div>

                        <div class="hero-side">
                            <div class="hero-logo-card">
                                <img src="assets/images/where2go_transparent.png" alt="Where2Go logo" class="hero-logo">
                                <p>The homepage now uses the same real logo file from your project instead of the old recreated intro artwork.</p>
                            </div>
                            <div class="hero-stat-card">
                                <p>Keep planning simple with your own account history and quick access to live Google search when you want more than the homepage picks.</p>
                                <div class="hero-stat-grid">
                                    <div class="hero-stat">
                                        <strong><?php echo count($featuredPlaces); ?></strong>
                                        <span>Featured spots</span>
                                    </div>
                                    <div class="hero-stat">
                                        <strong id="visited-count"><?php echo count($visitedPlaceIds); ?></strong>
                                        <span>Saved to profile</span>
                                    </div>
                                    <div class="hero-stat">
                                        <strong>Live</strong>
                                        <span>Google search</span>
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
                        <h2 class="section-title">Pick the kind of outing you want first.</h2>
                        <p class="section-desc">These quick categories filter the mood fast, so the homepage feels more like a decision helper and less like another random list.</p>
                    </div>
                </div>

                <div class="category-grid">
                    <article class="category-card" data-category-query="restaurants"><div class="category-icon"><i data-lucide="utensils-crossed"></i></div><div class="category-name">Restaurants</div></article>
                    <article class="category-card" data-category-query="fun spots"><div class="category-icon"><i data-lucide="smile-plus"></i></div><div class="category-name">Fun Spots</div></article>
                    <article class="category-card" data-category-query="activities"><div class="category-icon"><i data-lucide="mountain-snow"></i></div><div class="category-name">Activities</div></article>
                    <article class="category-card" data-category-query="entertainment"><div class="category-icon"><i data-lucide="star"></i></div><div class="category-name">Entertainment</div></article>
                    <article class="category-card" data-category-query="nightlife"><div class="category-icon"><i data-lucide="music-4"></i></div><div class="category-name">Nightlife</div></article>
                </div>
            </div>
        </section>

        <section class="section" id="places">
            <div class="section-inner">
                <div class="section-head">
                    <div>
                        <span class="section-kicker"><i data-lucide="sparkles"></i>Featured places</span>
                        <h2 class="section-title">Save places you like straight into your profile.</h2>
                        <p class="section-desc">The partner area is removed from the homepage. These cards now focus only on browsing, saving, and returning to the places users care about.</p>
                    </div>
                </div>

                <div class="places-grid" id="place-grid">
                    <?php foreach ($featuredPlaces as $place): ?>
                    <?php $searchBlob = strtolower($place['name'] . ' ' . $place['category'] . ' ' . $place['area'] . ' ' . $place['city'] . ' ' . $place['description']); ?>
                    <article class="place-card" data-place-card data-place-id="<?php echo htmlspecialchars($place['id'], ENT_QUOTES, 'UTF-8'); ?>" data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="place-media" data-card-photo="<?php echo htmlspecialchars($place['id'], ENT_QUOTES, 'UTF-8'); ?>"><i data-lucide="<?php echo htmlspecialchars($place['icon'], ENT_QUOTES, 'UTF-8'); ?>" class="place-media-icon"></i></div>
                        <div class="photo-attribution" data-card-attribution="<?php echo htmlspecialchars($place['id'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="place-chip-row">
                            <span class="place-chip"><i data-lucide="layers-3" class="tiny-icon"></i><?php echo htmlspecialchars($place['category'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="place-chip" data-card-address="<?php echo htmlspecialchars($place['id'], ENT_QUOTES, 'UTF-8'); ?>"><i data-lucide="map-pin" class="tiny-icon"></i><?php echo htmlspecialchars($place['area'] . ', ' . $place['city'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div>
                            <h3 class="place-name"><a href="place.php?catalog_id=<?php echo rawurlencode($place['id']); ?>" style="text-decoration:none;color:inherit;"><?php echo htmlspecialchars($place['name'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                            <p class="place-description" data-card-description="<?php echo htmlspecialchars($place['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($place['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div class="place-meta-row">
                            <span class="price-pill"><i data-lucide="wallet" class="tiny-icon"></i><?php echo htmlspecialchars($place['price_range'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="rating-pill" data-card-rating="<?php echo htmlspecialchars($place['id'], ENT_QUOTES, 'UTF-8'); ?>"><i data-lucide="star" class="tiny-icon"></i><?php echo htmlspecialchars($place['rating'], ENT_QUOTES, 'UTF-8'); ?> rating</span>
                        </div>
                        <div class="place-actions">
                            <button class="view-btn<?php echo isset($visitedLookup[$place['id']]) ? ' is-saved' : ''; ?>" type="button" data-track-place="<?php echo htmlspecialchars($place['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo $loggedIn ? (isset($visitedLookup[$place['id']]) ? 'Remove from profile' : 'Save to profile') : 'Login to save'; ?>
                            </button>
                            <a class="ghost-btn" href="place.php?catalog_id=<?php echo rawurlencode($place['id']); ?>"><i data-lucide="arrow-up-right"></i>Open details</a>
                        </div>
                    </article>
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
                    <p>Where2Go helps people choose faster, save better ideas, and turn scattered plans into an easy night out.</p>
                </div>
                <div class="footer-card">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="Home.php#home">Home</a></li>
                        <li><a href="Home.php#explore">Explore</a></li>
                        <li><a href="Home.php#places">Places</a></li>
                        <li><a href="search.php?q=nightlife">Search</a></li>
                        <li><a href="about.php">About</a></li>
                    </ul>
                </div>
                <div class="footer-card">
                    <h4>Account</h4>
                    <ul>
                        <?php if ($loggedIn): ?>
                        <li><a href="profile.php">Profile</a></li>
                        <li><a href="suggestions.php">Suggestions</a></li>
                        <li><a href="logout.php">Logout</a></li>
                        <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Create account</a></li>
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
            <div class="footer-bottom">Where2Go keeps discovery focused on real places, with deeper live results through search.</div>
        </div>
    </footer>
</div>

<script>
window.where2goHomeData = <?php echo json_encode([
    'isLoggedIn' => $loggedIn,
    'mapsApiKey' => $mapsApiKey,
    'featuredPlaces' => array_values($featuredPlaces),
    'visitedPlaceIds' => array_values($visitedPlaceIds),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/js/home.js"></script>
</body>
</html>

<?php
// Load the logged-in customer's account data, saved places, and profile image details.
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/place_data.php';

start_session();
require_login();

$customerId = (int) ($_SESSION['customer_id'] ?? 0);
$customer = get_customer_by_id($customerId) ?: [];
$customerName = trim($customer['First_N'] ?? ($_SESSION['customer_name'] ?? 'Traveler'));
$profilePhoto = get_profile_photo_web_path($customerId);
$visitedPlaceIds = get_visited_place_ids();
$visitedPlaces = get_visited_places(12);
$customerBookings = get_customer_bookings($customerId);
$memberSince = '';
$messages = [];

if (!empty($customer['Created_At'])) {
    $timestamp = strtotime($customer['Created_At']);
    $memberSince = $timestamp ? date('F Y', $timestamp) : '';
}

// Handle profile photo uploads and validate the selected image before saving it.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    $file = $_FILES['profile_photo'];

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $messages[] = ['type' => 'error', 'text' => 'Your session expired. Refresh the page and try again.'];
    } elseif (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $messages[] = ['type' => 'error', 'text' => 'Choose an image before uploading your profile picture.'];
    } elseif (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $messages[] = ['type' => 'error', 'text' => 'The upload could not be completed. Try again.'];
    } elseif (($file['size'] ?? 0) > 3 * 1024 * 1024) {
        $messages[] = ['type' => 'error', 'text' => 'Profile pictures must be 3 MB or smaller.'];
    } else {
        $mime = mime_content_type($file['tmp_name']) ?: '';
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($allowedTypes[$mime])) {
            $messages[] = ['type' => 'error', 'text' => 'Use a JPG, PNG, or WEBP image for the profile picture.'];
        } else {
            $uploadDirectory = dirname(__DIR__, 2) . '/assets/images/uploads';

            if (!is_dir($uploadDirectory)) {
                mkdir($uploadDirectory, 0777, true);
            }

            foreach (glob($uploadDirectory . '/profile-' . $customerId . '.*') as $existingFile) {
                @unlink($existingFile);
            }

            $filePath = $uploadDirectory . '/profile-' . $customerId . '.' . $allowedTypes[$mime];

            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $messages[] = ['type' => 'success', 'text' => 'Your profile picture was updated.'];
                $profilePhoto = get_profile_photo_web_path($customerId);
            } else {
                $messages[] = ['type' => 'error', 'text' => 'The image could not be saved to the uploads folder.'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Manage your Where2Go profile, saved places, visit history, suggestions, and account photo.">
<title>Where2Go | Profile</title>
<link rel="icon" type="image/png" href="assets/images/where2go_transparent_clean.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="assets/css/account.css?v=20260502-alignment-1">
</head>
<body class="dark-mode">
<!-- Profile header with quick links back to discovery and account actions. -->
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

        <nav class="topbar-right" aria-label="Profile navigation">
            <a class="nav-link" href="Home.php">Home</a>
            <a class="nav-link" href="about.php">About</a>
            <a class="nav-link" href="suggestions.php">Suggestions</a>
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
    <!-- Profile hero summarizing the customer's saved-place activity and shortcuts. -->
    <section class="hero-panel">
        <span class="eyebrow"><i data-lucide="user-round"></i>Your profile</span>
        <h1><?php echo htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'); ?>'s Where2Go space</h1>
        <p>This is the account home for saved places, reservations, quick suggestions, and your profile picture.</p>
        <div class="profile-stats">
            <span class="status-badge is-success"><i data-lucide="bookmark-check"></i><?php echo count($visitedPlaceIds); ?> saved places</span>
            <span class="status-badge"><i data-lucide="calendar-check-2"></i><?php echo count($customerBookings); ?> reservations</span>
            <span class="status-badge"><i data-lucide="calendar-days"></i><?php echo $memberSince !== '' ? 'Member since ' . htmlspecialchars($memberSince, ENT_QUOTES, 'UTF-8') : 'Where2Go member'; ?></span>
        </div>
        <div class="hero-actions">
            <a class="primary-btn" href="suggestions.php"><i data-lucide="sparkles"></i>Open suggestions</a>
            <a class="secondary-btn" href="search.php"><i data-lucide="search"></i>Search local places</a>
        </div>
    </section>

    <section class="layout-grid">
        <!-- Left column for profile image management and account details. -->
        <aside class="panel-card">
            <h2>Profile picture</h2>
            <div class="upload-avatar">
                <?php if ($profilePhoto): ?>
                <img src="<?php echo htmlspecialchars($profilePhoto, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile picture">
                <?php else: ?>
                <?php echo htmlspecialchars(strtoupper(substr($customerName !== '' ? $customerName : 'W', 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </div>

            <?php if ($messages): ?>
            <div class="messages">
                <?php foreach ($messages as $message): ?>
                <div class="message <?php echo htmlspecialchars($message['type'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($message['text'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <p>Upload a clear image to make your account menu and profile page feel more personal.</p>

            <form action="profile.php" method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <div class="field">
                    <span>Choose image</span>
                    <input class="profile-file-input" id="profile_photo" type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp" data-file-input data-file-name-target="profile-photo-name">
                    <label class="file-picker" for="profile_photo">
                        <span class="file-picker-icon"><i data-lucide="image-plus"></i></span>
                        <span class="file-picker-copy">
                            <strong>Pick profile photo</strong>
                            <small id="profile-photo-name">JPG, PNG, or WEBP up to 3 MB</small>
                        </span>
                    </label>
                </div>
                <div class="card-actions" style="margin-top:16px;">
                    <button class="primary-btn" type="submit"><i data-lucide="upload"></i>Upload photo</button>
                </div>
            </form>

            <div class="detail-list">
                <div class="detail-row">
                    <strong>Email</strong>
                    <span><?php echo htmlspecialchars($customer['Email'] ?? ($_SESSION['customer_email'] ?? 'Not available'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Phone</strong>
                    <span><?php echo htmlspecialchars($customer['Customer_NUM'] ?? 'Not added yet', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Location</strong>
                    <span><?php echo htmlspecialchars($customer['Physical_Address'] ?? 'Not added yet', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        </aside>

        <!-- Right column showing the customer's most recently saved places. -->
        <section class="panel-card">
            <div class="section-row">
                <div>
                    <h2 style="margin-bottom:8px;">Reservations and saved places</h2>
                    <p class="section-copy">Your upcoming bookings and saved spots stay together here.</p>
                </div>
                <?php if (count($visitedPlaces) > 1): ?>
                <div class="card-actions">
                    <button class="slider-control" type="button" data-slider-target="visited-slider" data-slider-direction="prev"><i data-lucide="arrow-left"></i>Prev</button>
                    <button class="slider-control" type="button" data-slider-target="visited-slider" data-slider-direction="next">Next<i data-lucide="arrow-right"></i></button>
                </div>
                <?php endif; ?>
            </div>

            <h3 style="margin:0 0 10px;">My reservations</h3>
            <?php if ($customerBookings): ?>
            <div class="detail-list" style="margin-top:0;margin-bottom:18px;">
                <?php foreach ($customerBookings as $booking): ?>
                <div class="detail-row">
                    <strong><?php echo htmlspecialchars((string) ($booking['business_name'] ?? 'Business'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span><?php echo htmlspecialchars(date('M j, Y', strtotime((string) ($booking['date'] ?? 'now'))) . ' at ' . date('g:i A', strtotime((string) ($booking['time_slot'] ?? 'now'))), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><?php echo (int) ($booking['guests'] ?? 1); ?> guests - <?php echo htmlspecialchars(ucfirst((string) ($booking['status'] ?? 'pending')), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string) ($booking['location_address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="section-copy" style="margin-top:0;">No reservations yet. Open a partner place and pick an available time to book.</p>
            <?php endif; ?>

            <?php if ($visitedPlaces): ?>
            <div class="slider-shell">
                <div class="slider-track" id="visited-slider">
                    <?php foreach ($visitedPlaces as $place): ?>
                    <?php
                    $placeSource = $place['source'] ?? 'catalog';
                    $detailHref = $placeSource === 'google'
                        ? 'place.php?place_id=' . rawurlencode($place['place_id'] ?? $place['id'])
                        : ($placeSource === 'business'
                            ? ($place['detail_url'] ?? ('place.php?business_id=' . rawurlencode((string) ($place['business_id'] ?? ''))))
                            : 'place.php?catalog_id=' . rawurlencode($place['id']));
                    ?>
                    <article class="place-card">
                        <div class="place-media"<?php if (!empty($place['photo_url'])): ?> style="background-image:url('<?php echo htmlspecialchars($place['photo_url'], ENT_QUOTES, 'UTF-8'); ?>');background-size:cover;background-position:center;"<?php endif; ?>>
                            <?php if (empty($place['photo_url'])): ?>
                            <i data-lucide="<?php echo htmlspecialchars($place['icon'] ?? 'map-pinned', ENT_QUOTES, 'UTF-8'); ?>" style="width:56px;height:56px;"></i>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($place['photo_attribution'])): ?>
                        <div class="photo-attribution"><?php echo htmlspecialchars($place['photo_attribution'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        <div class="meta-row">
                            <span class="pill"><i data-lucide="layers-3"></i><?php echo htmlspecialchars($place['category'] ?? 'Saved place', ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="pill"><i data-lucide="wallet"></i><?php echo htmlspecialchars($place['price_range'] ?? '$$', ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div>
                            <h3 class="place-name"><?php echo htmlspecialchars($place['name'] ?? 'Saved place', ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="place-description"><?php echo htmlspecialchars($place['description'] ?? 'Saved from Where2Go.', ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div class="meta-row">
                            <span class="pill"><i data-lucide="map-pin"></i><?php echo htmlspecialchars(($place['address'] ?? (($place['area'] ?? '') . ', ' . ($place['city'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="pill"><i data-lucide="star"></i><?php echo htmlspecialchars(($place['rating'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?> rating</span>
                        </div>
                        <div class="card-actions">
                            <a class="secondary-btn" href="<?php echo htmlspecialchars($detailHref, ENT_QUOTES, 'UTF-8'); ?>"><i data-lucide="map"></i>Open details</a>
                            <a class="primary-btn" href="suggestions.php"><i data-lucide="sparkles"></i>Similar ideas</a>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="empty-card">
                <h3 style="margin-top:0;">No saved places yet</h3>
                <p>Go back to the homepage and use the save button on a place card. It will appear here and also shape your suggestions page.</p>
                <div class="card-actions">
                    <a class="primary-btn" href="Home.php#places"><i data-lucide="compass"></i>Browse places</a>
                </div>
            </div>
            <?php endif; ?>
        </section>
    </section>
</main>

<script>
// Pass the saved-place ids to the shared account JavaScript for button state and sliders.
window.where2goPageData = <?php echo json_encode([
    'visitedPlaceIds' => array_values($visitedPlaceIds),
    'csrfToken' => get_csrf_token(),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/js/account.js?v=20260502-file-picker-1"></script>
</body>
</html>

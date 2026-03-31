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
$businessId = (int) ($_GET['business_id'] ?? ($_POST['business_id'] ?? 0));
$catalogId = trim($_GET['catalog_id'] ?? '');
$catalogPlace = $catalogId !== '' ? get_place_by_id($catalogId) : null;
$business = $businessId > 0 ? get_business_by_id($businessId) : null;
$messages = [];
$source = 'catalog';
$pageTitle = 'Place details';
$pageSummary = 'Discover more on Where2Go.';
$activePlaceId = '';
$saveSource = 'catalog';
$savePayload = [];
$selectedLocationId = (int) ($_POST['location_id'] ?? ($_GET['location_id'] ?? 0));
$selectedGuests = max(1, (int) ($_POST['guests'] ?? ($_GET['guests'] ?? 1)));
$selectedDate = trim((string) ($_POST['reservation_date'] ?? ($_GET['reservation_date'] ?? '')));
$availableSlots = [];
$calendarDays = [];
$selectedLocation = null;
$businessCanBook = false;

function format_display_time($time) {
    $time = trim((string) $time);

    if ($time === '') {
        return 'Time not set';
    }

    $timestamp = strtotime($time);

    return $timestamp ? date('g:i A', $timestamp) : $time;
}

function build_location_hours_summary($hoursRows) {
    $hoursRows = is_array($hoursRows) ? $hoursRows : [];
    $lines = [];

    foreach ($hoursRows as $row) {
        $dayName = get_day_name_from_index((int) ($row['day_of_week'] ?? 0));

        if ((int) ($row['is_closed'] ?? 0) === 1) {
            $lines[] = $dayName . ': Closed';
            continue;
        }

        $openTime = trim((string) ($row['open_time'] ?? ''));
        $closeTime = trim((string) ($row['close_time'] ?? ''));
        $lines[] = $dayName . ': ' . format_display_time($openTime) . ' - ' . format_display_time($closeTime);
    }

    return $lines;
}

function get_location_display_label($location) {
    $location = is_array($location) ? $location : [];
    $locationName = trim((string) ($location['location_name'] ?? ''));
    $address = trim((string) ($location['address'] ?? ''));

    if ($locationName !== '') {
        return $locationName;
    }

    if ($address !== '') {
        return $address;
    }

    return 'Business location';
}

function get_location_guest_limit($location) {
    $location = is_array($location) ? $location : [];
    $tablesPerHour = max(1, (int) ($location['capacity_per_hour'] ?? 1));

    return max(4, $tablesPerHour * 4);
}

if ($businessId > 0) {
    if (!$business || !can_current_user_access_business($business)) {
        http_response_code(404);
        header('Location: search.php');
        exit;
    }

    $source = 'business';
    $pageTitle = (string) ($business['name'] ?? 'Business details');
    $pageSummary = trim((string) ($business['description'] ?? 'Business details on Where2Go.'));
    $activePlaceId = (string) $businessId;
    $saveSource = 'business';
    $savePayload = [
        'business_id' => $businessId,
        'location_id' => (int) ($business['primary_location']['location_id'] ?? 0),
        'name' => (string) ($business['name'] ?? ''),
        'description' => (string) ($business['description'] ?? ''),
        'address' => (string) ($business['primary_location']['address'] ?? ''),
        'website' => (string) ($business['website'] ?? ''),
        'photo_url' => (string) ($business['photo_url'] ?? ''),
    ];

    if (($business['approval_status'] ?? '') === 'approved') {
        record_business_view($businessId);
    } else {
        $messages[] = [
            'type' => 'info',
            'text' => 'This business is in ' . (string) $business['approval_status'] . ' status and is visible only to the owner or admin.',
        ];
    }

    if ($selectedLocationId <= 0 && !empty($business['primary_location']['location_id'])) {
        $selectedLocationId = (int) $business['primary_location']['location_id'];
    }

    foreach (($business['locations'] ?? []) as $location) {
        if ((int) ($location['location_id'] ?? 0) === $selectedLocationId) {
            $selectedLocation = $location;
            break;
        }
    }

    if (!$selectedLocation && !empty($business['locations'])) {
        foreach ($business['locations'] as $location) {
            if ((int) ($location['has_reservations'] ?? 0) === 1) {
                $selectedLocation = $location;
                break;
            }
        }

        if (!$selectedLocation) {
            $selectedLocation = $business['locations'][0];
        }

        $selectedLocationId = (int) ($selectedLocation['location_id'] ?? 0);
    }

    $businessCanBook = $selectedLocation && (int) ($selectedLocation['has_reservations'] ?? 0) === 1;

    if ($selectedLocation) {
        $selectedGuests = min($selectedGuests, get_location_guest_limit($selectedLocation));
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'book_location') {
        if (!$loggedIn) {
            $messages[] = ['type' => 'error', 'text' => 'Login with a customer account before making a reservation.'];
        } elseif (!$selectedLocation || (int) ($selectedLocation['has_reservations'] ?? 0) !== 1) {
            $messages[] = ['type' => 'error', 'text' => 'Choose a location that accepts reservations.'];
        } else {
            $selectedTimeSlot = trim((string) ($_POST['time_slot'] ?? ''));
            $bookingCreated = create_booking($customerId, $selectedLocationId, $selectedDate, $selectedTimeSlot, $selectedGuests);

            if ($bookingCreated) {
                $messages[] = ['type' => 'success', 'text' => 'Your reservation was sent successfully and is waiting for confirmation.'];
            } else {
                $messages[] = ['type' => 'error', 'text' => 'That time is no longer available. Pick another slot and try again.'];
            }
        }
    }

    if ($businessCanBook && $selectedLocationId > 0) {
        $calendarDays = get_location_booking_calendar_days($selectedLocationId, date('Y-m-d'), 21, $selectedGuests);
    }

    if ($businessCanBook && $selectedDate !== '') {
        $availableSlots = get_available_booking_slots($selectedLocationId, $selectedDate, 60, $selectedGuests);
        $hasAvailableSlot = false;

        foreach ($availableSlots as $slot) {
            if (!empty($slot['available'])) {
                $hasAvailableSlot = true;
                break;
            }
        }

        if (!$availableSlots || !$hasAvailableSlot) {
            $hoursForDate = get_location_hours_for_date($selectedLocationId, $selectedDate);

            if (!$hoursForDate || (int) ($hoursForDate['is_closed'] ?? 0) === 1) {
                $messages[] = ['type' => 'info', 'text' => 'This location is closed on the selected date.'];
            } else {
                $messages[] = ['type' => 'info', 'text' => 'No booking slots are available for the selected date and guest count.'];
            }
        }
    }
} elseif ($catalogPlace) {
    $catalogPlace = normalize_catalog_place_for_discovery($catalogPlace);
    $pageTitle = (string) ($catalogPlace['name'] ?? 'Place details');
    $pageSummary = (string) ($catalogPlace['description'] ?? 'Discover more on Where2Go.');
    $activePlaceId = (string) ($catalogPlace['id'] ?? '');
    $savePayload = [];
} else {
    header('Location: search.php');
    exit;
}

$isSaved = $activePlaceId !== '' && isset($visitedLookup[$activePlaceId]);
$savePayloadJson = htmlspecialchars(json_encode($savePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
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
        <span class="hero-chip">
            <i data-lucide="<?php echo $source === 'business' ? 'badge-check' : 'sparkles'; ?>"></i>
            <?php echo htmlspecialchars($source === 'business' ? (($business['approval_status'] ?? 'approved') === 'approved' ? 'Approved partner business' : 'Private business preview') : 'Original Where2Go pick', ENT_QUOTES, 'UTF-8'); ?>
        </span>
        <h1 class="detail-title" id="detail-title"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="hero-copy" id="detail-summary"><?php echo htmlspecialchars($pageSummary, ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="hero-actions">
            <a class="secondary-btn" href="search.php"><i data-lucide="arrow-left"></i>Back to search</a>
            <button
                class="primary-btn<?php echo $isSaved ? ' is-saved' : ''; ?>"
                type="button"
                id="save-place-button"
                data-track-place="<?php echo htmlspecialchars($activePlaceId, ENT_QUOTES, 'UTF-8'); ?>"
                data-track-source="<?php echo htmlspecialchars($saveSource, ENT_QUOTES, 'UTF-8'); ?>"
                data-track-payload="<?php echo $savePayloadJson; ?>"
            >
                <i data-lucide="<?php echo $isSaved ? 'bookmark-check' : 'bookmark-plus'; ?>"></i>
                <?php echo $loggedIn ? ($isSaved ? 'Remove from profile' : 'Save to profile') : 'Login to save'; ?>
            </button>
            <?php if ($source === 'business' && !empty($business['website'])): ?>
            <a class="secondary-btn" href="<?php echo htmlspecialchars((string) $business['website'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><i data-lucide="globe"></i>Visit website</a>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($messages): ?>
    <section class="detail-panel" style="margin-bottom:20px;">
        <?php foreach ($messages as $message): ?>
        <div class="status-badge" style="justify-content:flex-start;margin:8px 0;background:<?php echo $message['type'] === 'success' ? 'rgba(31,138,86,0.12)' : ($message['type'] === 'error' ? 'rgba(198,81,8,0.12)' : 'var(--panel-soft)'); ?>;color:<?php echo $message['type'] === 'success' ? 'var(--success)' : ($message['type'] === 'error' ? 'var(--accent)' : 'var(--muted)'); ?>;">
            <i data-lucide="<?php echo $message['type'] === 'success' ? 'check-circle-2' : ($message['type'] === 'error' ? 'alert-circle' : 'info'); ?>"></i>
            <?php echo htmlspecialchars((string) $message['text'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <section class="detail-panel">
        <div class="detail-layout">
            <div>
                <div class="gallery-main" id="gallery-main"<?php if ($source === 'business' && !empty($business['photo_url'])): ?> style="background-image:url('<?php echo htmlspecialchars((string) $business['photo_url'], ENT_QUOTES, 'UTF-8'); ?>');background-size:cover;background-position:center;"<?php endif; ?>>
                    <?php if ($source === 'catalog' || ($source === 'business' && empty($business['photo_url']))): ?>
                    <i data-lucide="<?php echo htmlspecialchars($source === 'business' ? (string) ($business['icon'] ?? 'building-2') : (string) ($catalogPlace['icon'] ?? 'map-pinned'), ENT_QUOTES, 'UTF-8'); ?>" style="width:64px;height:64px;"></i>
                    <?php endif; ?>
                </div>

                <?php if ($source === 'business' && !empty($business['photos'])): ?>
                <div class="gallery-strip" id="gallery-strip">
                    <?php foreach ($business['photos'] as $index => $photo): ?>
                    <a class="gallery-thumb<?php echo $index === 0 ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars((string) ($photo['image_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" style="background-image:url('<?php echo htmlspecialchars((string) ($photo['image_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>');"></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="detail-panel" style="margin-top:18px;">
                    <h2 class="section-title" style="margin-top:0;">What to expect</h2>
                    <p class="detail-copy" id="detail-copy">
                        <?php if ($source === 'business'): ?>
                        <?php echo htmlspecialchars((string) ($business['description'] ?? 'This business is listed on Where2Go.'), ENT_QUOTES, 'UTF-8'); ?>
                        <?php else: ?>
                        <?php echo htmlspecialchars((string) ($catalogPlace['description'] ?? 'This is one of the original Where2Go places.'), ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
                    </p>
                </div>

                <?php if ($source === 'business' && !empty($business['menus'])): ?>
                <div class="detail-panel" style="margin-top:18px;">
                    <h2 class="section-title" style="margin-top:0;">Menus</h2>
                    <div class="contact-list">
                        <?php foreach ($business['menus'] as $menu): ?>
                        <a href="<?php echo htmlspecialchars((string) ($menu['file_url'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><i data-lucide="book-open-text" style="width:16px;height:16px;"></i><?php echo htmlspecialchars((string) (($menu['title'] ?? '') !== '' ? $menu['title'] : 'Open menu'), ENT_QUOTES, 'UTF-8'); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="detail-panel" style="margin-top:18px;">
                    <h2 class="section-title" style="margin-top:0;"><?php echo $source === 'business' ? 'Customer reviews' : 'Why it made the original list'; ?></h2>
                    <div class="review-grid" id="reviews-grid">
                        <?php if ($source === 'business' && !empty($business['reviews'])): ?>
                        <?php foreach ($business['reviews'] as $review): ?>
                        <?php $reviewAuthor = trim((string) (($review['First_N'] ?? '') . ' ' . ($review['Last_N'] ?? ''))); ?>
                        <article class="review-card">
                            <h3><?php echo htmlspecialchars($reviewAuthor !== '' ? $reviewAuthor : 'Where2Go customer', ENT_QUOTES, 'UTF-8'); ?></h3>
                            <div class="detail-meta" style="margin-bottom:10px;">
                                <span class="meta-pill"><i data-lucide="star" style="width:14px;height:14px;"></i><?php echo (int) ($review['rating'] ?? 0); ?>/5</span>
                                <span class="meta-pill"><i data-lucide="clock-3" style="width:14px;height:14px;"></i><?php echo htmlspecialchars(date('M j, Y', strtotime((string) ($review['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <p><?php echo htmlspecialchars((string) ($review['comment'] ?? 'No comment shared yet.'), ENT_QUOTES, 'UTF-8'); ?></p>
                        </article>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div class="review-card"><p><?php echo htmlspecialchars($source === 'business' ? 'This business does not have public reviews yet.' : 'This place is one of the original Where2Go picks you added together for the platform launch.', ENT_QUOTES, 'UTF-8'); ?></p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <aside class="detail-side">
                <div class="detail-panel">
                    <h2 class="section-title" style="margin-top:0;">Details</h2>
                    <div class="detail-meta" id="detail-meta">
                        <?php if ($source === 'business'): ?>
                        <span class="meta-pill"><i data-lucide="layers-3" style="width:14px;height:14px;"></i><?php echo htmlspecialchars((string) ($business['type_label'] ?? 'Business'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="meta-pill"><i data-lucide="star" style="width:14px;height:14px;"></i><?php echo htmlspecialchars($business['average_rating'] !== null ? number_format((float) $business['average_rating'], 1) : 'New', ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($business['review_count']) ? ' (' . (int) $business['review_count'] . ')' : ''; ?></span>
                        <?php if (!empty($business['primary_location']['address'])): ?>
                        <span class="meta-pill"><i data-lucide="map-pin" style="width:14px;height:14px;"></i><?php echo htmlspecialchars((string) $business['primary_location']['address'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="meta-pill"><i data-lucide="layers-3" style="width:14px;height:14px;"></i><?php echo htmlspecialchars((string) ($catalogPlace['category'] ?? 'Place'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="meta-pill"><i data-lucide="wallet" style="width:14px;height:14px;"></i><?php echo htmlspecialchars((string) ($catalogPlace['price_range'] ?? '$$'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if (!empty($catalogPlace['address'])): ?>
                        <span class="meta-pill"><i data-lucide="map-pin" style="width:14px;height:14px;"></i><?php echo htmlspecialchars((string) $catalogPlace['address'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="contact-list" id="contact-list" style="margin-top:14px;">
                        <?php if ($source === 'business'): ?>
                        <?php if (!empty($business['primary_location']['phone'])): ?>
                        <a href="tel:<?php echo htmlspecialchars((string) $business['primary_location']['phone'], ENT_QUOTES, 'UTF-8'); ?>"><i data-lucide="phone" style="width:16px;height:16px;"></i><?php echo htmlspecialchars((string) $business['primary_location']['phone'], ENT_QUOTES, 'UTF-8'); ?></a>
                        <?php endif; ?>
                        <?php if (!empty($business['website'])): ?>
                        <a href="<?php echo htmlspecialchars((string) $business['website'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><i data-lucide="globe" style="width:16px;height:16px;"></i>Visit website</a>
                        <?php endif; ?>
                        <span><i data-lucide="shield-check" style="width:16px;height:16px;"></i><?php echo htmlspecialchars(ucfirst((string) ($business['approval_status'] ?? 'pending')) . ' listing', ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php else: ?>
                        <?php if (!empty($catalogPlace['address'])): ?>
                        <span><i data-lucide="map-pin" style="width:16px;height:16px;"></i><?php echo htmlspecialchars((string) $catalogPlace['address'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <span><i data-lucide="sparkles" style="width:16px;height:16px;"></i>Original Where2Go discovery pick</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($source === 'business' && !empty($business['active_offers'])): ?>
                <div class="detail-panel" style="margin-top:18px;">
                    <h2 class="section-title" style="margin-top:0;">Active offers</h2>
                    <div class="contact-list">
                        <?php foreach ($business['active_offers'] as $offer): ?>
                        <span><i data-lucide="ticket-percent" style="width:16px;height:16px;"></i><?php echo htmlspecialchars((string) ($offer['title'] ?? 'Offer'), ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($offer['discount']) ? ' - ' . rtrim(rtrim((string) $offer['discount'], '0'), '.') . '%' : ''; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($source === 'business' && trim((string) ($business['rules'] ?? '')) !== ''): ?>
                <div class="detail-panel" style="margin-top:18px;">
                    <h2 class="section-title" style="margin-top:0;">Rules</h2>
                    <p class="detail-copy"><?php echo nl2br(htmlspecialchars((string) $business['rules'], ENT_QUOTES, 'UTF-8')); ?></p>
                </div>
                <?php endif; ?>

                <?php if ($source === 'business' && !empty($business['locations'])): ?>
                <div class="detail-panel" style="margin-top:18px;">
                    <h2 class="section-title" style="margin-top:0;">Locations</h2>
                    <div class="contact-list" style="display:grid;gap:14px;">
                        <?php foreach ($business['locations'] as $location): ?>
                        <div style="padding:14px;border:1px solid var(--border);border-radius:22px;background:var(--panel-soft);">
                            <strong style="display:block;margin-bottom:8px;"><?php echo htmlspecialchars(get_location_display_label($location), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <?php if (!empty($location['location_name']) && !empty($location['address'])): ?>
                            <div class="result-subtitle" style="margin-bottom:8px;"><?php echo htmlspecialchars((string) $location['address'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($location['phone'])): ?>
                            <div class="result-subtitle" style="margin-bottom:8px;"><?php echo htmlspecialchars((string) $location['phone'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                            <div class="detail-meta" style="margin-bottom:10px;">
                                <span class="meta-pill"><i data-lucide="<?php echo (int) ($location['has_reservations'] ?? 0) === 1 ? 'calendar-check-2' : 'ban'; ?>" style="width:14px;height:14px;"></i><?php echo (int) ($location['has_reservations'] ?? 0) === 1 ? 'Reservations enabled' : 'Walk-in only'; ?></span>
                            </div>
                            <?php foreach (build_location_hours_summary($location['hours'] ?? []) as $line): ?>
                            <div class="result-subtitle"><?php echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($source === 'business' && $selectedLocation && (int) ($selectedLocation['has_reservations'] ?? 0) === 1): ?>
                <div class="detail-panel" style="margin-top:18px;">
                    <h2 class="section-title" style="margin-top:0;">Reservation request</h2>
                    <form action="place.php?business_id=<?php echo $businessId; ?>" method="GET" style="display:grid;gap:14px;">
                        <input type="hidden" name="business_id" value="<?php echo $businessId; ?>">
                        <?php if ($selectedDate !== ''): ?>
                        <input type="hidden" name="reservation_date" value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php endif; ?>
                        <label style="display:grid;gap:8px;">
                            <span>Location</span>
                            <select name="location_id" style="padding:12px 14px;border-radius:16px;border:1px solid var(--border);background:var(--panel-strong);color:var(--text);">
                                <?php foreach ($business['locations'] as $location): ?>
                                <?php if ((int) ($location['has_reservations'] ?? 0) !== 1) { continue; } ?>
                                <option value="<?php echo (int) $location['location_id']; ?>"<?php echo (int) $location['location_id'] === $selectedLocationId ? ' selected' : ''; ?>><?php echo htmlspecialchars(get_location_display_label($location), ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label style="display:grid;gap:8px;">
                            <span>Guests</span>
                            <input type="number" name="guests" min="1" max="<?php echo get_location_guest_limit($selectedLocation); ?>" value="<?php echo $selectedGuests; ?>" style="padding:12px 14px;border-radius:16px;border:1px solid var(--border);background:var(--panel-strong);color:var(--text);">
                        </label>
                        <p class="result-subtitle" style="margin:0;">We arrange reservations in tables of up to 4 people. Choose your group size and then pick a highlighted day.</p>
                        <button class="secondary-btn" type="submit"><i data-lucide="calendar-search"></i>Refresh availability</button>
                    </form>

                    <?php if ($calendarDays): ?>
                    <div class="availability-panel">
                        <div class="availability-head">
                            <strong>Pick a day</strong>
                            <div class="availability-legend">
                                <span class="availability-legend-chip is-available">Available</span>
                                <span class="availability-legend-chip is-full">Full</span>
                                <span class="availability-legend-chip is-closed">Closed</span>
                            </div>
                        </div>
                        <form action="place.php?business_id=<?php echo $businessId; ?>" method="GET" class="availability-grid-form">
                            <input type="hidden" name="business_id" value="<?php echo $businessId; ?>">
                            <input type="hidden" name="location_id" value="<?php echo $selectedLocationId; ?>">
                            <input type="hidden" name="guests" value="<?php echo $selectedGuests; ?>">
                            <div class="availability-grid">
                                <?php foreach ($calendarDays as $day): ?>
                                <?php
                                $dayStatus = trim((string) ($day['status'] ?? 'closed'));
                                $dayDate = trim((string) ($day['date'] ?? ''));
                                $isSelectedDay = $selectedDate !== '' && $selectedDate === $dayDate;
                                $buttonClasses = 'availability-day is-' . $dayStatus . ($isSelectedDay ? ' is-selected' : '');
                                $statusLabel = $dayStatus === 'available' ? 'Open' : ($dayStatus === 'full' ? 'Full' : 'Closed');
                                ?>
                                <button
                                    class="<?php echo htmlspecialchars($buttonClasses, ENT_QUOTES, 'UTF-8'); ?>"
                                    type="submit"
                                    name="reservation_date"
                                    value="<?php echo htmlspecialchars($dayDate, ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php echo $dayStatus === 'available' ? '' : 'disabled'; ?>
                                >
                                    <span><?php echo htmlspecialchars(date('D', strtotime($dayDate)), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <strong><?php echo htmlspecialchars(date('j', strtotime($dayDate)), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <small><?php echo htmlspecialchars(date('M', strtotime($dayDate)), ENT_QUOTES, 'UTF-8'); ?></small>
                                    <em><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></em>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <?php if ($selectedDate !== ''): ?>
                    <div class="availability-panel" style="margin-top:16px;display:grid;gap:12px;">
                        <strong>Available hours for <?php echo htmlspecialchars(date('F j, Y', strtotime($selectedDate)), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <?php if ($availableSlots): ?>
                        <form action="place.php?business_id=<?php echo $businessId; ?>" method="POST" style="display:grid;gap:12px;">
                            <input type="hidden" name="action" value="book_location">
                            <input type="hidden" name="business_id" value="<?php echo $businessId; ?>">
                            <input type="hidden" name="location_id" value="<?php echo $selectedLocationId; ?>">
                            <input type="hidden" name="reservation_date" value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="guests" value="<?php echo $selectedGuests; ?>">
                            <div class="slot-grid">
                                <?php foreach ($availableSlots as $slot): ?>
                                <button
                                    class="slot-pill<?php echo !empty($slot['available']) ? ' is-available' : ' is-unavailable'; ?>"
                                    type="submit"
                                    name="time_slot"
                                    value="<?php echo htmlspecialchars((string) $slot['time'], ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php echo !empty($slot['available']) ? '' : 'disabled'; ?>
                                >
                                    <i data-lucide="<?php echo !empty($slot['available']) ? 'clock-3' : 'ban'; ?>" style="width:14px;height:14px;"></i>
                                    <?php echo htmlspecialchars(format_display_time((string) $slot['time']), ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            <p class="result-subtitle" style="margin:0;">Greyed times are already full for your group size.</p>
                        </form>
                        <?php else: ?>
                        <p class="result-subtitle" style="margin:0;">Pick another date, location, or guest count to see more availability.</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </aside>
        </div>
    </section>
</main>

<script>
window.where2goPlaceData = <?php echo json_encode([
    'isLoggedIn' => $loggedIn,
    'visitedPlaceIds' => array_values($visitedPlaceIds),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/js/place-detail.js"></script>
</body>
</html>

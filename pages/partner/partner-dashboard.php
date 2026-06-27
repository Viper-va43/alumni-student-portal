<?php
// Load partner-owned businesses, metrics, and reservations for the dashboard view.
require_once dirname(__DIR__, 2) . '/includes/functions.php';

start_session();
require_partner_login();

$partnerId = (int) ($_SESSION['partner_id'] ?? 0);
$messages = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'update_reservation_status') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $result = ['ok' => false, 'message' => 'Your session expired. Refresh the page and try again.'];
    } else {
        $bookingId = (int) ($_POST['booking_id'] ?? 0);
        $reservationStatus = trim((string) ($_POST['status'] ?? ''));
        $result = update_partner_booking_status($partnerId, $bookingId, $reservationStatus);
    }

    $messages[] = [
        'type' => !empty($result['ok']) ? 'success' : 'error',
        'text' => (string) ($result['message'] ?? 'The reservation could not be updated right now.'),
    ];
}

$partner = get_partner_by_id($partnerId) ?: [];
$partnerName = trim((string) ($partner['owner_name'] ?? ($_SESSION['partner_name'] ?? 'Partner')));
$summary = get_partner_dashboard_summary($partnerId);
$businesses = get_partner_businesses($partnerId);
$upcomingReservations = get_partner_upcoming_reservations($partnerId, 8);
$calendarStartRaw = trim((string) ($_GET['calendar_start'] ?? ''));
$calendarStartTimestamp = strtotime($calendarStartRaw !== '' ? $calendarStartRaw : date('Y-m-d'));
$calendarStart = $calendarStartTimestamp ? date('Y-m-d', $calendarStartTimestamp) : date('Y-m-d');
$reservationCalendar = get_partner_reservation_calendar($partnerId, $calendarStart, 14);
$previousCalendarStart = date('Y-m-d', strtotime($calendarStart . ' -14 days'));
$nextCalendarStart = date('Y-m-d', strtotime($calendarStart . ' +14 days'));
$adminLoggedIn = is_admin_user();

foreach ($businesses as $index => $business) {
    $businesses[$index]['locations'] = get_business_locations((int) ($business['business_id'] ?? 0));
    $businesses[$index]['completion'] = get_partner_business_profile_completion($businesses[$index]);
}

// Format reservation times into a readable label inside the dashboard cards.
function partner_dashboard_time_label($time) {
    $time = trim((string) $time);

    if ($time === '') {
        return 'Time not set';
    }

    $timestamp = strtotime($time);

    return $timestamp ? date('g:i A', $timestamp) : $time;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Manage Where2Go partner businesses, approval status, reservations, QR codes, and business details.">
<title>Where2Go | Partner Dashboard</title>
<link rel="icon" type="image/png" href="assets/images/where2go_transparent_clean.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="assets/css/account.css?v=20260502-alignment-1">
<link rel="stylesheet" href="assets/css/partner-portal.css">
</head>
<body class="dark-mode">
<!-- Partner dashboard header with shortcuts to business management and sign-out. -->
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

        <nav class="topbar-right" aria-label="Partner dashboard navigation">
            <a class="nav-link" href="Home.php">Home</a>
            <a class="nav-link" href="search.php">Search</a>
            <?php if ($adminLoggedIn): ?>
            <a class="nav-link" href="admin/dashboard.php">Admin</a>
            <a class="nav-link" href="admin/business-approvals.php">Approvals</a>
            <?php endif; ?>
            <a class="secondary-btn" href="partner-business-form.php"><i data-lucide="plus"></i>Add business</a>
            <a class="primary-btn" href="partner-logout.php"><i data-lucide="log-out"></i>Logout</a>
        </nav>
    </div>
</header>

<main class="main-inner">
    <!-- Hero summary for the current partner account and key dashboard actions. -->
    <section class="hero-panel partner-hero-panel">
        <span class="eyebrow"><i data-lucide="layout-dashboard"></i>Partner dashboard</span>
        <h1><?php echo htmlspecialchars($partnerName, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p>Manage your businesses, monitor clicks and reservations, and see upcoming customer bookings across your saved locations.</p>
        <div class="profile-stats">
            <span class="status-badge"><i data-lucide="mail"></i><?php echo htmlspecialchars((string) ($partner['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="status-badge"><i data-lucide="shield-check"></i>Listings stay editable from your dashboard</span>
            <span class="status-badge"><i data-lucide="calendar-check-2"></i>Reservations show customer names and times</span>
        </div>
        <div class="hero-actions">
            <a class="primary-btn" href="partner-business-form.php"><i data-lucide="plus"></i>Add a business</a>
            <?php if ($businesses): ?>
            <a class="secondary-btn" href="partner-business-form.php?business_id=<?php echo (int) ($businesses[0]['business_id'] ?? 0); ?>"><i data-lucide="pencil"></i>Edit latest business</a>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($messages): ?>
    <div class="messages" style="margin-top:24px;">
        <?php foreach ($messages as $message): ?>
        <div class="message <?php echo htmlspecialchars((string) $message['type'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $message['text'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Dashboard metrics covering listings, views, reservations, and active offers. -->
    <section class="stat-grid" style="margin-top:24px;">
        <article class="stat-card">
            <span class="mini-note">Businesses</span>
            <strong><?php echo (int) ($summary['business_count'] ?? 0); ?></strong>
            <span class="mini-note">Listings connected to your partner account.</span>
        </article>
        <article class="stat-card">
            <span class="mini-note">Views</span>
            <strong><?php echo (int) ($summary['view_count'] ?? 0); ?></strong>
            <span class="mini-note">Public detail-page visits on approved businesses.</span>
        </article>
        <article class="stat-card">
            <span class="mini-note">Reservations</span>
            <strong><?php echo (int) ($summary['reservation_count'] ?? 0); ?></strong>
            <span class="mini-note">Total reservation requests across your locations.</span>
        </article>
        <article class="stat-card">
            <span class="mini-note">Upcoming</span>
            <strong><?php echo (int) ($summary['upcoming_reservation_count'] ?? 0); ?></strong>
            <span class="mini-note">Pending or confirmed bookings from today forward.</span>
        </article>
        <article class="stat-card">
            <span class="mini-note">Live offers</span>
            <strong><?php echo (int) ($summary['active_offer_count'] ?? 0); ?></strong>
            <span class="mini-note">Offers currently active on your approved businesses.</span>
        </article>
    </section>

    <section class="panel-card reservation-calendar-panel" style="margin-top:24px;">
        <div class="section-row">
            <div>
                <h2 style="margin-bottom:8px;">Reservation calendar</h2>
                <p class="section-copy">A two-week view of customer bookings across all your locations.</p>
            </div>
            <div class="action-row">
                <a class="secondary-btn" href="partner-dashboard.php?calendar_start=<?php echo urlencode($previousCalendarStart); ?>"><i data-lucide="chevron-left"></i>Previous</a>
                <a class="secondary-btn" href="partner-dashboard.php"><i data-lucide="calendar-days"></i>Today</a>
                <a class="secondary-btn" href="partner-dashboard.php?calendar_start=<?php echo urlencode($nextCalendarStart); ?>">Next<i data-lucide="chevron-right"></i></a>
            </div>
        </div>

        <div class="reservation-calendar-grid">
            <?php foreach ($reservationCalendar as $day): ?>
            <?php
            $dateValue = (string) ($day['date'] ?? date('Y-m-d'));
            $items = is_array($day['items'] ?? null) ? $day['items'] : [];
            ?>
            <article class="calendar-day-card<?php echo $items ? ' has-bookings' : ''; ?>">
                <div class="calendar-day-head">
                    <strong><?php echo htmlspecialchars(date('D, M j', strtotime($dateValue)), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span class="mini-note"><?php echo count($items); ?> bookings</span>
                </div>
                <?php if ($items): ?>
                <div class="calendar-booking-list">
                    <?php foreach (array_slice($items, 0, 4) as $item): ?>
                    <?php $itemStatus = trim((string) ($item['status'] ?? 'pending')); ?>
                    <div class="calendar-booking-item">
                        <span class="status-pill <?php echo htmlspecialchars($itemStatus, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(ucfirst($itemStatus), ENT_QUOTES, 'UTF-8'); ?></span>
                        <strong><?php echo htmlspecialchars(partner_dashboard_time_label((string) ($item['time_slot'] ?? '')), ENT_QUOTES, 'UTF-8'); ?> - <?php echo (int) ($item['guests'] ?? 1); ?> guests</strong>
                        <span class="mini-note"><?php echo htmlspecialchars((string) ($item['business_name'] ?? 'Business'), ENT_QUOTES, 'UTF-8'); ?><?php echo trim((string) ($item['location_name'] ?? '')) !== '' ? ' - ' . htmlspecialchars((string) $item['location_name'], ENT_QUOTES, 'UTF-8') : ''; ?></span>
                        <span class="mini-note"><?php echo htmlspecialchars(trim((string) ($item['user_name'] ?? 'Where2Go customer')), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (count($items) > 4): ?>
                    <span class="mini-note">+<?php echo count($items) - 4; ?> more on this day</span>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <p class="mini-note">No reservations.</p>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Main dashboard workspace for business management and upcoming reservations. -->
    <section class="layout-grid">
        <section class="panel-card">
            <div class="section-row">
                <div>
                    <h2 style="margin-bottom:8px;">Your businesses</h2>
                    <p class="section-copy">Manage your listings and keep business details current.</p>
                </div>
                <a class="secondary-btn" href="partner-business-form.php"><i data-lucide="plus"></i>New business</a>
            </div>

            <?php if ($businesses): ?>
            <div class="dashboard-list">
                <?php foreach ($businesses as $business): ?>
                <?php $status = trim((string) ($business['approval_status'] ?? 'pending')); ?>
                <?php $searchTags = get_business_search_tag_list($business['search_tags'] ?? ''); ?>
                <?php $completion = is_array($business['completion'] ?? null) ? $business['completion'] : ['percent' => 0, 'completed' => 0, 'total' => 0, 'missing' => []]; ?>
                <article class="dashboard-item">
                    <div class="dashboard-item-head">
                        <div>
                            <h3 style="margin:0 0 6px;"><?php echo htmlspecialchars((string) ($business['name'] ?? 'Business'), ENT_QUOTES, 'UTF-8'); ?></h3>
                            <div class="mini-note"><?php echo htmlspecialchars((string) ($business['primary_address'] ?? 'Address not added yet'), ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <span class="status-pill <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                            <i data-lucide="<?php echo $status === 'approved' ? 'badge-check' : ($status === 'rejected' ? 'x-circle' : 'clock-3'); ?>"></i>
                            <?php echo htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>

                    <div class="detail-meta">
                        <span class="meta-pill"><i data-lucide="layers-3" style="width:14px;height:14px;"></i><?php echo htmlspecialchars((string) ($business['type_label'] ?? 'Business'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="meta-pill"><i data-lucide="mouse-pointer-click" style="width:14px;height:14px;"></i><?php echo (int) ($business['total_views'] ?? 0); ?> views</span>
                        <span class="meta-pill"><i data-lucide="calendar-check-2" style="width:14px;height:14px;"></i><?php echo (int) ($business['total_bookings'] ?? 0); ?> bookings</span>
                        <span class="meta-pill"><i data-lucide="ticket-percent" style="width:14px;height:14px;"></i><?php echo (int) ($business['active_offers'] ?? 0); ?> active offers</span>
                        <span class="meta-pill"><i data-lucide="map-pin" style="width:14px;height:14px;"></i><?php echo count($business['locations'] ?? []); ?> locations</span>
                    </div>

                    <?php if ($searchTags): ?>
                    <div class="detail-meta">
                        <?php foreach (array_slice($searchTags, 0, 6) as $tag): ?>
                        <span class="meta-pill"><i data-lucide="tag" style="width:14px;height:14px;"></i><?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="profile-completion">
                        <div class="dashboard-item-head">
                            <strong>Profile <?php echo (int) ($completion['percent'] ?? 0); ?>% complete</strong>
                            <span class="mini-note"><?php echo (int) ($completion['completed'] ?? 0); ?>/<?php echo (int) ($completion['total'] ?? 0); ?> items ready</span>
                        </div>
                        <div class="completion-meter" aria-hidden="true"><span style="width:<?php echo max(0, min(100, (int) ($completion['percent'] ?? 0))); ?>%;"></span></div>
                        <?php if (!empty($completion['missing'])): ?>
                        <p class="mini-note">Missing: <?php echo htmlspecialchars(implode(', ', array_slice((array) $completion['missing'], 0, 4)), ENT_QUOTES, 'UTF-8'); ?><?php echo count((array) $completion['missing']) > 4 ? ', and more' : ''; ?>.</p>
                        <?php else: ?>
                        <p class="mini-note">This listing has the key details customers expect.</p>
                        <?php endif; ?>
                    </div>

                    <div class="action-row">
                        <a class="secondary-btn" href="partner-business-form.php?business_id=<?php echo (int) ($business['business_id'] ?? 0); ?>"><i data-lucide="pencil"></i>Edit</a>
                        <a class="secondary-btn" href="place.php?business_id=<?php echo (int) ($business['business_id'] ?? 0); ?>"><i data-lucide="eye"></i>Preview</a>
                    </div>

                    <?php if ($status === 'rejected' && trim((string) ($business['review_note'] ?? '')) !== ''): ?>
                    <p class="mini-note review-note-text">Admin note: <?php echo htmlspecialchars((string) $business['review_note'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-block">
                <h3 style="margin-top:0;">No business submitted yet</h3>
                <p>Create your first listing and prepare the page customers will see.</p>
                <div class="action-row">
                    <a class="primary-btn" href="partner-business-form.php"><i data-lucide="plus"></i>Add your first business</a>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <aside class="panel-card">
            <h2>Upcoming reservations</h2>
            <p class="section-copy">This list shows pending and confirmed requests across all your reservable locations.</p>

            <?php if ($upcomingReservations): ?>
            <div class="stack-list">
                <?php foreach ($upcomingReservations as $reservation): ?>
                <?php $reservationStatus = trim((string) ($reservation['status'] ?? 'pending')); ?>
                <div class="repeat-card">
                    <div class="dashboard-item-head">
                        <strong><?php echo htmlspecialchars((string) ($reservation['business_name'] ?? 'Business'), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span class="status-pill <?php echo htmlspecialchars($reservationStatus, ENT_QUOTES, 'UTF-8'); ?>">
                            <i data-lucide="<?php echo $reservationStatus === 'confirmed' ? 'badge-check' : 'clock-3'; ?>"></i>
                            <?php echo htmlspecialchars(ucfirst($reservationStatus), ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>
                    <p><?php echo htmlspecialchars((string) ($reservation['location_address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="detail-meta">
                        <span class="meta-pill"><i data-lucide="calendar-days" style="width:14px;height:14px;"></i><?php echo htmlspecialchars(date('M j, Y', strtotime((string) ($reservation['date'] ?? 'now'))), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="meta-pill"><i data-lucide="clock-3" style="width:14px;height:14px;"></i><?php echo htmlspecialchars(partner_dashboard_time_label((string) ($reservation['time_slot'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="meta-pill"><i data-lucide="users" style="width:14px;height:14px;"></i><?php echo (int) ($reservation['guests'] ?? 1); ?></span>
                    </div>
                    <p class="mini-note"><?php echo htmlspecialchars(trim((string) ($reservation['user_name'] ?? 'Where2Go customer')), ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($reservation['user_email']) ? ' - ' . htmlspecialchars((string) $reservation['user_email'], ENT_QUOTES, 'UTF-8') : ''; ?></p>
                    <form action="partner-dashboard.php" method="POST" class="action-row reservation-action-row">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="update_reservation_status">
                        <input type="hidden" name="booking_id" value="<?php echo (int) ($reservation['id'] ?? 0); ?>">
                        <?php if ($reservationStatus === 'pending'): ?>
                        <button class="secondary-btn" type="submit" name="status" value="canceled"><i data-lucide="x-circle"></i>Reject</button>
                        <button class="primary-btn" type="submit" name="status" value="confirmed"><i data-lucide="badge-check"></i>Approve</button>
                        <?php elseif ($reservationStatus === 'confirmed'): ?>
                        <button class="secondary-btn" type="submit" name="status" value="canceled"><i data-lucide="x-circle"></i>Cancel</button>
                        <button class="primary-btn" type="submit" name="status" value="completed"><i data-lucide="check-circle-2"></i>Complete</button>
                        <?php endif; ?>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-block">
                <p>No upcoming reservations yet. Once customers start booking your approved locations, they will appear here.</p>
            </div>
            <?php endif; ?>
        </aside>
    </section>

</main>

<script>
// Keep the shared account script initialized even though this page does not expose saved places.
window.where2goPageData = <?php echo json_encode(['visitedPlaceIds' => []], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/js/account.js"></script>
<script src="assets/js/partner-portal.js"></script>
</body>
</html>

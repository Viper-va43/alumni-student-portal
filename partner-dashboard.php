<?php
// Load partner-owned businesses, metrics, and reservations for the dashboard view.
require_once __DIR__ . '/includes/functions.php';

start_session();
require_partner_login();

$partnerId = (int) ($_SESSION['partner_id'] ?? 0);
$messages = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'refresh_location_qr') {
    $refreshResult = refresh_partner_location_qr_token($partnerId, (int) ($_POST['location_id'] ?? 0));
    $redirectUrl = 'partner-dashboard.php';

    if (!empty($refreshResult['ok'])) {
        $redirectUrl .= '?qr_updated=1';
    } else {
        $redirectUrl .= '?qr_error=' . rawurlencode((string) ($refreshResult['message'] ?? 'The QR code could not be refreshed.'));
    }

    header('Location: ' . $redirectUrl);
    exit;
}

$qrUpdated = ($_GET['qr_updated'] ?? '') === '1';
$qrError = trim((string) ($_GET['qr_error'] ?? ''));

if ($qrUpdated) {
    $messages[] = ['type' => 'success', 'text' => 'A fresh QR code was generated for the selected location.'];
} elseif ($qrError !== '') {
    $messages[] = ['type' => 'error', 'text' => $qrError];
}

$partner = get_partner_by_id($partnerId) ?: [];
$partnerName = trim((string) ($partner['owner_name'] ?? ($_SESSION['partner_name'] ?? 'Partner')));
$summary = get_partner_dashboard_summary($partnerId);
$businesses = get_partner_businesses($partnerId);
$upcomingReservations = get_partner_upcoming_reservations($partnerId, 8);
$recentCheckins = get_partner_recent_checkins($partnerId, 8);
$adminLoggedIn = is_admin_user();

foreach ($businesses as $index => $business) {
    $businesses[$index]['locations'] = get_business_locations((int) ($business['business_id'] ?? 0));
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
<title>Where2Go | Partner Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="assets/css/account.css">
<link rel="stylesheet" href="assets/css/partner-portal.css">
</head>
<body class="light-mode">
<!-- Partner dashboard header with shortcuts to business management and sign-out. -->
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

        <nav class="topbar-right" aria-label="Partner dashboard navigation">
            <a class="nav-link" href="Home.php">Home</a>
            <a class="nav-link" href="search.php">Search</a>
            <?php if ($adminLoggedIn): ?>
            <a class="nav-link" href="admin/business-approvals.php">Approvals</a>
            <a class="nav-link" href="admin/reward-config.php">Reward config</a>
            <?php endif; ?>
            <a class="secondary-btn" href="partner-business-form.php"><i data-lucide="plus"></i>Add business</a>
            <a class="primary-btn" href="partner-logout.php"><i data-lucide="log-out"></i>Logout</a>
        </nav>
    </div>
</header>

<main class="main-inner">
    <!-- Hero summary for the current partner account and key dashboard actions. -->
    <section class="hero-panel">
        <span class="eyebrow"><i data-lucide="layout-dashboard"></i>Partner dashboard</span>
        <h1><?php echo htmlspecialchars($partnerName, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p>Manage your businesses, monitor clicks and reservations, and now generate in-store QR codes for each saved location so customers can unlock promo codes and reward points.</p>
        <div class="profile-stats">
            <span class="status-badge"><i data-lucide="mail"></i><?php echo htmlspecialchars((string) ($partner['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="status-badge"><i data-lucide="shield-check"></i>Approved businesses stay editable from your dashboard</span>
            <span class="status-badge"><i data-lucide="qr-code"></i>Every saved location gets its own QR check-in link</span>
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
        <article class="stat-card">
            <span class="mini-note">QR check-ins</span>
            <strong><?php echo (int) ($summary['checkin_count'] ?? 0); ?></strong>
            <span class="mini-note">Customer scans successfully claimed across your locations.</span>
        </article>
        <article class="stat-card">
            <span class="mini-note">Points issued</span>
            <strong><?php echo (int) ($summary['points_issued'] ?? 0); ?></strong>
            <span class="mini-note">Reward points credited to customers from your QR scans.</span>
        </article>
    </section>

    <!-- Main dashboard workspace for business management and upcoming reservations. -->
    <section class="layout-grid">
        <section class="panel-card">
            <div class="section-row">
                <div>
                    <h2 style="margin-bottom:8px;">Your businesses</h2>
                    <p class="section-copy">You can keep updating your business details from here. If a listing is rejected, the admin note stays visible so you know what to fix.</p>
                </div>
                <a class="secondary-btn" href="partner-business-form.php"><i data-lucide="plus"></i>New business</a>
            </div>

            <?php if ($businesses): ?>
            <div class="dashboard-list">
                <?php foreach ($businesses as $business): ?>
                <?php $status = trim((string) ($business['approval_status'] ?? 'pending')); ?>
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
                        <span class="meta-pill"><i data-lucide="qr-code" style="width:14px;height:14px;"></i><?php echo count($business['locations'] ?? []); ?> QR locations</span>
                    </div>

                    <div class="action-row">
                        <a class="secondary-btn" href="partner-business-form.php?business_id=<?php echo (int) ($business['business_id'] ?? 0); ?>"><i data-lucide="pencil"></i>Edit</a>
                        <a class="secondary-btn" href="place.php?business_id=<?php echo (int) ($business['business_id'] ?? 0); ?>"><i data-lucide="eye"></i>Preview</a>
                    </div>

                    <?php if (!empty($business['locations'])): ?>
                    <div class="stack-list qr-location-list">
                        <?php foreach ($business['locations'] as $location): ?>
                        <?php
                        $locationLabel = trim((string) ($location['location_name'] ?? '')) !== ''
                            ? (string) $location['location_name']
                            : (string) ($location['address'] ?? 'Business location');
                        $promoCode = trim((string) ($location['promo_code'] ?? ''));
                        $promoDetails = trim((string) ($location['promo_details'] ?? ''));
                        $checkinUrl = trim((string) ($location['checkin_url'] ?? ''));
                        ?>
                        <div class="repeat-card qr-location-card">
                            <div class="dashboard-item-head">
                                <div>
                                    <h3 style="margin:0 0 6px;"><?php echo htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <p class="mini-note" style="margin:0;"><?php echo htmlspecialchars((string) ($location['address'] ?? 'Address not added yet'), ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <span class="status-pill <?php echo !empty($location['checkin_enabled']) ? 'approved' : 'pending'; ?>">
                                    <i data-lucide="<?php echo !empty($location['checkin_enabled']) ? 'badge-check' : 'ban'; ?>"></i>
                                    <?php echo !empty($location['checkin_enabled']) ? 'QR active' : 'QR paused'; ?>
                                </span>
                            </div>

                            <div class="qr-grid">
                                <div class="qr-copy">
                                    <div class="detail-meta" style="margin-bottom:12px;">
                                        <span class="meta-pill"><i data-lucide="ticket" style="width:14px;height:14px;"></i><?php echo htmlspecialchars($promoCode !== '' ? $promoCode : 'No promo code yet', ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="meta-pill"><i data-lucide="star" style="width:14px;height:14px;"></i>20 points + 20 XP</span>
                                    </div>
                                    <p class="mini-note" style="margin:0 0 12px;"><?php echo htmlspecialchars($promoDetails !== '' ? $promoDetails : 'Add promo details in the business form if customers should see a specific code or reward message for this location.', ENT_QUOTES, 'UTF-8'); ?></p>
                                    <p class="mini-note" style="margin:0 0 14px;">Level 1 currently starts at 100 XP. Customers can only earn one reward per location per day, with the app-wide daily place limit enforced automatically.</p>
                                    <div class="action-row">
                                        <?php if ($checkinUrl !== ''): ?>
                                        <a class="secondary-btn" href="<?php echo htmlspecialchars($checkinUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><i data-lucide="external-link"></i>Open check-in page</a>
                                        <?php endif; ?>
                                        <form action="partner-dashboard.php" method="POST" style="display:inline-flex;">
                                            <input type="hidden" name="action" value="refresh_location_qr">
                                            <input type="hidden" name="location_id" value="<?php echo (int) ($location['location_id'] ?? 0); ?>">
                                            <button class="secondary-btn" type="submit"><i data-lucide="refresh-cw"></i>Refresh QR</button>
                                        </form>
                                    </div>
                                </div>
                                <div class="qr-preview" data-location-qr data-qr-url="<?php echo htmlspecialchars($checkinUrl, ENT_QUOTES, 'UTF-8'); ?>" aria-label="QR code for <?php echo htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="mini-note">QR code will render here.</span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($status === 'rejected' && trim((string) ($business['review_note'] ?? '')) !== ''): ?>
                    <p class="mini-note review-note-text">Admin note: <?php echo htmlspecialchars((string) $business['review_note'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-block">
                <h3 style="margin-top:0;">No business submitted yet</h3>
                <p>Create your first listing to start the approval flow and prepare the public page customers will eventually see.</p>
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
                <div class="repeat-card">
                    <strong><?php echo htmlspecialchars((string) ($reservation['business_name'] ?? 'Business'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <p><?php echo htmlspecialchars((string) ($reservation['location_address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="detail-meta">
                        <span class="meta-pill"><i data-lucide="calendar-days" style="width:14px;height:14px;"></i><?php echo htmlspecialchars(date('M j, Y', strtotime((string) ($reservation['date'] ?? 'now'))), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="meta-pill"><i data-lucide="clock-3" style="width:14px;height:14px;"></i><?php echo htmlspecialchars(partner_dashboard_time_label((string) ($reservation['time_slot'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="meta-pill"><i data-lucide="users" style="width:14px;height:14px;"></i><?php echo (int) ($reservation['guests'] ?? 1); ?></span>
                    </div>
                    <p class="mini-note"><?php echo htmlspecialchars(trim((string) ($reservation['user_name'] ?? 'Where2Go customer')), ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($reservation['user_email']) ? ' - ' . htmlspecialchars((string) $reservation['user_email'], ENT_QUOTES, 'UTF-8') : ''; ?></p>
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

    <section class="panel-card" style="margin-top:20px;">
        <div class="section-row">
            <div>
                <h2 style="margin-bottom:8px;">Recent QR check-ins</h2>
                <p class="section-copy">This feed shows the latest in-store scans that turned into customer rewards on your partner locations.</p>
            </div>
        </div>

        <?php if ($recentCheckins): ?>
        <div class="dashboard-list">
            <?php foreach ($recentCheckins as $checkin): ?>
            <?php
            $customerLabel = trim((string) (($checkin['First_N'] ?? '') . ' ' . ($checkin['Last_N'] ?? '')));
            $locationLabel = trim((string) ($checkin['location_name'] ?? '')) !== ''
                ? (string) $checkin['location_name']
                : (string) ($checkin['location_address'] ?? 'Business location');
            ?>
            <article class="dashboard-item">
                <div class="dashboard-item-head">
                    <div>
                        <h3 style="margin:0 0 6px;"><?php echo htmlspecialchars((string) ($checkin['business_name'] ?? 'Business'), ENT_QUOTES, 'UTF-8'); ?></h3>
                        <div class="mini-note"><?php echo htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <span class="status-pill approved"><i data-lucide="badge-check"></i>Reward sent</span>
                </div>
                <div class="detail-meta">
                    <span class="meta-pill"><i data-lucide="user-round" style="width:14px;height:14px;"></i><?php echo htmlspecialchars($customerLabel !== '' ? $customerLabel : 'Where2Go customer', ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="meta-pill"><i data-lucide="coins" style="width:14px;height:14px;"></i><?php echo (int) ($checkin['points_awarded'] ?? 0); ?> points</span>
                    <span class="meta-pill"><i data-lucide="sparkles" style="width:14px;height:14px;"></i><?php echo (int) ($checkin['xp_awarded'] ?? 0); ?> XP</span>
                    <span class="meta-pill"><i data-lucide="calendar-days" style="width:14px;height:14px;"></i><?php echo htmlspecialchars(date('M j, Y', strtotime((string) ($checkin['checkin_date'] ?? 'now'))), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if (trim((string) ($checkin['promo_code_snapshot'] ?? '')) !== ''): ?>
                    <span class="meta-pill"><i data-lucide="ticket" style="width:14px;height:14px;"></i><?php echo htmlspecialchars((string) $checkin['promo_code_snapshot'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-block">
            <p>No customer has completed a QR reward scan yet. Once they do, the latest claims will appear here.</p>
        </div>
        <?php endif; ?>
    </section>
</main>

<script>
// Keep the shared account script initialized even though this page does not expose saved places.
window.where2goPageData = <?php echo json_encode(['visitedPlaceIds' => []], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/js/account.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script src="assets/js/partner-portal.js"></script>
</body>
</html>

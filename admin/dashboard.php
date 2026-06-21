<?php
// Admin dashboard for site-wide health, reservations, approvals, and place statistics.
require_once __DIR__ . '/../includes/functions.php';

start_session();
require_admin_user();
ensure_customer_place_visits_table();
ensure_where2go_rewards_schema();

$conn = db_connect();
$adminName = trim((string) ($_SESSION['customer_name'] ?? 'Admin'));

function admin_dashboard_scalar($conn, $sql) {
    $result = $conn->query($sql);
    $row = $result ? $result->fetch_assoc() : null;

    return (int) ($row['value'] ?? 0);
}

function admin_dashboard_rows($conn, $sql) {
    $result = $conn->query($sql);
    $rows = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function admin_dashboard_time_label($time) {
    $timestamp = strtotime((string) $time);

    return $timestamp ? date('g:i A', $timestamp) : trim((string) $time);
}

function admin_dashboard_place_stats($conn, $search = '') {
    $search = trim((string) $search);
    $whereSql = '';
    $params = [];

    if ($search !== '') {
        $whereSql = "WHERE b.name LIKE ?
            OR bl.location_name LIKE ?
            OR bl.address LIKE ?
            OR p.owner_name LIKE ?";
        $needle = '%' . $search . '%';
        $params = [$needle, $needle, $needle, $needle];
    }

    $sql = "SELECT bl.location_id,
            b.business_id,
            b.name AS business_name,
            b.approval_status,
            p.owner_name,
            bl.location_name,
            bl.address,
            COALESCE(view_counts.total_views, 0) AS total_views,
            COUNT(DISTINCT bk.id) AS reservation_count,
            COUNT(DISTINCT CASE WHEN bk.status IN ('pending', 'confirmed') AND bk.date >= CURDATE() THEN bk.id END) AS upcoming_reservations,
            COUNT(DISTINCT br.review_id) AS review_count,
            COALESCE(AVG(br.rating), 0) AS average_rating
        FROM business_locations bl
        INNER JOIN businesses b ON b.business_id = bl.business_id
        INNER JOIN partners p ON p.partner_id = b.partner_id
        LEFT JOIN bookings bk ON bk.location_id = bl.location_id
        LEFT JOIN business_reviews br ON br.location_id = bl.location_id
        LEFT JOIN (
            SELECT business_id, COUNT(*) AS total_views
            FROM customer_place_visits
            GROUP BY business_id
        ) view_counts ON view_counts.business_id = b.business_id
        {$whereSql}
        GROUP BY bl.location_id, b.business_id, b.name, b.approval_status, p.owner_name, bl.location_name, bl.address, view_counts.total_views
        ORDER BY reservation_count DESC, total_views DESC, b.name ASC";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    if ($params) {
        $stmt->bind_param("ssss", $params[0], $params[1], $params[2], $params[3]);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return $rows;
}

$placeSearch = trim((string) ($_GET['place_search'] ?? ''));

$stats = [
    'active_views' => admin_dashboard_scalar($conn, "SELECT COUNT(*) AS value FROM customer_place_visits WHERE viewed_at >= (NOW() - INTERVAL 15 MINUTE)"),
    'customers' => admin_dashboard_scalar($conn, "SELECT COUNT(*) AS value FROM customers"),
    'partners' => admin_dashboard_scalar($conn, "SELECT COUNT(*) AS value FROM partners"),
    'businesses' => admin_dashboard_scalar($conn, "SELECT COUNT(*) AS value FROM businesses"),
    'pending_approvals' => admin_dashboard_scalar($conn, "SELECT COUNT(*) AS value FROM businesses WHERE approval_status = 'pending'"),
    'reservations' => admin_dashboard_scalar($conn, "SELECT COUNT(*) AS value FROM bookings"),
    'today_reservations' => admin_dashboard_scalar($conn, "SELECT COUNT(*) AS value FROM bookings WHERE date = CURDATE()"),
    'reviews' => admin_dashboard_scalar($conn, "SELECT COUNT(*) AS value FROM business_reviews"),
];

$pendingBusinesses = get_pending_businesses('pending');
$recentReservations = admin_dashboard_rows($conn, "SELECT bk.id, bk.user_name, bk.user_email, bk.date, bk.time_slot, bk.guests, bk.status,
        b.business_id, b.name AS business_name, bl.location_name, bl.address AS location_address
    FROM bookings bk
    INNER JOIN business_locations bl ON bl.location_id = bk.location_id
    INNER JOIN businesses b ON b.business_id = bl.business_id
    ORDER BY bk.date DESC, bk.time_slot DESC, bk.created_at DESC
    LIMIT 8");
$placeStats = admin_dashboard_place_stats($conn, $placeSearch);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Where2Go admin dashboard for monitoring customers, partners, businesses, reservations, reviews, and approvals.">
<title>Where2Go | Admin Dashboard</title>
<link rel="icon" type="image/png" href="../assets/images/where2go_transparent_clean.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="../assets/css/account.css">
<link rel="stylesheet" href="../assets/css/partner-portal.css">
</head>
<body class="dark-mode">
<header class="topbar">
    <div class="topbar-inner">
        <div class="topbar-left">
            <a class="brand-link" href="../Home.php" aria-label="Where2Go home">
                <img src="../assets/images/where2go_transparent_clean.png" alt="Where2Go logo" class="logo">
            </a>
            <button class="theme-toggle" id="theme-toggle" type="button">
                <i data-lucide="moon-star" id="theme-icon"></i>
                <span id="theme-label">Dark mode</span>
            </button>
        </div>

        <nav class="topbar-right" aria-label="Admin navigation">
            <a class="nav-link" href="../Home.php">Home</a>
            <a class="nav-link" href="../search.php">Search</a>
            <a class="nav-link" href="business-approvals.php">Approvals</a>
            <a class="nav-link" href="../partner-dashboard.php">Partner dashboard</a>
            <a class="primary-btn" href="../logout.php"><i data-lucide="log-out"></i>Logout</a>
        </nav>
    </div>
</header>

<main class="main-inner">
    <section class="hero-panel">
        <span class="eyebrow"><i data-lucide="layout-dashboard"></i>Admin dashboard</span>
        <h1>Welcome, <?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p>Track live site activity, reservations, pending approvals, and place-level performance from one admin page.</p>
        <div class="profile-stats">
            <span class="status-badge"><i data-lucide="activity"></i><?php echo (int) $stats['active_views']; ?> active page views</span>
            <span class="status-badge"><i data-lucide="calendar-check-2"></i><?php echo (int) $stats['reservations']; ?> reservations</span>
            <span class="status-badge"><i data-lucide="clock-3"></i><?php echo (int) $stats['pending_approvals']; ?> approvals pending</span>
        </div>
    </section>

    <section class="stat-grid" style="margin-top:24px;">
        <article class="stat-card"><span class="mini-note">Customers</span><strong><?php echo (int) $stats['customers']; ?></strong><span class="mini-note">Registered customer accounts.</span></article>
        <article class="stat-card"><span class="mini-note">Partners</span><strong><?php echo (int) $stats['partners']; ?></strong><span class="mini-note">Partner accounts.</span></article>
        <article class="stat-card"><span class="mini-note">Businesses</span><strong><?php echo (int) $stats['businesses']; ?></strong><span class="mini-note">Submitted partner listings.</span></article>
        <article class="stat-card"><span class="mini-note">Today</span><strong><?php echo (int) $stats['today_reservations']; ?></strong><span class="mini-note">Reservations scheduled for today.</span></article>
        <article class="stat-card"><span class="mini-note">Reviews</span><strong><?php echo (int) $stats['reviews']; ?></strong><span class="mini-note">Customer reviews submitted.</span></article>
    </section>

    <section class="layout-grid">
        <section class="panel-card">
            <div class="section-row">
                <div>
                    <h2 style="margin-bottom:8px;">Pending approvals</h2>
                    <p class="section-copy">Newest partner listings waiting for admin review.</p>
                </div>
                <a class="secondary-btn" href="business-approvals.php"><i data-lucide="shield-check"></i>Open approvals</a>
            </div>
            <?php if ($pendingBusinesses): ?>
            <div class="dashboard-list">
                <?php foreach (array_slice($pendingBusinesses, 0, 5) as $business): ?>
                <article class="dashboard-item">
                    <div class="dashboard-item-head">
                        <div>
                            <h3 style="margin:0 0 6px;"><?php echo htmlspecialchars((string) ($business['name'] ?? 'Business'), ENT_QUOTES, 'UTF-8'); ?></h3>
                            <div class="mini-note"><?php echo htmlspecialchars((string) ($business['owner_name'] ?? 'Partner'), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string) ($business['primary_address'] ?? 'Address not added'), ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <span class="status-pill pending"><i data-lucide="clock-3"></i>Pending</span>
                    </div>
                    <div class="action-row">
                        <a class="secondary-btn" href="business-approvals.php?review_business_id=<?php echo (int) ($business['business_id'] ?? 0); ?>"><i data-lucide="arrow-up-right"></i>Review</a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-block"><p>No pending businesses right now.</p></div>
            <?php endif; ?>
        </section>

        <aside class="panel-card">
            <h2>Recent reservations</h2>
            <p class="section-copy">Latest bookings across all partner locations.</p>
            <?php if ($recentReservations): ?>
            <div class="stack-list">
                <?php foreach ($recentReservations as $reservation): ?>
                <div class="repeat-card">
                    <strong><?php echo htmlspecialchars((string) ($reservation['business_name'] ?? 'Business'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <p><?php echo htmlspecialchars((string) ($reservation['location_address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="detail-meta">
                        <span class="meta-pill"><i data-lucide="calendar-days" style="width:14px;height:14px;"></i><?php echo htmlspecialchars(date('M j, Y', strtotime((string) ($reservation['date'] ?? 'now'))), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="meta-pill"><i data-lucide="clock-3" style="width:14px;height:14px;"></i><?php echo htmlspecialchars(admin_dashboard_time_label((string) ($reservation['time_slot'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="meta-pill"><i data-lucide="users" style="width:14px;height:14px;"></i><?php echo (int) ($reservation['guests'] ?? 1); ?></span>
                    </div>
                    <p class="mini-note"><?php echo htmlspecialchars(trim((string) ($reservation['user_name'] ?? 'Where2Go customer')), ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($reservation['user_email']) ? ' - ' . htmlspecialchars((string) $reservation['user_email'], ENT_QUOTES, 'UTF-8') : ''; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-block"><p>No reservations have been created yet.</p></div>
            <?php endif; ?>
        </aside>
    </section>

    <section class="panel-card" style="margin-top:20px;">
        <div class="section-row">
            <div>
                <h2 style="margin-bottom:8px;">Place statistics</h2>
                <p class="section-copy">Every partner location with views, reservations, reviews, and approval status.</p>
            </div>
        </div>
        <form action="dashboard.php" method="GET" class="admin-place-search" style="margin-top:18px;">
            <label class="field" for="place_search">
                <span>Search by place</span>
                <input id="place_search" type="search" name="place_search" value="<?php echo htmlspecialchars($placeSearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Business, location, address, or partner">
            </label>
            <button class="primary-btn" type="submit"><i data-lucide="search"></i>Search</button>
            <?php if ($placeSearch !== ''): ?>
            <a class="secondary-btn" href="dashboard.php"><i data-lucide="x"></i>Clear</a>
            <?php endif; ?>
        </form>
        <?php if ($placeStats): ?>
        <div class="dashboard-list">
            <?php foreach ($placeStats as $place): ?>
            <?php
            $locationLabel = trim((string) ($place['location_name'] ?? '')) !== ''
                ? (string) $place['location_name']
                : (string) ($place['address'] ?? 'Business location');
            ?>
            <article class="dashboard-item">
                <div class="dashboard-item-head">
                    <div>
                        <h3 style="margin:0 0 6px;"><?php echo htmlspecialchars((string) ($place['business_name'] ?? 'Business'), ENT_QUOTES, 'UTF-8'); ?></h3>
                        <div class="mini-note"><?php echo htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string) ($place['owner_name'] ?? 'Partner'), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <span class="status-pill <?php echo htmlspecialchars((string) ($place['approval_status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars(ucfirst((string) ($place['approval_status'] ?? 'pending')), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="detail-meta">
                    <span class="meta-pill"><i data-lucide="mouse-pointer-click" style="width:14px;height:14px;"></i><?php echo (int) ($place['total_views'] ?? 0); ?> views</span>
                    <span class="meta-pill"><i data-lucide="calendar-check-2" style="width:14px;height:14px;"></i><?php echo (int) ($place['reservation_count'] ?? 0); ?> reservations</span>
                    <span class="meta-pill"><i data-lucide="clock-3" style="width:14px;height:14px;"></i><?php echo (int) ($place['upcoming_reservations'] ?? 0); ?> upcoming</span>
                    <span class="meta-pill"><i data-lucide="message-square-heart" style="width:14px;height:14px;"></i><?php echo (int) ($place['review_count'] ?? 0); ?> reviews</span>
                    <span class="meta-pill"><i data-lucide="star" style="width:14px;height:14px;"></i><?php echo number_format((float) ($place['average_rating'] ?? 0), 1); ?> avg</span>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-block"><p><?php echo $placeSearch !== '' ? 'No places matched that search.' : 'No partner locations have been submitted yet.'; ?></p></div>
        <?php endif; ?>
    </section>
</main>

<script>
window.where2goPageData = <?php echo json_encode(['visitedPlaceIds' => []], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="../assets/js/account.js"></script>
<script src="../assets/js/partner-portal.js"></script>
</body>
</html>

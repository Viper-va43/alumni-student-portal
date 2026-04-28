<?php
require_once __DIR__ . '/includes/functions.php';

start_session();

$loggedIn = is_logged_in();
$partnerLoggedIn = is_partner_logged_in();
$adminLoggedIn = is_admin_user();
$customerId = (int) ($_SESSION['customer_id'] ?? 0);
$customerName = trim((string) ($_SESSION['customer_name'] ?? 'Traveler'));
$partnerName = trim((string) ($_SESSION['partner_name'] ?? 'Partner'));
$profilePhoto = $loggedIn ? get_profile_photo_web_path($customerId) : null;
$token = trim((string) ($_POST['token'] ?? ($_GET['token'] ?? '')));
$location = get_location_by_qr_token($token);
$messages = [];
$rewardSettings = get_where2go_rewards_program_settings();
$firstScanPoints = (int) ($rewardSettings['first_scan_points'] ?? 20);
$repeatScanPoints = (int) ($rewardSettings['repeat_scan_points'] ?? 10);
$sameDayRepeatPoints = (int) ($rewardSettings['same_day_repeat_points'] ?? 3);
$reviewPoints = (int) ($rewardSettings['review_points'] ?? 5);
$dailyLimit = (int) ($rewardSettings['daily_place_limit'] ?? 5);
$cooldownHours = (int) ($rewardSettings['place_cooldown_hours'] ?? 24);
$mysteryBoxEveryLevels = (int) ($rewardSettings['mystery_box_every_levels'] ?? 5);

if (!$location || trim((string) ($location['approval_status'] ?? 'pending')) !== 'approved') {
    http_response_code(404);
    exit('This QR code is not connected to an approved Where2Go location.');
}

$redirectTarget = 'checkin.php?token=' . rawurlencode($token);
$loginUrl = 'login.php?redirect=' . rawurlencode($redirectTarget);
$registerUrl = 'register.php?redirect=' . rawurlencode($redirectTarget);
$claimResult = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'claim_checkin') {
    if (!$loggedIn) {
        header('Location: ' . $loginUrl);
        exit;
    }

    $claimResult = claim_location_checkin_reward($customerId, $token);
    $messages[] = [
        'type' => !empty($claimResult['ok']) ? 'success' : 'error',
        'text' => (string) ($claimResult['message'] ?? 'The reward could not be claimed right now.'),
    ];

    if (!empty($claimResult['location']) && is_array($claimResult['location'])) {
        $location = $claimResult['location'];
    }
}

$rewardSummary = $loggedIn ? get_customer_rewards_summary($customerId) : null;
$pendingBoxes = $loggedIn ? get_customer_pending_reward_boxes($customerId, 3) : [];
$activeRewards = $loggedIn ? get_customer_reward_vouchers($customerId, 4, false) : [];
$promoCode = trim((string) ($location['promo_code'] ?? ''));
$promoDetails = trim((string) ($location['promo_details'] ?? ''));
$locationLabel = trim((string) ($location['location_name'] ?? '')) !== ''
    ? (string) $location['location_name']
    : (string) ($location['address'] ?? 'Partner location');
$pointsToNextLevel = $rewardSummary
    ? max(0, (int) (($rewardSummary['next_threshold'] ?? 0) - ($rewardSummary['total_points'] ?? 0)))
    : 100;
$activeBox = null;

if (!empty($claimResult['next_pending_box']) && is_array($claimResult['next_pending_box'])) {
    $activeBox = $claimResult['next_pending_box'];
} elseif (!empty($pendingBoxes)) {
    $activeBox = $pendingBoxes[0];
}

$wheelSegments = [];

if ($loggedIn) {
    $wheelSegments = build_reward_wheel_segments_for_user(db_connect(), $customerId);
}

if (!$wheelSegments) {
    foreach (($rewardSettings['wheel_segments'] ?? []) as $rewardValue => $segment) {
        $wheelSegments[] = [
            'id' => 0,
            'value' => (int) $rewardValue,
            'label' => (string) ($segment['label'] ?? ($rewardValue . '% OFF')),
            'probability' => (float) ($segment['probability'] ?? 0),
        ];
    }
}

$wheelSegmentsJson = htmlspecialchars(json_encode($wheelSegments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Where2Go | QR Check-In</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="assets/css/account.css">
<link rel="stylesheet" href="assets/css/rewards.css">
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

        <nav class="topbar-right" aria-label="QR check-in navigation">
            <a class="nav-link" href="Home.php">Home</a>
            <a class="nav-link" href="search.php">Search</a>
            <?php if ($partnerLoggedIn): ?>
            <a class="nav-link" href="partner-dashboard.php"><?php echo htmlspecialchars($partnerName !== '' ? $partnerName : 'Partner dashboard', ENT_QUOTES, 'UTF-8'); ?></a>
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
                    <a class="profile-link" href="logout.php"><i data-lucide="log-out"></i><span>Logout</span></a>
                </div>
            </div>
            <?php else: ?>
            <a class="nav-link" href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">Login</a>
            <a class="primary-btn" href="<?php echo htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8'); ?>">Create account</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="main-inner">
    <section class="hero-panel">
        <span class="eyebrow"><i data-lucide="qr-code"></i>Partner QR check-in</span>
        <h1>Claim your Where2Go reward</h1>
        <p>You scanned the in-store QR code for <?php echo htmlspecialchars((string) ($location['business_name'] ?? 'this business'), ENT_QUOTES, 'UTF-8'); ?> at <?php echo htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8'); ?>. This page now handles the full loyalty flow: scan validation, points, streak bonuses, level progress, and mystery-box rewards every <?php echo $mysteryBoxEveryLevels; ?> levels.</p>
        <div class="profile-stats">
            <span class="status-badge"><i data-lucide="ticket"></i><?php echo htmlspecialchars($promoCode !== '' ? $promoCode : 'Promo not set yet', ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="status-badge"><i data-lucide="coins"></i>+<?php echo $firstScanPoints; ?> first scan</span>
            <span class="status-badge"><i data-lucide="rotate-cw"></i>+<?php echo $repeatScanPoints; ?> after <?php echo $cooldownHours; ?>h</span>
            <span class="status-badge"><i data-lucide="sparkles"></i>+<?php echo $sameDayRepeatPoints; ?> same-day repeat</span>
            <span class="status-badge"><i data-lucide="star"></i>+<?php echo $reviewPoints; ?> review</span>
        </div>
        <div class="hero-actions">
            <a class="secondary-btn" href="place.php?business_id=<?php echo (int) ($location['business_id'] ?? 0); ?>"><i data-lucide="map"></i>Open business page</a>
            <?php if (!$loggedIn): ?>
            <a class="primary-btn" href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>"><i data-lucide="log-in"></i>Login to claim</a>
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

    <section class="layout-grid">
        <section class="panel-card">
            <div class="section-row">
                <div>
                    <h2 style="margin-bottom:8px;">Promo and check-in</h2>
                    <p class="section-copy">This location reward is tied to the QR code you just opened. First-time scans at a place earn <?php echo $firstScanPoints; ?> points, return scans after <?php echo $cooldownHours; ?> hours earn <?php echo $repeatScanPoints; ?>, and same-day repeats can still earn <?php echo $sameDayRepeatPoints; ?> with anti-abuse limits.</p>
                </div>
            </div>

            <div class="detail-list" style="margin-top:0;">
                <div class="detail-row">
                    <strong>Business</strong>
                    <span><?php echo htmlspecialchars((string) ($location['business_name'] ?? 'Partner business'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Location</strong>
                    <span><?php echo htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Address</strong>
                    <span><?php echo htmlspecialchars((string) ($location['address'] ?? 'Address not available'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Promo code</strong>
                    <span><?php echo htmlspecialchars($promoCode !== '' ? $promoCode : 'No promo code has been added for this location yet.', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Promo details</strong>
                    <span><?php echo htmlspecialchars($promoDetails !== '' ? $promoDetails : 'This QR is currently set up for rewards only. The partner can add promo details later.', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Reward rule</strong>
                    <span>Daily limit: <?php echo $dailyLimit; ?> places. Reviews add <?php echo $reviewPoints; ?> points, and your first valid scan each day adds a streak bonus worth 2 x your streak day.</span>
                </div>
            </div>

            <?php if ($loggedIn): ?>
            <form action="checkin.php" method="POST" style="margin-top:18px;">
                <input type="hidden" name="action" value="claim_checkin">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="card-actions">
                    <button class="primary-btn" type="submit"><i data-lucide="gift"></i>Claim promo and reward</button>
                    <a class="secondary-btn" href="profile.php"><i data-lucide="user-round"></i>Open my profile</a>
                </div>
            </form>
            <?php else: ?>
            <div class="empty-card" style="margin-top:18px;">
                <h3 style="margin-top:0;">Login required for points</h3>
                <p>You can view the promo details here, but your points and XP can only be added after you sign in with a customer account.</p>
                <div class="card-actions">
                    <a class="primary-btn" href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>"><i data-lucide="log-in"></i>Login to claim</a>
                    <a class="secondary-btn" href="<?php echo htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8'); ?>"><i data-lucide="user-plus"></i>Create account</a>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <aside class="panel-card">
            <h2>My reward progress</h2>
            <?php if ($loggedIn && $rewardSummary): ?>
            <p class="section-copy">Your account updates here immediately after a successful QR claim, including streaks, pending mystery boxes, and active vouchers.</p>
            <div class="detail-list" style="margin-top:0;">
                <div class="detail-row">
                    <strong>Current level</strong>
                    <span>Level <?php echo (int) ($rewardSummary['current_level'] ?? 0); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Total points</strong>
                    <span><?php echo (int) ($rewardSummary['total_points'] ?? 0); ?> points across <?php echo (int) ($rewardSummary['total_scans'] ?? 0); ?> scans</span>
                </div>
                <div class="detail-row">
                    <strong>Today</strong>
                    <span><?php echo (int) ($rewardSummary['today_checkins'] ?? 0); ?> of <?php echo (int) ($rewardSummary['daily_place_limit'] ?? $dailyLimit); ?> places claimed today</span>
                </div>
                <div class="detail-row">
                    <strong>Streak</strong>
                    <span><?php echo (int) ($rewardSummary['streak'] ?? 0); ?> days current, <?php echo (int) ($rewardSummary['longest_streak'] ?? 0); ?> days best</span>
                </div>
                <div class="detail-row">
                    <strong>Next level</strong>
                    <span><?php echo $pointsToNextLevel; ?> points remaining to reach level <?php echo (int) ($rewardSummary['next_level'] ?? ((int) ($rewardSummary['current_level'] ?? 0) + 1)); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Mystery boxes</strong>
                    <span><span data-pending-box-count><?php echo (int) ($rewardSummary['pending_reward_boxes'] ?? 0); ?></span> pending, next unlock at level <?php echo (int) ($rewardSummary['next_mystery_box_level'] ?? $mysteryBoxEveryLevels); ?></span>
                </div>
            </div>
            <div class="reward-progress" style="margin-top:18px;">
                <div class="reward-progress-bar">
                    <span class="reward-progress-fill" style="--progress-width:<?php echo (int) ($rewardSummary['progress_percent'] ?? 0); ?>%;"></span>
                </div>
                <p class="reward-note" style="margin:0;">Level progress: <?php echo (int) ($rewardSummary['progress_percent'] ?? 0); ?>%</p>
            </div>
            <?php else: ?>
            <p class="section-copy">Sign in first so Where2Go can connect this scan to your customer account and save the points, XP, and level progress.</p>
            <div class="detail-list" style="margin-top:0;">
                <div class="detail-row">
                    <strong>Default reward</strong>
                    <span><?php echo $firstScanPoints; ?> points for a first scan, <?php echo $repeatScanPoints; ?> after <?php echo $cooldownHours; ?> hours, and <?php echo $sameDayRepeatPoints; ?> for allowed same-day repeats.</span>
                </div>
                <div class="detail-row">
                    <strong>Leveling</strong>
                    <span>Levels scale with the formula 100 x level^1.4, and every <?php echo $mysteryBoxEveryLevels; ?> levels unlocks a mystery reward box.</span>
                </div>
                <div class="detail-row">
                    <strong>Daily limit</strong>
                    <span>You can currently claim rewards from up to <?php echo $dailyLimit; ?> places in one day.</span>
                </div>
            </div>
            <?php endif; ?>
        </aside>
    </section>

    <?php if ($loggedIn): ?>
    <section class="reward-grid two-up" style="margin-top:24px;">
        <section class="panel-card">
            <div class="section-row">
                <div>
                    <h2 style="margin-bottom:8px;">Mystery reward boxes</h2>
                    <p class="section-copy">Every <?php echo $mysteryBoxEveryLevels; ?> levels you unlock a weighted spinning wheel. The backend decides the reward first, then the wheel animates to that exact result.</p>
                </div>
            </div>

            <?php if ($activeBox): ?>
            <div class="reward-wheel-stack">
                <article class="reward-wheel-card" data-reward-wheel-card data-box-id="<?php echo (int) ($activeBox['id'] ?? 0); ?>" data-spin-endpoint="spin-reward" data-segments="<?php echo $wheelSegmentsJson; ?>">
                    <div class="reward-wheel-head">
                        <div>
                            <h3 style="margin:0 0 8px;">Level <?php echo (int) ($activeBox['trigger_level'] ?? 0); ?> mystery box</h3>
                            <p class="reward-note"><?php echo htmlspecialchars((string) ($activeBox['business_name'] ?? 'Partner business'), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(trim((string) ($activeBox['location_name'] ?? '')) !== '' ? (string) $activeBox['location_name'] : (string) ($activeBox['location_address'] ?? 'Business location'), ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <span class="reward-pill"><i data-lucide="gift"></i><?php echo (int) ($rewardSummary['pending_reward_boxes'] ?? 0); ?> pending</span>
                    </div>
                    <div class="reward-wheel-stage">
                        <div class="reward-wheel-pointer"></div>
                        <div class="reward-wheel" data-reward-wheel></div>
                        <div class="reward-wheel-center">Mystery Box</div>
                    </div>
                    <div class="reward-wheel-legend" data-wheel-legend></div>
                    <div class="card-actions">
                        <button class="primary-btn" type="button" data-spin-reward-button><i data-lucide="sparkles"></i>Spin the wheel</button>
                    </div>
                    <div class="reward-wheel-status" data-wheel-status></div>
                </article>
            </div>
            <?php else: ?>
            <div class="empty-card">
                <h3 style="margin-top:0;">No mystery box yet</h3>
                <p>Keep scanning places and posting reviews. Your next box unlocks at level <?php echo (int) ($rewardSummary['next_mystery_box_level'] ?? $mysteryBoxEveryLevels); ?>.</p>
            </div>
            <?php endif; ?>
        </section>

        <section class="panel-card">
            <div class="section-row">
                <div>
                    <h2 style="margin-bottom:8px;">Active vouchers</h2>
                    <p class="section-copy">Every wheel reward is linked to the business and location that unlocked it, with automatic expiry for safety.</p>
                </div>
            </div>

            <?php if ($activeRewards): ?>
            <div class="voucher-list">
                <?php foreach ($activeRewards as $reward): ?>
                <?php
                $rewardLocationLabel = trim((string) ($reward['location_name'] ?? '')) !== ''
                    ? (string) $reward['location_name']
                    : (string) ($reward['location_address'] ?? 'Business location');
                ?>
                <article class="voucher-card<?php echo (int) ($reward['used'] ?? 0) === 1 ? ' is-used' : ''; ?>">
                    <div class="voucher-card-head">
                        <div>
                            <strong><?php echo htmlspecialchars((string) ($reward['reward_label'] ?? ((int) ($reward['reward_value'] ?? 0) . '% OFF')), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <p class="reward-note"><?php echo htmlspecialchars((string) ($reward['business_name'] ?? 'Business'), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars($rewardLocationLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <span class="voucher-code"><?php echo htmlspecialchars((string) ($reward['voucher_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <p class="reward-note" style="margin:0;">Expires <?php echo htmlspecialchars(date('M j, Y', strtotime((string) ($reward['expires_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8'); ?><?php echo (int) ($reward['used'] ?? 0) === 1 ? ' | Used' : ''; ?></p>
                </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-card">
                <h3 style="margin-top:0;">No active vouchers yet</h3>
                <p>Your next wheel win will appear here with its reward code and expiry date.</p>
            </div>
            <?php endif; ?>
        </section>
    </section>
    <?php endif; ?>
</main>

<script>
window.where2goPageData = <?php echo json_encode([
    'visitedPlaceIds' => [],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/js/account.js"></script>
<script src="assets/js/rewards.js"></script>
</body>
</html>

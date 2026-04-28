<?php
// Load admin-only business review helpers and block non-admin sessions.
require_once __DIR__ . '/../includes/functions.php';

start_session();
require_admin_user();

$messages = [];

// Choose the best visible label for a reviewed business location.
function admin_location_label($location) {
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

// Convert weekly business hours rows into readable admin review text.
function admin_build_location_hours_summary($hoursRows) {
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
        $openLabel = $openTime !== '' ? date('g:i A', strtotime($openTime)) : 'Not set';
        $closeLabel = $closeTime !== '' ? date('g:i A', strtotime($closeTime)) : 'Not set';
        $lines[] = $dayName . ': ' . $openLabel . ' - ' . $closeLabel;
    }

    return $lines;
}

// Look up the compact review summary that matches the selected business id.
function admin_find_review_summary($businesses, $businessId) {
    foreach ($businesses as $business) {
        if ((int) ($business['business_id'] ?? 0) === $businessId) {
            return $business;
        }
    }

    return null;
}

// Process approve, reject, or reset actions posted from the pending review cards.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $businessId = (int) ($_POST['business_id'] ?? 0);
    $status = trim((string) ($_POST['status'] ?? ''));
    $reviewNote = trim((string) ($_POST['review_note'] ?? ''));

    if ($status === 'rejected' && $reviewNote === '') {
        $messages[] = ['type' => 'error', 'text' => 'Add a rejection note so you can remember why this business was rejected.'];
    } elseif ($businessId > 0 && in_array($status, ['approved', 'rejected', 'pending'], true) && set_business_approval_status($businessId, $status, $reviewNote)) {
        header('Location: business-approvals.php?updated=' . rawurlencode($status) . '&review_business_id=' . $businessId . '&review_status=' . rawurlencode($status));
        exit;
    } else {
        $messages[] = ['type' => 'error', 'text' => 'The approval action could not be completed right now.'];
    }
}

$updatedStatus = trim((string) ($_GET['updated'] ?? ''));

if ($updatedStatus !== '') {
    $messages[] = ['type' => 'success', 'text' => 'Business status updated to ' . $updatedStatus . '.'];
}

$pendingBusinesses = get_pending_businesses('pending');
$approvedBusinesses = get_pending_businesses('approved');
$rejectedBusinesses = get_pending_businesses('rejected');
$selectedReviewId = (int) ($_GET['review_business_id'] ?? 0);
$selectedReviewStatus = trim((string) ($_GET['review_status'] ?? ''));
$selectedReviewSummary = null;

if ($selectedReviewStatus === 'approved') {
    $selectedReviewSummary = admin_find_review_summary($approvedBusinesses, $selectedReviewId);
} elseif ($selectedReviewStatus === 'rejected') {
    $selectedReviewSummary = admin_find_review_summary($rejectedBusinesses, $selectedReviewId);
}

if (!$selectedReviewSummary && $selectedReviewId > 0) {
    $selectedReviewSummary = admin_find_review_summary($approvedBusinesses, $selectedReviewId);

    if (!$selectedReviewSummary) {
        $selectedReviewSummary = admin_find_review_summary($rejectedBusinesses, $selectedReviewId);
    }

    if ($selectedReviewSummary) {
        $selectedReviewStatus = (string) ($selectedReviewSummary['approval_status'] ?? $selectedReviewStatus);
    }
}

$selectedReviewedBusiness = $selectedReviewSummary ? get_business_by_id((int) ($selectedReviewSummary['business_id'] ?? 0)) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Where2Go | Business Approvals</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="../assets/css/account.css">
<link rel="stylesheet" href="../assets/css/partner-portal.css">
</head>
<body class="light-mode">
<!-- Admin review header with shortcuts back to discovery and the partner workspace. -->
<header class="topbar">
    <div class="topbar-inner">
        <div class="topbar-left">
            <a class="brand-link" href="../Home.php" aria-label="Where2Go home">
                <img src="../assets/images/where2go_transparent.png" alt="Where2Go logo" class="logo">
            </a>
            <button class="theme-toggle" id="theme-toggle" type="button">
                <i data-lucide="sun-medium" id="theme-icon"></i>
                <span id="theme-label">Light mode</span>
            </button>
        </div>

        <nav class="topbar-right" aria-label="Admin approvals navigation">
            <a class="nav-link" href="../Home.php">Home</a>
            <a class="nav-link" href="../search.php">Search</a>
            <a class="nav-link" href="../partner-dashboard.php">Partner dashboard</a>
            <a class="nav-link" href="reward-config.php">Reward config</a>
            <a class="primary-btn" href="../logout.php"><i data-lucide="log-out"></i>Logout</a>
        </nav>
    </div>
</header>

<main class="main-inner">
    <!-- Hero panel summarizing the current approval queues. -->
    <section class="hero-panel">
        <span class="eyebrow"><i data-lucide="shield-check"></i>Admin approvals</span>
        <h1>Review partner businesses before they go public</h1>
        <p>Pending businesses stay private until you approve them. Approved and rejected history now stays visible here so you can reopen any reviewed listing and inspect the full details again.</p>
        <div class="profile-stats">
            <span class="status-badge"><i data-lucide="clock-3"></i><?php echo count($pendingBusinesses); ?> pending</span>
            <span class="status-badge is-success"><i data-lucide="badge-check"></i><?php echo count($approvedBusinesses); ?> approved</span>
            <span class="status-badge"><i data-lucide="x-circle"></i><?php echo count($rejectedBusinesses); ?> rejected</span>
        </div>
    </section>

    <?php if ($messages): ?>
    <div class="messages" style="margin-top:24px;">
        <?php foreach ($messages as $message): ?>
        <div class="message <?php echo htmlspecialchars((string) $message['type'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($message['text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Main review workspace with pending approvals on the left and review history on the right. -->
    <section class="layout-grid" style="margin-top:24px;">
        <section class="panel-card">
            <div class="section-row">
                <div>
                    <h2 style="margin-bottom:8px;">Pending approval</h2>
                    <p class="section-copy">Approve valid businesses to publish them to customers, or reject them with a note if something needs to be corrected.</p>
                </div>
            </div>

            <?php if ($pendingBusinesses): ?>
            <div class="approval-list">
                <?php foreach ($pendingBusinesses as $business): ?>
                <article class="approval-item">
                    <div class="approval-item-head">
                        <div>
                            <h3 style="margin:0 0 6px;"><?php echo htmlspecialchars((string) ($business['name'] ?? 'Business'), ENT_QUOTES, 'UTF-8'); ?></h3>
                            <div class="mini-note"><?php echo htmlspecialchars((string) ($business['primary_address'] ?? 'Address not added yet'), ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <span class="status-pill pending"><i data-lucide="clock-3"></i>Pending</span>
                    </div>

                    <div class="detail-meta">
                        <span class="meta-pill"><i data-lucide="<?php echo htmlspecialchars((string) ($business['icon'] ?? 'building-2'), ENT_QUOTES, 'UTF-8'); ?>" style="width:14px;height:14px;"></i><?php echo htmlspecialchars((string) ($business['type_label'] ?? 'Business'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="meta-pill"><i data-lucide="user-round" style="width:14px;height:14px;"></i><?php echo htmlspecialchars((string) ($business['owner_name'] ?? 'Partner'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="meta-pill"><i data-lucide="mail" style="width:14px;height:14px;"></i><?php echo htmlspecialchars((string) ($business['partner_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>

                    <?php if (!empty($business['description'])): ?>
                    <p class="mini-note"><?php echo htmlspecialchars((string) $business['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>

                    <form action="business-approvals.php" method="POST" class="approval-action-form">
                        <input type="hidden" name="business_id" value="<?php echo (int) ($business['business_id'] ?? 0); ?>">
                        <div class="field">
                            <label for="review_note_<?php echo (int) ($business['business_id'] ?? 0); ?>">Rejection note</label>
                            <textarea id="review_note_<?php echo (int) ($business['business_id'] ?? 0); ?>" name="review_note" placeholder="Tell the partner what needs to be fixed if you reject this submission."></textarea>
                        </div>
                        <div class="action-row">
                            <a class="secondary-btn" href="../place.php?business_id=<?php echo (int) ($business['business_id'] ?? 0); ?>"><i data-lucide="eye"></i>Preview</a>
                            <div class="action-row">
                                <button class="secondary-btn" type="submit" name="status" value="rejected"><i data-lucide="x-circle"></i>Reject</button>
                                <button class="primary-btn" type="submit" name="status" value="approved"><i data-lucide="badge-check"></i>Approve</button>
                            </div>
                        </div>
                    </form>
                </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-block">
                <p>No pending businesses right now. New partner submissions will appear here automatically.</p>
            </div>
            <?php endif; ?>
        </section>

        <section class="stack-list">
            <section class="panel-card">
                <div class="section-row">
                    <div>
                        <h2 style="margin-bottom:8px;">Recently reviewed</h2>
                        <p class="section-copy">Approved and rejected businesses stay in compact scrollable lists. Click any item to reopen its full details.</p>
                    </div>
                </div>

                <div class="review-history-grid">
                    <div class="review-scroll-shell">
                        <strong>Approved</strong>
                        <?php if ($approvedBusinesses): ?>
                        <div class="review-scroll-list">
                            <?php foreach ($approvedBusinesses as $business): ?>
                            <?php $isSelected = (int) ($business['business_id'] ?? 0) === $selectedReviewId && $selectedReviewStatus === 'approved'; ?>
                            <a class="review-history-item<?php echo $isSelected ? ' is-selected' : ''; ?>" href="business-approvals.php?review_business_id=<?php echo (int) ($business['business_id'] ?? 0); ?>&review_status=approved">
                                <strong><?php echo htmlspecialchars((string) ($business['name'] ?? 'Business'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span><?php echo htmlspecialchars((string) ($business['owner_name'] ?? 'Partner'), ENT_QUOTES, 'UTF-8'); ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="mini-note">No approved businesses yet.</p>
                        <?php endif; ?>
                    </div>

                    <div class="review-scroll-shell">
                        <strong>Rejected</strong>
                        <?php if ($rejectedBusinesses): ?>
                        <div class="review-scroll-list">
                            <?php foreach ($rejectedBusinesses as $business): ?>
                            <?php $isSelected = (int) ($business['business_id'] ?? 0) === $selectedReviewId && $selectedReviewStatus === 'rejected'; ?>
                            <a class="review-history-item<?php echo $isSelected ? ' is-selected' : ''; ?>" href="business-approvals.php?review_business_id=<?php echo (int) ($business['business_id'] ?? 0); ?>&review_status=rejected">
                                <strong><?php echo htmlspecialchars((string) ($business['name'] ?? 'Business'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span><?php echo htmlspecialchars((string) ($business['owner_name'] ?? 'Partner'), ENT_QUOTES, 'UTF-8'); ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="mini-note">No rejected businesses right now.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="panel-card">
                <div class="section-row">
                    <div>
                        <h2 style="margin-bottom:8px;">Review details</h2>
                        <p class="section-copy"><?php echo $selectedReviewedBusiness ? 'Full information for the selected reviewed business.' : 'Select an approved or rejected business from the history lists to inspect everything again.'; ?></p>
                    </div>
                    <?php if ($selectedReviewedBusiness): ?>
                    <a class="secondary-btn" href="../place.php?business_id=<?php echo (int) ($selectedReviewedBusiness['business_id'] ?? 0); ?>"><i data-lucide="eye"></i>Open preview</a>
                    <?php endif; ?>
                </div>

                <?php if ($selectedReviewedBusiness && $selectedReviewSummary): ?>
                <?php $selectedStatus = trim((string) ($selectedReviewedBusiness['approval_status'] ?? 'pending')); ?>
                <div class="stack-list">
                    <article class="dashboard-item">
                        <div class="dashboard-item-head">
                            <div>
                                <h3 style="margin:0 0 6px;"><?php echo htmlspecialchars((string) ($selectedReviewedBusiness['name'] ?? 'Business'), ENT_QUOTES, 'UTF-8'); ?></h3>
                                <div class="mini-note"><?php echo htmlspecialchars((string) ($selectedReviewSummary['owner_name'] ?? 'Partner'), ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($selectedReviewSummary['partner_email']) ? ' - ' . htmlspecialchars((string) $selectedReviewSummary['partner_email'], ENT_QUOTES, 'UTF-8') : ''; ?></div>
                            </div>
                            <span class="status-pill <?php echo htmlspecialchars($selectedStatus, ENT_QUOTES, 'UTF-8'); ?>">
                                <i data-lucide="<?php echo $selectedStatus === 'approved' ? 'badge-check' : ($selectedStatus === 'rejected' ? 'x-circle' : 'clock-3'); ?>"></i>
                                <?php echo htmlspecialchars(ucfirst($selectedStatus), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>

                        <div class="detail-meta">
                            <span class="meta-pill"><i data-lucide="<?php echo htmlspecialchars((string) ($selectedReviewedBusiness['icon'] ?? 'building-2'), ENT_QUOTES, 'UTF-8'); ?>" style="width:14px;height:14px;"></i><?php echo htmlspecialchars((string) ($selectedReviewedBusiness['type_label'] ?? 'Business'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if (!empty($selectedReviewedBusiness['website'])): ?>
                            <a class="meta-pill" href="<?php echo htmlspecialchars((string) $selectedReviewedBusiness['website'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><i data-lucide="globe" style="width:14px;height:14px;"></i>Website</a>
                            <?php endif; ?>
                            <?php if (!empty($selectedReviewedBusiness['reviewed_at'])): ?>
                            <span class="meta-pill"><i data-lucide="clock-3" style="width:14px;height:14px;"></i><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime((string) $selectedReviewedBusiness['reviewed_at'])), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ($selectedStatus === 'rejected' && trim((string) ($selectedReviewedBusiness['review_note'] ?? '')) !== ''): ?>
                        <div class="message error">Rejection note: <?php echo htmlspecialchars((string) $selectedReviewedBusiness['review_note'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </article>

                    <div class="repeat-card">
                        <strong>Description</strong>
                        <p><?php echo htmlspecialchars(trim((string) ($selectedReviewedBusiness['description'] ?? '')) !== '' ? (string) $selectedReviewedBusiness['description'] : 'No description added.', ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>

                    <div class="repeat-card">
                        <strong>Rules / policies</strong>
                        <p><?php echo nl2br(htmlspecialchars(trim((string) ($selectedReviewedBusiness['rules'] ?? '')) !== '' ? (string) $selectedReviewedBusiness['rules'] : 'No rules were added.', ENT_QUOTES, 'UTF-8')); ?></p>
                    </div>

                    <div class="repeat-card">
                        <strong>Locations</strong>
                        <div class="stack-list" style="margin-top:14px;">
                            <?php foreach (($selectedReviewedBusiness['locations'] ?? []) as $location): ?>
                            <div class="review-detail-block">
                                <strong><?php echo htmlspecialchars(admin_location_label($location), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if (!empty($location['location_name']) && !empty($location['address'])): ?>
                                <p><?php echo htmlspecialchars((string) $location['address'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($location['phone'])): ?>
                                <p><?php echo htmlspecialchars((string) $location['phone'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                                <div class="detail-meta">
                                    <span class="meta-pill"><i data-lucide="<?php echo (int) ($location['has_reservations'] ?? 0) === 1 ? 'calendar-check-2' : 'ban'; ?>" style="width:14px;height:14px;"></i><?php echo (int) ($location['has_reservations'] ?? 0) === 1 ? 'Reservations enabled' : 'Walk-in only'; ?></span>
                                    <span class="meta-pill"><i data-lucide="layout-grid" style="width:14px;height:14px;"></i><?php echo (int) ($location['capacity_per_hour'] ?? 0); ?> tables per hour</span>
                                </div>
                                <?php foreach (admin_build_location_hours_summary($location['hours'] ?? []) as $line): ?>
                                <p><?php echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="repeat-card">
                        <strong>Offers</strong>
                        <?php if (!empty($selectedReviewedBusiness['offers'])): ?>
                        <div class="stack-list" style="margin-top:14px;">
                            <?php foreach ($selectedReviewedBusiness['offers'] as $offer): ?>
                            <div class="review-detail-block">
                                <strong><?php echo htmlspecialchars((string) ($offer['title'] ?? 'Offer'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <p><?php echo htmlspecialchars(trim((string) ($offer['description'] ?? '')) !== '' ? (string) $offer['description'] : 'No description added.', ENT_QUOTES, 'UTF-8'); ?></p>
                                <div class="detail-meta">
                                    <?php if ($offer['discount'] !== null && $offer['discount'] !== ''): ?>
                                    <span class="meta-pill"><i data-lucide="ticket-percent" style="width:14px;height:14px;"></i><?php echo htmlspecialchars(rtrim(rtrim((string) $offer['discount'], '0'), '.') . '%', ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($offer['start_date'])): ?>
                                    <span class="meta-pill"><i data-lucide="calendar-range" style="width:14px;height:14px;"></i><?php echo htmlspecialchars(date('M j, Y', strtotime((string) $offer['start_date'])), ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($offer['end_date']) ? ' - ' . htmlspecialchars(date('M j, Y', strtotime((string) $offer['end_date'])), ENT_QUOTES, 'UTF-8') : ''; ?></span>
                                    <?php endif; ?>
                                    <span class="meta-pill"><i data-lucide="<?php echo !empty($offer['is_active']) ? 'badge-check' : 'pause-circle'; ?>" style="width:14px;height:14px;"></i><?php echo !empty($offer['is_active']) ? 'Active' : 'Inactive'; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p>No offers added for this business.</p>
                        <?php endif; ?>
                    </div>

                    <div class="repeat-card">
                        <strong>Menus</strong>
                        <?php if (!empty($selectedReviewedBusiness['menus'])): ?>
                        <div class="stack-list" style="margin-top:14px;">
                            <?php foreach ($selectedReviewedBusiness['menus'] as $menu): ?>
                            <a class="review-history-item" href="<?php echo htmlspecialchars((string) ($menu['file_url'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                <strong><?php echo htmlspecialchars(trim((string) ($menu['title'] ?? '')) !== '' ? (string) $menu['title'] : 'Open menu', ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span><?php echo htmlspecialchars((string) ($menu['file_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p>No menus added for this business.</p>
                        <?php endif; ?>
                    </div>

                    <div class="repeat-card">
                        <strong>Photos</strong>
                        <?php if (!empty($selectedReviewedBusiness['photos'])): ?>
                        <div class="stack-list" style="margin-top:14px;">
                            <?php foreach ($selectedReviewedBusiness['photos'] as $index => $photo): ?>
                            <a class="review-history-item" href="<?php echo htmlspecialchars((string) ($photo['image_url'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                <strong>Photo <?php echo (int) $index + 1; ?></strong>
                                <span><?php echo htmlspecialchars((string) ($photo['image_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p>No photos added for this business.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="empty-block">
                    <p>Pick an approved or rejected business from the lists above to open its full details here.</p>
                </div>
                <?php endif; ?>
            </section>
        </section>
    </section>
</main>

<script>
// Keep the shared account script initialized even though this page does not expose saved places.
window.where2goPageData = <?php echo json_encode(['visitedPlaceIds' => []], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="../assets/js/account.js"></script>
<script src="../assets/js/partner-portal.js"></script>
</body>
</html>

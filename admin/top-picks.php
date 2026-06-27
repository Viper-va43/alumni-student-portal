<?php
require_once __DIR__ . '/../includes/functions.php';

start_session();
require_admin_user();

$messages = [];
$selectedDate = normalize_top_pick_date($_POST['pick_date'] ?? ($_GET['date'] ?? ''));
$searchQuery = trim((string) ($_GET['q'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $messages[] = ['ok' => false, 'message' => 'Your session expired. Refresh the page and try again.'];
    } elseif ($action === 'add') {
        $messages[] = add_daily_top_pick((int) ($_POST['business_id'] ?? 0), $selectedDate);
    } elseif ($action === 'remove') {
        $messages[] = remove_daily_top_pick((int) ($_POST['pick_id'] ?? 0), $selectedDate);
    } elseif ($action === 'clear') {
        $messages[] = clear_daily_top_picks($selectedDate);
    } else {
        $messages[] = ['ok' => false, 'message' => 'Choose a top-pick action first.'];
    }
}

$manualTopPicks = get_daily_top_pick_rows($selectedDate, 6);
$appTopPicks = get_top_pick_business_rows_for_app($selectedDate, 6);
$candidatePlaces = search_daily_top_pick_candidates($searchQuery, $selectedDate, 24);
$manualPickCount = count($manualTopPicks);

function admin_top_pick_place_label($business) {
    $name = trim((string) ($business['name'] ?? 'Place'));
    $area = trim((string) (($business['location_name'] ?? '') ?: ($business['address'] ?? '')));

    return $area !== '' ? $name . ' - ' . $area : $name;
}

function admin_top_pick_tag_list($business) {
    return array_slice(get_business_search_tag_list($business['search_tags'] ?? ''), 0, 5);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Manage Where2Go mobile app top picks for each day.">
<title>Where2Go | Top Picks</title>
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

        <nav class="topbar-right" aria-label="Admin top picks navigation">
            <a class="nav-link" href="../Home.php">Home</a>
            <a class="nav-link" href="dashboard.php">Admin dashboard</a>
            <a class="nav-link" href="business-approvals.php">Approvals</a>
            <a class="nav-link" href="reward-config.php">Rewards</a>
            <a class="primary-btn" href="../logout.php"><i data-lucide="log-out"></i>Logout</a>
        </nav>
    </div>
</header>

<main class="main-inner">
    <section class="hero-panel">
        <span class="eyebrow"><i data-lucide="sparkles"></i>Daily top picks</span>
        <h1>Promote up to 6 places in the mobile app</h1>
        <p>Top picks are an advertising lane only. Selected places still remain in the normal Discover list, Saved list, search results, and category filters.</p>
        <div class="profile-stats">
            <span class="status-badge"><i data-lucide="calendar-days"></i><?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="status-badge"><i data-lucide="badge-check"></i><?php echo $manualPickCount; ?> of 6 manual picks</span>
            <span class="status-badge"><i data-lucide="moon-star"></i>Nightlife fallback</span>
        </div>
    </section>

    <?php if ($messages): ?>
    <div class="messages" style="margin-top:24px;">
        <?php foreach ($messages as $message): ?>
        <?php $type = !empty($message['ok']) ? 'success' : 'error'; ?>
        <div class="message <?php echo $type; ?>"><?php echo htmlspecialchars((string) ($message['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <section class="layout-grid" style="margin-top:24px;">
        <section class="stack-list">
            <section class="panel-card">
                <div class="section-row">
                    <div>
                        <h2 style="margin-bottom:8px;">Manual picks</h2>
                        <p class="section-copy">These places appear first for the selected day. If you choose fewer than 6, the app fills the remaining slots automatically from nightlife and approved places.</p>
                    </div>
                    <?php if ($manualTopPicks): ?>
                    <form action="top-picks.php" method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="clear">
                        <input type="hidden" name="pick_date" value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>">
                        <button class="secondary-btn" type="submit"><i data-lucide="rotate-ccw"></i>Clear day</button>
                    </form>
                    <?php endif; ?>
                </div>

                <?php if ($manualTopPicks): ?>
                <div class="dashboard-list">
                    <?php foreach ($manualTopPicks as $pick): ?>
                    <article class="dashboard-item">
                        <div class="dashboard-item-head">
                            <div>
                                <h3 style="margin:0 0 6px;"><?php echo htmlspecialchars(admin_top_pick_place_label($pick), ENT_QUOTES, 'UTF-8'); ?></h3>
                                <div class="mini-note"><?php echo htmlspecialchars((string) ($pick['type_label'] ?? 'Place'), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                            <span class="status-pill approved"><i data-lucide="star"></i>#<?php echo (int) ($pick['top_pick_position'] ?? 0); ?></span>
                        </div>
                        <?php $tags = admin_top_pick_tag_list($pick); ?>
                        <?php if ($tags): ?>
                        <div class="detail-meta">
                            <?php foreach ($tags as $tag): ?>
                            <span class="meta-pill"><?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="action-row">
                            <span class="mini-note">Manual promotion for <?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?></span>
                            <form action="top-picks.php" method="POST">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="pick_date" value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="pick_id" value="<?php echo (int) ($pick['top_pick_id'] ?? 0); ?>">
                                <button class="secondary-btn" type="submit"><i data-lucide="trash-2"></i>Remove</button>
                            </form>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-block">
                    <p>No manual picks set for this date. The mobile app will automatically use approved nightlife places first, then other approved places until it reaches 6.</p>
                </div>
                <?php endif; ?>
            </section>

            <section class="panel-card">
                <div class="section-row">
                    <div>
                        <h2 style="margin-bottom:8px;">Mobile preview</h2>
                        <p class="section-copy">This is the exact top-pick lane the app API returns for the selected day.</p>
                    </div>
                </div>
                <?php if ($appTopPicks): ?>
                <div class="dashboard-list">
                    <?php foreach ($appTopPicks as $pick): ?>
                    <?php $source = trim((string) ($pick['top_pick_source'] ?? 'automatic')); ?>
                    <article class="dashboard-item">
                        <div class="dashboard-item-head">
                            <div>
                                <h3 style="margin:0 0 6px;"><?php echo htmlspecialchars(admin_top_pick_place_label($pick), ENT_QUOTES, 'UTF-8'); ?></h3>
                                <div class="mini-note"><?php echo htmlspecialchars((string) ($pick['type_label'] ?? 'Place'), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                            <span class="status-pill <?php echo $source === 'manual' ? 'approved' : 'pending'; ?>">
                                <i data-lucide="<?php echo $source === 'manual' ? 'star' : 'wand-sparkles'; ?>"></i><?php echo htmlspecialchars(ucfirst($source), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-block"><p>No approved places are available for the app preview yet.</p></div>
                <?php endif; ?>
            </section>
        </section>

        <aside class="panel-card">
            <h2>Find places to promote</h2>
            <p class="section-copy">Search by place name, area, category, or partner tags. Only approved places can be added.</p>

            <form action="top-picks.php" method="GET" class="admin-place-search" style="margin-top:18px;">
                <label class="field">
                    <span>Date</span>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>">
                </label>
                <label class="field">
                    <span>Search</span>
                    <input type="search" name="q" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nightlife, Zamalek, rooftop...">
                </label>
                <button class="primary-btn" type="submit"><i data-lucide="search"></i>Search</button>
            </form>

            <div class="stack-list" style="margin-top:18px;">
                <?php foreach ($candidatePlaces as $business): ?>
                <?php
                    $isSelected = !empty($business['top_pick_id']);
                    $isLimitReached = $manualPickCount >= 6 && !$isSelected;
                    $tags = admin_top_pick_tag_list($business);
                ?>
                <article class="repeat-card">
                    <div class="action-row">
                        <div>
                            <h3 style="margin:0 0 6px;"><?php echo htmlspecialchars(admin_top_pick_place_label($business), ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p><?php echo htmlspecialchars((string) ($business['type_label'] ?? 'Place'), ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <?php if ($isSelected): ?>
                        <span class="status-pill approved"><i data-lucide="badge-check"></i>Selected</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($tags): ?>
                    <div class="detail-meta" style="margin-top:12px;">
                        <?php foreach ($tags as $tag): ?>
                        <span class="meta-pill"><?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <form action="top-picks.php" method="POST" class="action-row" style="margin-top:14px;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="pick_date" value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="business_id" value="<?php echo (int) ($business['business_id'] ?? 0); ?>">
                        <span class="mini-note"><?php echo $isLimitReached ? 'Remove one pick before adding more.' : 'Promote without removing it from Discover.'; ?></span>
                        <button class="primary-btn" type="submit"<?php echo ($isSelected || $isLimitReached) ? ' disabled style="opacity:0.55;cursor:not-allowed;"' : ''; ?>>
                            <i data-lucide="plus"></i>Add
                        </button>
                    </form>
                </article>
                <?php endforeach; ?>
            </div>

            <?php if (!$candidatePlaces): ?>
            <div class="empty-block" style="margin-top:18px;">
                <p>No approved places matched that search yet.</p>
            </div>
            <?php endif; ?>
        </aside>
    </section>
</main>

<script>
window.where2goPageData = <?php echo json_encode([
    'visitedPlaceIds' => [],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="../assets/js/account.js"></script>
</body>
</html>

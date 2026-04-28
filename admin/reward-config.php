<?php
require_once __DIR__ . '/../includes/functions.php';

start_session();
require_admin_user();

$messages = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $saveResult = save_where2go_reward_program_settings($_POST);
    $messages[] = [
        'type' => !empty($saveResult['ok']) ? 'success' : 'error',
        'text' => (string) ($saveResult['message'] ?? 'The reward settings could not be saved right now.'),
    ];
}

$settings = get_where2go_rewards_program_settings();
$rewardDefinitions = get_active_reward_definitions(db_connect(), true);
$probabilityMap = [];

foreach ($rewardDefinitions as $definition) {
    $probabilityMap[(int) ($definition['value'] ?? 0)] = (float) ($definition['probability'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Where2Go | Reward Config</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="../assets/css/account.css">
<link rel="stylesheet" href="../assets/css/partner-portal.css">
<link rel="stylesheet" href="../assets/css/rewards.css">
</head>
<body class="light-mode">
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

        <nav class="topbar-right" aria-label="Admin reward configuration navigation">
            <a class="nav-link" href="../Home.php">Home</a>
            <a class="nav-link" href="business-approvals.php">Approvals</a>
            <a class="nav-link" href="../partner-dashboard.php">Partner dashboard</a>
            <a class="primary-btn" href="../logout.php"><i data-lucide="log-out"></i>Logout</a>
        </nav>
    </div>
</header>

<main class="main-inner">
    <section class="hero-panel">
        <span class="eyebrow"><i data-lucide="sliders-horizontal"></i>Reward config</span>
        <h1>Control the loyalty engine</h1>
        <p>These settings drive QR scan rewards, streak bonuses, mystery-box timing, voucher safety rules, and the weighted wheel outcome probabilities. The backend uses these values directly, so changing them here updates the live system.</p>
        <div class="profile-stats">
            <span class="status-badge"><i data-lucide="coins"></i><?php echo (int) ($settings['first_scan_points'] ?? 20); ?> first scan points</span>
            <span class="status-badge"><i data-lucide="refresh-cw"></i><?php echo (int) ($settings['place_cooldown_hours'] ?? 24); ?>h place cooldown</span>
            <span class="status-badge"><i data-lucide="gift"></i>Box every <?php echo (int) ($settings['mystery_box_every_levels'] ?? 5); ?> levels</span>
            <span class="status-badge"><i data-lucide="shield"></i><?php echo (int) ($settings['max_lifetime_free_vouchers'] ?? 2); ?> max 100% vouchers</span>
        </div>
    </section>

    <?php if ($messages): ?>
    <div class="messages" style="margin-top:24px;">
        <?php foreach ($messages as $message): ?>
        <div class="message <?php echo htmlspecialchars((string) ($message['type'] ?? 'info'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($message['text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form action="reward-config.php" method="POST" class="reward-grid" style="margin-top:24px;">
        <section class="reward-config-grid">
            <section class="panel-card">
                <h2>Scan rewards</h2>
                <p class="reward-note">Tune the QR reward values and the anti-abuse thresholds that control rapid repeat scans.</p>
                <div class="reward-form-grid two-up">
                    <label class="field">
                        <span>First scan points</span>
                        <input type="number" name="first_scan_points" min="0" value="<?php echo (int) ($settings['first_scan_points'] ?? 20); ?>">
                    </label>
                    <label class="field">
                        <span>Repeat scan points</span>
                        <input type="number" name="repeat_scan_points" min="0" value="<?php echo (int) ($settings['repeat_scan_points'] ?? 10); ?>">
                    </label>
                    <label class="field">
                        <span>Same-day repeat points</span>
                        <input type="number" name="same_day_repeat_points" min="0" value="<?php echo (int) ($settings['same_day_repeat_points'] ?? 3); ?>">
                    </label>
                    <label class="field">
                        <span>Review points</span>
                        <input type="number" name="review_points" min="0" value="<?php echo (int) ($settings['review_points'] ?? 5); ?>">
                    </label>
                    <label class="field">
                        <span>Daily streak multiplier</span>
                        <input type="number" name="daily_streak_multiplier" min="0" value="<?php echo (int) ($settings['daily_streak_multiplier'] ?? 2); ?>">
                    </label>
                    <label class="field">
                        <span>Daily place limit</span>
                        <input type="number" name="daily_place_limit" min="1" value="<?php echo (int) ($settings['daily_place_limit'] ?? 5); ?>">
                    </label>
                    <label class="field">
                        <span>Rapid repeat cooldown (minutes)</span>
                        <input type="number" name="rapid_repeat_cooldown_minutes" min="1" value="<?php echo (int) ($settings['rapid_repeat_cooldown_minutes'] ?? 15); ?>">
                    </label>
                    <label class="field">
                        <span>Place cooldown (hours)</span>
                        <input type="number" name="place_cooldown_hours" min="1" value="<?php echo (int) ($settings['place_cooldown_hours'] ?? 24); ?>">
                    </label>
                    <label class="field">
                        <span>Same-day repeat cap per place</span>
                        <input type="number" name="max_same_day_repeat_scans_per_place" min="0" value="<?php echo (int) ($settings['max_same_day_repeat_scans_per_place'] ?? 3); ?>">
                    </label>
                    <label class="field">
                        <span>Review requires prior check-in</span>
                        <select name="review_requires_checkin">
                            <option value="1"<?php echo (int) ($settings['review_requires_checkin'] ?? 1) === 1 ? ' selected' : ''; ?>>Yes</option>
                            <option value="0"<?php echo (int) ($settings['review_requires_checkin'] ?? 1) === 0 ? ' selected' : ''; ?>>No</option>
                        </select>
                    </label>
                </div>
            </section>

            <section class="panel-card">
                <h2>Boxes and vouchers</h2>
                <p class="reward-note">Safety controls for mystery-box rewards and voucher expiry windows.</p>
                <div class="reward-form-grid two-up">
                    <label class="field">
                        <span>Mystery box interval</span>
                        <input type="number" value="<?php echo (int) ($settings['mystery_box_every_levels'] ?? 5); ?>" disabled>
                    </label>
                    <label class="field">
                        <span>Level formula</span>
                        <input type="text" value="100 x level^1.4" disabled>
                    </label>
                    <label class="field">
                        <span>Voucher expiry min days</span>
                        <input type="number" name="voucher_expiry_min_days" min="1" value="<?php echo (int) ($settings['voucher_expiry_min_days'] ?? 7); ?>">
                    </label>
                    <label class="field">
                        <span>Voucher expiry max days</span>
                        <input type="number" name="voucher_expiry_max_days" min="1" value="<?php echo (int) ($settings['voucher_expiry_max_days'] ?? 14); ?>">
                    </label>
                    <label class="field">
                        <span>Lifetime 100% voucher cap</span>
                        <input type="number" name="max_lifetime_free_vouchers" min="0" value="<?php echo (int) ($settings['max_lifetime_free_vouchers'] ?? 2); ?>">
                    </label>
                    <label class="field">
                        <span>Business photo max</span>
                        <input type="number" value="<?php echo (int) ($settings['max_business_photos'] ?? 6); ?>" disabled>
                    </label>
                </div>
            </section>
        </section>

        <section class="panel-card">
            <div class="section-row">
                <div>
                    <h2 style="margin-bottom:8px;">Wheel probabilities</h2>
                    <p class="reward-note">Enter percentages that add up to exactly 100. The backend uses weighted randomness from these values, and the front-end wheel only animates after the backend returns the actual winning reward.</p>
                </div>
            </div>
            <div class="probability-grid">
                <?php foreach ([5, 10, 20, 50, 100] as $rewardValue): ?>
                <label class="field">
                    <span><?php echo $rewardValue; ?>% OFF probability</span>
                    <input type="number" step="0.01" min="0" max="100" name="reward_probability_<?php echo $rewardValue; ?>" value="<?php echo htmlspecialchars(number_format(((float) ($probabilityMap[$rewardValue] ?? 0)) * 100, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
                </label>
                <?php endforeach; ?>
            </div>
            <div class="card-actions" style="margin-top:18px;">
                <button class="primary-btn" type="submit"><i data-lucide="save"></i>Save reward settings</button>
            </div>
        </section>
    </form>
</main>

<script>
window.where2goPageData = <?php echo json_encode([
    'visitedPlaceIds' => [],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="../assets/js/account.js"></script>
</body>
</html>


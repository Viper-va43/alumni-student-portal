<?php

/* -------------------------
   REWARDS DEFAULTS
------------------------- */
function get_where2go_default_reward_segments() {

    return [
        5 => [
            'label' => '5% OFF',
            'probability' => 0.40,
            'sort_order' => 10,
        ],
        10 => [
            'label' => '10% OFF',
            'probability' => 0.35,
            'sort_order' => 20,
        ],
        20 => [
            'label' => '20% OFF',
            'probability' => 0.20,
            'sort_order' => 30,
        ],
        50 => [
            'label' => '50% OFF',
            'probability' => 0.04,
            'sort_order' => 40,
        ],
        100 => [
            'label' => '100% OFF',
            'probability' => 0.01,
            'sort_order' => 50,
        ],
    ];

}


/* -------------------------
   REWARDS DEFAULT SETTINGS
------------------------- */
function get_where2go_default_reward_settings() {

    return [
        'max_business_photos' => 6,
        'first_scan_points' => 20,
        'repeat_scan_points' => 10,
        'same_day_repeat_points' => 3,
        'review_points' => 5,
        'daily_streak_multiplier' => 2,
        'daily_place_limit' => 5,
        'rapid_repeat_cooldown_minutes' => 15,
        'place_cooldown_hours' => 24,
        'max_same_day_repeat_scans_per_place' => 3,
        'review_requires_checkin' => 1,
        'level_formula_base' => 100,
        'level_formula_exponent' => 1.4,
        'mystery_box_every_levels' => 5,
        'voucher_expiry_min_days' => 7,
        'voucher_expiry_max_days' => 14,
        'max_lifetime_free_vouchers' => 2,
        'wheel_segments' => get_where2go_default_reward_segments(),
    ];

}


/* -------------------------
   CLEAR REWARDS CACHE
------------------------- */
function clear_where2go_rewards_settings_cache() {

    unset($GLOBALS['where2go_rewards_settings_cache']);

}


/* -------------------------
   DROP TABLE INDEX
------------------------- */
function drop_table_index_if_exists($conn, $table_name, $index_name) {

    $table_name = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table_name);
    $index_name = trim((string) $index_name);

    if (!$conn || $table_name === '' || $index_name === '') {
        return;
    }

    $escaped = $conn->real_escape_string($index_name);
    $result = $conn->query("SHOW INDEX FROM `{$table_name}` WHERE Key_name = '{$escaped}'");

    if ($result && $result->num_rows > 0) {
        $conn->query("ALTER TABLE `{$table_name}` DROP INDEX `{$index_name}`");
    }

}


/* -------------------------
   UPSERT CONFIG
------------------------- */
function upsert_where2go_reward_config($conn, $config_key, $config_value) {

    $config_key = trim((string) $config_key);

    if (!$conn || $config_key === '') {
        return false;
    }

    $config_value = (string) $config_value;
    $stmt = $conn->prepare("INSERT INTO reward_program_config (config_key, config_value, updated_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                config_value = VALUES(config_value),
                updated_at = NOW()");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("ss", $config_key, $config_value);
    return (bool) $stmt->execute();

}


/* -------------------------
   SEED DEFAULT CONFIG
------------------------- */
function seed_where2go_reward_program_config($conn) {

    if (!$conn) {
        return;
    }

    $defaults = get_where2go_default_reward_settings();
    $configKeys = [
        'max_business_photos',
        'first_scan_points',
        'repeat_scan_points',
        'same_day_repeat_points',
        'review_points',
        'daily_streak_multiplier',
        'daily_place_limit',
        'rapid_repeat_cooldown_minutes',
        'place_cooldown_hours',
        'max_same_day_repeat_scans_per_place',
        'review_requires_checkin',
        'level_formula_base',
        'level_formula_exponent',
        'mystery_box_every_levels',
        'voucher_expiry_min_days',
        'voucher_expiry_max_days',
        'max_lifetime_free_vouchers',
    ];

    foreach ($configKeys as $configKey) {
        if (array_key_exists($configKey, $defaults)) {
            $configValue = (string) $defaults[$configKey];
            $stmt = $conn->prepare("INSERT IGNORE INTO reward_program_config (config_key, config_value, updated_at)
                    VALUES (?, ?, NOW())");

            if (!$stmt) {
                continue;
            }

            $stmt->bind_param("ss", $configKey, $configValue);
            $stmt->execute();
        }
    }

}


/* -------------------------
   SEED DEFAULT REWARDS
------------------------- */
function seed_where2go_reward_catalog($conn) {

    if (!$conn) {
        return;
    }

    foreach (get_where2go_default_reward_segments() as $value => $segment) {
        $rewardType = 'percentage_off';
        $label = trim((string) ($segment['label'] ?? ($value . '% OFF')));
        $probability = (float) ($segment['probability'] ?? 0);
        $sortOrder = (int) ($segment['sort_order'] ?? $value);
        $stmt = $conn->prepare("INSERT INTO rewards (type, value, label, probability, is_active, sort_order, created_at, updated_at)
                VALUES (?, ?, ?, ?, 1, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    label = VALUES(label),
                    sort_order = VALUES(sort_order),
                    updated_at = NOW()");

        if (!$stmt) {
            continue;
        }

        $stmt->bind_param("sisdi", $rewardType, $value, $label, $probability, $sortOrder);
        $stmt->execute();
    }

}


/* -------------------------
   ENSURE REWARDS SCHEMA
------------------------- */
function ensure_where2go_rewards_schema() {

    static $ensured = false;

    if ($ensured) {
        return;
    }

    $conn = db_connect();

    if (!$conn) {
        return;
    }

    ensure_table_column($conn, 'business_locations', 'promo_code', "ALTER TABLE business_locations ADD COLUMN promo_code VARCHAR(80) NULL AFTER phone");
    ensure_table_column($conn, 'business_locations', 'promo_details', "ALTER TABLE business_locations ADD COLUMN promo_details TEXT NULL AFTER promo_code");
    ensure_table_column($conn, 'business_locations', 'qr_token', "ALTER TABLE business_locations ADD COLUMN qr_token VARCHAR(64) NULL AFTER promo_details");
    ensure_table_column($conn, 'business_locations', 'checkin_enabled', "ALTER TABLE business_locations ADD COLUMN checkin_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER has_reservations");
    ensure_table_index($conn, 'business_locations', 'uniq_business_locations_qr_token', "ALTER TABLE business_locations ADD UNIQUE KEY uniq_business_locations_qr_token (qr_token)");

    $conn->query("CREATE TABLE IF NOT EXISTS business_reviews (
            review_id INT AUTO_INCREMENT PRIMARY KEY,
            business_id INT NOT NULL,
            location_id INT NULL,
            customer_id INT NOT NULL,
            rating TINYINT UNSIGNED NOT NULL,
            comment TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_business_review_customer (business_id, customer_id),
            KEY idx_business_reviews_location (location_id),
            KEY idx_business_reviews_customer (customer_id),
            CONSTRAINT fk_business_reviews_business
                FOREIGN KEY (business_id) REFERENCES businesses (business_id) ON DELETE CASCADE,
            CONSTRAINT fk_business_reviews_location
                FOREIGN KEY (location_id) REFERENCES business_locations (location_id) ON DELETE SET NULL,
            CONSTRAINT fk_business_reviews_customer
                FOREIGN KEY (customer_id) REFERENCES customers (Customer_ID) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS customer_rewards (
            customer_id INT PRIMARY KEY,
            total_points INT NOT NULL DEFAULT 0,
            total_xp INT NOT NULL DEFAULT 0,
            current_level INT NOT NULL DEFAULT 0,
            streak INT NOT NULL DEFAULT 0,
            longest_streak INT NOT NULL DEFAULT 0,
            total_scans INT NOT NULL DEFAULT 0,
            total_checkins INT NOT NULL DEFAULT 0,
            last_checkin_date DATE NULL,
            last_checkin_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_customer_rewards_customer
                FOREIGN KEY (customer_id) REFERENCES customers (Customer_ID) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    ensure_table_column($conn, 'customer_rewards', 'streak', "ALTER TABLE customer_rewards ADD COLUMN streak INT NOT NULL DEFAULT 0 AFTER current_level");
    ensure_table_column($conn, 'customer_rewards', 'longest_streak', "ALTER TABLE customer_rewards ADD COLUMN longest_streak INT NOT NULL DEFAULT 0 AFTER streak");
    ensure_table_column($conn, 'customer_rewards', 'total_scans', "ALTER TABLE customer_rewards ADD COLUMN total_scans INT NOT NULL DEFAULT 0 AFTER longest_streak");
    ensure_table_column($conn, 'customer_rewards', 'last_checkin_date', "ALTER TABLE customer_rewards ADD COLUMN last_checkin_date DATE NULL AFTER total_checkins");
    ensure_table_column($conn, 'customer_rewards', 'last_checkin_at', "ALTER TABLE customer_rewards ADD COLUMN last_checkin_at DATETIME NULL AFTER last_checkin_date");

    $conn->query("CREATE TABLE IF NOT EXISTS customer_checkins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            business_id INT NOT NULL,
            location_id INT NOT NULL,
            promo_code_snapshot VARCHAR(80) NULL,
            scan_type VARCHAR(40) NOT NULL DEFAULT 'first_visit',
            points_awarded INT NOT NULL DEFAULT 0,
            xp_awarded INT NOT NULL DEFAULT 0,
            base_points_awarded INT NOT NULL DEFAULT 0,
            streak_bonus_awarded INT NOT NULL DEFAULT 0,
            checkin_date DATE NOT NULL,
            checked_in_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            cooldown_ends_at DATETIME NULL,
            source VARCHAR(20) NOT NULL DEFAULT 'qr',
            KEY idx_customer_checkins_customer_date (customer_id, checkin_date),
            KEY idx_customer_checkins_customer_location (customer_id, location_id, checked_in_at),
            KEY idx_customer_checkins_business (business_id),
            KEY idx_customer_checkins_location (location_id),
            CONSTRAINT fk_customer_checkins_customer
                FOREIGN KEY (customer_id) REFERENCES customers (Customer_ID) ON DELETE CASCADE,
            CONSTRAINT fk_customer_checkins_business
                FOREIGN KEY (business_id) REFERENCES businesses (business_id) ON DELETE CASCADE,
            CONSTRAINT fk_customer_checkins_location
                FOREIGN KEY (location_id) REFERENCES business_locations (location_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    ensure_table_column($conn, 'customer_checkins', 'scan_type', "ALTER TABLE customer_checkins ADD COLUMN scan_type VARCHAR(40) NOT NULL DEFAULT 'first_visit' AFTER promo_code_snapshot");
    ensure_table_column($conn, 'customer_checkins', 'base_points_awarded', "ALTER TABLE customer_checkins ADD COLUMN base_points_awarded INT NOT NULL DEFAULT 0 AFTER xp_awarded");
    ensure_table_column($conn, 'customer_checkins', 'streak_bonus_awarded', "ALTER TABLE customer_checkins ADD COLUMN streak_bonus_awarded INT NOT NULL DEFAULT 0 AFTER base_points_awarded");
    ensure_table_column($conn, 'customer_checkins', 'cooldown_ends_at', "ALTER TABLE customer_checkins ADD COLUMN cooldown_ends_at DATETIME NULL AFTER checked_in_at");
    ensure_table_index($conn, 'customer_checkins', 'idx_customer_checkins_customer_location', "ALTER TABLE customer_checkins ADD KEY idx_customer_checkins_customer_location (customer_id, location_id, checked_in_at)");
    drop_table_index_if_exists($conn, 'customer_checkins', 'uniq_customer_location_day');

    $conn->query("CREATE TABLE IF NOT EXISTS reward_program_config (
            config_key VARCHAR(80) PRIMARY KEY,
            config_value VARCHAR(255) NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS rewards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(40) NOT NULL DEFAULT 'percentage_off',
            value INT NOT NULL,
            label VARCHAR(80) NOT NULL,
            probability DECIMAL(8,5) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_rewards_type_value (type, value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    ensure_table_column($conn, 'rewards', 'sort_order', "ALTER TABLE rewards ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER is_active");

    $conn->query("CREATE TABLE IF NOT EXISTS reward_boxes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            business_id INT NOT NULL,
            location_id INT NOT NULL,
            trigger_level INT NOT NULL,
            unlock_points INT NOT NULL DEFAULT 0,
            source_context VARCHAR(20) NOT NULL DEFAULT 'scan',
            reward_id INT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            spun_at DATETIME NULL,
            expires_at DATETIME NULL,
            UNIQUE KEY uniq_reward_boxes_customer_level (customer_id, trigger_level),
            KEY idx_reward_boxes_customer_status (customer_id, status, created_at),
            KEY idx_reward_boxes_business (business_id),
            KEY idx_reward_boxes_location (location_id),
            CONSTRAINT fk_reward_boxes_customer
                FOREIGN KEY (customer_id) REFERENCES customers (Customer_ID) ON DELETE CASCADE,
            CONSTRAINT fk_reward_boxes_business
                FOREIGN KEY (business_id) REFERENCES businesses (business_id) ON DELETE CASCADE,
            CONSTRAINT fk_reward_boxes_location
                FOREIGN KEY (location_id) REFERENCES business_locations (location_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    ensure_table_column($conn, 'reward_boxes', 'unlock_points', "ALTER TABLE reward_boxes ADD COLUMN unlock_points INT NOT NULL DEFAULT 0 AFTER trigger_level");
    ensure_table_column($conn, 'reward_boxes', 'source_context', "ALTER TABLE reward_boxes ADD COLUMN source_context VARCHAR(20) NOT NULL DEFAULT 'scan' AFTER unlock_points");
    ensure_table_column($conn, 'reward_boxes', 'reward_id', "ALTER TABLE reward_boxes ADD COLUMN reward_id INT NULL AFTER source_context");
    ensure_table_column($conn, 'reward_boxes', 'expires_at', "ALTER TABLE reward_boxes ADD COLUMN expires_at DATETIME NULL AFTER spun_at");

    $conn->query("CREATE TABLE IF NOT EXISTS user_rewards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            reward_id INT NOT NULL,
            reward_box_id INT NULL,
            business_id INT NOT NULL,
            location_id INT NOT NULL,
            voucher_code VARCHAR(64) NOT NULL,
            claimed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            used TINYINT(1) NOT NULL DEFAULT 0,
            used_at DATETIME NULL,
            expires_at DATETIME NOT NULL,
            KEY idx_user_rewards_user (user_id, used, expires_at),
            KEY idx_user_rewards_business (business_id),
            UNIQUE KEY uniq_user_rewards_voucher_code (voucher_code),
            CONSTRAINT fk_user_rewards_user
                FOREIGN KEY (user_id) REFERENCES customers (Customer_ID) ON DELETE CASCADE,
            CONSTRAINT fk_user_rewards_reward
                FOREIGN KEY (reward_id) REFERENCES rewards (id) ON DELETE CASCADE,
            CONSTRAINT fk_user_rewards_box
                FOREIGN KEY (reward_box_id) REFERENCES reward_boxes (id) ON DELETE SET NULL,
            CONSTRAINT fk_user_rewards_business
                FOREIGN KEY (business_id) REFERENCES businesses (business_id) ON DELETE CASCADE,
            CONSTRAINT fk_user_rewards_location
                FOREIGN KEY (location_id) REFERENCES business_locations (location_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    seed_where2go_reward_program_config($conn);
    seed_where2go_reward_catalog($conn);

    $result = $conn->query("SELECT location_id, qr_token FROM business_locations");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            ensure_location_qr_token(
                (int) ($row['location_id'] ?? 0),
                (string) ($row['qr_token'] ?? ''),
                $conn
            );
        }
    }

    $ensured = true;

}


/* -------------------------
   REWARD CONFIG MAP
------------------------- */
function get_where2go_reward_config_map($conn = null) {

    ensure_where2go_rewards_schema();

    $ownsConnection = false;

    if (!$conn) {
        $conn = db_connect();
        $ownsConnection = true;
    }

    if (!$conn) {
        return [];
    }

    $result = $conn->query("SELECT config_key, config_value FROM reward_program_config");
    $config = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $config[(string) ($row['config_key'] ?? '')] = (string) ($row['config_value'] ?? '');
        }
    }

    if ($ownsConnection) {
        // The shared db helper uses a singleton connection, so there is nothing to close here.
    }

    return $config;

}


/* -------------------------
   ACTIVE REWARD DEFINITIONS
------------------------- */
function get_active_reward_definitions($conn = null, $includeInactive = false) {

    ensure_where2go_rewards_schema();

    $ownsConnection = false;

    if (!$conn) {
        $conn = db_connect();
        $ownsConnection = true;
    }

    if (!$conn) {
        return [];
    }

    $sql = "SELECT id, type, value, label, probability, is_active, sort_order
            FROM rewards";

    if (!$includeInactive) {
        $sql .= " WHERE is_active = 1";
    }

    $sql .= " ORDER BY sort_order ASC, value ASC, id ASC";
    $result = $conn->query($sql);
    $definitions = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int) ($row['id'] ?? 0);
            $row['value'] = (int) ($row['value'] ?? 0);
            $row['probability'] = (float) ($row['probability'] ?? 0);
            $row['is_active'] = (int) ($row['is_active'] ?? 0);
            $row['sort_order'] = (int) ($row['sort_order'] ?? 0);
            $definitions[] = $row;
        }
    }

    if (!$definitions) {
        foreach (get_where2go_default_reward_segments() as $value => $segment) {
            $definitions[] = [
                'id' => 0,
                'type' => 'percentage_off',
                'value' => (int) $value,
                'label' => (string) ($segment['label'] ?? ($value . '% OFF')),
                'probability' => (float) ($segment['probability'] ?? 0),
                'is_active' => 1,
                'sort_order' => (int) ($segment['sort_order'] ?? $value),
            ];
        }
    }

    if ($ownsConnection) {
        // Intentionally left blank because the project shares one mysqli connection.
    }

    return $definitions;

}


/* -------------------------
   REWARDS SETTINGS
------------------------- */
function get_where2go_rewards_program_settings() {

    if (isset($GLOBALS['where2go_rewards_settings_cache']) && is_array($GLOBALS['where2go_rewards_settings_cache'])) {
        return $GLOBALS['where2go_rewards_settings_cache'];
    }

    $defaults = get_where2go_default_reward_settings();
    $config = get_where2go_reward_config_map();
    $settings = $defaults;
    $intKeys = [
        'max_business_photos',
        'first_scan_points',
        'repeat_scan_points',
        'same_day_repeat_points',
        'review_points',
        'daily_streak_multiplier',
        'daily_place_limit',
        'rapid_repeat_cooldown_minutes',
        'place_cooldown_hours',
        'max_same_day_repeat_scans_per_place',
        'review_requires_checkin',
        'mystery_box_every_levels',
        'voucher_expiry_min_days',
        'voucher_expiry_max_days',
        'max_lifetime_free_vouchers',
    ];
    $floatKeys = [
        'level_formula_base',
        'level_formula_exponent',
    ];

    foreach ($intKeys as $key) {
        if (isset($config[$key]) && is_numeric($config[$key])) {
            $settings[$key] = (int) $config[$key];
        }
    }

    foreach ($floatKeys as $key) {
        if (isset($config[$key]) && is_numeric($config[$key])) {
            $settings[$key] = (float) $config[$key];
        }
    }

    $wheelSegments = [];

    foreach (get_active_reward_definitions() as $definition) {
        $rewardValue = (int) ($definition['value'] ?? 0);

        if ($rewardValue <= 0) {
            continue;
        }

        $wheelSegments[$rewardValue] = [
            'label' => trim((string) ($definition['label'] ?? ($rewardValue . '% OFF'))),
            'probability' => max(0, (float) ($definition['probability'] ?? 0)),
            'sort_order' => (int) ($definition['sort_order'] ?? $rewardValue),
        ];
    }

    if (!$wheelSegments) {
        $wheelSegments = $defaults['wheel_segments'];
    }

    $settings['wheel_segments'] = $wheelSegments;
    $settings['points_per_visit'] = (int) ($settings['first_scan_points'] ?? 20);
    $settings['xp_per_visit'] = (int) ($settings['first_scan_points'] ?? 20);
    $settings['level_thresholds'] = [
        1 => calculate_required_points_for_level(1),
    ];
    $settings['fallback_xp_per_level'] = calculate_required_points_for_level(1);
    $GLOBALS['where2go_rewards_settings_cache'] = $settings;

    return $settings;

}


/* -------------------------
   REQUIRED POINTS FOR LEVEL
------------------------- */
function calculate_required_points_for_level($level) {

    $level = max(1, (int) $level);
    $settings = get_where2go_default_reward_settings();
    $base = max(1, (float) ($settings['level_formula_base'] ?? 100));
    $exponent = max(1.0, (float) ($settings['level_formula_exponent'] ?? 1.4));
    $runtimeSettings = isset($GLOBALS['where2go_rewards_settings_cache']) && is_array($GLOBALS['where2go_rewards_settings_cache'])
        ? $GLOBALS['where2go_rewards_settings_cache']
        : null;

    if ($runtimeSettings) {
        $base = max(1, (float) ($runtimeSettings['level_formula_base'] ?? $base));
        $exponent = max(1.0, (float) ($runtimeSettings['level_formula_exponent'] ?? $exponent));
    }

    return (int) ceil($base * pow($level, $exponent));

}


/* -------------------------
   CUSTOMER LEVEL FROM POINTS
------------------------- */
function calculate_customer_level_from_points($total_points) {

    $total_points = max(0, (int) $total_points);
    $level = 0;

    while ($total_points >= calculate_required_points_for_level($level + 1)) {
        $level++;

        if ($level >= 1000) {
            break;
        }
    }

    return $level;

}


/* -------------------------
   CUSTOMER LEVEL
------------------------- */
function calculate_customer_level_from_xp($total_xp) {

    return calculate_customer_level_from_points($total_xp);

}


/* -------------------------
   LEVEL PROGRESS
------------------------- */
function get_customer_level_progress($total_points) {

    $total_points = max(0, (int) $total_points);
    $currentLevel = calculate_customer_level_from_points($total_points);
    $currentThreshold = $currentLevel > 0 ? calculate_required_points_for_level($currentLevel) : 0;
    $nextLevel = $currentLevel + 1;
    $nextThreshold = calculate_required_points_for_level($nextLevel);
    $previousThreshold = $currentLevel > 0 ? calculate_required_points_for_level(max(1, $currentLevel)) : 0;

    if ($currentLevel <= 0) {
        $previousThreshold = 0;
    } elseif ($currentLevel > 1) {
        $previousThreshold = calculate_required_points_for_level($currentLevel - 1);
    }

    $range = max(1, $nextThreshold - $previousThreshold);
    $progressPoints = max(0, $total_points - $previousThreshold);
    $progressPercent = min(100, (int) round(($progressPoints / $range) * 100));

    return [
        'current_level' => $currentLevel,
        'current_threshold' => $currentThreshold,
        'previous_threshold' => $previousThreshold,
        'next_level' => $nextLevel,
        'next_threshold' => $nextThreshold,
        'progress_points' => $progressPoints,
        'progress_percent' => $progressPercent,
        'points_to_next_level' => max(0, $nextThreshold - $total_points),
    ];

}


/* -------------------------
   APP BASE URL
------------------------- */
function get_where2go_base_url() {

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
    $projectRoot = realpath(__DIR__ . '/..');
    $basePath = '';

    if ($documentRoot && $projectRoot) {
        $normalizedDocumentRoot = str_replace('\\', '/', $documentRoot);
        $normalizedProjectRoot = str_replace('\\', '/', $projectRoot);

        if (stripos($normalizedProjectRoot, $normalizedDocumentRoot) === 0) {
            $basePath = substr($normalizedProjectRoot, strlen($normalizedDocumentRoot));
            $basePath = '/' . trim((string) $basePath, '/');
            $basePath = $basePath === '/' ? '' : $basePath;
        }
    }

    if ($basePath === '') {
        $scriptDirectory = trim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        $basePath = $scriptDirectory !== '' && $scriptDirectory !== '.' ? '/' . $scriptDirectory : '';

        if (substr($basePath, -6) === '/pages') {
            $basePath = substr($basePath, 0, -6);
        }

        if (substr($basePath, -6) === '/admin') {
            $basePath = substr($basePath, 0, -6);
        }
    }

    if ($host === '') {
        return $basePath;
    }

    return $scheme . '://' . $host . $basePath;

}


/* -------------------------
   SAFE REDIRECT TARGET
------------------------- */
function get_safe_internal_redirect_target($target, $fallback = 'Home.php') {

    $target = trim((string) $target);
    $fallback = trim((string) $fallback) !== '' ? trim((string) $fallback) : 'Home.php';

    if ($target === '') {
        return $fallback;
    }

    if (preg_match('/[\r\n]/', $target) || preg_match('#^[a-z][a-z0-9+\-.]*:#i', $target) || strpos($target, '//') === 0) {
        return $fallback;
    }

    if ($target[0] === '/') {
        return $target;
    }

    return preg_match("#^[A-Za-z0-9._/\\-]+(?:\\?[A-Za-z0-9\\-._~%!$&'()*+,;=:@/?]*)?$#", $target)
        ? $target
        : $fallback;

}


/* -------------------------
   CHECK-IN URL
------------------------- */
function build_location_checkin_url($qr_token) {

    $qr_token = trim((string) $qr_token);

    if ($qr_token === '') {
        return '';
    }

    $baseUrl = rtrim((string) get_where2go_base_url(), '/');
    $path = 'checkin.php?token=' . rawurlencode($qr_token);

    return $baseUrl !== '' ? $baseUrl . '/' . $path : $path;

}


/* -------------------------
   GENERATE QR TOKEN
------------------------- */
function generate_unique_location_qr_token($conn) {

    if (!$conn) {
        return '';
    }

    for ($attempt = 0; $attempt < 6; $attempt++) {
        try {
            $token = bin2hex(random_bytes(16));
        } catch (Throwable $error) {
            $token = md5(uniqid('w2g', true));
        }

        $stmt = $conn->prepare("SELECT location_id FROM business_locations WHERE qr_token = ? LIMIT 1");

        if (!$stmt) {
            return $token;
        }

        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result || !$result->fetch_assoc()) {
            return $token;
        }
    }

    return md5(uniqid('where2go-location', true));

}


/* -------------------------
   ENSURE LOCATION QR TOKEN
------------------------- */
function ensure_location_qr_token($location_id, $existing_token = '', $conn = null) {

    $location_id = (int) $location_id;
    $existing_token = trim((string) $existing_token);

    if ($location_id <= 0) {
        return '';
    }

    if ($existing_token !== '') {
        return $existing_token;
    }

    if (!$conn) {
        $conn = db_connect();
    }

    if (!$conn) {
        return '';
    }

    $token = generate_unique_location_qr_token($conn);

    if ($token === '') {
        return '';
    }

    $stmt = $conn->prepare("UPDATE business_locations SET qr_token = ? WHERE location_id = ?");

    if (!$stmt) {
        return '';
    }

    $stmt->bind_param("si", $token, $location_id);

    if (!$stmt->execute()) {
        return '';
    }

    return $token;

}


/* -------------------------
   LOCATION BY QR TOKEN
------------------------- */
function get_location_by_qr_token($qr_token) {

    $qr_token = trim((string) $qr_token);

    if ($qr_token === '') {
        return null;
    }

    ensure_where2go_rewards_schema();

    $conn = db_connect();
    $sql = "SELECT bl.location_id, bl.business_id, bl.location_name, bl.address, bl.phone, bl.promo_code, bl.promo_details,
                   bl.qr_token, bl.capacity_per_hour, bl.has_reservations, bl.checkin_enabled,
                   b.name AS business_name, b.type AS business_type, b.custom_type, b.website, b.approval_status
            FROM business_locations bl
            INNER JOIN businesses b ON b.business_id = bl.business_id
            WHERE bl.qr_token = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $qr_token);
    $stmt->execute();
    $result = $stmt->get_result();
    $location = $result ? $result->fetch_assoc() : null;

    if (!$location) {
        return null;
    }

    $location['checkin_enabled'] = (int) ($location['checkin_enabled'] ?? 1);
    $location['type_label'] = format_business_type_label($location['business_type'] ?? 'other', $location['custom_type'] ?? '');
    $location['checkin_url'] = build_location_checkin_url((string) ($location['qr_token'] ?? ''));

    return $location;

}


/* -------------------------
   LOCK CUSTOMER REWARDS ROW
------------------------- */
function lock_customer_rewards_row($conn, $customer_id) {

    $customer_id = (int) $customer_id;

    if (!$conn || $customer_id <= 0) {
        return null;
    }

    $insertStmt = $conn->prepare("INSERT INTO customer_rewards
            (customer_id, total_points, total_xp, current_level, streak, longest_streak, total_scans, total_checkins, created_at, updated_at)
            VALUES (?, 0, 0, 0, 0, 0, 0, 0, NOW(), NOW())
            ON DUPLICATE KEY UPDATE customer_id = customer_id");

    if ($insertStmt) {
        $insertStmt->bind_param("i", $customer_id);
        $insertStmt->execute();
    }

    $stmt = $conn->prepare("SELECT customer_id, total_points, total_xp, current_level, streak, longest_streak, total_scans, total_checkins,
                   last_checkin_date, last_checkin_at
            FROM customer_rewards
            WHERE customer_id = ?
            LIMIT 1
            FOR UPDATE");

    if (!$stmt) {
        return [
            'customer_id' => $customer_id,
            'total_points' => 0,
            'total_xp' => 0,
            'current_level' => 0,
            'streak' => 0,
            'longest_streak' => 0,
            'total_scans' => 0,
            'total_checkins' => 0,
            'last_checkin_date' => null,
            'last_checkin_at' => null,
        ];
    }

    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    if (!$row) {
        return [
            'customer_id' => $customer_id,
            'total_points' => 0,
            'total_xp' => 0,
            'current_level' => 0,
            'streak' => 0,
            'longest_streak' => 0,
            'total_scans' => 0,
            'total_checkins' => 0,
            'last_checkin_date' => null,
            'last_checkin_at' => null,
        ];
    }

    $row['total_points'] = (int) ($row['total_points'] ?? 0);
    $row['total_xp'] = (int) ($row['total_xp'] ?? 0);
    $row['current_level'] = (int) ($row['current_level'] ?? 0);
    $row['streak'] = (int) ($row['streak'] ?? 0);
    $row['longest_streak'] = (int) ($row['longest_streak'] ?? 0);
    $row['total_scans'] = (int) ($row['total_scans'] ?? 0);
    $row['total_checkins'] = (int) ($row['total_checkins'] ?? 0);

    return $row;

}


/* -------------------------
   LEVEL BOX UNLOCKS
------------------------- */
function create_reward_boxes_for_level_range($conn, $customer_id, $old_level, $new_level, $business_id, $location_id, $unlock_points, $source_context = 'scan') {

    $customer_id = (int) $customer_id;
    $old_level = max(0, (int) $old_level);
    $new_level = max(0, (int) $new_level);
    $business_id = (int) $business_id;
    $location_id = (int) $location_id;
    $unlock_points = max(0, (int) $unlock_points);
    $source_context = trim((string) $source_context) !== '' ? trim((string) $source_context) : 'scan';
    $settings = get_where2go_rewards_program_settings();
    $unlockEvery = max(1, (int) ($settings['mystery_box_every_levels'] ?? 5));

    if (!$conn || $customer_id <= 0 || $new_level <= $old_level) {
        return [];
    }

    $milestones = [];

    for ($level = $old_level + 1; $level <= $new_level; $level++) {
        if ($level % $unlockEvery === 0) {
            $milestones[] = $level;
        }
    }

    if (!$milestones) {
        return [];
    }

    $insertStmt = $conn->prepare("INSERT INTO reward_boxes
            (customer_id, business_id, location_id, trigger_level, unlock_points, source_context, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ON DUPLICATE KEY UPDATE
                business_id = VALUES(business_id),
                location_id = VALUES(location_id),
                unlock_points = VALUES(unlock_points),
                source_context = VALUES(source_context)");

    foreach ($milestones as $milestone) {
        if ($insertStmt) {
            $insertStmt->bind_param("iiiiis", $customer_id, $business_id, $location_id, $milestone, $unlock_points, $source_context);
            $insertStmt->execute();
        }
    }

    $placeholders = implode(',', array_fill(0, count($milestones), '?'));
    $types = 'i' . str_repeat('i', count($milestones));
    $params = array_merge([$customer_id], $milestones);
    $sql = "SELECT id, customer_id, business_id, location_id, trigger_level, unlock_points, source_context, status, created_at
            FROM reward_boxes
            WHERE customer_id = ?
              AND trigger_level IN ({$placeholders})
            ORDER BY trigger_level ASC";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $boxes = [];

    while ($result && ($row = $result->fetch_assoc())) {
        $boxes[] = $row;
    }

    return $boxes;

}


/* -------------------------
   APPLY REWARD POINTS DELTA
------------------------- */
function apply_customer_reward_points_delta($conn, $customer_id, $points_delta, $options = []) {

    $customer_id = (int) $customer_id;
    $points_delta = max(0, (int) $points_delta);
    $options = is_array($options) ? $options : [];

    if (!$conn || $customer_id <= 0) {
        return ['ok' => false, 'message' => 'Customer account is missing.'];
    }

    $current = lock_customer_rewards_row($conn, $customer_id);

    if (!$current) {
        return ['ok' => false, 'message' => 'Customer reward progress could not be loaded.'];
    }

    $oldLevel = calculate_customer_level_from_points((int) ($current['total_points'] ?? 0));
    $newTotalPoints = (int) ($current['total_points'] ?? 0) + $points_delta;
    $newTotalXp = $newTotalPoints;
    $newLevel = calculate_customer_level_from_points($newTotalPoints);
    $newStreak = array_key_exists('streak', $options) ? max(0, (int) $options['streak']) : (int) ($current['streak'] ?? 0);
    $newLongestStreak = max((int) ($current['longest_streak'] ?? 0), $newStreak, (int) ($options['longest_streak'] ?? 0));
    $incrementScans = max(0, (int) ($options['increment_scans'] ?? 0));
    $incrementCheckins = max(0, (int) ($options['increment_checkins'] ?? $incrementScans));
    $newTotalScans = (int) ($current['total_scans'] ?? 0) + $incrementScans;
    $newTotalCheckins = (int) ($current['total_checkins'] ?? 0) + $incrementCheckins;
    $lastCheckinDate = array_key_exists('last_checkin_date', $options)
        ? ($options['last_checkin_date'] !== '' ? (string) $options['last_checkin_date'] : null)
        : ($current['last_checkin_date'] ?? null);
    $lastCheckinAt = array_key_exists('last_checkin_at', $options)
        ? ($options['last_checkin_at'] !== '' ? (string) $options['last_checkin_at'] : null)
        : ($current['last_checkin_at'] ?? null);
    $businessId = (int) ($options['business_id'] ?? 0);
    $locationId = (int) ($options['location_id'] ?? 0);
    $sourceContext = trim((string) ($options['source_context'] ?? 'scan'));
    $updateStmt = $conn->prepare("UPDATE customer_rewards
            SET total_points = ?,
                total_xp = ?,
                current_level = ?,
                streak = ?,
                longest_streak = ?,
                total_scans = ?,
                total_checkins = ?,
                last_checkin_date = ?,
                last_checkin_at = ?,
                updated_at = NOW()
            WHERE customer_id = ?");

    if (!$updateStmt) {
        return ['ok' => false, 'message' => 'Customer reward progress could not be updated.'];
    }

    $updateStmt->bind_param(
        "iiiiiiissi",
        $newTotalPoints,
        $newTotalXp,
        $newLevel,
        $newStreak,
        $newLongestStreak,
        $newTotalScans,
        $newTotalCheckins,
        $lastCheckinDate,
        $lastCheckinAt,
        $customer_id
    );

    if (!$updateStmt->execute()) {
        return ['ok' => false, 'message' => 'Customer reward progress could not be saved.'];
    }

    $boxes = create_reward_boxes_for_level_range(
        $conn,
        $customer_id,
        $oldLevel,
        $newLevel,
        $businessId,
        $locationId,
        $newTotalPoints,
        $sourceContext
    );

    return [
        'ok' => true,
        'old_level' => $oldLevel,
        'new_level' => $newLevel,
        'new_total_points' => $newTotalPoints,
        'new_total_xp' => $newTotalXp,
        'new_streak' => $newStreak,
        'boxes_unlocked' => $boxes,
    ];

}


/* -------------------------
   CHECK-INS TODAY
------------------------- */
function get_customer_checkins_count_for_date($customer_id, $date = null, $conn = null) {

    $customer_id = (int) $customer_id;
    $date = $date !== null ? trim((string) $date) : date('Y-m-d');

    if ($customer_id <= 0 || !strtotime($date)) {
        return 0;
    }

    ensure_where2go_rewards_schema();

    if (!$conn) {
        $conn = db_connect();
    }

    $sql = "SELECT COUNT(DISTINCT location_id) AS total
            FROM customer_checkins
            WHERE customer_id = ?
              AND checkin_date = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param("is", $customer_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    return (int) ($row['total'] ?? 0);

}


/* -------------------------
   LOCATION SCAN SNAPSHOT
------------------------- */
function get_customer_location_scan_snapshot($conn, $customer_id, $location_id, $today) {

    $customer_id = (int) $customer_id;
    $location_id = (int) $location_id;
    $today = trim((string) $today);

    if (!$conn || $customer_id <= 0 || $location_id <= 0) {
        return [
            'lifetime_scans' => 0,
            'today_scans' => 0,
            'today_same_day_repeat_scans' => 0,
            'last_scan_at' => null,
            'last_scan_date' => null,
        ];
    }

    $sql = "SELECT COUNT(*) AS lifetime_scans,
                   SUM(CASE WHEN checkin_date = ? THEN 1 ELSE 0 END) AS today_scans,
                   SUM(CASE WHEN checkin_date = ? AND scan_type = 'same_day_repeat' THEN 1 ELSE 0 END) AS today_same_day_repeat_scans,
                   MAX(checked_in_at) AS last_scan_at,
                   MAX(checkin_date) AS last_scan_date
            FROM customer_checkins
            WHERE customer_id = ?
              AND location_id = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [
            'lifetime_scans' => 0,
            'today_scans' => 0,
            'today_same_day_repeat_scans' => 0,
            'last_scan_at' => null,
            'last_scan_date' => null,
        ];
    }

    $stmt->bind_param("ssii", $today, $today, $customer_id, $location_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    return [
        'lifetime_scans' => (int) ($row['lifetime_scans'] ?? 0),
        'today_scans' => (int) ($row['today_scans'] ?? 0),
        'today_same_day_repeat_scans' => (int) ($row['today_same_day_repeat_scans'] ?? 0),
        'last_scan_at' => $row['last_scan_at'] ?? null,
        'last_scan_date' => $row['last_scan_date'] ?? null,
    ];

}


/* -------------------------
   PENDING REWARD BOX COUNT
------------------------- */
function get_customer_pending_reward_box_count($customer_id, $conn = null) {

    $customer_id = (int) $customer_id;

    if ($customer_id <= 0) {
        return 0;
    }

    ensure_where2go_rewards_schema();

    if (!$conn) {
        $conn = db_connect();
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS total
            FROM reward_boxes
            WHERE customer_id = ?
              AND status = 'pending'");

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    return (int) ($row['total'] ?? 0);

}


/* -------------------------
   PENDING REWARD BOXES
------------------------- */
function get_customer_pending_reward_boxes($customer_id, $limit = 5) {

    $customer_id = (int) $customer_id;
    $limit = max(1, (int) $limit);

    if ($customer_id <= 0) {
        return [];
    }

    ensure_where2go_rewards_schema();

    $conn = db_connect();
    $sql = "SELECT rb.id, rb.customer_id, rb.business_id, rb.location_id, rb.trigger_level, rb.unlock_points, rb.source_context,
                   rb.created_at, b.name AS business_name, bl.location_name, bl.address AS location_address
            FROM reward_boxes rb
            INNER JOIN businesses b ON b.business_id = rb.business_id
            INNER JOIN business_locations bl ON bl.location_id = rb.location_id
            WHERE rb.customer_id = ?
              AND rb.status = 'pending'
            ORDER BY rb.trigger_level ASC, rb.created_at ASC
            LIMIT " . $limit;
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $boxes = [];

    while ($result && ($row = $result->fetch_assoc())) {
        $row['trigger_level'] = (int) ($row['trigger_level'] ?? 0);
        $row['unlock_points'] = (int) ($row['unlock_points'] ?? 0);
        $boxes[] = $row;
    }

    return $boxes;

}


/* -------------------------
   CUSTOMER REWARDS LIST
------------------------- */
function get_customer_reward_vouchers($customer_id, $limit = 20, $include_used = true) {

    $customer_id = (int) $customer_id;
    $limit = max(1, (int) $limit);

    if ($customer_id <= 0) {
        return [];
    }

    ensure_where2go_rewards_schema();

    $conn = db_connect();
    $sql = "SELECT ur.id, ur.user_id, ur.reward_id, ur.reward_box_id, ur.business_id, ur.location_id, ur.voucher_code,
                   ur.claimed_at, ur.used, ur.used_at, ur.expires_at,
                   r.value AS reward_value, r.label AS reward_label,
                   b.name AS business_name, bl.location_name, bl.address AS location_address
            FROM user_rewards ur
            INNER JOIN rewards r ON r.id = ur.reward_id
            INNER JOIN businesses b ON b.business_id = ur.business_id
            INNER JOIN business_locations bl ON bl.location_id = ur.location_id
            WHERE ur.user_id = ?";

    if (!$include_used) {
        $sql .= " AND ur.used = 0";
    }

    $sql .= " ORDER BY ur.claimed_at DESC, ur.id DESC
              LIMIT " . $limit;
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($result && ($row = $result->fetch_assoc())) {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['reward_value'] = (int) ($row['reward_value'] ?? 0);
        $row['used'] = (int) ($row['used'] ?? 0);
        $rows[] = $row;
    }

    return $rows;

}


/* -------------------------
   CUSTOMER REWARDS
------------------------- */
function get_customer_rewards_summary($customer_id) {

    $customer_id = (int) $customer_id;
    $settings = get_where2go_rewards_program_settings();
    $summary = [
        'customer_id' => $customer_id,
        'total_points' => 0,
        'total_xp' => 0,
        'current_level' => 0,
        'streak' => 0,
        'longest_streak' => 0,
        'total_scans' => 0,
        'total_checkins' => 0,
        'today_checkins' => 0,
        'daily_place_limit' => (int) ($settings['daily_place_limit'] ?? 5),
        'pending_reward_boxes' => 0,
        'available_rewards' => 0,
        'next_mystery_box_level' => (int) ($settings['mystery_box_every_levels'] ?? 5),
    ];

    if ($customer_id <= 0) {
        return array_merge($summary, get_customer_level_progress(0));
    }

    ensure_where2go_rewards_schema();

    $conn = db_connect();
    $stmt = $conn->prepare("SELECT customer_id, total_points, total_xp, current_level, streak, longest_streak,
                   total_scans, total_checkins, last_checkin_date, last_checkin_at
            FROM customer_rewards
            WHERE customer_id = ?
            LIMIT 1");

    if ($stmt) {
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        if ($row) {
            $summary['total_points'] = (int) ($row['total_points'] ?? 0);
            $summary['total_xp'] = max((int) ($row['total_xp'] ?? 0), (int) ($row['total_points'] ?? 0));
            $summary['current_level'] = calculate_customer_level_from_points((int) ($row['total_points'] ?? 0));
            $summary['streak'] = (int) ($row['streak'] ?? 0);
            $summary['longest_streak'] = (int) ($row['longest_streak'] ?? 0);
            $summary['total_scans'] = (int) ($row['total_scans'] ?? 0);
            $summary['total_checkins'] = (int) ($row['total_checkins'] ?? 0);
            $summary['last_checkin_date'] = $row['last_checkin_date'] ?? null;
            $summary['last_checkin_at'] = $row['last_checkin_at'] ?? null;
        }
    }

    $summary['today_checkins'] = get_customer_checkins_count_for_date($customer_id, null, $conn);
    $summary['pending_reward_boxes'] = get_customer_pending_reward_box_count($customer_id, $conn);
    $summary['available_rewards'] = count(get_customer_reward_vouchers($customer_id, 200, false));
    $unlockEvery = max(1, (int) ($settings['mystery_box_every_levels'] ?? 5));
    $summary['next_mystery_box_level'] = ((int) floor(((int) ($summary['current_level'] ?? 0)) / $unlockEvery) + 1) * $unlockEvery;

    return array_merge($summary, get_customer_level_progress((int) $summary['total_points']));

}


/* -------------------------
   CUSTOMER CHECK-INS
------------------------- */
function get_customer_recent_checkins($customer_id, $limit = 8) {

    $customer_id = (int) $customer_id;
    $limit = max(1, (int) $limit);

    if ($customer_id <= 0) {
        return [];
    }

    ensure_where2go_rewards_schema();

    $conn = db_connect();
    $sql = "SELECT cc.id, cc.business_id, cc.location_id, cc.promo_code_snapshot, cc.scan_type, cc.points_awarded, cc.xp_awarded,
                   cc.base_points_awarded, cc.streak_bonus_awarded, cc.checkin_date, cc.checked_in_at,
                   b.name AS business_name,
                   bl.location_name, bl.address AS location_address
            FROM customer_checkins cc
            INNER JOIN businesses b ON b.business_id = cc.business_id
            INNER JOIN business_locations bl ON bl.location_id = cc.location_id
            WHERE cc.customer_id = ?
            ORDER BY cc.checked_in_at DESC
            LIMIT " . $limit;
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $checkins = [];

    while ($result && ($row = $result->fetch_assoc())) {
        $checkins[] = $row;
    }

    return $checkins;

}


/* -------------------------
   BUILD STREAK STATE
------------------------- */
function build_customer_streak_state($previous_date, $current_streak, $today) {

    $previous_date = trim((string) $previous_date);
    $today = trim((string) $today);
    $current_streak = max(0, (int) $current_streak);
    $yesterday = date('Y-m-d', strtotime($today . ' -1 day'));

    if ($previous_date === $today) {
        return [
            'streak' => $current_streak,
            'bonus_points' => 0,
            'is_first_checkin_today' => false,
        ];
    }

    $nextStreak = $previous_date === $yesterday ? $current_streak + 1 : 1;
    $settings = get_where2go_rewards_program_settings();
    $bonusPoints = max(0, (int) ($settings['daily_streak_multiplier'] ?? 2)) * $nextStreak;

    return [
        'streak' => $nextStreak,
        'bonus_points' => $bonusPoints,
        'is_first_checkin_today' => true,
    ];

}


/* -------------------------
   FORMAT MINUTES
------------------------- */
function format_wait_time_minutes($seconds) {

    $seconds = max(0, (int) $seconds);
    $minutes = (int) ceil($seconds / 60);

    if ($minutes <= 1) {
        return '1 minute';
    }

    return $minutes . ' minutes';

}


/* -------------------------
   CLAIM CHECK-IN REWARD
------------------------- */
function claim_location_checkin_reward($customer_id, $qr_token) {

    $customer_id = (int) $customer_id;
    $qr_token = trim((string) $qr_token);
    $settings = get_where2go_rewards_program_settings();

    if ($customer_id <= 0 || $qr_token === '') {
        return ['ok' => false, 'message' => 'A customer account and valid QR code are required.'];
    }

    ensure_where2go_rewards_schema();

    $location = get_location_by_qr_token($qr_token);

    if (!$location || (int) ($location['location_id'] ?? 0) <= 0) {
        return ['ok' => false, 'message' => 'This QR code is not linked to a live Where2Go location.'];
    }

    if ((int) ($location['checkin_enabled'] ?? 1) !== 1) {
        return ['ok' => false, 'message' => 'This location is not accepting QR check-ins right now.', 'location' => $location];
    }

    if (trim((string) ($location['approval_status'] ?? 'pending')) !== 'approved') {
        return ['ok' => false, 'message' => 'This business is not public yet, so check-ins are disabled for now.', 'location' => $location];
    }

    $nowTimestamp = time();
    $nowSql = date('Y-m-d H:i:s', $nowTimestamp);
    $today = date('Y-m-d', $nowTimestamp);
    $businessId = (int) ($location['business_id'] ?? 0);
    $locationId = (int) ($location['location_id'] ?? 0);
    $cooldownHours = max(1, (int) ($settings['place_cooldown_hours'] ?? 24));
    $rapidRepeatMinutes = max(1, (int) ($settings['rapid_repeat_cooldown_minutes'] ?? 15));
    $dailyPlaceLimit = max(1, (int) ($settings['daily_place_limit'] ?? 5));
    $repeatLimitPerPlace = max(0, (int) ($settings['max_same_day_repeat_scans_per_place'] ?? 3));
    $conn = db_connect();

    try {
        $conn->begin_transaction();

        $currentRewards = lock_customer_rewards_row($conn, $customer_id);
        $scanSnapshot = get_customer_location_scan_snapshot($conn, $customer_id, $locationId, $today);
        $todayCheckins = get_customer_checkins_count_for_date($customer_id, $today, $conn);
        $alreadyVisitedToday = (int) ($scanSnapshot['today_scans'] ?? 0) > 0;
        $basePoints = 0;
        $scanType = 'first_visit';
        $cooldownEndsAt = date('Y-m-d H:i:s', $nowTimestamp + ($cooldownHours * 3600));
        $lastScanAt = trim((string) ($scanSnapshot['last_scan_at'] ?? ''));
        $lastScanDate = trim((string) ($scanSnapshot['last_scan_date'] ?? ''));
        $sameDayRepeatScans = (int) ($scanSnapshot['today_same_day_repeat_scans'] ?? 0);
        $lifetimeScans = (int) ($scanSnapshot['lifetime_scans'] ?? 0);
        $secondsSinceLastScan = null;

        if ($lastScanAt !== '') {
            $lastScanTimestamp = strtotime($lastScanAt);
            $secondsSinceLastScan = $lastScanTimestamp ? max(0, $nowTimestamp - $lastScanTimestamp) : null;
        }

        if ($lifetimeScans <= 0) {
            $basePoints = max(0, (int) ($settings['first_scan_points'] ?? 20));
            $scanType = 'first_visit';
        } elseif ($secondsSinceLastScan !== null && $secondsSinceLastScan >= ($cooldownHours * 3600)) {
            $basePoints = max(0, (int) ($settings['repeat_scan_points'] ?? 10));
            $scanType = 'repeat_visit';
        } elseif ($lastScanDate === $today) {
            if ($secondsSinceLastScan !== null && $secondsSinceLastScan < ($rapidRepeatMinutes * 60)) {
                $conn->rollback();

                return [
                    'ok' => false,
                    'code' => 'rapid_repeat_blocked',
                    'message' => 'Wait ' . format_wait_time_minutes(($rapidRepeatMinutes * 60) - $secondsSinceLastScan) . ' before scanning this place again.',
                    'location' => $location,
                    'summary' => get_customer_rewards_summary($customer_id),
                ];
            }

            if ($sameDayRepeatScans >= $repeatLimitPerPlace) {
                $conn->rollback();

                return [
                    'ok' => false,
                    'code' => 'same_day_repeat_limit',
                    'message' => 'You already used all of today\'s repeat-scan bonuses for this place. Come back after the 24-hour cooldown for more points.',
                    'location' => $location,
                    'summary' => get_customer_rewards_summary($customer_id),
                ];
            }

            $basePoints = max(0, (int) ($settings['same_day_repeat_points'] ?? 3));
            $scanType = 'same_day_repeat';
            $cooldownEndsAt = date('Y-m-d H:i:s', $nowTimestamp + ($rapidRepeatMinutes * 60));
        } else {
            $remainingSeconds = $secondsSinceLastScan !== null ? max(0, ($cooldownHours * 3600) - $secondsSinceLastScan) : ($cooldownHours * 3600);
            $conn->rollback();

            return [
                'ok' => false,
                'code' => 'place_cooldown_active',
                'message' => 'This location has a 24-hour cooldown. Try again in ' . format_wait_time_minutes($remainingSeconds) . '.',
                'location' => $location,
                'summary' => get_customer_rewards_summary($customer_id),
            ];
        }

        if (!$alreadyVisitedToday && $todayCheckins >= $dailyPlaceLimit) {
            $conn->rollback();

            return [
                'ok' => false,
                'code' => 'daily_limit_reached',
                'message' => 'You reached today\'s place limit. Explore more tomorrow to keep leveling up.',
                'location' => $location,
                'summary' => get_customer_rewards_summary($customer_id),
            ];
        }

        $streakState = build_customer_streak_state(
            (string) ($currentRewards['last_checkin_date'] ?? ''),
            (int) ($currentRewards['streak'] ?? 0),
            $today
        );
        $streakBonus = max(0, (int) ($streakState['bonus_points'] ?? 0));
        $pointsAwarded = $basePoints + $streakBonus;
        $promoCodeSnapshot = trim((string) ($location['promo_code'] ?? ''));
        $insertCheckinStmt = $conn->prepare("INSERT INTO customer_checkins
                (customer_id, business_id, location_id, promo_code_snapshot, scan_type, points_awarded, xp_awarded, base_points_awarded,
                 streak_bonus_awarded, checkin_date, checked_in_at, cooldown_ends_at, source)
                VALUES (?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?, ?, ?, ?, 'qr')");

        if (!$insertCheckinStmt) {
            throw new Exception('The customer check-in could not be prepared.');
        }

        $insertCheckinStmt->bind_param(
            "iiissiiiisss",
            $customer_id,
            $businessId,
            $locationId,
            $promoCodeSnapshot,
            $scanType,
            $pointsAwarded,
            $pointsAwarded,
            $basePoints,
            $streakBonus,
            $today,
            $nowSql,
            $cooldownEndsAt
        );

        if (!$insertCheckinStmt->execute()) {
            throw new Exception('The customer check-in could not be saved.');
        }

        $deltaResult = apply_customer_reward_points_delta($conn, $customer_id, $pointsAwarded, [
            'streak' => (int) ($streakState['streak'] ?? 0),
            'longest_streak' => max((int) ($currentRewards['longest_streak'] ?? 0), (int) ($streakState['streak'] ?? 0)),
            'increment_scans' => 1,
            'increment_checkins' => 1,
            'last_checkin_date' => $today,
            'last_checkin_at' => $nowSql,
            'business_id' => $businessId,
            'location_id' => $locationId,
            'source_context' => 'scan',
        ]);

        if (empty($deltaResult['ok'])) {
            throw new Exception((string) ($deltaResult['message'] ?? 'Reward progress could not be updated.'));
        }

        $conn->commit();
        $summary = get_customer_rewards_summary($customer_id);
        $pendingBoxes = get_customer_pending_reward_boxes($customer_id, 3);
        $message = 'Scan saved. You earned ' . $pointsAwarded . ' points';

        if ($streakBonus > 0) {
            $message .= ' including a ' . $streakBonus . '-point streak bonus';
        }

        $message .= '.';

        if (!empty($deltaResult['boxes_unlocked'])) {
            $message .= ' Mystery box unlocked. Open the wheel below.';
        }

        return [
            'ok' => true,
            'message' => $message,
            'location' => $location,
            'scan_type' => $scanType,
            'points_awarded' => $pointsAwarded,
            'xp_awarded' => $pointsAwarded,
            'base_points_awarded' => $basePoints,
            'streak_bonus_awarded' => $streakBonus,
            'summary' => $summary,
            'unlocked_boxes' => $deltaResult['boxes_unlocked'] ?? [],
            'pending_boxes' => $pendingBoxes,
            'next_pending_box' => $pendingBoxes[0] ?? null,
            'cooldown_ends_at' => $cooldownEndsAt,
        ];
    } catch (Throwable $error) {
        $conn->rollback();

        return [
            'ok' => false,
            'message' => 'The check-in could not be completed right now.',
            'error' => $error->getMessage(),
            'location' => $location,
        ];
    }

}


/* -------------------------
   CUSTOMER REVIEW FOR BUSINESS
------------------------- */
function get_customer_review_for_business($customer_id, $business_id) {

    $customer_id = (int) $customer_id;
    $business_id = (int) $business_id;

    if ($customer_id <= 0 || $business_id <= 0) {
        return null;
    }

    ensure_where2go_rewards_schema();

    $conn = db_connect();
    $stmt = $conn->prepare("SELECT review_id, business_id, location_id, customer_id, rating, comment, created_at, updated_at
            FROM business_reviews
            WHERE business_id = ?
              AND customer_id = ?
            LIMIT 1");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("ii", $business_id, $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result ? $result->fetch_assoc() : null;

}


/* -------------------------
   CUSTOMER CHECKED IN BUSINESS
------------------------- */
function has_customer_checked_in_business($customer_id, $business_id, $conn = null) {

    $customer_id = (int) $customer_id;
    $business_id = (int) $business_id;

    if ($customer_id <= 0 || $business_id <= 0) {
        return false;
    }

    ensure_where2go_rewards_schema();

    if (!$conn) {
        $conn = db_connect();
    }

    $stmt = $conn->prepare("SELECT id
            FROM customer_checkins
            WHERE customer_id = ?
              AND business_id = ?
            LIMIT 1");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("ii", $customer_id, $business_id);
    $stmt->execute();
    $result = $stmt->get_result();

    return (bool) ($result && $result->fetch_assoc());

}


/* -------------------------
   REVIEW ELIGIBILITY
------------------------- */
function can_customer_review_business($customer_id, $business_id) {

    $customer_id = (int) $customer_id;
    $business_id = (int) $business_id;

    if ($customer_id <= 0 || $business_id <= 0) {
        return false;
    }

    $settings = get_where2go_rewards_program_settings();

    if ((int) ($settings['review_requires_checkin'] ?? 1) !== 1) {
        return true;
    }

    return has_customer_checked_in_business($customer_id, $business_id);

}


/* -------------------------
   SUBMIT BUSINESS REVIEW
------------------------- */
function submit_business_review($customer_id, $business_id, $location_id, $rating, $comment) {

    $customer_id = (int) $customer_id;
    $business_id = (int) $business_id;
    $location_id = (int) $location_id;
    $rating = max(1, min(5, (int) $rating));
    $comment = trim((string) $comment);

    if ($customer_id <= 0 || $business_id <= 0) {
        return ['ok' => false, 'message' => 'A logged-in customer and valid business are required.'];
    }

    if ($comment === '') {
        return ['ok' => false, 'message' => 'Share a short review before submitting.'];
    }

    ensure_where2go_rewards_schema();

    $business = get_business_by_id($business_id);

    if (!$business || trim((string) ($business['approval_status'] ?? 'pending')) !== 'approved') {
        return ['ok' => false, 'message' => 'Reviews are only available on approved public businesses.'];
    }

    if ($location_id <= 0) {
        $location_id = (int) ($business['primary_location']['location_id'] ?? 0);
    }

    if ($location_id <= 0) {
        return ['ok' => false, 'message' => 'This business needs a saved location before reviews can be collected.'];
    }

    if (!can_customer_review_business($customer_id, $business_id)) {
        return ['ok' => false, 'message' => 'Review rewards unlock after your first QR check-in at this business.'];
    }

    $settings = get_where2go_rewards_program_settings();
    $reviewPoints = max(0, (int) ($settings['review_points'] ?? 5));
    $conn = db_connect();

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare("SELECT review_id
                FROM business_reviews
                WHERE business_id = ?
                  AND customer_id = ?
                LIMIT 1
                FOR UPDATE");

        if (!$stmt) {
            throw new Exception('The review record could not be prepared.');
        }

        $stmt->bind_param("ii", $business_id, $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existingReview = $result ? $result->fetch_assoc() : null;
        $awardedPoints = 0;
        $unlockedBoxes = [];

        if ($existingReview) {
            $reviewId = (int) ($existingReview['review_id'] ?? 0);
            $updateStmt = $conn->prepare("UPDATE business_reviews
                    SET location_id = ?, rating = ?, comment = ?, updated_at = NOW()
                    WHERE review_id = ?");

            if (!$updateStmt) {
                throw new Exception('The review update could not be prepared.');
            }

            $updateStmt->bind_param("iisi", $location_id, $rating, $comment, $reviewId);

            if (!$updateStmt->execute()) {
                throw new Exception('The review could not be updated.');
            }
        } else {
            $insertStmt = $conn->prepare("INSERT INTO business_reviews
                    (business_id, location_id, customer_id, rating, comment, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())");

            if (!$insertStmt) {
                throw new Exception('The review insert could not be prepared.');
            }

            $insertStmt->bind_param("iiiis", $business_id, $location_id, $customer_id, $rating, $comment);

            if (!$insertStmt->execute()) {
                throw new Exception('The review could not be saved.');
            }

            if ($reviewPoints > 0) {
                $deltaResult = apply_customer_reward_points_delta($conn, $customer_id, $reviewPoints, [
                    'business_id' => $business_id,
                    'location_id' => $location_id,
                    'source_context' => 'review',
                ]);

                if (empty($deltaResult['ok'])) {
                    throw new Exception((string) ($deltaResult['message'] ?? 'Review reward points could not be added.'));
                }

                $awardedPoints = $reviewPoints;
                $unlockedBoxes = $deltaResult['boxes_unlocked'] ?? [];
            }
        }

        $conn->commit();
        $summary = get_customer_rewards_summary($customer_id);

        return [
            'ok' => true,
            'message' => $existingReview
                ? 'Your review was updated.'
                : 'Review posted. ' . ($awardedPoints > 0 ? 'You earned ' . $awardedPoints . ' points.' : 'Thanks for sharing your feedback.'),
            'points_awarded' => $awardedPoints,
            'summary' => $summary,
            'unlocked_boxes' => $unlockedBoxes,
            'pending_boxes' => get_customer_pending_reward_boxes($customer_id, 3),
        ];
    } catch (Throwable $error) {
        $conn->rollback();

        return [
            'ok' => false,
            'message' => 'The review could not be saved right now.',
            'error' => $error->getMessage(),
        ];
    }

}


/* -------------------------
   LIFETIME REWARD COUNT
------------------------- */
function get_customer_lifetime_reward_count($conn, $customer_id, $reward_value) {

    $customer_id = (int) $customer_id;
    $reward_value = (int) $reward_value;

    if (!$conn || $customer_id <= 0 || $reward_value <= 0) {
        return 0;
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS total
            FROM user_rewards ur
            INNER JOIN rewards r ON r.id = ur.reward_id
            WHERE ur.user_id = ?
              AND r.value = ?");

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param("ii", $customer_id, $reward_value);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    return (int) ($row['total'] ?? 0);

}


/* -------------------------
   REWARD WHEEL SEGMENTS
------------------------- */
function build_reward_wheel_segments_for_user($conn, $customer_id) {

    $customer_id = (int) $customer_id;
    $settings = get_where2go_rewards_program_settings();
    $maxLifetimeFree = max(0, (int) ($settings['max_lifetime_free_vouchers'] ?? 2));
    $definitions = get_active_reward_definitions($conn);
    $segments = [];

    foreach ($definitions as $definition) {
        $rewardValue = (int) ($definition['value'] ?? 0);

        if ($rewardValue === 100 && $maxLifetimeFree > 0) {
            $lifetimeCount = get_customer_lifetime_reward_count($conn, $customer_id, 100);

            if ($lifetimeCount >= $maxLifetimeFree) {
                continue;
            }
        }

        $segments[] = [
            'id' => (int) ($definition['id'] ?? 0),
            'value' => $rewardValue,
            'label' => trim((string) ($definition['label'] ?? ($rewardValue . '% OFF'))),
            'probability' => max(0, (float) ($definition['probability'] ?? 0)),
            'sort_order' => (int) ($definition['sort_order'] ?? $rewardValue),
        ];
    }

    if (!$segments) {
        foreach ($settings['wheel_segments'] ?? [] as $rewardValue => $segment) {
            $segments[] = [
                'id' => 0,
                'value' => (int) $rewardValue,
                'label' => trim((string) ($segment['label'] ?? ($rewardValue . '% OFF'))),
                'probability' => max(0, (float) ($segment['probability'] ?? 0)),
                'sort_order' => (int) ($segment['sort_order'] ?? $rewardValue),
            ];
        }
    }

    usort($segments, function ($left, $right) {
        $leftOrder = (int) ($left['sort_order'] ?? 0);
        $rightOrder = (int) ($right['sort_order'] ?? 0);

        if ($leftOrder === $rightOrder) {
            return (int) ($left['value'] ?? 0) <=> (int) ($right['value'] ?? 0);
        }

        return $leftOrder <=> $rightOrder;
    });

    return $segments;

}


/* -------------------------
   PICK WEIGHTED REWARD
------------------------- */
function pick_weighted_reward_segment($segments) {

    $segments = is_array($segments) ? $segments : [];
    $weights = [];
    $totalWeight = 0;

    foreach ($segments as $index => $segment) {
        $weight = max(0, (int) round(((float) ($segment['probability'] ?? 0)) * 100000));

        if ($weight <= 0) {
            continue;
        }

        $weights[$index] = $weight;
        $totalWeight += $weight;
    }

    if ($totalWeight <= 0) {
        return ['segment' => $segments[0] ?? null, 'selected_index' => 0];
    }

    $draw = random_int(1, $totalWeight);
    $runningTotal = 0;

    foreach ($weights as $index => $weight) {
        $runningTotal += $weight;

        if ($draw <= $runningTotal) {
            return [
                'segment' => $segments[$index] ?? null,
                'selected_index' => (int) $index,
            ];
        }
    }

    return [
        'segment' => end($segments) ?: null,
        'selected_index' => max(0, count($segments) - 1),
    ];

}


/* -------------------------
   GENERATE VOUCHER CODE
------------------------- */
function generate_unique_reward_voucher_code($conn, $reward_value) {

    $reward_value = max(0, (int) $reward_value);

    if (!$conn) {
        return '';
    }

    for ($attempt = 0; $attempt < 6; $attempt++) {
        $code = 'W2G-' . $reward_value . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $stmt = $conn->prepare("SELECT id
                FROM user_rewards
                WHERE voucher_code = ?
                LIMIT 1");

        if (!$stmt) {
            return $code;
        }

        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result || !$result->fetch_assoc()) {
            return $code;
        }
    }

    return 'W2G-' . $reward_value . '-' . strtoupper(substr(md5(uniqid('w2g-reward', true)), 0, 8));

}


/* -------------------------
   SPIN REWARD BOX
------------------------- */
function spin_reward_box($customer_id, $box_id = 0) {

    $customer_id = (int) $customer_id;
    $box_id = (int) $box_id;

    if ($customer_id <= 0) {
        return ['ok' => false, 'message' => 'A customer account is required to spin the reward wheel.'];
    }

    ensure_where2go_rewards_schema();

    $conn = db_connect();
    $settings = get_where2go_rewards_program_settings();

    try {
        $conn->begin_transaction();

        if ($box_id > 0) {
            $sql = "SELECT id, customer_id, business_id, location_id, trigger_level, unlock_points, source_context, status, created_at
                    FROM reward_boxes
                    WHERE id = ?
                      AND customer_id = ?
                      AND status = 'pending'
                    LIMIT 1
                    FOR UPDATE";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                throw new Exception('The reward box could not be prepared.');
            }

            $stmt->bind_param("ii", $box_id, $customer_id);
        } else {
            $sql = "SELECT id, customer_id, business_id, location_id, trigger_level, unlock_points, source_context, status, created_at
                    FROM reward_boxes
                    WHERE customer_id = ?
                      AND status = 'pending'
                    ORDER BY trigger_level ASC, created_at ASC
                    LIMIT 1
                    FOR UPDATE";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                throw new Exception('The reward box could not be prepared.');
            }

            $stmt->bind_param("i", $customer_id);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $box = $result ? $result->fetch_assoc() : null;

        if (!$box) {
            $conn->rollback();

            return [
                'ok' => false,
                'code' => 'no_pending_box',
                'message' => 'There is no pending mystery box ready to spin right now.',
            ];
        }

        $segments = build_reward_wheel_segments_for_user($conn, $customer_id);

        if (!$segments) {
            throw new Exception('The reward wheel has no active rewards configured.');
        }

        $selection = pick_weighted_reward_segment($segments);
        $selectedSegment = $selection['segment'] ?? null;
        $selectedIndex = (int) ($selection['selected_index'] ?? 0);

        if (!$selectedSegment || (int) ($selectedSegment['value'] ?? 0) <= 0) {
            throw new Exception('The reward wheel could not choose a reward.');
        }

        $rewardId = (int) ($selectedSegment['id'] ?? 0);
        $rewardValue = (int) ($selectedSegment['value'] ?? 0);
        $minExpiryDays = max(1, (int) ($settings['voucher_expiry_min_days'] ?? 7));
        $maxExpiryDays = max($minExpiryDays, (int) ($settings['voucher_expiry_max_days'] ?? 14));
        $expiryDays = random_int($minExpiryDays, $maxExpiryDays);
        $claimedAt = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $expiryDays . ' days'));
        $voucherCode = generate_unique_reward_voucher_code($conn, $rewardValue);
        $insertVoucherStmt = $conn->prepare("INSERT INTO user_rewards
                (user_id, reward_id, reward_box_id, business_id, location_id, voucher_code, claimed_at, used, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)");

        if (!$insertVoucherStmt) {
            throw new Exception('The reward voucher could not be prepared.');
        }

        $rewardBoxId = (int) ($box['id'] ?? 0);
        $businessId = (int) ($box['business_id'] ?? 0);
        $locationId = (int) ($box['location_id'] ?? 0);
        $insertVoucherStmt->bind_param(
            "iiiiisss",
            $customer_id,
            $rewardId,
            $rewardBoxId,
            $businessId,
            $locationId,
            $voucherCode,
            $claimedAt,
            $expiresAt
        );

        if (!$insertVoucherStmt->execute()) {
            throw new Exception('The reward voucher could not be saved.');
        }

        $voucherId = (int) $conn->insert_id;
        $updateBoxStmt = $conn->prepare("UPDATE reward_boxes
                SET reward_id = ?, status = 'spun', spun_at = ?, expires_at = ?
                WHERE id = ?");

        if (!$updateBoxStmt) {
            throw new Exception('The reward box status could not be updated.');
        }

        $updateBoxStmt->bind_param("issi", $rewardId, $claimedAt, $expiresAt, $rewardBoxId);

        if (!$updateBoxStmt->execute()) {
            throw new Exception('The reward box status could not be saved.');
        }

        $conn->commit();
        $summary = get_customer_rewards_summary($customer_id);
        $voucher = null;

        foreach (get_customer_reward_vouchers($customer_id, 10, true) as $rewardRow) {
            if ((int) ($rewardRow['id'] ?? 0) === $voucherId) {
                $voucher = $rewardRow;
                break;
            }
        }

        return [
            'ok' => true,
            'message' => 'Mystery box opened. You won ' . trim((string) ($selectedSegment['label'] ?? ($rewardValue . '% OFF'))) . '.',
            'box_id' => $rewardBoxId,
            'segments' => array_values(array_map(function ($segment) {
                return [
                    'id' => (int) ($segment['id'] ?? 0),
                    'value' => (int) ($segment['value'] ?? 0),
                    'label' => trim((string) ($segment['label'] ?? 'Reward')),
                    'probability' => (float) ($segment['probability'] ?? 0),
                ];
            }, $segments)),
            'selected_index' => $selectedIndex,
            'reward' => [
                'reward_id' => $rewardId,
                'value' => $rewardValue,
                'label' => trim((string) ($selectedSegment['label'] ?? ($rewardValue . '% OFF'))),
                'voucher_code' => $voucherCode,
                'expires_at' => $expiresAt,
                'business_id' => $businessId,
                'location_id' => $locationId,
            ],
            'voucher' => $voucher,
            'summary' => $summary,
        ];
    } catch (Throwable $error) {
        $conn->rollback();

        return [
            'ok' => false,
            'message' => 'The reward wheel could not be completed right now.',
            'error' => $error->getMessage(),
        ];
    }

}


/* -------------------------
   REWARD CONFIG SAVE
------------------------- */
function save_where2go_reward_program_settings($data) {

    $data = is_array($data) ? $data : [];
    ensure_where2go_rewards_schema();
    $defaults = get_where2go_default_reward_settings();
    $editableKeys = [
        'first_scan_points',
        'repeat_scan_points',
        'same_day_repeat_points',
        'review_points',
        'daily_streak_multiplier',
        'daily_place_limit',
        'rapid_repeat_cooldown_minutes',
        'place_cooldown_hours',
        'max_same_day_repeat_scans_per_place',
        'review_requires_checkin',
        'voucher_expiry_min_days',
        'voucher_expiry_max_days',
        'max_lifetime_free_vouchers',
    ];
    $configValues = [];

    foreach ($editableKeys as $key) {
        $incoming = $data[$key] ?? $defaults[$key] ?? 0;

        if (!is_numeric($incoming)) {
            return ['ok' => false, 'message' => 'Reward setting "' . $key . '" must be numeric.'];
        }

        $configValues[$key] = max(0, (int) $incoming);
    }

    if ($configValues['voucher_expiry_max_days'] < $configValues['voucher_expiry_min_days']) {
        return ['ok' => false, 'message' => 'The voucher expiry max must be greater than or equal to the min.'];
    }

    $rewardValues = array_keys(get_where2go_default_reward_segments());
    $probabilities = [];
    $probabilityTotal = 0;

    foreach ($rewardValues as $rewardValue) {
        $fieldName = 'reward_probability_' . $rewardValue;
        $incoming = $data[$fieldName] ?? null;

        if ($incoming === null || $incoming === '' || !is_numeric($incoming)) {
            return ['ok' => false, 'message' => 'Add a valid probability for the ' . $rewardValue . '% reward.'];
        }

        $percentage = max(0, (float) $incoming);
        $probabilities[$rewardValue] = $percentage / 100;
        $probabilityTotal += $percentage;
    }

    if (abs($probabilityTotal - 100) > 0.001) {
        return ['ok' => false, 'message' => 'Reward probabilities must add up to exactly 100%.'];
    }

    $conn = db_connect();

    try {
        $conn->begin_transaction();

        foreach ($configValues as $key => $value) {
            if (!upsert_where2go_reward_config($conn, $key, $value)) {
                throw new Exception('The "' . $key . '" setting could not be saved.');
            }
        }

        foreach (get_active_reward_definitions($conn, true) as $definition) {
            $rewardValue = (int) ($definition['value'] ?? 0);

            if (!isset($probabilities[$rewardValue])) {
                continue;
            }

            $stmt = $conn->prepare("UPDATE rewards
                    SET probability = ?, is_active = 1, updated_at = NOW()
                    WHERE id = ?");

            if (!$stmt) {
                throw new Exception('The reward probability could not be prepared.');
            }

            $probability = (float) $probabilities[$rewardValue];
            $rewardId = (int) ($definition['id'] ?? 0);
            $stmt->bind_param("di", $probability, $rewardId);

            if (!$stmt->execute()) {
                throw new Exception('The reward probability could not be saved.');
            }
        }

        $conn->commit();
        clear_where2go_rewards_settings_cache();

        return ['ok' => true, 'message' => 'Reward settings updated successfully.'];
    } catch (Throwable $error) {
        $conn->rollback();

        return ['ok' => false, 'message' => 'The reward settings could not be saved right now.', 'error' => $error->getMessage()];
    }

}

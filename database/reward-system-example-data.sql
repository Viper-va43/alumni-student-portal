-- Example reward-system seed data for local testing.
-- Replace the ids below with real records from your database before running it.

SET @customer_id = 1;
SET @business_id = 1;
SET @location_id = 1;
SET @reward_id_10 = (
    SELECT id
    FROM rewards
    WHERE value = 10
    ORDER BY id ASC
    LIMIT 1
);

INSERT INTO customer_rewards (
    customer_id,
    total_points,
    total_xp,
    current_level,
    streak,
    longest_streak,
    total_scans,
    total_checkins,
    last_checkin_date,
    last_checkin_at
) VALUES (
    @customer_id,
    520,
    520,
    3,
    4,
    6,
    14,
    14,
    CURDATE(),
    NOW()
) ON DUPLICATE KEY UPDATE
    total_points = VALUES(total_points),
    total_xp = VALUES(total_xp),
    current_level = VALUES(current_level),
    streak = VALUES(streak),
    longest_streak = VALUES(longest_streak),
    total_scans = VALUES(total_scans),
    total_checkins = VALUES(total_checkins),
    last_checkin_date = VALUES(last_checkin_date),
    last_checkin_at = VALUES(last_checkin_at);

INSERT INTO customer_checkins (
    customer_id,
    business_id,
    location_id,
    promo_code_snapshot,
    scan_type,
    points_awarded,
    xp_awarded,
    base_points_awarded,
    streak_bonus_awarded,
    checkin_date,
    checked_in_at,
    cooldown_ends_at,
    source
) VALUES
    (@customer_id, @business_id, @location_id, 'WELCOME10', 'first_visit', 22, 22, 20, 2, CURDATE() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 1 DAY, 'qr'),
    (@customer_id, @business_id, @location_id, 'WELCOME10', 'repeat_visit', 18, 18, 10, 8, CURDATE() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY, NOW(), 'qr'),
    (@customer_id, @business_id, @location_id, 'WELCOME10', 'same_day_repeat', 3, 3, 3, 0, CURDATE(), NOW(), NOW() + INTERVAL 15 MINUTE, 'qr');

INSERT INTO reward_boxes (
    customer_id,
    business_id,
    location_id,
    trigger_level,
    unlock_points,
    source_context,
    status,
    created_at
) VALUES (
    @customer_id,
    @business_id,
    @location_id,
    5,
    995,
    'scan',
    'pending',
    NOW()
);

INSERT INTO user_rewards (
    user_id,
    reward_id,
    reward_box_id,
    business_id,
    location_id,
    voucher_code,
    claimed_at,
    used,
    expires_at
) VALUES (
    @customer_id,
    @reward_id_10,
    NULL,
    @business_id,
    @location_id,
    'W2G-10-DEMO2026',
    NOW(),
    0,
    NOW() + INTERVAL 10 DAY
);


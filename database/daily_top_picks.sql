CREATE TABLE IF NOT EXISTS daily_top_picks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pick_date DATE NOT NULL,
    business_id INT NOT NULL,
    position TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_daily_top_pick (pick_date, business_id),
    KEY idx_daily_top_picks_date_position (pick_date, position),
    KEY idx_daily_top_picks_business (business_id),
    CONSTRAINT fk_daily_top_picks_business
        FOREIGN KEY (business_id) REFERENCES businesses(business_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE daily_check_in_user_data
  ADD COLUMN claimed_days JSON NOT NULL DEFAULT (JSON_ARRAY()) COMMENT '已领奖天数，如 [1,2]' AFTER checked_days;

CREATE TABLE IF NOT EXISTS daily_check_in_gift_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_role_primary_id BIGINT UNSIGNED NOT NULL,
    activity_id BIGINT UNSIGNED NOT NULL,
    check_day INT NOT NULL,
    gift_id INT NOT NULL,
    gift_name VARCHAR(64) NOT NULL,
    created_at INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_role_activity_day (user_role_primary_id, activity_id, check_day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
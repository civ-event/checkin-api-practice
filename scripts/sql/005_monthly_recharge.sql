CREATE TABLE IF NOT EXISTS monthly_recharge_user_data (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_role_primary_id BIGINT UNSIGNED NOT NULL,
    activity_id BIGINT UNSIGNED NOT NULL,
    `year_month` INT NOT NULL,
    total_amount INT NOT NULL DEFAULT 0,
    claimed_tiers JSON NOT NULL DEFAULT (JSON_ARRAY()),
    created_at INT NOT NULL DEFAULT 0,
    updated_at INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_role_activity_month (user_role_primary_id, activity_id, `year_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
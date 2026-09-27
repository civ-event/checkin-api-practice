CREATE TABLE IF NOT EXISTS monthly_daily_check_in_user_data_unique_records (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_role_primary_id BIGINT UNSIGNED NOT NULL,
    activity_id BIGINT UNSIGNED NOT NULL,
    year_month_num INT NOT NULL,
    check_day INT NOT NULL,
    created_at INT NOT NULL DEFAULT 0,
    updated_at INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_monthly_daily_check_in_claim (activity_id, user_role_primary_id, year_month_num, check_day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS monthly_cumulative_recharge_user_data_unique_records (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_role_primary_id BIGINT UNSIGNED NOT NULL,
    activity_id BIGINT UNSIGNED NOT NULL,
    year_month_num INT NOT NULL,
    reward_id VARCHAR(64) NOT NULL,
    created_at INT NOT NULL DEFAULT 0,
    updated_at INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_monthly_cumulative_recharge_claim (activity_id, user_role_primary_id, year_month_num, reward_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

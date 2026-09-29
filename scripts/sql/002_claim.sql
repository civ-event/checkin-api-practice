-- 签到领奖记录。进度表增加已领天数，并用礼物日志保证同一天只能领一次。
-- 现在的打卡把记进度和发奖放在同一次请求里，发奖是否重复以 007 的唯一领取表为准。

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
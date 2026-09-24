-- 签到进度表（学习用，对齐 daily_check_in 语义）
-- IF NOT EXISTS：重复执行迁移不会因表已存在而失败
CREATE TABLE IF NOT EXISTS daily_check_in_user_data (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,                         -- 自增主键
    user_role_primary_id BIGINT UNSIGNED NOT NULL COMMENT '角色主键',   -- 哪个角色
    activity_id BIGINT UNSIGNED NOT NULL COMMENT '活动实例 ID',         -- 哪个活动
    checked_days JSON NOT NULL COMMENT '已签天数，如 [1,2,3]',          -- JSON 数组
    total_checked INT NOT NULL DEFAULT 0 COMMENT '已签总天数（冗余）',  -- = checked_days 长度
    last_check_time INT NOT NULL DEFAULT 0 COMMENT '最后签到 Unix 时间戳', -- 判断「今天」
    created_at INT NOT NULL DEFAULT 0,                                  -- 创建时间
    updated_at INT NOT NULL DEFAULT 0,                                  -- 更新时间
    PRIMARY KEY (id),                                                   -- 主键
    UNIQUE KEY uniq_user_role_activity (user_role_primary_id, activity_id), -- 同一角色同一活动一条
    KEY idx_user_role (user_role_primary_id),                           -- 按角色查
    KEY idx_activity (activity_id)                                      -- 按活动查
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;     -- InnoDB + utf8mb4

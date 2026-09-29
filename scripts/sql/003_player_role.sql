-- 登录落库的玩家和角色。activity_user 按 player_id 一人一行。
-- activity_user_role.id 是后面 JWT、签到和累充使用的角色主键，不是游戏角色号 r100、r200。

CREATE TABLE IF NOT EXISTS activity_user (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    player_id VARCHAR(64) NOT NULL,
    created_at INT NOT NULL DEFAULT 0,
    updated_at INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_player_id (player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_user_role (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    activity_user_id BIGINT UNSIGNED NOT NULL,
    server_id VARCHAR(64) NOT NULL,
    role_id VARCHAR(64) NOT NULL,
    role_name VARCHAR(64) NOT NULL,
    created_at INT NOT NULL DEFAULT 0,
    updated_at INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_user_server_role (activity_user_id, server_id, role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
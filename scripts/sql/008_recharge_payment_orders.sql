-- 累充订单。充值接口每笔插入一行；config/mock_payments.php 的初始订单也导入到这里，且只导入一次。
-- 查询和领奖按游戏角色号、支付时间落在当月的金额加总，写回 monthly_recharge_user_data.total_amount。
-- role_id 是游戏角色号 r100、r200，不是 activity_user_role 自增主键。

CREATE TABLE IF NOT EXISTS recharge_payment_orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id VARCHAR(64) NOT NULL,
    order_id VARCHAR(64) NOT NULL,
    amount INT NOT NULL,
    send_time INT NOT NULL,
    created_at INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_role_order_amount_time (role_id, order_id, amount, send_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 同一角色的同一个订单号只保留最先写入的一行。线上一笔支付订单只同步一次，不能按重复行把金额加上去。
-- 角色甲原先和角色乙共用了 pay-2001、pay-2002，这里清掉角色甲这两笔，改由 mock 里互不相同的 pay-1001、pay-1002、pay-1003 重新导入。

DELETE t FROM recharge_payment_orders t
INNER JOIN recharge_payment_orders keeper
    ON keeper.role_id = t.role_id
    AND keeper.order_id = t.order_id
    AND keeper.id < t.id;

DELETE FROM recharge_payment_orders
WHERE role_id = 'r100' AND order_id IN ('pay-2001', 'pay-2002');

ALTER TABLE recharge_payment_orders
    DROP INDEX uniq_role_order_amount_time,
    ADD UNIQUE KEY uniq_role_order (role_id, order_id);

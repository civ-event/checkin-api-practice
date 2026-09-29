# TC-PAY-02: 客户端入账后当月累计增加，同一订单只算一次

| 属性 | 值 |
|------|-----|
| 编号 | TC-PAY-02 |
| 优先级 | P0 |
| 模块 | 累充 |
| 平台 | Server |
| 关联需求 | `POST /api-front/activity/recharge/record` 写入订单再按当月合计 |
| 关联 CR | CR-3、CR-7；7.3 草案；第四章「先入账再查状态」 |

## 前置条件

- 角色丁登录，`roleId=r400`，`serverId=s4`。丁没有种子订单。
- 先 `GET /api-front/activity/monthly-cumulative-recharge/status?activity_id=2`，把 `totalRechargeGoods` 记为 T。
- 入账路径不是前端那条 `monthly-cumulative-recharge`。必须打 `/api-front/activity/recharge/record`（或短路径 `/recharge/record`）。

## 操作步骤

1. `POST /api-front/activity/recharge/record`。请求头 `activity-user-token`，`Content-Type: application/json`，正文 `{"activity_id":2,"amount":100}`。
2. 从响应或数据库读出刚插入的 `order_id`（形如 `client-{unix}-{6位十六进制}`）。
3. 再 `GET .../monthly-cumulative-recharge/status?activity_id=2`。
4. `SELECT COUNT(*) FROM recharge_payment_orders WHERE role_id='r400' AND order_id='{上一步的订单号}'`。

## 预期结果

- 第 1 步 HTTP 200。`totalRechargeGoods` 为 T+100。`yearMonth` 为 `s4` 时区 `Etc/GMT+5` 的当月。
- 响应就是累充状态对象，含 `tiers`。没有单独的「入账成功」外壳。
- 第 3 步 `totalRechargeGoods` 仍为 T+100，不是 T+200。
- 第 4 步计数为 1。
- `routes.yml` 的 `record` 注释写明 amount 写入订单表，再按当月订单合计。T+100 与这句注释一致。

## 不应出现

- 第二次 status 变成 T+200。
- 打 `POST /api-front/activity/monthly-cumulative-recharge/record` 得到入账。这条路径不存在。
- 金额加到角色丙或角色甲头上。

## 备注

- `Content-Type: application/x-www-form-urlencoded` 时，Body 必须是表单字段 `activity_id=2`、`amount=100`。这个头只声明正文编码，不代替活动 id，也不代替 token。
- 已有自动化：`tests/Service/RechargeRecordTest.php` 对角色丙断言增加 100。丙的绝对金额会随每次运行变大，本条用丁，并断言相对 T 的增量。
- 练习环境允许客户端写任意正整数。这是 CR-3 的现状，不是支付验签通过。

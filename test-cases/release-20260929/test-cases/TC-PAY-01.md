# TC-PAY-01: 累充状态按角色服务器的当月合计，种子不计入 10 月

| 属性 | 值 |
|------|-----|
| 编号 | TC-PAY-01 |
| 优先级 | P0 |
| 模块 | 累充 |
| 平台 | Server |
| 关联需求 | `GET /api-front/activity/monthly-cumulative-recharge/status`，字段 `totalRechargeGoods`、`roundEndAt`、`tiers` |
| 关联 CR | 7.2 Happy「角色乙登录后查累充」；7.4 不要断言乙一定是 600 |

## 前置条件

- `POST /api-auth/activity/join`，`roleId=r200`，`serverId=s2`，取得 `activityUserToken`。
- 容器业务时间仍落在 2026-10（见 TC-API-01）。角色乙的服务器时区是 `Asia/Shanghai`，与 `config/game.php`、`config/mock_payments.php` 的注释一致。
- 种子两笔：`pay-2001` 金额 100，`pay-2002` 金额 500。两笔 `send_time` 在上海时区都是 2026-09（分别是 09-01 23:00 和 09-11 01:00）。

## 操作步骤

1. `GET /api-front/activity/monthly-cumulative-recharge/status?activity_id=2`，请求头带上面的 token。
2. 查表 `recharge_payment_orders`，条件 `role_id='r200'`。
3. 再请求一次同一个 status。

## 预期结果

- 第 1 步 HTTP 200，无 `code` 外壳。
- `yearMonth` 为角色服务器时区的当月。10 月内为 `202610`。签到用的游戏时区是 `Etc/GMT+5`，两边在 10 月内都是 `202610`，但不要用签到的时区去解释订单落月。
- `tiers` 六档门槛为 100、300、500、800、1000、2000。`reached` 为 `totalRechargeGoods >= threshold`。已领为 `claimed`，达到未领为 `claimable`，未达到为 `locked`。
- `roundEndAt` 是该服务器时区下个月 1 日 0 点的 Unix 秒。
- 第 2 步能看到 `pay-2001`、`pay-2002` 各一行。第一次查询会 `INSERT IGNORE`，重复查询不增加行，金额也不变成 1200。
- 这两笔的月份是 2026-09。当 `yearMonth` 为 `202610` 时，它们不进入 `totalRechargeGoods`。若该角色 10 月没有其它订单，合计为 0，六档都是 `locked`。
- 第 3 步的 `totalRechargeGoods` 与第 1 步相同。

## 不应出现

- 断言 `totalRechargeGoods` 为 600 或 900。那是 9 月种子合计，不是 10 月合计。
- 把 9 月的 600 当成 10 月合计。注释里的 600 只描述种子金额。
- 第二次 status 把同一 `order_id` 再加一遍。

## 备注

- 角色甲 `r100` / `s1` 的三笔种子在 `Etc/GMT+5` 同样全是 2026-09，合计 900，10 月同样不计入。甲的服务器才是 `Etc/GMT+5`。
- 角色丙、丁没有种子。丙可能已被入账用例加过 10 月订单，不要用丙验证「种子月」。
- 7.2 写「以响应为准」。本条的判据是：种子行存在，且其 `send_time` 所在月与 `yearMonth` 不同时不进合计。

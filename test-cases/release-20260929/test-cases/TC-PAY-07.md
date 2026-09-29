# TC-PAY-07: 累充接口传入签到活动 id

| 属性 | 值 |
|------|-----|
| 编号 | TC-PAY-07 |
| 优先级 | P1 |
| 模块 | 累充 |
| 平台 | Server |
| 关联需求 | 累充活动 id 为 2，类型 `monthly_cumulative_recharge` |
| 关联 CR | 第四章「活动 id 传错」。签到侧的对应用例是 TC-CHECKIN-05 |

## 前置条件

- 已登录角色丁。先用 `activity_id=2` 查到一次 200，确认 token 有效。记下 `totalRechargeGoods`。

## 操作步骤

1. `GET /api-front/activity/monthly-cumulative-recharge/status?activity_id=1`。
2. `POST /api-front/activity/recharge/record`，JSON `{"activity_id":1,"amount":100}`。
3. `POST /api-front/activity/monthly-cumulative-recharge/claim`，JSON `{"activity_id":1,"threshold":100}`。
4. 再用 `activity_id=2` 查 status。

## 预期结果

- 前三步都是 HTTP 404，`code` 为 `ACTIVITY_NOT_FOUND`，`exception.message` 为 `Activity is not configured`。
- 第 2 步不插入新的 `recharge_payment_orders`。活动校验在插入订单之前。
- 第 4 步 `totalRechargeGoods` 与本条开始前相同。签到 `checkedDays` 也不变。

## 不应出现

- 把 100 加进活动 1 的签到进度。
- 消息是 `Activity is not running`。

## 备注

- 缺 `activity_id` 是另一条：HTTP 400，`INVALID_PARAMETER`，消息 `activity_id is required and must be int`。不要和 404 混断言。

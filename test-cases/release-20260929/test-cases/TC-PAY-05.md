# TC-PAY-05: 任意正整数金额都会入账

| 属性 | 值 |
|------|-----|
| 编号 | TC-PAY-05 |
| 优先级 | P1 |
| 模块 | 累充 |
| 平台 | Server |
| 关联需求 | 练习路径接受客户端金额，没有单笔上限，也没有支付验签 |
| 关联 CR | CR-3；7.1 超大 amount |

## 前置条件

- 不要用正在验证「刚好 100 档」的角色。建议角色甲 `r100` / `s1`。先记下当月 `totalRechargeGoods` 为 T。
- 甲的 9 月种子不进入 10 月。T 只含该角色服务器当月的订单。
- 本条会把该角色当月累计至少加上 2001，之后 2000 档也可领。只在练习库执行。

## 操作步骤

1. `POST /api-front/activity/recharge/record`，JSON `{"activity_id":2,"amount":1}`。
2. 再 record，`amount` 为 `2000`。
3. 再 record，`amount` 为 `0`。
4. 再 record，`amount` 为 `-1`。

## 预期结果

- 第 1 步 HTTP 200，`totalRechargeGoods` 为 T+1。
- 第 2 步 HTTP 200，`totalRechargeGoods` 为 T+2001。2000 档 `reached` 为 true。没有单笔上限错误。
- 第 3 步和第 4 步 HTTP 400，`code` 为 `INVALID_PARAMETER`，`exception.message` 为 `amount must be a positive int`。合计仍为 T+2001。

## 不应出现

- 合计停在种子原值。
- `amount=2000` 被拒绝或被截成某一档门槛。
- 把这次增加记到别的角色。

## 备注

- 接到真实发奖后，这条接口不能对公网开放。本条证明的是当前练习行为，不是支付回调。
- 同一 `order_id` 不双计已由 TC-PAY-02 覆盖。本条两笔的 `order_id` 不同，所以两笔都算。

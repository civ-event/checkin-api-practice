# TC-PAY-03: 未达档拒绝，达到后领取，立刻再领被拒绝

| 属性 | 值 |
|------|-----|
| 编号 | TC-PAY-03 |
| 优先级 | P0 |
| 模块 | 累充 |
| 平台 | Server |
| 关联需求 | `POST /api-front/activity/monthly-cumulative-recharge/claim`，档位 100/300/500/800/1000/2000 |
| 关联 CR | 7.2 Negative「未达档领取」；7.2 时序「领奖成功后立刻再领」 |

## 前置条件

- 使用角色丁，且已经按 TC-PAY-02 入账 100，或 status 里 `totalRechargeGoods` 满足：`100 <= 合计 < 300`，并且 100 档仍是 `claimable`。
- 若 100 档已经是 `claimed`，选当前最低的 `claimable` 档，下面用 Q 表示。礼物 id 为：100→2001，300→2002，500→2003，800→2004，1000→2005，2000→2006。
- `GIFT_API_URL` 为空。
- 领取前数一下 `monthly_cumulative_recharge_user_data_unique_records` 里该角色、活动 2、当月、`reward_id='tier_Q'` 的行数，应为 0。同时确认一个仍为 `locked` 的更高档 H。

## 操作步骤

1. `POST /api-front/activity/monthly-cumulative-recharge/claim`，JSON `{"activity_id":2,"threshold":H}`。
2. 再 claim，`threshold` 为 `999`。
3. claim `threshold` 为 Q。间隔约 1 秒后，用同一个 Q 再 claim 一次。
4. 再查 status，并数 `reward_id='tier_Q'` 的行数。

## 预期结果

- 第 1 步 HTTP 400，`code` 为 `RECHARGE_NOT_REACHED`，`exception.message` 为 `Recharge amount not reached`。`tier_H` 行数仍为 0。没有 `[gift] mock`。
- 第 2 步 HTTP 400，`code` 为 `INVALID_RECHARGE_TIER`，`exception.message` 为 `Invalid recharge tier`。
- 第 3 步第一次 HTTP 200。`status` 为 `success`，`threshold` 与 `claimedTier` 都是 Q，`claimedTiers` 含 `tier_Q`。日志一行 `[gift] mock`，`rewardType` 为 `recharge`，礼物 id 与 Q 对应。
- 第 3 步第二次 HTTP 400，`code` 为 `REWARD_ALREADY_CLAIMED`，`exception.message` 为 `Recharge reward already claimed`。没有第二行 `[gift] mock`。
- 第 4 步该档 `status` 为 `claimed`，`tier_Q` 唯一行恰好 1 行。

## 不应出现

- 未达档仍插入唯一领取行。
- 第二次领取再次发奖，或把档位改回 `claimable`。
- 成功体里出现 `code:0`。

## 备注

- 两请求几乎同时发出见 TC-PAY-06。本条第二次必须在第一次的响应返回之后，间隔约 1 秒，避开锁墓碑。
- 已有自动化：`tests/Service/RechargeClaimTest.php` 覆盖未达档、非法档、领取一次后再领。它会改角色丙的已领档，执行前不要假设丙的 100 档仍可领。
- 发奖 HTTP 500 的路径是 TC-PAY-04，不要把空 URL 的 `[gift] mock` 当成发奖失败。

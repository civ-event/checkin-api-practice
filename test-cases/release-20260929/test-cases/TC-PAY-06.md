# TC-PAY-06: 同一档同时领取至多成功一次

| 属性 | 值 |
|------|-----|
| 编号 | TC-PAY-06 |
| 优先级 | P0 |
| 模块 | 累充 |
| 平台 | Server |
| 关联需求 | 领取有锁，唯一键为角色 + 活动 + 年月 + 档位 |
| 关联 CR | 7.2 竞态「同一档并发 claim」；第四章「同一秒连点领取」 |

## 前置条件

- 找一个 `claimable` 档 Q，且 `tier_Q` 行数为 0。若没有，对尚未达到 100 的角色 record `amount=100`，使 100 档变为 `claimable`。
- `GIFT_API_URL` 为空，便于数 `[gift] mock`。
- 锁等待为 0。抢不到立即返回，不会在接口里排队到对方提交完。

## 操作步骤

1. 准备两条相同的 claim：`POST /api-front/activity/monthly-cumulative-recharge/claim`，JSON `{"activity_id":2,"threshold":Q}`，同一个 token。
2. 同一秒内发出这两条请求（两个 curl 并行，或 Postman 的两次几乎同时发送）。
3. 数 `reward_id='tier_Q'` 的行，并在日志里数这次 Q 对应的 `[gift] mock`。

## 预期结果

- 恰有一个响应是 HTTP 200，`claimedTier` 为 Q。
- 另一个响应是下列之一：
  - HTTP 409，`code` 为 `RESOURCE_BUSY`，消息为 `The resource is busy, please retry.`
  - HTTP 400，`code` 为 `REWARD_ALREADY_CLAIMED`（仅当第一个已经提交并且锁已释放）
- `tier_Q` 行数为 1。
- 该档的 `[gift] mock` 只有 1 行。

## 不应出现

- 两个 HTTP 200。
- 两行 `tier_Q`，或两行 `[gift] mock`。
- 409 之后档位仍是 `claimable` 且行数为 0。那种情况说明两笔都没写入，应重试本条，不能算「至多一次」已经证实。至少要看到一次成功。

## 备注

- 墓碑 TTL 约 1 秒。串行间隔 1 秒以上的再领属于 TC-PAY-03，不是本条。
- 旧库若没跑到迁移 `009`，订单去重会失真，但本条看的是领取唯一表，不看订单唯一键。订单唯一键见 TC-REG-02。

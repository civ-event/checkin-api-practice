# TC-PAY-04: 累充发奖失败后重试不再发奖

| 属性 | 值 |
|------|-----|
| 编号 | TC-PAY-04 |
| 优先级 | P0 |
| 模块 | 累充 |
| 平台 | Server |
| 关联需求 | 领取先提交已领记录，再请求发奖 |
| 关联 CR | CR-2；7.3 草案 |

## 前置条件

- 把 `GIFT_API_URL` 指到必定返回 HTTP 500 的地址，重启 app。测完改回空字符串并再次重启。
- 角色当月累计已达到 100，且 100 档尚未领取。若练习库里丙、丁的 100 档已领，先对一个未领角色 `record`，使 `100 <= totalRechargeGoods`，且该档不是 `claimed`。
- 领取前 `reward_id='tier_100'` 的行数为 0。

## 操作步骤

1. `POST /api-front/activity/monthly-cumulative-recharge/claim`，JSON `{"activity_id":2,"threshold":100}`。
2. 查该唯一行，并再查 status。
3. 再 claim 一次 `threshold=100`。
4. 看容器日志里这次阈值对应的发奖行。

## 预期结果

- 第 1 步 HTTP 500，`code` 为 `INTERNAL_ERROR`，消息为 `Internal server error, please try again later.`。响应无 `file`、`line`。
- 库中 `tier_100` 已有 1 行，status 里 100 档为 `claimed`。
- 日志有 `[gift] http failed`，没有 `[gift] sent`。
- 第 3 步 HTTP 400，`code` 为 `REWARD_ALREADY_CLAIMED`。没有第二行 `[gift] http failed` 或 `[gift] sent`。

## 不应出现

- 500 之后档位回到未领。
- 重试再插入一行礼物，或再插一行 `tier_100`。
- 用默认空 `GIFT_API_URL` 执行本条。空 URL 只会 `[gift] mock` 并返回 200。

## 备注

- 签到侧同一时序是 TC-CHECKIN-04。
- 唯一索引冲突走的是 `RESOURCE_BUSY`（409），不是这条 500。本条的 500 来自发奖客户端抛出的 `RuntimeException`。

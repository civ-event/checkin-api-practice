# TC-CHECKIN-01: 月度签到进度

| 属性 | 值 |
|------|-----|
| 编号 | TC-CHECKIN-01 |
| 优先级 | P0 |
| 模块 | 签到 |
| 平台 | Server |
| 关联需求 | `GET /api-front/activity/monthly-check-in/status`，键为角色 + 活动 + 年月 |
| 关联 CR | 7.1 签到状态 |

## 前置条件

- 角色丁已登录，请求头 `activity-user-token` 有效。
- 先在 app 容器确认游戏时区的今天：`php -r 'echo (new DateTimeImmutable("now", new DateTimeZone("Etc/GMT+5")))->format("Y-m-d");'`。下面把这个 `Ym` 记为「游戏当月」，把「日」记为 D。10 月内 D 应在 1～31。

## 操作步骤

1. `GET /api-front/activity/monthly-check-in/status?activity_id=1`，带请求头。
2. 去掉查询参数再请求一次。
3. 换一个已登录角色（角色丙）再查 `activity_id=1`。

## 预期结果

- 第 1 步 HTTP 200，且没有 `code` 外壳。
- `yearMonth` 等于游戏当月，例如仍在 10 月时为 `202610`。
- `makeupLimit` 为 `3`。`makeupRemaining` = `3 - makeupUsed`，且不小于 0。
- `tiers` 长度为 31。已签的天 `status` 为 `claimed`。下一档若可签则为 `claimable`，其余为 `locked`。
- `maxAvailableDay` 等于 min(D, 31)。活动从 2026-09-01 开始，10 月从 1 号起算，所以 10 月 10 日是 10，不是从 9 月 1 日累计的 40。
- `checkedDays` 只含这个角色、活动 1、这个 `yearMonth` 的天数。9 月若有旧行，不会出现在这次 `checkedDays` 里。
- 第 2 步 HTTP 400，`code` 为 `INVALID_PARAMETER`，`exception.message` 为 `activity_id is required and must be int`。
- 第 3 步的 `checkedDays` 与角色丁无关。

## 不应出现

- 用宿主机 2026-09 的日期断言 `yearMonth` 或 `maxAvailableDay`。
- `claimedDays` 这种旧字段。当前已签状态在 `tiers[].status=claimed`。
- 状态接口本身插入签到记录。没进度时只在内存里造一条，本请求不落库。

## 备注

- 签到「今天」用游戏时区 `Etc/GMT+5`，不用角色服务器时区。角色乙、丙的服务器是 `Asia/Shanghai`，也不改变签到年月。
- 若容器重启后库里的 `last_check_time` 比回跳后的时钟更晚，本接口会 500。那是 TC-API-01b，不要把本条判失败。

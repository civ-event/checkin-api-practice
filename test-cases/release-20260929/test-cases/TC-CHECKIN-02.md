# TC-CHECKIN-02: 打卡发奖，以及当天第二次算补签

| 属性 | 值 |
|------|-----|
| 编号 | TC-CHECKIN-02 |
| 优先级 | P0 |
| 模块 | 签到 |
| 平台 | Server |
| 关联需求 | `POST /api-front/activity/monthly-check-in/clock-in` 记签到并发奖，每月补签 3 次 |
| 关联 CR | 7.1 打卡 / 补签 |

## 前置条件

- 用角色丁的 token。若丁本月 `makeupRemaining` 为 0，或 `nextCheckDay` 已超过 `maxAvailableDay`，换一个本月还没签满的角色，并在备注里写下角色。
- `GIFT_API_URL` 为空。发奖只打日志，不请求游戏服。
- 先执行 TC-CHECKIN-01，记下 `nextCheckDay`、`isCheckedToday`、`makeupUsed`、`makeupRemaining`、`maxAvailableDay`。下面把 `nextCheckDay` 记为 N。N 必须 ≤ `maxAvailableDay`。

## 操作步骤

1. `POST /api-front/activity/monthly-check-in/clock-in`，请求头 `activity-user-token`，`Content-Type: application/json`，正文 `{"activity_id":1,"check_day":N}`。
2. 再查一次 status，把新的 `nextCheckDay` 记为 N2。
3. 立刻再打卡 `check_day` = N2（同一游戏日）。
4. 再打一次 `check_day` = N（已经签过的那天）。
5. 再打一次 `check_day` = N2 + 1（跳过下一档）。

## 预期结果

- 第 1 步 HTTP 200。`status` 为 `success`，`claimedTier` 为 N，`yearMonth` 为游戏当月。
- 若打卡前 `isCheckedToday` 为 false：`isMakeup` 为 false，`makeupUsed` 不变。
- 若打卡前 `isCheckedToday` 为 true：`isMakeup` 为 true，`makeupUsed` 比打卡前多 1，`makeupRemaining` 少 1。
- 容器日志有一行 `[gift] mock`，`rewardType` 为 `sign`，礼物 id 为 `1000+N`。没有 `[gift] sent`，也没有 `[gift] http failed`。
- 第 3 步 HTTP 200，`isMakeup` 为 true，`claimedTier` 为 N2，`makeupUsed` 再加 1。
- 第 4 步 HTTP 400，`code` 为 `ALREADY_CHECKED_IN`，`exception.message` 为 `Already checked in this day`。日志有 `[checkin] reject`，且 `reason=already_checked_day`。`checkedDays` 不增加。
- 第 5 步 HTTP 400，`code` 为 `CHECK_IN_ORDER_ERROR`，`exception.message` 为 `This day is not available for check-in yet`。日志 `reason=order`。

## 不应出现

- 另有一个签到领奖接口才发奖。打卡成功即发奖。
- 第 4、5 步仍出现新的 `[gift] mock`。
- 第 3 步把 `isMakeup` 记成 false。

## 备注

- 正文用 JSON 时，`Content-Type` 为 `application/json`。改成 `application/x-www-form-urlencoded` 时，字段必须是表单 `activity_id` 和 `check_day`，不能把 JSON 原文放进表单正文。
- 同一角色连续两次打卡若间隔太短，第二次可能 HTTP 409，`code` 为 `RESOURCE_BUSY`，消息为 `The resource is busy, please retry.`。锁墓碑约 1 秒。等 1 秒再执行第 3 步，不要把 409 当成业务拒绝。
- 已有自动化：`tests/Service/CheckInClockInTest.php` 覆盖「签下一档、重复同一天、跳天」。补签扣次以本条第 3 步为准。
- 补签次数用尽见 TC-CHECKIN-03。

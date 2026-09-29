# TC-CHECKIN-03: 本月补签次数用尽

| 属性 | 值 |
|------|-----|
| 编号 | TC-CHECKIN-03 |
| 优先级 | P1 |
| 模块 | 签到 |
| 平台 | Server |
| 关联需求 | 每月补签 3 次，用尽后再签下一档被拒绝 |
| 关联 CR | 7.1。CR-2 曾把签到发奖失败也写成 TC-CHECKIN-03；发奖失败改到 TC-CHECKIN-04，避免两个预期共用一个编号 |

## 前置条件

- 选一个本月 `makeupUsed` 还小于 3、且 `nextCheckDay + (3 - makeupUsed)` 不超过 `maxAvailableDay` 的角色。练习库里角色丁可能已经签过若干天，先查 status 再决定从哪一天打。
- 会连续打卡，占用该角色本月剩余补签。不要和 TC-CHECKIN-02 使用同一个还要观察补签剩余的现场。

## 操作步骤

1. 查询 status，确认今天若还没签，先打 `nextCheckDay` 一次（这次是当日首签，不扣补签）。
2. 在同一个游戏日内，连续打下一档，直到 `makeupUsed` 变为 3。每两次之间至少间隔 1 秒。
3. `makeupUsed` 已经是 3 之后，再打新的 `nextCheckDay`。

## 预期结果

- 第 2 步每次 HTTP 200，`isMakeup` 为 true。三次补签之后 `makeupUsed` 为 3，`makeupRemaining` 为 0，`makeupLimit` 仍为 3。
- 第 3 步 HTTP 400，`code` 为 `MAKEUP_LIMIT_EXCEEDED`，`exception.message` 为 `No makeup check-in chance left this month`。
- 第 3 步之后再查 status：`checkedDays` 与第 3 步之前相同，`makeupUsed` 仍为 3。
- 第 3 步的容器日志没有新的 `[gift] mock`。

## 不应出现

- `makeupUsed` 变成 4。
- 次数用尽却仍返回 `status=success`。
- 把 409 `RESOURCE_BUSY` 记成补签用尽。那是锁，等 1 秒重试第 3 步。

## 备注

- 当天第一次打卡不扣补签。只在 `isCheckedToday` 已经为 true 时再打下一档才扣。
- 跨过游戏时区的 0 点之后，下一次打卡又是当日首签。本条必须在同一次「游戏日」内打完。游戏日按 `Etc/GMT+5`，不是北京时间的 0 点。

# TC-CHECKIN-04: 签到发奖失败后重试不再发奖

| 属性 | 值 |
|------|-----|
| 编号 | TC-CHECKIN-04 |
| 优先级 | P1 |
| 模块 | 签到 |
| 平台 | Server |
| 关联需求 | 打卡先落库再发奖。发奖失败不回滚 |
| 关联 CR | CR-2。审查正文把签到侧写成 TC-CHECKIN-03，与 7.1「补签用尽」冲突，本条改用 TC-CHECKIN-04 |

## 前置条件

- 只在本地练习环境改 `GIFT_API_URL`，指向一个必定返回 HTTP 500 的地址。改完后重启 app 容器。
- 记下改之前的值（练习默认是空）。本条结束后改回空字符串并再次重启。
- 选一个今天还能签、且目标天尚未写入 `monthly_daily_check_in_user_data_unique_records` 的角色。记下打卡前的 `checkedDays` 和目标天 N。

## 操作步骤

1. `POST /api-front/activity/monthly-check-in/clock-in`，正文 `{"activity_id":1,"check_day":N}`。
2. 查 `monthly_daily_check_in_user_data_unique_records` 中该角色、活动 1、当月、`check_day=N` 的行数。
3. 再查签到 status。
4. 用同一个 `check_day` 再打一次。
5. 把 `GIFT_API_URL` 改回空并重启后，再打下一档，确认日志恢复为 `[gift] mock`。这一步只验证环境已还原。

## 预期结果

- 第 1 步 HTTP 500，`code` 为 `INTERNAL_ERROR`。响应消息是通用的 `Internal server error, please try again later.`，响应里没有 `file`、`line`。
- 容器日志有 `[gift] http failed`，没有 `[gift] sent`，也没有 `[gift] mock`。
- 第 2 步该天的唯一行有 1 行。进度里的已签天数已包含 N。
- 第 3 步该天 `tiers[].status` 为 `claimed`。
- 第 4 步 HTTP 400，`code` 为 `ALREADY_CHECKED_IN`。日志是 `[checkin] reject`，没有第二行 `[gift] http failed` 或 `[gift] sent`。

## 不应出现

- 第 1 步 500 之后该天回到未签。
- 第 4 步再次请求游戏服发奖。
- 测完仍把 `GIFT_API_URL` 留在 500 地址上。

## 备注

- 累充的同一缺陷见 TC-PAY-04。两处都是事务提交之后才调用 `HttpGiftClient`。
- 默认空 URL 不会走出这条路径，所以本条不能用「看到 `[gift] mock`」代替。

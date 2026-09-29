# TC-CHECKIN-05: 签到接口传入累充活动 id

| 属性 | 值 |
|------|-----|
| 编号 | TC-CHECKIN-05 |
| 优先级 | P0 |
| 模块 | 签到 |
| 平台 | Server |
| 关联需求 | 签到活动 id 为 1，类型 `monthly_daily_check_in` |
| 关联 CR | 7.2 Negative「签到 activity_id=2」；第四章「活动 id 传错」 |

## 前置条件

- 角色丁的 `activity-user-token` 有效。
- 先按 TC-CHECKIN-01 确认 `activity_id=1` 能 200，避免把过期 token 当成活动错误。

## 操作步骤

1. `GET /api-front/activity/monthly-check-in/status?activity_id=2`，带请求头。
2. `POST /api-front/activity/monthly-check-in/clock-in`，JSON 正文 `{"activity_id":2,"check_day":1}`。
3. 再查 `activity_id=1` 的 status，对比 `checkedDays`。

## 预期结果

- 第 1 步和第 2 步都是 HTTP 404，`code` 为 `ACTIVITY_NOT_FOUND`，`exception.message` 为 `Activity is not configured`。
- 第 2 步没有 `[gift] mock`，也没有 `[checkin] reject`。请求在活动查找时就结束。
- 第 3 步 `checkedDays` 与本条开始前相同。

## 不应出现

- 消息是 `Activity is not running`。那句只在活动记录存在、但当前时间不在窗口内时出现。活动 2 的类型不是签到，所以是未配置。
- 累充表 `monthly_recharge_user_data` 或充值订单被这次打卡改动。
- 旧数字码 `10014`。

## 备注

- 累充接口传入签到活动 id 见 TC-PAY-07。
- 已有自动化：`tests/Config/RunningActivityTest.php` 用活动 2 按签到类型查找，期望 `ACTIVITY_NOT_FOUND`。本条补的是 HTTP 路径和「进度不被写入」。

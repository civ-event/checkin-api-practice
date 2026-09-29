# TC-API-01b: 重启后假时钟跳回锚点

| 属性 | 值 |
|------|-----|
| 编号 | TC-API-01b |
| 优先级 | P0 |
| 模块 | 时钟 |
| 平台 | Server |
| 关联需求 | `FAKETIME` 使用 `@` 起始时间。容器重启后从锚点再走，不会接着上次的墙上时间 |
| 关联 CR | CR-1 时序。中断时机：签到已经写入 `last_check_time` 之后，再重启 app 容器 |

## 前置条件

- 先完成一次成功打卡（TC-CHECKIN-02 第 1 步），确认该角色当月 `isCheckedToday` 为 true。
- 记下容器里当时的 `php -r 'echo time();'`，记为 T1。数据库该行 `last_check_time` 应接近 T1，且不大于当时的 `time()`。

## 操作步骤

1. `docker compose restart app`。等 app 重新可用。
2. 立刻在容器执行 `php -r 'echo time(), PHP_EOL, date("Y-m-d H:i:s");'`。
3. 用刚才打卡的那个角色查 `GET /api-front/activity/monthly-check-in/status?activity_id=1`。

## 预期结果

- 第 2 步的 Unix 时间回到锚点附近（`2026-10-10 17:00:00` UTC，上海时间 `2026-10-11 01:00:00` 附近），小于重启前的 T1。前提是重启前时钟已经往前走过。
- 若 `last_check_time` 大于重启后的 `time()`：第 3 步 HTTP 500，`code` 为 `INTERNAL_ERROR`。容器日志含 `Inconsistent check-in data: lastCheckTime is in the future.`。响应正文仍是通用 500 文案，没有 `file`、`line`。
- 累充 status 不读签到的 `last_check_time`，同一 token 查累充仍可 200。

## 不应出现

- 重启后 Unix 时间继续大于 T1，却声称 `@` 起始时间会保留流逝。
- 把这条 500 当成签到规则错误（补签、跳天、活动 id）。比较的是时钟和已写入的 `last_check_time`。

## 备注

- 练习要继续打卡时，删掉该角色当月签到行和对应唯一记录，或一直等到假时钟再次超过 `last_check_time`。不要为了本条去改业务代码。
- 竞态见 TC-API-01c（不适用）。宿主机日期见 TC-API-01d。

# TC-AUTH-03: 只带查询串 token、不带请求头

| 属性 | 值 |
|------|-----|
| 编号 | TC-AUTH-03 |
| 优先级 | P0 |
| 模块 | 登录 |
| 平台 | Server |
| 关联需求 | 身份来自登录 token |
| 关联 CR | CR-4；7.2 Negative「去掉请求头只留查询串 token」 |

## 前置条件

- 已用 TC-AUTH-02 拿到角色丁的 `activityUserToken`，且未过期。
- 当前代码仍接受查询参数 `activity_user_token`。本条记录的是修 CR-4 之前的行为。

## 操作步骤

1. `GET /api-front/activity/monthly-check-in/status?activity_id=1&activity_user_token={token}`。不要设置请求头 `activity-user-token`。
2. 同一条 URL 去掉 `activity_user_token`，改为请求头 `activity-user-token: {token}`。

## 预期结果

- 第 1 步当前 HTTP 200，正文含 `yearMonth`、`makeupLimit=3`。这表示查询串分支仍生效。
- 第 2 步 HTTP 200，`yearMonth` 与第 1 步相同。
- 若以后去掉查询串分支：第 1 步必须变为 HTTP 401，`code` 为 `UNAUTHORIZED`，`exception.message` 为 `activity-user-token is required`。第 2 步仍为 200。

## 不应出现

- 把「查询串也能 200」写成已经修复。CR-4 仍开放时，第 1 步 200 是现状，不是通过修复。
- token 被写进对外文档的示例 URL。

## 备注

- 7.4：访问日志、代理日志会记下查询串。本条只在本地练习环境执行。
- 请求头和查询串同时存在时，代码先用请求头。本条不测那种混合，避免分不清走了哪一条。

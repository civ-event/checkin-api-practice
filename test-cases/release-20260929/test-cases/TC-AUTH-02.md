# TC-AUTH-02: 登录签发 activityUserToken

| 属性 | 值 |
|------|-----|
| 编号 | TC-AUTH-02 |
| 优先级 | P0 |
| 模块 | 登录 |
| 平台 | Server |
| 关联需求 | `POST /api-auth/activity/join`，响应字段 `activityUserToken` |
| 关联 CR | 7.1 登录拿 token |

## 前置条件

- 已完成 TC-AUTH-01，确认 `token-player-1001` 下有角色丁 `r400` / `s4`。

## 操作步骤

1. `POST /api-auth/activity/join`，JSON 正文 `{"accessToken":"token-player-1001","roleId":"r400","serverId":"s4"}`。
2. 立刻再登录一次，正文相同。
3. 第三次把 `roleId` 改成 `r100`，`serverId` 仍为 `s4`。
4. 不带请求头，`GET /api-front/activity/monthly-check-in/status?activity_id=1`。
5. 请求头 `activity-user-token` 填 `not-a-jwt`，再查一次签到状态。

## 预期结果

- 第 1 步 HTTP 200。`user_info.player_id` 为 `p1001`，`user_info.username` 为 `player1001`。
- `active_user_role` 为角色丁：`role_id=r400`，`server_id=s4`，`role_level=200`。
- `activityUserToken` 非空。后续接口只把这一串放进请求头 `activity-user-token`。
- 第 2 步再次 200，且数据库里该玩家、该角色的主键不新增。两次登录代表同一个角色。
- 第 3 步 HTTP 404，`code` 为 `ROLE_NOT_FOUND`，`exception.message` 为 `Role does not belong to this player`。
- 第 4 步 HTTP 401，`code` 为 `UNAUTHORIZED`，`exception.message` 为 `activity-user-token is required`。
- 第 5 步 HTTP 401，`code` 为 `UNAUTHORIZED`，`exception.message` 为 `Invalid or expired token`。

## 不应出现

- 响应字段仍叫 `data.token`。
- 用游戏字符串 `r400` 当作后续签到、累充的身份。身份是 JWT 里的角色主键。
- 第 3 步登录成功。

## 备注

- JWT 里写入的 `activity_id` 固定为 1。签到、累充真正用的活动 id 来自各自请求，不要用解码出的活动 id 去调累充。
- 默认 `JWT_TTL` 为 3600 秒。过期后再查状态，消息同样是 `Invalid or expired token`。
- 只带查询参数、不带请求头的通过路径在 TC-AUTH-03，不要和本条的「缺 token」混在一次断言里。

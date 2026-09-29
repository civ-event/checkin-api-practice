# TC-AUTH-01: 假账号列出四个角色

| 属性 | 值 |
|------|-----|
| 编号 | TC-AUTH-01 |
| 优先级 | P0 |
| 模块 | 登录 |
| 平台 | Server |
| 关联需求 | 选角。`POST /api-auth/activity/get-user-role-list`，账号来自 `config/accounts.php` |
| 关联 CR | 7.1 选角四个角色 |

## 前置条件

- 服务已启动。基址同 TC-E2E-01。
- 不需要 `activity-user-token`。

## 操作步骤

1. `POST /api-auth/activity/get-user-role-list`，`Content-Type: application/json`，正文 `{"accessToken":"token-player-1001"}`。
2. 用同一个路径再请求一次，正文 `{"accessToken":"no-such-token"}`。
3. 再请求一次，正文 `{}`。

## 预期结果

- 第 1 步 HTTP 200。`roles` 恰好 4 条，顺序与配置一致：
  - 角色甲 `r100`，等级 30，`s1` / 一区
  - 角色乙 `r200`，等级 28，`s2` / 二区
  - 角色丙 `r300`，等级 100，`s3` / 三区
  - 角色丁 `r400`，等级 200，`s4` / 四区
- 每条只有 `role_id`、`role_name`、`role_level`、`server_id`、`server_name`。没有活动 token。
- 第 2 步 HTTP 401，`code` 为 `UNAUTHORIZED`，`exception.message` 为 `Invalid access token`。
- 第 3 步 HTTP 400，`code` 为 `INVALID_PARAMETER`，`exception.message` 为 `accessToken is required`。

## 不应出现

- 只返回甲、乙两个角色。
- 角色 id 变成 `activity_user_role` 的自增主键。这里仍是游戏字符串 `r100` 等。
- 成功体套 `{code,message,data}`。

## 备注

- 短路径 `POST /auth/roles` 和旧路径 `POST /api-front/auth/roles` 打到同一个动作。抽一条复测即可，三条都要 200 且 `roles` 相同。
- 错误体形状是 `{"code":"...","exception":{"type":"BusinessException","message":"..."}}`。

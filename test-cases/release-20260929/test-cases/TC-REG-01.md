# TC-REG-01: 探活是纯文本，JSON 探针是对象本身

| 属性 | 值 |
|------|-----|
| 编号 | TC-REG-01 |
| 优先级 | P2 |
| 模块 | 回归 |
| 平台 | Server |
| 关联需求 | 内核探活 |
| 关联 CR | 7.4「探活是纯文本，不是 JSON」 |

## 前置条件

- 服务已启动。这些路径不带 `activity-user-token`。
- Nginx 对 `/`、`/memcached`、`/lock`、`/db`、`/json` 能转到入口。若直连路径 404，改用项目文档里的 `/front.php/...` 再测一次，并在结果里写明实际路径。

## 操作步骤

1. `GET /`
2. `GET /memcached`
3. `GET /lock`
4. `GET /db`
5. `GET /json`

## 预期结果

- 第 1 步正文为纯文本 `slimapp ok`，`Content-Type` 含 `text/plain`。
- 第 2 步纯文本 `memcached ok`。
- 第 3 步纯文本 `lock ok`。
- 第 4 步纯文本 `db ok`。
- 第 5 步 JSON 为 `{"ok":true}`。没有 `code`、`message`、`data`。

## 不应出现

- 把前四步解析成 `{"code":0,...}`。
- `/json` 被包进 `data.ok`。

## 备注

- 对外端口以 `HTTP_PORT` 为准。

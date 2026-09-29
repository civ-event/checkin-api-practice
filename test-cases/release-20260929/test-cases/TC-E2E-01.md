# TC-E2E-01: 选角、登录、查签到、查累充

| 属性 | 值 |
|------|-----|
| 编号 | TC-E2E-01 |
| 优先级 | P0 |
| 模块 | 端到端冒烟 |
| 平台 | Server |
| 关联需求 | 选角、登录、月度签到进度、查月度累充 |
| 关联 CR | 第七章 7.1；第九章「平台 E2E」 |

## 前置条件

- `docker compose up` 已启动，基址取 `.env` 的 `HTTP_PORT`。本机练习若是 `18080`，基址为 `http://localhost:18080`。缺省端口是 `8080`。
- 不修改 `FAKETIME`、`GIFT_API_URL`。
- 本条只读，不打卡、不入账、不领奖。

## 操作步骤

1. `POST /api-auth/activity/get-user-role-list`，`Content-Type: application/json`，正文 `{"accessToken":"token-player-1001"}`。
2. `POST /api-auth/activity/join`，正文 `{"accessToken":"token-player-1001","roleId":"r400","serverId":"s4"}`。记下响应里的 `activityUserToken`。
3. `GET /api-front/activity/monthly-check-in/status?activity_id=1`，请求头 `activity-user-token` 为上一步的 token。
4. `GET /api-front/activity/monthly-cumulative-recharge/status?activity_id=2`，请求头同上。

## 预期结果

- 四步 HTTP 都是 200。成功体是 JSON 对象本身，没有 `code`、`message`、`data` 外壳。
- 第 1 步 `roles` 长度为 4，含 `r100/s1`、`r200/s2`、`r300/s3`、`r400/s4`。
- 第 2 步 `active_user_role.role_id` 为 `r400`，`activityUserToken` 为非空字符串。
- 第 3 步 `yearMonth` 等于容器内游戏时区 `Etc/GMT+5` 的当前 `Ym`，`makeupLimit` 为 `3`。
- 第 4 步含 `totalRechargeGoods`、`roundEndAt`、`tiers`。`tiers` 有 6 档：100、300、500、800、1000、2000。

## 不应出现

- 成功体 `{code:0,message,data}`。
- 用宿主机当天（例如 2026-09-29）去对 `yearMonth`。
- 把 `activityUserToken` 当成活动 id。活动 id 仍是查询参数 `activity_id`。

## 备注

- 角色丁没有种子订单。第 4 步的金额是否为 0，取决于这个库是否已经对 `r400` 调用过 `record`。本条不断言绝对金额，绝对金额见 TC-PAY-01、TC-PAY-02。
- 7.4：前端路径 `monthly-cumulative-recharge` 没有入账接口，本条也不调用它。

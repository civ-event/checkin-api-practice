# TC-API-02: 登录、发奖、初始订单使用假数据

| 属性 | 值 |
|------|-----|
| 编号 | TC-API-02 |
| 优先级 | P0 |
| 模块 | 接口 |
| 平台 | Server |
| 关联需求 | 不能调用的外部系统用假数据：账号、发奖、初始充值订单 |
| 关联 CR | 第一章需求行 TC-API-02；第九章「假登录、假发奖与不能调用就用假数据一致」 |

## 前置条件

- `GIFT_API_URL` 为空。不要指到真实游戏服。
- 角色丁本月还有可打的下一档 N，且今天按 TC-CHECKIN-02 的规则允许打这一档。若已经没有可打档，只做第 1 步和第 3 步，第 2 步记阻塞并说明原因。

## 操作步骤

1. 用 `accessToken=token-player-1001` 登录角色丁。确认没有请求游戏登录服。
2. 打卡 `check_day=N`，`activity_id=1`。看 app 日志。
3. 用角色乙查累充 status。查 `recharge_payment_orders` 里 `pay-2001`、`pay-2002`。

## 预期结果

- 第 1 步 HTTP 200，`activityUserToken` 非空。账号只来自 `config/accounts.php`。
- 第 2 步 HTTP 200。日志是 `[gift] mock`，正文含 `rewardType`、`serverId`、`roleId`、`playerId`、`itemList`。没有 `[gift] sent`，也没有对 `GIFT_API_URL` 的外呼。
- 第 3 步种子订单来自 `config/mock_payments.php`，同一 `order_id` 只有一行。10 月合计不含这两笔 9 月金额，见 TC-PAY-01。

## 不应出现

- 空 `GIFT_API_URL` 却出现 `[gift] http failed`。
- 登录去校验真实 accessToken。
- 每次查 status 都再插一行相同的 `pay-2001`。

## 备注

- 假数据是练习决定，保留。TC-CHECKIN-04、TC-PAY-04 才把 URL 改成 500，测完必须改回。
- 发奖日志关键字见审查第八章。

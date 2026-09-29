# checkin-api-practice 20260928 代码审查报告

> 分支：`main`（与 `origin/main` 同提交 `9971672`）  
> 基线：`4d40066`（Docker 骨架；其后 3 个提交才是选角 / 签到 / 累充）  
> 相对 `origin/main` 的 diff 为空，因此不能把「相对远端无差异」当成需求已验收。  
> 日期：2026-09-28  
> 统计：`4d40066...9971672` 约 80 文件，+8571 / −114  
> 项目无 `.cursor/skills/project-test-profile.md`，使用默认：用例前缀 `TC`，对象为调用接口的客户端，平台为服务端。  
> 审查说明：仓库内无 PHPUnit / 自动化用例目录。本轮 curl 抽测覆盖的是本机已跑通的主路径，不能代替第七章的用例落盘。

## 审查概要（严重度计数）

| 级别 | 数量 | 说明 |
|------|------|------|
| 🔴 CR-P0 | 0 | 未发现可在默认空 `GIFT_API_URL` 下直接丢进度的必修项 |
| 🟡 CR-P1 | 4 | 发奖失败后无法补发、活动窗口写死 9 月、充值金额不接受客户端入账、token 可走查询串 |
| 🟢 CR-P2 | 3 | 重复查库、假流水订单号重复、800 档礼物与 500 档相同 |
| ℹ️ | 1 | 「月度充值」若产品定义为客户端记账，则现实现为有意偏差 |

**四态建议：需补测后发版。**  
作为练习后端，选角、登录、月度签到（含领奖）、月度累充查询和按档领奖主链路已经按分层写完，默认本地环境可以演示。作为可交给其他后端长期维护、或接到真实游戏服的交付，自动化用例、活动窗口和发奖失败补发都还没过门禁。

## 一、需求与实现匹配（含关联用例、覆盖状态）

| 需求 | 实现 | 关联用例 | 覆盖 |
|------|------|----------|------|
| 选角 | `POST /api-auth/activity/get-user-role-list`，账号来自 `config/accounts.php` | TC-AUTH-01 | ⚠️有用例未测（仅草案） |
| 登录 | `POST /api-auth/activity/join`，签发 `activityUserToken`，角色主键复用 | TC-AUTH-02 | ⚠️ |
| 按月查询签到进度 | `GET .../monthly-check-in/status`，键为角色 + 活动 + `yearMonth` | TC-CHECKIN-01 | ⚠️ 本机会话曾 curl 通过，无仓库用例 |
| 月度签到并领奖 | `POST .../monthly-check-in/clock-in` 记进度并发奖，补签在同接口 | TC-CHECKIN-02 | ⚠️ |
| 查月度累充进度 | `GET .../monthly-cumulative-recharge/status` | TC-PAY-01 | ⚠️ |
| 月度充值 | 无客户端入账。`record` 的 `amount` 不累加，金额由 `config/mock_payments.php` 覆盖 | TC-PAY-02 | ❌无用例；与「客户端记账」不一致 |
| 领取累充奖励 | `POST .../claim`，`threshold` 为档位 100/300/500/800 | TC-PAY-03 | ⚠️ 本机领过 300 档 |

未纳入本需求、也不作为缺口：`POST /api-front/session/switch-role`。换角色现由再次 `join` 完成。

## 二、行为变更摘要

| 前（`4d40066`） | 后（`9971672`） | 触发条件 |
|-----------------|-----------------|----------|
| 只有 Compose 骨架 | 选角、登录、月度签到、月度累充可 HTTP 调用 | 任意上述 path |
| 无身份 | JWT 角色主键；请求头 `activity-user-token`，也接受查询参数 `activity_user_token` | 签到 / 累充 |
| 无进度 | MySQL 按月一行；写操作 Memcached 锁 + 唯一领取表 | clock-in / claim |
| 无发奖 | 事务提交后 `HttpGiftClient`；URL 为空只打日志 | 打卡成功或领奖成功 |

## 三、问题与风险

### CR-1 发奖 HTTP 失败发生在领取提交之后

- 位置：`src/Service/RechargeService.php:150-155`，`src/Service/CheckInService.php` 打卡发奖同样在事务之后；`src/Gift/HttpGiftClient.php:46-54`
- 前置：`GIFT_API_URL` 非空，且游戏服返回非 2xx 或超时
- 影响：`claimed_tiers` 或签到天已经落库，接口返回 500。客户端重试得到 `REWARD_ALREADY_CLAIMED` 或 `ALREADY_CHECKED_IN`，礼物不会再发
- 建议：发奖失败单独记补偿表，或领取与发奖的对外语义写成「已记账、待补发」，不要让重试走「已领取」拒绝
- 验证：`GIFT_API_URL` 指向一个必定 500 的地址，领 100 档；库中该档为已领，响应 500；再次领取不是第二次发奖
- 关联：TC-PAY-04、TC-CHECKIN-03
- 严重度：🟡 CR-P1（默认 URL 为空时只打日志，不触发）

### CR-2 活动窗口写死到 2026-09-30

- 位置：`config/activities.php:10-11`、`17-18`
- 前置：本机日期晚于 2026-09-30 23:59:59（游戏时区）
- 影响：选角和登录仍可用，签到与累充全部 `ACTIVITY_NOT_FOUND`
- 建议：窗口跟练习周期配置，或在 README 写明过期后的预期
- 验证：把 `ends_at` 改到昨天再查 status，必须是 404，而不是 200 空进度
- 关联：TC-API-01
- 严重度：🟡 CR-P1

### CR-3 「月度充值」不接受请求里的金额

- 位置：`src/Service/RechargeService.php:86-96`，`config/mock_payments.php`
- 前置：调用 `POST /api-front/activity/recharge/record`，`amount` 为正整数
- 影响：`amount` 只做校验。当月 `total_amount` 被假流水覆盖。角色乙当前合计 600（100+500），角色甲 900。这不是客户端充了多少
- 建议：若交付范围包含「记一笔充值」，应把订单写入可查询流水再合计；若交付范围就是假流水，在接口说明里写明 `record` 不能入账，并避免同事把它当充值接口
- 验证：连续两次 `record`，`amount` 分别为 1 和 99999，`totalRechargeGoods` 不变
- 关联：TC-PAY-02
- 严重度：🟡 CR-P1（相对本次口头需求）/ ℹ️（相对代码注释中的练习设计）

### CR-4 活动 token 可放在查询串

- 位置：`src/Auth/LoginContext.php:26-28`
- 前置：`GET` 带 `activity_user_token=`
- 影响：Nginx access log 会记下完整 JWT（本机已出现过）。token 可被 Referer、代理日志带走
- 建议：对外只保留请求头 `activity-user-token`。查询参数若仅为了绕过测试工具折行，不要留在可交付分支
- 验证：只带查询参数能 200；只看 access log 不应再出现整段 JWT
- 关联：TC-AUTH-03
- 严重度：🟡 CR-P1

### 冗余：累充状态在覆盖金额后又查一次同一行

- 位置：`RechargeService::applyMockTotal` 已 `findOrCreate`，`getStatus` 随即再 `findOrCreate`（约 54-56 行，167-171 行）。`record` 先 `applyMockTotal` 再 `getStatus`，覆盖逻辑跑两遍
- 等价性：两次读取的是同一角色、活动、月份。合并为一次读取后，档位结果不变
- 性能：每次状态多 1 次 SELECT；`record` 多一轮覆盖判断。数据量小时无感
- 建议：`getStatus` 使用 `applyMockTotal` 已经取出的行
- 严重度：🟢 CR-P2

### CR-5 假流水订单号重复

- 位置：`config/mock_payments.php:10-12`，`r100` 两条都叫 `pay-2002`
- 影响：合计仍是 900，因为代码按行加总，不去重。以后若有人按 `order_id` 去重，角色甲金额会少 500
- 建议：订单号唯一
- 严重度：🟢 CR-P2

### CR-6 800 档与 500 档礼物相同

- 位置：`config/recharge.php:11-12`
- 影响：领 800 与领 500 的 `gift_id` 都是 2003，礼物名都是「充值礼包*500」
- 建议：确认是否故意复用道具。若不是，改独立 `gift_id` / 名称
- 严重度：🟢 CR-P2

## 三-附、AI/冗余与无效性能损耗

见 CR-P2「累充状态重复读取」。未发现无意义 `ORDER BY`、恒真 SQL、未使用的 JOIN。签到状态的 `findOrCreate` 对查询不 `save`，空进度不会写库，这是有效行为。

## 四、用户操作场景分析

| 场景 | 操作时机 | 预期 | 不应出现 |
|------|----------|------|----------|
| 同一 `accessToken` 选角后用 `s2/r200` 登录，再换 `s1/r100` 登录 | 第一次 `join` 成功之后 | 两个 token 的角色主键不同，签到进度分开 | 共用一条进度 |
| 同一角色再次 `join` | 已有 `activity_user_role` 行之后 | 主键不变 | 新插入主键导致进度丢失 |
| 累充金额 600 时领 800 | `claim` 读到 `total_amount` 之后、写 `claimed_tiers` 之前 | `RECHARGE_NOT_REACHED` | `claimedTiers` 出现 `tier_800` |
| 已领档再领 | 唯一表已有 `tier_300` 之后 | `REWARD_ALREADY_CLAIMED` | 发奖日志再打一条 |
| 两请求同时领同一档 | 锁 `add` 成功与唯一索引插入之间 | 一个成功，另一个 409 或已领取 | 两笔 `gift` 都 sent |
| 今天已签后再打下一档 | `isCheckedToday` 为真且 `makeupRemaining>0` | `isMakeup=true`，`makeupUsed` +1 | 不扣补签却记入下一天 |
| 活动过期后查进度 | `ends_at` 已过 | `ACTIVITY_NOT_FOUND` | 200 且空 `tiers` |
| `threshold` 只放在 query | 请求体为空 | `INVALID_PARAMETER` | 200 |

## 五、模块变更概览

| 模块 | 文件 | 结论 |
|------|------|------|
| 选角 / 登录 | `SlimAuthController`、`LoginService`、`accounts.php` | 符合假登录需求 |
| 身份 | `LoginContext`、`JwtService` | 主路径符合；查询串 token 是额外入口 |
| 月度签到 | `CheckInService`、`SlimCheckInController` | 查询、打卡、领奖、补签在同一套进度上 |
| 月度累充 | `RechargeService`、`SlimRechargeController` | 查询与按档领奖符合；入账是假流水 |
| 并发 | `Lock`、`007_unique_claim.sql` | 锁等待为 0，冲突靠唯一索引兜底 |
| 发奖 | `GiftPayload`、`HttpGiftClient` | 成功路径可用；失败补发缺失 |
| 测试 | 无测试目录 | 不达标 |

## 六、各 commit 审查明细

| Commit | 摘要 | 结论 |
|--------|------|------|
| `e24ac13` | SlimApp、登录、签到、累充、锁、表结构 | 骨架正确，当时响应信封与线上不一致，后续提交改掉 |
| `6338286` | 对齐线上活动规则（活动类型、月份、补签、累充） | 业务规则的主体 |
| `9971672` | 路径、字段、`activity-user-token` 对齐客户端 | 主契约对齐；同时加入查询串 token |

## 七、测试覆盖与缺口

### 7.1 需求-用例矩阵

| 需求 | 用例 | 状态 |
|------|------|------|
| 选角成功 / token 不存在 | TC-AUTH-01 | ❌无仓库用例 |
| 登录成功 / 角色不属于该玩家 | TC-AUTH-02 | ❌ |
| token 仅应来自请求头 | TC-AUTH-03 | ❌ |
| 签到 status 按月、按角色 | TC-CHECKIN-01 | ❌ |
| 打卡成功、乱序、补签用尽 | TC-CHECKIN-02 | ❌ |
| 发奖失败后的签到状态 | TC-CHECKIN-03 | ❌ |
| 累充 status 等于假流水合计 | TC-PAY-01 | ❌ |
| `record` 不改变合计 | TC-PAY-02 | ❌ |
| 领达到档 / 未达到 / 已领 | TC-PAY-03 | ❌ |
| 游戏服失败后的已领档 | TC-PAY-04 | ❌ |
| 活动过期 | TC-API-01 | ❌ |

### 7.2 P0 必测清单

| 类型 | 条目 |
|------|------|
| Happy | 选角 → 角色乙登录 → 签到 status → 打下一档 → 累充 status 为 600 → 领一个未领且已达到的档 |
| 时序 | 打卡或领奖成功后，发奖日志出现；失败时进度不回滚 |
| 竞态 | 同一 token、同一天或同一档并发两次，至多一次成功发奖 |
| Negative | 错误 `activity_id`、`threshold` 放在 query、未达到的 800 档（角色乙）、过期活动、无 token |

平台 E2E：本项目只有服务端。iOS/Android 不适用，门禁改为「服务端 P0 curl 或自动化 ≥1」，当前仓库内没有，不通过。

### 7.3 待新增用例草案

#### TC-AUTH-01（新建）: 假 token 列出两个角色

| 优先级 | P0 | 平台 | 服务端 | 关联 CR | — |
**前置** `accounts.php` 存在 `token-player-1001`。  
**步骤** `POST /api-auth/activity/get-user-role-list`，body `accessToken=token-player-1001`。  
**预期** 200，`roles` 含 `r100/s1` 与 `r200/s2`。  
**不应出现** `{code,message,data}` 外层包裹。

#### TC-PAY-02（新建）: 上报金额不改变累充合计

| 优先级 | P0 | 平台 | 服务端 | 关联 CR | CR-3 |
**前置** 角色乙已登录，假流水合计为 600。  
**步骤** `POST /api-front/activity/recharge/record`，body `activity_id=2&amount=1`，再查 status。  
**预期** `totalRechargeGoods` 仍为 600。  
**不应出现** 合计变成 601。

#### TC-PAY-03（新建）: 达到的档可领，未达到的档拒绝

| 优先级 | P0 | 平台 | 服务端 | 关联 CR | — |
**前置** 角色乙当月合计 600，500 档未领，800 档未达到。  
**步骤** body `activity_id=2&threshold=500` 领奖；再领 `threshold=800`；再领一次 `threshold=500`。  
**预期** 第一次 200 且 `claimedTiers` 含 `tier_500`；800 为 `RECHARGE_NOT_REACHED`；第二次 500 为 `REWARD_ALREADY_CLAIMED`。  
**不应出现** 800 写入 `claimed_tiers`。

#### TC-PAY-04（新建）: 发奖 HTTP 失败后领取记录仍在

| 优先级 | P0 | 平台 | 服务端 | 关联 CR | CR-1 |
**前置** `GIFT_API_URL` 指向返回 500 的地址，100 档未领且金额已达到。  
**步骤** 在 `grant` 返回非 2xx 的时机领 100；再读库或再领一次。  
**预期** 第一次响应不是 200；行内已含 100。第二次不是再次发奖成功。  
**不应出现** 进度回滚后客户端可以再次把同一档发成功，同时又没有补偿记录。

### 7.4 测试陷阱与错误测法

- 把 `threshold` 放在 query。错误测法：地址栏 `?activity_id=2&threshold=300` 就认为领奖接口坏了。正确测法：`x-www-form-urlencoded` 正文。
- 用请求里的 `amount` 代表充值了多少。正确测法：改 `mock_payments.php` 后查 `totalRechargeGoods`。
- 用 `r200` 当签到身份。正确测法：使用 `join` 返回的 `activityUserToken`，库里的角色主键才是进度键。
- 活动过期日之后仍期望 200。正确测法：先看 `config/activities.php` 的 `ends_at`。
- 在发奖日志尚未出现时就断言「游戏内已到账」。本地 URL 为空时日志只表示请求体组装完成。

## 八、观测清单

| 场景 | 日志 |
|------|------|
| 发奖跳过 HTTP | `[gift] mock` |
| 发奖 HTTP 成功 | `[gift] sent` |
| 发奖 HTTP 失败 | `[gift] http failed` |
| 打卡拒绝 | `[checkin] reject` |
| 锁失败 | 响应 `RESOURCE_BUSY` 或 50000 类未捕获锁异常（等待时间为 0 时是业务 409） |

## 九、发版门禁

| 门禁项 | 结果 |
|--------|------|
| P0 需求匹配 | 选角、登录、签到查询、签到并领奖、累充查询、累充领奖对齐。月度充值入账为书面偏差（假流水），未签字接受 |
| P0 修复有用例 | 不通过。第七章全是草案，仓库无自动化 |
| CR-P0 open | 0 |
| 平台 E2E | 服务端用例未落盘，不通过 |
| 测试陷阱 | 7.4 已写，未同步 test-plan |
| 跨服务 | `GIFT_API_URL` 为空，不强制联调 |

**发版建议：需补测后发版。**

对「后端开发练习是否把这条链路做出来」：主链路达标，结构（控制器 / 服务 / 锁 / 唯一领取 / 发奖）可以讲清楚。  
对「把当前 `main` 交给他人当可运行交付」：未达标。至少补上 7.3 的 P0 用例或同等自动化，并处理 CR-1（真实发奖时）与 CR-3 的需求口径；CR-4 建议在交付分支去掉查询串 token。

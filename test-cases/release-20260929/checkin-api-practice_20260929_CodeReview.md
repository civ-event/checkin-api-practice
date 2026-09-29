# checkin-api-practice 20260929 代码审查报告

> 分支：`main`（与 `origin/main` 同提交 `8b5c9c0`）  
> 基线：`9971672`（上一份审查 `test-cases/release-20260928/` 的节点）  
> 本轮增量：`8b5c9c0` Record recharge from payment orders and add two more practice roles.  
> 日期：2026-09-29  
> 统计：`9971672...8b5c9c0` 约 32 文件，+415 / −46  
> 项目无 `.cursor/skills/project-test-profile.md`，使用默认：用例前缀 `TC`，对象为调用接口的客户端，平台为服务端。  
> 审查说明：无 PHPUnit。需求说明仍是口头范围（选角、登录、月度签到、月度累充、不能调用的用假数据）。相对远端无未推送提交，不能代替第七章验收。

## 审查概要（严重度计数）

| 级别 | 数量 | 说明 |
|------|------|------|
| 🔴 CR-P0 | 1 | Compose 把业务时间钉在 2026-10-10，原样发布会让所有「今天 / 当月」失真 |
| 🟡 CR-P1 | 3 | 发奖失败不可补发、客户端可任意入账、token 可进查询串 |
| 🟢 CR-P2 | 2 | 状态接口重复查角色、订单合计多余排序。路由注释已在提交前改成与入账一致 |
| ℹ️ | 1 | 前端正式路径没有充值入账，只有旧路径有 `record` |

**四态建议：需修复后发版。**  
若只在内网练习、不接真实游戏服、并在发布说明里写明容器时间被钉在 2026-10-10，可降为「可发版但需登记风险」。不能把当前 `docker-compose.yml` 当作线上配置原样发布。

上一轮 CR-3（`record` 的 amount 不入账）已由 `8b5c9c0` 关闭。活动窗口已从 9 月 30 日延到 10 月 31 日，但被下面的 CR-1 盖住，不能单独当成窗口问题已消失。

## 一、需求与实现匹配（含关联用例、覆盖状态）

| 需求 | 实现 | 关联用例 | 覆盖 |
|------|------|----------|------|
| 选角 | `POST /api-auth/activity/get-user-role-list`，假账号现有甲乙丙丁四个角色 | TC-AUTH-01 | ❌无落盘用例 |
| 登录 | `POST /api-auth/activity/join`，响应 `activityUserToken` | TC-AUTH-02 | ❌ |
| 月度签到进度 | `GET /api-front/activity/monthly-check-in/status`，键为角色 + 活动 + 年月 | TC-CHECKIN-01 | ❌ |
| 签到并发奖、含补签 | `POST .../monthly-check-in/clock-in`，每月补签 3 次，最多 31 档 | TC-CHECKIN-02 | ❌ |
| 查月度累充 | `GET .../monthly-cumulative-recharge/status`，字段 `totalRechargeGoods` / `roundEndAt` / `tiers` | TC-PAY-01 | ❌ |
| 记一笔充值 | `POST /api-front/activity/recharge/record` 写入 `recharge_payment_orders` 再合计。前端路径 `.../monthly-cumulative-recharge/` **没有** 这条 | TC-PAY-02 | ❌ |
| 领累充奖励 | `POST .../monthly-cumulative-recharge/claim`，档位 100/300/500/800/1000/2000 | TC-PAY-03 | ❌ |
| 不能调的外部系统用假数据 | 登录用 `config/accounts.php`；发奖在 `GIFT_API_URL` 为空时只打日志；初始充值订单来自 `config/mock_payments.php` | TC-API-02 | ❌ |
| 成功响应包一层 `{code,message,data}` | 已取消。数组原样 JSON | — | 与旧练习文档不一致，与当前前端契约一致 |

## 二、行为变更摘要

| 前（`9971672`） | 后（`8b5c9c0`） | 触发条件 |
|-----------------|-----------------|----------|
| `record` 的 `amount` 只校验，不改累计 | 插入一行 `recharge_payment_orders`，状态按当月订单合计覆盖 `total_amount` | `POST /recharge/record` 且 `amount >= 1` |
| 假流水直接盖总金额 | `mock_payments.php` 用 `INSERT IGNORE` 只导入一次 | 第一次查状态或领奖 |
| 同一订单号可重复加总 | 唯一键 `(role_id, order_id)`，合计时同一订单只算一次 | 同步或重复导入 |
| 容器时间跟随宿主机 | `LD_PRELOAD=libfaketime`，`FAKETIME=@2026-10-10 17:00:00` | 所有 `new DateTimeImmutable('now')` 和 `time()` |
| 活动结束 2026-09-30 | 结束改为 2026-10-31 | 签到、累充的 `RunningActivity::find` |
| 两个练习角色 | 增加角色丙 `r300/s3`、角色丁 `r400/s4` | 选角 |

## 三、问题与风险

### CR-1 业务时间被钉死在 2026-10-10

- 位置：`docker-compose.yml:20-22`，`docker/php/Dockerfile` 安装了 `libfaketime`
- 前置：用这份 Compose 启动 `app`。宿主机真实日期无关
- 影响：签到「今天」、年月、可签最大档、累充订单落在哪个月、活动是否在窗口内，全部按 2026-10-10 17:00 计算。10 月进度和 9 月进度不是同一行。过了真实 10 月 31 日，容器里活动仍在进行。提交说明写的是为了重复做换月检查，这是测试夹具，不是线上时钟
- 建议：默认 Compose 去掉 `LD_PRELOAD` / `FAKETIME`。换月检查用单独的 override 文件
- 验证：容器内 `date` 必须是 2026-10-10，而不是宿主机当天。去掉这两行环境变量后重启，`date` 与宿主机同一天，签到 `yearMonth` 跟着变
- 关联：TC-API-01
- 严重度：🔴 CR-P0（作为上线配置）。练习演示可保留，但必须写进发布说明

### CR-2 发奖失败发生在进度提交之后

- 位置：`src/Service/RechargeService.php:147-152`，`src/Service/CheckInService.php:226-228`，`src/Gift/HttpGiftClient.php:46-54`
- 前置：`GIFT_API_URL` 非空，游戏服非 2xx 或超时
- 影响：档位或签到天已提交。接口 500。重试得到已签或已领，礼物不会再发。默认 URL 为空时只打 `[gift] mock`，不触发
- 建议：上线前保持 URL 为空，或增加补偿表。不要把「已记账」和「已到账」说成同一件事
- 验证：URL 指向必定 500 的地址，领 100 档；库中已领，响应 500；再次领取不是第二次发奖
- 关联：TC-PAY-04、TC-CHECKIN-03
- 严重度：🟡 CR-P1

### CR-3 登录用户可以任意写入充值金额

- 位置：`src/Service/RechargeService.php:85-94`、`189-199`
- 前置：持有 `activity-user-token`，`POST /api-front/activity/recharge/record`，`amount` 为正整数。没有支付验签，也没有单笔上限
- 影响：一笔 `amount=2000` 即可把当月累计推到最高档，随后 `claim` 把礼物记为已领。练习里这是「客户端记账」。接到真实发奖后就是资金入口
- 建议：线上入账只接受支付回调或带订单签名的同步。练习路径保留，但不要挂到对公网开放的活动域名
- 验证：同一角色连续 `record` amount=1 与 amount=2000，`totalRechargeGoods` 增加 2001，而不是保持假流水原值
- 关联：TC-PAY-02、TC-PAY-05
- 严重度：🟡 CR-P1（练习内测）/ 接到真实发奖时升为 🔴

### CR-4 活动 token 可以放在查询串

- 位置：`src/Auth/LoginContext.php:25-28`
- 前置：请求带 `?activity_user_token=`
- 影响：token 进入访问日志、代理日志和浏览器历史。请求头 `activity-user-token` 仍是主路径
- 建议：上线只接受请求头
- 验证：只带查询参数、不带请求头，当前会 200。去掉查询参数分支后应 401
- 关联：TC-AUTH-03
- 严重度：🟡 CR-P1

### CR-5 冗余：状态接口对同一角色查两次服务器

- 位置：`src/Service/RechargeService.php:170-173` 调用 `gameRole()`，随后 `currentYearMonth()` → `serverTimezoneForRole()`（`266-273`）
- 前置：每次累充状态、入账、领奖
- 等价性依据：两次 SQL 都是 `activity_user_role.id = ?`，第二次只为了 `server_id`，而 `gameRole()` 已经返回了 `server_id`
- 性能影响：热路径每次多一次往返
- 建议：`currentYearMonth` 直接用已取出的 `server_id`
- 验证：打开 SQL 日志，一次 status 里对 `activity_user_role` 的主键查询应从两次变为一次，响应字段不变
- 关联：无单独用例
- 严重度：🟢 CR-P2

### CR-6 冗余：订单合计的排序和 PHP 去重

- 位置：`src/Service/RechargeService.php:221-238`
- 前置：`009` 已加上唯一键 `uniq_role_order (role_id, order_id)`
- 等价性依据：同一角色不会再有两行相同 `order_id`。`ORDER BY id` 和 `$seen` 去掉后，当月合计不变
- 性能影响：按角色拉出全部历史订单再在 PHP 里滤月份，订单变多后每次状态都全表扫描该角色。月份过滤本身不是多余的，缺的是 SQL 按 `send_time` 落月
- 建议：去掉 `ORDER BY` 和 `$seen`。月份条件留在查询里或继续在 PHP 过滤，但不要为了去重排序
- 验证：有唯一键时，改前改后 `totalRechargeGoods` 相同
- 关联：无
- 严重度：🟢 CR-P2（订单量大时升为 P1）

### CR-7 路由注释与入账行为相反（提交前已关闭）

- 位置：`config/routes.yml` 的 `record` 路由
- 审查时：注释写「请求里的 amount 不累加」，代码会插入订单并增加当月合计
- 当前注释：amount 写入 `recharge_payment_orders`，再按该角色当月订单合计。`mock_payments.php` 里同一订单号只导入一次
- 状态接口注释也已改掉「按 mock 流水覆盖当月金额」。实现是种子订单 `INSERT IGNORE` 一次，再按订单表重算
- 关联：TC-PAY-02
- 严重度：🟢 CR-P2，注释已与 `RechargeService::record` 一致

## 三-附、AI/冗余与无效性能损耗

见 CR-5、CR-6。未发现恒真 SQL 条件或未使用的 JOIN。`ORDER BY id` 在唯一键存在时不改变合计，属于可删除的无效排序。

## 四、用户操作场景分析

| 场景 | 操作时机 | 预期 | 不应出现 |
|------|----------|------|----------|
| 先入账再查状态 | `record` 成功返回之后立刻 `status` | 两次 `totalRechargeGoods` 相同，且包含这笔 amount | 第二次又加一遍同一 `order_id` |
| 领奖后发奖失败 | 唯一领取行已插入，`HttpGiftClient` 抛错之前 | HTTP 500，库中该档已领 | 重试再次插入礼物或把档位改回未领 |
| 同一秒连点领取 | 两请求都已进入 `claim`，锁等待为 0 | 一个成功，另一个 `RESOURCE_BUSY` 或已领 | 两笔礼物 |
| 换月 | 容器时间停在 10 月 10 日；9 月的签到行还在 | 10 月是新的 `yearMonth`，进度从空开始 | 把 9 月已签天数加到 10 月 |
| 活动 id 传错 | 签到传 `2`，累充传 `1` | `ACTIVITY_NOT_FOUND` | 把累充金额写进签到活动 |

## 五、模块变更概览

| 模块 | 变更 | 风险 |
|------|------|------|
| 累充入账 | 订单表 + 客户端插入 + 种子只导入一次 | 任意金额；正式前端路径没有 record |
| 时钟 | libfaketime 固定 2026-10-10 | 上线时钟错误 |
| 账号 | 丙、丁 | 无假支付，当月金额为 0，直到 record |
| 活动窗口 | 延到 2026-10-31 | 被固定时钟掩盖 |
| 迁移 | `008` 建订单表，`009` 删重复行并改唯一键 | 已有库必须跑到 `009`，否则旧的「订单号+金额+时间」唯一键仍允许同号不同金额的重复行 |

## 六、各 commit 审查明细

### 9971672（上一轮已审，本轮只核对是否仍成立）

前端路径、`activity-user-token`、响应不再套 `code/message/data` 仍在。发奖后置、查询串 token 仍在。`record` 不入账这一条已被下一提交替换。

### 8b5c9c0

- 入账行为与提交说明一致：订单落表，合计来自订单，种子只导入一次。
- 审查时 `mock_payments.php` 把角色乙写成 `Etc/GMT+5`。提交前已改为甲在 s1（Etc/GMT+5）、乙在 s2（Asia/Shanghai），与 `config/game.php` 一致。种子 `send_time` 在这两个时区都落在 2026-09。
- `FAKETIME` 与「把窗口延到 10 月」放在同一提交，换月演示可以重复，但默认启动不再代表真实日期。

## 七、测试覆盖与缺口

仓库内仍无自动化测试。上一份审查的用例仍是草案，没有 ✅。

### 7.1 需求-用例矩阵

| 需求 | 用例 | 状态 |
|------|------|------|
| 选角四个角色 | TC-AUTH-01 | ❌ |
| 登录拿 token | TC-AUTH-02 | ❌ |
| 查询串 token | TC-AUTH-03 | ❌ |
| 签到状态 / 打卡 / 补签用尽 | TC-CHECKIN-01、02、03 | ❌ |
| 累充状态含种子金额 | TC-PAY-01 | ❌ |
| record 增加合计且同订单不双计 | TC-PAY-02 | ❌ |
| 未达档拒绝、达到后领取 | TC-PAY-03 | ❌ |
| 发奖 HTTP 失败不二次发放 | TC-PAY-04 | ❌ |
| 超大 amount | TC-PAY-05 | ❌ |
| 容器时间与活动窗口 | TC-API-01 | ❌ |

### 7.2 P0 必测清单

| 类型 | 项 | 判据 |
|------|----|------|
| Happy | 角色乙登录后查累充 | 在容器时间 2026-10-10 下，种子订单若落在 10 月则进入合计；若仍落在 9 月则本月为 0。以响应为准，不要沿用「乙一定是 600」 |
| Happy | `record` amount=100 后再 status | 合计比调用前多 100 |
| Negative | 未达档领取 | `RECHARGE_NOT_REACHED`，无新唯一领取行 |
| Negative | 签到 activity_id=2 | `ACTIVITY_NOT_FOUND` |
| 时序 | 领奖成功后立刻再领 | 已领，不是再发一次 |
| 竞态 | 同一档并发 claim | 至多一次成功 |
| Negative | 去掉请求头只留查询串 token | 当前会通过；若修 CR-4 则必须 401 |

### 7.3 待新增用例草案

#### TC-PAY-02（新建）: 客户端入账后当月累计增加且同订单只算一次

| 优先级 | P0 | 平台 | 服务端 | 关联 CR | CR-3、CR-7 |

**前置** 已登录角色丙（无种子订单）。容器时间为 2026-10-10。  
**步骤** `POST /api-front/activity/recharge/record`，body `activity_id=2`、`amount=100`。再调一次 status。查 `recharge_payment_orders` 该 `order_id` 只有一行。  
**预期** `totalRechargeGoods` 为 100。  
**不应出现** 第二次 status 变成 200。

#### TC-API-01（新建）: 容器业务时间固定在 2026-10-10

| 优先级 | P0 | 平台 | 服务端 | 关联 CR | CR-1 |

**前置** `docker compose up` 使用仓库里的 compose。  
**步骤** 进入 app 容器执行 `date`。再查签到状态的 `yearMonth`。  
**预期** 日期是 2026-10-10，`yearMonth` 为 202610。  
**不应出现** 与宿主机当天相同却仍声称已经对齐线上时钟。

#### TC-PAY-04（新建）: 发奖失败后重试不再发奖

| 优先级 | P0 | 平台 | 服务端 | 关联 CR | CR-2 |

**前置** `GIFT_API_URL` 指向返回 500 的地址。角色累计已达到 100。  
**步骤** claim threshold=100。再 claim 一次。  
**预期** 第一次 500 且库中已领。第二次拒绝。日志有 `[gift] http failed`，没有第二次 `[gift] sent`。  
**不应出现** 档位回到未领。

### 7.4 测试陷阱与错误测法

- 不要用宿主机「今天是 9 月 29 日」判断签到第几天。容器里是 2026-10-10，9 月进度不会出现在本次 status。
- 不要再预期成功体为 `{code:0,message,data}`。当前是控制器数组原样 JSON。
- 不要用「角色乙合计一定是 600」做断言。种子 `send_time` 在 `game.php` 的两个服务器时区都落在 2026-09，容器停在 10 月时不进当月合计。注释已与 `game.php` 一致。
- 前端集合若只挂了 `monthly-cumulative-recharge`，调用不到 `record`。入账要打 `/api-front/activity/recharge/record`。
- 旧库若只跑过 `008`、没跑 `009`，同订单号不同金额仍可能各插一行。
- 探活 `/`、`/memcached`、`/lock`、`/db` 是纯文本，不是 JSON。
- 默认对外端口以 `.env` 的 `HTTP_PORT` 为准；compose 缺省是 8080，不是以前的 18080。

## 八、观测清单

| 信号 | 含义 |
|------|------|
| `[gift] mock` | URL 为空，未请求游戏服 |
| `[gift] http failed` | 进度已提交，发奖失败 |
| `[gift] sent` | 游戏服 2xx |
| `[checkin] reject` | 签到业务拒绝，进度未写 |
| `ACTIVITY_NOT_FOUND` | 活动类型不对、未开放，或不在 2026-09-01～2026-10-31 |
| 容器 `date` | 必须先确认是不是 2026-10-10，再解释任何「今天」 |

## 九、发版门禁

| 门禁项 | 结果 |
|--------|------|
| P0 需求匹配 | 选角、登录、签到、累充查询和领取已实现。入账只在旧路径。假登录、假发奖与「不能调用就用假数据」一致 |
| P0 修复有用例 | 7.2 仅有草案，没有 ✅ |
| CR-P0 open | 1（CR-1 固定时钟） |
| 平台 E2E | 服务端单端，无已执行的 E2E 记录 |
| 测试陷阱 | 7.4 已写，尚未同步独立 test-plan |
| 跨服务联调 | 无 profile，未声明关联仓库。真实发奖未联调 |

**建议：需修复后发版。**

上线前至少要做：从默认 Compose 去掉 `FAKETIME`，或单独说明这是练习时钟；确认目标环境的 `GIFT_API_URL` 为空或已有补发方案；`record` 不对公网开放。做完后仍须按 7.2 补测，才能改成「可发版但需登记风险」。

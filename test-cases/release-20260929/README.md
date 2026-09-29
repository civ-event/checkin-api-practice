# checkin-api-practice 20260929 测试用例

对照审查 `checkin-api-practice_20260929_CodeReview.md` 第七章补齐。编号沿用审查里的 `TC-*`。项目没有 `project-test-profile.md`，前缀用审查默认的 `TC`，平台是服务端。

审查当时写「仓库内无自动化测试」。其后已有 PHPUnit，覆盖关系写在对应用例的备注和 `test-plan.html` 的「自动化」列。手工结果仍全部是未执行。

## 统计

| 优先级 | 条数 |
|--------|------|
| P0 | 17 |
| P1 | 4 |
| P2 | 2 |
| 合计 | 23 |

P0 含端到端 1 条，以及 CR-1 的四类：`TC-API-01` Happy、`TC-API-01b` 时序、`TC-API-01c` 竞态（N/A）、`TC-API-01d` Negative。

## 发版门禁摘要

| 门禁项 | 用例落盘后的状态 |
|--------|------------------|
| P0 需求有用例 | 选角、登录、签到、累充查询、入账、领取、假数据都有 MD |
| P0 修复有用例 | CR-1 四类已写。尚未执行，不能改成 ✅ |
| CR-P0 | CR-1 仍开放。练习环境按决定保留 `FAKETIME` |
| 平台 E2E | `TC-E2E-01` 已写，未执行 |
| 测试陷阱 | 7.4 已写入 `test-plan.html` 和相关用例备注 |
| 跨服务 | 无关联仓库。真实发奖未联调 |

门禁结论不变：练习可继续；当作线上配置仍是「需修复后发版」。

## CR 覆盖闭合

| CR 条目 | plan 有行 | MD |
|---------|-----------|----|
| 7.1 选角 TC-AUTH-01 | 有 | 有 |
| 7.1 登录 TC-AUTH-02 | 有 | 有 |
| 7.1 查询串 TC-AUTH-03 | 有 | 有 |
| 7.1 签到 TC-CHECKIN-01、02、03 | 有 | 有。补签用尽是 03 |
| 7.1 累充状态 TC-PAY-01 | 有 | 有 |
| 7.1 入账 TC-PAY-02 | 有 | 有 |
| 7.1 领取 TC-PAY-03 | 有 | 有 |
| 7.1 发奖失败 TC-PAY-04 | 有 | 有 |
| 7.1 超大金额 TC-PAY-05 | 有 | 有 |
| 7.1 容器时间 TC-API-01 | 有 | 有 |
| 7.2 Happy 乙查累充 | TC-PAY-01 | 有 |
| 7.2 Happy record +100 | TC-PAY-02 | 有 |
| 7.2 Negative 未达档 | TC-PAY-03 | 有 |
| 7.2 Negative 签到 activity_id=2 | TC-CHECKIN-05 | 有 |
| 7.2 时序再领 | TC-PAY-03 | 有 |
| 7.2 竞态并发 claim | TC-PAY-06 | 有 |
| 7.2 Negative 只留查询串 | TC-AUTH-03 | 有 |
| 7.3 TC-PAY-02 | 有 | 有 |
| 7.3 TC-API-01 | 有 | 有。预期改为「@ 起始后继续走」，不断言永远停在 10 日 |
| 7.3 TC-PAY-04 | 有 | 有 |
| 7.2 四类之 CR-1 | 01 / 01b / 01c / 01d | 竞态 MD 标明 N/A |
| CR-2 签到侧 | TC-CHECKIN-04 | 审查正文写成 TC-CHECKIN-03，与 7.1 补签用尽冲突，签到发奖失败改编号为 04 |
| 第一章 TC-API-02 假数据 | 有 | 有 |
| 7.4 每条陷阱 | plan 陷阱表 | 写入 PAY-01、PAY-02、API-01、API-01d、REG-01、REG-02、E2E 备注 |

## 目录

- 用例：`test-cases/TC-*.md`
- 执行计划：`test-plan.html`
- 审查：`checkin-api-practice_20260929_CodeReview.md`

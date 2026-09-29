# checkin-api-practice

签到与月度充值练习。HTTP 入口是公司的 SlimApp（`oasis/slimapp`），进度在 MySQL，锁用 Memcached。

## 快速开始

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php scripts/pdo_migrate.php
curl -s http://localhost:18080/
```

`curl /` 的正文是纯文本 `slimapp ok`。默认端口是 `18080`。

`pdo_migrate.php` 按文件名执行 `scripts/sql` 下的全部脚本。已经记入 `schema_migrations` 的会跳过，所以可以重复执行。

## 常用验收

角色乙登录。JWT 里的 `role_id` 是角色表主键，不是游戏角色字符串 `r200`。响应里的 token 字段是 `activityUserToken`。

```bash
TOKEN=$(curl -s -X POST http://localhost:18080/api-auth/activity/join \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'accessToken=token-player-1001&serverId=s2&roleId=r200' \
  | php -r 'echo json_decode(stream_get_contents(STDIN))->activityUserToken;')
```

成功响应没有 `{code, message, data}`，正文就是接口数据。错误是 `{code, exception}`。发奖日志在 `docker compose logs app` 里搜 `[gift] sent`。

```bash
curl -s 'http://localhost:18080/api-front/activity/monthly-check-in/status?activity_id=1' \
  -H "activity-user-token: $TOKEN"

curl -s -X POST http://localhost:18080/api-front/activity/monthly-check-in/clock-in \
  -H "activity-user-token: $TOKEN" \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'activity_id=1&check_day=1'
```

累充活动 id 来自请求，类型必须是 `monthly_cumulative_recharge`。`/api-front/activity/recharge/record` 是练习用的充值入账，前端没有这个地址。它把一笔订单写入 `recharge_payment_orders`，再按当月订单重算累充合计。`config/mock_payments.php` 只导入一次，作为初始订单。

```bash
curl -s 'http://localhost:18080/api-front/activity/monthly-cumulative-recharge/status?activity_id=2' \
  -H "activity-user-token: $TOKEN"
```

## 学习进度

1. [x] Docker Compose 本地环境
2. [x] SlimApp 路由、统一 JSON、业务错误码
3. [x] PDO 签到表读写
4. [x] Doctrine Entity / Repository
5. [x] 分层架构
6. [x] GET status（每次查 MySQL）
7. [x] POST clock-in（记签到；已签的天状态是 claimed）
8. [x] 校验 / 异常 / 日志
9. [x] Memcached 分布式锁（`Checkin\Common\Lock`，add 抢锁，CAS 释放）
10. [x] status 每次查 MySQL。`docs/cache-problems.md` 是去掉缓存之前的笔记
11. [x] 发奖先写唯一领取记录，再由 `HttpGiftClient` 请求游戏服（签到在 `clockIn`，充值在 `RechargeService::claim`）
12. [x] 登录、获取角色（`config/accounts.php` 是假账号）
13. [x] 按角色、按月签到
14. [x] 月度充值：记账、查进度、按档领取
15. [x] `HttpGiftClient`。`GIFT_API_URL` 为空时只记日志；有地址时 POST 游戏服。成功表示领取记录已落库且请求已发出，不等于游戏内一定到账。发奖失败不回滚进度。

# checkin-api-practice

签到接口后端学习实战：Slim + PDO/Doctrine + Redis + Docker Compose。

## 快速开始

```bash
cp .env.example .env
docker compose up -d --build
curl -s http://localhost:8080/health
```

## 服务

| 服务 | 端口 | 说明 |
|------|------|------|
| nginx + php | 8080 | HTTP API |
| mysql | 3306 | 业务库 |
| redis | 6379 | 锁 / 缓存 |

## 学习进度

1. [x] Docker Compose 本地环境
2. [ ] Slim 路由 + JSON + CORS
3. [ ] PDO 签到表读写
4. [ ] Doctrine Entity / Repository
5. [ ] 分层架构
6. [ ] GET status
7. [ ] POST clock-in
8. [ ] 校验 / 异常 / 日志
9. [ ] Redis 分布式锁
10. [ ] status 缓存（可选）
11. [ ] 真实发奖（可选）

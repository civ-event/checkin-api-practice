<?php

declare(strict_types=1);

/**
 * 容器内连通性自检（学习用，后续可删）。
 *
 * 用法（在 app 容器内）:
 *   php scripts/healthcheck.php
 */

$checks = [];

$checks['php'] = PHP_VERSION;

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('MYSQL_HOST') ?: 'mysql',
        getenv('MYSQL_PORT') ?: '3306',
        getenv('MYSQL_DATABASE') ?: 'checkin',
    );
    $pdo = new PDO(
        $dsn,
        getenv('MYSQL_USER') ?: 'checkin',
        getenv('MYSQL_PASSWORD') ?: 'checkin',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $checks['mysql'] = $pdo->query('SELECT 1')->fetchColumn() === '1' ? 'ok' : 'fail';
} catch (Throwable $e) {
    $checks['mysql'] = 'fail: ' . $e->getMessage();
}

try {
    if (!class_exists('Redis')) {
        throw new RuntimeException('ext-redis not loaded');
    }
    $redis = new Redis();
    $host = getenv('REDIS_HOST') ?: 'redis';
    $port = (int) (getenv('REDIS_PORT') ?: 6379);
    if (!$redis->connect($host, $port, 2.0)) {
        throw new RuntimeException('connect failed');
    }
    $checks['redis'] = $redis->ping() ? 'ok' : 'fail';
} catch (Throwable $e) {
    $checks['redis'] = 'fail: ' . $e->getMessage();
}

echo json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(str_contains(json_encode($checks), 'fail') ? 1 : 0);

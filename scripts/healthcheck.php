<?php // PHP 起始

declare(strict_types=1); // 严格类型

/**
 * 容器内连通性自检（学习用，后续可删）。
 *
 * 用法（在 app 容器内）:
 *   php scripts/healthcheck.php
 */

$checks = []; // 收集各项检查结果

$checks['php'] = PHP_VERSION; // 记录 PHP 版本

try {
    $dsn = sprintf( // 拼 MySQL DSN
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('MYSQL_HOST') ?: 'mysql',
        getenv('MYSQL_PORT') ?: '3306',
        getenv('MYSQL_DATABASE') ?: 'checkin',
    );
    $pdo = new PDO(
        $dsn,
        getenv('MYSQL_USER') ?: 'checkin',
        getenv('MYSQL_PASSWORD') ?: 'checkin',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION], // 失败抛异常进 catch
    );
    // SELECT 1 成功且结果为 '1' 则 ok
    $checks['mysql'] = $pdo->query('SELECT 1')->fetchColumn() === '1' ? 'ok' : 'fail';
} catch (Throwable $e) {
    $checks['mysql'] = 'fail: ' . $e->getMessage(); // 记下失败原因
}

try {
    if (!class_exists(Memcached::class)) {
        throw new RuntimeException('ext-memcached not loaded');
    }
    $memcached = new Memcached();
    $host = getenv('MEMCACHED_HOST') ?: 'memcached';
    $port = (int) (getenv('MEMCACHED_PORT') ?: 11211);
    $memcached->addServer($host, $port);
    $stats = $memcached->getStats();
    $checks['memcached'] = is_array($stats) && $stats !== [] ? 'ok' : 'fail';
} catch (Throwable $e) {
    $checks['memcached'] = 'fail: ' . $e->getMessage();
}

echo json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL; // 输出结果
exit(str_contains(json_encode($checks), 'fail') ? 1 : 0); // 任一项 fail 则进程退出码 1

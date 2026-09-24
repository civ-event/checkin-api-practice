<?php // PHP 起始

declare(strict_types=1); // 严格类型

/**
 * 返回已配置好的 PDO（ERRMODE 异常 + 真预处理）。
 * 被 pdo_migrate / pdo_checkin_demo 共用。
 */
function pdo_connect(): PDO
{
    $dsn = sprintf( // Data Source Name：驱动与连接信息
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('MYSQL_HOST') ?: 'mysql', // 主机，Docker 内默认服务名 mysql
        getenv('MYSQL_PORT') ?: '3306',
        getenv('MYSQL_DATABASE') ?: 'checkin',
    );

    return new PDO(
        $dsn,
        getenv('MYSQL_USER') ?: 'checkin', // 用户名
        getenv('MYSQL_PASSWORD') ?: 'checkin', // 密码（学习默认值；生产勿硬编码）
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // SQL 错误抛异常，而不是静默 false
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // fetch 默认关联数组
            PDO::ATTR_EMULATE_PREPARES => false, // 关闭模拟预处理，用服务端真预处理
        ],
    );
}

<?php // PHP 起始

declare(strict_types=1); // 严格类型

/**
 * 执行建表 SQL。
 *
 *   docker compose exec app php scripts/pdo_migrate.php
 */

require __DIR__ . '/pdo_bootstrap.php'; // 引入 pdo_connect()

$pdo = pdo_connect(); // 连接 MySQL
$sql = file_get_contents(__DIR__ . '/sql/001_daily_check_in_user_data.sql'); // 读出建表脚本全文
if ($sql === false) { // 读文件失败
    fwrite(STDERR, "cannot read migration sql\n");
    exit(1);
}

$pdo->exec($sql); // 直接执行（本脚本是固定 SQL，无用户输入）
echo "migrated: daily_check_in_user_data\n"; // 成功提示

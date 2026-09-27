<?php // PHP 起始

declare(strict_types=1); // 严格类型

/**
 * 按文件名顺序执行 scripts/sql 下的全部建表脚本。
 * 已记入 schema_migrations 的文件会跳过，所以可以重复执行。
 *
 *   docker compose exec app php scripts/pdo_migrate.php
 */

require __DIR__ . '/pdo_bootstrap.php'; // 引入 pdo_connect()

$pdo = pdo_connect(); // 连接 MySQL
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        filename VARCHAR(255) NOT NULL,
        applied_at INT NOT NULL,
        PRIMARY KEY (filename)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$files = glob(__DIR__ . '/sql/*.sql');
if ($files === false || $files === []) {
    fwrite(STDERR, "no migration sql\n");
    exit(1);
}
sort($files, SORT_STRING);

$applied = $pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$applied = is_array($applied) ? $applied : [];

$insert = $pdo->prepare('INSERT INTO schema_migrations (filename, applied_at) VALUES (?, ?)');

foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        echo "skipped: {$name}\n";
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "cannot read {$name}\n");
        exit(1);
    }

    foreach (sql_statements($sql) as $statement) {
        $pdo->exec($statement);
    }

    $insert->execute([$name, time()]);
    echo "migrated: {$name}\n";
}

/**
 * 去掉行注释后按分号拆开。这些脚本里没有字符串中的分号。
 *
 * @return list<string>
 */
function sql_statements(string $sql): array
{
    $sql = preg_replace('/--.*$/m', '', $sql) ?? $sql;
    $parts = preg_split('/;/', $sql) ?: [];
    $statements = [];
    foreach ($parts as $part) {
        $statement = trim($part);
        if ($statement !== '') {
            $statements[] = $statement;
        }
    }

    return $statements;
}

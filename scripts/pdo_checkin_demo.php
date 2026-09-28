<?php // PHP 起始

declare(strict_types=1); // 严格类型

/**
 * PDO 预处理读写练习（第 3 项）。
 *
 *   docker compose exec app php scripts/pdo_checkin_demo.php
 *
 * 演示：
 * 1. 预处理 SELECT
 * 2. 无记录则 INSERT
 * 3. 事务内 UPDATE 签到天
 * 4. 再 SELECT 校验
 *
 * 对比（切勿用于生产）：字符串拼接 SQL 的危险写法见文件底部注释。
 */

require __DIR__ . '/pdo_bootstrap.php'; // 引入 pdo_connect()

$roleId = 1001; // 演示用角色 ID
$activityId = 1; // 演示用活动 ID
$checkDay = 1; // 要签的天
$now = time(); // 当前时间戳，写入 created/updated/last_check
// 进度键含年月。与签到服务一样用游戏时区 Etc/GMT+5
$yearMonth = (int) (new DateTimeImmutable('now', new DateTimeZone('Etc/GMT+5')))->format('Ym');

$pdo = pdo_connect(); // 拿到已配置的 PDO

// 1) 预处理查询：占位符防注入。一行进度是角色 + 活动 + 年月
$select = $pdo->prepare(
    'SELECT id, user_role_primary_id, activity_id, `year_month`, checked_days, total_checked, last_check_time
     FROM daily_check_in_user_data
     WHERE user_role_primary_id = :role_id AND activity_id = :activity_id AND `year_month` = :year_month
     LIMIT 1'
);
$select->execute([ // 绑定参数并执行
    ':role_id' => $roleId,
    ':activity_id' => $activityId,
    ':year_month' => $yearMonth,
]);
$row = $select->fetch(); // 取一行关联数组；没有则为 false

if ($row === false) {
    // 2) 无记录则 INSERT（checked_days 先空数组 JSON）
    $insert = $pdo->prepare(
        'INSERT INTO daily_check_in_user_data
            (user_role_primary_id, activity_id, `year_month`, checked_days, total_checked, last_check_time, created_at, updated_at)
         VALUES
            (:role_id, :activity_id, :year_month, :checked_days, 0, 0, :created_at, :updated_at)'
    );
    $insert->execute([
        ':role_id' => $roleId,
        ':activity_id' => $activityId,
        ':year_month' => $yearMonth,
        ':checked_days' => '[]', // JSON 空数组字符串
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    echo "inserted empty progress for role={$roleId} activity={$activityId} year_month={$yearMonth}\n";

    $select->execute([ // 插入后再查一遍拿到 id
        ':role_id' => $roleId,
        ':activity_id' => $activityId,
        ':year_month' => $yearMonth,
    ]);
    $row = $select->fetch();
}

if ($row === false) { // 仍没有：异常退出
    fwrite(STDERR, "row still missing after insert\n");
    exit(1);
}

/** @var list<int> $checkedDays */
$checkedDays = json_decode((string) $row['checked_days'], true, 512, JSON_THROW_ON_ERROR); // JSON → PHP 数组
if (!is_array($checkedDays)) {
    $checkedDays = []; // 兜底
}

if (in_array($checkDay, $checkedDays, true)) {
    echo "day {$checkDay} already checked, skip update\n"; // 已签则跳过
} else {
    // 3) 事务：读-改-写，失败则回滚
    $pdo->beginTransaction();
    try {
        $checkedDays[] = $checkDay; // 追加当天
        sort($checkedDays); // 排序
        $checkedDays = array_values(array_unique($checkedDays)); // 去重并重置下标

        $update = $pdo->prepare(
            'UPDATE daily_check_in_user_data
             SET checked_days = :checked_days,
                 total_checked = :total_checked,
                 last_check_time = :last_check_time,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $update->execute([
            ':checked_days' => json_encode($checkedDays, JSON_THROW_ON_ERROR), // 数组 → JSON
            ':total_checked' => count($checkedDays),
            ':last_check_time' => $now,
            ':updated_at' => $now,
            ':id' => (int) $row['id'], // 按主键更新
        ]);
        $pdo->commit(); // 提交事务
        echo "checked day {$checkDay}\n";
    } catch (Throwable $e) {
        $pdo->rollBack(); // 出错回滚
        throw $e; // 继续抛出
    }
}

// 4) 再查一次确认最终状态
$select->execute([
    ':role_id' => $roleId,
    ':activity_id' => $activityId,
    ':year_month' => $yearMonth,
]);
$final = $select->fetch();
echo json_encode($final, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL; // 漂亮打印

/*
 * ❌ 危险示例（学习对照，不要执行）：
 *
 *   $sql = "SELECT * FROM daily_check_in_user_data WHERE user_role_primary_id = {$roleId}";
 *   // 若 $roleId 来自用户输入，可被改写成注入语句。
 *
 * ✅ 正确做法：始终用 prepare + 绑定参数（如上）。
 */

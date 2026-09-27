<?php // PHP 起始

declare(strict_types=1); // 严格类型

/**
 * Doctrine ORM 读写练习（第 4 项）。
 *
 *   docker compose exec app composer update
 *   docker compose exec app php scripts/doctrine_checkin_demo.php
 *
 * 使用 role=1002，避免与 PDO 演示的 role=1001 数据互相干扰。
 *
 * 笔记：
 * - 主路径（单条用户进度 CRUD）用 ORM
 * - 批量统计 / 复杂报表再用原生 SQL
 */

use Checkin\Config\GameClock;
use Checkin\Database\DoctrineFactory; // EM 工厂
use Checkin\Entities\DailyCheckInUserData; // 实体类
use Checkin\Entities\Repositories\DailyCheckInUserDataRepository; // 仓储类型

require dirname(__DIR__) . '/vendor/autoload.php'; // Composer 自动加载

$roleId = 1002; // 与 PDO demo 区分的角色
$activityId = 1;
$checkDay = 1;
$timezone = (new GameClock())->gameTimezone()->getName(); // 和签到一样用游戏时区判断「今天」

$em = DoctrineFactory::createEntityManager(); // 创建 EntityManager

/** @var DailyCheckInUserDataRepository $repo */
$repo = $em->getRepository(DailyCheckInUserData::class); // 取自定义仓储

$userData = $repo->findOrCreate($roleId, $activityId); // 查或内存新建

if ($userData->isDayChecked($checkDay)) {
    echo "day {$checkDay} already checked via ORM\n"; // 已签
} else {
    $userData->markDay($checkDay); // 实体内改字段
    $repo->save($userData); // persist + flush
    echo "checked day {$checkDay} via ORM\n";
}

// 清掉 EM 一级缓存再读，证明确实落库而不是只读内存
$em->clear();

/** @var DailyCheckInUserDataRepository $repo */
$repo = $em->getRepository(DailyCheckInUserData::class); // clear 后需重新取
$reloaded = $repo->findOrCreate($roleId, $activityId); // 应从 DB 加载

echo json_encode([ // 打印关键字段便于人工核对
    'id' => $reloaded->getId(),
    'userRolePrimaryId' => $reloaded->getUserRolePrimaryId(),
    'activityId' => $reloaded->getActivityId(),
    'checkedDays' => $reloaded->getCheckedDays(),
    'totalChecked' => $reloaded->getTotalChecked(),
    'lastCheckTime' => $reloaded->getLastCheckTime(),
    'isCheckedToday' => $reloaded->isCheckedToday($timezone),
    'isDayChecked_1' => $reloaded->isDayChecked(1),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

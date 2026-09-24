<?php // PHP 起始

declare(strict_types=1); // 严格类型

namespace Checkin\Entities\Repositories; // 仓储命名空间

use Checkin\Entities\DailyCheckInUserData; // 实体
use Doctrine\ORM\EntityRepository; // Doctrine 泛型仓储基类

/**
 * @extends EntityRepository<DailyCheckInUserData>
 * 封装「按角色+活动找进度 / 没有就 new」以及 save
 */
class DailyCheckInUserDataRepository extends EntityRepository
{
    /** 查已有进度；没有则返回未 persist 的新实体（调用方再 save） */
    public function findOrCreate(int $userRolePrimaryId, int $activityId, int $yearMonth): DailyCheckInUserData
    {
        $data = $this->findOneBy([
            'userRolePrimaryId' => $userRolePrimaryId,
            'activityId' => $activityId,
            'yearMonth' => $yearMonth,
        ]);

        if ($data !== null) {
            return $data;
        }

        $now = time();
        $data = new DailyCheckInUserData();
        $data->setUserRolePrimaryId($userRolePrimaryId);
        $data->setActivityId($activityId);
        $data->setYearMonth($yearMonth);
        $data->setCreatedAt($now);
        $data->setUpdatedAt($now);

        return $data;
    }

    /** persist + flush 落库；顺带刷新 updated_at */
    public function save(DailyCheckInUserData $data): void
    {
        $data->setUpdatedAt(time());
        $em = $this->getEntityManager(); // 拿到 EntityManager
        $em->persist($data); // 纳入管理（insert 或 update）
        $em->flush(); // 立刻写出到数据库
    }
}

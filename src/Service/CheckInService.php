<?php // PHP 文件起始标记

declare(strict_types=1); // 开启严格类型：参数/返回值类型不匹配会直接报错

namespace Checkin\Service; // 本类所属命名空间（对应 src/Service）

use Checkin\Common\ErrorCode; // 业务错误码常量
use Checkin\Config\DailyCheckInConfig; // 签到天数与礼物配置
use Checkin\Entities\Repositories\DailyCheckInUserDataRepository; // 用户签到进度仓储
use Checkin\Exception\BusinessException; // 可转成 JSON 业务错误的异常
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Checkin\Gift\GiftGrantClientInterface;
use Checkin\Gift\GiftPayload;
use Checkin\Config\RunningActivity;

/**
 * 进度键是角色主键 + 请求里的活动 id + YYYYMM。活动类型必须是 monthly_daily_check_in。
 * clockIn 先和唯一领取记录一起落库，再请求游戏服发奖。已签的天在状态里就是 claimed。
 * 今天第一次签到不扣补签；今天已经签过再签下一档，才扣每月补签次数。
 */
final class CheckInService // final：禁止被继承，保证业务入口单一
{
    /** 构造注入：签到仓储、天数配置、游戏时区、EntityManager、发奖客户端。 */
    public function __construct(
        private readonly DailyCheckInUserDataRepository $repo,
        private readonly DailyCheckInConfig $config,
        private readonly string $timezone,
        private readonly EntityManagerInterface $em,
        private readonly GiftGrantClientInterface $giftClient
    ) {}

    public function currentYearMonth(): int
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone($this->timezone));

        return (int) $now->format('Ym');
    }

    /**
     * 按类型 monthly_daily_check_in 和请求里的 activity_id 查找正在进行的签到活动。
     * 类型或 id 对不上、未开放、或不在时间窗口内，由 RunningActivity 抛 ACTIVITY_NOT_FOUND。
     *
     * @return array{activity_id: int, type: string, name: string, starts_at: string, ends_at: string, is_open: bool}
     */
    private function getRunningActivity(int $activityId): array
    {
        return RunningActivity::find('monthly_daily_check_in', $activityId, $this->timezone);
    }

    /**
     * 本月能签到的最大档。$startsAt 是查到的活动开始时间。
     * 开始当月从活动开始那天算第 1 档，之后的月份从 1 号算，再和配置天数取较小值。
     */
    private function maxAvailableDay(string $startsAt): int
    {
        $tz = new \DateTimeZone($this->timezone);
        $now = new \DateTimeImmutable('now', $tz);
        $start = new \DateTimeImmutable($startsAt, $tz);
        if ($now < $start) {
            return 0;
        }

        $startDate = $start->setTime(0, 0);
        $nowDate = $now->setTime(0, 0);
        $roundStart = $startDate->format('Ym') === $nowDate->format('Ym')
            ? $startDate
            : $nowDate->modify('first day of this month');
        $available = (int) $roundStart->diff($nowDate)->days + 1;

        return min($available, count($this->config->getCheckDays()));
    }

    /**
     * 查询签到状态（给 status 接口用）
     *
     * @return array{
     *   checkedDays: list<int>,
     *   totalChecked: int,
     *   nextCheckDay: int|null,
     *   isCheckedToday: bool,
     *   maxAvailableDay: int,
     *   makeupLimit: int,
     *   makeupUsed: int,
     *   makeupRemaining: int,
     *   tiers: list<array{day: int, status: string}>,
     *   yearMonth: int
     * }
     */
    public function getStatus(int $userRolePrimaryId, int $activityId): array
    {
        $activity = $this->getRunningActivity($activityId);
        // 按角色 + 活动 + 当月取进度；没有则内存里新建一条，status 本身不落库
        $yearMonth = $this->currentYearMonth();
        $userData = $this->repo->findOrCreate($userRolePrimaryId, $activityId, $yearMonth);
        $checkedDays = $userData->getCheckedDays(); // 已签过的天数列表，如 [1,2]
        $next = $this->config->getNextCheckDay($checkedDays); // 下一个应签的天；全签完为 null

        $isCheckedToday = $userData->isCheckedToday($this->timezone);
        $maxAvailableDay = $this->maxAvailableDay($activity['starts_at']);
        $makeupLimit = $this->config->getMakeupCheckInLimit();
        $makeupUsed = $userData->getMakeupUsed();
        $makeupRemaining = max(0, $makeupLimit - $makeupUsed);
        // 下一档已经开放，并且今天还没签，或者还剩补签次数
        $nextCheckable = $next !== null
            && $next <= $maxAvailableDay
            && (!$isCheckedToday || $makeupRemaining > 0);

        $tiers = [];
        foreach ($this->config->getCheckDays() as $day) {
            if ($userData->isDayChecked($day)) {
                $status = 'claimed';
            } elseif ($day === $next && $nextCheckable) {
                $status = 'claimable';
            } else {
                $status = 'locked';
            }
            $tiers[] = ['day' => $day, 'status' => $status];
        }

        return [
            'yearMonth' => $yearMonth,
            'checkedDays' => $checkedDays,
            'totalChecked' => $userData->getTotalChecked(),
            'nextCheckDay' => $next,
            'isCheckedToday' => $isCheckedToday,
            'maxAvailableDay' => $maxAvailableDay,
            'makeupLimit' => $makeupLimit,
            'makeupUsed' => $makeupUsed,
            'makeupRemaining' => $makeupRemaining,
            'tiers' => $tiers,
        ];
    }

    /**
     * 执行打卡（给 clock-in 接口用）
     *
     * @return array{
     *   status: string,
     *   claimedTier: int,
     *   isMakeup: bool,
     *   yearMonth: int,
     *   checkedDays: list<int>,
     *   totalChecked: int,
     *   makeupLimit: int,
     *   makeupUsed: int,
     *   makeupRemaining: int
     * }
     */
    public function clockIn(int $userRolePrimaryId, int $activityId, int $checkDay): array
    {
        $activity = $this->getRunningActivity($activityId);
        // 校验：请求的天数是否在活动配置里
        if (!$this->config->isValidCheckDay($checkDay)) {
            error_log(sprintf( // 打拒绝日志，方便排查
                '[checkin] reject role=%d activity=%d day=%d reason=invalid_day',
                $userRolePrimaryId,
                $activityId,
                $checkDay,
            ));
            throw new BusinessException(ErrorCode::INVALID_CHECK_DAY, 'Check in invalid day'); // 非法天数
        }

        $yearMonth = $this->currentYearMonth();
        $userData = $this->repo->findOrCreate($userRolePrimaryId, $activityId, $yearMonth); // 取/建进度

        // 校验：该天是否已经签过
        if ($userData->isDayChecked($checkDay)) {
            error_log(sprintf(
                '[checkin] reject role=%d activity=%d day=%d reason=already_checked_day',
                $userRolePrimaryId,
                $activityId,
                $checkDay,
            ));
            throw new BusinessException(ErrorCode::ALREADY_CHECKED_IN, 'Already checked in this day');
        }

        // 校验：必须按顺序签（只能签「下一天」）
        $next = $this->config->getNextCheckDay($userData->getCheckedDays());
        if ($next !== $checkDay) {
            error_log(sprintf(
                '[checkin] reject role=%d activity=%d day=%d next=%s reason=order',
                $userRolePrimaryId,
                $activityId,
                $checkDay,
                $next === null ? 'null' : (string) $next, // null 转字符串便于日志
            ));
            throw new BusinessException(ErrorCode::CHECK_IN_ORDER_ERROR, 'This day is not available for check-in yet');
        }

        // 档位不能超过活动开始至今的自然日，也不能超过配置天数。例如 9 月 1 日开始、当前是 9 月 27 日，开放到第 27 档。
        $maxAvailableDay = $this->maxAvailableDay($activity['starts_at']);
        if ($checkDay > $maxAvailableDay) {
            throw new BusinessException(ErrorCode::CHECK_IN_DAY_NOT_REACHED, 'This check-in day has not been reached yet');
        }

        // 今天还没签是正常签到。今天已经签过，就消耗一次补签
        $isMakeup = $userData->isCheckedToday($this->timezone);
        if ($isMakeup) {
            if ($userData->getMakeupUsed() >= $this->config->getMakeupCheckInLimit()) {
                throw new BusinessException(ErrorCode::MAKEUP_LIMIT_EXCEEDED, 'No makeup check-in chance left this month');
            }
            $userData->consumeMakeup();
        }

        $gift = $this->config->getGiftForDay($checkDay);
        $now = time();
        // 进度和唯一领取记录放进同一个事务。唯一索引冲突时两边都回滚。
        try {
            $this->em->getConnection()->transactional(function () use ($userData, $userRolePrimaryId, $activityId, $yearMonth, $checkDay, $now): void {
                $userData->markDay($checkDay);
                $this->repo->save($userData);
                $this->em->getConnection()->insert('monthly_daily_check_in_user_data_unique_records', [
                    'user_role_primary_id' => $userRolePrimaryId,
                    'activity_id' => $activityId,
                    'year_month_num' => $yearMonth,
                    'check_day' => $checkDay,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessException(ErrorCode::RESOURCE_BUSY, 'The resource is busy, please retry.', 409);
        }

        // 事务已提交。发奖失败不回滚。GIFT_API_URL 为空时只记日志。
        $this->giftClient->grant(
            $userRolePrimaryId,
            $activityId,
            GiftPayload::build($this->em, $userRolePrimaryId, 'sign', $gift),
        );

        return [
            'status' => 'success',
            'claimedTier' => $checkDay,
            'isMakeup' => $isMakeup,
            'yearMonth' => $yearMonth,
            'checkedDays' => $userData->getCheckedDays(),
            'totalChecked' => $userData->getTotalChecked(),
            'makeupLimit' => $this->config->getMakeupCheckInLimit(),
            'makeupUsed' => $userData->getMakeupUsed(),
            'makeupRemaining' => max(0, $this->config->getMakeupCheckInLimit() - $userData->getMakeupUsed()),
        ];
    }
}

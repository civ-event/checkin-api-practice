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
use Checkin\Config\ActivitySchedule;

/**
 * 进度键是角色主键 + 请求里的签到活动 1 + YYYYMM。
 *  clockIn 记签到并立刻发当天礼物。/claim 仍保留，给已经签过但还没领的天。
 */
final class CheckInService // final：禁止被继承，保证业务入口单一
{
    /** 构造注入：仓储、活动配置、时区字符串 */
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

    /** 月度签到活动。累充是 2，不要混用。 */
    private const ACTIVITY_ID = 1;

    /** 请求里的活动必须是签到 1，并且当前时间在活动窗口内。错活动或不在窗口都是 10014。 */
    private function assertCheckInActivity(int $activityId): void
    {
        if ($activityId !== self::ACTIVITY_ID) {
            throw new BusinessException(ErrorCode::ACTIVITY_NOT_RUNNING, 'Activity is not the monthly check-in activity');
        }

        ActivitySchedule::assertRunning(self::ACTIVITY_ID, $this->timezone);
    }

    /**
     * 本月能签到的最大档。开始当月从活动开始那天算第 1 档，之后的月份从 1 号算。
     * 结果不超过配置里的总天数。
     */
    private function maxAvailableDay(int $activityId): int
    {
        /** @var array<int, array{starts_at: string}> $all */
        $all = require dirname(__DIR__, 2) . '/config/activities.php';
        $tz = new \DateTimeZone($this->timezone);
        $now = new \DateTimeImmutable('now', $tz);
        $start = new \DateTimeImmutable($all[$activityId]['starts_at'], $tz);
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
     *   tiers: list<array{day: int, status: string}>
     * }
     */
    public function getStatus(int $userRolePrimaryId, int $activityId): array
    {
        $this->assertCheckInActivity($activityId);
        // 按角色+活动取进度；没有则内存里新建一条（尚未落库）
        $yearMonth = $this->currentYearMonth();
        $userData = $this->repo->findOrCreate($userRolePrimaryId, $activityId, $yearMonth);
        $checkedDays = $userData->getCheckedDays(); // 已签过的天数列表，如 [1,2]
        $next = $this->config->getNextCheckDay($checkedDays); // 下一个应签的天；全签完为 null

        $isCheckedToday = $userData->isCheckedToday($this->timezone);
        $maxAvailableDay = $this->maxAvailableDay($activityId);
        $makeupLimit = $this->config->getMakeupCheckInLimit();
        $makeupUsed = $userData->getMakeupUsed();
        $makeupRemaining = max(0, $makeupLimit - $makeupUsed);
        // 下一档已经开放，并且今天还没签，或者还剩补签次数
        $nextCheckable = $next !== null
            && $next <= $maxAvailableDay
            && (!$isCheckedToday || $makeupRemaining > 0);

        $tiers = [];
        foreach ($this->config->getCheckDays() as $day) {
            if ($userData->isDayClaimed($day)) {
                $status = 'claimed'; // 已领奖
            } elseif ($userData->isDayChecked($day)) {
                $status = 'checked'; // 已签，还没领
            } elseif ($next === $day && $nextCheckable) {
                $status = 'checkable'; // 可以签
            } else {
                $status = 'locked';
            }
            $tiers[] = ['day' => $day, 'status' => $status];
        }

        return [
            'checkedDays' => $checkedDays,
            'claimedDays' => $userData->getClaimedDays(),
            'totalChecked' => $userData->getTotalChecked(),
            'nextCheckDay' => $next,
            'isCheckedToday' => $isCheckedToday,
            'maxAvailableDay' => $maxAvailableDay,
            'makeupLimit' => $makeupLimit,
            'makeupUsed' => $makeupUsed,
            'makeupRemaining' => $makeupRemaining,
            'tiers' => $tiers,
            'year_month' => $yearMonth,
        ];
    }

    /**
     * 执行打卡（给 clock-in 接口用）
     *
     * @return array{
     *   status: string,
     *   claimedTier: int,
     *   checkedDays: list<int>,
     *   claimedDays: list<int>,
     *   totalChecked: int,
     * }
     */
    public function clockIn(int $userRolePrimaryId, int $activityId, int $checkDay): array
    {
        $this->assertCheckInActivity($activityId);
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

        // 校验：同一自然日只能签一次
        // 档位不能超过活动开始至今的自然日。9 月 1 日开始、今天 9 月 24 日，7 档都已开放
        $maxAvailableDay = $this->maxAvailableDay($activityId);
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

        // 线上是签到当场发奖。这里同时记已签和已领，避免同一天再走 /claim 发第二次
        $userData->markDay($checkDay);
        $userData->markClaimed($checkDay);
        $this->repo->save($userData);

        $gift = $this->config->getGiftForDay($checkDay);

        try {
            // year_month 是关键字，插入键必须自带反引号
            $this->em->getConnection()->insert('daily_check_in_gift_log', [
                'user_role_primary_id' => $userRolePrimaryId,
                'activity_id' => $activityId,
                '`year_month`' => $yearMonth,
                'check_day' => $checkDay,
                'gift_id' => $gift['gift_id'],
                'gift_name' => $gift['gift_name'],
                'created_at' => time(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new BusinessException(ErrorCode::ALREADY_CLAIMED, 'Already claimed this day');
        }

        // 流水写入后再发奖。日志里搜 [gift] sent
        $this->giftClient->grant($userRolePrimaryId, $activityId, $gift);

        return [
            'status' => 'success',
            'claimedTier' => $checkDay,
            'checkedDays' => $userData->getCheckedDays(),
            'claimedDays' => $userData->getClaimedDays(),
            'totalChecked' => $userData->getTotalChecked(),
            'gift' => $gift,
            'year_month' => $yearMonth,
            'isMakeup' => $isMakeup,
            'makeupLimit' => $this->config->getMakeupCheckInLimit(),
            'makeupUsed' => $userData->getMakeupUsed(),
            'makeupRemaining' => max(0, $this->config->getMakeupCheckInLimit() - $userData->getMakeupUsed()),
        ];
    }
    /**
     * 领取某一天的签到奖励。必须先签过；同一天重复领返回 10008。
     * 拒绝顺序：非法天数 → 没签(10007) → 已领(10008)。
     */
    public function claim(int $userRolePrimaryId, int $activityId, int $checkDay): array
    {
        $this->assertCheckInActivity($activityId);
        if (!$this->config->isValidCheckDay($checkDay)) {
            throw new BusinessException(ErrorCode::INVALID_CHECK_DAY, 'Check in invalid day');
        }

        $yearMonth = $this->currentYearMonth();
        $userData = $this->repo->findOrCreate($userRolePrimaryId, $activityId, $yearMonth);

        if (!$userData->isDayChecked($checkDay)) {
            throw new BusinessException(ErrorCode::NOT_CHECKED, 'This day is not checked in yet');
        }

        if ($userData->isDayClaimed($checkDay)) {
            throw new BusinessException(ErrorCode::ALREADY_CLAIMED, 'Already claimed this day');
        }

        $userData->markClaimed($checkDay);
        $this->repo->save($userData);

        $gift = $this->config->getGiftForDay($checkDay);

        try {
            // 与充值插入相同：DBAL 不转义列名，year_month 必须自带反引号，否则 1064 → 50000
            $this->em->getConnection()->insert('daily_check_in_gift_log', [
                'user_role_primary_id' => $userRolePrimaryId,
                'activity_id' => $activityId,
                '`year_month`' => $yearMonth,
                'check_day' => $checkDay,
                'gift_id' => $gift['gift_id'],
                'gift_name' => $gift['gift_name'],
                'created_at' => time(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new BusinessException(ErrorCode::ALREADY_CLAIMED, 'Already claimed this day');
        }

        // 流水已写入后再发奖。日志里搜 [gift] sent；没有这行说明没走到发奖
        $this->giftClient->grant($userRolePrimaryId, $activityId, $gift);

        return [
            'status' => 'success',
            'checkDay' => $checkDay,
            'claimedDays' => $userData->getClaimedDays(),
            'gift' => $gift,
            'year_month' => $yearMonth,
        ];
    }
}

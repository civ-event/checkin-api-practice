<?php

declare(strict_types=1);

namespace Tests\Service;

use Checkin\Common\ErrorCode;
use Checkin\Config\DailyCheckInConfig;
use Checkin\Config\GameClock;
use Checkin\Database\DoctrineFactory;
use Checkin\Entities\DailyCheckInUserData;
use Checkin\Entities\Repositories\DailyCheckInUserDataRepository;
use Checkin\Exception\BusinessException;
use Checkin\Gift\HttpGiftClient;
use Checkin\Service\CheckInService;
use Checkin\Service\LoginService;
use PHPUnit\Framework\TestCase;

final class CheckInClockInTest extends TestCase
{
    public function testClockInNextDayThenRejectRepeatAndSkip(): void
    {
        $em = DoctrineFactory::createEntityManager();
        $repo = $em->getRepository(DailyCheckInUserData::class);
        self::assertInstanceOf(DailyCheckInUserDataRepository::class, $repo);
        $checkIn = new CheckInService(
            $repo,
            DailyCheckInConfig::load(),
            (new GameClock())->gameTimezone()->getName(),
            $em,
            new HttpGiftClient(''),
        );
        $roleId = (new LoginService($em))->login('token-player-1001', 's4', 'r400')['user_role_primary_id'];

        $status = $checkIn->getStatus($roleId, 1);
        $day = $status['nextCheckDay'];
        $checkable = $day !== null
            && $day <= $status['maxAvailableDay']
            && (!$status['isCheckedToday'] || $status['makeupRemaining'] > 0);
        if (!$checkable) {
            self::markTestSkipped('本月没有可签的下一档');
        }

        $result = $checkIn->clockIn($roleId, 1, $day);
        self::assertSame('success', $result['status']);
        self::assertContains($day, $result['checkedDays']);
        self::assertSame($status['isCheckedToday'], $result['isMakeup']);

        try {
            $checkIn->clockIn($roleId, 1, $day);
            self::fail('同一天不能再签');
        } catch (BusinessException $e) {
            self::assertSame(ErrorCode::ALREADY_CHECKED_IN, $e->getBusinessCode());
        }

        try {
            $checkIn->clockIn($roleId, 1, $day + 2);
            self::fail('不能跳档');
        } catch (BusinessException $e) {
            self::assertSame(ErrorCode::CHECK_IN_ORDER_ERROR, $e->getBusinessCode());
        }
    }
}

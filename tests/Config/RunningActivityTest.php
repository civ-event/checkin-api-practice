<?php

declare(strict_types=1);

namespace Tests\Config;

use Checkin\Common\ErrorCode;
use Checkin\Config\RunningActivity;
use Checkin\Exception\BusinessException;
use PHPUnit\Framework\TestCase;

final class RunningActivityTest extends TestCase
{
    public function testCheckInActivityIsRunning(): void
    {
        $activity = RunningActivity::find('monthly_daily_check_in', 1, 'Etc/GMT+5');

        self::assertSame(1, $activity['activity_id']);
    }

    public function testRechargeIdIsNotACheckInActivity(): void
    {
        try {
            RunningActivity::find('monthly_daily_check_in', 2, 'Etc/GMT+5');
            self::fail('activity 2 must not pass as check-in');
        } catch (BusinessException $e) {
            self::assertSame(ErrorCode::ACTIVITY_NOT_FOUND, $e->getBusinessCode());
        }
    }
}

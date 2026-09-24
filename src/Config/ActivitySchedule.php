<?php

declare(strict_types=1);

namespace Checkin\Config;

use Checkin\Common\ErrorCode;
use Checkin\Exception\BusinessException;

/** 用 config/activities.php 判断活动此刻是否在进行。时区用 APP_TIMEZONE。 */
final class ActivitySchedule
{
    public static function assertRunning(int $activityId, string $timezone): void
    {
        /** @var array<int, array{name: string, starts_at: string, ends_at: string}> $all */
        $all = require dirname(__DIR__, 2) . '/config/activities.php';
        $activity = $all[$activityId] ?? null;
        if (!is_array($activity)) {
            throw new BusinessException(ErrorCode::ACTIVITY_NOT_RUNNING, 'Activity is not configured');
        }

        $tz = new \DateTimeZone($timezone);
        $now = new \DateTimeImmutable('now', $tz);
        $start = new \DateTimeImmutable($activity['starts_at'], $tz);
        $end = new \DateTimeImmutable($activity['ends_at'], $tz);
        if ($now < $start || $now > $end) {
            throw new BusinessException(ErrorCode::ACTIVITY_NOT_RUNNING, 'Activity is not running');
        }
    }
}

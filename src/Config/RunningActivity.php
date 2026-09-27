<?php

declare(strict_types=1);

namespace Checkin\Config;

use Checkin\Common\ErrorCode;
use Checkin\Exception\BusinessException;

/**
 * 按活动类型和 activity_id 查找正在进行的活动。
 * 对应线上 RunningActivities::getTheActivity()。
 * 时间用调用方传入的时区解释。签到和累充的活动窗口传游戏时区。
 */
final class RunningActivity
{
    /**
     * 三条同时满足才返回这条配置：已开放、当前时间在窗口内、类型和活动 id 都匹配。
     *
     * @return array{
     *     activity_id: int,
     *     type: string,
     *     name: string,
     *     starts_at: string,
     *     ends_at: string,
     *     is_open: bool
     * }
     */
    public static function find(string $type, int $activityId, string $timezone): array
    {
        /** @var list<array{activity_id: int, type: string, name: string, starts_at: string, ends_at: string, is_open: bool}> $all */
        $all = require dirname(__DIR__, 2) . '/config/activities.php';

        $matched = null;
        foreach ($all as $activity) {
            if ($activity['type'] === $type && $activity['activity_id'] === $activityId) {
                $matched = $activity;
                break;
            }
        }

        // 没有这条类型加 id 的记录，或活动没开放。
        if ($matched === null || $matched['is_open'] !== true) {
            throw new BusinessException(ErrorCode::ACTIVITY_NOT_FOUND, 'Activity is not configured', 404);
        }

        $tz = new \DateTimeZone($timezone);
        $now = new \DateTimeImmutable('now', $tz);
        $start = new \DateTimeImmutable($matched['starts_at'], $tz);
        $end = new \DateTimeImmutable($matched['ends_at'], $tz);
        // 记录在，但当前时间不在开始和结束之间（含两端）。
        if ($now < $start || $now > $end) {
            throw new BusinessException(ErrorCode::ACTIVITY_NOT_FOUND, 'Activity is not running', 404);
        }

        return $matched;
    }
}
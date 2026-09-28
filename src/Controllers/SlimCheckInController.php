<?php

declare(strict_types=1);

namespace Checkin\Controllers;

use Checkin\Auth\JwtService;
use Checkin\Common\ErrorCode;
use Checkin\Common\Lock;
use Checkin\Exception\BusinessException;
use Checkin\Service\CheckInService;
use Symfony\Component\HttpFoundation\Request;
use Checkin\Auth\LoginContext;

/**
 * 新内核上的月度签到。身份仍用旧登录接口签发的 JWT。
 * clock-in 记签到并发奖。写操作使用锁 check_in:{角色主键}:{活动}:{YYYYMM}。
 * 签到活动 id 来自请求，类型必须是 monthly_daily_check_in。
 */
class SlimCheckInController
{
    /** GET /front.php/check-in/status 每次查 MySQL */
    public function statusAction(Request $request, CheckInService $checkInService, JwtService $jwt): array
    {
        $roleId = LoginContext::fromRequest($request, $jwt)->rolePrimaryId();
        $activityId = $this->readQueryInt($request, 'activity_id', 'activity_id is required and must be int');

        return $checkInService->getStatus($roleId, $activityId);
    }

    /** clock-in 记签到并发奖 */
    public function clockInAction(Request $request, CheckInService $checkInService, JwtService $jwt, Lock $lock): array
    {
        $roleId = LoginContext::fromRequest($request, $jwt)->rolePrimaryId();
        $activityId = $this->readBodyInt($request, 'activity_id', 'activity_id is required and must be int');
        $checkDay = $this->readCheckDay($request);
        $yearMonth = $checkInService->currentYearMonth();
        // 等待时间 0：抢不到立刻失败，不在接口里空转
        $lockKey = sprintf('check_in:%d:%d:%d', $roleId, $activityId, $yearMonth);

        return $lock->memcachedLock(
            fn(): array => $checkInService->clockIn($roleId, $activityId, $checkDay),
            $lockKey,
            30,
            0.0,
        );
    }

    /** form 或 JSON 里的整数。前端打卡用 form。 */
    private function readCheckDay(Request $request): int
    {
        return $this->readBodyInt($request, 'check_day', 'check_day is required and must be int');
    }

    private function readQueryInt(Request $request, string $field, string $message): int
    {
        $value = $request->query->get($field);
        if (!is_numeric($value)) {
            throw new BusinessException(ErrorCode::INVALID_PARAMETER, $message);
        }

        return (int) $value;
    }

    private function readBodyInt(Request $request, string $field, string $message): int
    {
        $value = $this->params($request)[$field] ?? null;
        if (!is_numeric($value)) {
            throw new BusinessException(ErrorCode::INVALID_PARAMETER, $message);
        }

        return (int) $value;
    }

    /** @return array<string, mixed> */
    private function params(Request $request): array
    {
        $json = json_decode($request->getContent(), true);
        $params = is_array($json) ? $json : [];
        foreach ($request->request->all() as $key => $value) {
            $params[$key] = $value;
        }

        return $params;
    }
}

<?php

declare(strict_types=1);

namespace Checkin\Controllers;

use Checkin\Auth\JwtService;
use Checkin\Common\ErrorCode;
use Checkin\Common\Lock;
use Checkin\Exception\BusinessException;
use Checkin\Service\CheckInService;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

/**
 * 新内核上的月度签到。身份仍用旧登录接口签发的 JWT。
 * role_id 是角色表主键。clock-in 只记签到，领奖走 claim。
 * 两处写操作共用锁 check_in:{角色主键}:{活动}:{YYYYMM}。
 */
class SlimCheckInController
{
    /** GET /front.php/check-in/status 每次查 MySQL */
    public function statusAction(Request $request, CheckInService $checkInService, JwtService $jwt): array
    {
        [$roleId, $activityId] = $this->resolveContext($request, $jwt);

        return $checkInService->getStatus($roleId, $activityId);
    }

    /** POST /front.php/check-in/clock-in 只打卡，不发奖 */
    public function clockInAction(Request $request, CheckInService $checkInService, JwtService $jwt, Lock $lock): array
    {
        [$roleId, $activityId] = $this->resolveContext($request, $jwt);
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

    /** POST /front.php/check-in/claim 这一天必须已签且未领，成功后才会打出 [gift] sent */
    public function claimAction(Request $request, CheckInService $checkInService, JwtService $jwt, Lock $lock): array
    {
        [$roleId, $activityId] = $this->resolveContext($request, $jwt);
        $checkDay = $this->readCheckDay($request);
        $yearMonth = $checkInService->currentYearMonth();
        $lockKey = sprintf('check_in:%d:%d:%d', $roleId, $activityId, $yearMonth);

        return $lock->memcachedLock(
            fn(): array => $checkInService->claim($roleId, $activityId, $checkDay),
            $lockKey,
            30,
            0.0,
        );
    }

    /**
     * 从 Authorization: Bearer 取出角色主键和活动 id。
     *
     * @return array{0: int, 1: int}
     */
    private function resolveContext(Request $request, JwtService $jwt): array
    {
        $header = (string) $request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            throw new BusinessException(ErrorCode::MISSING_CONTEXT, 'Authorization Bearer token is required', 401);
        }

        try {
            $claims = $jwt->decode($matches[1]);
        } catch (Throwable) {
            throw new BusinessException(ErrorCode::MISSING_CONTEXT, 'Invalid or expired token', 401);
        }

        return [$claims['role_id'], $claims['activity_id']];
    }

    /** 新内核没有 Slim 的 JSON body 解析，要自己读原始正文 */
    private function readCheckDay(Request $request): int
    {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body) || !isset($body['check_day']) || !is_numeric($body['check_day'])) {
            throw new BusinessException(ErrorCode::INVALID_PARAM, 'check_day is required and must be int');
        }

        return (int) $body['check_day'];
    }
}

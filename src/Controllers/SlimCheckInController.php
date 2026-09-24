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
 * clock-in 记签到并发奖
 * 两处写操作共用锁 check_in:{角色主键}:{活动}:{YYYYMM}。
 * 签到活动 id 来自请求，只接受 1
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

    /** POST /front.php/check-in/claim 这一天必须已签且未领，成功后才会打出 [gift] sent */
    public function claimAction(Request $request, CheckInService $checkInService, JwtService $jwt, Lock $lock): array
    {
        $roleId = LoginContext::fromRequest($request, $jwt)->rolePrimaryId();
        $activityId = $this->readBodyInt($request, 'activity_id', 'activity_id is required and must be int');
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


    /** 新内核没有 Slim 的 JSON body 解析，要自己读原始正文 */
    private function readCheckDay(Request $request): int
    {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body) || !isset($body['check_day']) || !is_numeric($body['check_day'])) {
            throw new BusinessException(ErrorCode::INVALID_PARAM, 'check_day is required and must be int');
        }

        return (int) $body['check_day'];
    }

    private function readQueryInt(Request $request, string $field, string $message): int
    {
        $value = $request->query->get($field);
        if (!is_numeric($value)) {
            throw new BusinessException(ErrorCode::INVALID_PARAM, $message);
        }

        return (int) $value;
    }

    private function readBodyInt(Request $request, string $field, string $message): int
    {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body) || !isset($body[$field]) || !is_numeric($body[$field])) {
            throw new BusinessException(ErrorCode::INVALID_PARAM, $message);
        }

        return (int) $body[$field];
    }
}

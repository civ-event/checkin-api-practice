<?php

declare(strict_types=1);

namespace Checkin\Controllers;

use Checkin\Auth\JwtService;
use Checkin\Common\ErrorCode;
use Checkin\Common\Lock;
use Checkin\Exception\BusinessException;
use Checkin\Service\RechargeService;
use Symfony\Component\HttpFoundation\Request;
use Checkin\Auth\LoginContext;

/**
 * 新内核上的月度累充。身份仍用旧登录 JWT 的角色主键。
 * 累充活动 id 来自请求，类型必须是 monthly_cumulative_recharge。
 * 锁键 recharge:{角色主键}:{活动}:{YYYYMM}。YYYYMM 按角色所在服务器时区，和签到锁分开。
 */
class SlimRechargeController
{
    /** GET /front.php/recharge/status 不加锁。先按 mock 流水覆盖当月金额，再返回进度。 */
    public function statusAction(Request $request, RechargeService $rechargeService, JwtService $jwt): array
    {
        $roleId = $this->roleId($request, $jwt);
        $activityId = $this->readQueryInt($request, 'activity_id', 'activity_id is required and must be int');

        return $rechargeService->getStatus($roleId, $activityId);
    }

    /** POST /front.php/recharge/record amount 只校验为正整数，不累加。当月金额按 mock_payments 覆盖。 */
    public function recordAction(Request $request, RechargeService $rechargeService, JwtService $jwt, Lock $lock): array
    {
        $roleId = $this->roleId($request, $jwt);
        $amount = $this->readInt($request, 'amount', 'amount is required and must be int');
        $activityId = $this->readInt($request, 'activity_id', 'activity_id is required and must be int');

        return $this->locked($lock, $rechargeService, $roleId, $activityId, fn(): array => $rechargeService->record($roleId, $activityId, $amount));
    }

    /** POST /front.php/recharge/claim 未达到是 RECHARGE_NOT_REACHED，该档已领是 REWARD_ALREADY_CLAIMED */
    public function claimAction(Request $request, RechargeService $rechargeService, JwtService $jwt, Lock $lock): array
    {
        $roleId = $this->roleId($request, $jwt);
        $threshold = $this->readInt($request, 'threshold', 'threshold is required and must be int');
        $activityId = $this->readInt($request, 'activity_id', 'activity_id is required and must be int');

        return $this->locked($lock, $rechargeService, $roleId, $activityId, fn(): array => $rechargeService->claim($roleId, $activityId, $threshold));
    }

    private function roleId(Request $request, JwtService $jwt): int
    {
        // 只取角色主键。累充活动 id 来自请求，不读 JWT。
        return LoginContext::fromRequest($request, $jwt)->rolePrimaryId();
    }

    private function readInt(Request $request, string $field, string $message): int
    {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body) || !isset($body[$field]) || !is_numeric($body[$field])) {
            throw new BusinessException(ErrorCode::INVALID_PARAMETER, $message);
        }

        return (int) $body[$field];
    }

    private function readQueryInt(Request $request, string $field, string $message): int
    {
        $value = $request->query->get($field);
        if (!is_numeric($value)) {
            throw new BusinessException(ErrorCode::INVALID_PARAMETER, $message);
        }

        return (int) $value;
    }

    /**
     * @param callable(): array<string, mixed> $callback
     * @return array<string, mixed>
     */
    private function locked(Lock $lock, RechargeService $rechargeService, int $roleId, int $activityId, callable $callback): array
    {
        $yearMonth = $rechargeService->currentYearMonth($roleId);
        $lockKey = sprintf('recharge:%d:%d:%d', $roleId, $activityId, $yearMonth);

        return $lock->memcachedLock($callback, $lockKey, 30, 0.0);
    }
}

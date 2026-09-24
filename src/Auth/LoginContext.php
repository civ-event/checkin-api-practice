<?php

declare(strict_types=1);

namespace Checkin\Auth;

use Checkin\Common\ErrorCode;
use Checkin\Exception\BusinessException;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

/**
 * 一次请求里的登录身份。角色主键只来自登录接口签发的 JWT。
 * checkInActivityId 是签到活动，固定为 1。累充活动仍是 2，不要用这个字段。
 */
final class LoginContext
{
    private function __construct(
        private readonly int $rolePrimaryId,
        private readonly int $checkInActivityId,
    ) {}

    public static function fromRequest(Request $request, JwtService $jwt): self
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

        return new self($claims['role_id'], $claims['activity_id']);
    }

    public function rolePrimaryId(): int
    {
        return $this->rolePrimaryId;
    }

    public function checkInActivityId(): int
    {
        return $this->checkInActivityId;
    }
}

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
        $token = self::normalizeToken((string) $request->headers->get('activity-user-token', ''));
        if ($token === '') {
            $token = self::normalizeToken((string) $request->query->get('activity_user_token', ''));
        }
        // 请求头或查询参数都没有 token，或后面验签失败，都是未登录。HTTP 401。
        if ($token === '') {
            throw new BusinessException(ErrorCode::UNAUTHORIZED, 'activity-user-token is required', 401);
        }

        try {
            $claims = $jwt->decode($token);
        } catch (Throwable) {
            throw new BusinessException(ErrorCode::UNAUTHORIZED, 'Invalid or expired token', 401);
        }

        return new self($claims['role_id'], $claims['activity_id']);
    }

    /** 去掉引号、换行和空格。编辑器把 token 折成多行时，签名仍要按一整段校验。 */
    private static function normalizeToken(string $token): string
    {
        $token = trim($token, " \t\n\r\0\x0B\"'");
        $token = preg_replace('/\s+/u', '', $token) ?? '';

        return str_replace(["\u{200B}", "\u{FEFF}"], '', $token);
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

<?php

declare(strict_types=1);

namespace Checkin\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;

final class JwtService
{
    private string $secret;
    private int $ttl;

    public function __construct()
    {
        $secret = getenv('JWT_SECRET') ?: '';
        if ($secret === '') {
            throw new RuntimeException('JWT_SECRET is empty');
        }

        $this->secret = $secret;
        $this->ttl = (int) (getenv('JWT_TTL') ?: 3600);
    }

    /**
     * role_id 必须是 activity_user_role.id，不是游戏侧角色字符串。
     * 过期、签名不对会在 decode 抛异常，LoginContext 收成 UNAUTHORIZED。
     */
    public function encode(int $roleId, int $activityId): string

    {
        $now = time();

        $payload = [
            'role_id' => $roleId,
            'activity_id' => $activityId,
            'iat' => $now,
            'exp' => $now + $this->ttl,
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    /**
     * @return array{role_id: int, activity_id: int}
     */
    public function decode(string $token): array
    {
        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));

        $roleId = (int) ($decoded->role_id ?? 0);
        $activityId = (int) ($decoded->activity_id ?? 0);

        if ($roleId < 1 || $activityId < 1) {
            throw new RuntimeException('invalid jwt claims');
        }

        return [
            'role_id' => $roleId,
            'activity_id' => $activityId,
        ];
    }
}

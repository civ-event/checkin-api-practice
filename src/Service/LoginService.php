<?php

declare(strict_types=1);

namespace Checkin\Service;

use Checkin\Common\ErrorCode;
use Checkin\Exception\BusinessException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * 练习用登录。账号来自 config/accounts.php，不是游戏服的真实 accessToken。
 * 返回的 user_role_primary_id 是 activity_user_role.id，之后 JWT 和签到/充值都用这个数，
 * 不是游戏角色字符串（例如 r100、r200）。同一角色再次登录必须得到同一个 id。
 */
final class LoginService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /** @return list<array{server_id: string, role_id: string, role_name: string}> */
    public function roles(string $accessToken): array
    {
        return $this->account($accessToken)['roles'];
    }

    /**
     * @return array{
     *   user_role_primary_id: int,
     *   player_id: string,
     *   server_id: string,
     *   role_id: string,
     *   role_name: string
     * }
     */
    public function login(string $accessToken, string $serverId, string $roleId): array
    {
        $account = $this->account($accessToken);

        $matched = null;
        foreach ($account['roles'] as $role) {
            if ($role['server_id'] === $serverId && $role['role_id'] === $roleId) {
                $matched = $role;
                break;
            }
        }
        if ($matched === null) {
            // 10010：token 有效，但 server_id + role_id 不属于这个玩家
            throw new BusinessException(ErrorCode::ROLE_NOT_OWNED, 'Role does not belong to this player', 400);
        }

        $conn = $this->em->getConnection();
        $now = time();

        $user = $conn->fetchAssociative(
            'SELECT id FROM activity_user WHERE player_id = ?',
            [$account['player_id']],
        );
        // 玩家、角色都是找到就复用，没有才插入。重复登录不应新建主键
        if ($user === false) {
            $conn->insert('activity_user', [
                'player_id' => $account['player_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $activityUserId = (int) $conn->lastInsertId();
        } else {
            $activityUserId = (int) $user['id'];
        }

        $roleRow = $conn->fetchAssociative(
            'SELECT id FROM activity_user_role WHERE activity_user_id = ? AND server_id = ? AND role_id = ?',
            [$activityUserId, $serverId, $roleId],
        );
        if ($roleRow === false) {
            $conn->insert('activity_user_role', [
                'activity_user_id' => $activityUserId,
                'server_id' => $serverId,
                'role_id' => $roleId,
                'role_name' => $matched['role_name'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $userRolePrimaryId = (int) $conn->lastInsertId();
        } else {
            $userRolePrimaryId = (int) $roleRow['id'];
        }

        return [
            'user_role_primary_id' => $userRolePrimaryId,
            'player_id' => $account['player_id'],
            'server_id' => $serverId,
            'role_id' => $roleId,
            'role_name' => $matched['role_name'],
        ];
    }

    /** @return array{player_id: string, roles: list<array{server_id: string, role_id: string, role_name: string}>} */
    private function account(string $accessToken): array
    {
        /** @var array<string, array{player_id: string, roles: list<array{server_id: string, role_id: string, role_name: string}>}> $accounts */
        $accounts = require dirname(__DIR__, 2) . '/config/accounts.php';

        if (!isset($accounts[$accessToken])) {
            throw new BusinessException(ErrorCode::INVALID_ACCESS_TOKEN, 'Invalid access token', 401);
        }

        return $accounts[$accessToken];
    }
}

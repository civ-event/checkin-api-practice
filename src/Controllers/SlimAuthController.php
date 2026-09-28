<?php

declare(strict_types=1);

namespace Checkin\Controllers;

use Checkin\Auth\JwtService;
use Checkin\Common\ErrorCode;
use Checkin\Exception\BusinessException;
use Checkin\Service\LoginService;
use Symfony\Component\HttpFoundation\Request;

/**
 * 假登录。入参与前端一致：accessToken、roleId、serverId。
 * 成功体由 JsonResultHandler 原样输出，后面取 activityUserToken。
 */
class SlimAuthController
{
    /** POST /api-auth/activity/get-user-role-list 按假 accessToken 列出角色 */
    public function rolesAction(Request $request, LoginService $loginService): array
    {
        $accessToken = $this->requiredString($request, 'accessToken');

        return [
            'roles' => $loginService->roles($accessToken),
        ];
    }

    /** POST /api-auth/activity/join 校验角色属于该玩家，再签发活动 token */
    public function loginAction(Request $request, LoginService $loginService, JwtService $jwt): array
    {
        $accessToken = $this->requiredString($request, 'accessToken');
        $roleId = $this->requiredString($request, 'roleId');
        $serverId = $this->requiredString($request, 'serverId');

        $result = $loginService->login($accessToken, $serverId, $roleId);
        // JWT 里仍写入活动 1。签到和累充实际用的活动 id 来自各自请求，不读这个字段。
        $token = $jwt->encode($result['user_role_primary_id'], 1);

        return [
            'user_info' => [
                'player_id' => $result['player_id'],
                'username' => $result['username'],
            ],
            'active_user_role' => [
                'role_id' => $result['role_id'],
                'role_name' => $result['role_name'],
                'role_level' => $result['role_level'],
                'server_id' => $result['server_id'],
                'server_name' => $result['server_name'],
            ],
            'roles' => $loginService->roles($accessToken),
            'activityUserToken' => $token,
        ];
    }

    private function requiredString(Request $request, string $field): string
    {
        $value = $this->params($request)[$field] ?? '';
        if (!is_string($value) || trim($value) === '') {
            throw new BusinessException(ErrorCode::INVALID_PARAMETER, $field . ' is required');
        }

        return trim($value);
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

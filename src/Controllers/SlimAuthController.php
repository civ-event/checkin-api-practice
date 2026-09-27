<?php

declare(strict_types=1);

namespace Checkin\Controllers;

use Checkin\Auth\JwtService;
use Checkin\Common\ErrorCode;
use Checkin\Exception\BusinessException;
use Checkin\Service\LoginService;
use Symfony\Component\HttpFoundation\Request;

/**
 * 新内核上的假登录。字段仍是 access_token、server_id、role_id。
 * 成功数组由 JsonResultHandler 包装，本类只返回 data 里的内容。，和旧登录一样，后面取 data.token 不用改。
 */
class SlimAuthController
{
    /** POST /front.php/auth/roles 按假 access_token 列出角色 */
    public function rolesAction(Request $request, LoginService $loginService): array
    {
        $body = $this->body($request);
        $accessToken = $body['access_token'] ?? '';
        if (!is_string($accessToken) || trim($accessToken) === '') {
            throw new BusinessException(ErrorCode::INVALID_PARAMETER, 'access_token is required');
        }

        // 外层 {code, message, data} 由 JsonResultHandler 包
        return [
            'roles' => $loginService->roles(trim($accessToken)),
        ];
    }

    /** POST /front.php/auth/login 校验角色属于该玩家，再签发签到活动 1 的 JWT */
    public function loginAction(Request $request, LoginService $loginService, JwtService $jwt): array
    {
        $body = $this->body($request);
        foreach (['access_token', 'server_id', 'role_id'] as $key) {
            if (!isset($body[$key]) || !is_string($body[$key]) || trim($body[$key]) === '') {
                throw new BusinessException(ErrorCode::INVALID_PARAMETER, $key . ' is required');
            }
        }

        $result = $loginService->login(
            trim($body['access_token']),
            trim($body['server_id']),
            trim($body['role_id']),
        );
        // JWT 里仍写入活动 1。签到和累充实际用的活动 id 来自各自请求，不读这个字段。
        $token = $jwt->encode($result['user_role_primary_id'], 1);

        // 外层包装交给 JsonResultHandler，这里只放 token 和角色
        return [
            'token' => $token,
            'expires_in' => (int) (getenv('JWT_TTL') ?: 3600),
            'role' => $result,
        ];
    }

    /** @return array<string, mixed> */
    private function body(Request $request): array
    {
        $body = json_decode($request->getContent(), true);

        return is_array($body) ? $body : [];
    }
}

<?php

declare(strict_types=1);

namespace Checkin\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 控制器返回数组时，原样编成 JSON，不再套 {code, message, data}。
 * 探测接口若已经返回 Response，则原样送出。
 * JSON 不转义中文，角色名应显示为「角色乙」，而不是 \u89d2\u8272\u4e59。
 */
final class JsonResultHandler
{
    public function __invoke(mixed $result, Request $request): ?Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result)) {
            $response = new JsonResponse($result);
            $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $response;
        }

        return null;
    }
}
